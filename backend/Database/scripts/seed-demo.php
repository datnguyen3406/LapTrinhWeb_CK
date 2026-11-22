<?php
declare(strict_types=1);

use App\Database\Environment;
use App\Database\MongoDatabase;
use App\Database\SqliteDatabase;
use App\Database\UsersRepository;
use App\Database\CategoriesRepository;
use App\Database\ArticlesRepository;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$backendRoot = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($backendRoot): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $backendRoot . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

try {
    $env = Environment::load($backendRoot . '/.env');
    $appEnv = strtolower((string) ($env['APP_ENV'] ?? 'development'));
    if ($appEnv === 'production' || $appEnv === 'prod') {
        fwrite(STDOUT, "Seed demo skipped outside development.\n");
        exit(0);
    }
    $database = new SqliteDatabase($backendRoot);
    $users = new UsersRepository($database->pdo());
    $categories = new CategoriesRepository($database->pdo());
    $articles = new ArticlesRepository($database->pdo());

    // 1. Seed Admin Account
    if ($users->activeAdminCount() === 0 && !$users->findByUsername('admin')) {
        $created = $users->create(
            'admin',
            'Site Admin',
            password_hash('Admin@123456', PASSWORD_BCRYPT),
        );
        $user = $created['user'];
        $users->setRole($user['id'], 'admin');
        fwrite(STDOUT, "Created development admin: admin / Admin@123456\n");
    } else {
        fwrite(STDOUT, "Admin account already exists; skipped admin seed.\n");
    }

    try {
        $mongo = new MongoDatabase($env);
        $contentDb = $env['MONGODB_CONTENT_DB'] ?? 'news_content';

        $categoriesCount = $mongo->count($contentDb, 'categories', []);
        $categoryMap = [];
        $defaultCategories = [
            ['name' => 'MOBILE', 'slug' => 'mobile', 'sort_order' => 1],
            ['name' => 'AI', 'slug' => 'ai', 'sort_order' => 2],
            ['name' => 'INTERNET', 'slug' => 'internet', 'sort_order' => 3],
            ['name' => 'TIN ICT', 'slug' => 'tin-ict', 'sort_order' => 4],
            ['name' => 'KHÁM PHÁ', 'slug' => 'kham-pha', 'sort_order' => 5],
        ];
        if ($categoriesCount === 0) {
            $now = new \MongoDB\BSON\UTCDateTime();
            foreach ($defaultCategories as $cat) {
                $id = $mongo->insert($contentDb, 'categories', [
                    'name' => $cat['name'],
                    'slug' => $cat['slug'],
                    'description' => 'Chuyên mục ' . $cat['name'],
                    'is_active' => true,
                    'sort_order' => $cat['sort_order'],
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $categoryMap[$cat['slug']] = MongoDatabase::objectId($id);
            }
            fwrite(STDOUT, "Seeded 5 default categories.\n");
        }
        foreach ($defaultCategories as $cat) {
            if (!$categories->findBySlug($cat['slug'])) {
                $categories->create(
                    $cat['name'],
                    $cat['slug'],
                    'Chuyên mục ' . $cat['name'],
                    $cat['sort_order'],
                );
            }
        }
        $sqliteMobileCategory = $categories->findBySlug('mobile');

        $sourcesCount = $mongo->count($contentDb, 'news_sources', []);
        $sourceId = null;
        if ($sourcesCount === 0) {
            $now = new \MongoDB\BSON\UTCDateTime();
            $defaultSources = [
                [
                    'name' => 'GenK',
                    'slug' => 'genk',
                    'website_url' => 'https://genk.vn',
                    'category' => 'mobile',
                    'is_active' => true,
                ],
                [
                    'name' => 'VnExpress Số Hóa',
                    'slug' => 'vnexpress-so-hoa',
                    'website_url' => 'https://vnexpress.net/so-hoa',
                    'category' => 'tin-ict',
                    'is_active' => true,
                ],
            ];
            foreach ($defaultSources as $src) {
                $id = $mongo->insert($contentDb, 'news_sources', [
                    'name' => $src['name'],
                    'slug' => $src['slug'],
                    'website_url' => $src['website_url'],
                    'category' => $src['category'],
                    'is_active' => $src['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if (!$sourceId) {
                    $sourceId = MongoDatabase::objectId($id);
                }
            }
            fwrite(STDOUT, "Seeded 2 default news sources.\n");
        }

        $sqlitePublishedCount = $articles->count(['status' => 'PUBLISHED']);
        if ($sqlitePublishedCount === 0 && $sqliteMobileCategory) {
            $now = new \MongoDB\BSON\UTCDateTime();
            $sampleContent = "Công nghệ trí tuệ nhân tạo (AI) và các thiết bị di động thông minh đang phát triển vượt bậc. Những tính năng nhận diện giọng nói, tối ưu hóa camera và xử lý ngôn ngữ tự nhiên đã trở thành tiêu chuẩn mới cho các dòng smartphone hiện đại.";
            $admin = $users->findByUsername('admin');
            if (!$admin) {
                throw new \RuntimeException('Tài khoản admin demo không tồn tại.');
            }
            $existingMongoArticle = $mongo->find(
                $contentDb,
                'articles',
                ['slug' => 'chao-mung-den-voi-genk-magazine'],
                ['limit' => 1],
            );
            $mongoArticleId = $existingMongoArticle
                ? (string) $existingMongoArticle[0]->_id
                : $mongo->insert($contentDb, 'articles', [
                    'title' => 'Chào mừng đến với GenK Magazine - Tạp chí công nghệ hàng đầu',
                    'slug' => 'chao-mung-den-voi-genk-magazine',
                    'summary' => 'Nền tảng đọc tin tức công nghệ đa nguồn, cập nhật liên tục các xu hướng AI, Mobile và Internet.',
                    'content' => [['type' => 'paragraph', 'text' => $sampleContent]],
                    'content_plain_text' => $sampleContent,
                    'category_ids' => [],
                    'source_id' => $sourceId,
                    'status' => 'published',
                    'published_at' => $now,
                    'created_by' => 'system',
                    'updated_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            $articles->create([
                'article_id' => $mongoArticleId,
                'title' => 'Chào mừng đến với GenK Magazine - Tạp chí công nghệ hàng đầu',
                'slug' => 'chao-mung-den-voi-genk-magazine',
                'summary' => 'Nền tảng đọc tin tức công nghệ đa nguồn, cập nhật liên tục các xu hướng AI, Mobile và Internet.',
                'author_id' => $admin['id'],
                'category_id' => $sqliteMobileCategory['id'],
                'source_id' => $sourceId ? (string) $sourceId : null,
                'publication_status' => 'PUBLISHED',
                'content_sync_status' => 'READY',
            ]);
            fwrite(STDOUT, "Seeded 1 sample published article.\n");
        }
    } catch (\Throwable $mongoError) {
        fwrite(STDOUT, "MongoDB seed skipped or deferred: {$mongoError->getMessage()}\n");
    }

    fwrite(STDOUT, "Seed completed successfully.\n");
} catch (\Throwable $error) {
    fwrite(STDERR, "Demo seed failed: {$error->getMessage()}\n");
    exit(1);
}
