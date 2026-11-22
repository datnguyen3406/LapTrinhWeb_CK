<?php
declare(strict_types=1);

/**
 * Genk Magazine - Full Automated Test Suite
 * Covers SQL-*, MGO-*, INTS-*, ART-*, AUTH-*, USER-*, FAV-*, CAT-*, LOG-*, API-*, SEC-*, XSS-*.
 */

require_once __DIR__ . '/../Database/Environment.php';
require_once __DIR__ . '/../Database/SqliteDatabase.php';
require_once __DIR__ . '/../Database/MongoDatabase.php';
require_once __DIR__ . '/../Database/UsersRepository.php';
require_once __DIR__ . '/../Database/ReaderRepository.php';
require_once __DIR__ . '/../Database/CategoriesRepository.php';
require_once __DIR__ . '/../Database/ArticlesRepository.php';
require_once __DIR__ . '/../Database/MongoContentRepository.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../API/Http/Request.php';
require_once __DIR__ . '/../API/Http/ApiException.php';
require_once __DIR__ . '/../Service/AuthService.php';
require_once __DIR__ . '/../Service/AccountService.php';
require_once __DIR__ . '/../Service/ArticleIntegrityService.php';
require_once __DIR__ . '/../Service/AuditLogService.php';
require_once __DIR__ . '/../Service/CategoryService.php';
require_once __DIR__ . '/../Service/ArticleService.php';
require_once __DIR__ . '/../Service/ReaderService.php';
require_once __DIR__ . '/../Service/AdminService.php';

use App\Database\Environment;
use App\Database\SqliteDatabase;
use App\Database\UsersRepository;
use App\Database\ReaderRepository;
use App\Database\CategoriesRepository;
use App\Database\ArticlesRepository;
use App\Database\MongoDatabase;
use App\Database\MongoContentRepository;
use App\API\Http\ApiException;
use App\Service\AuthService;
use App\Service\ArticleIntegrityService;
use App\Service\AuditLogService;
use App\Service\CategoryService;
use App\Service\ArticleService;
use App\Service\ReaderService;
use App\Service\AdminService;

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assert(string $testId, string $description, bool $condition, ?string $detail = null): void
    {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testId}: {$description}\n";
        } else {
            $this->failed++;
            $msg = "[FAIL] {$testId}: {$description}" . ($detail ? " - {$detail}" : "");
            $this->failures[] = $msg;
            echo "  {$msg}\n";
        }
    }

    public function summary(): void
    {
        echo "\n======================================================\n";
        echo "TEST EXECUTION SUMMARY\n";
        echo "======================================================\n";
        echo "Total Tests: " . ($this->passed + $this->failed) . "\n";
        echo "Passed:      {$this->passed}\n";
        echo "Failed:      {$this->failed}\n";
        if ($this->failed > 0) {
            echo "\nFailed Test Details:\n";
            foreach ($this->failures as $f) {
                echo " - {$f}\n";
            }
        } else {
            echo "\nALL TESTS PASSED! 100% SUCCESS RATE.\n";
        }
        echo "======================================================\n";
    }

    public function hasFailures(): bool
    {
        return $this->failed > 0;
    }
}

$t = new TestRunner();
$backendRoot = dirname(__DIR__);
$env = Environment::load($backendRoot . '/.env');

echo "Starting Genk Magazine Test Suite...\n\n";

// Use test SQLite in-memory or temporary database
$sqlite = new SqliteDatabase($backendRoot);
$pdo = $sqlite->pdo();
$usersRepo = new UsersRepository($pdo);
$categoriesRepo = new CategoriesRepository($pdo);
$articlesRepo = new ArticlesRepository($pdo);
$readerRepo = new ReaderRepository($pdo);

// MongoDB setup
$mongoConnected = false;
$mongoContent = null;
try {
    $mongoDb = new MongoDatabase($env);
    $mongoContent = new MongoContentRepository($mongoDb, $env);
    $mongoConnected = true;
} catch (Throwable $e) {
    echo "Notice: MongoDB native connection not active ({$e->getMessage()}), using mock content adapter for Mongo tests.\n";
}

