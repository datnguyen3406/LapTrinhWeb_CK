<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($requestPath === '/api' || str_starts_with($requestPath, '/api/')) {
    require __DIR__ . '/API/index.php';
    return true;
}

if ($requestPath === '/') {
    header('Location: /pages/index.html', true, 302);
    return true;
}

$frontendRoot = realpath($projectRoot . '/frontend');
$file = realpath($projectRoot . '/frontend' . rawurldecode($requestPath));
if ($frontendRoot && $file && str_starts_with($file, $frontendRoot . DIRECTORY_SEPARATOR) && is_file($file)) {
    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'html' => 'text/html; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'svg' => 'image/svg+xml',
        'webmanifest' => 'application/manifest+json',
    ];
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = $mimeTypes[$extension] ?? mime_content_type($file);
    if (!$mime) {
        $mime = 'application/octet-stream';
    }
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    readfile($file);
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
return true;
