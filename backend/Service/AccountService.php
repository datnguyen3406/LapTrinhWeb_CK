<?php
declare(strict_types=1);

namespace App\Service;

use App\API\Http\ApiException;
use App\Database\UsersRepository;

final class AccountService
{
    public function __construct(
        private readonly UsersRepository $users,
        private readonly AuthService $auth,
        private readonly string $uploadRoot,
        private readonly int $maxUploadBytes,
    ) {
    }

    public function updateProfile(array $user, array $input): array
    {
        $name = trim((string) ($input['displayName'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ApiException('Tên hiển thị phải từ 1 đến 100 ký tự.', 422, 'VALIDATION_ERROR');
        }
        $updated = $this->users->updateProfile($user['id'], ['displayName' => $name]);
        return $this->auth->currentUser($updated)['user'];
    }

    public function changePassword(array $user, array $input): void
    {
        $current = (string) ($input['currentPassword'] ?? '');
        $next = (string) ($input['newPassword'] ?? '');
        if (!password_verify($current, $this->users->passwordHash($user['id']) ?? '')) {
            throw new ApiException('Mật khẩu hiện tại không chính xác.', 422, 'INVALID_PASSWORD');
        }
        if (strlen($next) < 8 || strlen($next) > 200) {
            throw new ApiException('Mật khẩu mới phải có ít nhất 8 ký tự.', 422, 'VALIDATION_ERROR');
        }
        $this->users->updatePassword($user['id'], password_hash($next, PASSWORD_BCRYPT));
    }

    public function updatePreferences(array $user, array $input): array
    {
        $preferences = [];
        if (array_key_exists('fontSize', $input)) {
            $size = filter_var($input['fontSize'], FILTER_VALIDATE_INT);
            if ($size === false || $size < 12 || $size > 24) {
                throw new ApiException('Cỡ chữ phải từ 12 đến 24 px.', 422, 'VALIDATION_ERROR');
            }
            $preferences['fontSize'] = $size;
        }
        if (array_key_exists('theme', $input)) {
            if (!in_array($input['theme'], ['light', 'dark', 'system'], true)) {
                throw new ApiException('Chế độ giao diện không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $preferences['theme'] = $input['theme'];
        }
        if (!$preferences) {
            throw new ApiException('Không có tùy chọn hợp lệ để cập nhật.', 422, 'VALIDATION_ERROR');
        }
        return ['preferences' => $this->users->updatePreferences($user['id'], $preferences)];
    }

    public function uploadAvatar(array $user, ?array $upload): array
    {
        if (!$upload || !isset($upload['error'], $upload['tmp_name'], $upload['size'])) {
            throw new ApiException('Thiếu tệp ảnh đại diện.', 422, 'INVALID_UPLOAD');
        }
        if ($upload['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new ApiException('Không thể nhận tệp tải lên.', 422, 'INVALID_UPLOAD');
        }
        if ($upload['size'] < 1 || $upload['size'] > $this->maxUploadBytes) {
            throw new ApiException('Ảnh đại diện vượt quá dung lượng cho phép.', 413, 'UPLOAD_TOO_LARGE');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) {
            throw new ApiException('Chỉ chấp nhận ảnh JPEG, PNG hoặc WebP.', 415, 'UNSUPPORTED_MEDIA_TYPE');
        }
        if (!is_dir($this->uploadRoot) && !mkdir($this->uploadRoot, 0770, true) && !is_dir($this->uploadRoot)) {
            throw new ApiException('Không thể tạo thư mục lưu ảnh.', 500, 'UPLOAD_STORAGE_ERROR');
        }

        $key = bin2hex(random_bytes(12));
        $path = $this->uploadRoot . '/' . $key . '.' . $extensions[$mime];
        if (!move_uploaded_file($upload['tmp_name'], $path)) {
            throw new ApiException('Không thể lưu ảnh đại diện.', 500, 'UPLOAD_STORAGE_ERROR');
        }

        $oldKey = $user['avatar_key'] ?? null;
        $updated = $this->users->updateProfile($user['id'], ['avatarKey' => $key]);
        if (!$updated) {
            unlink($path);
            throw new ApiException('Không tìm thấy tài khoản.', 404, 'NOT_FOUND');
        }
        if (is_string($oldKey) && preg_match('/^[a-f0-9]{24}$/', $oldKey)) {
            foreach (['jpg', 'png', 'webp'] as $extension) {
                $oldPath = $this->uploadRoot . '/' . $oldKey . '.' . $extension;
                if (is_file($oldPath)) {
                    unlink($oldPath);
                }
            }
        }
        return ['avatarUrl' => '/api/media/' . $key];
    }

    public function avatar(string $key): ?array
    {
        if (!preg_match('/^[a-f0-9]{24}$/', $key)) {
            return null;
        }
        foreach (['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'] as $ext => $mime) {
            $path = $this->uploadRoot . '/' . $key . '.' . $ext;
            if (is_file($path)) {
                $content = file_get_contents($path);
                if ($content === false) {
                    throw new ApiException('Không thể đọc ảnh đại diện.', 500, 'MEDIA_READ_ERROR');
                }
                return ['content' => $content, 'mime' => $mime];
            }
        }
        return null;
    }
}