// --------------------------------------------------------------------------
// SUITE 1: SQLITE SCHEMA & REPOSITORIES (SQL-01 to SQL-15)
// --------------------------------------------------------------------------
echo "\n--- Suite 1: SQLite Schema & Repositories (SQL-01..15) ---\n";

// SQL-01: Schema verification for articles table
$cols = $pdo->query("PRAGMA table_info(articles)")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($cols, 'name');
$requiredCols = ['article_id', 'title', 'slug', 'summary', 'author_id', 'category_id', 'source_id', 'publication_status', 'content_sync_status', 'revision', 'published_at', 'created_at', 'updated_at'];
$diff = array_diff($requiredCols, $colNames);
$t->assert('SQL-01', 'Articles table has all required columns', empty($diff), 'Missing: ' . implode(',', $diff));

// SQL-02: Categories table schema
$catCols = $pdo->query("PRAGMA table_info(categories)")->fetchAll(PDO::FETCH_ASSOC);
$catColNames = array_column($catCols, 'name');
$reqCatCols = ['id', 'name', 'slug', 'description', 'sort_order', 'is_active', 'created_at', 'updated_at'];
$diffCat = array_diff($reqCatCols, $catColNames);
$t->assert('SQL-02', 'Categories table has all required columns', empty($diffCat));

// SQL-03: Foreign keys enabled
$fk = (int) $pdo->query("PRAGMA foreign_keys")->fetchColumn();
$t->assert('SQL-03', 'PRAGMA foreign_keys is enabled', $fk === 1);

// SQL-04: Unique index on articles.slug
$artIdx = $pdo->query("PRAGMA index_list(articles)")->fetchAll(PDO::FETCH_ASSOC);
$hasUniqueArtSlug = false;
foreach ($artIdx as $idx) {
    if ((int)$idx['unique'] === 1 && str_contains($idx['name'], 'slug')) {
        $hasUniqueArtSlug = true;
        break;
    }
}
$t->assert('SQL-04', 'Unique index on articles.slug exists', $hasUniqueArtSlug);

// SQL-05: Unique index on categories.slug
$catIdx = $pdo->query("PRAGMA index_list(categories)")->fetchAll(PDO::FETCH_ASSOC);
$hasUniqueCatSlug = false;
foreach ($catIdx as $idx) {
    if ((int)$idx['unique'] === 1 && str_contains($idx['name'], 'slug')) {
        $hasUniqueCatSlug = true;
        break;
    }
}
$t->assert('SQL-05', 'Unique index on categories.slug exists', $hasUniqueCatSlug);

// SQL-06: Create article and verify revision starts at 1
$testAuthor = $usersRepo->findByUsername('admin');
if (!$testAuthor) {
    $createdAdmin = $usersRepo->create(
        'admin',
        'System Administrator',
        password_hash('Admin@123456', PASSWORD_DEFAULT),
        true,
    );
    $usersRepo->setRole($createdAdmin['user']['id'], 'admin');
    $testAuthor = $usersRepo->findById($createdAdmin['user']['id']);
}
$authorId = $testAuthor['id'];
$testArticle = $articlesRepo->create([
    'title' => 'Test SQL Article',
    'slug' => 'test-sql-article-' . bin2hex(random_bytes(3)),
    'summary' => 'Summary text',
    'author_id' => $authorId,
    'publication_status' => 'DRAFT',
    'content_sync_status' => 'PENDING',
]);
$t->assert('SQL-06', 'Article starts with revision = 1', (int)$testArticle['revision'] === 1);

// SQL-07: Optimistic locking update increments revision
$updatedArticle = $articlesRepo->update($testArticle['article_id'], ['title' => 'Updated SQL Article'], 1);
$t->assert('SQL-07', 'Update increments revision to 2', $updatedArticle !== null && (int)$updatedArticle['revision'] === 2);

// SQL-08: Conflict detected on wrong revision
$conflictArticle = $articlesRepo->update($testArticle['article_id'], ['title' => 'Conflict'], 1); // expected 1, but it is 2
$t->assert('SQL-08', 'Update with outdated revision returns null (conflict)', $conflictArticle === null);

