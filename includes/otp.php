<?php
/**
 * OTP, Password Reset, and Email Verification Engine
 * Triple8 Supermarket POS & Retail System
 */

require_once __DIR__ . '/db.php';

/**
 * Generate a cryptographically secure 6-digit OTP code
 */
function generateOtp($length = 6)
{
    return str_pad((string)random_int(100000, 999999), $length, '0', STR_PAD_LEFT);
}

/**
 * Generate a cryptographically secure random token (64 hex characters from 32 bytes)
 */
function generateToken($bytes = 32)
{
    return bin2hex(random_bytes($bytes));
}

/**
 * Store a password reset OTP for a user (invalidates previous active OTPs)
 */
function storePasswordResetOtp($userId, $expiryMinutes = 5)
{
    global $conn;
    if (!$conn) return false;

    $userId = (int)$userId;
    if ($userId <= 0) return false;

    // Invalidate any existing active OTPs for this user
    $invStmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0");
    if ($invStmt) {
        $invStmt->bind_param("i", $userId);
        $invStmt->execute();
        $invStmt->close();
    }

    $otp = generateOtp(6);
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));
    
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, otp_code, expires_at, used) VALUES (?, ?, ?, 0)");
    if (!$stmt) return false;

    $stmt->bind_param("iss", $userId, $otp, $expiresAt);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result ? $otp : false;
}

/**
 * Verify a password reset OTP with brute-force rate limit protection
 */
function verifyPasswordResetOtp($userId, $otp)
{
    global $conn;
    if (!$conn) return ['valid' => false, 'message' => 'Database connection unavailable.'];

    $userId = (int)$userId;
    $otp = trim((string)$otp);

    if ($userId <= 0 || empty($otp)) {
        return ['valid' => false, 'message' => 'Invalid OTP request.'];
    }

    // Rate limiting for OTP verification attempts (session-based attempt tracking)
    $rateKey = 'otp_verify_attempts_' . $userId;
    $attempts = $_SESSION[$rateKey] ?? 0;
    $lastAttempt = $_SESSION[$rateKey . '_time'] ?? 0;

    // Reset attempt counter if more than 15 minutes passed
    if (time() - $lastAttempt > 900) {
        $attempts = 0;
    }

    if ($attempts >= 5) {
        return ['valid' => false, 'message' => 'Too many failed OTP attempts. Please request a new OTP.'];
    }

    $stmt = $conn->prepare("SELECT id, otp_code, expires_at, used FROM password_resets WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    if (!$stmt) {
        return ['valid' => false, 'message' => 'Failed to verify OTP.'];
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();
    
    if (!$record) {
        $_SESSION[$rateKey] = $attempts + 1;
        $_SESSION[$rateKey . '_time'] = time();
        return ['valid' => false, 'message' => 'No active OTP found. Please request a new code.'];
    }
    
    if ($record['used']) {
        return ['valid' => false, 'message' => 'This OTP has already been used.'];
    }
    
    if (strtotime($record['expires_at']) < time()) {
        return ['valid' => false, 'message' => 'This OTP has expired. Please request a new one.'];
    }

    if (!hash_equals((string)$record['otp_code'], $otp)) {
        $_SESSION[$rateKey] = $attempts + 1;
        $_SESSION[$rateKey . '_time'] = time();
        $remaining = max(0, 5 - ($attempts + 1));
        return ['valid' => false, 'message' => 'Invalid OTP code. ' . ($remaining > 0 ? "($remaining attempts remaining)" : '')];
    }
    
    // Reset attempt counter on success
    unset($_SESSION[$rateKey], $_SESSION[$rateKey . '_time']);

    return ['valid' => true, 'message' => 'OTP is valid.'];
}

/**
 * Mark password reset OTP as used
 */
function markPasswordResetOtpUsed($userId, $otp)
{
    global $conn;
    if (!$conn) return false;

    $userId = (int)$userId;
    $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND otp_code = ?");
    if (!$stmt) return false;

    $stmt->bind_param("is", $userId, $otp);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Clean up expired password reset OTPs
 */
function cleanExpiredPasswordResets()
{
    global $conn;
    if (!$conn) return;

    $stmt = $conn->prepare("DELETE FROM password_resets WHERE expires_at < NOW() OR used = 1");
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

/**
 * Store an email verification token (64 hex characters)
 */
function storeEmailVerification($userId, $email, $expiryHours = 24)
{
    global $conn;
    if (!$conn) return false;

    $userId = (int)$userId;
    $email = trim((string)$email);

    // Invalidate existing pending verification tokens for this user
    $cleanStmt = $conn->prepare("DELETE FROM email_verifications WHERE user_id = ?");
    if ($cleanStmt) {
        $cleanStmt->bind_param("i", $userId);
        $cleanStmt->execute();
        $cleanStmt->close();
    }

    $token = generateToken(32); // 64 hex characters exactly
    $expiresAt = date('Y-m-d H:i:s', time() + ($expiryHours * 3600));
    
    $stmt = $conn->prepare("INSERT INTO email_verifications (user_id, email, token, expires_at) VALUES (?, ?, ?, ?)");
    if (!$stmt) return false;

    $stmt->bind_param("isss", $userId, $email, $token, $expiresAt);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result ? $token : false;
}

/**
 * Verify email with token
 */
function verifyEmailWithToken($token)
{
    global $conn;
    if (!$conn) return ['valid' => false, 'message' => 'Database connection unavailable.'];

    $token = trim((string)$token);
    if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
        return ['valid' => false, 'message' => 'Invalid or malformed verification token.'];
    }
    
    $stmt = $conn->prepare("SELECT id, user_id, email, expires_at FROM email_verifications WHERE token = ?");
    if (!$stmt) return ['valid' => false, 'message' => 'Verification query failed.'];

    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $record = $result->fetch_assoc();
    $stmt->close();
    
    if (!$record) {
        return ['valid' => false, 'message' => 'Invalid verification token or link already used.'];
    }
    
    if (strtotime($record['expires_at']) < time()) {
        // Delete expired token
        $del = $conn->prepare("DELETE FROM email_verifications WHERE id = ?");
        if ($del) {
            $del->bind_param("i", $record['id']);
            $del->execute();
            $del->close();
        }
        return ['valid' => false, 'message' => 'This verification link has expired. Please request a new one.'];
    }
    
    // Mark user as verified
    $updateStmt = $conn->prepare("UPDATE users SET email_verified = 1, email = ? WHERE id = ?");
    if ($updateStmt) {
        $updateStmt->bind_param("si", $record['email'], $record['user_id']);
        $updateStmt->execute();
        $updateStmt->close();
    }
    
    // Delete used verification record immediately
    $deleteStmt = $conn->prepare("DELETE FROM email_verifications WHERE id = ?");
    if ($deleteStmt) {
        $deleteStmt->bind_param("i", $record['id']);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    
    return ['valid' => true, 'message' => 'Email verified successfully!', 'user_id' => $record['user_id']];
}

/**
 * Check if user has verified email
 */
function isEmailVerified($userId)
{
    global $conn;
    if (!$conn) return false;

    $userId = (int)$userId;
    $stmt = $conn->prepare("SELECT email_verified FROM users WHERE id = ?");
    if (!$stmt) return false;

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user ? (bool)$user['email_verified'] : false;
}
