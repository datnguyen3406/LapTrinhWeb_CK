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
    if (($env['APP_ENV'] ?? 'production') !== 'development') {
        fwrite(STDOUT, "Demo admin seed skipped outside development.\n");
        exit(0);
    }

    $database = new SqliteDatabase($backendRoot);
    $users = new UsersRepository($database->pdo());
    if ($users->activeAdminCount() > 0) {
        fwrite(STDOUT, "An active admin already exists; demo seed skipped.\n");
        exit(0);
    }
    if ($users->findByUsername('admin')) {
        throw new RuntimeException('Username admin is already in use; no demo account was changed.');
    }

    $user = $users->create(
        'admin',
        'Site Admin',
        password_hash('Admin@123456', PASSWORD_BCRYPT),
    );
    $users->setRole($user['id'], 'admin');
    fwrite(STDOUT, "Created development admin: admin / Admin@123456\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Demo admin seed failed: {$error->getMessage()}\n");
    exit(1);
}
