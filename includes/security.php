<?php
/**
 * Endpoint Security & Web Application Firewall (WAF) Engine
 * For NewLeafBreads / Triple8 Bakery POS & Management System
 */

class EndpointSecurity {
    private static $initialized = false;
    private static $settingsCache = null;
    private static $clientIp = null;

    /**
     * Initialize Endpoint Security protection on current request
     */
    public static function init() {
        if (self::$initialized) return;
        self::$initialized = true;

        self::applySecurityHeaders();
        $ip = self::getClientIp();

        // 1. IP Blacklist & Autoban Check
        if (self::isIpBlocked($ip)) {
            self::respondForbidden('IP_BLACKLISTED', 'Access from your IP address has been blocked due to security policy violations.');
            exit();
        }

        $settings = self::getSettings();
        $wafMode = $settings['waf_mode'] ?? 'active_blocking';

        if ($wafMode === 'disabled') {
            return;
        }

        // 2. Bad Bot / Automated Vulnerability Scanner Check
        if (!empty($settings['block_bad_bots'])) {
            $botThreat = self::detectBadBot();
            if ($botThreat) {
                self::handleThreat('SCANNER_BOT', 25, 'Automated attack/scanner bot detected', $botThreat);
            }
        }

        // 3. WAF Request Payload Threat Inspection (GET, POST, COOKIE, JSON Input)
        $threat = self::inspectPayloads($settings);
        if ($threat) {
            self::handleThreat($threat['type'], $threat['score'], $threat['details'], $threat['snippet']);
        }

        // 4. Adaptive Rate Limiting
        self::checkRateLimit($ip, $settings);
    }

    /**
     * Retrieve Client IP Address safely
     */
    public static function getClientIp() {
        if (self::$clientIp !== null) return self::$clientIp;

        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        // Normalize IPv6 localhost
        if ($ip === '::1') {
            $ip = '127.0.0.1';
        }
        self::$clientIp = $ip;
        return $ip;
    }

