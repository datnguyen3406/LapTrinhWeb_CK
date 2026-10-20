<?php

declare(strict_types=1);

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function request_json(): array
{
    $body = file_get_contents('php://input');
    if ($body === false || $body === '') {
        return [];
    }

    try {
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['error' => 'Dữ liệu JSON không hợp lệ.'], 400);
    }
    if (!is_array($decoded)) {
        json_response(['error' => 'Nội dung yêu cầu phải là một đối tượng JSON.'], 400);
    }
    return $decoded;
}

function require_method(string ...$methods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        json_response(['error' => 'Phương thức không được hỗ trợ.'], 405);
    }
}

function require_csrf(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_response(['error' => 'Phiên làm việc hết hạn. Hãy tải lại trang và thử lại.'], 419);
    }
}

function current_user(): ?array
{
    global $pdo;
    $id = $_SESSION['user_id'] ?? null;
    if (!is_int($id)) {
        return null;
    }
    $statement = $pdo->prepare('SELECT id, email, display_name, avatar_url FROM users WHERE id = :id');
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();
    return $user === false ? null : $user;
}

function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(['error' => 'Vui lòng đăng nhập để tiếp tục.'], 401);
    }
    return $user;
}

function enqueue_job(string $type, array $payload): int
{
    global $pdo;
    $statement = $pdo->prepare('INSERT INTO jobs (type, payload) VALUES (:type, :payload)');
    $statement->execute([
        'type' => $type,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
    ]);
    return (int) $pdo->lastInsertId();
}