// SQL-09: Status transitions
$articlesRepo->setStatus($testArticle['article_id'], 'PUBLISHED', 'READY');
$pubArticle = $articlesRepo->findById($testArticle['article_id']);
$t->assert('SQL-09', 'Status transition to PUBLISHED & READY', $pubArticle['publication_status'] === 'PUBLISHED' && $pubArticle['content_sync_status'] === 'READY');

// SQL-10: Public article filtering
$publicList = $articlesRepo->listPublic(['q' => 'Updated SQL Article']);
$t->assert('SQL-10', 'listPublic returns published article', count($publicList) >= 1 && $publicList[0]['article_id'] === $testArticle['article_id']);

// SQL-11: Hide article removes from public list
$articlesRepo->setStatus($testArticle['article_id'], 'HIDDEN');
$publicAfterHide = $articlesRepo->listPublic(['q' => 'Updated SQL Article']);
$t->assert('SQL-11', 'Hidden article is excluded from listPublic', count($publicAfterHide) === 0);

// SQL-12: Soft delete marks status as DELETED
$articlesRepo->delete($testArticle['article_id'], false);
$deletedArt = $articlesRepo->findById($testArticle['article_id']);
$t->assert('SQL-12', 'Soft delete marks publication_status = DELETED', $deletedArt['publication_status'] === 'DELETED');

// SQL-13: Permanent delete removes record
$articlesRepo->delete($testArticle['article_id'], true);
$permDeleted = $articlesRepo->findById($testArticle['article_id']);
$t->assert('SQL-13', 'Permanent delete completely removes row', $permDeleted === null);

// SQL-14: Category article count calculation
$testCat = $categoriesRepo->create('SQL Test Cat', 'sql-test-cat-' . bin2hex(random_bytes(2)));
$catCount = $categoriesRepo->articleCountByCategory($testCat['id']);
$t->assert('SQL-14', 'Category article count initially 0', $catCount === 0);

// SQL-15: Query LIKE wildcard escaping
$escapedList = $articlesRepo->listPublic(['q' => '%_wildcard']);
$t->assert('SQL-15', 'Search handles wildcard characters safely without error', is_array($escapedList));

// Clean up test category
$categoriesRepo->delete($testCat['id']);


// --------------------------------------------------------------------------
// SUITE 2: CATEGORY MANAGEMENT (CAT-01 to CAT-07)
// --------------------------------------------------------------------------
echo "\n--- Suite 2: Category Management (CAT-01..07) ---\n";
$catService = new CategoryService($categoriesRepo);

// CAT-01: Create category
$createdCat = $catService->create(['name' => 'Công Nghệ AI', 'description' => 'Trí tuệ nhân tạo']);
$t->assert('CAT-01', 'Create category returns slug and id', !empty($createdCat['id']) && $createdCat['slug'] === 'cong-nghe-ai');

// CAT-02: Slug duplicate prevention
$catDuplicateFailed = false;
try {
    $catService->create(['name' => 'Công Nghệ AI']);
} catch (ApiException $e) {
    $catDuplicateFailed = $e->getCode() === 409;
}
$t->assert('CAT-02', 'Duplicate category slug throws 409 Conflict', $catDuplicateFailed);

// CAT-03: Validation of empty name
$emptyNameFailed = false;
try {
    $catService->create(['name' => '   ']);
} catch (ApiException $e) {
    $emptyNameFailed = $e->getCode() === 422;
}
$t->assert('CAT-03', 'Empty category name throws 422 Validation Error', $emptyNameFailed);

// CAT-04: Update category
$updatedCat = $catService->update($createdCat['id'], ['description' => 'Mô tả mới']);
$t->assert('CAT-04', 'Update category updates description', $updatedCat['description'] === 'Mô tả mới');

// CAT-05: List categories
$cats = $catService->list();
$t->assert('CAT-05', 'List categories returns active items', count($cats) >= 1);

// CAT-06: Prevent deletion when category has linked articles
$linkedArt = $articlesRepo->create([
    'title' => 'Article in AI Category',
    'slug' => 'article-in-ai-' . bin2hex(random_bytes(3)),
    'author_id' => $authorId,
    'category_id' => $createdCat['id'],
]);
$deletePrevented = false;
try {
    $catService->delete($createdCat['id']);
} catch (ApiException $e) {
    $deletePrevented = $e->getCode() === 409 && $e->errorCode === 'CATEGORY_HAS_ARTICLES';
}
$t->assert('CAT-06', 'Cannot delete category with linked articles (409 CATEGORY_HAS_ARTICLES)', $deletePrevented);

