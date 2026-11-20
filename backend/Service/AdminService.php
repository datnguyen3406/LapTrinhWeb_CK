<?php
declare(strict_types=1);

namespace App\Service;

use App\API\Http\ApiException;
use App\Database\MongoContentRepository;
use App\Database\UsersRepository;

final class AdminService
{
    public function __construct(
        private readonly UsersRepository $users,
        private readonly MongoContentRepository $content,
    ) {
    }

    public function dashboard(): array
    {
        return [
            'usersCount' => $this->users->count(),
            ...$this->content->dashboardCounts(),
        ];
    }

    public function users(array $filters): array
    {
        return ['items' => array_map(self::publicUser(...), $this->users->list($filters))];
    }

    public function activityLogs(array $filters): array
    {
        $items = $this->content->activityLogs($filters);
        $usernames = $this->users->usernamesByIds(array_column($items, 'actor_user_id'));
        return [
            'items' => array_map(static function (array $item) use ($usernames): array {
                $actorId = $item['actor_user_id'] ?? '';
                $target = $item['target'] ?? [];
                return [
                    'createdAt' => $item['created_at'] ?? null,
                    'actorUsername' => $usernames[$actorId] ?? ($item['actor_role'] ?? 'system'),
                    'action' => $item['action'] ?? '',
                    'target' => trim(($target['type'] ?? '') . ': ' . ($target['id'] ?? ''), ': '),
                    'details' => json_encode(
                        $item['details'] ?? [],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                    ) ?: '',
                ];
            }, $items),
        ];
    }

    public function updateUser(string $actorId, string $targetId, array $input): array
    {
        $target = $this->users->findById($targetId);
        if (!$target) {
            throw new ApiException('Không tìm thấy tài khoản.', 404, 'USER_NOT_FOUND');
        }
        if (!$input || array_diff(array_keys($input), ['role', 'isLocked', 'isActive'])) {
            throw new ApiException('Dữ liệu cập nhật tài khoản không hợp lệ.', 422, 'VALIDATION_ERROR');
        }
        if (array_key_exists('role', $input)) {
            if (!in_array($input['role'], ['reader', 'admin'], true)) {
                throw new ApiException('Vai trò không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
        }
        if (array_key_exists('isLocked', $input) && !is_bool($input['isLocked'])) {
            throw new ApiException('Trạng thái khóa không hợp lệ.', 422, 'VALIDATION_ERROR');
        }
        if (array_key_exists('isActive', $input) && !is_bool($input['isActive'])) {
            throw new ApiException('Trạng thái hoạt động không hợp lệ.', 422, 'VALIDATION_ERROR');
        }

        if (array_key_exists('role', $input)) {
            if ($actorId === $targetId && $input['role'] !== 'admin') {
                throw new ApiException('Bạn không thể tự hạ quyền quản trị của mình.', 409, 'LAST_ADMIN');
            }
            if ($target['role'] === 'admin' && $input['role'] !== 'admin'
                && (int) $target['is_active'] === 1 && (int) $target['is_locked'] === 0
                && $this->users->activeAdminCount() <= 1) {
                throw new ApiException('Không thể hạ quyền quản trị viên hoạt động cuối cùng.', 409, 'LAST_ADMIN');
            }
            $this->users->setRole($targetId, $input['role']);
        }
        if (array_key_exists('isLocked', $input)) {
            if ($actorId === $targetId && $input['isLocked']) {
                throw new ApiException('Bạn không thể tự khóa tài khoản của mình.', 409, 'SELF_LOCK');
            }
            if ($target['role'] === 'admin' && $input['isLocked']
                && (int) $target['is_active'] === 1 && (int) $target['is_locked'] === 0
                && $this->users->activeAdminCount() <= 1) {
                throw new ApiException('Không thể khóa quản trị viên hoạt động cuối cùng.', 409, 'LAST_ADMIN');
            }
            $this->users->setLocked($targetId, $input['isLocked']);
        }
        if (array_key_exists('isActive', $input)) {
            if ($actorId === $targetId && !$input['isActive']) {
                throw new ApiException('Bạn không thể tự vô hiệu hóa tài khoản của mình.', 409, 'SELF_INACTIVE');
            }
            if ($target['role'] === 'admin' && !$input['isActive']
                && (int) $target['is_locked'] === 0
                && $this->users->activeAdminCount() <= 1) {
                throw new ApiException('Không thể vô hiệu hóa quản trị viên hoạt động cuối cùng.', 409, 'LAST_ADMIN');
            }
            $this->users->setActive($targetId, $input['isActive']);
        }
        $this->content->recordActivity(
            $actorId,
            'admin',
            'user.update',
            'user',
            $targetId,
            array_intersect_key($input, array_flip(['role', 'isLocked', 'isActive'])),
        );
        return ['user' => self::publicUser($this->users->findById($targetId))];
    }

    public function articleList(): array
    {
        return ['items' => $this->content->adminArticles()];
    }

    public function sourceList(): array
    {
        return ['items' => $this->content->sources(true)];
    }

    private static function publicUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'displayName' => $user['display_name'],
            'role' => $user['role'],
            'isActive' => (bool) $user['is_active'],
            'isLocked' => (bool) $user['is_locked'],
        ];
    }
}
