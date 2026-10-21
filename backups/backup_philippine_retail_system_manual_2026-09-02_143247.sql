-- ==============================================================================
-- Triple8 Retail Store Database Backup
-- Database: `philippine_retail_system`
-- Backup Type: manual
-- Generated: 2026-09-02 14:32:47
-- Tables: 34
-- Notes: 
-- ==============================================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = '+00:00';
SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Table structure for `access_audit_logs`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `access_audit_logs`;
CREATE TABLE `access_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `target_type` varchar(50) DEFAULT NULL,
  `target_id` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `status` enum('success','warning','denied') NOT NULL DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_action` (`action`),
  KEY `idx_user` (`user_id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `access_audit_logs` (153 records)
INSERT INTO `access_audit_logs` (`id`, `user_id`, `username`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `status`, `created_at`) VALUES
('3', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-15 07:33:19'),
('5', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-15 07:44:06'),
('6', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-15 07:46:16'),
('8', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-15 07:56:03'),
('10', '1', NULL, 'LOGIN_FAILED', 'credentials', 'admin', 'Invalid password attempt', '::1', 'denied', '2026-08-18 22:44:29'),
('11', '1', NULL, 'LOGIN_FAILED', 'credentials', 'admin', 'Invalid password attempt', '::1', 'denied', '2026-08-18 22:44:40'),
('12', '1', NULL, 'LOGIN_FAILED', 'credentials', 'admin', 'Invalid password attempt', '::1', 'denied', '2026-08-18 22:44:43'),
('13', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-18 22:45:06'),
('14', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-18 22:54:05'),
('15', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-18 22:54:17'),
('16', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-18 23:03:38'),
('17', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (session_expired)', '::1', 'success', '2026-08-19 01:03:37'),
('18', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-19 01:03:44'),
('19', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-19 01:03:56'),
('20', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-19 01:04:00'),
('21', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-20 22:43:18'),
('22', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-21 08:40:14'),
('23', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 06:22:53'),
('24', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 06:38:12'),
('25', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 06:38:21'),
('26', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 06:38:25'),
('27', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 06:50:00'),
('28', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 06:51:26'),
('29', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 06:51:34'),
('30', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 06:51:42'),
('31', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 07:18:06'),
('32', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 09:12:34'),
('33', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 09:33:40'),
('34', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 18:14:15'),
('35', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 18:15:12'),
('36', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: cashier1', '::1', 'warning', '2026-08-22 18:15:19'),
('37', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: cashier1', '::1', 'warning', '2026-08-22 18:15:24'),
('38', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Username not found: cashier', '::1', 'warning', '2026-08-22 18:15:35'),
('39', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Username not found: cashier', '::1', 'warning', '2026-08-22 18:15:38'),
('40', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 18:15:52'),
('41', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 18:56:54'),
('42', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 18:57:08'),
('43', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 18:58:03'),
('44', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:00:21'),
('45', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:00:28'),
('46', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: cashier1', '::1', 'warning', '2026-08-22 19:00:44'),
('47', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 19:00:53'),
('48', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:00:59'),
('49', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:01:04'),
('50', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:02:15'),
('51', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:02:51'),
('52', '1', 'admin', 'PERMISSION_UPDATE', 'role_matrix', 'all', 'Updated role-permission matrix (30 mappings)', '::1', 'success', '2026-08-22 19:06:42'),
('53', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:06:50'),
('54', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 19:06:57'),
('55', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:07:05'),
('56', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:09:56'),
('57', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:10:59'),
('58', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 19:11:04'),
('59', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:11:14'),
('60', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:11:18'),
('61', '1', 'admin', 'PERMISSION_UPDATE', 'role_matrix', 'all', 'Updated role-permission matrix (32 mappings)', '::1', 'success', '2026-08-22 19:11:49'),
('62', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:11:56'),
('63', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 19:12:00'),
('64', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:12:10'),
('65', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:12:14'),
('66', '1', 'admin', 'PERMISSION_UPDATE', 'role_matrix', 'all', 'Updated role-permission matrix (30 mappings)', '::1', 'success', '2026-08-22 19:12:20'),
('67', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:19:00'),
('68', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 19:22:47'),
('69', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:23:31'),
('70', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:32:27'),
('71', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 19:43:37'),
('72', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 19:44:34'),
('73', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (session_expired)', '::1', 'success', '2026-08-22 22:03:29'),
('74', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 22:03:36'),
('75', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 22:14:06'),
('76', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 23:17:30'),
('77', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 23:35:52'),
('78', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 23:35:58'),
('79', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-08-22 23:35:58'),
('80', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-08-22 23:36:19'),
('81', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-22 23:36:24'),
('82', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-23 03:42:16'),
('83', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-23 03:45:36'),
('84', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-23 03:46:14'),
('85', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-25 21:24:13'),
('86', NULL, NULL, 'BACKUP_CREATED', 'backup', 'triple8_backup_manual_2026-08-26_13-34-30.sql.gz', 'Created manual backup (13.98 KB, 32 tables, 413 rows in 1.858s)', '127.0.0.1', 'success', '2026-08-27 03:34:32'),
('87', NULL, NULL, 'BACKUP_CREATED', 'backup', 'triple8_backup_scheduled_2026-08-26_13-38-58.sql.gz', 'Created scheduled backup (14.21 KB, 32 tables, 415 rows in 1.035s)', '127.0.0.1', 'success', '2026-08-27 03:38:59'),
('88', NULL, NULL, 'BACKUP_CREATED', 'backup', 'triple8_backup_manual_2026-08-26_13-40-07.sql.gz', 'Created manual backup (14.37 KB, 32 tables, 417 rows in 1.967s)', '127.0.0.1', 'success', '2026-08-27 03:40:09'),
('89', NULL, NULL, 'BACKUP_CREATED', 'backup', 'triple8_backup_manual_2026-08-26_13-40-37.sql.gz', 'Created manual backup (14.48 KB, 32 tables, 419 rows in 1.715s)', '127.0.0.1', 'success', '2026-08-27 03:40:39'),
('90', NULL, NULL, 'BACKUP_CREATED', 'backup', 'triple8_backup_scheduled_2026-08-26_13-40-39.sql.gz', 'Created scheduled backup (14.58 KB, 32 tables, 421 rows in 0.919s)', '127.0.0.1', 'success', '2026-08-27 03:40:40'),
('91', NULL, NULL, 'BACKUP_RESTORED', 'backup', 'triple8_backup_checkpoint_2026-08-26_13-40-40.sql.gz', 'Successfully restored database from backup file: triple8_backup_checkpoint_2026-08-26_13-40-40.sql.gz (Safety snapshot: triple8_backup_pre_restore_2026-08-26_13-40-44.sql.gz)', '127.0.0.1', 'success', '2026-08-27 03:40:46'),
('92', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-27 03:41:11'),
('93', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-27 21:19:56'),
('94', '1', 'admin', 'BACKUP_DOWNLOADED', 'backup', 'triple8_backup_scheduled_2026-08-26_13-40-39.sql.gz', 'Downloaded backup archive: triple8_backup_scheduled_2026-08-26_13-40-39.sql.gz', '::1', 'success', '2026-08-27 21:21:55'),
('95', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-08-27 21:59:36'),
('96', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-27 22:05:02'),
('97', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: admin', '::1', 'warning', '2026-08-29 20:44:19'),
('98', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 20:44:25'),
('99', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 20:51:46'),
('100', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 20:56:51'),
('101', '1', 'admin', 'SESSION_HIJACK_ATTEMPT', 'session', '1', 'User Agent fingerprint mismatch detected', '::1', 'denied', '2026-08-29 21:08:48'),
('102', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (session_expired)', '::1', 'success', '2026-08-29 21:08:48'),
('103', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 21:08:48'),
('104', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: admin', '::1', 'warning', '2026-08-29 21:32:54'),
('105', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 21:32:54');
INSERT INTO `access_audit_logs` (`id`, `user_id`, `username`, `action`, `target_type`, `target_id`, `details`, `ip_address`, `status`, `created_at`) VALUES
('106', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: admin', '::1', 'warning', '2026-08-29 21:33:18'),
('107', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 21:33:18'),
('108', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: admin', '::1', 'warning', '2026-08-29 21:33:46'),
('109', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 21:33:46'),
('110', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: admin', '::1', 'warning', '2026-08-29 21:35:08'),
('111', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-08-29 21:35:09'),
('112', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: admin', '::1', 'warning', '2026-08-29 21:35:37'),
('113', NULL, NULL, 'LOGIN_BLOCKED', 'rate_limit', 'admin', 'Too many failed login attempts from ::1', '::1', 'warning', '2026-08-29 21:35:37'),
('114', NULL, NULL, 'LOGIN_BLOCKED', 'rate_limit', 'admin', 'Too many failed login attempts from ::1', '::1', 'warning', '2026-08-29 21:36:17'),
('115', NULL, NULL, 'LOGIN_BLOCKED', 'rate_limit', 'admin', 'Too many failed login attempts from ::1', '::1', 'warning', '2026-08-29 21:37:22'),
('116', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-01 00:56:39'),
('117', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 01:06:17'),
('118', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-01 01:06:23'),
('119', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-01 07:07:10'),
('120', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 07:31:45'),
('121', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-01 07:31:51'),
('122', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-01 22:23:52'),
('123', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 22:54:15'),
('124', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: manager1', '::1', 'warning', '2026-09-01 22:58:46'),
('125', '2', 'manager1', 'LOGIN_SUCCESS', 'user', '2', 'Successful login for role: manager', '::1', 'success', '2026-09-01 22:58:55'),
('126', '2', 'manager1', 'LOGOUT', 'session', '2', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 22:59:06'),
('127', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-01 23:09:14'),
('128', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 23:17:05'),
('129', NULL, NULL, 'LOGIN_FAILED', 'user', NULL, 'Failed password attempt for username: cashier1', '::1', 'warning', '2026-09-01 23:18:10'),
('130', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-09-01 23:18:13'),
('131', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 23:19:48'),
('132', '2', 'manager1', 'LOGIN_SUCCESS', 'user', '2', 'Successful login for role: manager', '::1', 'success', '2026-09-01 23:19:52'),
('133', '2', 'manager1', 'LOGOUT', 'session', '2', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 23:23:21'),
('134', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-01 23:23:25'),
('135', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-01 23:32:30'),
('136', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-02 23:20:46'),
('137', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 00:38:56'),
('138', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 00:54:17'),
('139', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 00:54:33'),
('140', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 01:50:26'),
('141', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 01:50:32'),
('142', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 02:03:01'),
('143', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 02:03:05'),
('144', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 03:38:04'),
('145', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 03:41:01'),
('146', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 03:54:08'),
('147', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 04:03:45'),
('148', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 04:03:59'),
('149', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 04:15:28'),
('150', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 04:21:33'),
('151', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 04:22:26'),
('152', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 04:22:31'),
('153', '1', 'admin', 'LOGOUT', 'session', '1', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 04:23:13'),
('154', '2', 'manager1', 'LOGIN_SUCCESS', 'user', '2', 'Successful login for role: manager', '::1', 'success', '2026-09-03 04:23:18'),
('155', '2', 'manager1', 'LOGOUT', 'session', '2', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 04:23:29'),
('156', '3', 'cashier1', 'LOGIN_SUCCESS', 'user', '3', 'Successful login for role: cashier', '::1', 'success', '2026-09-03 04:23:35'),
('157', '3', 'cashier1', 'LOGOUT', 'session', '3', 'User logged out (logged_out)', '::1', 'success', '2026-09-03 04:23:58'),
('158', '1', 'admin', 'LOGIN_SUCCESS', 'user', '1', 'Successful login for role: admin', '::1', 'success', '2026-09-03 04:24:55');

-- -----------------------------------------------------------------------------
-- Table structure for `api_keys`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `api_keys`;
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL,
  `api_key_hash` varchar(64) NOT NULL,
  `key_prefix` varchar(16) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'cashier',
  `allowed_ips` text DEFAULT NULL,
  `rate_limit_per_min` int(11) NOT NULL DEFAULT 60,
  `status` enum('active','revoked','expired') NOT NULL DEFAULT 'active',
  `last_used_at` datetime DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key_hash` (`api_key_hash`),
  KEY `idx_key_hash` (`api_key_hash`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `audit_log`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_user_id` (`user_id`),
  KEY `idx_audit_log_created_at` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `backup_logs`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `backup_logs`;
CREATE TABLE `backup_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `filesize_bytes` bigint(20) NOT NULL DEFAULT 0,
  `backup_type` enum('scheduled','checkpoint','manual','pre_restore','emergency') NOT NULL DEFAULT 'manual',
  `status` enum('success','failed','restored') NOT NULL DEFAULT 'success',
  `checksum` varchar(64) DEFAULT NULL,
  `tables_count` int(11) NOT NULL DEFAULT 0,
  `total_rows` int(11) NOT NULL DEFAULT 0,
  `duration_seconds` decimal(6,3) NOT NULL DEFAULT 0.000,
  `created_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_backup_type` (`backup_type`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `backup_logs` (5 records)
INSERT INTO `backup_logs` (`id`, `filename`, `filepath`, `filesize_bytes`, `backup_type`, `status`, `checksum`, `tables_count`, `total_rows`, `duration_seconds`, `created_by`, `notes`, `created_at`) VALUES
('1', 'triple8_backup_manual_2026-08-26_13-34-30.sql.gz', 'C:\\xampp\\htdocs\\Triple8\\backups\\triple8_backup_manual_2026-08-26_13-34-30.sql.gz', '14315', 'manual', 'success', '0777d8aaf7d6ccb1b57792be4b378d38fa10cc0ab7126dc3aa9b5a942151b835', '32', '413', '1.858', NULL, 'Initial verification test backup', '2026-08-27 03:34:32'),
('2', 'triple8_backup_scheduled_2026-08-26_13-38-58.sql.gz', 'C:\\xampp\\htdocs\\Triple8\\backups\\triple8_backup_scheduled_2026-08-26_13-38-58.sql.gz', '14554', 'scheduled', 'success', '67c737793f157954f38f71296fccb7fb7eba5ad09ef70675f602e281969c085b', '32', '415', '1.035', NULL, 'CLI Automated Backup Worker (Power-Loss Protection)', '2026-08-27 03:38:59'),
('3', 'triple8_backup_manual_2026-08-26_13-40-07.sql.gz', 'C:\\xampp\\htdocs\\Triple8\\backups\\triple8_backup_manual_2026-08-26_13-40-07.sql.gz', '14710', 'manual', 'success', '8fee81acaaf3c6d57499b61fcf87a62a8e248e56c48360553fe1a6368ac8b526', '32', '417', '1.967', NULL, 'Comprehensive Test Backup', '2026-08-27 03:40:09'),
('4', 'triple8_backup_manual_2026-08-26_13-40-37.sql.gz', 'C:\\xampp\\htdocs\\Triple8\\backups\\triple8_backup_manual_2026-08-26_13-40-37.sql.gz', '14832', 'manual', 'success', '642c0cad38d7b21deeefbfa8d28025dde223f11b184d925788ac870c49f6644b', '32', '419', '1.715', NULL, 'Comprehensive Test Backup', '2026-08-27 03:40:39'),
('5', 'triple8_backup_scheduled_2026-08-26_13-40-39.sql.gz', 'C:\\xampp\\htdocs\\Triple8\\backups\\triple8_backup_scheduled_2026-08-26_13-40-39.sql.gz', '14929', 'scheduled', 'success', 'ffce87b0be91cbb30a7fb1614120162212a080608ebe790a71bd3ec0f42dd569', '32', '421', '0.919', NULL, 'Automated scheduled cron execution for power outage resilience', '2026-08-27 03:40:40');

-- -----------------------------------------------------------------------------
-- Table structure for `bakery_batches`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `bakery_batches`;
CREATE TABLE `bakery_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_code` varchar(50) NOT NULL,
  `product_id` int(11) NOT NULL,
  `premix_id` int(11) NOT NULL,
  `premix_used_kg` decimal(10,3) NOT NULL,
  `baked_quantity` int(11) NOT NULL,
  `baked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `best_before` date DEFAULT NULL,
  `status` enum('planned','baked','waste') NOT NULL DEFAULT 'baked',
  `prepared_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `batch_code` (`batch_code`),
  KEY `product_id` (`product_id`),
  KEY `premix_id` (`premix_id`),
  KEY `prepared_by` (`prepared_by`),
  CONSTRAINT `bakery_batches_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `bakery_batches_ibfk_2` FOREIGN KEY (`premix_id`) REFERENCES `premixes` (`id`),
  CONSTRAINT `bakery_batches_ibfk_3` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `bakery_recipes`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `bakery_recipes`;
CREATE TABLE `bakery_recipes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `premix_id` int(11) NOT NULL,
  `premix_kg_per_batch` decimal(10,3) NOT NULL,
  `yield_per_batch` int(11) NOT NULL,
  `bake_minutes` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_premix` (`product_id`,`premix_id`),
  KEY `premix_id` (`premix_id`),
  CONSTRAINT `bakery_recipes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bakery_recipes_ibfk_2` FOREIGN KEY (`premix_id`) REFERENCES `premixes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `categories`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `categories`;
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `categories` (29 records)
INSERT INTO `categories` (`id`, `name`, `created_at`) VALUES
('4', 'Personal Care & Hygiene', '2026-03-17 10:59:39'),
('5', 'Household & Cleaning', '2026-03-17 10:59:39'),
('8', 'SHOES & BAGS', '2026-03-18 07:31:00'),
('17', 'Household & Cleaning', '2026-08-20 22:39:37'),
('18', 'Dairy, Chilled & Frozen', '2026-08-20 22:39:37'),
('20', 'Apparel', '2026-08-22 08:28:10'),
('21', 'Apparel / Accessories', '2026-08-22 08:28:10'),
('22', 'Footwear', '2026-08-22 08:28:10'),
('23', 'Bags & Luggage', '2026-08-22 08:28:10'),
('24', 'Fashion Accessories', '2026-08-22 08:28:10'),
('25', 'Everyday Accessories', '2026-08-22 08:28:10'),
('26', 'Home & Living', '2026-08-22 08:28:10'),
('27', 'Drinkware', '2026-08-22 08:28:10'),
('28', 'Home Essentials', '2026-08-22 08:28:10'),
('29', 'Kitchenware', '2026-08-22 08:28:10'),
('30', 'Home Appliances', '2026-08-22 08:28:10'),
('31', 'Hardware / Lighting', '2026-08-22 08:28:10'),
('32', 'Electronics / Hardware', '2026-08-22 08:28:10'),
('33', 'Mobile Accessories', '2026-08-22 08:28:10'),
('34', 'Audio & Accessories', '2026-08-22 08:28:10'),
('35', 'Electronics', '2026-08-22 08:28:10'),
('36', 'Beauty & Cosmetics', '2026-08-22 08:28:10'),
('37', 'Personal Care', '2026-08-22 08:28:10'),
('38', 'Personal Care Appliances', '2026-08-22 08:28:10'),
('39', 'Toys & Hobbies', '2026-08-22 08:28:10'),
('40', 'Games & Hobbies', '2026-08-22 08:28:10'),
('41', 'Outdoor Toys', '2026-08-22 08:28:10'),
('42', 'Hardware & Tools', '2026-08-22 08:28:10'),
('43', 'Hardware Supplies', '2026-08-22 08:28:10');

-- -----------------------------------------------------------------------------
-- Table structure for `customers`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `customers`;
CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `loyalty_points` int(11) NOT NULL DEFAULT 0,
  `membership_tier` enum('Regular','Bronze','Silver','Gold') NOT NULL DEFAULT 'Regular',
  `total_spent` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_code` (`customer_code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `customers` (4 records)
INSERT INTO `customers` (`id`, `customer_code`, `name`, `phone`, `email`, `address`, `loyalty_points`, `membership_tier`, `total_spent`, `created_at`) VALUES
('1', 'CUST-1001', 'Juan Dela Cruz', '0917-123-4567', 'juan.delacruz@email.com', 'Quezon City', '322', 'Silver', '32725.00', '2026-08-20 22:32:53'),
('2', 'CUST-1002', 'Maria Santos', '0918-234-5678', 'maria.santos@email.com', 'Pasig City', '150', 'Bronze', '15200.00', '2026-08-20 22:32:53'),
('3', 'CUST-1003', 'Roberto Tan', '0920-345-6789', 'roberto.tan@email.com', 'Makati City', '650', 'Gold', '68400.00', '2026-08-20 22:32:53'),
('4', 'CUST-1004', 'Elena Reyes', '0922-456-7890', 'elena.reyes@email.com', 'Mandaluyong City', '45', 'Regular', '4500.00', '2026-08-20 22:32:53');

-- -----------------------------------------------------------------------------
-- Table structure for `email_verifications`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `email_verifications`;
CREATE TABLE `email_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `email_verifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `login_attempts`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_username` (`username`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_time` (`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `login_attempts` (90 records)
INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempt_time`, `success`) VALUES
('1', 'admin', '::1', '2026-08-14 23:44:06', '1'),
('2', 'admin', '::1', '2026-08-14 23:46:16', '1'),
('3', 'admin', '::1', '2026-08-14 23:56:03', '1'),
('4', 'admin', '::1', '2026-08-18 14:44:29', '0'),
('5', 'admin', '::1', '2026-08-18 14:44:40', '0'),
('6', 'admin', '::1', '2026-08-18 14:44:43', '0'),
('7', 'admin', '::1', '2026-08-18 14:45:06', '1'),
('8', 'admin', '::1', '2026-08-18 14:54:17', '1'),
('9', 'admin', '::1', '2026-08-18 15:03:38', '1'),
('10', 'admin', '::1', '2026-08-18 17:03:44', '1'),
('11', 'admin', '::1', '2026-08-18 17:04:00', '1'),
('12', 'admin', '::1', '2026-08-20 14:43:18', '1'),
('13', 'admin', '::1', '2026-08-21 00:40:14', '1'),
('14', 'admin', '::1', '2026-08-21 22:22:53', '1'),
('15', 'admin', '::1', '2026-08-21 22:38:21', '1'),
('16', 'admin', '::1', '2026-08-21 22:50:00', '1'),
('17', 'admin', '::1', '2026-08-21 22:51:33', '1'),
('18', 'admin', '::1', '2026-08-21 23:18:06', '1'),
('19', 'admin', '::1', '2026-08-22 01:12:34', '1'),
('20', 'admin', '::1', '2026-08-22 10:14:15', '1'),
('21', 'cashier1', '::1', '2026-08-22 10:15:19', '0'),
('22', 'cashier1', '::1', '2026-08-22 10:15:24', '0'),
('23', 'cashier', '::1', '2026-08-22 10:15:35', '0'),
('24', 'cashier', '::1', '2026-08-22 10:15:38', '0'),
('25', 'admin', '::1', '2026-08-22 10:15:52', '1'),
('26', 'cashier1', '::1', '2026-08-22 10:57:08', '1'),
('27', 'admin', '::1', '2026-08-22 11:00:21', '1'),
('28', 'cashier1', '::1', '2026-08-22 11:00:44', '0'),
('29', 'cashier1', '::1', '2026-08-22 11:00:53', '1'),
('30', 'admin', '::1', '2026-08-22 11:01:04', '1'),
('31', 'admin', '::1', '2026-08-22 11:02:51', '1'),
('32', 'cashier1', '::1', '2026-08-22 11:06:57', '1'),
('33', 'admin', '::1', '2026-08-22 11:09:56', '1'),
('34', 'cashier1', '::1', '2026-08-22 11:11:04', '1'),
('35', 'admin', '::1', '2026-08-22 11:11:18', '1'),
('36', 'cashier1', '::1', '2026-08-22 11:12:00', '1'),
('37', 'admin', '::1', '2026-08-22 11:12:14', '1'),
('38', 'cashier1', '::1', '2026-08-22 11:22:47', '1'),
('39', 'admin', '::1', '2026-08-22 11:32:27', '1'),
('40', 'admin', '::1', '2026-08-22 11:44:34', '1'),
('41', 'admin', '::1', '2026-08-22 14:03:36', '1'),
('42', 'admin', '::1', '2026-08-22 15:17:30', '1'),
('43', 'cashier1', '::1', '2026-08-22 15:35:58', '1'),
('44', 'cashier1', '::1', '2026-08-22 15:35:58', '1'),
('45', 'admin', '::1', '2026-08-22 15:36:24', '1'),
('46', 'admin', '::1', '2026-08-22 19:42:16', '1'),
('47', 'admin', '::1', '2026-08-22 19:46:14', '1'),
('48', 'admin', '::1', '2026-08-25 13:24:13', '1'),
('49', 'admin', '::1', '2026-08-26 19:41:11', '1'),
('50', 'admin', '::1', '2026-08-27 13:19:56', '1'),
('51', 'admin', '::1', '2026-08-27 14:05:02', '1'),
('52', 'admin', '::1', '2026-08-29 12:44:19', '0'),
('53', 'admin', '::1', '2026-08-29 12:44:25', '1'),
('54', 'admin', '::1', '2026-08-29 12:51:46', '1'),
('55', 'admin', '::1', '2026-08-29 12:56:51', '1'),
('56', 'admin', '::1', '2026-08-29 13:08:48', '1'),
('57', 'admin', '::1', '2026-08-29 13:32:54', '0'),
('58', 'admin', '::1', '2026-08-29 13:32:54', '1'),
('59', 'admin', '::1', '2026-08-29 13:33:18', '0'),
('60', 'admin', '::1', '2026-08-29 13:33:18', '1'),
('61', 'admin', '::1', '2026-08-29 13:33:46', '0'),
('62', 'admin', '::1', '2026-08-29 13:33:46', '1'),
('63', 'admin', '::1', '2026-08-29 13:35:08', '0'),
('64', 'admin', '::1', '2026-08-29 13:35:09', '1'),
('65', 'admin', '::1', '2026-08-29 13:35:37', '0'),
('66', 'admin', '::1', '2026-08-31 16:56:39', '1'),
('67', 'admin', '::1', '2026-08-31 17:06:23', '1'),
('68', 'admin', '::1', '2026-08-31 23:07:10', '1'),
('69', 'admin', '::1', '2026-08-31 23:31:51', '1'),
('70', 'admin', '::1', '2026-09-01 14:23:52', '1'),
('71', 'manager1', '::1', '2026-09-01 14:58:46', '0'),
('72', 'manager1', '::1', '2026-09-01 14:58:55', '1'),
('73', 'admin', '::1', '2026-09-01 15:09:14', '1'),
('74', 'cashier1', '::1', '2026-09-01 15:18:10', '0'),
('75', 'cashier1', '::1', '2026-09-01 15:18:13', '1'),
('76', 'manager1', '::1', '2026-09-01 15:19:52', '1'),
('77', 'admin', '::1', '2026-09-01 15:23:25', '1'),
('78', 'admin', '::1', '2026-09-02 15:20:46', '1'),
('79', 'admin', '::1', '2026-09-02 16:38:56', '1'),
('80', 'admin', '::1', '2026-09-02 16:54:33', '1'),
('81', 'admin', '::1', '2026-09-02 17:50:32', '1'),
('82', 'admin', '::1', '2026-09-02 18:03:05', '1'),
('83', 'admin', '::1', '2026-09-02 19:38:04', '1'),
('84', 'admin', '::1', '2026-09-02 19:54:08', '1'),
('85', 'admin', '::1', '2026-09-02 20:03:59', '1'),
('86', 'admin', '::1', '2026-09-02 20:21:33', '1'),
('87', 'admin', '::1', '2026-09-02 20:22:31', '1'),
('88', 'manager1', '::1', '2026-09-02 20:23:18', '1'),
('89', 'cashier1', '::1', '2026-09-02 20:23:35', '1'),
('90', 'admin', '::1', '2026-09-02 20:24:55', '1');

-- -----------------------------------------------------------------------------
-- Table structure for `password_resets`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `otp_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_otp` (`otp_code`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `password_resets` (1 records)
INSERT INTO `password_resets` (`id`, `user_id`, `otp_code`, `expires_at`, `used`, `created_at`) VALUES
('5', '1', '365138', '2026-09-02 14:25:35', '0', '2026-09-02 20:20:35');

-- -----------------------------------------------------------------------------
-- Table structure for `permissions`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'General',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `permissions` (26 records)
INSERT INTO `permissions` (`id`, `name`, `display_name`, `category`, `description`, `created_at`) VALUES
('1', 'dashboard', 'View Dashboard', 'Core Access', 'Access role-specific dashboard and key performance summaries', '2026-08-15 07:16:56'),
('2', 'profile', 'Manage Profile', 'Core Access', 'View and update personal profile and security credentials', '2026-08-15 07:16:56'),
('3', 'users', 'User Management', 'Users & Security', 'View, create, edit, and deactivate user accounts', '2026-08-15 07:16:56'),
('4', 'manage_roles', 'Manage Roles & Permissions', 'Users & Security', 'Configure access control matrix, custom roles, and user overrides', '2026-08-15 07:16:56'),
('5', 'view_audit_logs', 'View Audit Trail', 'Users & Security', 'View security events, access logs, and modification history', '2026-08-15 07:16:56'),
('6', 'products', 'Product Management', 'Products & Categories', 'View product catalog and pricing', '2026-08-15 07:16:56'),
('7', 'add_edit_products', 'Add & Edit Products', 'Products & Categories', 'Create and update products, bakery recipes, and barcodes', '2026-08-15 07:16:56'),
('8', 'categories', 'Category Management', 'Products & Categories', 'Manage product categories and department groups', '2026-08-15 07:16:56'),
('9', 'inventory', 'Inventory Management', 'Inventory & Premix', 'View stock levels, ingredients, and batch movement tracking', '2026-08-15 07:16:56'),
('10', 'receive_stock', 'Receive Stock Deliveries', 'Inventory & Premix', 'Record incoming premix and ingredient shipments', '2026-08-15 07:16:56'),
('11', 'adjust_inventory', 'Adjust Inventory', 'Inventory & Premix', 'Perform stock reconciliations, waste logs, and count adjustments', '2026-08-15 07:16:56'),
('12', 'pos', 'Point of Sale (POS)', 'POS & Sales', 'Access the POS terminal register to input sales', '2026-08-15 07:16:56'),
('13', 'process_sale', 'Process Sales & Checkout', 'POS & Sales', 'Submit payment, finalize checkout, and record revenue', '2026-08-15 07:16:56'),
('14', 'transactions', 'Transaction History', 'POS & Sales', 'View historical sales receipts and customer transactions', '2026-08-15 07:16:56'),
('15', 'receipt', 'Print / View Receipts', 'POS & Sales', 'Reprint and inspect official sales receipts', '2026-08-15 07:16:56'),
('16', 'void_transactions', 'Void Transactions', 'POS & Sales', 'Void completed transactions with manager authorization', '2026-08-15 07:16:56'),
('17', 'apply_discounts', 'Apply Discounts', 'POS & Sales', 'Apply special order discounts and promotional rates', '2026-08-15 07:16:56'),
('18', 'approve_returns', 'Approve Product Returns', 'POS & Sales', 'Authorize customer product returns and exchange vouchers', '2026-08-15 07:16:56'),
('19', 'sales', 'Sales Overview', 'Reports & Finance', 'View aggregate sales analytics and operational metrics', '2026-08-15 07:16:56'),
('20', 'reports', 'Business Reports', 'Reports & Finance', 'Generate and export financial, inventory, and sales reports', '2026-08-15 07:16:56'),
('21', 'settings', 'System Settings', 'System Settings', 'Configure store metadata, tax rates, currency, and bakery policies', '2026-08-15 07:16:56'),
('22', 'suppliers', 'Manage Suppliers', 'Products & Catalog', 'View, add, and update supplier vendor records', '2026-08-20 22:34:13'),
('23', 'customers', 'Manage Customers', 'POS & Sales', 'Manage customer database and loyalty rewards membership', '2026-08-20 22:34:13'),
('24', 'stock_receiving', 'Stock Inward Receiving', 'Inventory Operations', 'Receive stock deliveries against supplier purchase orders', '2026-08-20 22:34:13'),
('25', 'stock_audit', 'Physical Stock Audit', 'Inventory Operations', 'Perform physical count reconciliation and log shrinkage/damage', '2026-08-20 22:34:13'),
('26', 'backup', 'Data Backup & Recovery', 'System Settings', 'Manage automated backups, execute disaster recovery restores, and check table integrity', '2026-08-27 03:33:49');

-- -----------------------------------------------------------------------------
-- Table structure for `premix_daily_controls`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `premix_daily_controls`;
CREATE TABLE `premix_daily_controls` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premix_id` int(11) NOT NULL,
  `control_date` date NOT NULL,
  `beginning_kg` decimal(10,3) NOT NULL DEFAULT 0.000,
  `delivery_kg` decimal(10,3) NOT NULL DEFAULT 0.000,
  `production_kg` decimal(10,3) NOT NULL DEFAULT 0.000,
  `actual_ending_kg` decimal(10,3) NOT NULL,
  `remarks` text DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_premix_control_day` (`premix_id`,`control_date`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `premix_daily_controls_ibfk_1` FOREIGN KEY (`premix_id`) REFERENCES `premixes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `premix_daily_controls_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `premix_daily_controls` (1 records)
INSERT INTO `premix_daily_controls` (`id`, `premix_id`, `control_date`, `beginning_kg`, `delivery_kg`, `production_kg`, `actual_ending_kg`, `remarks`, `recorded_by`, `recorded_at`) VALUES
('1', '1', '2026-08-18', '0.000', '0.000', '0.000', '0.000', '', '1', '2026-08-18 23:42:17');

-- -----------------------------------------------------------------------------
-- Table structure for `premix_movements`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `premix_movements`;
CREATE TABLE `premix_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `premix_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `movement_type` enum('receive','bake_use','adjustment','waste') NOT NULL,
  `quantity_kg` decimal(10,3) NOT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `premix_id` (`premix_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `premix_movements_ibfk_1` FOREIGN KEY (`premix_id`) REFERENCES `premixes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `premix_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `premixes`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `premixes`;
CREATE TABLE `premixes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `supplier` varchar(150) DEFAULT NULL,
  `stock_kg` decimal(10,3) NOT NULL DEFAULT 0.000,
  `reorder_level_kg` decimal(10,3) NOT NULL DEFAULT 0.000,
  `cost_per_kg` decimal(10,2) NOT NULL DEFAULT 0.00,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sku` (`sku`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `premixes` (49 records)
INSERT INTO `premixes` (`id`, `name`, `sku`, `supplier`, `stock_kg`, `reorder_level_kg`, `cost_per_kg`, `active`, `created_at`) VALUES
('1', 'Banana Loaf/Slice', 'PMX-001', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('2', 'Binangkal', 'PMX-002', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('3', 'Butter Stresussel', 'PMX-003', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('4', 'Chesse Ballls', 'PMX-004', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('5', 'Cheese Bread', 'PMX-005', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('6', 'Chees Loaf (2kg)', 'PMX-006', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('7', 'Cheese Roll', 'PMX-007', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('8', 'Cheesemelt', 'PMX-008', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('9', 'Choco Balls', 'PMX-009', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('10', 'Choco Krinkles(1.2g)', 'PMX-010', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('11', 'Cinnamon Roll', 'PMX-011', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('12', 'Glassie(2kg)', 'PMX-012', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('13', 'Cracked Pandesal', 'PMX-013', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('14', 'Eggroll', 'PMX-014', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('15', 'Ensaymada', 'PMX-015', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('16', 'Francis', 'PMX-016', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('17', 'Garlic Bread', 'PMX-017', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('18', 'Halfmoon', 'PMX-018', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('19', 'Hopia', 'PMX-019', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('20', 'King Roll', 'PMX-020', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('21', 'Monay', 'PMX-021', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('22', 'Pancho', 'PMX-022', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('23', 'Pande Leche', 'PMX-023', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('24', 'Pandeciosa', 'PMX-024', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('25', 'Pandesal B1T1', 'PMX-025', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('26', 'Pandesal - 5', 'PMX-026', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('27', 'Polvoron Cookies', 'PMX-027', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('28', 'Premium(2kg)', 'PMX-028', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('29', 'Red Pie(1kg)', 'PMX-029', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('30', 'Siakoy', 'PMX-030', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('31', 'Spanish', 'PMX-031', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('32', 'Star Bread', 'PMX-032', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('33', 'Torta Special', 'PMX-033', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('34', 'Wheat Pandesal', 'PMX-034', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('35', 'Wheat Loaf', 'PMX-035', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('36', 'Choco German', 'PMX-036', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('37', 'Fig Bun', 'PMX-037', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('38', 'Coffee Bun', 'PMX-038', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('39', 'Nutri Bun', 'PMX-039', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('40', 'Pande-Coco', 'PMX-040', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('41', 'Butchi-Coco', 'PMX-041', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('42', 'Coco Bun', 'PMX-042', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('43', 'Malunggay Bun', 'PMX-043', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('44', 'Malunggay Cheese Pandesal', 'PMX-044', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('45', 'Ube Bread', 'PMX-045', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('46', 'Mini Cinnamon', 'PMX-046', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('47', 'Kababayan', 'PMX-047', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('48', 'PIneapple Pie', 'PMX-048', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16'),
('49', 'Spansish Overload', 'PMX-049', NULL, '0.000', '0.000', '0.00', '1', '2026-08-15 06:58:16');

-- -----------------------------------------------------------------------------
-- Table structure for `product_images`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `product_images`;
CREATE TABLE `product_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `image_name` varchar(255) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `products`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sku` varchar(50) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(11) NOT NULL DEFAULT 0,
  `barcode` varchar(50) DEFAULT NULL,
  `item_type` varchar(50) NOT NULL DEFAULT 'retail_item',
  `unit` varchar(30) NOT NULL DEFAULT 'piece',
  `reorder_level` decimal(10,2) NOT NULL DEFAULT 10.00,
  `shelf_life_days` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `image` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lead_time_days` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `barcode` (`barcode`),
  UNIQUE KEY `sku` (`sku`),
  KEY `category_id` (`category_id`),
  KEY `fk_products_supplier` (`supplier_id`),
  CONSTRAINT `fk_products_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=91 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `products` (60 records)
INSERT INTO `products` (`id`, `sku`, `name`, `brand`, `category_id`, `supplier_id`, `price`, `cost_price`, `stock`, `barcode`, `item_type`, `unit`, `reorder_level`, `shelf_life_days`, `is_active`, `image`, `image_path`, `created_at`, `lead_time_days`) VALUES
('31', 'SKU-STY-193', 'STYROFOAM BOXES', 'Retail', '18', NULL, '150.00', '0.00', '30', '4804275872841', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:04:21', NULL),
('32', 'SKU-BIG-349', 'Big long Glass', 'Retail', '5', NULL, '110.00', '0.00', '130', '4805288222930', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:05:10', NULL),
('33', 'SKU-SER-411', 'Serving Tray and Plate Pure Stainless', 'Retail', '17', NULL, '130.00', '0.00', '60', '4804700950865', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:06:03', NULL),
('34', 'SKU-COL-170', 'COLORFUL PLAYMATS!', 'Retail', '17', NULL, '149.00', '0.00', '69', '4804444048286', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:06:47', NULL),
('35', 'SKU-RAT-388', 'RATTAN DESIGN PLASTIC PLANTER', 'Retail', '5', NULL, '95.00', '0.00', '30', '4808034707773', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:10:48', NULL),
('36', 'SKU-RAT-558', 'RATTAN STOOL CHAIR', 'Retail', '5', NULL, '225.00', '0.00', '27', '4802651860479', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:11:48', NULL),
('37', 'SKU-PLA-300', 'PLASTIC FAUCET', 'Retail', '4', NULL, '110.00', '0.00', '340', '4801588781806', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:12:35', NULL),
('38', 'SKU-BAS-001', 'Basic Plain White T-Shirt (M)', 'Triple8 Retail', '20', NULL, '199.00', '139.30', '48', '480888000001', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('39', 'SKU-MEN-002', 'Men\'s Denim Jeans Size 32', 'Triple8 Retail', '20', NULL, '599.00', '419.30', '30', '480888000002', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('40', 'SKU-WOM-003', 'Women\'s Casual Blouse', 'Triple8 Retail', '20', NULL, '299.00', '209.30', '40', '480888000003', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('41', 'SKU-UNI-004', 'Unisex Hoodie Jacket', 'Triple8 Retail', '20', NULL, '699.00', '489.30', '25', '480888000004', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('42', 'SKU-COT-005', 'Cotton Socks 3-Pack', 'Triple8 Retail', '21', NULL, '120.00', '84.00', '59', '480888000005', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('43', 'SKU-MEN-006', 'Men\'s Cargo Shorts', 'Triple8 Retail', '20', NULL, '349.00', '244.30', '35', '480888000006', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('44', 'SKU-WOM-007', 'Women\'s Leggings Black', 'Triple8 Retail', '20', NULL, '180.00', '126.00', '45', '480888000007', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('45', 'SKU-CAS-008', 'Casual Canvas Sneakers', 'Triple8 Retail', '22', NULL, '550.00', '385.00', '19', '480888000008', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('46', 'SKU-RUB-009', 'Rubber Flip Flops / Slippers', 'Triple8 Retail', '22', NULL, '85.00', '59.50', '100', '480888000009', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('47', 'SKU-RUN-010', 'Running Shoes Unisex', 'Triple8 Retail', '22', NULL, '990.00', '693.00', '15', '480888000010', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('48', 'SKU-FOR-011', 'Formal Leather Shoes', 'Triple8 Retail', '22', NULL, '1250.00', '875.00', '12', '480888000011', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('49', 'SKU-SPO-012', 'Sports Sandals', 'Triple8 Retail', '22', NULL, '399.00', '279.30', '25', '480888000012', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('50', 'SKU-SCH-013', 'School Backpack Heavy Duty', 'Triple8 Retail', '23', NULL, '650.00', '455.00', '30', '480888000013', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('51', 'SKU-WOM-014', 'Women\'s Tote Bag', 'Triple8 Retail', '23', NULL, '249.00', '174.30', '40', '480888000014', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('52', 'SKU-LEA-015', 'Leather Belt Black', 'Triple8 Retail', '24', NULL, '150.00', '105.00', '50', '480888000015', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('53', 'SKU-BAS-016', 'Baseball Cap Adjustable', 'Triple8 Retail', '24', NULL, '129.00', '90.30', '44', '480888000016', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('54', 'SKU-TRA-017', 'Travel Duffel Bag', 'Triple8 Retail', '23', NULL, '499.00', '349.30', '20', '480888000017', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('55', 'SKU-FOL-018', 'Foldable Umbrella UV-Proof', 'Triple8 Retail', '25', NULL, '199.00', '139.30', '60', '480888000018', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('56', 'SKU-PLA-019', 'Plastic Storage Box 30L', 'Triple8 Retail', '26', NULL, '299.00', '209.30', '25', '480888000019', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('57', 'SKU-PLA-020', 'Plastic Clothes Hanger 6s', 'Triple8 Retail', '26', NULL, '75.00', '52.50', '80', '480888000020', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('58', 'SKU-STA-021', 'Stainless Steel Water Tumbler 800ml', 'Triple8 Retail', '27', NULL, '350.00', '245.00', '35', '480888000021', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('59', 'SKU-MIC-022', 'Microfiber Towel 70x140cm', 'Triple8 Retail', '28', NULL, '180.00', '126.00', '50', '480888000022', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('60', 'SKU-NON-023', 'Non-Stick Frying Pan 24cm', 'Triple8 Retail', '29', NULL, '320.00', '224.00', '20', '480888000023', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('61', 'SKU-CER-024', 'Ceramic Dinner Mug 350ml', 'Triple8 Retail', '29', NULL, '95.00', '66.50', '40', '480888000024', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('62', 'SKU-DIS-025', 'Dish Drying Rack Plastic', 'Triple8 Retail', '29', NULL, '280.00', '196.00', '15', '480888000025', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('63', 'SKU-ELE-026', 'Electric Desk Fan 12-inch', 'Triple8 Retail', '30', NULL, '599.00', '419.30', '15', '480888000026', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('64', 'SKU-LED-027', 'LED Bulb 9W Daylight', 'Triple8 Retail', '31', NULL, '95.00', '66.50', '100', '480888000027', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('65', 'SKU-EXT-028', 'Extension Cord 3-Gang 3M', 'Triple8 Retail', '32', NULL, '249.00', '174.30', '30', '480888000028', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('66', 'SKU-USB-029', 'USB-C Fast Charging Cable 1M', 'Triple8 Retail', '33', NULL, '120.00', '84.00', '75', '480888000029', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('67', 'SKU-MIC-030', 'Micro USB Cable 1M', 'Triple8 Retail', '33', NULL, '85.00', '59.50', '60', '480888000030', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('68', 'SKU-UNI-031', 'Universal Phone Holder Stand', 'Triple8 Retail', '33', NULL, '65.00', '45.50', '90', '480888000031', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('69', 'SKU-WIR-032', 'Wired In-Ear Earphones 3.5mm', 'Triple8 Retail', '34', NULL, '110.00', '77.00', '50', '480888000032', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('70', 'SKU-WIR-033', 'Wireless Bluetooth Earbuds', 'Triple8 Retail', '34', NULL, '450.00', '315.00', '30', '480888000033', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('71', 'SKU-POW-034', 'Power Bank 10000mAh', 'Triple8 Retail', '33', NULL, '599.00', '419.30', '25', '480888000034', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('72', 'SKU-AAR-035', 'AA Rechargeable Batteries 4s', 'Triple8 Retail', '35', NULL, '280.00', '196.00', '39', '480888000035', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('73', 'SKU-PRE-036', 'Pressed Powder Compact', 'Triple8 Retail', '36', NULL, '149.00', '104.30', '35', '480888000036', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('74', 'SKU-MAT-037', 'Matte Lipstick', 'Triple8 Retail', '36', NULL, '129.00', '90.30', '45', '480888000037', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('75', 'SKU-FAC-038', 'Facial Cleanser Foam 100ml', 'Triple8 Retail', '37', NULL, '165.00', '115.50', '40', '480888000038', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('76', 'SKU-SUN-039', 'Sunscreen Lotion SPF50 50ml', 'Triple8 Retail', '37', NULL, '220.00', '154.00', '50', '480888000039', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('77', 'SKU-HAI-040', 'Hair Dryer Portable 1000W', 'Triple8 Retail', '38', NULL, '380.00', '266.00', '20', '480888000040', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('78', 'SKU-NAI-041', 'Nail Cutter Clipper', 'Triple8 Retail', '37', NULL, '35.00', '24.50', '120', '480888000041', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('79', 'SKU-PER-042', 'Perfume Body Mist 100ml', 'Triple8 Retail', '37', NULL, '185.00', '129.50', '40', '480888000042', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('80', 'SKU-BUI-043', 'Building Blocks Toy Set', 'Triple8 Retail', '39', NULL, '299.00', '209.30', '25', '480888000043', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('81', 'SKU-STU-044', 'Stuffed Toy Medium 10-inch', 'Triple8 Retail', '39', NULL, '199.00', '139.30', '30', '480888000044', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('82', 'SKU-RUB-045', 'Rubik\'s Cube 3x3', 'Triple8 Retail', '39', NULL, '75.00', '52.50', '60', '480888000045', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('83', 'SKU-PLA-046', 'Playing Cards Deck', 'Triple8 Retail', '40', NULL, '45.00', '31.50', '100', '480888000046', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('84', 'SKU-WAT-047', 'Water Gun Toy Medium', 'Triple8 Retail', '41', NULL, '120.00', '84.00', '35', '480888000047', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('85', 'SKU-HAN-048', 'Hand Hammer 16oz', 'Triple8 Retail', '42', NULL, '185.00', '129.50', '20', '480888000048', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('86', 'SKU-SCR-049', 'Screwdriver Set 6-in-1', 'Triple8 Retail', '42', NULL, '160.00', '112.00', '30', '480888000049', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('87', 'SKU-MEA-050', 'Measuring Tape 5M', 'Triple8 Retail', '42', NULL, '95.00', '66.50', '40', '480888000050', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('88', 'SKU-ELE-051', 'Electrical Tape Black 3-Pack', 'Triple8 Retail', '43', NULL, '55.00', '38.50', '80', '480888000051', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('89', 'SKU-UTI-052', 'Utility Cutter Knife', 'Triple8 Retail', '42', NULL, '40.00', '28.00', '70', '480888000052', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:28:10', NULL),
('90', 'SKU-PLA-276', 'PLASTIC FAUCET', 'Retail', '4', NULL, '110.00', '0.00', '340', '4804809841464', 'retail_item', 'pc', '10.00', NULL, '1', NULL, NULL, '2026-08-22 08:31:20', NULL);

-- -----------------------------------------------------------------------------
-- Table structure for `profile_settings`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `profile_settings`;
CREATE TABLE `profile_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `setting_name` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_setting` (`user_id`,`setting_name`),
  CONSTRAINT `profile_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `profile_settings` (3 records)
INSERT INTO `profile_settings` (`id`, `user_id`, `setting_name`, `setting_value`, `created_at`, `updated_at`) VALUES
('1', '1', 'email_notifications', '1', '2026-03-18 07:23:05', '2026-03-18 07:23:05'),
('2', '1', 'theme', 'light', '2026-03-18 07:23:05', '2026-03-18 07:23:05'),
('3', '1', 'language', 'en', '2026-03-18 07:23:05', '2026-03-18 07:23:05');

-- -----------------------------------------------------------------------------
-- Table structure for `purchase_order_items`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_order_items`;
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_cost` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `purchase_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `purchase_orders`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `purchase_orders`;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `reference_invoice` varchar(80) DEFAULT NULL,
  `total_cost` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('pending','received','cancelled') NOT NULL DEFAULT 'received',
  `received_by` int(11) DEFAULT NULL,
  `received_at` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `purchase_orders` (2 records)
INSERT INTO `purchase_orders` (`id`, `po_number`, `supplier_id`, `reference_invoice`, `total_cost`, `status`, `received_by`, `received_at`, `notes`, `created_at`) VALUES
('1', 'PO-2026-001', '1', 'URC-INV-88901', '12450.00', 'received', '1', '2026-08-17 14:34:13', 'Weekly beverage & snack replenishment', '2026-08-20 22:34:13'),
('2', 'PO-2026-002', '4', 'PG-DEL-44120', '21800.00', 'received', '1', '2026-08-19 14:34:13', 'Personal care and laundry detergent bulk order', '2026-08-20 22:34:13');

-- -----------------------------------------------------------------------------
-- Table structure for `role_permissions`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role` varchar(50) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  KEY `idx_role` (`role`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=188 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `role_permissions` (46 records)
INSERT INTO `role_permissions` (`id`, `role`, `permission_id`, `created_at`) VALUES
('140', 'admin', '2', '2026-08-22 19:12:20'),
('141', 'cashier', '2', '2026-08-22 19:12:20'),
('142', 'admin', '1', '2026-08-22 19:12:20'),
('143', 'admin', '11', '2026-08-22 19:12:20'),
('144', 'admin', '9', '2026-08-22 19:12:20'),
('145', 'admin', '10', '2026-08-22 19:12:20'),
('146', 'admin', '25', '2026-08-22 19:12:20'),
('147', 'admin', '24', '2026-08-22 19:12:20'),
('148', 'admin', '17', '2026-08-22 19:12:20'),
('149', 'admin', '18', '2026-08-22 19:12:20'),
('150', 'admin', '23', '2026-08-22 19:12:20'),
('151', 'admin', '12', '2026-08-22 19:12:20'),
('152', 'cashier', '12', '2026-08-22 19:12:20'),
('153', 'admin', '15', '2026-08-22 19:12:20'),
('154', 'cashier', '15', '2026-08-22 19:12:20'),
('155', 'admin', '13', '2026-08-22 19:12:20'),
('156', 'cashier', '13', '2026-08-22 19:12:20'),
('157', 'admin', '14', '2026-08-22 19:12:20'),
('158', 'cashier', '14', '2026-08-22 19:12:20'),
('159', 'admin', '16', '2026-08-22 19:12:20'),
('160', 'admin', '22', '2026-08-22 19:12:20'),
('161', 'admin', '7', '2026-08-22 19:12:20'),
('162', 'admin', '8', '2026-08-22 19:12:20'),
('163', 'admin', '6', '2026-08-22 19:12:20'),
('164', 'admin', '20', '2026-08-22 19:12:20'),
('165', 'admin', '19', '2026-08-22 19:12:20'),
('166', 'admin', '21', '2026-08-22 19:12:20'),
('167', 'admin', '4', '2026-08-22 19:12:20'),
('168', 'admin', '3', '2026-08-22 19:12:20'),
('169', 'admin', '5', '2026-08-22 19:12:20'),
('172', 'manager', '1', '2026-09-01 22:56:57'),
('173', 'manager', '2', '2026-09-01 22:56:57'),
('174', 'manager', '6', '2026-09-01 22:56:57'),
('175', 'manager', '7', '2026-09-01 22:56:57'),
('176', 'manager', '8', '2026-09-01 22:56:57'),
('177', 'manager', '9', '2026-09-01 22:56:57'),
('178', 'manager', '11', '2026-09-01 22:56:57'),
('179', 'manager', '12', '2026-09-01 22:56:57'),
('180', 'manager', '13', '2026-09-01 22:56:57'),
('181', 'manager', '14', '2026-09-01 22:56:57'),
('182', 'manager', '15', '2026-09-01 22:56:57'),
('183', 'manager', '16', '2026-09-01 22:56:57'),
('184', 'manager', '17', '2026-09-01 22:56:57'),
('185', 'manager', '18', '2026-09-01 22:56:57'),
('186', 'manager', '19', '2026-09-01 22:56:57'),
('187', 'manager', '20', '2026-09-01 22:56:57');

-- -----------------------------------------------------------------------------
-- Table structure for `roles`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `roles`;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `default_dashboard` varchar(100) NOT NULL DEFAULT 'dashboard.php',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `roles` (3 records)
INSERT INTO `roles` (`id`, `role_name`, `display_name`, `description`, `is_system`, `default_dashboard`, `created_at`) VALUES
('1', 'admin', 'Administrator', 'Full system access, inventory, sales, users, settings', '1', 'admin/dashboard.php', '2026-08-15 07:16:56'),
('3', 'cashier', 'Cashier', 'POS register and transaction checkout', '1', 'cashier/pos.php', '2026-08-15 07:16:56'),
('6', 'manager', 'Store Manager', 'Store operations, inventory overview, catalog control, POS terminal, and sales reports', '1', 'admin/dashboard.php', '2026-09-01 22:56:57');

-- -----------------------------------------------------------------------------
-- Table structure for `sale_items`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(10,2) DEFAULT 0.00,
  `vatable_amount` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `sale_items_ibfk_2` (`product_id`),
  CONSTRAINT `sale_items_ibfk_1` FOREIGN KEY (`sale_id`) REFERENCES `sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sale_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `sale_items` (7 records)
INSERT INTO `sale_items` (`id`, `sale_id`, `product_id`, `quantity`, `price`, `cost_price`, `subtotal`, `vat_amount`, `vatable_amount`) VALUES
('14', '14', '42', '1', '120.00', '84.00', '120.00', '0.00', '0.00'),
('15', '15', '45', '1', '550.00', '385.00', '550.00', '0.00', '0.00'),
('16', '16', '34', '1', '149.00', '0.00', '149.00', '0.00', '0.00'),
('17', '17', '38', '1', '199.00', '139.30', '199.00', '0.00', '0.00'),
('18', '18', '38', '1', '199.00', '139.30', '199.00', '0.00', '0.00'),
('19', '19', '72', '1', '280.00', '196.00', '280.00', '0.00', '0.00'),
('20', '20', '53', '1', '129.00', '90.30', '129.00', '0.00', '0.00');

-- -----------------------------------------------------------------------------
-- Table structure for `sales`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cashier_id` int(11) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `vat_amount` decimal(10,2) DEFAULT 0.00,
  `vatable_sales` decimal(10,2) DEFAULT 0.00,
  `non_vatable_sales` decimal(10,2) DEFAULT 0.00,
  `payment` decimal(10,2) NOT NULL,
  `change_amount` decimal(10,2) NOT NULL,
  `discount_type` enum('none','senior_pwd','member','promo','custom') NOT NULL DEFAULT 'none',
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `discount_reason` text DEFAULT NULL,
  `is_voided` tinyint(1) NOT NULL DEFAULT 0,
  `voided_by` int(11) DEFAULT NULL,
  `voided_at` datetime DEFAULT NULL,
  `void_reason` text DEFAULT NULL,
  `is_return` tinyint(1) NOT NULL DEFAULT 0,
  `return_approved_by` int(11) DEFAULT NULL,
  `return_approved_at` datetime DEFAULT NULL,
  `payment_method` enum('cash','card','gcash','maya','other') NOT NULL DEFAULT 'cash',
  `payment_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `cashier_id` (`cashier_id`),
  CONSTRAINT `sales_ibfk_1` FOREIGN KEY (`cashier_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `sales` (20 records)
INSERT INTO `sales` (`id`, `cashier_id`, `customer_id`, `total_amount`, `subtotal`, `vat_amount`, `vatable_sales`, `non_vatable_sales`, `payment`, `change_amount`, `discount_type`, `discount_amount`, `discount_reason`, `is_voided`, `voided_by`, `voided_at`, `void_reason`, `is_return`, `return_approved_by`, `return_approved_at`, `payment_method`, `payment_reference`, `created_at`) VALUES
('1', '3', NULL, '78.00', '0.00', '0.00', '0.00', '0.00', '200.00', '122.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-17 23:34:04'),
('2', '3', NULL, '90.00', '0.00', '0.00', '0.00', '0.00', '500.00', '410.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-18 05:41:33'),
('3', '3', NULL, '45.00', '0.00', '0.00', '0.00', '0.00', '232414.00', '232369.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-18 06:05:32'),
('4', '3', NULL, '25.00', '0.00', '0.00', '0.00', '0.00', '30.00', '5.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-18 06:09:24'),
('5', '3', NULL, '45.00', '0.00', '0.00', '0.00', '0.00', '100.00', '55.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-18 23:01:50'),
('6', '3', NULL, '42.00', '0.00', '0.00', '0.00', '0.00', '19999.00', '19957.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-18 23:03:44'),
('7', '3', NULL, '28.00', '0.00', '3.00', '25.00', '0.00', '28.00', '0.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-18 23:22:15'),
('8', '3', NULL, '50.40', '0.00', '5.40', '45.00', '0.00', '50.40', '0.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-18 23:22:48'),
('9', '3', NULL, '47.04', '0.00', '5.04', '42.00', '0.00', '100.00', '52.96', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-19 01:26:07'),
('10', '3', NULL, '28.00', '0.00', '3.00', '25.00', '0.00', '50.00', '22.00', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-19 01:28:11'),
('11', '3', NULL, '50.40', '0.00', '5.40', '45.00', '0.00', '100.00', '49.60', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-19 02:22:03'),
('12', '3', NULL, '134.40', '0.00', '14.40', '120.00', '0.00', '143.00', '8.60', 'none', '0.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-03-20 10:58:07'),
('13', '1', '1', '225.00', '250.00', '24.11', '200.89', '0.00', '500.00', '275.00', 'promo', '25.00', NULL, '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', NULL, '2026-08-20 22:42:49'),
('14', '1', NULL, '120.00', '120.00', '12.86', '107.14', '0.00', '120.00', '0.00', 'none', '0.00', '', '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', '', '2026-08-22 09:23:48'),
('15', '1', NULL, '550.00', '550.00', '58.93', '491.07', '0.00', '800.00', '250.00', 'none', '0.00', '', '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', '', '2026-08-22 09:25:45'),
('16', '1', NULL, '149.00', '149.00', '15.96', '133.04', '0.00', '150.00', '1.00', 'none', '0.00', '', '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', '', '2026-08-22 23:32:21'),
('17', '1', NULL, '142.14', '199.00', '15.23', '126.91', '0.00', '150.00', '7.86', 'senior_pwd', '56.86', '', '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', '', '2026-08-22 23:52:51'),
('18', '1', NULL, '199.00', '199.00', '21.32', '177.68', '0.00', '200.00', '1.00', 'none', '0.00', '', '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', '', '2026-08-23 03:47:16'),
('19', '1', NULL, '200.00', '280.00', '21.43', '178.57', '0.00', '1000.00', '800.00', 'senior_pwd', '80.00', '', '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', '', '2026-09-03 00:57:54'),
('20', '1', NULL, '92.14', '129.00', '9.87', '82.27', '0.00', '292.14', '200.00', 'senior_pwd', '36.86', '', '0', NULL, NULL, NULL, '0', NULL, NULL, 'cash', '', '2026-09-03 01:33:21');

-- -----------------------------------------------------------------------------
-- Table structure for `security_firewall_logs`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `security_firewall_logs`;
CREATE TABLE `security_firewall_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `http_method` varchar(10) NOT NULL DEFAULT 'GET',
  `threat_type` varchar(50) NOT NULL,
  `threat_score` int(11) NOT NULL DEFAULT 10,
  `details` text DEFAULT NULL,
  `payload_snippet` text DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `action_taken` enum('blocked','logged','throttled','banned') NOT NULL DEFAULT 'blocked',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_threat` (`threat_type`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `security_firewall_logs` (2 records)
INSERT INTO `security_firewall_logs` (`id`, `ip_address`, `user_id`, `endpoint`, `http_method`, `threat_type`, `threat_score`, `details`, `payload_snippet`, `user_agent`, `action_taken`, `created_at`) VALUES
('1', '127.0.0.1', NULL, '/Triple8/login.php', 'GET', 'SQL_INJECTION', '40', 'UNION SELECT attack in GET:test', 'UNION SELECT 1,2,3', 'curl/7.88.1', 'blocked', '2026-08-15 07:50:15'),
('2', '127.0.0.1', NULL, '/Triple8/resend_verification.php', 'GET', 'RATE_LIMIT_EXCEEDED', '15', 'Rate limit exceeded (12 req/min)', 'Count: 13', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', 'blocked', '2026-08-19 01:04:20');

-- -----------------------------------------------------------------------------
-- Table structure for `security_ip_rules`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `security_ip_rules`;
CREATE TABLE `security_ip_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `rule_type` enum('blacklist','whitelist','autoban') NOT NULL DEFAULT 'blacklist',
  `reason` varchar(255) NOT NULL,
  `failed_count` int(11) NOT NULL DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_rule_type` (`rule_type`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `security_rate_limits`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `security_rate_limits`;
CREATE TABLE `security_rate_limits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `endpoint_hash` varchar(64) NOT NULL,
  `request_count` int(11) NOT NULL DEFAULT 1,
  `window_start` int(11) NOT NULL,
  `last_request_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_endpoint` (`ip_address`,`endpoint_hash`),
  KEY `idx_last_request` (`last_request_time`)
) ENGINE=InnoDB AUTO_INCREMENT=543 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `security_rate_limits` (3 records)
INSERT INTO `security_rate_limits` (`id`, `ip_address`, `endpoint_hash`, `request_count`, `window_start`, `last_request_time`) VALUES
('1', '127.0.0.1', 'c47460456bcc17442ec53198a9ed5c91', '8', '1787369835', '1787369892'),
('2', '127.0.0.1', '528b694a7e8eca57ab1619f3f55a5d29', '2', '1786723000', '1786723000'),
('15', '127.0.0.1', '36a9dc295ecc2e8969b54219398cb3be', '7', '1787364912', '1787364951');

-- -----------------------------------------------------------------------------
-- Table structure for `security_settings`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `security_settings`;
CREATE TABLE `security_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `security_settings` (10 records)
INSERT INTO `security_settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
('1', 'waf_mode', 'active_blocking', '2026-08-15 07:36:11'),
('2', 'rate_limit_standard', '120', '2026-08-15 07:36:11'),
('3', 'rate_limit_auth', '12', '2026-08-15 07:36:11'),
('4', 'rate_limit_api', '90', '2026-08-15 07:36:11'),
('5', 'autoban_threat_threshold', '5', '2026-08-15 07:36:11'),
('6', 'autoban_duration_hours', '24', '2026-08-15 07:36:11'),
('7', 'inspect_sqli', '1', '2026-08-15 07:36:11'),
('8', 'inspect_xss', '1', '2026-08-15 07:36:11'),
('9', 'inspect_traversal', '1', '2026-08-15 07:36:11'),
('10', 'block_bad_bots', '1', '2026-08-15 07:36:11');

-- -----------------------------------------------------------------------------
-- Table structure for `stock_adjustments`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `stock_adjustments`;
CREATE TABLE `stock_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `adjustment_type` enum('damage','expired','shrinkage','stocktake','correction') NOT NULL,
  `quantity_change` int(11) NOT NULL,
  `previous_stock` int(11) NOT NULL,
  `new_stock` int(11) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `stock_adjustments_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stock_adjustments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `suppliers`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lead_time_days` int(11) NOT NULL DEFAULT 5,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `suppliers` (7 records)
INSERT INTO `suppliers` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `status`, `created_at`, `lead_time_days`) VALUES
('1', 'Universal Robina Corporation', 'Carlos Mendoza', '+63 917 555 1001', 'sales@urc.com.ph', 'Pasig City, Metro Manila', 'active', '2026-08-20 22:32:53', '5'),
('2', 'Nestlé Philippines Inc.', 'Ma. Teresa Ramos', '+63 918 555 2002', 'orders@ph.nestle.com', 'Makati City, Metro Manila', 'active', '2026-08-20 22:32:53', '5'),
('3', 'San Miguel Foods & Beverage', 'Eduardo Santos', '+63 920 555 3003', 'distributors@sanmiguel.com.ph', 'Mandaluyong City, Metro Manila', 'active', '2026-08-20 22:32:53', '5'),
('4', 'Procter & Gamble Philippines', 'Patricia De Leon', '+63 917 555 4004', 'phorders@pg.com', 'BGC, Taguig City', 'active', '2026-08-20 22:32:53', '5'),
('5', 'Unilever Philippines', 'Antonio Cruz', '+63 919 555 5005', 'customercare@unilever.ph', 'Paco, Manila', 'active', '2026-08-20 22:32:53', '5'),
('6', 'Coca-Cola Beverages Philippines', 'Grace Villanueva', '+63 917 555 6006', 'orders@coca-cola.com.ph', 'Taguig City, Metro Manila', 'active', '2026-08-20 22:32:53', '5'),
('7', 'Monde Nissin Corporation', 'Roberto Garcia', '+63 922 555 7007', 'sales@mondenissin.com', 'Sta. Rosa, Laguna', 'active', '2026-08-20 22:32:53', '5');

-- -----------------------------------------------------------------------------
-- Table structure for `system_heartbeat`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `system_heartbeat`;
CREATE TABLE `system_heartbeat` (
  `id` int(11) NOT NULL DEFAULT 1,
  `last_ping` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `clean_shutdown` tinyint(1) NOT NULL DEFAULT 1,
  `last_shutdown_at` timestamp NULL DEFAULT NULL,
  `crash_detected_at` timestamp NULL DEFAULT NULL,
  `crash_resolved` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `system_heartbeat` (1 records)
INSERT INTO `system_heartbeat` (`id`, `last_ping`, `clean_shutdown`, `last_shutdown_at`, `crash_detected_at`, `crash_resolved`) VALUES
('1', '2026-08-27 03:40:40', '0', NULL, NULL, '1');

-- -----------------------------------------------------------------------------
-- Table structure for `system_settings`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `system_settings` (38 records)
INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_at`) VALUES
('1', 'store_name', 'Triple8 Retail Store', NULL, '2026-08-22 06:23:28'),
('2', 'store_address', 'Balingasag, Misamis Oriental', NULL, '2026-08-22 06:23:28'),
('3', 'store_phone', '(02) 8888-0888 / +63 917 888 8888', NULL, '2026-08-20 22:32:53'),
('4', 'tax_rate', '12', NULL, '2026-08-20 22:32:53'),
('5', 'currency_symbol', '₱', NULL, '2026-08-20 22:32:53'),
('26', 'vat_rate', '12', 'VAT rate in percent (e.g., 12 for 12%)', '2026-03-18 23:21:28'),
('27', 'vat_enabled', '1', 'Enable VAT calculations', '2026-03-18 23:21:28'),
('28', 'vat_description', 'VAT', 'VAT description on receipts', '2026-03-18 23:21:28'),
('29', 'non_vatable_description', 'Non-VATable', 'Non-VATable description on receipts', '2026-03-18 23:21:28'),
('40', 'max_image_size', '2048', NULL, '2026-03-20 09:18:09'),
('41', 'allowed_image_types', 'jpg,jpeg,png,gif', NULL, '2026-03-20 09:18:09'),
('42', 'product_image_width', '800', NULL, '2026-03-20 09:18:09'),
('43', 'product_image_height', '600', NULL, '2026-03-20 09:18:09'),
('55', 'store_tagline', 'Your Everyday One-Stop Supermarket', NULL, '2026-08-20 22:32:53'),
('56', 'store_email', 'store@triple8retail.com', NULL, '2026-08-20 22:32:53'),
('57', 'store_tin', '248-888-888-000-VAT', NULL, '2026-08-20 22:32:53'),
('58', 'receipt_header', 'WELCOME TO TRIPLE8 RETAIL STORE\r\nOfficial Sales Invoice / POS Receipt', NULL, '2026-08-22 06:23:28'),
('59', 'receipt_footer', 'Thank you for shopping at Triple8 Superstore!\r\nPlease present receipt for exchanges within 7 days.', NULL, '2026-08-22 06:23:28'),
('60', 'loyalty_points_rate', '100', NULL, '2026-08-20 22:32:53'),
('72', 'backup_auto_enabled', '1', NULL, '2026-08-27 03:33:49'),
('73', 'backup_frequency_minutes', '60', NULL, '2026-08-27 03:33:49'),
('74', 'backup_on_sale', '1', NULL, '2026-08-27 03:33:49'),
('75', 'backup_on_sale_interval_count', '5', NULL, '2026-08-27 03:33:49'),
('76', 'backup_retention_count', '30', NULL, '2026-08-27 03:33:49'),
('77', 'backup_compression', '1', NULL, '2026-08-27 03:33:49'),
('78', 'backup_storage_path', 'backups/', NULL, '2026-08-27 03:33:49'),
('79', 'backup_cron_token', 't8_bkp_e8f29c4701a39b542017d84a', NULL, '2026-08-27 03:33:49'),
('80', 'backup_last_run', '2026-08-26 13:40:40', NULL, '2026-08-27 03:40:40'),
('81', 'backup_power_loss_protection', '1', NULL, '2026-08-27 03:33:49'),
('87', 'smtp_enabled', '1', NULL, '2026-09-03 03:52:06'),
('88', 'smtp_host', 'smtp.gmail.com', NULL, '2026-09-03 03:52:06'),
('89', 'smtp_port', '587', NULL, '2026-09-03 03:52:06'),
('90', 'smtp_auth', '1', NULL, '2026-09-03 03:52:06'),
('91', 'smtp_username', '', NULL, '2026-09-03 03:52:06'),
('92', 'smtp_password', '', NULL, '2026-09-03 03:52:06'),
('93', 'smtp_encryption', 'tls', NULL, '2026-09-03 03:52:06'),
('94', 'smtp_from_email', '', NULL, '2026-09-03 03:52:06'),
('95', 'smtp_from_name', 'Triple8 Superstore Security', NULL, '2026-09-03 03:52:06');

-- -----------------------------------------------------------------------------
-- Table structure for `user_permissions`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `is_granted` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_permission` (`user_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- Table structure for `users`
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `email_verified` tinyint(1) NOT NULL DEFAULT 1,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'cashier',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_email` (`email`),
  KEY `idx_users_last_login` (`last_login`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users` (4 records)
INSERT INTO `users` (`id`, `username`, `email`, `email_verified`, `password`, `role`, `status`, `created_at`, `last_login`) VALUES
('1', 'admin', 'abregana.archjulian@gmail.com', '1', '$2y$10$NMjnQUVndoPmojJaBORz8.V0NS7GexOOqjXM142Icil90knKhOXd2', 'admin', 'active', '2026-03-17 10:59:39', '2026-09-03 04:24:55'),
('2', 'manager1', NULL, '1', '$2y$10$G40iTMgnwtzfUBZScNnX.OdjRXUfNsWp.WhFNG/9HEiPsv2j89iHy', 'manager', 'active', '2026-03-17 10:59:39', '2026-09-03 04:23:18'),
('3', 'cashier1', NULL, '1', '$2y$10$AqSmiNGF5dNYplacjwgugOdqLu58chdPTuRg9SnObnlTeEjM1h/ty', 'cashier', 'active', '2026-03-17 10:59:39', '2026-09-03 04:23:35'),
('5', 'inventory1', 'inventory@triple8retail.com', '1', '$2y$10$alJr6e0S7ChYO2D7IbcVvu2zMvH7SGBWsR1gTC6.upWzBsuUFj.LW', 'cashier', 'active', '2026-08-20 22:34:22', NULL);

COMMIT;
SET FOREIGN_KEY_CHECKS=1;
-- End of Triple8 Backup Dump
