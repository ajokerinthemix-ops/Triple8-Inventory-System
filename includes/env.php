<?php
/**
 * Environment Variables Loader (.env)
 * Triple8 Supermarket POS & Retail System
 */

if (!function_exists('loadEnv')) {
    function loadEnv($path = null)
    {
        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($path) || !is_readable($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, ';') === 0) {
                continue;
            }

            // Split into name and value
            if (strpos($line, '=') !== false) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Remove surrounding quotes if present
                if (preg_match('/^"([\s\S]*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'([\s\S]*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                // Handle boolean and null values
                $lowerVal = strtolower($value);
                if ($lowerVal === 'true') {
                    $value = true;
                } elseif ($lowerVal === 'false') {
                    $value = false;
                } elseif ($lowerVal === 'null') {
                    $value = null;
                }

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
        return true;
    }
}

if (!function_exists('env')) {
    function env($key, $default = null)
    {
        $val = getenv($key);
        if ($val === false) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        if ($val === 'true' || $val === true) return true;
        if ($val === 'false' || $val === false) return false;
        if ($val === 'null' || $val === null) return null;
        return $val;
    }
}

// Auto-load .env immediately
loadEnv();
