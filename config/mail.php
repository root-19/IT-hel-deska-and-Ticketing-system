<?php
// SMTP settings for outgoing email (login OTP codes)
// Gmail: turn on 2-Step Verification, then create an App Password at
// https://myaccount.google.com/apppasswords and use it as MAIL_PASSWORD.
// Put these in the project's .env file (kept out of git), or set them as environment variables.

// Reads KEY=value lines from .env, falling back to real environment variables
function mail_env(string $key, string $default = ''): string
{
    static $env = null;

    if ($env === null) {
        $env = [];
        $file = __DIR__ . '/../.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(ltrim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $env[trim($name)] = trim(trim($value), '"\'');
            }
        }
    }

    return $env[$key] ?? (getenv($key) ?: $default);
}

define('MAIL_HOST', mail_env('MAIL_HOST', 'smtp.gmail.com'));
define('MAIL_PORT', (int) mail_env('MAIL_PORT', '587'));
define('MAIL_ENCRYPTION', mail_env('MAIL_ENCRYPTION', 'tls')); // 'tls' (port 587) or 'ssl' (port 465)
define('MAIL_USERNAME', mail_env('MAIL_USERNAME'));
define('MAIL_PASSWORD', mail_env('MAIL_PASSWORD'));
define('MAIL_FROM_ADDRESS', mail_env('MAIL_FROM_ADDRESS', MAIL_USERNAME));
define('MAIL_FROM_NAME', mail_env('MAIL_FROM_NAME', 'ICCT IT Help Desk'));

// Login one-time passwords
define('OTP_LENGTH', 6);
define('OTP_TTL', 300);           // seconds a code stays valid
define('OTP_MAX_ATTEMPTS', 5);    // wrong codes allowed before the login must restart
define('OTP_RESEND_COOLDOWN', 60); // seconds between resends
