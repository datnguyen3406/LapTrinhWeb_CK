<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class UsersRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, display_name, avatar_key, is_active, is_locked,
                    session_version
             FROM users WHERE username = ? COLLATE NOCASE',
        );
        $statement->execute([$username]);
        return $statement->fetch() ?: null;
    }

    public function findById(string $id): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, role, display_name, avatar_key, is_active, is_locked, session_version
             FROM users WHERE id = ?',
        );
        $statement->execute([$id]);
        return $statement->fetch() ?: null;
    }

    public function passwordHash(string $id): ?string
    {
        $statement = $this->pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $statement->execute([$id]);
        $hash = $statement->fetchColumn();
        return $hash === false ? null : (string) $hash;
    }

    public function create(
        string $username,
        string $displayName,
        string $passwordHash,
        bool $isActive = true,
    ): array
    {
        $id = self::uuid();
        $recoveryHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (id, username, password_hash, recovery_key_hash, role, display_name, is_active)
                 VALUES (?, ?, ?, ?, \'reader\', ?, ?)',
            );
            $statement->execute([$id, $username, $passwordHash, $recoveryHash, $displayName, $isActive ? 1 : 0]);
            $this->pdo->prepare('INSERT INTO user_preferences (user_id) VALUES (?)')->execute([$id]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
        return $this->findById($id) ?? throw new \RuntimeException('Không thể tạo tài khoản.');
    }

    public function updateProfile(string $id, array $fields): ?array
    {
        $sets = [];
        $values = [];
        if (array_key_exists('displayName', $fields)) {
            $sets[] = 'display_name = ?';
            $values[] = $fields['displayName'];
        }
        if (array_key_exists('avatarKey', $fields)) {
            $sets[] = 'avatar_key = ?';
            $values[] = $fields['avatarKey'];
        }
        if ($sets) {
            $values[] = $id;
            $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')
                ->execute($values);
        }
        return $this->findById($id);
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([$passwordHash, $id]);
    }

    public function updatePasswordAndInvalidateSessions(string $id, string $passwordHash): void
    {
        $this->pdo->prepare(
            'UPDATE users
             SET password_hash = ?, session_version = session_version + 1
             WHERE id = ?',
        )->execute([$passwordHash, $id]);
    }

    public function setActive(string $id, bool $isActive): void
    {
        $this->pdo->prepare('UPDATE users SET is_active = ? WHERE id = ?')
            ->execute([$isActive ? 1 : 0, $id]);
    }

    public function deleteById(string $id): void
    {
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
    }

    public function setRole(string $id, string $role): void
    {
        $this->pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $id]);
    }

    public function setLocked(string $id, bool $locked): void
    {
        $this->pdo->prepare('UPDATE users SET is_locked = ? WHERE id = ?')->execute([$locked ? 1 : 0, $id]);
    }

    public function list(array $filters = []): array
    {
        $where = [];
        $parameters = [];
        if (isset($filters['q']) && $filters['q'] !== '') {
            $where[] = '(username LIKE ? OR display_name LIKE ?)';
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']) . '%';
            $parameters[] = $term;
            $parameters[] = $term;
        }
        if (isset($filters['role']) && in_array($filters['role'], ['reader', 'admin'], true)) {
            $where[] = 'role = ?';
            $parameters[] = $filters['role'];
        }
        $sql = 'SELECT id, username, role, display_name, avatar_key, is_active, is_locked, created_at
                FROM users';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT 100';
        $statement = $this->pdo->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }

    public function usernamesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, 'is_string')));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $statement = $this->pdo->prepare(
            "SELECT id, username FROM users WHERE id IN ($placeholders)",
        );
        $statement->execute($ids);
        $usernames = [];
        foreach ($statement->fetchAll() as $user) {
            $usernames[$user['id']] = $user['username'];
        }
        return $usernames;
    }

    public function activeAdminCount(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND is_locked = 0",
        )->fetchColumn();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    public function updatePreferences(string $userId, array $preferences): array
    {
        $sets = [];
        $values = [];
        if (array_key_exists('fontSize', $preferences)) {
            $sets[] = 'font_size_px = ?';
            $values[] = $preferences['fontSize'];
        }
        if (array_key_exists('theme', $preferences)) {
            if ($preferences['theme'] === 'system') {
                $sets[] = 'system_theme = 1';
            } else {
                $sets[] = 'theme = ?';
                $values[] = $preferences['theme'];
                $sets[] = 'system_theme = 0';
            }
        }
        if ($sets) {
            $values[] = $userId;
            $this->pdo->prepare(
                'UPDATE user_preferences SET ' . implode(', ', $sets) . ' WHERE user_id = ?',
            )->execute($values);
        }
        $statement = $this->pdo->prepare(
            'SELECT font_size_px, theme, system_theme FROM user_preferences WHERE user_id = ?',
        );
        $statement->execute([$userId]);
        $row = $statement->fetch() ?: ['font_size_px' => 16, 'theme' => 'light', 'system_theme' => 0];
        return [
            'fontSize' => (int) $row['font_size_px'],
            'theme' => (int) $row['system_theme'] === 1 ? 'system' : $row['theme'],
        ];
    }

    public function preferences(string $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT font_size_px, theme, system_theme FROM user_preferences WHERE user_id = ?',
        );
        $statement->execute([$userId]);
        $row = $statement->fetch() ?: ['font_size_px' => 16, 'theme' => 'light', 'system_theme' => 0];
        return [
            'fontSize' => (int) $row['font_size_px'],
            'theme' => (int) $row['system_theme'] === 1 ? 'system' : $row['theme'],
        ];
    }

    public function setPasswordResetToken(string $userId, string $tokenHash, string $expiresAt): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)
             ON CONFLICT(user_id) DO UPDATE SET token_hash = excluded.token_hash, expires_at = excluded.expires_at',
        );
        $statement->execute([$userId, $tokenHash, $expiresAt]);
    }

    public function consumePasswordResetToken(string $tokenHash): ?string
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare(
                'SELECT user_id FROM password_reset_tokens WHERE token_hash = ? AND expires_at > ?',
            );
            $statement->execute([$tokenHash, gmdate('Y-m-d\TH:i:s\Z')]);
            $userId = $statement->fetchColumn();
            if ($userId !== false) {
                $this->pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = ?')->execute([$userId]);
            }
            $this->pdo->commit();
            return $userId === false ? null : (string) $userId;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
