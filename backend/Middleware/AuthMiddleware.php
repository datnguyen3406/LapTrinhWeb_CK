<?php
declare(strict_types=1);

namespace App\Middleware;

use App\API\Http\ApiException;
use App\API\Http\Request;
use App\Database\UsersRepository;

final class AuthMiddleware
{
    public function __construct(private readonly UsersRepository $users)
    {
    }

    public function currentUser(): ?array
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_string($id) || $id === '') {
            return null;
        }
        $user = $this->users->findById($id);
        $sessionVersion = $_SESSION['user_session_version'] ?? null;
        if (!$user
            || (int) $user['is_locked'] === 1
            || !is_int($sessionVersion)
            || $sessionVersion !== (int) $user['session_version']) {
            unset($_SESSION['user_id'], $_SESSION['user_session_version']);
            return null;
        }
        return $user;
    }

    public function requireUser(): array
    {
        return $this->currentUser()
            ?? throw new ApiException('Vui lòng đăng nhập để tiếp tục.', 401, 'UNAUTHENTICATED');
    }

    public function requireAdmin(): array
    {
        $user = $this->requireUser();
        if ($user['role'] !== 'admin' || (int) $user['is_active'] !== 1) {
            throw new ApiException('Bạn không có quyền thực hiện thao tác này.', 403, 'FORBIDDEN');
        }
        return $user;
    }

    public function verifyCsrf(Request $request): void
    {
        if (!in_array($request->method(), ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
            return;
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        $provided = $request->header('X-CSRF-Token') ?? '';
        if (!is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
            throw new ApiException('Phiên làm việc đã hết hạn. Vui lòng tải lại trang.', 419, 'CSRF_MISMATCH');
        }
    }
}
