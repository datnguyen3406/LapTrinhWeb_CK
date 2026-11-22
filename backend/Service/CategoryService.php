<?php
declare(strict_types=1);

namespace App\Service;

use App\API\Http\ApiException;
use App\Database\CategoriesRepository;

final class CategoryService
{
    public function __construct(
        private readonly CategoriesRepository $categories,
    ) {
    }

    public function list(bool $includeInactive = false): array
    {
        return $this->categories->list($includeInactive);
    }

    public function get(string $id): ?array
    {
        return $this->categories->findById($id);
    }

    public function create(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new ApiException('Tên danh mục không hợp lệ (1-100 ký tự).', 422, 'VALIDATION_ERROR');
        }

        $slug = trim((string) ($input['slug'] ?? ''));
        if ($slug === '') {
            $slug = self::slugify($name);
        } else {
            $slug = self::slugify($slug);
        }

        if ($this->categories->findBySlug($slug)) {
            throw new ApiException('Slug danh mục đã tồn tại.', 409, 'SLUG_EXISTS');
        }

        $description = isset($input['description']) ? trim((string) $input['description']) : null;
        $sortOrder = isset($input['sortOrder']) ? (int) $input['sortOrder'] : 0;
        $isActive = isset($input['isActive']) ? (bool) $input['isActive'] : true;

        return $this->categories->create(
            name: $name,
            slug: $slug,
            description: $description,
            sortOrder: $sortOrder,
            isActive: $isActive,
        );
    }

    public function update(string $id, array $input): array
    {
        $category = $this->categories->findById($id);
        if (!$category) {
            throw new ApiException('Không tìm thấy danh mục.', 404, 'CATEGORY_NOT_FOUND');
        }

        $data = [];
        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '' || mb_strlen($name) > 100) {
                throw new ApiException('Tên danh mục không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('slug', $input)) {
            $slug = self::slugify((string) $input['slug']);
            $existing = $this->categories->findBySlug($slug);
            if ($existing && $existing['id'] !== $id) {
                throw new ApiException('Slug danh mục đã tồn tại.', 409, 'SLUG_EXISTS');
            }
            $data['slug'] = $slug;
        }

        if (array_key_exists('description', $input)) {
            $data['description'] = $input['description'] !== null ? trim((string) $input['description']) : null;
        }

        if (array_key_exists('sortOrder', $input)) {
            $data['sortOrder'] = (int) $input['sortOrder'];
        }

        if (array_key_exists('isActive', $input)) {
            $data['isActive'] = (bool) $input['isActive'];
        }

        $updated = $this->categories->update($id, $data);
        return $updated ?? throw new ApiException('Không thể cập nhật danh mục.', 500, 'UPDATE_FAILED');
    }

    public function delete(string $id): void
    {
        $category = $this->categories->findById($id);
        if (!$category) {
            throw new ApiException('Không tìm thấy danh mục.', 404, 'CATEGORY_NOT_FOUND');
        }

        $articleCount = $this->categories->articleCountByCategory($id);
        if ($articleCount > 0) {
            throw new ApiException(
                "Không thể xóa danh mục vì đang có {$articleCount} bài viết liên kết.",
                409,
                'CATEGORY_HAS_ARTICLES',
            );
        }

        $this->categories->delete($id);
    }

    private static function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii), '-'));
        return substr($slug !== '' ? $slug : 'cat', 0, 100);
    }
}
