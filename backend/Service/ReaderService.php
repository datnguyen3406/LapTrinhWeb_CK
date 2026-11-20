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
        $items = [];
        foreach ($this->reader->favorites($userId) as $favorite) {
            $article = $this->content->article($favorite['article_id']);
            if ($article) {
                $article['isFavorite'] = true;
                $items[] = ['article' => $article, 'folderId' => $favorite['folder_id']];
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
        $folderId = isset($input['folderId']) && $input['folderId'] !== ''
            ? (string) $input['folderId']
            : null;
        if ($folderId !== null && !$this->reader->ownsFolder($userId, $folderId)) {
            throw new ApiException('Không tìm thấy thư mục yêu thích.', 404, 'FOLDER_NOT_FOUND');
        }
        $this->reader->addFavorite($userId, $articleId, $folderId);
        $this->content->recordActivity($userId, 'reader', 'bookmark.add', 'article', $articleId);
    }

    public function removeFavorite(string $userId, string $articleId): void
    {
        $this->reader->removeFavorite($userId, $this->articleId($articleId));
        $this->content->recordActivity($userId, 'reader', 'bookmark.remove', 'article', $articleId);
    }

    public function folders(string $userId): array
    {
        return ['items' => $this->reader->folders($userId)];
    }

    public function createFolder(string $userId, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ApiException('Tên thư mục phải từ 1 đến 80 ký tự.', 422, 'VALIDATION_ERROR');
        }
        try {
            return ['folder' => $this->reader->createFolder($userId, $name)];
        } catch (\PDOException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unique')) {
                throw new ApiException('Tên thư mục đã được sử dụng.', 409, 'FOLDER_EXISTS');
            }
            throw $error;
        }
    }

    public function history(string $userId): array
    {
        $items = [];
        foreach ($this->reader->history($userId) as $entry) {
            $article = $this->content->article($entry['article_id']);
            if ($article) {
                $items[] = ['article' => $article, 'readAt' => $entry['read_at']];
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
