<?php
declare(strict_types=1);

namespace App\Database;

use App\API\Http\ApiException;

final class MongoContentRepository
{
    private string $contentDb;
    private string $auditDb;

    public function __construct(private readonly MongoDatabase $mongo, array $config)
    {
        $this->contentDb = $config['MONGODB_CONTENT_DB'] ?? 'news_content';
        $this->auditDb = $config['MONGODB_AUDIT_DB'] ?? 'news_audit';
    }

    public function publicArticles(array $filters = []): array
    {
        $filter = ['status' => 'published'];
        if (!empty($filters['category'])) {
            $categoryId = $this->resolveCategoryId($filters['category']);
            $filter['category_ids'] = $categoryId ?? MongoDatabase::objectId('000000000000000000000000');
        }
        if (!empty($filters['source'])) {
            $filter['source_id'] = MongoDatabase::objectId($filters['source']);
        }
        if (!empty($filters['q'])) {
            $filter['$text'] = ['$search' => $filters['q']];
        }
        $items = $this->mongo->find($this->contentDb, 'articles', $filter, [
            'sort' => ['published_at' => -1, '_id' => -1],
            'limit' => 100,
        ]);
        return array_map(fn (object $document): array => $this->formatArticle($document), $items);
    }

    public function adminArticles(): array
    {
        $items = $this->mongo->find(
            $this->contentDb,
            'articles',
            [],
            ['sort' => ['updated_at' => -1], 'limit' => 100],
        );
        return array_map(fn (object $document): array => $this->formatArticle($document), $items);
    }

    public function article(string $id, bool $publicOnly = true): ?array
    {
        $filter = ['_id' => MongoDatabase::objectId($id)];
        if ($publicOnly) {
            $filter['status'] = 'published';
        }
        $documents = $this->mongo->find($this->contentDb, 'articles', $filter, ['limit' => 1]);
        return $documents ? $this->formatArticle($documents[0]) : null;
    }

    public function categories(bool $includeInactive = false): array
    {
        $items = $this->mongo->find(
            $this->contentDb,
            'categories',
            $includeInactive ? [] : ['is_active' => true],
            ['sort' => ['sort_order' => 1, 'name' => 1], 'limit' => 200],
        );
        return array_map(fn (object $item): array => [
            'id' => self::id($item->_id ?? null),
            'name' => (string) ($item->name ?? ''),
            'slug' => (string) ($item->slug ?? ''),
        ], $items);
    }

