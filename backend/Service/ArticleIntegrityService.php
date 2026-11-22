<?php
declare(strict_types=1);

namespace App\Service;

use App\Database\ArticlesRepository;
use App\Database\MongoContentRepository;
use Throwable;

final class ArticleIntegrityService
{
    public function __construct(
        private readonly ArticlesRepository $articles,
        private readonly MongoContentRepository $content,
    ) {
    }

    public function verify(string $articleId): array
    {
        $meta = $this->articles->findById($articleId);
        if (!$meta) {
            return [
                'status' => 'FAIL',
                'sqlite' => false,
                'mongo' => false,
                'reason' => 'Không tìm thấy metadata bài viết trong SQLite.',
                'blocks_count' => 0,
            ];
        }

        try {
            $content = $this->content->getContent($articleId);
        } catch (Throwable $e) {
            return [
                'status' => 'UNKNOWN',
                'sqlite' => true,
                'mongo' => false,
                'reason' => 'Không thể kết nối MongoDB: ' . $e->getMessage(),
                'blocks_count' => 0,
            ];
        }

        if (!$content) {
            return [
                'status' => 'FAIL',
                'sqlite' => true,
                'mongo' => false,
                'reason' => 'Không tìm thấy nội dung bài viết trong MongoDB.',
                'blocks_count' => 0,
            ];
        }

        $blocks = $content['blocks'] ?? [];
        if (!is_array($blocks) || empty($blocks)) {
            return [
                'status' => 'FAIL',
                'sqlite' => true,
                'mongo' => true,
                'reason' => 'Nội dung bài viết không có khối nội dung (blocks) hợp lệ.',
                'blocks_count' => 0,
            ];
        }

        return [
            'status' => 'PASS',
            'sqlite' => true,
            'mongo' => true,
            'reason' => null,
            'blocks_count' => count($blocks),
        ];
    }

    public function reconcile(): array
    {
        $sqliteIds = $this->articles->allIds();
        $mongoIds = [];
        try {
            $mongoIds = $this->content->allContentIds();
        } catch (Throwable) {
            // Mongo connection down
        }

        $orphanedInMongo = array_values(array_diff($mongoIds, $sqliteIds));
        $missingInMongo = array_values(array_diff($sqliteIds, $mongoIds));

        return [
            'total_sqlite' => count($sqliteIds),
            'total_mongo' => count($mongoIds),
            'orphaned_in_mongo' => $orphanedInMongo,
            'missing_in_mongo' => $missingInMongo,
            'in_sync' => empty($orphanedInMongo) && empty($missingInMongo),
        ];
    }

    public function cleanupOrphans(array $orphanIds): int
    {
        $count = 0;
        foreach ($orphanIds as $id) {
            if ($this->content->deleteContent($id)) {
                $count++;
            }
        }
        return $count;
    }
}
