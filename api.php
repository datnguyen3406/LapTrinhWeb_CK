<?php

declare(strict_types=1);

require __DIR__ . '/Includes/bootstrap.php';

$path = trim((string) ($_GET['path'] ?? ''), '/');
$segments = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (($segments[0] ?? '') === 'csrf') {
    require_method('GET');
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    json_response(['csrfToken' => $_SESSION['csrf_token']]);
}

if (($segments[0] ?? '') === 'ws-token') {
    require_method('GET');
    $user = require_user();
    $articleId = filter_input(INPUT_GET, 'article_id', FILTER_VALIDATE_INT);
    if ($articleId === false || $articleId === null || $articleId < 1) {
        json_response(['error' => 'Mã bài viết không hợp lệ.'], 422);
    }
    $statement = $pdo->prepare('SELECT author_id FROM articles WHERE id = :id');
    $statement->execute(['id' => $articleId]);
    if ((int) $statement->fetchColumn() !== (int) $user['id']) {
        json_response(['error' => 'Bạn không có quyền mở phiên cộng tác này.'], 403);
    }
    $secret = getenv('APP_KEY');
    if ($secret === false || strlen($secret) < 32) {
        json_response(['error' => 'Tính năng cộng tác thời gian thực chưa được cấu hình.'], 503);
    }
    $expires = time() + 300;
    $message = $articleId . '.' . $user['id'] . '.' . $expires;
    $token = rtrim(strtr(base64_encode($message . '.' . hash_hmac('sha256', $message, $secret)), '+/', '-_'), '=');
    $socketUrl = getenv('APP_WS_URL') ?: 'ws://127.0.0.1:8081';
    json_response(['url' => $socketUrl . '/?article_id=' . $articleId . '&token=' . rawurlencode($token)]);
}

if (($segments[0] ?? '') === 'auth') {
    $action = $segments[1] ?? '';
    if ($action === 'register') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $name = trim((string) ($input['display_name'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        if ($email === false || mb_strlen($name) < 2 || mb_strlen($name) > 80 || strlen($password) < 10) {
            json_response(['error' => 'Nhập email hợp lệ, tên từ 2–80 ký tự và mật khẩu ít nhất 10 ký tự.'], 422);
        }
        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO users (email, password_hash, display_name) VALUES (:email, :hash, :name)');
            $statement->execute(['email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'name' => $name]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_preferences (user_id) VALUES (:id)')->execute(['id' => $userId]);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                json_response(['error' => 'Email này đã được đăng ký.'], 409);
            }
            throw $exception;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        json_response(['user' => current_user(), 'csrfToken' => $_SESSION['csrf_token']], 201);
    }
    if ($action === 'login') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $statement = $pdo->prepare('SELECT id, password_hash FROM users WHERE email = :email');
        $statement->execute(['email' => trim((string) ($input['email'] ?? ''))]);
        $user = $statement->fetch();
        if ($user === false || !password_verify((string) ($input['password'] ?? ''), $user['password_hash'])) {
            json_response(['error' => 'Email hoặc mật khẩu không chính xác.'], 422);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        json_response(['user' => current_user(), 'csrfToken' => $_SESSION['csrf_token']]);
    }
    if ($action === 'logout') {
        require_method('POST');
        require_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        json_response(['ok' => true]);
    }
    if ($action === 'me') {
        require_method('GET');
        json_response(['user' => current_user()]);
    }
    if ($action === 'reset-request') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        if ($email !== false) {
            $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
            $userId = $statement->fetchColumn();
            if ($userId !== false) {
                $token = bin2hex(random_bytes(32));
                $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                    VALUES (:id, :token, datetime("now", "+1 hour"))
                    ON CONFLICT(user_id) DO UPDATE SET token_hash = excluded.token_hash, expires_at = excluded.expires_at')
                    ->execute(['id' => $userId, 'token' => hash('sha256', $token)]);
                enqueue_job('auth.password_reset_email', ['email' => $email, 'token' => $token]);
            }
        }
        json_response(['message' => 'Nếu email tồn tại, hướng dẫn đặt lại mật khẩu sẽ được gửi trong ít phút.']);
    }
    if ($action === 'reset') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $token = (string) ($input['token'] ?? '');
        $password = (string) ($input['password'] ?? '');
        if (strlen($password) < 10 || strlen($token) !== 64) {
            json_response(['error' => 'Token không hợp lệ hoặc mật khẩu chưa đủ 10 ký tự.'], 422);
        }
        $pdo->beginTransaction();
        $statement = $pdo->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash = :hash AND expires_at > CURRENT_TIMESTAMP');
        $statement->execute(['hash' => hash('sha256', $token)]);
        $userId = $statement->fetchColumn();
        if ($userId === false) {
            $pdo->rollBack();
            json_response(['error' => 'Liên kết đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'], 422);
        }
        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $userId]);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $userId]);
        $pdo->commit();
        json_response(['message' => 'Mật khẩu đã được cập nhật.']);
    }
    json_response(['error' => 'Không tìm thấy endpoint tài khoản.'], 404);
}

