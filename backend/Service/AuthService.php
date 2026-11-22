<?php
declare(strict_types=1);

namespace App\Service;

use App\API\Http\ApiException;
use App\Database\Environment;
use App\Database\UsersRepository;

final class AuthService
{
    private array $config;

    public function __construct(private readonly UsersRepository $users, string $backendRoot)
    {
        $this->config = Environment::load($backendRoot . '/.env');
    }

    public function register(array $input): array
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $name = trim((string) ($input['displayName'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $this->validateUsername($username);
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ApiException('Tên hiển thị phải từ 1 đến 100 ký tự.', 422, 'VALIDATION_ERROR');
        }
        $this->validatePassword($password);
        if ($this->users->findByUsername($username)) {
            throw new ApiException('Tên đăng nhập đã được sử dụng.', 409, 'USERNAME_EXISTS');
        }

        try {
            $created = $this->users->create($username, $name, password_hash($password, PASSWORD_BCRYPT));
            $user = $created['user'];
        } catch (\PDOException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unique')) {
                throw new ApiException('Tên đăng nhập đã được sử dụng.', 409, 'USERNAME_EXISTS');
            }
            throw $error;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_session_version'] = (int) $user['session_version'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return [
            'user' => $this->publicUser($user),
            'recoveryCode' => $created['recoveryCode'],
            'csrfToken' => $_SESSION['csrf_token'],
        ];
    }

    public function login(array $input): array
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $user = $this->users->findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new ApiException('Tên đăng nhập hoặc mật khẩu không chính xác.', 401, 'INVALID_CREDENTIALS');
        }
        if ((int) $user['is_locked'] === 1) {
            throw new ApiException('Tài khoản đã bị khóa.', 403, 'ACCOUNT_LOCKED');
        }
        if ((int) $user['is_active'] !== 1) {
            throw new ApiException('Tài khoản đã bị vô hiệu hóa.', 403, 'ACCOUNT_INACTIVE');
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_session_version'] = (int) $user['session_version'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return ['user' => $this->publicUser($user), 'csrfToken' => $_SESSION['csrf_token']];
    }

    public function requestPasswordReset(string $username): array
    {
        $user = $this->users->findByUsername($username);
        if (!$user) {
            throw new ApiException('Không tìm thấy tài khoản.', 404, 'USER_NOT_FOUND');
        }
        return [
            'success' => true,
            'message' => 'Vui lòng sử dụng mã khôi phục cá nhân của bạn để đặt lại mật khẩu.',
        ];
    }

    public function generateRecoveryCode(string $userId): array
    {
        $token = bin2hex(random_bytes(32));
        $this->users->setRecoveryKeyHash($userId, password_hash($token, PASSWORD_DEFAULT));
        return [
            'recoveryCode' => $token,
            'message' => 'Mã khôi phục mới đã được tạo. Lưu mã này ở nơi an toàn.',
        ];
    }

    public function confirmPasswordReset(array $input): array
    {
        $username = strtolower(trim((string) ($input['username'] ?? '')));
        $recoveryKey = (string) ($input['recoveryKey'] ?? $input['recoveryCode'] ?? $input['resetCode'] ?? $input['token'] ?? '');
        $password = (string) ($input['password'] ?? $input['newPassword'] ?? '');
        $this->validateUsername($username);
        $this->validatePassword($password);
        $user = $this->users->findByUsername($username);
        if (!$user || !$this->users->verifyRecoveryKey($user['id'], $recoveryKey)) {
            throw new ApiException('Tên đăng nhập hoặc mã khôi phục không chính xác.', 400, 'INVALID_RECOVERY_KEY');
        }
        $newRecoveryCode = $this->users->resetPasswordWithRecoveryKey(
            $user['id'],
            password_hash($password, PASSWORD_BCRYPT),
        );
        return ['recoveryCode' => $newRecoveryCode];
    }

    public function currentUser(array $user): array
    {
        return ['user' => $this->publicUser($user)];
    }

    private function publicUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'displayName' => $user['display_name'],
            'role' => $user['role'],
            'isActive' => (bool) $user['is_active'],
            'isLocked' => (bool) $user['is_locked'],
            'avatarUrl' => $user['avatar_key'] ? '/api/media/' . $user['avatar_key'] : null,
            'preferences' => $this->users->preferences($user['id']),
        ];
    }

    private function validatePassword(string $password): void
    {
        if (strlen($password) < 8 || strlen($password) > 200) {
            throw new ApiException('Mật khẩu phải có ít nhất 8 ký tự.', 422, 'VALIDATION_ERROR');
        }
    }

    private function validateUsername(string $username): void
    {
        if (!preg_match('/^[a-z0-9_.-]{3,32}$/', $username)) {
            throw new ApiException(
                'Tên đăng nhập phải dài 3-32 ký tự và chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.',
                422,
                'VALIDATION_ERROR',
            );
        }
    }
}