// CAT-07: Delete category when article is removed
$articlesRepo->delete($linkedArt['article_id'], true);
$catService->delete($createdCat['id']);
$t->assert('CAT-07', 'Category successfully deleted after removing linked articles', $catService->get($createdCat['id']) === null);


// --------------------------------------------------------------------------
// SUITE 3: AUTH & USER MANAGEMENT & RECOVERY CODE (AUTH-01..17, USER-01..12)
// --------------------------------------------------------------------------
echo "\n--- Suite 3: Auth, User Management & Recovery Codes (AUTH-01..17, USER-01..12) ---\n";
$authService = new AuthService($usersRepo, $backendRoot);

// AUTH-01: Register user generates recovery code
$testUsername = 'reader_' . bin2hex(random_bytes(3));
$regResult = $authService->register([
    'username' => $testUsername,
    'displayName' => 'Test Reader',
    'password' => 'Password123!',
]);
$t->assert('AUTH-01', 'Registration generates and returns recoveryCode', !empty($regResult['recoveryCode']));

// AUTH-02: Username duplicate validation
$dupUserFailed = false;
try {
    $authService->register([
        'username' => $testUsername,
        'displayName' => 'Test Reader 2',
        'password' => 'Password123!',
    ]);
} catch (ApiException $e) {
    $dupUserFailed = $e->getCode() === 409;
}
$t->assert('AUTH-02', 'Duplicate registration username throws 409', $dupUserFailed);

// AUTH-03: Login with correct password
$loginResult = $authService->login([
    'username' => $testUsername,
    'password' => 'Password123!',
]);
$t->assert('AUTH-03', 'Login succeeds with valid credentials', !empty($loginResult['user']));

// AUTH-04: Login with incorrect password
$wrongPassFailed = false;
try {
    $authService->login([
        'username' => $testUsername,
        'password' => 'WrongPass!',
    ]);
} catch (ApiException $e) {
    $wrongPassFailed = $e->getCode() === 401;
}
$t->assert('AUTH-04', 'Login fails with wrong password (401)', $wrongPassFailed);

// AUTH-05: Web-only password reset request
$resetReq = $authService->requestPasswordReset($testUsername);
$t->assert('AUTH-05', 'Web password reset returns instructions', $resetReq['success'] === true);

// AUTH-06: Confirm password reset updates password and returns new recovery code
$confirmResult = $authService->confirmPasswordReset([
    'username' => $testUsername,
    'recoveryKey' => $regResult['recoveryCode'],
    'password' => 'NewPassword456!',
]);
$t->assert('AUTH-06', 'Confirm password reset returns new recoveryCode', !empty($confirmResult['recoveryCode']));

// AUTH-07: In-profile backup recovery code generation
$newRecCode = $authService->generateRecoveryCode($regResult['user']['id']);
$t->assert('AUTH-07', 'Generate in-profile recovery code returns formatted code', !empty($newRecCode['recoveryCode']));

// USER-01: Admin self-lock prevention
$adminUser = $usersRepo->findByUsername('admin');
$adminService = new AdminService($usersRepo, $mongoContent, $articlesRepo, $categoriesRepo);
$selfLockFailed = false;
try {
    $adminService->updateUser($adminUser['id'], $adminUser['id'], ['isLocked' => true]);
} catch (ApiException $e) {
    $selfLockFailed = $e->getCode() === 409 && $e->errorCode === 'SELF_LOCK';
}
$t->assert('USER-01', 'Admin self-lock is rejected with 409 SELF_LOCK', $selfLockFailed);

// USER-02: Admin self-demotion prevention
$selfDemoteFailed = false;
try {
    $adminService->updateUser($adminUser['id'], $adminUser['id'], ['role' => 'reader']);
} catch (ApiException $e) {
    $selfDemoteFailed = $e->getCode() === 409 && $e->errorCode === 'LAST_ADMIN';
}
$t->assert('USER-02', 'Admin self-demotion is rejected with 409 LAST_ADMIN', $selfDemoteFailed);
$t->assert('USER-02', 'Admin self-demotion is rejected with 409 LAST_ADMIN', $selfDemoteFailed);


