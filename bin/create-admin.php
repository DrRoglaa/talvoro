<?php
declare(strict_types=1);

use CMS\Core\Database;

require __DIR__ . '/../bootstrap/app.php';

function ask(string $prompt): string
{
    echo $prompt;
    return trim((string)fgets(STDIN));
}

$email = mb_strtolower(ask('Super Administrator email: '));
$name = ask('Display name: ');
$password = ask('Password (minimum 14 characters): ');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Invalid email.\n");
    exit(1);
}
if (mb_strlen($name) < 2) {
    fwrite(STDERR, "Display name is too short.\n");
    exit(1);
}
$errors = \CMS\Core\PasswordPolicy::validate($password, $email, $name);
if ($errors) {
    fwrite(STDERR, implode(" ", $errors) . "\n");
    exit(1);
}

$db = Database::connection();
$roleId = $db->query(
    "SELECT id FROM roles WHERE name='super_administrator' LIMIT 1"
)->fetchColumn();

if (!$roleId) {
    fwrite(STDERR, "Run all migrations first.\n");
    exit(1);
}

$stmt = $db->prepare(
    "INSERT INTO users
     (email,password_hash,display_name,role_id,status,created_at,updated_at)
     VALUES (?,?,?,?,'active',UTC_TIMESTAMP(),UTC_TIMESTAMP())"
);

try {
    $stmt->execute([
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        $name,
        (int)$roleId,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "Could not create Super Administrator: {$e->getMessage()}\n");
    exit(1);
}

echo "Super Administrator created.\n";
