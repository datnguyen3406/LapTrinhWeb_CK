<?php
declare(strict_types=1);

namespace App\Service;

use App\API\Http\ApiException;
use App\Database\ArticlesRepository;
use App\Database\MongoContentRepository;
use Throwable;

final class ArticleService
{
    public function __construct(
        private readonly ArticlesRepository $articles,
        private readonly MongoContentRepository $content,
        private readonly ArticleIntegrityService $integrity,
        private readonly AuditLogService $auditLog,
    ) {
    }

    public function createArticle(array $input, string $authorId): array
    {
        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new ApiException('Tiêu đề bài viết không hợp lệ (1-255 ký tự).', 422, 'VALIDATION_ERROR');
        }

        $slug = trim((string) ($input['slug'] ?? ''));
        $slug = $slug === '' ? self::slugify($title) : self::slugify($slug);
        if ($this->articles->findBySlug($slug)) {
            $slug .= '-' . bin2hex(random_bytes(3));
        }

        $summary = isset($input['summary']) ? trim((string) $input['summary']) : null;
        $categoryId = !empty($input['category_id']) ? (string) $input['category_id'] : (!empty($input['categoryId']) ? (string) $input['categoryId'] : null);
        $sourceId = !empty($input['source_id']) ? (string) $input['source_id'] : (!empty($input['sourceId']) ? (string) $input['sourceId'] : null);
        $thumbnailUrl = !empty($input['thumbnail_url']) ? (string) $input['thumbnail_url'] : (!empty($input['thumbnailUrl']) ? (string) $input['thumbnailUrl'] : null);

        $blocks = $input['blocks'] ?? [];
        if (!is_array($blocks) || empty($blocks)) {
            // Check if raw content or html was provided and convert to block
            $rawContent = trim((string) ($input['content'] ?? ''));
            if ($rawContent !== '') {
                $blocks = [
                    ['type' => 'paragraph', 'data' => ['text' => $rawContent]],
                ];
            } else {
                throw new ApiException('Bài viết phải có ít nhất một khối nội dung (blocks).', 422, 'VALIDATION_ERROR');
            }
        }

        $plainText = self::extractPlainText($blocks);
        $shouldPublish = !empty($input['publish']) || ($input['status'] ?? '') === 'published';

        // SAGA STEP 1: Insert SQLite metadata as PENDING
        $meta = $this->articles->create([
            'title' => $title,
            'slug' => $slug,
            'summary' => $summary,
            'author_id' => $authorId,
            'category_id' => $categoryId,
            'source_id' => $sourceId,
            'thumbnail_url' => $thumbnailUrl,
            'publication_status' => 'DRAFT',
            'content_sync_status' => 'PENDING',
        ]);
        $articleId = $meta['article_id'];

        // SAGA STEP 2: Write MongoDB content blocks
        try {
            $this->content->saveContent($articleId, $blocks, $plainText);
        } catch (Throwable $e) {
            $this->articles->setStatus($articleId, 'DRAFT', 'FAILED');
            try {
                $this->auditLog->log($authorId, 'admin', 'article.create_failed', 'article', $articleId, [
                    'stage' => 'mongo_write',
                    'error' => $e->getMessage(),
                ]);
            } catch (Throwable) {
                // Ignore audit logging error if MongoDB is down
            }
            throw new ApiException('Không thể lưu nội dung bài viết sang MongoDB: ' . $e->getMessage(), 500, 'CONTENT_SYNC_FAILED');
        }

        // SAGA STEP 3: Integrity verification
        $verifyResult = $this->integrity->verify($articleId);
        if ($verifyResult['status'] !== 'PASS') {
            $this->articles->setStatus($articleId, 'DRAFT', 'FAILED');
            try {
                $this->auditLog->log($authorId, 'admin', 'article.verify_failed', 'article', $articleId, $verifyResult);
            } catch (Throwable) {
                // Ignore audit logging error if MongoDB is down
            }
            throw new ApiException(
                'Kiểm tra tính toàn vẹn thất bại: ' . ($verifyResult['reason'] ?? 'Dữ liệu không khớp'),
                500,
                'INTEGRITY_CHECK_FAILED',
            );
        }

        // SAGA STEP 4: Update status to READY (and PUBLISHED if requested)
        $newPubStatus = $shouldPublish ? 'PUBLISHED' : 'DRAFT';
        $this->articles->setStatus($articleId, $newPubStatus, 'READY');
        $updatedMeta = $this->articles->findById($articleId);

        // SAGA STEP 5: Audit log
        try {
            $this->auditLog->log($authorId, 'admin', 'article.create', 'article', $articleId, [
                'title' => $title,
                'slug' => $slug,
                'publication_status' => $newPubStatus,
                'blocks_count' => count($blocks),
            ]);
        } catch (Throwable) {
            // Ignore audit logging error if MongoDB is down
        }

