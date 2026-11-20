<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class ReaderRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function favorites(string $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT b.article_id, b.saved_at, bf.folder_id
             FROM bookmarks b
             LEFT JOIN bookmark_folders bf
               ON bf.user_id = b.user_id AND bf.article_id = b.article_id
             WHERE b.user_id = ? ORDER BY b.saved_at DESC',
        );
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function addFavorite(string $userId, string $articleId, ?string $folderId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare(
                'INSERT OR IGNORE INTO bookmarks (user_id, article_id) VALUES (?, ?)',
            )->execute([$userId, $articleId]);
            $this->pdo->prepare(
                'INSERT INTO bookmark_folders (user_id, article_id, folder_id) VALUES (?, ?, ?)
                 ON CONFLICT(user_id, article_id) DO UPDATE SET folder_id = excluded.folder_id',
            )->execute([$userId, $articleId, $folderId]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function removeFavorite(string $userId, string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM bookmarks WHERE user_id = ? AND article_id = ?')
            ->execute([$userId, $articleId]);
    }

    public function folders(string $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, created_at FROM favorite_folders WHERE user_id = ? ORDER BY created_at, name',
        );
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function createFolder(string $userId, string $name): array
    {
        $id = bin2hex(random_bytes(16));
        $this->pdo->prepare('INSERT INTO favorite_folders (id, user_id, name) VALUES (?, ?, ?)')
            ->execute([$id, $userId, $name]);
        return ['id' => $id, 'name' => $name];
    }

    public function ownsFolder(string $userId, string $folderId): bool
    {
        $statement = $this->pdo->prepare('SELECT 1 FROM favorite_folders WHERE id = ? AND user_id = ?');
        $statement->execute([$folderId, $userId]);
        return (bool) $statement->fetchColumn();
    }

    public function addHistory(string $userId, string $articleId): void
    {
        $this->pdo->prepare(
            'INSERT INTO reading_history (user_id, article_id) VALUES (?, ?)
             ON CONFLICT(user_id, article_id) DO UPDATE SET read_at = strftime(\'%Y-%m-%dT%H:%M:%fZ\', \'now\')',
        )->execute([$userId, $articleId]);
    }

    public function history(string $userId): array
    {
        $statement = $this->pdo->prepare(
            'SELECT article_id, read_at FROM reading_history WHERE user_id = ? ORDER BY read_at DESC LIMIT 100',
        );
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function clearHistory(string $userId): void
    {
        $this->pdo->prepare('DELETE FROM reading_history WHERE user_id = ?')->execute([$userId]);
    }

    public function createCategory(string $userId, string $name): array
    {
        $id = bin2hex(random_bytes(16));
        $this->pdo->prepare('INSERT INTO user_categories (id, user_id, name) VALUES (?, ?, ?)')
            ->execute([$id, $userId, $name]);
        return ['id' => $id, 'name' => $name];
    }
}
