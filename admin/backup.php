<?php
$pageTitle = 'Database Backup & Restore';
require_once '../includes/auth.php';
require_once '../includes/permissions.php';
require_once '../includes/db.php';
require_once '../includes/backup_engine.php';

// Ensure user has admin or backup permission
if (!hasPermission('backup') && getUserRole() !== 'admin') {
    header("Location: ../unauthorized.php");
    exit();
}

$message = '';
$error = '';

// Check and trigger auto backup if due
BackupEngine::checkAndRunAutoBackup();

// Handle Download Request
if (isset($_GET['action']) && $_GET['action'] === 'download' && !empty($_GET['file'])) {
    $filename = basename($_GET['file']);
    $backupDir = BackupEngine::getBackupDirectory();
    $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

    if (file_exists($filepath) && is_readable($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    } else {
        $error = "Backup file '{$filename}' not found or cannot be downloaded.";
    }
}

// Handle Save Auto Backup Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_auto_settings'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed (CSRF token invalid). Please refresh and try again.';
    } else {
        $autoSettingsToSave = [
            'backup_auto_enabled'      => isset($_POST['backup_auto_enabled']) ? '1' : '0',
            'backup_frequency_minutes' => max(15, (int)($_POST['backup_frequency_minutes'] ?? 60)),
            'backup_retention_count'   => max(3, (int)($_POST['backup_retention_count'] ?? 30)),
            'backup_compression'       => isset($_POST['backup_compression']) ? '1' : '0',
        ];

        if (isset($_POST['regenerate_token']) && $_POST['regenerate_token'] == '1') {
            $autoSettingsToSave['backup_cron_token'] = 't8_bkp_' . bin2hex(random_bytes(12));
        }

        if (BackupEngine::saveAutoBackupSettings($autoSettingsToSave)) {
            $message = '<i class="fas fa-check-circle me-1"></i> Automated backup schedule settings saved successfully!';
        } else {
            $error = '<i class="fas fa-exclamation-circle me-1"></i> Error saving auto backup settings.';
        }
    }
}

// Handle Create Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_backup'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed (CSRF token invalid). Please refresh and try again.';
    } else {
        $notes = trim($_POST['notes'] ?? '');
        $compress = isset($_POST['compress']) && $_POST['compress'] == '1';
        $result = BackupEngine::createBackup('manual', $notes, $compress);

        if ($result['success']) {
            $message = '<i class="fas fa-check-circle me-1"></i> ' . htmlspecialchars($result['message']);
        } else {
            $error = '<i class="fas fa-exclamation-circle me-1"></i> ' . htmlspecialchars($result['message']);
        }
    }
}

// Handle Restore from Existing Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_existing'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed (CSRF token invalid). Please refresh and try again.';
    } else {
        $filename = basename($_POST['filename'] ?? '');
        $result = BackupEngine::restoreBackup($filename);

        if ($result['success']) {
            $message = '<i class="fas fa-check-circle me-1"></i> ' . htmlspecialchars($result['message']) . ' An automatic safety checkpoint was saved prior to restoration.';
        } else {
            $error = '<i class="fas fa-exclamation-circle me-1"></i> ' . htmlspecialchars($result['message']);
        }
    }
}

// Handle Upload & Restore
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_restore'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed (CSRF token invalid). Please refresh and try again.';
    } elseif (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please select a valid SQL backup file to upload.';
    } else {
        $uploadedFile = $_FILES['backup_file'];
        $ext = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['sql', 'gz'];

        if (!in_array($ext, $allowedExtensions)) {
            $error = 'Invalid file type. Only .sql and .sql.gz backup files are permitted.';
        } else {
            $backupDir = BackupEngine::getBackupDirectory();
            $targetName = 'uploaded_' . date('Y-m-d_His') . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $uploadedFile['name']);
            $targetPath = $backupDir . DIRECTORY_SEPARATOR . $targetName;

            if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                $result = BackupEngine::restoreBackup($targetName);
                if ($result['success']) {
                    $message = '<i class="fas fa-check-circle me-1"></i> Uploaded backup restored successfully! ' . htmlspecialchars($result['message']);
                } else {
                    $error = '<i class="fas fa-exclamation-circle me-1"></i> ' . htmlspecialchars($result['message']);
                }
            } else {
                $error = 'Failed to process uploaded file.';
            }
        }
    }
}

// Handle Delete Backup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_backup'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security validation failed (CSRF token invalid). Please refresh and try again.';
    } else {
        $filename = basename($_POST['filename'] ?? '');
        $result = BackupEngine::deleteBackup($filename);

        if ($result['success']) {
            $message = '<i class="fas fa-check-circle me-1"></i> ' . htmlspecialchars($result['message']);
        } else {
            $error = '<i class="fas fa-exclamation-circle me-1"></i> ' . htmlspecialchars($result['message']);
        }
    }
}