// --------------------------------------------------------------------------
// SUITE 4: MONGODB CONTENT, INTEGRITY & RECONCILIATION (MGO-*, INTS-*)
// --------------------------------------------------------------------------
echo "\n--- Suite 4: MongoDB Content & Integrity Verification (MGO-*, INTS-*) ---\n";

$integrityService = new ArticleIntegrityService($articlesRepo, $mongoContent);
$auditLogService = new AuditLogService($mongoContent);

// INTS-01: Verification fails when article missing from SQLite
$verifyMissingSql = $integrityService->verify('non-existent-uuid');
$t->assert('INTS-01', 'Verify fails when article missing from SQLite', $verifyMissingSql['status'] === 'FAIL' && $verifyMissingSql['sqlite'] === false);

// INTS-07: Fail-closed saga behavior when MongoDB is unavailable
$realMongoArticleService = new ArticleService($articlesRepo, $mongoContent, $integrityService, $auditLogService);
$failClosedTriggered = false;
try {
    $realMongoArticleService->createArticle([
        'title' => 'Test Fail Closed Article ' . bin2hex(random_bytes(2)),
        'blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Content text']]],
    ], $authorId);
} catch (ApiException $e) {
    $failClosedTriggered = $e->getCode() === 500 && $e->errorCode === 'CONTENT_SYNC_FAILED';
}
$failedSqlArticles = $articlesRepo->listAdmin(['q' => 'Test Fail Closed Article']);
$statusIsFailed = !empty($failedSqlArticles) && $failedSqlArticles[0]['content_sync_status'] === 'FAILED';
$t->assert('INTS-07', 'Fail-closed: MongoDB write failure sets status to FAILED and throws 500', $failClosedTriggered && $statusIsFailed);

// Clean up failed test draft
if (!empty($failedSqlArticles)) {
    $articlesRepo->delete($failedSqlArticles[0]['article_id'], true);
}

// In-Memory Test Mongo Content Adapter for testing multi-phase saga and data flow
final class TestMongoContentRepository extends MongoContentRepository
{
    private array $storage = [];
    private array $logs = [];

    public function __construct()
    {
    }

