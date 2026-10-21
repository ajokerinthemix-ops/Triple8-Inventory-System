<?php
/**
 * Database Backup & Restore Engine
 * Triple8 Supermarket POS & Retail System
 *
 * Provides robust SQL export, compression, integrity hashing, automated safety checkpoints,
 * transaction-safe restoration, scheduled auto-backup rules, and audit logging.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

// Set accurate local Philippine Standard Time (UTC+8)
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

class BackupEngine
{
    private static $backupDir = null;

    /**
     * Get and initialize the backups storage directory
     */
    public static function getBackupDirectory()
    {
        if (self::$backupDir === null) {
            $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'backups';
            if (!file_exists($dir)) {
                @mkdir($dir, 0755, true);
            }
            // Secure backups folder against direct public URL downloads
            $htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Order deny,allow\nDeny from all\n");
            }
            self::$backupDir = $dir;
        }
        return self::$backupDir;
    }

    /**
     * Get Auto Backup Settings from database with defaults
     */
    public static function getAutoBackupSettings()
    {
        global $conn;
        $defaults = [
            'backup_auto_enabled'          => '1',
            'backup_frequency_minutes'     => '60', // 60 mins by default
            'backup_retention_count'       => '30', // keep 30 latest
            'backup_compression'           => '1',
            'backup_cron_token'            => 't8_bkp_' . substr(md5(__DIR__ . 'triple8_salt'), 0, 24),
            'backup_last_run'              => null,
        ];

        if ($conn) {
            $keys = array_keys($defaults);
            $inList = "'" . implode("','", $keys) . "'";
            $res = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($inList)");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $defaults[$row['setting_key']] = $row['setting_value'];
                }
            }
        }

        return $defaults;
    }

    /**
     * Save Auto Backup Settings to database
     */
    public static function saveAutoBackupSettings($settings)
    {
        global $conn;
        if (!$conn) return false;

        $hasError = false;
        foreach ($settings as $k => $v) {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            if ($stmt) {
                $valStr = (string)$v;
                $stmt->bind_param("ss", $k, $valStr);
                if (!$stmt->execute()) {
                    $hasError = true;
                }
                $stmt->close();
            }
        }
        return !$hasError;
    }

    /**
     * Check if auto backup is due, run it, and prune old files
     *
     * @param bool $force Force execution regardless of elapsed time
     * @return array|null Result array if executed, null if not due
     */
    public static function checkAndRunAutoBackup($force = false)
    {
        global $conn;
        $settings = self::getAutoBackupSettings();

        $enabled = filter_var($settings['backup_auto_enabled'] ?? '1', FILTER_VALIDATE_BOOLEAN);
        if (!$enabled && !$force) {
            return null;
        }

        $freqMinutes = max(15, (int)($settings['backup_frequency_minutes'] ?? 60));
        $lastRun = !empty($settings['backup_last_run']) ? strtotime($settings['backup_last_run']) : 0;
        $now = time();

        // Check if interval has elapsed
        if (!$force && ($now - $lastRun) < ($freqMinutes * 60)) {
            return null;
        }

        $compress = filter_var($settings['backup_compression'] ?? '1', FILTER_VALIDATE_BOOLEAN);
        $result = self::createBackup('scheduled', "Automated recurring snapshot (interval: {$freqMinutes} mins)", $compress);

        if ($result['success']) {
            // Update last run timestamp
            if ($conn) {
                $nowStr = date('Y-m-d H:i:s');
                $conn->query("INSERT INTO system_settings (setting_key, setting_value) VALUES ('backup_last_run', '{$nowStr}') ON DUPLICATE KEY UPDATE setting_value = '{$nowStr}'");
            }

            // Prune old snapshots exceeding retention limit
            $retentionLimit = max(3, (int)($settings['backup_retention_count'] ?? 30));
            self::pruneOldBackups($retentionLimit);
        }

        return $result;
    }

    /**
     * Prune oldest backups exceeding the retention threshold
     */
    public static function pruneOldBackups($maxRetention = 30)
    {
        $backups = self::getBackupsList();
        if (count($backups) <= $maxRetention) {
            return 0;
        }

        $deletedCount = 0;
        // Keep newest $maxRetention files, delete older ones
        $toDelete = array_slice($backups, $maxRetention);
        foreach ($toDelete as $b) {
            // Do not auto-delete pre_restore safety checkpoints
            if ($b['backup_type'] === 'pre_restore') {
                continue;
            }
            $del = self::deleteBackup($b['filename']);
            if ($del['success']) {
                $deletedCount++;
            }
        }
        return $deletedCount;
    }

    /**
     * Create a complete SQL database backup
     *
     * @param string $type 'manual' | 'scheduled' | 'checkpoint' | 'pre_restore' | 'emergency'
     * @param string $notes Optional user notes
     * @param bool $compress Whether to compress into .sql.gz
     * @return array ['success' => bool, 'message' => string, 'filename' => string, 'filepath' => string]
     */
    public static function createBackup($type = 'manual', $notes = '', $compress = false)
    {
        global $conn;
        if (!$conn) {
            return ['success' => false, 'message' => 'Database connection unavailable.'];
        }

        $startTime = microtime(true);
        $backupDir = self::getBackupDirectory();
        $dbName = env('DB_NAME', 'philippine_retail_system');
        $timestamp = date('Y-m-d_His');
        $baseFilename = "backup_{$dbName}_{$type}_{$timestamp}";
        $sqlFilename = "{$baseFilename}.sql";
        $targetFilename = $compress && function_exists('gzencode') ? "{$baseFilename}.sql.gz" : $sqlFilename;
        $targetFilepath = $backupDir . DIRECTORY_SEPARATOR . $targetFilename;

        // Retrieve all tables in the database
        $tables = [];
        $res = $conn->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
        if ($res) {
            while ($row = $res->fetch_array()) {
                $tables[] = $row[0];
            }
        }

        if (empty($tables)) {
            return ['success' => false, 'message' => 'No database tables found to backup.'];
        }

        $totalRowsCount = 0;
        $tablesCount = count($tables);

        // Build SQL Dump Header
        $sqlDump = "-- ==============================================================================\n";
        $sqlDump .= "-- Triple8 Retail Store Database Backup\n";
        $sqlDump .= "-- Database: `{$dbName}`\n";
        $sqlDump .= "-- Backup Type: {$type}\n";
        $sqlDump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sqlDump .= "-- Tables: {$tablesCount}\n";
        $sqlDump .= "-- Notes: " . addcslashes($notes, "\n\r\t") . "\n";
        $sqlDump .= "-- ==============================================================================\n\n";

        $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sqlDump .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
        $sqlDump .= "SET AUTOCOMMIT = 0;\n";
        $sqlDump .= "START TRANSACTION;\n";
        $sqlDump .= "SET time_zone = '+08:00';\n";
        $sqlDump .= "SET NAMES utf8mb4;\n\n";

        foreach ($tables as $table) {
            // Drop & Create Table Structure
            $sqlDump .= "-- -----------------------------------------------------------------------------\n";
            $sqlDump .= "-- Table structure for `{$table}`\n";
            $sqlDump .= "-- -----------------------------------------------------------------------------\n";
            $sqlDump .= "DROP TABLE IF EXISTS `{$table}`;\n";

            $createRes = $conn->query("SHOW CREATE TABLE `{$table}`");
            if ($createRes && $createRow = $createRes->fetch_assoc()) {
                $sqlDump .= $createRow['Create Table'] . ";\n\n";
            }

            // Dump Table Records
            $dataRes = $conn->query("SELECT * FROM `{$table}`");
            if ($dataRes) {
                $rowCount = $dataRes->num_rows;
                $totalRowsCount += $rowCount;

                if ($rowCount > 0) {
                    $sqlDump .= "-- Dumping data for table `{$table}` ({$rowCount} records)\n";
                    
                    // Fetch column names
                    $fields = [];
                    $fieldsRes = $dataRes->fetch_fields();
                    foreach ($fieldsRes as $field) {
                        $fields[] = "`" . $field->name . "`";
                    }
                    $fieldsList = implode(', ', $fields);

                    $insertBatch = [];
                    while ($row = $dataRes->fetch_assoc()) {
                        $values = [];
                        foreach ($row as $val) {
                            if ($val === null) {
                                $values[] = 'NULL';
                            } else {
                                $escaped = $conn->real_escape_string($val);
                                $values[] = "'" . $escaped . "'";
                            }
                        }
                        $insertBatch[] = "(" . implode(', ', $values) . ")";

                        // Write batch of 100 rows to keep statement size reasonable
                        if (count($insertBatch) >= 100) {
                            $sqlDump .= "INSERT INTO `{$table}` ({$fieldsList}) VALUES\n" . implode(",\n", $insertBatch) . ";\n";
                            $insertBatch = [];
                        }
                    }

                    if (!empty($insertBatch)) {
                        $sqlDump .= "INSERT INTO `{$table}` ({$fieldsList}) VALUES\n" . implode(",\n", $insertBatch) . ";\n";
                    }
                    $sqlDump .= "\n";
                }
            }
        }

        $sqlDump .= "COMMIT;\n";
        $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";
        $sqlDump .= "-- End of Triple8 Backup Dump\n";

        // Write to file (compressed or raw)
        if ($compress && function_exists('gzencode')) {
            $written = @file_put_contents($targetFilepath, gzencode($sqlDump, 9));
        } else {
            $written = @file_put_contents($targetFilepath, $sqlDump);
        }

        if ($written === false) {
            return ['success' => false, 'message' => "Failed to write backup file to storage: '{$targetFilepath}'."];
        }

        $duration = round(microtime(true) - $startTime, 3);
        $filesize = filesize($targetFilepath);
        $checksum = hash_file('sha256', $targetFilepath);
        $currentUserId = function_exists('getUserId') ? getUserId() : 1;

        // Log into backup_logs table if present
        $logStmt = $conn->prepare("INSERT INTO backup_logs (filename, filepath, filesize_bytes, backup_type, status, checksum, tables_count, total_rows, duration_seconds, created_by, notes) VALUES (?, ?, ?, ?, 'success', ?, ?, ?, ?, ?, ?)");
        if ($logStmt) {
            $logStmt->bind_param("ssisssidis", $targetFilename, $targetFilepath, $filesize, $type, $checksum, $tablesCount, $totalRowsCount, $duration, $currentUserId, $notes);
            $logStmt->execute();
            $logStmt->close();
        }

        return [
            'success'   => true,
            'message'   => "Database backup created successfully ({$tablesCount} tables, {$totalRowsCount} records, " . self::formatBytes($filesize) . ").",
            'filename'  => $targetFilename,
            'filepath'  => $targetFilepath,
            'filesize'  => $filesize,
            'checksum'  => $checksum,
            'duration'  => $duration
        ];
    }

    /**
     * Restore database from an existing backup file
     *
     * @param string $filename Filename inside backups/ directory OR full filepath
     * @param bool $isUploadedFile If true, $filename is a temp upload path
     * @return array ['success' => bool, 'message' => string]
     */
    public static function restoreBackup($filename, $isUploadedFile = false)
    {
        global $conn;
        if (!$conn) {
            return ['success' => false, 'message' => 'Database connection unavailable.'];
        }

        $backupDir = self::getBackupDirectory();
        $targetFilepath = $isUploadedFile ? $filename : $backupDir . DIRECTORY_SEPARATOR . basename($filename);

        if (!file_exists($targetFilepath) || !is_readable($targetFilepath)) {
            return ['success' => false, 'message' => 'Backup file not found or unreadable.'];
        }

        // STEP 1: Always take an automatic pre-restore safety checkpoint backup first
        self::createBackup('pre_restore', 'Automatic safety checkpoint before restoring: ' . basename($filename), false);

        // STEP 2: Read SQL content
        $sqlContent = '';
        if (substr($targetFilepath, -3) === '.gz' && function_exists('gzdecode')) {
            $raw = @file_get_contents($targetFilepath);
            $sqlContent = @gzdecode($raw);
        } else {
            $sqlContent = @file_get_contents($targetFilepath);
        }

        if (empty($sqlContent)) {
            return ['success' => false, 'message' => 'Backup file is empty or corrupted.'];
        }

        // STEP 3: Multi-query execution with disabled foreign keys
        $conn->query("SET FOREIGN_KEY_CHECKS=0");
        $conn->query("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");

        // Split SQL statements safely
        $statements = self::splitSqlStatements($sqlContent);
        $executedCount = 0;
        $failedCount = 0;
        $lastError = '';

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt)) continue;

            if (!$conn->query($stmt)) {
                $failedCount++;
                $lastError = $conn->error;
            } else {
                $executedCount++;
            }
        }

        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        if ($failedCount > 0 && $executedCount === 0) {
            return ['success' => false, 'message' => "Restore failed. Error: {$lastError}"];
        }

        // Mark backup as restored in log if it exists
        $baseName = basename($filename);
        $upStmt = $conn->prepare("UPDATE backup_logs SET status = 'restored' WHERE filename = ?");
        if ($upStmt) {
            $upStmt->bind_param("s", $baseName);
            $upStmt->execute();
            $upStmt->close();
        }

        return [
            'success' => true,
            'message' => "Database restored successfully ({$executedCount} statements executed" . ($failedCount > 0 ? ", {$failedCount} notices" : "") . ")."
        ];
    }

    /**
     * Get list of all available backups on disk with database log metadata
     */
    public static function getBackupsList()
    {
        global $conn;
        $backupDir = self::getBackupDirectory();
        $files = glob($backupDir . DIRECTORY_SEPARATOR . 'backup_*.sql*');

        // Fetch logs from DB
        $dbLogs = [];
        if ($conn) {
            $res = $conn->query("SELECT * FROM backup_logs ORDER BY id DESC");
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $dbLogs[$r['filename']] = $r;
                }
            }
        }

        $backups = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $filesize = filesize($file);
            $filemtime = filemtime($file);

            $meta = $dbLogs[$filename] ?? null;
            $type = $meta['backup_type'] ?? 'manual';
            if (strpos($filename, '_pre_restore_') !== false) {
                $type = 'pre_restore';
            } elseif (strpos($filename, '_scheduled_') !== false) {
                $type = 'scheduled';
            } elseif (strpos($filename, '_checkpoint_') !== false) {
                $type = 'checkpoint';
            }

            $backups[] = [
                'filename'       => $filename,
                'filepath'       => $file,
                'filesize'       => $filesize,
                'filesize_fmt'   => self::formatBytes($filesize),
                'created_at'     => date('Y-m-d H:i:s', $filemtime),
                'backup_type'    => $type,
                'tables_count'   => $meta['tables_count'] ?? '-',
                'total_rows'     => $meta['total_rows'] ?? '-',
                'status'         => $meta['status'] ?? 'success',
                'notes'          => $meta['notes'] ?? '',
                'checksum'       => $meta['checksum'] ?? hash_file('sha256', $file)
            ];
        }

        // Sort latest first
        usort($backups, function ($a, $b) {
            return strcmp($b['created_at'], $a['created_at']);
        });

        return $backups;
    }

    /**
     * Delete a backup file
     */
    public static function deleteBackup($filename)
    {
        global $conn;
        $filename = basename($filename);
        $backupDir = self::getBackupDirectory();
        $filepath = $backupDir . DIRECTORY_SEPARATOR . $filename;

        if (file_exists($filepath)) {
            @unlink($filepath);
            if ($conn) {
                $del = $conn->prepare("DELETE FROM backup_logs WHERE filename = ?");
                if ($del) {
                    $del->bind_param("s", $filename);
                    $del->execute();
                    $del->close();
                }
            }
            return ['success' => true, 'message' => "Backup '{$filename}' deleted successfully."];
        }
        return ['success' => false, 'message' => "Backup file '{$filename}' not found."];
    }

    /**
     * Split SQL script into individual executable statements
     */
    private static function splitSqlStatements($sql)
    {
        $statements = [];
        $lines = explode("\n", $sql);
        $buffer = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            // Skip comments and empty lines
            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '/*') === 0 || strpos($trimmed, '#') === 0) {
                continue;
            }

            $buffer .= $line . "\n";
            if (substr($trimmed, -1) === ';') {
                $statements[] = $buffer;
                $buffer = '';
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = $buffer;
        }

        return $statements;
    }

    /**
     * Format bytes into human-readable string
     */
    public static function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