// Fetch list of backups, auto settings, and statistics
$backups = BackupEngine::getBackupsList();
$autoSettings = BackupEngine::getAutoBackupSettings();
$totalBackupsCount = count($backups);
$totalStorageBytes = 0;
$lastBackupDate = 'Never';

foreach ($backups as $b) {
    $totalStorageBytes += $b['filesize'];
}
if (!empty($backups)) {
    $lastBackupDate = $backups[0]['created_at'];
}

// Format cron URL
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$cronUrl = "{$protocol}://{$host}/Triple8/api/cron_backup.php?token=" . urlencode($autoSettings['backup_cron_token'] ?? '');

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include '../includes/header.php'; ?>
    <style>
        .stat-card {
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08) !important;
        }
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .table-custom th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #f8fafc;
            color: #64748b;
        }
        .badge-type-manual { background-color: #e0e7ff; color: #3730a3; font-weight: 600; }
        .badge-type-checkpoint { background-color: #fef3c7; color: #92400e; font-weight: 600; }
        .badge-type-pre_restore { background-color: #fee2e2; color: #991b1b; font-weight: 600; }
        .badge-type-scheduled { background-color: #d1fae5; color: #065f46; font-weight: 600; }
    </style>
</head>
<body>
    <?php include '../includes/sidebar.php'; ?>

    <div class="main-content">
        <div class="container-fluid">
            
            <!-- Page Header & Actions -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <h2 class="fw-bold mb-1"><i class="fas fa-database text-primary me-2"></i> Database Backup & Restore</h2>
                    <p class="text-muted small mb-0">Generate full SQL snapshots, configure automated schedules, and restore system state safely.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-secondary fw-bold" data-bs-toggle="modal" data-bs-target="#autoSettingsModal">
                        <i class="fas fa-clock-rotate-left me-1"></i> Auto Backup Settings
                    </button>
                    <button type="button" class="btn btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload me-1"></i> Upload Backup
                    </button>
                    <button type="button" class="btn btn-primary fw-bold px-3" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                        <i class="fas fa-plus-circle me-1"></i> Create Backup Now
                    </button>
                </div>
            </div>

            <!-- Alerts -->
            <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Metrics / Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm stat-card p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                                <i class="fas fa-server"></i>
                            </div>
                            <div>
                                <span class="text-muted small fw-semibold">Total Snapshots</span>
                                <h4 class="fw-bold mb-0 text-dark"><?php echo number_format($totalBackupsCount); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm stat-card p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                                <i class="fas fa-hard-drive"></i>
                            </div>
                            <div>
                                <span class="text-muted small fw-semibold">Storage Used</span>
                                <h4 class="fw-bold mb-0 text-dark"><?php echo BackupEngine::formatBytes($totalStorageBytes); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm stat-card p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <span class="text-muted small fw-semibold">Last Backup</span>
                                <div class="fw-bold text-dark text-truncate" style="max-width: 150px; font-size: 14px;">
                                    <?php echo $lastBackupDate !== 'Never' ? date('M d, Y H:i', strtotime($lastBackupDate)) : 'None yet'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="card border-0 shadow-sm stat-card p-3 bg-white">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon <?php echo ($autoSettings['backup_auto_enabled'] == '1') ? 'bg-success text-success' : 'bg-secondary text-secondary'; ?> bg-opacity-10 me-3">
                                <i class="fas fa-robot"></i>
                            </div>
                            <div>
                                <span class="text-muted small fw-semibold">Auto Backup</span>
                                <h6 class="fw-bold mb-0 <?php echo ($autoSettings['backup_auto_enabled'] == '1') ? 'text-success' : 'text-muted'; ?>">
                                    <?php if ($autoSettings['backup_auto_enabled'] == '1'): ?>
                                        <i class="fas fa-check-circle me-1"></i> Every <?php echo (int)($autoSettings['backup_frequency_minutes'] ?? 60); ?>m
                                    <?php else: ?>
                                        <i class="fas fa-pause-circle me-1"></i> Disabled
                                    <?php endif; ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Backups Table Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="fas fa-list text-primary me-2"></i> Available Database Snapshots</h6>
                    <span class="badge bg-light text-muted border"><?php echo count($backups); ?> files</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4">Backup File</th>
                                    <th>Type</th>
                                    <th>Created Date</th>
                                    <th>Size</th>
                                    <th>Tables / Rows</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($backups)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fas fa-database fa-3x mb-3 text-secondary opacity-50"></i>
                                            <p class="mb-2 fw-semibold">No backup snapshots created yet.</p>
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createBackupModal">
                                                <i class="fas fa-plus me-1"></i> Create Your First Backup
                                            </button>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($backups as $b): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <i class="fas <?php echo substr($b['filename'], -3) === '.gz' ? 'fa-file-zipper text-warning' : 'fa-file-code text-primary'; ?> fa-lg me-3"></i>
                                                    <div>
                                                        <span class="fw-bold text-dark font-monospace small"><?php echo htmlspecialchars($b['filename']); ?></span>
                                                        <?php if (!empty($b['notes'])): ?>
                                                            <div class="text-muted small" style="font-size: 11px;"><i class="fas fa-info-circle me-1"></i><?php echo htmlspecialchars($b['notes']); ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge badge-type-<?php echo htmlspecialchars($b['backup_type']); ?> px-2 py-1">
                                                    <?php 
                                                        $typeLabels = [
                                                            'manual' => 'Manual',
                                                            'checkpoint' => 'Checkpoint',
                                                            'pre_restore' => 'Pre-Restore',
                                                            'scheduled' => 'Scheduled'
                                                        ];
                                                        echo $typeLabels[$b['backup_type']] ?? ucfirst($b['backup_type']); 
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted">
                                                <?php echo date('M d, Y h:i A', strtotime($b['created_at'])); ?>
                                            </td>
                                            <td class="small font-monospace fw-semibold">
                                                <?php echo $b['filesize_fmt']; ?>
                                            </td>
                                            <td class="small text-muted">
                                                <?php echo $b['tables_count']; ?> tbls / <?php echo is_numeric($b['total_rows']) ? number_format($b['total_rows']) : $b['total_rows']; ?> rows
                                            </td>
                                            <td>
                                                <?php if ($b['status'] === 'restored'): ?>
                                                    <span class="badge bg-info text-white"><i class="fas fa-rotate-left me-1"></i> Restored</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success text-white"><i class="fas fa-check me-1"></i> Ready</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="btn-group btn-group-sm">
                                                    <!-- Download -->
                                                    <a href="backup.php?action=download&file=<?php echo urlencode($b['filename']); ?>" class="btn btn-outline-secondary" title="Download SQL File">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                    <!-- Restore -->
                                                    <button type="button" class="btn btn-outline-primary" title="Restore this snapshot" onclick="confirmRestore('<?php echo htmlspecialchars($b['filename']); ?>')">
                                                        <i class="fas fa-rotate-left"></i>
                                                    </button>
                                                    <!-- Delete -->
                                                    <button type="button" class="btn btn-outline-danger" title="Delete backup" onclick="confirmDelete('<?php echo htmlspecialchars($b['filename']); ?>')">
                                                        <i class="fas fa-trash-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal: Auto Backup Settings -->
    <div class="modal fade" id="autoSettingsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="modal-header bg-dark text-white">
                        <h6 class="modal-title fw-bold"><i class="fas fa-clock-rotate-left me-2"></i> Automated Backup Configuration</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="backup_auto_enabled" id="autoEnabledSwitch" value="1" <?php echo ($autoSettings['backup_auto_enabled'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-bold text-dark" for="autoEnabledSwitch">Enable Recurring Auto-Backups</label>
                            <div class="form-text small">Backups will be triggered automatically in the background during store operations or via scheduled webhook.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Backup Interval / Frequency</label>
                                <select name="backup_frequency_minutes" class="form-select">
                                    <option value="30" <?php echo ($autoSettings['backup_frequency_minutes'] == 30) ? 'selected' : ''; ?>>Every 30 Minutes</option>
                                    <option value="60" <?php echo ($autoSettings['backup_frequency_minutes'] == 60) ? 'selected' : ''; ?>>Every 1 Hour (Standard)</option>
                                    <option value="180" <?php echo ($autoSettings['backup_frequency_minutes'] == 180) ? 'selected' : ''; ?>>Every 3 Hours</option>
                                    <option value="360" <?php echo ($autoSettings['backup_frequency_minutes'] == 360) ? 'selected' : ''; ?>>Every 6 Hours</option>
                                    <option value="720" <?php echo ($autoSettings['backup_frequency_minutes'] == 720) ? 'selected' : ''; ?>>Every 12 Hours</option>
                                    <option value="1440" <?php echo ($autoSettings['backup_frequency_minutes'] == 1440) ? 'selected' : ''; ?>>Daily (Every 24 Hours)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-bold small">Retention Threshold (Max Snapshots Kept)</label>
                                <select name="backup_retention_count" class="form-select">
                                    <option value="10" <?php echo ($autoSettings['backup_retention_count'] == 10) ? 'selected' : ''; ?>>Keep Last 10 Snapshots</option>
                                    <option value="20" <?php echo ($autoSettings['backup_retention_count'] == 20) ? 'selected' : ''; ?>>Keep Last 20 Snapshots</option>
                                    <option value="30" <?php echo ($autoSettings['backup_retention_count'] == 30) ? 'selected' : ''; ?>>Keep Last 30 Snapshots (Recommended)</option>
                                    <option value="50" <?php echo ($autoSettings['backup_retention_count'] == 50) ? 'selected' : ''; ?>>Keep Last 50 Snapshots</option>
                                    <option value="100" <?php echo ($autoSettings['backup_retention_count'] == 100) ? 'selected' : ''; ?>>Keep Last 100 Snapshots</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="backup_compression" id="autoCompressSwitch" value="1" <?php echo ($autoSettings['backup_compression'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-bold" for="autoCompressSwitch">Compress automated backups with GZIP (.sql.gz)</label>
                        </div>

                        <hr>

                        <div class="mb-2">
                            <label class="form-label fw-bold small text-muted"><i class="fas fa-link me-1"></i> External Cron Webhook URL (Windows Task Scheduler / Cron)</label>
                            <div class="input-group">
                                <input type="text" class="form-control form-control-sm font-monospace bg-light" id="cronUrlInput" value="<?php echo htmlspecialchars($cronUrl); ?>" readonly>
                                <button class="btn btn-sm btn-outline-secondary" type="button" onclick="copyCronUrl()"><i class="fas fa-copy"></i> Copy</button>
                            </div>
                            <div class="form-text small">Trigger this URL via curl, Task Scheduler, or cPanel Cron to execute scheduled backups independently.</div>
                        </div>

                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="regenerate_token" id="regenTokenCheck" value="1">
                            <label class="form-check-label small text-danger fw-semibold" for="regenTokenCheck">Regenerate security token (will invalidate old webhook URL)</label>
                        </div>

                    </div>
                    <div class="modal-footer bg-light py-3">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="save_auto_settings" class="btn btn-primary fw-bold px-4">
                            <i class="fas fa-save me-1"></i> Save Schedule Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Create Backup -->
    <div class="modal fade" id="createBackupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="modal-header bg-primary text-white">
                        <h6 class="modal-title fw-bold"><i class="fas fa-database me-2"></i> Create Database Snapshot</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small">This will generate a complete SQL dump of all tables, retail products, users, transactions, and settings.</p>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Backup Description / Notes (Optional)</label>
                            <input type="text" name="notes" class="form-control" placeholder="e.g. Pre-month end audit backup">
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="compress" id="compressCheck" value="1" <?php echo ($autoSettings['backup_compression'] == '1') ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-bold" for="compressCheck">Compress snapshot with GZIP (.sql.gz)</label>
                        </div>
                        <div class="text-muted small">Compressed files consume 80% less disk space.</div>
                    </div>
                    <div class="modal-footer bg-light py-3">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_backup" class="btn btn-primary fw-bold px-4">
                            <i class="fas fa-save me-1"></i> Generate Backup
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Upload & Restore Backup -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <div class="modal-header bg-dark text-white">
                        <h6 class="modal-title fw-bold"><i class="fas fa-upload me-2"></i> Upload & Restore Backup File</h6>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="alert alert-warning small">
                            <i class="fas fa-triangle-exclamation me-1"></i> <strong>Caution:</strong> Restoring a backup will overwrite existing database records. An automatic pre-restore checkpoint backup will be created first.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Select SQL File (.sql or .sql.gz) *</label>
                            <input type="file" name="backup_file" class="form-control" accept=".sql,.gz" required>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-3">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="upload_restore" class="btn btn-danger fw-bold px-4" onclick="return confirm('Are you sure you want to upload and restore this backup file? Existing data will be overwritten.');">
                            <i class="fas fa-rotate-left me-1"></i> Upload & Restore
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Existing Restore -->
    <form id="restoreForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="filename" id="restoreFilename">
        <input type="hidden" name="restore_existing" value="1">
    </form>

    <!-- Hidden Form for Delete -->
    <form id="deleteForm" method="POST" action="" style="display: none;">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <input type="hidden" name="filename" id="deleteFilename">
        <input type="hidden" name="delete_backup" value="1">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmRestore(filename) {
            if (confirm('WARNING: Are you sure you want to restore the database from snapshot:\n\n' + filename + '\n\nExisting database tables will be updated. An automated safety checkpoint will be saved first.')) {
                document.getElementById('restoreFilename').value = filename;
                document.getElementById('restoreForm').submit();
            }
        }

        function confirmDelete(filename) {
            if (confirm('Are you sure you want to delete this backup file?\n\n' + filename)) {
                document.getElementById('deleteFilename').value = filename;
                document.getElementById('deleteForm').submit();
            }
        }

        function copyCronUrl() {
            const input = document.getElementById('cronUrlInput');
            input.select();
            navigator.clipboard.writeText(input.value).then(() => {
                alert('Webhook URL copied to clipboard!');
            });
        }
    </script>
</body>
</html>
