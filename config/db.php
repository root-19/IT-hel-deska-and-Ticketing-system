<?php
// Database connection settings. Keep production credentials in environment variables.
function db_env(string $key, string $default = ''): string
{
    static $loaded = false;
    if (!$loaded) {
        $envFile = __DIR__ . '/../.env';
        if (is_readable($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim(trim($value), "\"'");
                if ($name !== '' && getenv($name) === false) {
                    putenv($name . '=' . $value);
                }
            }
        }
        $loaded = true;
    }
    $value = getenv($key);
    return $value === false ? $default : $value;
}

define('APP_ENV', db_env('APP_ENV', 'development'));
define('DB_HOST', db_env('DB_HOST', '127.0.0.1'));
define('DB_NAME', db_env('DB_NAME', 'helpdesk_db'));
define('DB_USER', db_env('DB_USER', 'root'));
define('DB_PASS', db_env('DB_PASS'));

if (APP_ENV === 'production' && (DB_USER === 'root' || DB_PASS === '')) {
    throw new RuntimeException('Production database credentials are not configured securely.');
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
