<?php
/**
 * PHPMailer & Gmail SMTP Configuration
 * Triple8 Supermarket POS & Retail System
 *
 * Reads values from .env file, with fallback defaults below.
 */

require_once __DIR__ . '/env.php';

return [
    'smtp_enabled'    => env('SMTP_ENABLED', true),
    'smtp_host'       => env('SMTP_HOST', 'smtp.gmail.com'),
    'smtp_port'       => (int)env('SMTP_PORT', 587),
    'smtp_auth'       => env('SMTP_AUTH', true),
    'smtp_username'   => env('SMTP_USER', ''),
    'smtp_password'   => env('SMTP_PASS', ''),
    'smtp_encryption' => env('SMTP_ENCRYPTION', 'tls'),
    'smtp_from_email' => env('SMTP_FROM_EMAIL', ''),
    'smtp_from_name'  => env('SMTP_FROM_NAME', 'Triple8 Superstore Security'),
    'smtp_debug'      => (int)env('SMTP_DEBUG', 0),
];
