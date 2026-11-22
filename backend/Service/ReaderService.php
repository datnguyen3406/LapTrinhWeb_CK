<?php
declare(strict_types=1);

namespace App\Service;

use App\API\Http\ApiException;
use App\Database\ArticlesRepository;
use App\Database\MongoContentRepository;
use App\Database\ReaderRepository;
use Throwable;

final class ReaderService
{
    public function __construct(
        private readonly ReaderRepository $reader,
        private readonly MongoContentRepository $content,
        private readonly ?ArticlesRepository $articles = null,
    ) {
    }

    public function favorites(string $userId): array
    {
        $favorites = $this->reader->favorites($userId);
        if (empty($favorites)) {
            return ['items' => []];
        }

        if ($this->articles) {
            $items = [];
            foreach ($favorites as $favorite) {
                $id = $favorite['article_id'];
                $art = $this->articles->findById($id);
                if ($art && $art['publication_status'] === 'PUBLISHED') {
                    $items[] = [
                        'id' => $art['article_id'],
                        'article' => [
                            'id' => $art['article_id'],
                            'title' => $art['title'],
                            'slug' => $art['slug'],
                            'summary' => $art['summary'],
                            'thumbnailUrl' => $art['thumbnail_url'],
                            'isFavorite' => true,
                        ],
                    ];
                } else {
                    $this->reader->removeFavorite($userId, $id);
                }
            }
            return ['items' => $items];
        }

        $articleIds = array_column($favorites, 'article_id');
        $articles = $this->content->articlesByIds($articleIds, true);

        $items = [];
        foreach ($favorites as $favorite) {
            $id = $favorite['article_id'];
            if (isset($articles[$id])) {
                $article = $articles[$id];
                $article['isFavorite'] = true;
                $items[] = ['id' => $id, 'article' => $article];
            } else {
                $this->reader->removeFavorite($userId, $id);
            }
        }
        return ['items' => $items];
    }

    public function addFavorite(string $userId, array $input): void
    {
        $articleId = $this->articleId($input['articleId'] ?? '');
        if ($this->articles) {
            $art = $this->articles->findById($articleId);
            if (!$art || $art['publication_status'] !== 'PUBLISHED') {
                throw new ApiException('Không tìm thấy bài báo đang hiển thị.', 404, 'ARTICLE_NOT_FOUND');
            }
        } elseif (!$this->content->article($articleId)) {
            throw new ApiException('Không tìm thấy bài báo đang hiển thị.', 404, 'ARTICLE_NOT_FOUND');
        }

        $this->reader->addFavorite($userId, $articleId);
        try {
            $this->content->recordActivity($userId, 'reader', 'bookmark.add', 'article', $articleId);
        } catch (\Throwable) {
        }
    }

    public function removeFavorite(string $userId, string $articleId): void
    {
        $articleId = $this->articleId($articleId);
        $this->reader->removeFavorite($userId, $articleId);
        try {
            $this->content->recordActivity($userId, 'reader', 'bookmark.remove', 'article', $articleId);
        } catch (\Throwable) {
        }
    }

    public function history(string $userId): array
    {
        $rows = $this->reader->history($userId);
        if (empty($rows)) {
            return ['items' => []];
        }

        if ($this->articles) {
            $items = [];
            foreach ($rows as $row) {
                $id = $row['article_id'];
                $art = $this->articles->findById($id);
                if ($art && $art['publication_status'] === 'PUBLISHED') {
                    $items[] = [
                        'article' => [
                            'id' => $art['article_id'],
                            'title' => $art['title'],
                            'slug' => $art['slug'],
                            'summary' => $art['summary'],
                        ],
                        'viewedAt' => $row['viewed_at'] ?? null,
                    ];
                }
            }
            return ['items' => $items];
        }

        $articleIds = array_column($rows, 'article_id');
        $articles = $this->content->articlesByIds($articleIds, false);
        $items = [];
        foreach ($rows as $row) {
            $id = $row['article_id'];
            if (isset($articles[$id])) {
                $items[] = [
                    'article' => $articles[$id],
                    'viewedAt' => $row['viewed_at'],
                ];
            }
        }
        return ['items' => $items];
    }

    public function addHistory(string $userId, array $input): void
    {
        $articleId = $this->articleId($input['articleId'] ?? '');
        $this->reader->addHistory($userId, $articleId);
        try {
            $this->content->recordActivity(
                $userId,
                'reader',
                'history.read',
                'article',
                $articleId,
            );
        } catch (Throwable) {
            // Lịch sử đọc vẫn được lưu trong SQLite nếu MongoDB tạm thời không khả dụng.
        }
    }

    public function createCategory(string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            throw new ApiException('Tên phân loại không hợp lệ.', 422, 'VALIDATION_ERROR');
        }
        try {
            return $this->reader->createCategory($userId, $name);
        } catch (\PDOException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unique')) {
                throw new ApiException('Tên phân loại đã tồn tại.', 409, 'CATEGORY_EXISTS');
            }
            throw $error;
        }
    }

    private function articleId(mixed $value): string
    {
        $id = (string) $value;
        if (!preg_match('/^[a-f0-9]{24}$|^[a-f0-9-]{36}$/i', $id)) {
            throw new ApiException('Mã bài báo không hợp lệ.', 422, 'INVALID_ID');
        }
        return strtolower($id);
    }
}
