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
            'SELECT article_id, saved_at
             FROM bookmarks
             WHERE user_id = ? ORDER BY saved_at DESC',
        );
        $statement->execute([$userId]);
        return $statement->fetchAll();
    }

    public function addFavorite(string $userId, string $articleId): void
    {
        $this->pdo->prepare(
            'INSERT OR IGNORE INTO bookmarks (user_id, article_id) VALUES (?, ?)',
        )->execute([$userId, $articleId]);
    }

    public function removeFavorite(string $userId, string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM bookmarks WHERE user_id = ? AND article_id = ?')
            ->execute([$userId, $articleId]);
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

    public function deleteArticleReferences(string $articleId): void
    {
        $this->pdo->prepare('DELETE FROM bookmark_folders WHERE article_id = ?')->execute([$articleId]);
        $this->pdo->prepare('DELETE FROM bookmarks WHERE article_id = ?')->execute([$articleId]);
        $this->pdo->prepare('DELETE FROM reading_history WHERE article_id = ?')->execute([$articleId]);
    }

    public function createCategory(string $userId, string $name): array
    {
        $id = bin2hex(random_bytes(16));
        $this->pdo->prepare('INSERT INTO user_categories (id, user_id, name) VALUES (?, ?, ?)')
            ->execute([$id, $userId, $name]);
        return ['id' => $id, 'name' => $name];
    }
}
