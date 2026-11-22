<?php
declare(strict_types=1);

namespace App\Database;

use PDO;

final class CategoriesRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(bool $includeInactive = false): array
    {
        $sql = 'SELECT id, name, slug, description, sort_order, is_active, created_at, updated_at
                FROM categories';
        if (!$includeInactive) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, name ASC';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, description, sort_order, is_active, created_at, updated_at
             FROM categories WHERE id = ?',
        );
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, slug, description, sort_order, is_active, created_at, updated_at
             FROM categories WHERE slug = ? COLLATE NOCASE',
        );
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function create(
        string $name,
        string $slug,
        ?string $description = null,
        int $sortOrder = 0,
        bool $isActive = true,
        ?string $id = null,
    ): array {
        $id = $id ?? self::uuid();
        $stmt = $this->pdo->prepare(
            'INSERT INTO categories (id, name, slug, description, sort_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?)',
        );
        $stmt->execute([$id, $name, $slug, $description, $sortOrder, $isActive ? 1 : 0]);
        return $this->findById($id) ?? throw new \RuntimeException('Không thể tạo danh mục.');
    }

    public function update(string $id, array $data): ?array
    {
        $sets = [];
        $values = [];
        if (array_key_exists('name', $data)) {
            $sets[] = 'name = ?';
            $values[] = $data['name'];
        }
        if (array_key_exists('slug', $data)) {
            $sets[] = 'slug = ?';
            $values[] = $data['slug'];
        }
        if (array_key_exists('description', $data)) {
            $sets[] = 'description = ?';
            $values[] = $data['description'];
        }
        if (array_key_exists('sortOrder', $data)) {
            $sets[] = 'sort_order = ?';
            $values[] = (int) $data['sortOrder'];
        }
        if (array_key_exists('isActive', $data)) {
            $sets[] = 'is_active = ?';
            $values[] = $data['isActive'] ? 1 : 0;
        }
        if (empty($sets)) {
            return $this->findById($id);
        }
        $sets[] = "updated_at = strftime('%Y-%m-%dT%H:%M:%fZ', 'now')";
        $values[] = $id;

        $stmt = $this->pdo->prepare('UPDATE categories SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($values);
        return $this->findById($id);
    }

    public function delete(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM categories WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function articleCountByCategory(string $categoryId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM articles WHERE category_id = ?');
        $stmt->execute([$categoryId]);
        return (int) $stmt->fetchColumn();
    }

    public function count(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    }

    private static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