    public function createCategory(array $input, string $actorId): array
    {
        $name = self::requiredText($input['name'] ?? null, 'Tên danh mục', 100);
        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 500) {
            throw new ApiException('Mô tả danh mục không được vượt quá 500 ký tự.', 422, 'VALIDATION_ERROR');
        }
        $sortOrder = filter_var($input['sortOrder'] ?? 0, FILTER_VALIDATE_INT);
        if ($sortOrder === false) {
            throw new ApiException('Thứ tự danh mục không hợp lệ.', 422, 'VALIDATION_ERROR');
        }
        $now = new \MongoDB\BSON\UTCDateTime();
        $id = $this->mongo->insert($this->contentDb, 'categories', [
            'name' => $name,
            'slug' => self::slug($name),
            'description' => $description ?: null,
            'is_active' => true,
            'sort_order' => $sortOrder,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->recordActivity($actorId, 'admin', 'category.create', 'category', $id);
        return $this->category($id) ?? throw new \RuntimeException('Không thể tải danh mục vừa tạo.');
    }

    public function updateCategory(string $id, array $input, string $actorId): ?array
    {
        $changes = ['updated_by' => $actorId, 'updated_at' => new \MongoDB\BSON\UTCDateTime()];
        if (array_key_exists('name', $input)) {
            $name = self::requiredText($input['name'], 'Tên danh mục', 100);
            $changes['name'] = $name;
            $changes['slug'] = self::slug($name);
        }
        if (array_key_exists('description', $input)) {
            $description = trim((string) $input['description']);
            if (mb_strlen($description) > 500) {
                throw new ApiException('Mô tả danh mục không được vượt quá 500 ký tự.', 422, 'VALIDATION_ERROR');
            }
            $changes['description'] = $description ?: null;
        }
        if (array_key_exists('sortOrder', $input)) {
            $sortOrder = filter_var($input['sortOrder'], FILTER_VALIDATE_INT);
            if ($sortOrder === false) {
                throw new ApiException('Thứ tự danh mục không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $changes['sort_order'] = $sortOrder;
        }
        if (array_key_exists('isActive', $input)) {
            if (!is_bool($input['isActive'])) {
                throw new ApiException('Trạng thái danh mục không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $changes['is_active'] = $input['isActive'];
        }
        $this->mongo->update(
            $this->contentDb,
            'categories',
            ['_id' => MongoDatabase::objectId($id)],
            $changes,
        );
        $category = $this->category($id);
        if ($category) {
            $this->recordActivity($actorId, 'admin', 'category.update', 'category', $id);
        }
        return $category;
    }

    public function category(string $id): ?array
    {
        $items = $this->mongo->find(
            $this->contentDb,
            'categories',
            ['_id' => MongoDatabase::objectId($id)],
            ['limit' => 1],
        );
        if (!$items) {
            return null;
        }
        $item = $items[0];
        return [
            'id' => (string) $item->_id,
            'name' => (string) ($item->name ?? ''),
            'slug' => (string) ($item->slug ?? ''),
            'description' => $item->description ?? null,
            'sortOrder' => (int) ($item->sort_order ?? 0),
            'isActive' => (bool) ($item->is_active ?? false),
        ];
    }

    public function sources(bool $includeInactive = false): array
    {
        $items = $this->mongo->find(
            $this->contentDb,
            'news_sources',
            $includeInactive ? [] : ['is_active' => true],
            ['sort' => ['name' => 1], 'limit' => 200],
        );
        return array_map(fn (object $item): array => $this->formatSource($item), $items);
    }

    public function createArticle(array $input, string $actorId): array
    {
        $title = self::requiredText($input['title'] ?? null, 'Tiêu đề', 250);
        $content = trim((string) ($input['content'] ?? ''));
        if ($content === '') {
            throw new ApiException('Nội dung bài viết không được để trống.', 422, 'VALIDATION_ERROR');
        }
        $visible = $input['visible'] ?? false;
        if (!is_bool($visible)) {
            throw new ApiException('Trạng thái hiển thị không hợp lệ.', 422, 'VALIDATION_ERROR');
        }
        $summary = trim((string) ($input['summary'] ?? ''));
        if (mb_strlen($summary) > 1000) {
            throw new ApiException('Tóm tắt không được vượt quá 1000 ký tự.', 422, 'VALIDATION_ERROR');
        }
        $now = new \MongoDB\BSON\UTCDateTime();
        $categoryIds = [];
        if (!empty($input['categoryId'])) {
            $categoryId = MongoDatabase::objectId((string) $input['categoryId']);
            if (!$this->mongo->find(
                $this->contentDb,
                'categories',
                ['_id' => $categoryId, 'is_active' => true],
                ['limit' => 1],
            )) {
                throw new ApiException('Không tìm thấy danh mục đang hoạt động.', 422, 'INVALID_REFERENCE');
            }
            $categoryIds[] = $categoryId;
        }
        $sourceId = !empty($input['sourceId'])
            ? MongoDatabase::objectId((string) $input['sourceId'])
            : null;
        if ($sourceId && !$this->source((string) $sourceId)) {
            throw new ApiException('Không tìm thấy nguồn báo.', 422, 'INVALID_REFERENCE');
        }
        $slug = self::slug($title) . '-' . bin2hex(random_bytes(4));
        $id = $this->mongo->insert($this->contentDb, 'articles', [
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'content' => [['type' => 'paragraph', 'text' => $content]],
            'content_plain_text' => $content,
            'category_ids' => $categoryIds,
            'source_id' => $sourceId,
            'author_name' => null,
            'cover_image' => null,
            'status' => $visible ? 'published' : 'draft',
            'published_at' => $visible ? $now : null,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'published_by' => $visible ? $actorId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->recordActivity($actorId, 'admin', 'article.create', 'article', $id);
        return $this->article($id, false) ?? throw new \RuntimeException('Không thể tải bài viết vừa tạo.');
    }

    public function updateArticle(string $id, array $input, string $actorId): ?array
    {
        $changes = ['updated_by' => $actorId, 'updated_at' => new \MongoDB\BSON\UTCDateTime()];
        if (array_key_exists('title', $input)) {
            $changes['title'] = self::requiredText($input['title'], 'Tiêu đề', 250);
        }
        if (array_key_exists('summary', $input)) {
            $summary = trim((string) $input['summary']);
            if (mb_strlen($summary) > 1000) {
                throw new ApiException('Tóm tắt không được vượt quá 1000 ký tự.', 422, 'VALIDATION_ERROR');
            }
            $changes['summary'] = $summary;
        }
        if (array_key_exists('content', $input)) {
            $content = trim((string) $input['content']);
            if ($content === '') {
                throw new ApiException('Nội dung bài viết không được để trống.', 422, 'VALIDATION_ERROR');
            }
            $changes['content'] = [['type' => 'paragraph', 'text' => $content]];
            $changes['content_plain_text'] = $content;
        }
        if (array_key_exists('categoryId', $input)) {
            $categoryId = $input['categoryId'] ? MongoDatabase::objectId((string) $input['categoryId']) : null;
            if ($categoryId && !$this->mongo->find(
                $this->contentDb,
                'categories',
                ['_id' => $categoryId, 'is_active' => true],
                ['limit' => 1],
            )) {
                throw new ApiException('Không tìm thấy danh mục đang hoạt động.', 422, 'INVALID_REFERENCE');
            }
            $changes['category_ids'] = $categoryId ? [$categoryId] : [];
        }
        if (array_key_exists('sourceId', $input)) {
            $sourceId = $input['sourceId'] ? MongoDatabase::objectId((string) $input['sourceId']) : null;
            if ($sourceId && !$this->source((string) $sourceId)) {
                throw new ApiException('Không tìm thấy nguồn báo.', 422, 'INVALID_REFERENCE');
            }
            $changes['source_id'] = $sourceId;
        }
        if (array_key_exists('visible', $input)) {
            if (!is_bool($input['visible'])) {
                throw new ApiException('Trạng thái hiển thị không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $changes['status'] = $input['visible'] ? 'published' : 'draft';
            $changes['published_at'] = $input['visible'] ? new \MongoDB\BSON\UTCDateTime() : null;
            $changes['published_by'] = $input['visible'] ? $actorId : null;
        }
        $updated = $this->mongo->update(
            $this->contentDb,
            'articles',
            ['_id' => MongoDatabase::objectId($id)],
            $changes,
        );
        $article = $this->article($id, false);
        if ($article && $updated > 0) {
            $this->recordActivity($actorId, 'admin', 'article.update', 'article', $id);
        }
        return $article;
    }

    public function deleteArticle(string $id, string $actorId): bool
    {
        $article = $this->article($id, false);
        if (!$article) {
            return false;
        }
        $this->mongo->update(
            $this->contentDb,
            'articles',
            ['_id' => MongoDatabase::objectId($id)],
            [
                'status' => 'archived',
                'updated_at' => new \MongoDB\BSON\UTCDateTime(),
            ],
        );
        $this->recordActivity($actorId, 'admin', 'article.archive', 'article', $id);
        return true;
    }

    public function createSource(array $input, string $actorId): array
    {
        $name = self::requiredText($input['name'] ?? null, 'Tên nguồn báo', 160);
        $url = trim((string) ($input['url'] ?? ''));
        if ($url !== '' && !self::isHttpUrl($url)) {
            throw new ApiException('Địa chỉ nguồn báo không hợp lệ.', 422, 'VALIDATION_ERROR');
        }
        $now = new \MongoDB\BSON\UTCDateTime();
        $id = $this->mongo->insert($this->contentDb, 'news_sources', [
            'name' => $name,
            'slug' => self::slug($name) . '-' . bin2hex(random_bytes(4)),
            'description' => null,
            'website_url' => $url ?: null,
            'category' => trim((string) ($input['category'] ?? '')),
            'logo_file_id' => null,
            'is_active' => true,
            'created_by' => $actorId,
            'updated_by' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->recordActivity($actorId, 'admin', 'source.create', 'source', $id);
        return $this->source($id) ?? throw new \RuntimeException('Không thể tải nguồn báo vừa tạo.');
    }

    public function updateSource(string $id, array $input, string $actorId): ?array
    {
        $changes = ['updated_by' => $actorId, 'updated_at' => new \MongoDB\BSON\UTCDateTime()];
        if (array_key_exists('name', $input)) {
            $name = self::requiredText($input['name'], 'Tên nguồn báo', 160);
            $changes['name'] = $name;
        }
        if (array_key_exists('url', $input)) {
            $url = trim((string) $input['url']);
            if ($url !== '' && !self::isHttpUrl($url)) {
                throw new ApiException('Địa chỉ nguồn báo không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $changes['website_url'] = $url ?: null;
        }
        if (array_key_exists('category', $input)) {
            $changes['category'] = trim((string) $input['category']);
        }
        if (array_key_exists('isActive', $input)) {
            if (!is_bool($input['isActive'])) {
                throw new ApiException('Trạng thái nguồn báo không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $changes['is_active'] = $input['isActive'];
        }
        $updated = $this->mongo->update(
            $this->contentDb,
            'news_sources',
            ['_id' => MongoDatabase::objectId($id)],
            $changes,
        );
        $source = $this->source($id);
        if ($source && $updated > 0) {
            $this->recordActivity($actorId, 'admin', 'source.update', 'source', $id);
        }
        return $source;
    }

    public function source(string $id): ?array
    {
        $items = $this->mongo->find(
            $this->contentDb,
            'news_sources',
            ['_id' => MongoDatabase::objectId($id)],
            ['limit' => 1],
        );
        return $items ? $this->formatSource($items[0]) : null;
    }

    public function dashboardCounts(): array
    {
        return [
            'articlesCount' => $this->mongo->count($this->contentDb, 'articles'),
            'visibleArticlesCount' => $this->mongo->count($this->contentDb, 'articles', ['status' => 'published']),
        ];
    }

    public function activityLogs(array $filters = []): array
    {
        $items = $this->mongo->find(
            $this->auditDb,
            'activity_logs',
            [],
            ['sort' => ['created_at' => -1], 'limit' => 500],
        );
        $items = array_map(fn (object $item): array => self::normalize($item), $items);
        return array_values(array_filter($items, static function (array $item) use ($filters): bool {
            if (!empty($filters['action']) && ($item['action'] ?? '') !== $filters['action']) {
                return false;
            }
            if (!empty($filters['date']) && !str_starts_with((string) ($item['created_at'] ?? ''), $filters['date'])) {
                return false;
            }
            if (!empty($filters['q'])) {
                return str_contains(
                    mb_strtolower(json_encode($item, JSON_UNESCAPED_UNICODE) ?: ''),
                    mb_strtolower($filters['q']),
                );
            }
            return true;
        }));
    }

    public function deleteActivityLogs(): void
    {
        $this->mongo->delete($this->auditDb, 'activity_logs', [], true);
    }

    public function recordActivity(
        ?string $actorId,
        string $actorRole,
        string $action,
        string $targetType,
        string $targetId,
        array $details = [],
    ): void {
        $this->mongo->insert($this->auditDb, 'activity_logs', [
            'actor_user_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'target' => (object) ['type' => $targetType, 'id' => $targetId],
            'details' => (object) $details,
            'request_id' => bin2hex(random_bytes(16)),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
            'created_at' => new \MongoDB\BSON\UTCDateTime(),
        ]);
    }

    public function media(string $id): ?array
    {
        return $this->mongo->readFile($this->contentDb, $id);
    }

    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof \MongoDB\BSON\ObjectId) {
            return (string) $value;
        }
        if ($value instanceof \MongoDB\BSON\UTCDateTime) {
            return $value->toDateTime()->format(\DateTimeInterface::ATOM);
        }
        if ($value instanceof \MongoDB\BSON\Binary) {
            return base64_encode($value->getData());
        }
        if (is_object($value)) {
            $result = [];
            foreach (get_object_vars($value) as $key => $item) {
                $result[$key === '_id' ? 'id' : $key] = self::normalize($item);
            }
            return $result;
        }
        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }
        return $value;
    }

    private function formatArticle(object $document): array
    {
        $normalized = self::normalize($document);
        $category = null;
        $categoryId = $normalized['category_ids'][0] ?? null;
        if ($categoryId) {
            $matches = $this->mongo->find(
                $this->contentDb,
                'categories',
                ['_id' => MongoDatabase::objectId($categoryId)],
                ['limit' => 1],
            );
            if ($matches) {
                $category = [
                    'id' => (string) $matches[0]->_id,
                    'name' => (string) ($matches[0]->name ?? ''),
                    'slug' => (string) ($matches[0]->slug ?? ''),
                ];
            }
        }
        $source = null;
        if (!empty($normalized['source_id'])) {
            $source = $this->source($normalized['source_id']);
        }
        $content = $normalized['content_plain_text'] ?? '';
        if ($content === '' && isset($normalized['content']) && is_array($normalized['content'])) {
            $content = implode("\n", array_filter(array_map(
                static fn (array $block): string => (string) ($block['text'] ?? ''),
                $normalized['content'],
            )));
        }
        $image = $normalized['cover_image']['file_id'] ?? null;
        return [
            'id' => $normalized['id'],
            'title' => $normalized['title'] ?? '',
            'summary' => $normalized['summary'] ?? '',
            'content' => $content,
            'thumbnailUrl' => $image ? '/api/media/' . $image : null,
            'category' => $category,
            'categoryId' => $category['id'] ?? null,
            'source' => $source ? $source['name'] : null,
            'sourceId' => $source['id'] ?? null,
            'publishedAt' => $normalized['published_at'] ?? null,
            'isFeatured' => false,
            'isFavorite' => false,
            'visible' => ($normalized['status'] ?? '') === 'published',
            'status' => $normalized['status'] ?? 'draft',
        ];
    }

    private function formatSource(object $document): array
    {
        $item = self::normalize($document);
        return [
            'id' => $item['id'],
            'name' => $item['name'] ?? '',
            'url' => $item['website_url'] ?? '',
            'category' => $item['category'] ?? '',
            'isActive' => (bool) ($item['is_active'] ?? false),
        ];
    }

    private function resolveCategoryId(string $value): ?\MongoDB\BSON\ObjectId
    {
        if (preg_match('/^[a-f0-9]{24}$/i', $value)) {
            return MongoDatabase::objectId($value);
        }
        $matches = $this->mongo->find(
            $this->contentDb,
            'categories',
            ['slug' => $value, 'is_active' => true],
            ['limit' => 1],
        );
        return $matches ? $matches[0]->_id : null;
    }

    private static function id(mixed $id): ?string
    {
        return $id === null ? null : (string) $id;
    }

    private static function requiredText(mixed $value, string $label, int $maxLength): string
    {
        $text = trim((string) $value);
        if ($text === '' || mb_strlen($text) > $maxLength) {
            throw new ApiException("$label không hợp lệ.", 422, 'VALIDATION_ERROR');
        }
        return $text;
    }

    private static function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii), '-'));
        return substr($slug !== '' ? $slug : 'item', 0, 180);
    }

    private static function isHttpUrl(string $value): bool
    {
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        return filter_var($value, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true);
    }
}
