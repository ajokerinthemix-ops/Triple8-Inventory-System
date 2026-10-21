<?php
// Start secure session with hardened parameters
if (session_status() === PHP_SESSION_NONE) {
    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    
    // Configure secure session settings
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_strict_mode', '1');
    if ($isSecure) {
        ini_set('session.cookie_secure', '1');
    }
    
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    
    session_start();
}

// Session timeout configuration (seconds)
define('SESSION_IDLE_TIMEOUT', 1800);      // 30 minutes
define('SESSION_ABSOLUTE_TIMEOUT', 43200); // 12 hours

// Security headers
function setSecurityHeaders() {
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
}

setSecurityHeaders();

// Validate session hijacking / fingerprinting
function validateSessionFingerprint() {
    if (!isset($_SESSION['user_id'])) return true;

    $currentUa = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    if (isset($_SESSION['UA_FINGERPRINT']) && !hash_equals($_SESSION['UA_FINGERPRINT'], $currentUa)) {
        logAccessEvent('SESSION_HIJACK_ATTEMPT', 'session', $_SESSION['user_id'] ?? null, 'User Agent fingerprint mismatch detected', 'denied');
        logout('session_expired');
        exit();
    }

    return true;
}

validateSessionFingerprint();

// Enforce session timeout (idle, absolute, and periodic rotation)
function enforceSessionTimeout() {
    if (!isset($_SESSION['user_id'])) return;
    
    $idleLimit = SESSION_IDLE_TIMEOUT;
    $absoluteLimit = SESSION_ABSOLUTE_TIMEOUT;
    
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $idleLimit) {
        logout('session_expired');
        exit();
    }
    
    if (isset($_SESSION['CREATED_AT']) && (time() - $_SESSION['CREATED_AT']) > $absoluteLimit) {
        logout('session_expired');
        exit();
    }

    // Periodic session ID rotation every 15 minutes of activity
    if (!isset($_SESSION['LAST_ROTATION'])) {
        $_SESSION['LAST_ROTATION'] = time();
    } elseif ((time() - $_SESSION['LAST_ROTATION']) > 900) {
        session_regenerate_id(true);
        $_SESSION['LAST_ROTATION'] = time();
    }
    
    $_SESSION['LAST_ACTIVITY'] = time();
}

enforceSessionTimeout();

// Enforce email verification across protected routes
function enforceEmailVerification() {
    if (!isLoggedIn()) return;

    // If user's email verification status is unverified (0)
    if (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === 0) {
        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $currentFile = basename($scriptName);
        $exemptPages = [
            'verify_email.php',
            'resend_verification.php',
            'logout.php',
            'login.php',
            'change_password.php'
        ];

        if (!in_array($currentFile, $exemptPages, true)) {
            header("Location: " . getBasePath() . "verify_email.php");
            exit();
        }
    }
}

enforceEmailVerification();

// CSRF token management
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || (time() - $_SESSION['csrf_token_time']) > 1800) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Get current user role
function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

// Get current user ID
function getUserId() {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

// Get current username
function getUsername() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : null;
}

// Password strength validation helper
function validatePasswordStrength($password) {
    $errors = [];
    if (strlen($password) < 8) {
        $errors[] = 'at least 8 characters';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'at least one uppercase letter';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'at least one lowercase letter';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'at least one number';
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'at least one special character';
    }
    return $errors;
}

