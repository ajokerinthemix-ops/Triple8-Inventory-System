<?php
/**
 * =====================================================================================
 * TRIPLE 8 RETAIL STORE - VOICE BIOMETRICS & COMMAND AUTHENTICATION API
 * =====================================================================================
 * Handles:
 * 1. Voiceprint registration & profile storage (pitch, spectral timbre, centroid)
 * 2. Real-time voice command speaker verification & gatekeeping
 * 3. Role-based command authorization checks
 * 4. Security audit logging for authenticated and rejected foreign voice commands
 * =====================================================================================
 */

if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permissions.php';

// Active authenticated session is strictly required
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'code' => 401,
        'message' => 'Unauthorized. Active session required for voice biometric security.'
    ]);
    exit();
}

$currentUserId = (int)getUserId();
$currentUsername = getUsername() ?? 'User';
$currentUserRole = strtolower(getUserRole() ?? 'cashier');
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Read JSON input or POST/GET parameters
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

$action = $_GET['action'] ?? ($jsonInput['action'] ?? ($_POST['action'] ?? ''));

switch ($action) {

    // ---------------------------------------------------------------------------------
    // 1. GET VOICEPRINT PROFILE
    // ---------------------------------------------------------------------------------
    case 'get_profile':
        $targetUserId = $currentUserId;
        // Admins can query voiceprint of a specific user
        if (isset($_GET['user_id']) && $currentUserRole === 'admin') {
            $targetUserId = (int)$_GET['user_id'];
        }

        $stmt = $conn->prepare("
            SELECT v.*, u.username, u.role as user_role 
            FROM user_voiceprints v
            JOIN users u ON v.user_id = u.id
            WHERE v.user_id = ? AND v.is_active = 1
            LIMIT 1
        ");
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        $result = $stmt->get_result();
        $profile = $result->fetch_assoc();
        $stmt->close();

        if ($profile) {
            $spectralBands = json_decode($profile['spectral_bands'], true) ?: [];
            echo json_encode([
                'status' => 'success',
                'has_voiceprint' => true,
                'profile' => [
                    'id' => (int)$profile['id'],
                    'user_id' => (int)$profile['user_id'],
                    'username' => $profile['username'],
                    'user_role' => $profile['user_role'],
                    'voice_label' => $profile['voice_label'],
                    'pitch_mean' => (float)$profile['pitch_mean'],
                    'pitch_std' => (float)$profile['pitch_std'],
                    'pitch_tolerance' => (float)$profile['pitch_tolerance'],
                    'centroid_mean' => (float)$profile['centroid_mean'],
                    'bands' => $spectralBands,
                    'sample_count' => (int)$profile['sample_count'],
                    'passphrase' => $profile['passphrase'],
                    'auth_level' => $profile['auth_level'],
                    'enrolled_at' => $profile['enrolled_at'],
                    'updated_at' => $profile['updated_at']
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'success',
                'has_voiceprint' => false,
                'message' => 'No active voiceprint registered for this user.',
                'user' => [
                    'id' => $currentUserId,
                    'username' => $currentUsername,
                    'role' => $currentUserRole
                ]
            ]);
        }
        exit();

    // ---------------------------------------------------------------------------------
    // 2. ENROLL / REGISTER VOICEPRINT PROFILE
    // ---------------------------------------------------------------------------------
    case 'enroll':
        $data = $jsonInput ?: $_POST;

        $targetUserId = $currentUserId;
        if (isset($data['target_user_id']) && $currentUserRole === 'admin') {
            $targetUserId = (int)$data['target_user_id'];
        }

        $voiceLabel = trim($data['voice_label'] ?? 'Primary Voiceprint');
        $pitchMean = (float)($data['pitch_mean'] ?? 0);
        $pitchStd = (float)($data['pitch_std'] ?? 15);
        $pitchTolerance = (float)($data['pitch_tolerance'] ?? 40);
        $centroidMean = (float)($data['centroid_mean'] ?? 0);
        $sampleCount = (int)($data['sample_count'] ?? 1);
        $passphrase = trim($data['passphrase'] ?? 'Authorize Triple Eight Access');
        $authLevel = in_array($data['auth_level'] ?? '', ['standard', 'elevated', 'strict']) ? $data['auth_level'] : 'strict';

        // Validate spectral bands array
        $bands = $data['bands'] ?? ($data['spectral_bands'] ?? []);
        if (is_string($bands)) {
            $bandsDecoded = json_decode($bands, true);
            $bands = is_array($bandsDecoded) ? $bandsDecoded : [];
        }
        if (!is_array($bands) || count($bands) < 4) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid acoustic vector data. At least 4 spectral formant bands required.'
            ]);
            exit();
        }

        // Normalize spectral bands to ensure valid float values
        $cleanBands = array_map(function ($val) {
            return round((float)$val, 4);
        }, $bands);
        $bandsJson = json_encode($cleanBands);

        // Sanity checks on pitch baseline (human vocal range roughly 65Hz - 450Hz)
        if ($pitchMean < 50 || $pitchMean > 500) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Audio calibration detected anomalous pitch (' . round($pitchMean) . ' Hz). Please speak clearly into the microphone and retry.'
            ]);
            exit();
        }

        // Insert or Update profile in user_voiceprints
        $stmt = $conn->prepare("
            INSERT INTO user_voiceprints 
                (user_id, voice_label, pitch_mean, pitch_std, pitch_tolerance, centroid_mean, spectral_bands, sample_count, passphrase, auth_level, is_active, updated_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                voice_label = VALUES(voice_label),
                pitch_mean = VALUES(pitch_mean),
                pitch_std = VALUES(pitch_std),
                pitch_tolerance = VALUES(pitch_tolerance),
                centroid_mean = VALUES(centroid_mean),
                spectral_bands = VALUES(spectral_bands),
                sample_count = VALUES(sample_count),
                passphrase = VALUES(passphrase),
                auth_level = VALUES(auth_level),
                is_active = 1,
                updated_at = NOW()
        ");

        $stmt->bind_param(
            "isddddsiss",
            $targetUserId,
            $voiceLabel,
            $pitchMean,
            $pitchStd,
            $pitchTolerance,
            $centroidMean,
            $bandsJson,
            $sampleCount,
            $passphrase,
            $authLevel
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Log security audit event
            logAccessEvent(
                'VOICEPRINT_ENROLLED',
                'user_voiceprints',
                $targetUserId,
                "Voice biometric registration completed for user #{$targetUserId} ({$currentUsername}) with pitch {$pitchMean}Hz",
                'success',
                $currentUserId
            );

            echo json_encode([
                'status' => 'success',
                'message' => 'Voiceprint successfully enrolled and saved to secure database.',
                'profile' => [
                    'user_id' => $targetUserId,
                    'pitch_mean' => $pitchMean,
                    'pitch_std' => $pitchStd,
                    'pitch_tolerance' => $pitchTolerance,
                    'centroid_mean' => $centroidMean,
                    'sample_count' => $sampleCount,
                    'bands' => $cleanBands,
                    'auth_level' => $authLevel,
                    'enrolled_at' => date('Y-m-d H:i:s')
                ]
            ]);
        } else {
            $err = $stmt->error;
            $stmt->close();
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to save voiceprint to database: ' . $err
            ]);
        }
        exit();

    // ---------------------------------------------------------------------------------
    // 3. VERIFY VOICE COMMAND & ROLE-BASED ACCESS GATEKEEPER
    // ---------------------------------------------------------------------------------
    case 'verify_command':
        $data = $jsonInput ?: $_POST;

        $commandText = trim($data['command'] ?? ($data['command_text'] ?? ''));
        $confidence = (int)($data['confidence'] ?? 0);
        $speakerVerified = !empty($data['is_match']) ? 1 : 0;
        $reason = trim($data['reason'] ?? '');

        if (empty($commandText)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Command text is required.']);
            exit();
        }

        // Rule A: Check Speaker Verification (Biometric match)
        if (!$speakerVerified) {
            $execStatus = 'blocked_unauthenticated';
            $denialReason = !empty($reason) ? $reason : "Unrecognized foreign speaker (confidence {$confidence}%).";

            // Log attempt to voice_command_audit
            $logStmt = $conn->prepare("
                INSERT INTO voice_command_audit 
                    (user_id, username, user_role, command_text, recognition_confidence, speaker_verified, execution_status, reason, ip_address)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)
            ");
            $logStmt->bind_param("isssisss", $currentUserId, $currentUsername, $currentUserRole, $commandText, $confidence, $execStatus, $denialReason, $ipAddress);
            $logStmt->execute();
            $logStmt->close();

            // Also log to primary access audit trail
            logAccessEvent('VOICE_COMMAND_REJECTED', 'voice_assistant', null, "Foreign / unauthenticated voice rejected: \"{$commandText}\" ({$confidence}%)", 'denied', $currentUserId);

            echo json_encode([
                'status' => 'blocked',
                'authorized' => false,
                'execution_status' => $execStatus,
                'speaker_verified' => false,
                'confidence' => $confidence,
                'message' => 'Voice authentication failed. Speaker not recognized.',
                'reason' => $denialReason
            ]);
            exit();
        }

        // Rule B: Role-Based Authorization for Command Scope
        $cmdLower = strtolower($commandText);
        $roleAuthorized = true;
        $unauthorizedReason = '';

        // Scope 1: Administrator-Only Commands (System settings, Access Control, Users, Backup & Restore)
        $adminKeywords = ['backup', 'restore', 'manage users', 'user management', 'access control', 'rbac', 'roles and permissions', 'store settings', 'system configuration'];
        foreach ($adminKeywords as $kw) {
            if (strpos($cmdLower, $kw) !== false && $currentUserRole !== 'admin') {
                $roleAuthorized = false;
                $unauthorizedReason = "Administrator privileges required to execute: \"{$kw}\"";
                break;
            }
        }

        // Scope 2: Management/Inventory Commands (Adjust inventory, add categories, add products)
        if ($roleAuthorized) {
            $inventoryAdminKeywords = ['add product', 'create product', 'edit product', 'delete product', 'add category', 'delete category'];
            foreach ($inventoryAdminKeywords as $kw) {
                if (strpos($cmdLower, $kw) !== false && !in_array($currentUserRole, ['admin', 'manager', 'inventory_staff'])) {
                    $roleAuthorized = false;
                    $unauthorizedReason = "Role '{$currentUserRole}' is not permitted to perform inventory modifications.";
                    break;
                }
            }
        }

        if (!$roleAuthorized) {
            $execStatus = 'blocked_unauthorized';

            $logStmt = $conn->prepare("
                INSERT INTO voice_command_audit 
                    (user_id, username, user_role, command_text, recognition_confidence, speaker_verified, execution_status, reason, ip_address)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
            ");
            $logStmt->bind_param("isssisss", $currentUserId, $currentUsername, $currentUserRole, $commandText, $confidence, $execStatus, $unauthorizedReason, $ipAddress);
            $logStmt->execute();
            $logStmt->close();

            logAccessEvent('VOICE_COMMAND_DENIED', 'voice_assistant', null, "Unauthorized command by role {$currentUserRole}: \"{$commandText}\"", 'denied', $currentUserId);

            echo json_encode([
                'status' => 'blocked',
                'authorized' => false,
                'execution_status' => $execStatus,
                'speaker_verified' => true,
                'confidence' => $confidence,
                'message' => "Access denied. Your role ({$currentUserRole}) is not authorized for this command.",
                'reason' => $unauthorizedReason
            ]);
            exit();
        }

        // Rule C: Command is Authenticated AND Authorized!
        $execStatus = 'executed';
        $successReason = "Speaker verified ({$confidence}%) as authorized {$currentUserRole}.";

        $logStmt = $conn->prepare("
            INSERT INTO voice_command_audit 
                (user_id, username, user_role, command_text, recognition_confidence, speaker_verified, execution_status, reason, ip_address)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?)
        ");
        $logStmt->bind_param("isssisss", $currentUserId, $currentUsername, $currentUserRole, $commandText, $confidence, $execStatus, $successReason, $ipAddress);
        $logStmt->execute();
        $auditId = $conn->insert_id;
        $logStmt->close();

        echo json_encode([
            'status' => 'success',
            'authorized' => true,
            'execution_status' => 'executed',
            'speaker_verified' => true,
            'confidence' => $confidence,
            'audit_id' => $auditId,
            'user' => [
                'id' => $currentUserId,
                'username' => $currentUsername,
                'role' => $currentUserRole
            ],
            'command' => $commandText,
            'message' => "Authenticated voice command authorized for {$currentUsername}."
        ]);
        exit();

    // ---------------------------------------------------------------------------------
    // 4. DELETE / REVOKE VOICEPRINT PROFILE
    // ---------------------------------------------------------------------------------
    case 'delete_profile':
        $targetUserId = $currentUserId;
        $data = $jsonInput ?: $_POST;
        if (isset($data['user_id']) && $currentUserRole === 'admin') {
            $targetUserId = (int)$data['user_id'];
        }

        $stmt = $conn->prepare("DELETE FROM user_voiceprints WHERE user_id = ?");
        $stmt->bind_param("i", $targetUserId);
        $stmt->execute();
        $stmt->close();

        logAccessEvent('VOICEPRINT_REVOKED', 'user_voiceprints', $targetUserId, "Voiceprint credentials revoked for user #{$targetUserId}", 'warning', $currentUserId);

        echo json_encode([
            'status' => 'success',
            'message' => 'Voiceprint profile successfully revoked.'
        ]);
        exit();

    // ---------------------------------------------------------------------------------
    // 5. FETCH RECENT VOICE AUDIT TRAIL
    // ---------------------------------------------------------------------------------
    case 'audit_logs':
        $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
        $whereSql = "";
        $params = [];
        $types = "";

        // If not admin, can only view own voice audit logs
        if ($currentUserRole !== 'admin') {
            $whereSql = "WHERE user_id = ?";
            $params[] = $currentUserId;
            $types .= "i";
        }

        $sql = "SELECT * FROM voice_command_audit {$whereSql} ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        $types .= "i";

        $stmt = $conn->prepare($sql);
        if (!empty($types)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $logs = [];
        while ($row = $res->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();

        echo json_encode([
            'status' => 'success',
            'count' => count($logs),
            'logs' => $logs
        ]);
        exit();

    // ---------------------------------------------------------------------------------
    // 6. LIST ALL USERS WITH VOICE REGISTRATION STATUS (ADMIN / MANAGER)
    // ---------------------------------------------------------------------------------
    case 'users_status':
        if (!in_array($currentUserRole, ['admin', 'manager'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permission denied.']);
            exit();
        }

        $sql = "
            SELECT 
                u.id, u.username, u.email, u.role, u.status,
                v.id as voiceprint_id, v.voice_label, v.pitch_mean, v.sample_count,
                v.auth_level, v.is_active as voice_active, v.enrolled_at as voice_enrolled_at
            FROM users u
            LEFT JOIN user_voiceprints v ON u.id = v.user_id AND v.is_active = 1
            ORDER BY u.role ASC, u.username ASC
        ";
        $res = $conn->query($sql);
        $userList = [];
        while ($row = $res->fetch_assoc()) {
            $userList[] = [
                'id' => (int)$row['id'],
                'username' => $row['username'],
                'email' => $row['email'],
                'role' => $row['role'],
                'status' => $row['status'],
                'has_voiceprint' => !empty($row['voiceprint_id']),
                'voice_label' => $row['voice_label'],
                'pitch_mean' => $row['pitch_mean'] !== null ? (float)$row['pitch_mean'] : null,
                'auth_level' => $row['auth_level'],
                'voice_enrolled_at' => $row['voice_enrolled_at']
            ];
        }

        echo json_encode([
            'status' => 'success',
            'users' => $userList
        ]);
        exit();

    default:
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Unknown action: ' . htmlspecialchars($action)
        ]);
        exit();
}