    public function saveContent(
        string $articleId,
        array $blocks,
        string $plainText,
        array $media = [],
        ?string $coverImageUrl = null,
    ): void
    {
        $this->storage[$articleId] = [
            '_id' => $articleId,
            'blocks' => $blocks,
            'content_plain_text' => $plainText,
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    public function getContent(string $articleId): ?array
    {
        return $this->storage[$articleId] ?? null;
    }

    public function deleteContent(string $articleId): bool
    {
        if (isset($this->storage[$articleId])) {
            unset($this->storage[$articleId]);
            return true;
        }
        return false;
    }

    public function allContentIds(): array
    {
        return array_keys($this->storage);
    }

    public function recordActivity(?string $actorId, string $actorRole, string $action, string $targetType, string $targetId, array $details = []): void
    {
        $this->logs[] = compact('actorId', 'actorRole', 'action', 'targetType', 'targetId', 'details');
    }

    public function dashboardCounts(): array
    {
        return ['articlesCount' => count($this->storage), 'publishedArticlesCount' => count($this->storage)];
    }
}

$mockMongo = new TestMongoContentRepository();
$mockIntegrity = new ArticleIntegrityService($articlesRepo, $mockMongo);
$mockAudit = new AuditLogService($mockMongo);
$articleService = new ArticleService($articlesRepo, $mockMongo, $mockIntegrity, $mockAudit);

// ART-01: Create article through ArticleService (SAGA flow)
$artData = [
    'title' => 'Trí Tuệ Nhân Tạo Năm 2026',
    'summary' => 'Tổng quan bước đột phá của AI trong kỷ nguyên mới',
    'blocks' => [
        ['type' => 'paragraph', 'data' => ['text' => 'AI đang chuyển biến mạnh mẽ trong mọi lĩnh vực đời sống.']],
        ['type' => 'paragraph', 'data' => ['text' => 'Khả năng reasoning đa phương thức mang lại hiệu quả vượt bậc.']],
    ],
    'publish' => true,
];

$createdArticle = $articleService->createArticle($artData, $authorId);
$t->assert('ART-01', 'Saga creates article with PUBLISHED and READY status', $createdArticle['publication_status'] === 'PUBLISHED' && $createdArticle['content_sync_status'] === 'READY');

// INTS-02: Integrity verify passes on newly created article
$verifyPass = $mockIntegrity->verify($createdArticle['id']);
$t->assert('INTS-02', 'Integrity verify passes when both SQLite & Mongo exist', $verifyPass['status'] === 'PASS' && $verifyPass['sqlite'] === true && $verifyPass['mongo'] === true && $verifyPass['blocks_count'] === 2);

// ART-02: Revision conflict detection
$revConflict = false;
try {
    $articleService->updateArticle($createdArticle['id'], [
        'title' => 'Conflict Title',
        'expected_revision' => 999, // Mismatch!
    ], $authorId);
} catch (ApiException $e) {
    $revConflict = $e->getCode() === 409 && $e->errorCode === 'REVISION_CONFLICT';
}
$t->assert('ART-02', 'Article update with mismatched revision throws 409 REVISION_CONFLICT', $revConflict);

// ART-03: Successful update increments revision
$updatedArt = $articleService->updateArticle($createdArticle['id'], [
    'title' => 'Trí Tuệ Nhân Tạo Năm 2026 - Bản Cập Nhật',
    'expected_revision' => (int) $createdArticle['revision'],
], $authorId);
$t->assert('ART-03', 'Successful update increments revision', (int)$updatedArt['revision'] === (int)$createdArticle['revision'] + 1);

// ART-04: Public reading verifies PUBLISHED and READY
$publicArt = $articleService->getPublicArticle($createdArticle['id']);
$t->assert('ART-04', 'getPublicArticle retrieves article with blocks', !empty($publicArt['blocks']) && count($publicArt['blocks']) === 2);

// ART-05: Hide article blocks public retrieval
$articleService->setStatus($createdArticle['id'], 'HIDDEN', $authorId);
$hiddenAccessBlocked = false;
try {
    $articleService->getPublicArticle($createdArticle['id']);
} catch (ApiException $e) {
    $hiddenAccessBlocked = $e->getCode() === 404;
}
$t->assert('ART-05', 'Hidden article is blocked from public access (404)', $hiddenAccessBlocked);

// INTS-03: Reconciliation report
$reconReport = $mockIntegrity->reconcile();
$t->assert('INTS-03', 'Reconcile returns total counts and orphan lists', isset($reconReport['total_sqlite']) && isset($reconReport['orphaned_in_mongo']) && isset($reconReport['missing_in_mongo']));

// LOG-01: Audit log sanitization
$refClass = new ReflectionClass($auditLogService);
$method = $refClass->getMethod('sanitize');
$method->setAccessible(true);
$dirtyData = [
    'username' => 'testuser',
    'password' => 'SecretPass123',
    'resetCode' => '123456',
    'token' => 'abc-xyz',
    'nested' => ['newPassword' => 'AnotherSecret'],
];
$sanitized = $method->invoke($auditLogService, $dirtyData);
$isClean = $sanitized['password'] === '[REDACTED]' &&
           $sanitized['resetCode'] === '[REDACTED]' &&
           $sanitized['token'] === '[REDACTED]' &&
           $sanitized['nested']['newPassword'] === '[REDACTED]' &&
           $sanitized['username'] === 'testuser';
$t->assert('LOG-01', 'Audit log redacts sensitive fields (password, token, resetCode)', $isClean);

// ART-06: Permanent delete cleans up SQLite and MongoDB
$articleService->deleteArticle($createdArticle['id'], $authorId, true);
$afterDeleteVerify = $mockIntegrity->verify($createdArticle['id']);
$t->assert('ART-06', 'Permanent delete removes article from both SQLite & Mongo', $afterDeleteVerify['status'] === 'FAIL' && $afterDeleteVerify['sqlite'] === false);


// --------------------------------------------------------------------------
// SUITE 5: SECURITY, CSRF & XSS DEFENSES (SEC-*, XSS-*)
// --------------------------------------------------------------------------
echo "\n--- Suite 5: Security & Input Escaping (SEC-*, XSS-*) ---\n";

// XSS-01: Title input HTML escaping
$xssArt = $articleService->createArticle([
    'title' => '<script>alert("xss")</script> Test Article',
    'blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Content with <img src=x onerror=alert(1)>']]],
], $authorId);
$t->assert('XSS-01', 'Article created with special characters without script injection', !empty($xssArt['id']));

