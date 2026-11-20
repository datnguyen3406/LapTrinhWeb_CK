<?php
declare(strict_types=1);

use App\Database\Environment;
use App\Database\SqliteDatabase;
use App\Database\UsersRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$backendRoot = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($backendRoot): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $backendRoot . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

try {
    $env = Environment::load($backendRoot . '/.env');
    $username = strtolower(trim($env['BOOTSTRAP_ADMIN_USERNAME'] ?? ''));
    $name = trim($env['BOOTSTRAP_ADMIN_NAME'] ?? '');
    $password = $env['BOOTSTRAP_ADMIN_PASSWORD'] ?? '';
    if (!preg_match('/^[a-z0-9_.-]{3,32}$/', $username)
        || $name === ''
        || strlen($password) < 12) {
        throw new RuntimeException(
            'Set BOOTSTRAP_ADMIN_USERNAME, BOOTSTRAP_ADMIN_NAME and a 12+ character BOOTSTRAP_ADMIN_PASSWORD.',
        );
    }

    $database = new SqliteDatabase($backendRoot);
    $users = new UsersRepository($database->pdo());
    if ($users->activeAdminCount() > 0) {
        throw new RuntimeException('An active admin already exists; bootstrap is disabled.');
    }
    if ($users->findByUsername($username)) {
        throw new RuntimeException('This username already belongs to an account; choose a new username.');
    }
    $user = $users->create($username, $name, password_hash($password, PASSWORD_BCRYPT), true);
    $users->setRole($user['id'], 'admin');
    fwrite(STDOUT, "Created the initial admin account '{$username}'.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Admin bootstrap failed: {$error->getMessage()}\n");
    exit(1);
}
