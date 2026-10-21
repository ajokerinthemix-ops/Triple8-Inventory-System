<?php
/**
 * PHPMailer Enterprise Mail Delivery Engine
 * Triple8 Supermarket POS & Retail System
 *
 * Supports robust SMTP delivery for Gmail (smtp.gmail.com), TLS/SSL encryption,
 * Google App Passwords, HTML/Text multipart OTP codes, and email verifications.
 */

// Load PHPMailer library classes
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class SimpleMailer
{
    private static $lastError = '';
    private static $lastDebug = '';

    /**
     * Retrieve merged mailer configuration from database `system_settings` or `mail_config.php`
     */
    public static function getConfig()
    {
        $defaultConfig = [
            'smtp_enabled'    => '1',
            'smtp_host'       => 'smtp.gmail.com',
            'smtp_port'       => '587',
            'smtp_auth'       => '1',
            'smtp_username'   => '',
            'smtp_password'   => '',
            'smtp_encryption' => 'tls',
            'smtp_from_email' => '',
            'smtp_from_name'  => 'Triple8 Superstore Security',
        ];

        // Load config file defaults if present
        $configFile = __DIR__ . '/mail_config.php';
        if (file_exists($configFile)) {
            $fileConfig = require $configFile;
            if (is_array($fileConfig)) {
                $defaultConfig = array_merge($defaultConfig, $fileConfig);
            }
        }

        // Check if database is available and read system_settings
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            $conn = $GLOBALS['conn'];
            $keys = [
                'smtp_enabled', 'smtp_host', 'smtp_port', 'smtp_auth',
                'smtp_username', 'smtp_password', 'smtp_encryption',
                'smtp_from_email', 'smtp_from_name'
            ];
            $inClause = "'" . implode("','", $keys) . "'";
            $res = @$conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($inClause)");
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    if ($row['setting_value'] !== null && $row['setting_value'] !== '') {
                        $defaultConfig[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        }

        return $defaultConfig;
    }

    /**
     * Get last error message
     */
    public static function getLastError()
    {
        return self::$lastError;
    }

    /**
     * Get last debug output
     */
    public static function getLastDebug()
    {
        return self::$lastDebug;
    }

    /**
     * Initialize configured PHPMailer instance
     */
    public static function createMailer($debug = false, &$debugOutput = null)
    {
        $config = self::getConfig();
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 15;

            $smtpEnabled = filter_var($config['smtp_enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if ($smtpEnabled && !empty($config['smtp_host'])) {
                $mail->isSMTP();
                $mail->Host = trim($config['smtp_host']);
                $mail->SMTPAuth = filter_var($config['smtp_auth'] ?? true, FILTER_VALIDATE_BOOLEAN);

                if ($mail->SMTPAuth) {
                    $mail->Username = trim($config['smtp_username'] ?? '');
                    $mail->Password = trim($config['smtp_password'] ?? '');
                }

                $encryption = strtolower(trim($config['smtp_encryption'] ?? 'tls'));
                if ($encryption === 'ssl' || (int)($config['smtp_port'] ?? 0) === 465) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    $mail->Port = 465;
                } elseif ($encryption === 'tls' || (int)($config['smtp_port'] ?? 0) === 587) {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                } else {
                    $mail->SMTPSecure = false;
                    $mail->SMTPAutoTLS = false;
                    $mail->Port = (int)($config['smtp_port'] ?? 25);
                }

                // Options for local environments / self-signed certificates
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ]
                ];
            } else {
                $mail->isMail();
            }

            // Sender address
            $fromEmail = !empty($config['smtp_from_email']) ? trim($config['smtp_from_email']) : (!empty($config['smtp_username']) ? trim($config['smtp_username']) : 'noreply@triple8retail.com');
            $fromName = !empty($config['smtp_from_name']) ? trim($config['smtp_from_name']) : 'Triple8 Superstore Security';
            $mail->setFrom($fromEmail, $fromName);
            $mail->addReplyTo($fromEmail, $fromName);

            // Debug capturing if requested
            if ($debug) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = function ($str, $level) use (&$debugOutput) {
                    $debugOutput .= "[$level] $str\n";
                };
            }

            return $mail;
        } catch (Exception $e) {
            self::$lastError = $e->getMessage();
            return null;
        }
    }

    /**
     * Send email with HTML and text alternatives
     */
    public function send($to, $subject, $body, $altBody = '')
    {
        return self::sendMail($to, $subject, $body, $altBody);
    }

    /**
     * Send email using PHPMailer with error capturing and automatic fallback
     */
    public static function sendMail($to, $subject, $htmlBody, $altBody = '')
    {
        self::$lastError = '';
        self::$lastDebug = '';

        if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            self::$lastError = "Invalid recipient email address: '$to'";
            return false;
        }

        $debugOutput = '';
        $mail = self::createMailer(true, $debugOutput);

        if (!$mail) {
            self::$lastError = 'Mailer initialization failed: ' . self::$lastError;
            return false;
        }

        try {
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = !empty($altBody) ? $altBody : strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], ["\n", "\n", "\n", "\n\n"], $htmlBody));

            $sent = $mail->send();
            self::$lastDebug = $debugOutput;
            return $sent;
        } catch (Exception $e) {
            self::$lastError = $mail->ErrorInfo ?: $e->getMessage();
            self::$lastDebug = $debugOutput;
            error_log("Triple8 PHPMailer Error to [$to]: " . self::$lastError);
            return false;
        }
    }

    /**
     * Test SMTP connectivity and send a real diagnostic OTP
     */
    public static function testSmtpConnection($testRecipient, &$debugLog = null)
    {
        $debugOutput = '';
        $otp = (string)random_int(100000, 999999);
        $subject = 'Triple8 SMTP Diagnostic Test - OTP: ' . $otp;
        
        $html = "
            <div style='font-family: -apple-system, BlinkMacSystemFont, Roboto, sans-serif; max-width: 540px; margin: 0 auto; background: #ffffff; padding: 28px; border-radius: 12px; border: 1px solid #e2e8f0;'>
                <div style='text-align: center; margin-bottom: 20px;'>
                    <h2 style='color: #1e3a8a; margin: 0;'>Triple8 Superstore</h2>
                    <p style='color: #10b981; font-weight: bold; margin-top: 6px;'>&#10003; PHPMailer & SMTP Test Successful!</p>
                </div>
                <p>Hello,</p>
                <p>This is a live diagnostic email sent from the <strong>Triple8 Supermarket System</strong> to verify that your Gmail / SMTP configuration is fully operational.</p>
                <div style='background: #f1f5f9; padding: 18px; text-align: center; font-size: 32px; letter-spacing: 8px; font-weight: 800; margin: 20px 0; border-radius: 8px; color: #1e3a8a; font-family: monospace;'>
                    {$otp}
                </div>
                <p style='font-size: 13px; color: #64748b;'>Timestamp: " . date('Y-m-d H:i:s T') . "</p>
                <div style='margin-top: 24px; font-size: 12px; color: #94a3b8; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 12px;'>
                    Triple8 Retail System &bull; Email Delivery Diagnostics
                </div>
            </div>
        ";

        $mail = self::createMailer(true, $debugOutput);
        if (!$mail) {
            $debugLog = "Failed to create mailer instance: " . self::$lastError;
            return false;
        }

        try {
            $mail->addAddress($testRecipient);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = "Triple8 SMTP Test Successful! Your verification OTP code is: {$otp}";

            $result = $mail->send();
            $debugLog = $debugOutput;
            return $result;
        } catch (Exception $e) {
            $debugLog = $debugOutput . "\nException: " . $mail->ErrorInfo . "\n" . $e->getMessage();
            self::$lastError = $mail->ErrorInfo ?: $e->getMessage();
            return false;
        }
    }

    /**
     * Send OTP Verification Email
     */
    public static function sendOtpEmail($to, $otp, $expiryMinutes = 5)
    {
        $subject = 'Your Security OTP Code - Triple8 Superstore';
        
        $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #0f172a; background-color: #f8fafc; margin: 0; padding: 24px 12px; }
                    .wrapper { max-width: 540px; margin: 0 auto; background: #ffffff; padding: 36px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
                    .header { text-align: center; margin-bottom: 24px; }
                    .brand { font-size: 24px; font-weight: 800; color: #1e3a8a; letter-spacing: -0.5px; }
                    .title { font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 8px; margin-bottom: 0; }
                    .otp-box { background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); padding: 22px; text-align: center; font-size: 34px; letter-spacing: 10px; font-weight: 800; margin: 28px 0; border-radius: 10px; color: #1e3a8a; border: 2px dashed #93c5fd; font-family: 'Courier New', Courier, monospace; }
                    .warning-box { background: #fef2f2; border-left: 4px solid #ef4444; padding: 12px 16px; border-radius: 4px; color: #991b1b; font-size: 13px; margin: 20px 0; }
                    .footer { margin-top: 30px; font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 20px; }
                </style>
            </head>
            <body>
                <div class='wrapper'>
                    <div class='header'>
                        <div class='brand'>TRIPLE8 SUPERSTORE</div>
                        <h3 class='title'>One-Time Security Passcode</h3>
                    </div>
                    <p>Hello,</p>
                    <p>We received a request to verify your identity for account authentication or password update on the Triple8 Retail Portal. Use the verification code below:</p>
                    <div class='otp-box'>{$otp}</div>
                    <div class='warning-box'>
                        <strong>Security Notice:</strong> This OTP code will expire in <strong>{$expiryMinutes} minutes</strong>. Never share this code with anyone, including Triple8 staff.
                    </div>
                    <p style='font-size: 13px; color: #475569;'>If you did not initiate this request, please change your password immediately or report to your store administrator.</p>
                    <div class='footer'>
                        Triple8 Supermarket POS & Retail System &bull; Automated Security Dispatch
                    </div>
                </div>
            </body>
            </html>
        ";

        $altBody = "Your Triple8 security OTP code is: {$otp}\nThis code expires in {$expiryMinutes} minutes.\nDo not share this code with anyone.";

        return self::sendMail($to, $subject, $body, $altBody);
    }

    /**
     * Send Email Verification Link
     */
    public static function sendVerificationEmail($to, $token)
    {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || 
                    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        $baseDir = rtrim(str_replace(['/admin', '/manager', '/cashier', '/inventory_clerk'], '', $scriptDir), '/');
        
        $verifyUrl = "{$protocol}://{$host}{$baseDir}/verify_email.php?token=" . urlencode($token);
        
        $subject = 'Verify Your Email Address - Triple8 Superstore';
        $body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #0f172a; background-color: #f8fafc; margin: 0; padding: 24px 12px; }
                    .wrapper { max-width: 540px; margin: 0 auto; background: #ffffff; padding: 36px; border-radius: 12px; border: 1px solid #e2e8f0; }
                    .header { text-align: center; margin-bottom: 24px; }
                    .brand { font-size: 24px; font-weight: 800; color: #1e3a8a; }
                    .title { font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 8px; }
                    .btn-wrap { text-align: center; margin: 32px 0; }
                    .button { display: inline-block; padding: 14px 32px; background: #1e3a8a; color: #ffffff !important; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 15px; box-shadow: 0 4px 6px -1px rgba(30, 58, 138, 0.2); }
                    .footer { margin-top: 30px; font-size: 12px; color: #64748b; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 20px; word-break: break-all; }
                </style>
            </head>
            <body>
                <div class='wrapper'>
                    <div class='header'>
                        <div class='brand'>TRIPLE8 SUPERSTORE</div>
                        <h3 class='title'>Verify Your Email Address</h3>
                    </div>
                    <p>Hello,</p>
                    <p>Please click the button below to confirm and activate your email address for your Triple8 Retail Store account:</p>
                    <div class='btn-wrap'>
                        <a href='{$verifyUrl}' class='button' target='_blank'>Verify Email Address</a>
                    </div>
                    <p style='color: #64748b; font-size: 13px;'>This verification link is valid for <strong>24 hours</strong>.</p>
                    <div class='footer'>
                        If the button above does not work, copy and paste this URL into your browser:<br>
                        <a href='{$verifyUrl}' style='color: #1e3a8a;'>{$verifyUrl}</a>
                    </div>
                </div>
            </body>
            </html>
        ";

        $altBody = "Verify your Triple8 account email address by opening the following link in your browser:\n{$verifyUrl}\nThis link expires in 24 hours.";

        return self::sendMail($to, $subject, $body, $altBody);
    }
}