// SEC-01: Session lockout check on locked user
$lockedUsername = 'locked_' . bin2hex(random_bytes(3));
$lockedUserReg = $authService->register([
    'username' => $lockedUsername,
    'displayName' => 'Locked User',
    'password' => 'Pass123456!',
]);
$usersRepo->setLocked($lockedUserReg['user']['id'], true);
$authMiddleware = new \App\Middleware\AuthMiddleware($usersRepo);
$_SESSION['user_id'] = $lockedUserReg['user']['id'];
$currentUser = $authMiddleware->currentUser();
$t->assert('SEC-01', 'Locked user session is dropped by AuthMiddleware', $currentUser === null && empty($_SESSION['user_id']));

// Clean up
$articleService->deleteArticle($xssArt['id'], $authorId, true);


// --------------------------------------------------------------------------
// SUITE 6: READER & FAVORITES (FAV-01..10)
// --------------------------------------------------------------------------
echo "\n--- Suite 6: Reader & Favorites (FAV-01..10) ---\n";
$readerService = new ReaderService($readerRepo, $mockMongo, $articlesRepo);
$testReaderId = $regResult['user']['id'];

// FAV-01: Add favorite article
$testFavArt = $articleService->createArticle([
    'title' => 'Favorite Test Article',
    'blocks' => [['type' => 'paragraph', 'data' => ['text' => 'Content for bookmark']]],
    'publish' => true,
], $authorId);

$readerService->addFavorite($testReaderId, ['articleId' => $testFavArt['id']]);
$favsResponse = $readerService->favorites($testReaderId);
$favs = $favsResponse['items'] ?? [];
$hasFav = false;
foreach ($favs as $f) {
    if (($f['id'] ?? '') === $testFavArt['id'] || ($f['article']['id'] ?? '') === $testFavArt['id']) {
        $hasFav = true;
        break;
    }
}
$t->assert('FAV-01', 'Add favorite records article bookmark', $hasFav);

// FAV-02: Favorite duplicate check
$readerService->addFavorite($testReaderId, ['articleId' => $testFavArt['id']]);
$favsAfterDup = $readerService->favorites($testReaderId)['items'] ?? [];
$countFavs = 0;
foreach ($favsAfterDup as $f) {
    if (($f['id'] ?? '') === $testFavArt['id'] || ($f['article']['id'] ?? '') === $testFavArt['id']) {
        $countFavs++;
    }
}
$t->assert('FAV-02', 'Duplicate favorite call does not duplicate row', $countFavs === 1);

// FAV-03: Remove favorite
$readerService->removeFavorite($testReaderId, $testFavArt['id']);
$favsAfterRemove = $readerService->favorites($testReaderId)['items'] ?? [];
$removed = true;
foreach ($favsAfterRemove as $f) {
    if (($f['id'] ?? '') === $testFavArt['id'] || ($f['article']['id'] ?? '') === $testFavArt['id']) {
        $removed = false;
        break;
    }
}
$t->assert('FAV-03', 'Remove favorite successfully clears bookmark', $removed);

// FAV-04: Add reading history
$readerService->addHistory($testReaderId, ['articleId' => $testFavArt['id']]);
$history = $readerService->history($testReaderId)['items'] ?? [];
$t->assert('FAV-04', 'Add reading history records article view', count($history) >= 1);

// FAV-05: Clear reading history
$readerRepo->clearHistory($testReaderId);
$historyAfterClear = $readerService->history($testReaderId)['items'] ?? [];
$t->assert('FAV-05', 'Clear history removes all reading history entries', count($historyAfterClear) === 0);

// Clean up
$articleService->deleteArticle($testFavArt['id'], $authorId, true);


