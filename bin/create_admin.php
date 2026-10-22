<?php
// Run from the command line with ADMIN_* environment variables set.
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$email = strtolower(trim((string) getenv('ADMIN_EMAIL')));
$password = (string) getenv('ADMIN_PASSWORD');
$idNumber = trim((string) (getenv('ADMIN_ID_NUMBER') ?: 'ADMIN-0001'));
$firstName = trim((string) (getenv('ADMIN_FIRST_NAME') ?: 'System'));
$lastName = trim((string) (getenv('ADMIN_LAST_NAME') ?: 'Administrator'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
    fwrite(STDERR, "Set a valid ADMIN_EMAIL and an ADMIN_PASSWORD with at least 12 characters, uppercase, lowercase, and a number.\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9-]{4,30}$/', $idNumber)) {
    fwrite(STDERR, "ADMIN_ID_NUMBER must contain only letters, numbers, and dashes.\n");
    exit(1);
}

$stmt = db()->prepare("INSERT INTO users (id_number, first_name, last_name, email, role, department, status, password_hash) VALUES (?, ?, ?, ?, 'admin', 'IT Department', 'active', ?)");
$stmt->execute([$idNumber, $firstName, $lastName, $email, password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Administrator created for {$email}.\n");
