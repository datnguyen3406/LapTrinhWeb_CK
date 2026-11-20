<?php
declare(strict_types=1);

namespace App\API\Http;

final class Response
{
    public static function success(mixed $data = null, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, string $code, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(
            ['error' => ['message' => $message, 'code' => $code]],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );
        exit;
    }

    public static function file(string $content, string $mimeType): never
    {
        header('Content-Type: ' . $mimeType);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: public, max-age=3600');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    public static function noContent(): never
    {
        http_response_code(204);
        exit;
    }
}