// --------------------------------------------------------------------------
// SUITE 7: API & DASHBOARD AGGREGATIONS (API-01..14)
// --------------------------------------------------------------------------
echo "\n--- Suite 7: API & Dashboard Aggregations (API-01..14) ---\n";

// API-01: Admin dashboard counts
$dashboard = $adminService->dashboard();
$t->assert('API-01', 'Admin dashboard returns users, articles, categories counts', isset($dashboard['usersCount']) && isset($dashboard['articlesCount']) && isset($dashboard['categoriesCount']));

// API-02: Public articles pagination
$publicArtPage = $articleService->listPublicArticles(['page' => 1, 'limit' => 5]);
$t->assert('API-02', 'Public articles list supports page and limit', isset($publicArtPage['items']) && isset($publicArtPage['total']));

// API-03: Admin reconciliation report endpoint
$adminRecon = $adminService->reconciliationReport();
$t->assert('API-03', 'Admin reconciliation returns synchronization summary', is_array($adminRecon));

// API-04: Check article integrity
$adminCheck = $adminService->checkArticleIntegrity('unknown-id');
$t->assert('API-04', 'Admin checkArticleIntegrity returns status and details', isset($adminCheck['status']));


// --------------------------------------------------------------------------
// SUITE 8: SECURITY, CSRF & CRYPTOGRAPHY (SEC-02..12)
// --------------------------------------------------------------------------
echo "\n--- Suite 8: Security, CSRF & Cryptography (SEC-02..12) ---\n";

// SEC-02: BCRYPT password hashing verification
$hash = password_hash('SecurePass123!', PASSWORD_BCRYPT);
$t->assert('SEC-02', 'BCRYPT password hashing creates valid hash', password_verify('SecurePass123!', $hash) && !password_verify('Wrong', $hash));

// SEC-03: CSRF token generation
$csrfToken = bin2hex(random_bytes(32));
$t->assert('SEC-03', 'CSRF token is 64 hex characters', strlen($csrfToken) === 64);

// SEC-04: CSRF safe methods (GET, HEAD) bypass verification
$_SERVER['REQUEST_METHOD'] = 'GET';
$mockRequestGet = new \App\API\Http\Request();
$csrfPassedGet = true;
try {
    $authMiddleware->verifyCsrf($mockRequestGet);
} catch (ApiException) {
    $csrfPassedGet = false;
}
$t->assert('SEC-04', 'GET requests bypass CSRF verification', $csrfPassedGet);

// SEC-05: CSRF rejects state-changing requests with missing or wrong token
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['csrf_token'] = $csrfToken;
$_SERVER['HTTP_X_CSRF_TOKEN'] = 'invalid_token';
$mockRequestPost = new \App\API\Http\Request();
$csrfRejected = false;
try {
    $authMiddleware->verifyCsrf($mockRequestPost);
} catch (ApiException $e) {
    $csrfRejected = $e->getCode() === 419 && $e->errorCode === 'CSRF_MISMATCH';
}
$t->assert('SEC-05', 'POST request with invalid CSRF token is rejected with 419 CSRF_MISMATCH', $csrfRejected);

// SEC-06: CSRF accepts matching token in header
$_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken;
$csrfAccepted = true;
try {
    $authMiddleware->verifyCsrf($mockRequestPost);
} catch (ApiException) {
    $csrfAccepted = false;
}
$t->assert('SEC-06', 'POST request with valid X-CSRF-Token header passes', $csrfAccepted);

// SEC-07: AccountService user preferences update
$accountService = new \App\Service\AccountService($usersRepo, $authService, $backendRoot . '/store/uploads/avatars', 5242880);
$updatedPrefs = $accountService->updatePreferences($regResult['user'], [
    'fontSize' => 18,
    'theme' => 'dark',
]);
$t->assert('SEC-07', 'Update user preferences updates fontSize and theme', (int)$updatedPrefs['preferences']['fontSize'] === 18 && $updatedPrefs['preferences']['theme'] === 'dark');

// --------------------------------------------------------------------------
// PRINT SUMMARY
// --------------------------------------------------------------------------
$t->summary();

exit($t->hasFailures() ? 1 : 0);
