<?php
declare(strict_types=1);

namespace App\API;

use App\API\Http\ApiException;
use App\API\Http\Request;
use App\API\Http\Response;
use App\Database\MongoContentRepository;
use App\Database\MongoDatabase;
use App\Database\ReaderRepository;
use App\Database\SqliteDatabase;
use App\Database\UsersRepository;
use App\Middleware\AuthMiddleware;
use App\Service\AccountService;
use App\Service\AdminService;
use App\Service\AuthService;
use App\Service\ReaderService;

final class Kernel
{
    private UsersRepository $users;
    private ReaderRepository $readerRepository;
    private MongoContentRepository $content;
    private AuthMiddleware $auth;
    private AuthService $authService;
    private AccountService $accountService;
    private ReaderService $readerService;
    private AdminService $adminService;

    public function __construct(string $backendRoot)
    {
        $environment = \App\Database\Environment::load($backendRoot . '/.env');
        $database = new SqliteDatabase($backendRoot);
        $this->users = new UsersRepository($database->pdo());
        $this->readerRepository = new ReaderRepository($database->pdo());
        $this->content = new MongoContentRepository(new MongoDatabase($environment), $environment);
        $this->auth = new AuthMiddleware($this->users);
        $this->authService = new AuthService($this->users, $backendRoot);
        $this->accountService = new AccountService(
            $this->users,
            $this->authService,
            $backendRoot . '/store/uploads/avatars',
            max(1, (int) ($environment['UPLOAD_MAX_BYTES'] ?? 5242880)),
        );
        $this->readerService = new ReaderService($this->readerRepository, $this->content);
        $this->adminService = new AdminService($this->users, $this->content);
    }