if (($segments[0] ?? '') === 'articles') {
    $articleId = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;
    $action = $segments[2] ?? '';
    if ($method === 'GET' && $articleId === null) {
        $query = trim((string) ($_GET['q'] ?? ''));
        $statement = $pdo->prepare('SELECT id, title, category, excerpt, image_url, published_at, is_pinned
            FROM articles WHERE title LIKE :q OR excerpt LIKE :q ORDER BY is_pinned DESC, id DESC');
        $statement->execute(['q' => '%' . $query . '%']);
        json_response(['articles' => $statement->fetchAll()]);
    }
    if ($articleId === null && !($method === 'POST' && $action === '')) {
        json_response(['error' => 'Đường dẫn bài viết không hợp lệ.'], 404);
    }
    if ($method === 'POST' && $action === '') {
        require_csrf();
        $user = require_user();
        $input = request_json();
        $title = trim((string) ($input['title'] ?? ''));
        $category = trim((string) ($input['category'] ?? ''));
        $excerpt = trim((string) ($input['excerpt'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $image = trim((string) ($input['image_url'] ?? ''));
        $imageScheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
        if ($title === '' || mb_strlen($title) > 240 || $category === '' || mb_strlen($category) > 80 ||
            mb_strlen($excerpt) > 1000 || $content === '' || mb_strlen($content) > 100000 ||
            ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || !in_array($imageScheme, ['http', 'https'], true)))) {
            json_response(['error' => 'Tiêu đề, chuyên mục và nội dung là bắt buộc.'], 422);
        }
        $statement = $pdo->prepare('INSERT INTO articles (author_id, title, category, excerpt, content, image_url, published_at)
            VALUES (:author, :title, :category, :excerpt, :content, :image, :published)');
        $statement->execute([
            'author' => $user['id'], 'title' => $title, 'category' => $category,
            'excerpt' => $excerpt, 'content' => $content,
            'image' => $image ?: 'https://picsum.photos/seed/article/800/450',
            'published' => date('d/m/Y • H:i'),
        ]);
        $newId = (int) $pdo->lastInsertId();
        enqueue_job('article.changed', ['id' => $newId, 'action' => 'created']);
        json_response(['id' => $newId], 201);
    }
    $statement = $pdo->prepare('SELECT id, author_id, title, category, excerpt, content, image_url, published_at, is_pinned, is_locked, lock_password_hash
        FROM articles WHERE id = :id');
    $statement->execute(['id' => $articleId]);
    $article = $statement->fetch();
    if ($article === false) {
        json_response(['error' => 'Không tìm thấy bài viết.'], 404);
    }
    if ($method === 'GET' && $action === '') {
        $isOwner = (int) $article['author_id'] === (int) ($_SESSION['user_id'] ?? 0);
        unset($article['author_id']);
        if ((bool) $article['is_locked'] &&
            !$isOwner &&
            !in_array($articleId, $_SESSION['unlocked_articles'] ?? [], true)) {
            unset($article['content'], $article['lock_password_hash']);
            $article['requires_password'] = true;
        } else {
            unset($article['lock_password_hash']);
        }
        json_response(['article' => $article]);
    }
    if ($method === 'POST' && $action === 'unlock') {
        require_csrf();
        if (!(bool) $article['is_locked']) {
            json_response(['error' => 'Bài viết này không bị khóa.'], 409);
        }
        $input = request_json();
        if (!password_verify((string) ($input['password'] ?? ''), (string) $article['lock_password_hash'])) {
            json_response(['error' => 'Mật khẩu bài viết không chính xác.'], 403);
        }
        $_SESSION['unlocked_articles'] ??= [];
        if (!in_array($articleId, $_SESSION['unlocked_articles'], true)) {
            $_SESSION['unlocked_articles'][] = $articleId;
        }
        unset($article['author_id'], $article['lock_password_hash']);
        json_response(['article' => $article]);
    }
    $user = require_user();
    if ((int) $article['author_id'] !== (int) $user['id']) {
        json_response(['error' => 'Bạn không có quyền sửa bài viết này.'], 403);
    }
    if ($method === 'POST' && $action === 'autosave') {
        require_csrf();
        $input = request_json();
        $title = trim((string) ($input['title'] ?? $article['title']));
        $content = (string) ($input['content'] ?? $article['content']);
        if ($title === '' || mb_strlen($title) > 240 || $content === '') {
            json_response(['error' => 'Tiêu đề và nội dung không được để trống.'], 422);
        }
        $jobId = enqueue_job('article.autosave', [
            'id' => $articleId, 'author_id' => $user['id'], 'title' => $title, 'content' => $content,
        ]);
        json_response(['queued' => true, 'job_id' => $jobId], 202);
    }
    if ($method === 'PATCH' && $action === '') {
        require_csrf();
        $input = request_json();
        $title = trim((string) ($input['title'] ?? $article['title']));
        $category = trim((string) ($input['category'] ?? $article['category']));
        $excerpt = trim((string) ($input['excerpt'] ?? $article['excerpt']));
        $content = trim((string) ($input['content'] ?? $article['content']));
        $image = trim((string) ($input['image_url'] ?? $article['image_url']));
        $imageScheme = strtolower((string) parse_url($image, PHP_URL_SCHEME));
        if ($title === '' || mb_strlen($title) > 240 || $category === '' || mb_strlen($category) > 80 ||
            mb_strlen($excerpt) > 1000 || $content === '' || mb_strlen($content) > 100000 ||
            ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || !in_array($imageScheme, ['http', 'https'], true)))) {
            json_response(['error' => 'Tiêu đề, chuyên mục, nội dung hoặc URL ảnh không hợp lệ.'], 422);
        }
        $statement = $pdo->prepare('UPDATE articles SET title = :title, category = :category, excerpt = :excerpt,
            content = :content, image_url = :image, updated_at = CURRENT_TIMESTAMP WHERE id = :id AND author_id = :author');
        $statement->execute([
            'title' => $title,
            'category' => $category,
            'excerpt' => $excerpt,
            'content' => $content,
            'image' => $image,
            'id' => $articleId, 'author' => $user['id'],
        ]);
        enqueue_job('article.changed', ['id' => $articleId, 'action' => 'updated']);
        json_response(['ok' => true]);
    }
    if ($method === 'POST' && $action === 'pin') {
        require_csrf();
        $pdo->prepare('UPDATE articles SET is_pinned = 1 - is_pinned, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute(['id' => $articleId]);
        json_response(['is_pinned' => !(bool) $article['is_pinned']]);
    }
    if ($method === 'POST' && $action === 'lock') {
        require_csrf();
        $input = request_json();
        $locked = filter_var($input['locked'] ?? false, FILTER_VALIDATE_BOOL);
        $password = (string) ($input['password'] ?? '');
        if ($locked && strlen($password) < 8) {
            json_response(['error' => 'Mật khẩu khóa bài viết phải có ít nhất 8 ký tự.'], 422);
        }
        $pdo->prepare('UPDATE articles SET is_locked = :locked, lock_password_hash = :hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([
                'locked' => $locked ? 1 : 0,
                'hash' => $locked ? password_hash($password, PASSWORD_DEFAULT) : null,
                'id' => $articleId,
            ]);
        json_response(['is_locked' => $locked]);
    }
    if ($method === 'DELETE' && $action === '') {
        require_csrf();
        $pdo->prepare('DELETE FROM articles WHERE id = :id AND author_id = :author')
            ->execute(['id' => $articleId, 'author' => $user['id']]);
        json_response(['ok' => true]);
    }
    json_response(['error' => 'Phương thức hoặc thao tác không được hỗ trợ.'], 405);
}

if (($segments[0] ?? '') === 'jobs' && isset($segments[1]) && ctype_digit($segments[1])) {
    require_method('GET');
    $user = require_user();
    $statement = $pdo->prepare('SELECT type, payload, status, last_error FROM jobs WHERE id = :id');
    $statement->execute(['id' => (int) $segments[1]]);
    $job = $statement->fetch();
    if ($job === false || $job['type'] !== 'article.autosave') {
        json_response(['error' => 'Không tìm thấy tác vụ.'], 404);
    }
    $payload = json_decode($job['payload'], true, 32, JSON_THROW_ON_ERROR);
    if ((int) ($payload['author_id'] ?? 0) !== (int) $user['id']) {
        json_response(['error' => 'Bạn không có quyền xem tác vụ này.'], 403);
    }
    json_response(['status' => $job['status'], 'error' => $job['status'] === 'failed' ? $job['last_error'] : null]);
}

if (($segments[0] ?? '') === 'user') {
    $user = require_user();
    if (($segments[1] ?? '') === 'profile') {
        require_method('PATCH');
        require_csrf();
        $input = request_json();
        $name = trim((string) ($input['display_name'] ?? ''));
        $avatar = trim((string) ($input['avatar_url'] ?? ''));
        $avatarScheme = strtolower((string) parse_url($avatar, PHP_URL_SCHEME));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 80 ||
            ($avatar !== '' && (!filter_var($avatar, FILTER_VALIDATE_URL) || !in_array($avatarScheme, ['http', 'https'], true)))) {
            json_response(['error' => 'Tên hiển thị hoặc URL ảnh đại diện không hợp lệ.'], 422);
        }
        $pdo->prepare('UPDATE users SET display_name = :name, avatar_url = :avatar WHERE id = :id')
            ->execute(['name' => $name, 'avatar' => $avatar, 'id' => $user['id']]);
        json_response(['user' => current_user()]);
    }
    if (($segments[1] ?? '') === 'preferences') {
        if ($method === 'GET') {
            $statement = $pdo->prepare('SELECT theme, font_size, note_color FROM user_preferences WHERE user_id = :id');
            $statement->execute(['id' => $user['id']]);
            json_response(['preferences' => $statement->fetch()]);
        }
        require_method('PUT');
        require_csrf();
        $input = request_json();
        $theme = $input['theme'] ?? 'light';
        $fontSize = filter_var($input['font_size'] ?? 16, FILTER_VALIDATE_INT);
        $color = (string) ($input['note_color'] ?? '#fff3b0');
        if (!in_array($theme, ['light', 'dark'], true) || $fontSize === false || $fontSize < 12 || $fontSize > 24 || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            json_response(['error' => 'Tùy chọn giao diện không hợp lệ.'], 422);
        }
        $pdo->prepare('INSERT INTO user_preferences (user_id, theme, font_size, note_color)
            VALUES (:id, :theme, :size, :color) ON CONFLICT(user_id) DO UPDATE SET theme = excluded.theme,
            font_size = excluded.font_size, note_color = excluded.note_color')
            ->execute(['id' => $user['id'], 'theme' => $theme, 'size' => $fontSize, 'color' => $color]);
        json_response(['ok' => true]);
    }
}

json_response(['error' => 'Không tìm thấy endpoint.'], 404);
