<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class ArticlesRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $articleId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.article_id, a.title, a.slug, a.summary, a.author_id, a.category_id,
                    a.source_id, a.thumbnail_url, a.publication_status, a.content_sync_status,
                    a.revision, a.published_at, a.created_at, a.updated_at,
                    c.name AS category_name, c.slug AS category_slug,
                    u.display_name AS author_name, u.username AS author_username
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.article_id = ?',
        );
        $stmt->execute([$articleId]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.article_id, a.title, a.slug, a.summary, a.author_id, a.category_id,
                    a.source_id, a.thumbnail_url, a.publication_status, a.content_sync_status,
                    a.revision, a.published_at, a.created_at, a.updated_at,
                    c.name AS category_name, c.slug AS category_slug,
                    u.display_name AS author_name, u.username AS author_username
             FROM articles a
             LEFT JOIN categories c ON c.id = a.category_id
             LEFT JOIN users u ON u.id = a.author_id
             WHERE a.slug = ? COLLATE NOCASE',
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): array
    {
        $articleId = $data['article_id'] ?? self::uuid();
        $title = $data['title'];
        $slug = $data['slug'];
        $summary = $data['summary'] ?? null;
        $authorId = $data['author_id'];
        $categoryId = $data['category_id'] ?? null;
        $sourceId = $data['source_id'] ?? null;
        $thumbnailUrl = $data['thumbnail_url'] ?? null;
        $pubStatus = $data['publication_status'] ?? 'DRAFT';
        $syncStatus = $data['content_sync_status'] ?? 'PENDING';
        $publishedAt = $pubStatus === 'PUBLISHED' ? gmdate('Y-m-d\TH:i:s\Z') : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO articles (
                article_id, title, slug, summary, author_id, category_id, source_id,
                thumbnail_url, publication_status, content_sync_status, revision, published_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)',
        );
        $stmt->execute([
            $articleId, $title, $slug, $summary, $authorId, $categoryId, $sourceId,
            $thumbnailUrl, $pubStatus, $syncStatus, $publishedAt,
        ]);
        return $this->findById($articleId) ?? throw new \RuntimeException('Không thể tạo bài viết.');
    }

    public function update(string $articleId, array $data, ?int $expectedRevision = null): ?array
    {
        $sets = [];
        $values = [];

        if (array_key_exists('title', $data)) {
            $sets[] = 'title = ?';
            $values[] = $data['title'];
        }
        if (array_key_exists('slug', $data)) {
            $sets[] = 'slug = ?';
            $values[] = $data['slug'];
        }
        if (array_key_exists('summary', $data)) {
            $sets[] = 'summary = ?';
            $values[] = $data['summary'];
        }
        if (array_key_exists('category_id', $data)) {
            $sets[] = 'category_id = ?';
            $values[] = $data['category_id'];
        }
        if (array_key_exists('source_id', $data)) {
            $sets[] = 'source_id = ?';
            $values[] = $data['source_id'];
        }
        if (array_key_exists('thumbnail_url', $data)) {
            $sets[] = 'thumbnail_url = ?';
            $values[] = $data['thumbnail_url'];
        }
        if (array_key_exists('publication_status', $data)) {
            $sets[] = 'publication_status = ?';
            $values[] = $data['publication_status'];
            if ($data['publication_status'] === 'PUBLISHED') {
                $sets[] = "published_at = COALESCE(published_at, strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))";
            }
        }
        if (array_key_exists('content_sync_status', $data)) {
            $sets[] = 'content_sync_status = ?';
            $values[] = $data['content_sync_status'];
        }

        $sets[] = 'revision = revision + 1';
        $sets[] = "updated_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now')";

        $sql = 'UPDATE articles SET ' . implode(', ', $sets) . ' WHERE article_id = ?';
        $values[] = $articleId;

        if ($expectedRevision !== null) {
            $sql .= ' AND revision = ?';
            $values[] = $expectedRevision;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($values);

        if ($expectedRevision !== null && $stmt->rowCount() === 0) {
            // Check if article existed with different revision
            $check = $this->findById($articleId);
            if ($check && (int) $check['revision'] !== $expectedRevision) {
                return null; // Signals concurrency revision conflict!
            }
        }

        return $this->findById($articleId);
    }

    public function setStatus(
        string $articleId,
        ?string $publicationStatus = null,
        ?string $contentSyncStatus = null,
    ): ?array {
        $sets = [];
        $values = [];
        if ($publicationStatus !== null) {
            $sets[] = 'publication_status = ?';
            $values[] = $publicationStatus;
            if ($publicationStatus === 'PUBLISHED') {
                $sets[] = "published_at = COALESCE(published_at, strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))";
            }
        }
        if ($contentSyncStatus !== null) {
            $sets[] = 'content_sync_status = ?';
            $values[] = $contentSyncStatus;
        }
        if (empty($sets)) {
            return $this->findById($articleId);
        }
        $sets[] = "updated_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now')";
        $values[] = $articleId;

        $stmt = $this->pdo->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE article_id = ?');
        $stmt->execute($values);
        return $this->findById($articleId);
    }

    public function delete(string $articleId, bool $permanent = false): bool
    {
        if ($permanent) {
            $stmt = $this->pdo->prepare('DELETE FROM articles WHERE article_id = ?');
            $stmt->execute([$articleId]);
            return $stmt->rowCount() > 0;
        }

        $stmt = $this->pdo->prepare(
            "UPDATE articles
             SET publication_status = 'DELETED', updated_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now')
             WHERE article_id = ?",
        );
        $stmt->execute([$articleId]);
        return $stmt->rowCount() > 0;
    }

    public function listPublic(array $filters = []): array
    {
        $where = ["a.publication_status = 'PUBLISHED'", "a.content_sync_status = 'READY'"];
        $params = [];

        if (!empty($filters['category'])) {
            $cat = (string) $filters['category'];
            $where[] = '(c.slug = ? OR c.id = ?)';
            $params[] = $cat;
            $params[] = $cat;
        }
        if (!empty($filters['source'])) {
            $where[] = 'a.source_id = ?';
            $params[] = (string) $filters['source'];
        }
        if (!empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']) . '%';
            $where[] = '(a.title LIKE ? OR a.summary LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        $sql = 'SELECT a.article_id, a.title, a.slug, a.summary, a.author_id, a.category_id,
                       a.source_id, a.thumbnail_url, a.publication_status, a.content_sync_status,
                       a.revision, a.published_at, a.created_at, a.updated_at,
                       c.name AS category_name, c.slug AS category_slug,
                       u.display_name AS author_name
                FROM articles a
                LEFT JOIN categories c ON c.id = a.category_id
                LEFT JOIN users u ON u.id = a.author_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.published_at DESC, a.created_at DESC';

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 50)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $sql .= " LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function listAdmin(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'a.publication_status = ?';
            $params[] = (string) $filters['status'];
        } else {
            // By default don't hide deleted in admin unless specified
            $where[] = "a.publication_status != 'DELETED'";
        }

        if (!empty($filters['q'])) {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['q']) . '%';
            $where[] = '(a.title LIKE ? OR a.summary LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }

        $sql = 'SELECT a.article_id, a.title, a.slug, a.summary, a.author_id, a.category_id,
                       a.source_id, a.thumbnail_url, a.publication_status, a.content_sync_status,
                       a.revision, a.published_at, a.created_at, a.updated_at,
                       c.name AS category_name, c.slug AS category_slug,
                       u.display_name AS author_name
                FROM articles a
                LEFT JOIN categories c ON c.id = a.category_id
                LEFT JOIN users u ON u.id = a.author_id';

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY a.updated_at DESC';

        $limit = max(1, min(100, (int) ($filters['limit'] ?? 100)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;

        $sql .= " LIMIT $limit OFFSET $offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $where = [];
        $params = [];
        if (!empty($filters['status'])) {
            $where[] = 'publication_status = ?';
            $params[] = (string) $filters['status'];
        }
        $sql = 'SELECT COUNT(*) FROM articles';
        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function allIds(): array
    {
        return $this->pdo->query('SELECT article_id FROM articles')->fetchAll(PDO::FETCH_COLUMN);
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