        return array_merge($updatedMeta, [
            'id' => $articleId,
            'blocks' => $blocks,
            'content' => $plainText,
        ]);
    }

    public function updateArticle(string $articleId, array $input, string $actorId): array
    {
        $existing = $this->articles->findById($articleId);
        if (!$existing) {
            throw new ApiException('Không tìm thấy bài viết.', 404, 'ARTICLE_NOT_FOUND');
        }

        // Optimistic locking revision check
        if (isset($input['expected_revision']) || isset($input['revision'])) {
            $expectedRevision = (int) ($input['expected_revision'] ?? $input['revision']);
            if ((int) $existing['revision'] !== $expectedRevision) {
                throw new ApiException(
                    'Xung đột dữ liệu: Bài viết đã được thay đổi bởi phiên khác (revision mismatch).',
                    409,
                    'REVISION_CONFLICT',
                );
            }
        }

        $data = [];
        if (array_key_exists('title', $input)) {
            $title = trim((string) $input['title']);
            if ($title === '' || mb_strlen($title) > 255) {
                throw new ApiException('Tiêu đề không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $data['title'] = $title;
        }

        if (array_key_exists('slug', $input)) {
            $slug = self::slugify((string) $input['slug']);
            $bySlug = $this->articles->findBySlug($slug);
            if ($bySlug && $bySlug['article_id'] !== $articleId) {
                throw new ApiException('Slug bài viết đã tồn tại.', 409, 'SLUG_EXISTS');
            }
            $data['slug'] = $slug;
        }

        if (array_key_exists('summary', $input)) {
            $data['summary'] = $input['summary'] !== null ? trim((string) $input['summary']) : null;
        }
        if (array_key_exists('category_id', $input)) {
            $data['category_id'] = $input['category_id'] ?: null;
        } elseif (array_key_exists('categoryId', $input)) {
            $data['category_id'] = $input['categoryId'] ?: null;
        }
        if (array_key_exists('source_id', $input)) {
            $data['source_id'] = $input['source_id'] ?: null;
        } elseif (array_key_exists('sourceId', $input)) {
            $data['source_id'] = $input['sourceId'] ?: null;
        }
        if (array_key_exists('thumbnail_url', $input)) {
            $data['thumbnail_url'] = $input['thumbnail_url'] ?: null;
        } elseif (array_key_exists('thumbnailUrl', $input)) {
            $data['thumbnail_url'] = $input['thumbnailUrl'] ?: null;
        }

        // Update blocks in MongoDB if provided
        $blocks = $input['blocks'] ?? null;
        if ($blocks !== null) {
            if (!is_array($blocks) || empty($blocks)) {
                throw new ApiException('Blocks nội dung không hợp lệ.', 422, 'VALIDATION_ERROR');
            }
            $plainText = self::extractPlainText($blocks);
            $this->content->saveContent($articleId, $blocks, $plainText);
        }

        // Publication status change
        if (isset($input['status']) || isset($input['publication_status'])) {
            $newStatus = strtoupper((string) ($input['status'] ?? $input['publication_status']));
            if ($newStatus === 'PUBLISHED') {
                $verifyResult = $this->integrity->verify($articleId);
                if ($verifyResult['status'] !== 'PASS') {
                    throw new ApiException(
                        'Không thể xuất bản bài viết chưa đầy đủ nội dung hoặc lỗi kiểm tra.',
                        422,
                        'ARTICLE_NOT_READY',
                    );
                }
            }
            $data['publication_status'] = $newStatus;
        }

        $expectedRev = isset($input['expected_revision']) ? (int) $input['expected_revision'] : (int) $existing['revision'];
        $updated = $this->articles->update($articleId, $data, $expectedRev);
        if (!$updated) {
            throw new ApiException(
                'Không thể cập nhật bài viết do xung đột revision.',
                409,
                'REVISION_CONFLICT',
            );
        }

        $this->auditLog->log($actorId, 'admin', 'article.update', 'article', $articleId, [
            'changes' => array_keys($data),
            'new_revision' => $updated['revision'],
        ]);

        $content = $this->content->getContent($articleId);
        return array_merge($updated, [
            'id' => $articleId,
            'blocks' => $content['blocks'] ?? [],
            'content' => $content['content_plain_text'] ?? '',
        ]);
    }

    public function setStatus(string $articleId, string $status, string $actorId): array
    {
        $status = strtoupper($status);
        if (!in_array($status, ['DRAFT', 'PUBLISHED', 'HIDDEN', 'DELETED'], true)) {
            throw new ApiException('Trạng thái bài viết không hợp lệ.', 422, 'VALIDATION_ERROR');
        }

        $existing = $this->articles->findById($articleId);
        if (!$existing) {
            throw new ApiException('Không tìm thấy bài viết.', 404, 'ARTICLE_NOT_FOUND');
        }

        if ($status === 'PUBLISHED') {
            $verify = $this->integrity->verify($articleId);
            if ($verify['status'] !== 'PASS') {
                throw new ApiException('Không thể xuất bản bài viết chưa hoàn thiện nội dung.', 422, 'ARTICLE_NOT_READY');
            }
        }

        $this->articles->setStatus($articleId, $status);
        $updated = $this->articles->findById($articleId);

        $this->auditLog->log($actorId, 'admin', 'article.status', 'article', $articleId, [
            'old_status' => $existing['publication_status'],
            'new_status' => $status,
        ]);

        return $updated ?? [];
    }

    public function deleteArticle(string $articleId, string $actorId, bool $permanent = false): void
    {
        $existing = $this->articles->findById($articleId);
        if (!$existing) {
            throw new ApiException('Không tìm thấy bài viết.', 404, 'ARTICLE_NOT_FOUND');
        }

        if ($permanent) {
            $this->articles->delete($articleId, true);
            $this->content->deleteContent($articleId);
            $this->auditLog->log($actorId, 'admin', 'article.permanent_delete', 'article', $articleId, []);
        } else {
            $this->articles->setStatus($articleId, 'DELETED');
            $this->auditLog->log($actorId, 'admin', 'article.soft_delete', 'article', $articleId, []);
        }
    }

    public function getPublicArticle(string $idOrSlug): array
    {
        $meta = $this->articles->findById($idOrSlug) ?? $this->articles->findBySlug($idOrSlug);
        if (!$meta) {
            throw new ApiException('Không tìm thấy bài báo.', 404, 'ARTICLE_NOT_FOUND');
        }

        if ($meta['publication_status'] !== 'PUBLISHED' || $meta['content_sync_status'] !== 'READY') {
            throw new ApiException('Bài báo chưa được xuất bản hoặc đang xử lý.', 404, 'ARTICLE_NOT_FOUND');
        }

        $content = $this->content->getContent($meta['article_id']);
        return [
            'id' => $meta['article_id'],
            'title' => $meta['title'],
            'slug' => $meta['slug'],
            'summary' => $meta['summary'],
            'content' => $content['content_plain_text'] ?? '',
            'blocks' => $content['blocks'] ?? [],
            'thumbnailUrl' => $meta['thumbnail_url'],
            'publishedAt' => $meta['published_at'],
            'category' => $meta['category_name'] ? [
                'id' => $meta['category_id'],
                'name' => $meta['category_name'],
                'slug' => $meta['category_slug'],
            ] : null,
            'author' => [
                'id' => $meta['author_id'],
                'name' => $meta['author_name'] ?? 'Admin',
            ],
            'revision' => (int) $meta['revision'],
        ];
    }

    public function listPublicArticles(array $filters = []): array
    {
        $items = $this->articles->listPublic($filters);
        $total = $this->articles->count(['status' => 'PUBLISHED']);
        $formatted = array_map(static function (array $row): array {
            return [
                'id' => $row['article_id'],
                'title' => $row['title'],
                'slug' => $row['slug'],
                'summary' => $row['summary'],
                'thumbnailUrl' => $row['thumbnail_url'],
                'publishedAt' => $row['published_at'],
                'category' => $row['category_name'] ? [
                    'id' => $row['category_id'],
                    'name' => $row['category_name'],
                    'slug' => $row['category_slug'],
                ] : null,
                'author' => [
                    'id' => $row['author_id'],
                    'name' => $row['author_name'] ?? 'Admin',
                ],
            ];
        }, $items);

        return [
            'items' => $formatted,
            'total' => $total,
            'featured' => $formatted[0] ?? null,
        ];
    }

    public function listAdminArticles(array $filters = []): array
    {
        $items = $this->articles->listAdmin($filters);
        return [
            'items' => array_map(static function (array $row): array {
                return [
                    'id' => $row['article_id'],
                    'title' => $row['title'],
                    'slug' => $row['slug'],
                    'summary' => $row['summary'],
                    'status' => strtolower($row['publication_status']),
                    'publicationStatus' => $row['publication_status'],
                    'contentSyncStatus' => $row['content_sync_status'],
                    'revision' => (int) $row['revision'],
                    'publishedAt' => $row['published_at'],
                    'createdAt' => $row['created_at'],
                    'updatedAt' => $row['updated_at'],
                    'category' => $row['category_name'] ? [
                        'id' => $row['category_id'],
                        'name' => $row['category_name'],
                    ] : null,
                ];
            }, $items),
        ];
    }

    private static function extractPlainText(array $blocks): string
    {
        $texts = [];
        foreach ($blocks as $block) {
            if (isset($block['data']['text'])) {
                $texts[] = trim((string) $block['data']['text']);
            } elseif (isset($block['text'])) {
                $texts[] = trim((string) $block['text']);
            }
        }
        return implode("\n\n", array_filter($texts));
    }

    private static function slugify(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii), '-'));
        return substr($slug !== '' ? $slug : 'article', 0, 180);
    }
}
