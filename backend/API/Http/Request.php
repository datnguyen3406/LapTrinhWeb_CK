<?php
declare(strict_types=1);

namespace App\API\Http;

final class Request
{
    private ?array $decodedBody = null;
    private bool $bodyDecoded = false;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = preg_replace('#^/api/?#', '', $path) ?? '';
        return trim($path, '/');
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $_GET[$key] ?? $default;
        return is_string($value) ? trim($value) : $default;
    }

    public function body(): array
    {
        if ($this->bodyDecoded) {
            return $this->decodedBody ?? [];
        }
        $this->bodyDecoded = true;
        $raw = file_get_contents('php://input');
        if ($raw === false || trim($raw) === '') {
            return $this->decodedBody = [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !str_starts_with(ltrim($raw), '{')) {
            throw new ApiException('Dữ liệu JSON không hợp lệ.', 400, 'INVALID_JSON');
        }
        return $this->decodedBody = $decoded;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function file(string $name): ?array
    {
        $file = $_FILES[$name] ?? null;
        return is_array($file) ? $file : null;
    }
}