    /**
     * Apply enterprise HTTP Security Headers
     */
    public static function applySecurityHeaders() {
        if (headers_sent()) return;

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), camera=(), microphone=(self)');
    }

    /**
     * Get security settings from database or defaults
     */
    public static function getSettings() {
        if (self::$settingsCache !== null) return self::$settingsCache;

        $defaults = [
            'waf_mode' => 'active_blocking',
            'rate_limit_standard' => 120,
            'rate_limit_auth' => 15,
            'rate_limit_api' => 90,
            'autoban_threat_threshold' => 5,
            'autoban_duration_hours' => 24,
            'inspect_sqli' => 1,
            'inspect_xss' => 1,
            'inspect_traversal' => 1,
            'block_bad_bots' => 1
        ];

        try {
            $conn = $GLOBALS['conn'] ?? null;
            if (!$conn) {
                @include_once __DIR__ . '/db.php';
                $conn = $GLOBALS['conn'] ?? null;
            }

            if ($conn && !$conn->connect_error) {
                $res = $conn->query("SELECT setting_key, setting_value FROM security_settings");
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $defaults[$row['setting_key']] = is_numeric($row['setting_value']) ? (int)$row['setting_value'] : $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Security settings load failed: ' . $e->getMessage());
        }

        self::$settingsCache = $defaults;
        return $defaults;
    }

    /**
     * Check if client IP is currently blacklisted or autobanned
     */
    public static function isIpBlocked($ip) {
        // Whitelist always bypasses
        if (self::isIpWhitelisted($ip)) {
            return false;
        }

        try {
            $conn = $GLOBALS['conn'] ?? null;
            if (!$conn || $conn->connect_error) return false;

            $stmt = $conn->prepare("
                SELECT id, rule_type, expires_at 
                FROM security_ip_rules 
                WHERE ip_address = ? AND rule_type IN ('blacklist', 'autoban')
            ");
            if ($stmt) {
                $stmt->bind_param("s", $ip);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    // Check if ban is expired
                    if (!empty($row['expires_at'])) {
                        if (strtotime($row['expires_at']) <= time()) {
                            // Expired, delete rule
                            $delStmt = $conn->prepare("DELETE FROM security_ip_rules WHERE id = ?");
                            if ($delStmt) {
                                $delStmt->bind_param("i", $row['id']);
                                $delStmt->execute();
                                $delStmt->close();
                            }
                            $stmt->close();
                            return false;
                        }
                    }
                    $stmt->close();
                    return true;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            error_log('IP block check failed: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Check if client IP is in whitelist
     */
    public static function isIpWhitelisted($ip) {
        try {
            $conn = $GLOBALS['conn'] ?? null;
            if (!$conn || $conn->connect_error) return false;

            $stmt = $conn->prepare("SELECT id FROM security_ip_rules WHERE ip_address = ? AND rule_type = 'whitelist'");
            if ($stmt) {
                $stmt->bind_param("s", $ip);
                $stmt->execute();
                $isWhitelisted = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                return $isWhitelisted;
            }
        } catch (Exception $e) {}
        return false;
    }

    /**
     * Detect known malicious bots and vulnerability scanners
     */
    private static function detectBadBot() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (empty($userAgent)) {
            return null;
        }

        $badBots = [
            'sqlmap', 'nikto', 'dirbuster', 'gobuster', 'wpscan', 'acunetix',
            'nessus', 'masscan', 'zgrab', 'nmap', 'havij', 'pangolin',
            'qualys', 'burpsuite', 'hydra', 'metasploit'
        ];

        $uaLower = strtolower($userAgent);
        foreach ($badBots as $bot) {
            if (strpos($uaLower, $bot) !== false) {
                return "User-Agent matched scanner signature: $bot ($userAgent)";
            }
        }

        return null;
    }

    /**
     * Inspect request payloads (GET, POST, COOKIES, JSON body) for attack patterns
     */
    private static function inspectPayloads($settings) {
        $inspectSqli = !empty($settings['inspect_sqli']);
        $inspectXss = !empty($settings['inspect_xss']);
        $inspectTraversal = !empty($settings['inspect_traversal']);

        // Collect all inputs to inspect
        $inputs = [];
        foreach ($_GET as $k => $v) $inputs["GET:$k"] = $v;
        foreach ($_POST as $k => $v) $inputs["POST:$k"] = $v;
        foreach ($_COOKIE as $k => $v) $inputs["COOKIE:$k"] = $v;

        // Check raw input for JSON requests
        $rawInput = file_get_contents('php://input');
        if (!empty($rawInput) && strlen($rawInput) < 200000) {
            $inputs['BODY:raw'] = $rawInput;
        }

        foreach ($inputs as $source => $value) {
            $flatValue = is_array($value) ? json_encode($value) : (string)$value;
            if (empty($flatValue) || strlen($flatValue) < 3) continue;

            // 1. Path Traversal & LFI
            if ($inspectTraversal) {
                if (preg_match('/(\.\.[\/\\\\]|\b(etc\/passwd|win\.ini|boot\.ini)\b|php:\/\/(filter|input)|file:\/\/|phar:\/\/)/i', $flatValue, $m)) {
                    return [
                        'type' => 'PATH_TRAVERSAL',
                        'score' => 30,
                        'details' => "Directory traversal pattern '{$m[0]}' detected in $source",
                        'snippet' => substr($flatValue, 0, 150)
                    ];
                }
            }

            // 2. SQL Injection Inspection
            if ($inspectSqli) {
                // High-confidence SQLi signatures
                $sqliPatterns = [
                    '/\bunion\s+(all\s+)?select\b/i' => 'UNION SELECT attack',
                    '/\b(sleep|benchmark)\s*\(\s*\d+\s*\)/i' => 'Blind SQLi Timing attack',
                    '/\b(information_schema|schema_name|table_schema)\b/i' => 'Schema enumeration attempt',
                    '/((\%27)|(\'))\s*(or|and)\s*(\d+\s*=\s*\d+|[\'"][a-z0-9]+[\'"]\s*=\s*[\'"][a-z0-9]+)/i' => 'SQL tautology bypass',
                    '/(\-\-|\#|\/\*).*?(select|insert|update|delete|drop|union)/i' => 'SQL comment injection',
                    '/\b(drop|alter|truncate)\s+table\b/i' => 'DDL destructive injection',
                    '/\binto\s+(outfile|dumpfile)\b/i' => 'SQL file write attempt',
                    '/\bexec(\s|\+)+(sp_|xp_)/i' => 'Stored procedure execution'
                ];

                foreach ($sqliPatterns as $pattern => $desc) {
                    if (preg_match($pattern, $flatValue)) {
                        return [
                            'type' => 'SQL_INJECTION',
                            'score' => 40,
                            'details' => "$desc in $source",
                            'snippet' => substr($flatValue, 0, 150)
                        ];
                    }
                }
            }

            // 3. Cross-Site Scripting (XSS) Inspection
            if ($inspectXss) {
                $xssPatterns = [
                    '/<\s*script\b[^>]*>/i' => '<script> tag injection',
                    '/\bjavascript\s*:\s*[^;\s]+/i' => 'javascript: pseudo-protocol',
                    '/\bon(error|load|click|mouseover|submit|focus|blur)\s*=/i' => 'Inline HTML event handler injection',
                    '/<\s*iframe\b[^>]*>/i' => '<iframe> injection',
                    '/\bdocument\.(cookie|location|write)\b/i' => 'DOM/Cookie access payload',
                    '/<\s*svg\b[^>]*onload\s*=/i' => 'SVG onload XSS injection'
                ];

                foreach ($xssPatterns as $pattern => $desc) {
                    if (preg_match($pattern, $flatValue)) {
                        return [
                            'type' => 'XSS_ATTACK',
                            'score' => 30,
                            'details' => "$desc in $source",
                            'snippet' => substr($flatValue, 0, 150)
                        ];
                    }
                }
            }
        }

        return null;
    }

    /**
     * Adaptive Rate Limiting Engine
     */
    private static function checkRateLimit($ip, $settings) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $endpoint = basename($scriptName);

        // Determine rate limit category
        $isAuthEndpoint = in_array($endpoint, ['login.php', 'change_password.php', 'verify_email.php', 'resend_verification.php']);
        $isApiEndpoint = strpos($scriptName, '/api/') !== false;

        $limit = $settings['rate_limit_standard'] ?? 120;
        if ($isAuthEndpoint) {
            $limit = $settings['rate_limit_auth'] ?? 15;
        } elseif ($isApiEndpoint) {
            $limit = $settings['rate_limit_api'] ?? 90;
        }

        $now = time();
        $windowSize = 60; // 1 minute sliding window
        $endpointHash = md5($ip . ':' . ($isAuthEndpoint ? 'auth' : ($isApiEndpoint ? 'api' : 'standard')));

        try {
            $conn = $GLOBALS['conn'] ?? null;
            if (!$conn || $conn->connect_error) return;

            $stmt = $conn->prepare("
                INSERT INTO security_rate_limits (ip_address, endpoint_hash, request_count, window_start, last_request_time)
                VALUES (?, ?, 1, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    request_count = IF(? - window_start > ?, 1, request_count + 1),
                    window_start = IF(? - window_start > ?, ?, window_start),
                    last_request_time = ?
            ");

            if ($stmt) {
                $stmt->bind_param("ssiiiiiiii", $ip, $endpointHash, $now, $now, $now, $windowSize, $now, $windowSize, $now, $now);
                $stmt->execute();
                $stmt->close();
            }

            // Check current request count
            $checkStmt = $conn->prepare("SELECT request_count FROM security_rate_limits WHERE ip_address = ? AND endpoint_hash = ?");
            if ($checkStmt) {
                $checkStmt->bind_param("ss", $ip, $endpointHash);
                $checkStmt->execute();
                $res = $checkStmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if ($row['request_count'] > $limit) {
                        $checkStmt->close();
                        self::handleThreat('RATE_LIMIT_EXCEEDED', 15, "Rate limit exceeded ($limit req/min)", "Count: {$row['request_count']}");
                    }
                }
                $checkStmt->close();
            }
        } catch (Exception $e) {
            error_log('Rate limit check failed: ' . $e->getMessage());
        }
    }

    /**
     * Handle detected threat (log, autoban evaluation, block response)
     */
    private static function handleThreat($threatType, $score, $details, $snippet = '') {
        $ip = self::getClientIp();
        $endpoint = $_SERVER['SCRIPT_NAME'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $userId = function_exists('getUserId') ? getUserId() : null;

        $settings = self::getSettings();
        $wafMode = $settings['waf_mode'] ?? 'active_blocking';
        $actionTaken = ($wafMode === 'active_blocking') ? 'blocked' : 'logged';

        // 1. Log to security firewall logs
        try {
            $conn = $GLOBALS['conn'] ?? null;
            if (!$conn) {
                @include_once __DIR__ . '/db.php';
                $conn = $GLOBALS['conn'] ?? null;
            }
            if ($conn && !$conn->connect_error) {
                $stmt = $conn->prepare("
                    INSERT INTO security_firewall_logs 
                    (ip_address, user_id, endpoint, http_method, threat_type, threat_score, details, payload_snippet, user_agent, action_taken)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                if ($stmt) {
                    $stmt->bind_param("sisssissss", $ip, $userId, $endpoint, $method, $threatType, $score, $details, $snippet, $userAgent, $actionTaken);
                    $stmt->execute();
                    $incidentId = $conn->insert_id;
                    $stmt->close();
                }

                // 2. Check Autoban criteria (e.g. >= threshold threats in last 15 minutes)
                $threshold = $settings['autoban_threat_threshold'] ?? 5;
                $autobanCheck = $conn->prepare("
                    SELECT COUNT(*) as threat_count 
                    FROM security_firewall_logs 
                    WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                ");
                if ($autobanCheck) {
                    $autobanCheck->bind_param("s", $ip);
                    $autobanCheck->execute();
                    $res = $autobanCheck->get_result()->fetch_assoc();
                    $threatCount = $res['threat_count'] ?? 0;
                    $autobanCheck->close();

                    if ($threatCount >= $threshold && !self::isIpWhitelisted($ip)) {
                        $durationHours = $settings['autoban_duration_hours'] ?? 24;
                        $expiresAt = ($durationHours > 0) ? date('Y-m-d H:i:s', time() + ($durationHours * 3600)) : null;
                        $reason = "Auto-ban triggered: $threatCount security violations recorded in 15 minutes";

                        $banStmt = $conn->prepare("
                            INSERT INTO security_ip_rules (ip_address, rule_type, reason, failed_count, expires_at)
                            VALUES (?, 'autoban', ?, ?, ?)
                            ON DUPLICATE KEY UPDATE reason = VALUES(reason), failed_count = failed_count + 1, expires_at = VALUES(expires_at)
                        ");
                        if ($banStmt) {
                            $banStmt->bind_param("ssis", $ip, $reason, $threatCount, $expiresAt);
                            $banStmt->execute();
                            $banStmt->close();
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log('Security incident logging error: ' . $e->getMessage());
        }

        // If active blocking is enabled, terminate request immediately
        if ($wafMode === 'active_blocking') {
            if ($threatType === 'RATE_LIMIT_EXCEEDED') {
                self::respondTooManyRequests();
            } else {
                self::respondForbidden($threatType, $details);
            }
            exit();
        }
    }

    /**
     * Respond with 403 Forbidden & Security Block Page
     */
    private static function respondForbidden($threatType, $message) {
        if (!headers_sent()) {
            http_response_code(403);
            header('Content-Type: text/html; charset=UTF-8');
        }

        // Return clean JSON for API endpoints
        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            echo json_encode([
                'status' => 'error',
                'code' => 403,
                'error' => 'Request blocked by Endpoint Security Firewall',
                'threat_code' => $threatType,
                'ip' => self::getClientIp(),
                'timestamp' => date('c')
            ]);
            return;
        }

        $ip = htmlspecialchars(self::getClientIp());
        $threatCode = htmlspecialchars($threatType);
        $timeStr = date('Y-m-d H:i:s T');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden | Request Blocked</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #0f172a; color: #f8fafc; font-family: 'Segoe UI', system-ui, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .waf-card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; max-width: 600px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); overflow: hidden; }
        .waf-header { background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%); padding: 24px; text-align: center; }
        .waf-body { padding: 32px; }
        .detail-box { background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; font-family: monospace; font-size: 0.85rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="waf-card">
        <div class="waf-header">
            <i class="fas fa-shield-virus fa-3x text-white mb-2"></i>
            <h3 class="fw-bold text-white mb-0">Request Blocked by Security Firewall</h3>
        </div>
        <div class="waf-body">
            <p class="text-slate-300">Your request triggered our automated endpoint threat protection rules and was denied to safeguard the system.</p>
            <div class="detail-box mb-4">
                <div><strong>Reason:</strong> <span class="text-danger"><?php echo $threatCode; ?></span></div>
                <div><strong>Client IP:</strong> <span class="text-warning"><?php echo $ip; ?></span></div>
                <div><strong>Timestamp:</strong> <?php echo $timeStr; ?></div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <a href="javascript:history.back()" class="btn btn-outline-light"><i class="fas fa-arrow-left me-1"></i> Return</a>
                <small class="text-muted">Triple8 Endpoint Shield &bull; v2.0</small>
            </div>
        </div>
    </div>
</body>
</html>
<?php
    }

    /**
     * Respond with 429 Too Many Requests
     */
    private static function respondTooManyRequests() {
        if (!headers_sent()) {
            http_response_code(429);
            header('Retry-After: 60');
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
            echo json_encode([
                'status' => 'error',
                'code' => 429,
                'error' => 'Rate limit exceeded. Please slow down your requests.',
                'retry_after' => 60
            ]);
            return;
        }

        echo "<!DOCTYPE html><html><head><title>429 Too Many Requests</title><style>body{font-family:sans-serif;background:#0f172a;color:#fff;text-align:center;padding:80px;}</style></head><body><h1>429 - Rate Limit Exceeded</h1><p>You have submitted too many requests in a short period. Please wait 60 seconds and try again.</p></body></html>";
    }

    /**
     * Validate an API Key for protected API endpoints
     * Returns key data array on success, false on failure
     */
    public static function validateApiKey($rawApiKey) {
        if (empty($rawApiKey)) return false;

        $keyHash = hash('sha256', $rawApiKey);
        $clientIp = self::getClientIp();

        try {
            $conn = $GLOBALS['conn'] ?? null;
            if (!$conn) {
                @include_once __DIR__ . '/db.php';
                $conn = $GLOBALS['conn'] ?? null;
            }

            if ($conn && !$conn->connect_error) {
                $stmt = $conn->prepare("
                    SELECT id, key_name, role, allowed_ips, rate_limit_per_min, status, expires_at 
                    FROM api_keys 
                    WHERE api_key_hash = ?
                ");
                if ($stmt) {
                    $stmt->bind_param("s", $keyHash);
                    $stmt->execute();
                    $key = $stmt->get_result()->fetch_assoc();
                    $stmt->close();

                    if (!$key || $key['status'] !== 'active') {
                        return false;
                    }

                    // Check expiration
                    if (!empty($key['expires_at']) && strtotime($key['expires_at']) <= time()) {
                        return false;
                    }

                    // Check IP restrictions if set
                    if (!empty($key['allowed_ips'])) {
                        $allowedList = array_map('trim', explode(',', $key['allowed_ips']));
                        if (!in_array($clientIp, $allowedList)) {
                            return false;
                        }
                    }

                    // Update last used timestamp
                    $upStmt = $conn->prepare("UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
                    if ($upStmt) {
                        $upStmt->bind_param("i", $key['id']);
                        $upStmt->execute();
                        $upStmt->close();
                    }

                    return $key;
                }
            }
        } catch (Exception $e) {
            error_log('API Key validation error: ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Generate a new cryptographically secure API Key
     */
    public static function generateApiKey($name, $role = 'cashier', $rateLimit = 60, $allowedIps = null, $expiresInDays = 365, $createdBy = null) {
        $prefix = 'nlb_' . substr(bin2hex(random_bytes(4)), 0, 6);
        $secret = bin2hex(random_bytes(24));
        $fullKey = $prefix . '_' . $secret;
        $keyHash = hash('sha256', $fullKey);

        $expiresAt = ($expiresInDays > 0) ? date('Y-m-d H:i:s', time() + ($expiresInDays * 86400)) : null;

        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) {
            @include_once __DIR__ . '/db.php';
            $conn = $GLOBALS['conn'] ?? null;
        }

        if (!$conn || $conn->connect_error) {
            return false;
        }

        $stmt = $conn->prepare("
            INSERT INTO api_keys (key_name, api_key_hash, key_prefix, role, allowed_ips, rate_limit_per_min, expires_at, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $stmt->bind_param("sssssisi", $name, $keyHash, $prefix, $role, $allowedIps, $rateLimit, $expiresAt, $createdBy);
            $success = $stmt->execute();
            $newId = $conn->insert_id;
            $stmt->close();

            if ($success) {
                return [
                    'id' => $newId,
                    'key_name' => $name,
                    'api_key' => $fullKey,
                    'key_prefix' => $prefix,
                    'role' => $role,
                    'expires_at' => $expiresAt
                ];
            }
        }

        return false;
    }

    /**
     * Recursive deep input sanitizer
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::sanitize($value);
            }
            return $data;
        }
        return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
    }
}