// Rehash password if algorithm/options changed
function rehashPasswordIfNeeded($conn, $userId, $plainPassword, $existingHash) {
    if (password_needs_rehash($existingHash, PASSWORD_DEFAULT)) {
        $newHash = password_hash($plainPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $newHash, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// Compute base path for redirects from any subdirectory
function getBasePath() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $depth = substr_count($dir, '/');
    if ($depth <= 1) return './';
    return str_repeat('../', $depth - 1);
}

// Redirect based on role
function redirectToDashboard() {
    $role = getUserRole();
    if (!$role) {
        header("Location: " . getBasePath() . "login.php?error=no_role");
        exit();
    }
    
    // Check if role has a configured dashboard in DB
    $dashboard = null;
    if (isset($GLOBALS['conn'])) {
        $stmt = $GLOBALS['conn']->prepare("SELECT default_dashboard FROM roles WHERE role_name = ?");
        if ($stmt) {
            $stmt->bind_param("s", $role);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $dashboard = $row['default_dashboard'];
            }
            $stmt->close();
        }
    }
    
    // Validate that the target dashboard file actually exists on the filesystem
    $baseDir = realpath(__DIR__ . '/..');
    if (!$dashboard || !file_exists($baseDir . '/' . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $dashboard), DIRECTORY_SEPARATOR))) {
        switch($role) {
            case 'cashier':
                $dashboard = 'cashier/pos.php';
                break;
            case 'inventory_clerk':
                $dashboard = 'admin/inventory.php';
                break;
            case 'admin':
            case 'manager':
            default:
                $dashboard = 'admin/dashboard.php';
                break;
        }
    }
    
    header("Location: " . getBasePath() . $dashboard);
    exit();
}

// Check login attempts rate limit: returns true if login is blocked
// Enforces multi-dimensional rate limiting (IP-based and Username-based)
function checkLoginRateLimit($conn, $username, $ip) {
    if (!$conn) {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) {
            @include_once __DIR__ . '/db.php';
            $conn = $GLOBALS['conn'] ?? null;
        }
    }
    if (!$conn || $conn->connect_error) return false;

    // Small progressive delay to throttle high-frequency automated brute force
    usleep(100000); // 100ms

    $maxAttemptsPerUser = 5;
    $maxAttemptsPerIp = 10;
    $windowSeconds = 900; // 15 minutes
    
    // 1. Check failed attempts for this username across all IPs (targeted account brute-force protection)
    $stmtUser = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                                WHERE username = ? AND success = 0 
                                AND attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
    if ($stmtUser) {
        $stmtUser->bind_param("si", $username, $windowSeconds);
        $stmtUser->execute();
        $userResult = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        if (isset($userResult['attempts']) && $userResult['attempts'] >= $maxAttemptsPerUser) {
            return true;
        }
    }

    // 2. Check failed attempts from this IP across all usernames (credential stuffing / distributed spray protection)
    $stmtIp = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                              WHERE ip_address = ? AND success = 0 
                              AND attempt_time >= DATE_SUB(NOW(), INTERVAL ? SECOND)");
    if ($stmtIp) {
        $stmtIp->bind_param("si", $ip, $windowSeconds);
        $stmtIp->execute();
        $ipResult = $stmtIp->get_result()->fetch_assoc();
        $stmtIp->close();
        if (isset($ipResult['attempts']) && $ipResult['attempts'] >= $maxAttemptsPerIp) {
            return true;
        }
    }
    
    return false;
}

// Record a login attempt
function recordLoginAttempt($conn, $username, $ip, $success) {
    if (!$conn) {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) {
            @include_once __DIR__ . '/db.php';
            $conn = $GLOBALS['conn'] ?? null;
        }
    }
    if (!$conn || $conn->connect_error) return;

    $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)");
    if ($stmt === false) return;
    $successInt = $success ? 1 : 0;
    $stmt->bind_param("ssi", $username, $ip, $successInt);
    $stmt->execute();
    $stmt->close();
}

// Check role-based access
function checkRole($allowedRoles) {
    if (!isLoggedIn()) {
        header("Location: " . getBasePath() . "login.php?error=not_logged_in");
        exit();
    }
    
    $currentRole = getUserRole();
    if (!in_array($currentRole, $allowedRoles)) {
        logAccessEvent('ACCESS_DENIED', 'role_check', $currentRole, 'Denied access to role-protected page', 'denied');
        header("Location: " . getBasePath() . "unauthorized.php");
        exit();
    }
}

// ==========================================
// ACCESS CONTROL & RBAC / ACL CORE ENGINE
// ==========================================

