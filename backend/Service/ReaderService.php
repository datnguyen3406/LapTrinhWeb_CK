<?php
declare(strict_types=1);

namespace App\Service;

use App\API\Http\ApiException;
use App\Database\MongoContentRepository;
use App\Database\ReaderRepository;

final class ReaderService
{
    public function __construct(
        private readonly ReaderRepository $reader,
        private readonly MongoContentRepository $content,
    ) {
    }

    public function favorites(string $userId): array
    {
        $favorites = $this->reader->favorites($userId);
        if (empty($favorites)) {
            return ['items' => []];
        }
        $articleIds = array_column($favorites, 'article_id');
        $articles = $this->content->articlesByIds($articleIds, true);

        $items = [];
        foreach ($favorites as $favorite) {
            $id = $favorite['article_id'];
            if (isset($articles[$id])) {
                $article = $articles[$id];
                $article['isFavorite'] = true;
                $items[] = ['article' => $article];
            } else {
                // Tự động dọn bản ghi mồ côi nếu bài báo bên Mongo đã bị xóa hoặc ẩn
                $this->reader->removeFavorite($userId, $id);
            }
        }
        return ['items' => $items];
    }

    public function addFavorite(string $userId, array $input): void
    {
        $articleId = $this->articleId($input['articleId'] ?? '');
        if (!$this->content->article($articleId)) {
            throw new ApiException('Không tìm thấy bài báo đang hiển thị.', 404, 'ARTICLE_NOT_FOUND');
        }
        $this->reader->addFavorite($userId, $articleId);
        $this->content->recordActivity($userId, 'reader', 'bookmark.add', 'article', $articleId);
    }

    public function removeFavorite(string $userId, string $articleId): void
    {
        $this->reader->removeFavorite($userId, $this->articleId($articleId));
        $this->content->recordActivity($userId, 'reader', 'bookmark.remove', 'article', $articleId);
    }

    public function history(string $userId): array
    {
        $entries = $this->reader->history($userId);
        if (empty($entries)) {
            return ['items' => []];
        }
        $articleIds = array_column($entries, 'article_id');
        $articles = $this->content->articlesByIds($articleIds, true);

        $items = [];
        foreach ($entries as $entry) {
            $id = $entry['article_id'];
            if (isset($articles[$id])) {
                $items[] = ['article' => $articles[$id], 'readAt' => $entry['read_at']];
            }
        }
        return ['items' => $items];
    }

    public function addHistory(string $userId, array $input): void
    {
        $articleId = $this->articleId($input['articleId'] ?? '');
        if (!$this->content->article($articleId)) {
            throw new ApiException('Không tìm thấy bài báo đang hiển thị.', 404, 'ARTICLE_NOT_FOUND');
        }
        $this->reader->addHistory($userId, $articleId);
        $this->content->recordActivity($userId, 'reader', 'history.read', 'article', $articleId);
    }

    public function createCategory(string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ApiException('Tên phân loại phải từ 1 đến 80 ký tự.', 422, 'VALIDATION_ERROR');
        }
        try {
            return ['category' => $this->reader->createCategory($userId, $name)];
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
        if (!preg_match('/^[a-f0-9]{24}$/i', $id)) {
            throw new ApiException('Mã bài báo không hợp lệ.', 422, 'INVALID_ID');
        }
        return strtolower($id);
    }
}
