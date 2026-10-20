<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';

if (str_starts_with($path, '/Database/') || str_starts_with($path, '/Includes/')) {
    http_response_code(404);
    exit;
}
foreach (['/.git/', '/vendor/', '/Realtime/', '/bin/'] as $privatePath) {
    if (str_starts_with($path, $privatePath) || $path === rtrim($privatePath, '/')) {
        http_response_code(404);
        exit;
    }
}
if (preg_match('~^/(?:composer\.(?:json|lock)|api\.php|router\.php|404\.php)$~', $path) === 1) {
    http_response_code(404);
    exit;
}

if (str_starts_with($path, '/api/')) {
    $_GET['path'] = substr($path, strlen('/api/'));
    require __DIR__ . '/api.php';
    exit;
}

if ($path === '/' || $path === '/index.php') {
    require __DIR__ . '/index.php';
    exit;
}

$file = realpath(__DIR__ . $path);
if ($file !== false && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR) && is_file($file)) {
    return false;
}

http_response_code(404);
require __DIR__ . '/404.php';