// Log access / security audit events
function logAccessEvent($action, $targetType = null, $targetId = null, $details = null, $status = 'success', $userId = null) {
    try {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) {
            @include_once __DIR__ . '/db.php';
            $conn = $GLOBALS['conn'] ?? null;
        }
        if (!$conn || $conn->connect_error) return;

        $uid = $userId !== null ? (int)$userId : getUserId();
        $username = getUsername();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        $stmt = $conn->prepare("
            INSERT INTO access_audit_logs (user_id, username, action, target_type, target_id, details, ip_address, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        if ($stmt) {
            $detailsStr = is_array($details) ? json_encode($details) : (string)$details;
            $targetIdStr = $targetId !== null ? (string)$targetId : null;
            $stmt->bind_param("isssssss", $uid, $username, $action, $targetType, $targetIdStr, $detailsStr, $ip, $status);
            $stmt->execute();
            $stmt->close();
        }
    } catch (Exception $e) {
        error_log('Access audit logging error: ' . $e->getMessage());
    }
}

// Load Role Permissions Map from Database
function loadDbPermissionMap() {
    if (array_key_exists('_rbac_db_map', $GLOBALS) && $GLOBALS['_rbac_db_map'] !== null) {
        return $GLOBALS['_rbac_db_map'];
    }

    $GLOBALS['_rbac_db_map'] = null;

    try {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) {
            @include_once __DIR__ . '/db.php';
            $conn = $GLOBALS['conn'] ?? null;
        }

        if ($conn && !$conn->connect_error) {
            $result = $conn->query("
                SELECT r.role, p.name
                FROM role_permissions r
                JOIN permissions p ON r.permission_id = p.id
            ");

            if ($result) {
                $map = [];
                while ($row = $result->fetch_assoc()) {
                    $map[$row['name']][] = $row['role'];
                }
                $GLOBALS['_rbac_db_map'] = $map;
            }
        }
    } catch (Exception $e) {
        error_log('RBAC database load failed: ' . $e->getMessage());
    }

    return $GLOBALS['_rbac_db_map'];
}

// Load User-Specific Overrides from Database
function loadUserOverrides($userId) {
    $userId = (int)$userId;
    if (!$userId) return [];

    if (isset($GLOBALS['_user_overrides_map'][$userId])) {
        return $GLOBALS['_user_overrides_map'][$userId];
    }

    $overrides = [];
    try {
        $conn = $GLOBALS['conn'] ?? null;
        if (!$conn) {
            @include_once __DIR__ . '/db.php';
            $conn = $GLOBALS['conn'] ?? null;
        }

        if ($conn && !$conn->connect_error) {
            $stmt = $conn->prepare("
                SELECT p.name, up.is_granted
                FROM user_permissions up
                JOIN permissions p ON up.permission_id = p.id
                WHERE up.user_id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($row = $res->fetch_assoc()) {
                    $overrides[$row['name']] = (int)$row['is_granted'];
                }
                $stmt->close();
            }
        }
    } catch (Exception $e) {
        error_log('User overrides load failed: ' . $e->getMessage());
    }

    if (!isset($GLOBALS['_user_overrides_map'])) {
        $GLOBALS['_user_overrides_map'] = [];
    }
    $GLOBALS['_user_overrides_map'][$userId] = $overrides;
    return $overrides;
}

// Clear cached permission maps
function clearPermissionCache() {
    unset($GLOBALS['_rbac_db_map']);
    unset($GLOBALS['_user_overrides_map']);
}

// Get static fallback permission map
function getStaticPermissionMap() {
    static $staticMap = null;
    if ($staticMap === null) {
        $map = include __DIR__ . '/permissions.php';
        $staticMap = $map['permissions'] ?? [];
    }
    return $staticMap;
}

// Check if a user has a specific permission
function hasPermission($permission, $userId = null) {
    if ($userId === null) {
        if (!isLoggedIn()) return false;
        $userId = getUserId();
        $role = getUserRole();
    } else {
        $userId = (int)$userId;
        $role = null;
        // Lookup role of specified user if needed
        $conn = $GLOBALS['conn'] ?? null;
        if ($conn && !$conn->connect_error) {
            $stmt = $conn->prepare("SELECT role, status FROM users WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $res = $stmt->get_result();
                if ($row = $res->fetch_assoc()) {
                    if ($row['status'] === 'suspended' || $row['status'] === 'inactive') {
                        return false;
                    }
                    $role = $row['role'];
                }
                $stmt->close();
            }
        }
    }

    if (!$role) return false;

    // 1. Check user-level direct overrides first (highest priority)
    $overrides = loadUserOverrides($userId);
    if (isset($overrides[$permission])) {
        return $overrides[$permission] === 1;
    }

    // 2. Admin super-user has access to everything by default unless explicitly revoked above
    if ($role === 'admin') {
        return true;
    }

    // 3. Check role-permission mapping from database
    $dbMap = loadDbPermissionMap();
    if ($dbMap !== null && !empty($dbMap)) {
        return isset($dbMap[$permission]) && in_array($role, $dbMap[$permission], true);
    }

    // 4. Fallback to static permissions file
    $staticMap = getStaticPermissionMap();
    return isset($staticMap[$permission]) && in_array($role, $staticMap[$permission], true);
}

// Check if user has at least one of the given permissions
function hasAnyPermission(array $permissions, $userId = null) {
    foreach ($permissions as $permission) {
        if (hasPermission($permission, $userId)) {
            return true;
        }
    }
    return false;
}

// Check if user has all of the given permissions
function hasAllPermissions(array $permissions, $userId = null) {
    foreach ($permissions as $permission) {
        if (!hasPermission($permission, $userId)) {
            return false;
        }
    }
    return true;
}

// Require a permission or redirect to unauthorized page with audit log
function requirePermission($permission) {
    if (!isLoggedIn()) {
        header("Location: " . getBasePath() . "login.php?error=not_logged_in");
        exit();
    }

    if (!hasPermission($permission)) {
        logAccessEvent('ACCESS_DENIED', 'permission', $permission, 'User denied required permission: ' . $permission, 'denied');
        header("Location: " . getBasePath() . "unauthorized.php?denied_permission=" . urlencode($permission));
        exit();
    }
}

// Require at least one permission or redirect
function requireAnyPermission(array $permissions) {
    if (!isLoggedIn()) {
        header("Location: " . getBasePath() . "login.php?error=not_logged_in");
        exit();
    }

    if (!hasAnyPermission($permissions)) {
        $permList = implode(', ', $permissions);
        logAccessEvent('ACCESS_DENIED', 'permission_any', $permList, 'User lacks any of permissions: ' . $permList, 'denied');
        header("Location: " . getBasePath() . "unauthorized.php?denied_permission=" . urlencode($permList));
        exit();
    }
}

// Get effective permission map for a specific user
function getUserEffectivePermissions($userId) {
    $userId = (int)$userId;
    $conn = $GLOBALS['conn'] ?? null;
    if (!$conn) {
        @include_once __DIR__ . '/db.php';
        $conn = $GLOBALS['conn'] ?? null;
    }

    $effective = [];
    $allPermsResult = $conn ? $conn->query("SELECT id, name, display_name, category, description FROM permissions ORDER BY category, display_name") : null;
    if ($allPermsResult) {
        while ($p = $allPermsResult->fetch_assoc()) {
            $hasPerm = hasPermission($p['name'], $userId);
            $effective[$p['name']] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'display_name' => $p['display_name'],
                'category' => $p['category'],
                'description' => $p['description'],
                'granted' => $hasPerm
            ];
        }
    }
    return $effective;
}

// Logout function
function logout($reason = 'logged_out') {
    if (isLoggedIn()) {
        logAccessEvent('LOGOUT', 'session', getUserId(), 'User logged out (' . $reason . ')', 'success');
    }

    // Unset all session variables
    $_SESSION = [];
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    $redirect = 'login.php';
    if ($reason === 'session_expired') {
        $redirect .= '?error=session_expired';
    } elseif ($reason === 'account_suspended') {
        $redirect .= '?error=account_suspended';
    } else {
        $redirect .= '?logged_out=1';
    }

    header("Location: " . getBasePath() . $redirect);
    exit();
}

// Handle logout action via GET parameter
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout('user_initiated');
}