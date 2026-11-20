<?php
declare(strict_types=1);

$backendRoot = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($backendRoot): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = $backendRoot . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

$environment = App\Database\Environment::load($backendRoot . '/.env');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('news_reader_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || str_starts_with($environment['APP_URL'] ?? '', 'https://'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

return [$backendRoot, $environment];
