<?php
/**
 * Automated Cron Backup Endpoint
 * Triple8 Supermarket POS & Retail System
 *
 * Usage via CLI or Webhook:
 * GET/POST /api/cron_backup.php?token=YOUR_CRON_TOKEN
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/backup_engine.php';

$autoSettings = BackupEngine::getAutoBackupSettings();
$validToken = $autoSettings['backup_cron_token'] ?? '';
$requestToken = $_GET['token'] ?? $_POST['token'] ?? '';

// Allow CLI execution directly without token check
$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    if (empty($validToken) || empty($requestToken) || !hash_equals($validToken, $requestToken)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Unauthorized: Invalid or missing backup cron token.'
        ]);
        exit();
    }
}

$force = isset($_GET['force']) && $_GET['force'] == '1';
$result = BackupEngine::checkAndRunAutoBackup($force);

if ($result === null) {
    echo json_encode([
        'success' => true,
        'executed' => false,
        'message' => 'Auto backup is not due yet. Set force=1 to run immediately.'
    ]);
} elseif ($result['success']) {
    echo json_encode([
        'success' => true,
        'executed' => true,
        'message' => $result['message'],
        'filename' => $result['filename'],
        'duration' => $result['duration']
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'executed' => true,
        'message' => $result['message']
    ]);
}