    public function dispatch(Request $request): never
    {
        $method = $request->method();
        $path = $request->path();

        if ($method === 'GET' && $path === 'auth/csrf') {
            $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
            Response::success(['csrfToken' => $_SESSION['csrf_token']]);
        }
        if ($method === 'GET' && preg_match('#^media/([a-f0-9]{24})$#i', $path, $match)) {
            $this->serveMedia(strtolower($match[1]));
        }
        $this->auth->verifyCsrf($request);

        if ($method === 'POST' && $path === 'auth/register') {
            $result = $this->authService->register($request->body());
            Response::success($result, 201);
        }
        if ($method === 'POST' && $path === 'auth/login') {
            Response::success($this->authService->login($request->body()));
        }
        if ($method === 'POST' && $path === 'auth/logout') {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', [
                    'expires' => time() - 42000,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => 'Lax',
                ]);
            }
            session_destroy();
            Response::noContent();
        }
        if ($method === 'GET' && $path === 'auth/me') {
            $user = $this->auth->currentUser();
            if (!$user) {
                throw new ApiException('Vui lòng đăng nhập để tiếp tục.', 401, 'UNAUTHENTICATED');
            }
            Response::success($this->authService->currentUser($user));
        }
        if ($method === 'POST' && $path === 'auth/password-reset/request') {
            Response::success($this->authService->requestPasswordReset(
                (string) ($request->body()['username'] ?? ''),
            ));
        }
        if ($method === 'POST' && $path === 'auth/password-reset/confirm') {
            $result = $this->authService->confirmPasswordReset($request->body());
            Response::success([
                'message' => 'Mật khẩu đã được cập nhật.',
                'recoveryCode' => $result['recoveryCode'],
            ]);
        }

        if ($method === 'PATCH' && $path === 'me') {
            Response::success(['user' => $this->accountService->updateProfile(
                $this->auth->requireUser(),
                $request->body(),
            )]);
        }
        if ($method === 'POST' && $path === 'me/avatar') {
            Response::success($this->accountService->uploadAvatar(
                $this->auth->requireUser(),
                $request->file('avatar'),
            ));
        }
        if ($method === 'POST' && $path === 'me/password') {
            $this->accountService->changePassword($this->auth->requireUser(), $request->body());
            Response::success(['message' => 'Mật khẩu đã được cập nhật.']);
        }
        if ($method === 'PATCH' && $path === 'me/preferences') {
            Response::success($this->accountService->updatePreferences(
                $this->auth->requireUser(),
                $request->body(),
            ));
        }
        if ($method === 'POST' && $path === 'me/recovery-code') {
            $user = $this->auth->requireUser();
            Response::success($this->authService->generateRecoveryCode($user['id']));
        }

        if ($method === 'GET' && $path === 'me/favorites') {
            Response::success($this->readerService->favorites($this->auth->requireUser()['id']));
        }
        if ($method === 'POST' && $path === 'me/favorites') {
            $this->readerService->addFavorite($this->auth->requireUser()['id'], $request->body());
            Response::success(['saved' => true], 201);
        }
        if ($method === 'DELETE' && preg_match('#^me/favorites/([a-f0-9]{24})$#i', $path, $match)) {
            $this->readerService->removeFavorite($this->auth->requireUser()['id'], strtolower($match[1]));
            Response::noContent();
        }
        if ($method === 'GET' && $path === 'me/favorite-folders') {
            Response::success($this->readerService->folders($this->auth->requireUser()['id']));
        }
        if ($method === 'POST' && $path === 'me/favorite-folders') {
            Response::success($this->readerService->createFolder(
                $this->auth->requireUser()['id'],
                $request->body(),
            ), 201);
        }
        if ($method === 'GET' && $path === 'me/reading-history') {
            Response::success($this->readerService->history($this->auth->requireUser()['id']));
        }
        if ($method === 'POST' && $path === 'me/reading-history') {
            $this->readerService->addHistory($this->auth->requireUser()['id'], $request->body());
            Response::success(['recorded' => true], 201);
        }
        if ($method === 'DELETE' && $path === 'me/reading-history') {
            $this->auth->requireUser();
            $this->readerRepository->clearHistory($_SESSION['user_id']);
            Response::noContent();
        }
        if ($method === 'POST' && $path === 'me/categories') {
            Response::success($this->readerService->createCategory(
                $this->auth->requireUser()['id'],
                $request->body(),
            ), 201);
        }

        if ($method === 'GET' && $path === 'articles') {
            $items = $this->content->publicArticles([
                'q' => $request->query('q'),
                'category' => $request->query('category'),
                'source' => $request->query('source'),
                'page' => $request->query('page'),
                'limit' => $request->query('limit'),
            ]);
            $user = $this->auth->currentUser();
            if ($user) {
                $favoriteIds = array_column($this->readerRepository->favorites($user['id']), 'article_id');
                foreach ($items as &$item) {
                    $item['isFavorite'] = in_array($item['id'], $favoriteIds, true);
                }
                unset($item);
            }
            Response::success(['items' => $items, 'featured' => $items[0] ?? null]);
        }
        if ($method === 'GET' && preg_match('#^articles/([a-f0-9]{24})$#i', $path, $match)) {
            $article = $this->content->article(strtolower($match[1]));
            if (!$article) {
                throw new ApiException('Không tìm thấy bài báo.', 404, 'ARTICLE_NOT_FOUND');
            }
            $user = $this->auth->currentUser();
            if ($user) {
                $article['isFavorite'] = in_array(
                    strtolower($match[1]),
                    array_column($this->readerRepository->favorites($user['id']), 'article_id'),
                    true,
                );
            }
            Response::success(['article' => $article]);
        }
        if ($method === 'GET' && $path === 'categories') {
            Response::success(['items' => $this->content->categories()]);
        }
        if ($method === 'GET' && $path === 'sources') {
            Response::success(['items' => $this->content->sources()]);
        }

        if ($method === 'GET' && $path === 'admin/dashboard') {
            $this->auth->requireAdmin();
            Response::success($this->adminService->dashboard());
        }
        if ($method === 'GET' && $path === 'admin/users') {
            $this->auth->requireAdmin();
            Response::success($this->adminService->users([
                'q' => $request->query('q'),
                'role' => $request->query('role'),
            ]));
        }
        if ($method === 'PATCH' && preg_match('#^admin/users/([a-f0-9-]{36})$#i', $path, $match)) {
            $actor = $this->auth->requireAdmin();
            Response::success($this->adminService->updateUser($actor['id'], $match[1], $request->body()));
        }
        if ($method === 'GET' && $path === 'admin/activity-logs') {
            $this->auth->requireAdmin();
            Response::success($this->adminService->activityLogs([
                'q' => $request->query('q'),
                'action' => $request->query('action'),
                'date' => $request->query('date'),
            ]));
        }
        if ($method === 'DELETE' && $path === 'admin/activity-logs') {
            $this->auth->requireAdmin();
            $this->content->deleteActivityLogs();
            Response::noContent();
        }
        if ($method === 'GET' && $path === 'admin/articles') {
            $this->auth->requireAdmin();
            Response::success($this->adminService->articleList());
        }
        if ($method === 'GET' && $path === 'admin/categories') {
            $this->auth->requireAdmin();
            Response::success(['items' => $this->content->categories(true)]);
        }
        if ($method === 'POST' && $path === 'admin/categories') {
            $actor = $this->auth->requireAdmin();
            Response::success([
                'category' => $this->content->createCategory($request->body(), $actor['id']),
            ], 201);
        }
        if ($method === 'PATCH' && preg_match('#^admin/categories/([a-f0-9]{24})$#i', $path, $match)) {
            $actor = $this->auth->requireAdmin();
            $category = $this->content->updateCategory(
                strtolower($match[1]),
                $request->body(),
                $actor['id'],
            );
            if (!$category) {
                throw new ApiException('Không tìm thấy danh mục.', 404, 'CATEGORY_NOT_FOUND');
            }
            Response::success(['category' => $category]);
        }
        if ($method === 'DELETE' && preg_match('#^admin/categories/([a-f0-9]{24})$#i', $path, $match)) {
            $actor = $this->auth->requireAdmin();
            if (!$this->content->updateCategory(
                strtolower($match[1]),
                ['isActive' => false],
                $actor['id'],
            )) {
                throw new ApiException('Không tìm thấy danh mục.', 404, 'CATEGORY_NOT_FOUND');
            }
            Response::noContent();
        }
        if ($method === 'POST' && $path === 'admin/media') {
            $actor = $this->auth->requireAdmin();
            Response::success($this->content->uploadMedia($request->file('media'), $actor['id']), 201);
        }
        if ($method === 'POST' && $path === 'admin/articles') {
            $actor = $this->auth->requireAdmin();
            Response::success(['article' => $this->content->createArticle($request->body(), $actor['id'])], 201);
        }
        if ($method === 'PATCH' && preg_match('#^admin/articles/([a-f0-9]{24})$#i', $path, $match)) {
            $actor = $this->auth->requireAdmin();
            $article = $this->content->updateArticle(strtolower($match[1]), $request->body(), $actor['id']);
            if (!$article) {
                throw new ApiException('Không tìm thấy bài viết.', 404, 'ARTICLE_NOT_FOUND');
            }
            Response::success(['article' => $article]);
        }
        if ($method === 'DELETE' && preg_match('#^admin/articles/([a-f0-9]{24})$#i', $path, $match)) {
            $actor = $this->auth->requireAdmin();
            $articleId = strtolower($match[1]);
            if (!$this->content->deleteArticle($articleId, $actor['id'])) {
                throw new ApiException('Không tìm thấy bài viết.', 404, 'ARTICLE_NOT_FOUND');
            }
            Response::noContent();
        }
        if ($method === 'GET' && $path === 'admin/sources') {
            $this->auth->requireAdmin();
            Response::success($this->adminService->sourceList());
        }
        if ($method === 'POST' && $path === 'admin/sources') {
            $actor = $this->auth->requireAdmin();
            Response::success(['source' => $this->content->createSource($request->body(), $actor['id'])], 201);
        }
        if ($method === 'PATCH' && preg_match('#^admin/sources/([a-f0-9]{24})$#i', $path, $match)) {
            $actor = $this->auth->requireAdmin();
            $source = $this->content->updateSource(strtolower($match[1]), $request->body(), $actor['id']);
            if (!$source) {
                throw new ApiException('Không tìm thấy nguồn báo.', 404, 'SOURCE_NOT_FOUND');
            }
            Response::success(['source' => $source]);
        }
        if ($method === 'DELETE' && preg_match('#^admin/sources/([a-f0-9]{24})$#i', $path, $match)) {
            $actor = $this->auth->requireAdmin();
            if (!$this->content->updateSource(strtolower($match[1]), ['isActive' => false], $actor['id'])) {
                throw new ApiException('Không tìm thấy nguồn báo.', 404, 'SOURCE_NOT_FOUND');
            }
            Response::noContent();
        }

        throw new ApiException('Không tìm thấy API.', 404, 'NOT_FOUND');
    }

    private function serveMedia(string $id): never
    {
        $avatar = $this->accountService->avatar($id);
        if ($avatar) {
            header('Cache-Control: public, max-age=86400');
            Response::file($avatar['content'], $avatar['mime']);
        }
        $media = $this->content->media($id);
        if (!$media) {
            throw new ApiException('Không tìm thấy tệp.', 404, 'MEDIA_NOT_FOUND');
        }
        $mime = $media['file']->metadata->contentType ?? 'application/octet-stream';
        if (!preg_match('#^[a-z0-9.+-]+/[a-z0-9.+-]+$#i', $mime)) {
            $mime = 'application/octet-stream';
        }
        header('Cache-Control: public, max-age=86400');
        header('ETag: "' . md5($id) . '"');
        Response::file($media['content'], $mime);
    }
}
