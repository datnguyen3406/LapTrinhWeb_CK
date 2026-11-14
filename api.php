<?php

declare(strict_types=1);

require __DIR__ . '/Includes/bootstrap.php';

$path = trim((string) ($_GET['path'] ?? ''), '/');
$segments = $path === '' ? [] : explode('/', $path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 1. CSRF Token Endpoint
if (($segments[0] ?? '') === 'csrf') {
    require_method('GET');
    $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    json_response(['csrfToken' => $_SESSION['csrf_token']]);
}

// 2. WebSocket Auth Token
if (($segments[0] ?? '') === 'ws-token') {
    require_method('GET');
    $user = require_user();
    $articleId = filter_input(INPUT_GET, 'article_id', FILTER_VALIDATE_INT);
    if ($articleId === false || $articleId === null || $articleId < 1) {
        json_response(['error' => 'Mã bài viết không hợp lệ.'], 422);
    }
    
    // Kiểm tra quyền truy cập bài viết (chủ bài hoặc được chia sẻ)
    $stmt = $pdo->prepare('SELECT id, author_id FROM articles WHERE id = :id');
    $stmt->execute(['id' => $articleId]);
    $art = $stmt->fetch();
    if ($art === false) {
        json_response(['error' => 'Không tìm thấy bài viết.'], 404);
    }
    
    $secret = getenv('APP_KEY') ?: 'secret-key-at-least-32-chars-long-for-demo-web-2026';
    $expires = time() + 3600;
    $message = $articleId . '.' . $user['id'] . '.' . $expires;
    $token = rtrim(strtr(base64_encode($message . '.' . hash_hmac('sha256', $message, $secret)), '+/', '-_'), '=');
    $socketUrl = getenv('APP_WS_URL') ?: 'ws://127.0.0.1:8081';
    json_response(['url' => $socketUrl . '/?article_id=' . $articleId . '&token=' . rawurlencode($token)]);
}

// 3. Phân hệ Authentication
if (($segments[0] ?? '') === 'auth') {
    $action = $segments[1] ?? '';
    
    // Đăng ký tài khoản (Bắt buộc nhập mật khẩu 2 lần, hash bcrypt, lưu tài khoản)
    if ($action === 'register') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $rawEmail = trim((string) ($input['email'] ?? ''));
        $email = filter_var($rawEmail, FILTER_VALIDATE_EMAIL);
        $name = trim((string) ($input['display_name'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirmation'] ?? '');

        if ($email === false) {
            json_response(['error' => 'Vui lòng nhập định dạng email hợp lệ.'], 422);
        }
        $email = strtolower($email);

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            json_response(['error' => 'Tên hiển thị phải từ 2 đến 80 ký tự.'], 422);
        }
        if (strlen($password) < 6) {
            json_response(['error' => 'Mật khẩu phải có ít nhất 6 ký tự.'], 422);
        }
        if ($password !== $passwordConfirm) {
            json_response(['error' => 'Mật khẩu xác nhận không khớp.'], 422);
        }

        $activationToken = bin2hex(random_bytes(32));

        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('INSERT INTO users (email, password_hash, display_name, role, is_active, activation_token) VALUES (:email, :hash, :name, "reader", 0, :token)');
            $statement->execute([
                'email' => $email,
                'hash' => password_hash($password, PASSWORD_BCRYPT),
                'name' => $name,
                'token' => $activationToken,
            ]);
            $userId = (int) $pdo->lastInsertId();
            $pdo->prepare('INSERT INTO user_preferences (user_id) VALUES (:id)')->execute(['id' => $userId]);
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (str_contains($exception->getMessage(), 'UNIQUE') || $exception->getCode() === '23000') {
                json_response(['error' => 'Email này đã được đăng ký trên hệ thống. Vui lòng đăng nhập hoặc dùng email khác.'], 409);
            }
            throw $exception;
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        json_response([
            'message' => 'Đăng ký tài khoản thành công! Đang chuyển đến trang đăng nhập...',
            'user' => current_user(),
            'csrfToken' => $_SESSION['csrf_token'],
            'activationLink' => '/Modules/Auth/activate.php?token=' . urlencode($activationToken),
        ], 201);
    }

    // Đăng nhập
    if ($action === 'login') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');

        if ($email === '' || $password === '') {
            json_response(['error' => 'Vui lòng nhập đầy đủ email và mật khẩu.'], 422);
        }

        $statement = $pdo->prepare('SELECT id, password_hash FROM users WHERE LOWER(email) = :email');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();

        if ($user === false || !password_verify($password, (string)$user['password_hash'])) {
            json_response(['error' => 'Email hoặc mật khẩu không chính xác.'], 422);
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        json_response([
            'message' => 'Đăng nhập thành công! Đang vào trang chủ...',
            'user' => current_user(),
            'csrfToken' => $_SESSION['csrf_token']
        ]);
    }

    // Đăng xuất
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

    // Lấy thông tin user hiện tại
    if ($action === 'me') {
        require_method('GET');
        json_response(['user' => current_user()]);
    }

    // Yêu cầu reset mật khẩu
    if ($action === 'reset-request') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $email = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $resetToken = null;
        if ($email !== false) {
            $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $statement->execute(['email' => $email]);
            $userId = $statement->fetchColumn();
            if ($userId !== false) {
                $rawToken = bin2hex(random_bytes(16));
                $resetToken = $rawToken;
                $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                    VALUES (:id, :token, datetime("now", "+1 hour"))
                    ON CONFLICT(user_id) DO UPDATE SET token_hash = excluded.token_hash, expires_at = excluded.expires_at')
                    ->execute(['id' => $userId, 'token' => hash('sha256', $rawToken)]);
            }
        }
        json_response([
            'message' => 'Nếu email tồn tại, link hướng dẫn đặt lại mật khẩu đã được khởi tạo.',
            'demoToken' => $resetToken, // Hỗ trợ demo trực tiếp
        ]);
    }

    // Đặt lại mật khẩu với token
    if ($action === 'reset') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $token = trim((string) ($input['token'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        $passwordConfirm = (string) ($input['password_confirmation'] ?? '');

        if ($password !== $passwordConfirm) {
            json_response(['error' => 'Mật khẩu xác nhận không khớp.'], 422);
        }
        if (strlen($password) < 6) {
            json_response(['error' => 'Mật khẩu phải có ít nhất 6 ký tự.'], 422);
        }

        $statement = $pdo->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash = :hash AND expires_at > CURRENT_TIMESTAMP');
        $statement->execute(['hash' => hash('sha256', $token)]);
        $userId = $statement->fetchColumn();
        if ($userId === false) {
            json_response(['error' => 'Mã đặt lại mật khẩu không hợp lệ hoặc đã hết hạn.'], 422);
        }

        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($password, PASSWORD_BCRYPT), 'id' => $userId]);
        $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')->execute(['id' => $userId]);
        $pdo->commit();

        json_response(['message' => 'Đặt lại mật khẩu thành công. Vui lòng đăng nhập lại.']);
    }

    json_response(['error' => 'Endpoint auth không tồn tại.'], 404);
}

// 4. Phân hệ Hồ sơ và Tùy chọn người dùng (User & Preferences)
if (($segments[0] ?? '') === 'user') {
    $user = require_user();
    $subAction = $segments[1] ?? '';

    // Cập nhật Profile và Upload Avatar
    if ($subAction === 'profile') {
        require_method('POST', 'PATCH');
        require_csrf();
        
        $name = trim((string) ($_POST['display_name'] ?? ''));
        if ($name === '') {
            $input = request_json();
            $name = trim((string) ($input['display_name'] ?? ''));
        }

        if (mb_strlen($name) < 2 || mb_strlen($name) > 80) {
            json_response(['error' => 'Tên hiển thị phải từ 2 đến 80 ký tự.'], 422);
        }

        $avatarUrl = $user['avatar_url'];

        // Xử lý upload avatar nếu có file
        if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar_file'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowedTypes, true)) {
                json_response(['error' => 'Chỉ chấp nhận file ảnh định dạng JPG, PNG, WEBP hoặc GIF.'], 422);
            }
            if ($file['size'] > 3 * 1024 * 1024) {
                json_response(['error' => 'Dung lượng ảnh đại diện không được vượt quá 3MB.'], 422);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $destination = __DIR__ . '/Assets/Uploads/avatars/' . $newFileName;
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                $avatarUrl = '/Assets/Uploads/avatars/' . $newFileName;
            }
        }

        $pdo->prepare('UPDATE users SET display_name = :name, avatar_url = :avatar WHERE id = :id')
            ->execute(['name' => $name, 'avatar' => $avatarUrl, 'id' => $user['id']]);

        json_response(['message' => 'Cập nhật hồ sơ thành công!', 'user' => current_user()]);
    }

    // Đổi mật khẩu
    if ($subAction === 'change-password') {
        require_method('POST');
        require_csrf();
        $input = request_json();
        $currentPass = (string) ($input['current_password'] ?? '');
        $newPass = (string) ($input['new_password'] ?? '');
        $newPassConfirm = (string) ($input['new_password_confirmation'] ?? '');

        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id');
        $stmt->execute(['id' => $user['id']]);
        $currentHash = $stmt->fetchColumn();

        if (!password_verify($currentPass, (string)$currentHash)) {
            json_response(['error' => 'Mật khẩu hiện tại không đúng.'], 422);
        }
        if (strlen($newPass) < 6) {
            json_response(['error' => 'Mật khẩu mới phải có ít nhất 6 ký tự.'], 422);
        }
        if ($newPass !== $newPassConfirm) {
            json_response(['error' => 'Mật khẩu mới xác nhận không khớp.'], 422);
        }

        $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($newPass, PASSWORD_BCRYPT), 'id' => $user['id']]);

        json_response(['message' => 'Đổi mật khẩu thành công!']);
    }

    // Tùy chọn giao diện (Theme, Font Size, Note Color)
    if ($subAction === 'preferences') {
        if ($method === 'GET') {
            $stmt = $pdo->prepare('SELECT theme, font_size, note_color FROM user_preferences WHERE user_id = :id');
            $stmt->execute(['id' => $user['id']]);
            $prefs = $stmt->fetch() ?: ['theme' => 'light', 'font_size' => 16, 'note_color' => '#ffffff'];
            json_response(['preferences' => $prefs]);
        }
        require_method('PUT', 'POST');
        require_csrf();
        $input = request_json();
        $theme = in_array($input['theme'] ?? '', ['light', 'dark'], true) ? $input['theme'] : 'light';
        $fontSize = filter_var($input['font_size'] ?? 16, FILTER_VALIDATE_INT);
        $fontSize = ($fontSize >= 12 && $fontSize <= 24) ? $fontSize : 16;
        $color = (string) ($input['note_color'] ?? '#ffffff');

        $pdo->prepare('INSERT INTO user_preferences (user_id, theme, font_size, note_color)
            VALUES (:id, :theme, :size, :color)
            ON CONFLICT(user_id) DO UPDATE SET theme = excluded.theme, font_size = excluded.font_size, note_color = excluded.note_color')
            ->execute(['id' => $user['id'], 'theme' => $theme, 'size' => $fontSize, 'color' => $color]);

        json_response(['message' => 'Lưu tùy chọn thành công!']);
    }

    json_response(['error' => 'Endpoint user không tồn tại.'], 404);
}

// 5. Phân hệ Quản lý Nhãn (Labels / Tags)
if (($segments[0] ?? '') === 'labels') {
    $user = require_user();
    $labelId = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;

    // Lấy danh sách nhãn
    if ($method === 'GET' && $labelId === null) {
        $stmt = $pdo->prepare('SELECT id, name FROM labels WHERE user_id = :uid ORDER BY name ASC');
        $stmt->execute(['uid' => $user['id']]);
        json_response(['labels' => $stmt->fetchAll()]);
    }

    // Thêm nhãn mới
    if ($method === 'POST' && $labelId === null) {
        require_csrf();
        $input = request_json();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 50) {
            json_response(['error' => 'Tên nhãn phải từ 1 đến 50 ký tự.'], 422);
        }
        try {
            $stmt = $pdo->prepare('INSERT INTO labels (user_id, name) VALUES (:uid, :name)');
            $stmt->execute(['uid' => $user['id'], 'name' => $name]);
            json_response(['id' => (int) $pdo->lastInsertId(), 'name' => $name], 201);
        } catch (PDOException) {
            json_response(['error' => 'Nhãn này đã tồn tại.'], 409);
        }
    }

    // Đổi tên nhãn
    if (($method === 'PATCH' || $method === 'PUT') && $labelId !== null) {
        require_csrf();
        $input = request_json();
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 50) {
            json_response(['error' => 'Tên nhãn không hợp lệ.'], 422);
        }
        $stmt = $pdo->prepare('UPDATE labels SET name = :name WHERE id = :id AND user_id = :uid');
        $stmt->execute(['name' => $name, 'id' => $labelId, 'uid' => $user['id']]);
        json_response(['ok' => true]);
    }

    // Xóa nhãn (Không xóa các bài viết liên kết!)
    if ($method === 'DELETE' && $labelId !== null) {
        require_csrf();
        $stmt = $pdo->prepare('DELETE FROM labels WHERE id = :id AND user_id = :uid');
        $stmt->execute(['id' => $labelId, 'uid' => $user['id']]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'Phương thức nhãn không được hỗ trợ.'], 405);
}

// 6. Phân hệ AI Hỏi & Đáp (AI Q&A)
if (($segments[0] ?? '') === 'ai') {
    $user = require_user();
    if (($segments[1] ?? '') === 'qa' && $method === 'POST') {
        require_csrf();
        $input = request_json();
        $question = trim((string) ($input['question'] ?? ''));
        if ($question === '') {
            json_response(['error' => 'Vui lòng nhập câu hỏi.'], 422);
        }

        // Lấy danh sách bài viết của user để làm context
        $stmt = $pdo->prepare('SELECT id, title, content FROM articles WHERE author_id = :uid ORDER BY id DESC LIMIT 20');
        $stmt->execute(['uid' => $user['id']]);
        $userArticles = $stmt->fetchAll();

        $result = ai_answer_articles_question($question, $userArticles);
        json_response($result);
    }
    json_response(['error' => 'Endpoint AI không tồn tại.'], 404);
}

// 7. Phân hệ Quản lý Bài viết (Articles)
if (($segments[0] ?? '') === 'articles') {
    $user = require_user();
    $articleId = isset($segments[1]) && ctype_digit($segments[1]) ? (int) $segments[1] : null;
    $action = $segments[2] ?? '';

    // Lấy danh sách bài viết (Hỗ trợ Live search cả Title & Content, Filter Label, Category, Tab Shared)
    if ($method === 'GET' && $articleId === null) {
        $search = trim((string) ($_GET['q'] ?? ''));
        $category = trim((string) ($_GET['category'] ?? ''));
        $labelId = filter_input(INPUT_GET, 'label_id', FILTER_VALIDATE_INT);
        $tab = trim((string) ($_GET['tab'] ?? 'my')); // 'my', 'shared', 'all'
        $sort = trim((string) ($_GET['sort'] ?? 'pinned')); // 'pinned', 'newest', 'updated'

        $params = [];
        $where = [];

        if ($tab === 'shared') {
            $query = 'SELECT a.id, a.author_id, a.title, a.category, a.excerpt, a.content, a.image_url, a.color,
                             a.is_pinned, a.pinned_at, a.is_locked, a.created_at, a.updated_at,
                             s.permission, u.display_name AS author_name, s.created_at AS shared_at
                      FROM articles a
                      JOIN article_shares s ON a.id = s.article_id
                      LEFT JOIN users u ON a.author_id = u.id
                      WHERE s.shared_with_user_id = :uid';
            $params['uid'] = $user['id'];
        } else {
            $query = 'SELECT a.id, a.author_id, a.title, a.category, a.excerpt, a.content, a.image_url, a.color,
                             a.is_pinned, a.pinned_at, a.is_locked, a.created_at, a.updated_at,
                             NULL as permission, u.display_name AS author_name, NULL as shared_at
                      FROM articles a
                      LEFT JOIN users u ON a.author_id = u.id
                      WHERE (a.author_id = :uid OR a.id IN (SELECT article_id FROM article_shares WHERE shared_with_user_id = :uid))';
            $params['uid'] = $user['id'];
        }

        if ($search !== '') {
            $query .= ' AND (a.title LIKE :search OR a.content LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }
        if ($category !== '') {
            $query .= ' AND a.category = :category';
            $params['category'] = $category;
        }
        if ($labelId !== false && $labelId !== null && $labelId > 0) {
            $query .= ' AND a.id IN (SELECT article_id FROM article_labels WHERE label_id = :lid)';
            $params['lid'] = $labelId;
        }

        // Sắp xếp
        if ($sort === 'pinned') {
            $query .= ' ORDER BY a.is_pinned DESC, a.pinned_at DESC, a.updated_at DESC, a.id DESC';
        } elseif ($sort === 'updated') {
            $query .= ' ORDER BY a.updated_at DESC, a.id DESC';
        } else {
            $query .= ' ORDER BY a.created_at DESC, a.id DESC';
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $articles = $stmt->fetchAll();

        // Xử lý bài viết bị khóa: ẩn nội dung nếu chưa unlock trong session
        $_SESSION['unlocked_articles'] ??= [];
        foreach ($articles as &$art) {
            $isOwner = (int) $art['author_id'] === $user['id'];
            $isUnlocked = in_array((int)$art['id'], $_SESSION['unlocked_articles'], true);
            $art['is_owner'] = $isOwner;

            // Kiểm tra xem bài có được share không
            $shareCountStmt = $pdo->prepare('SELECT COUNT(*) FROM article_shares WHERE article_id = :aid');
            $shareCountStmt->execute(['aid' => $art['id']]);
            $art['is_shared'] = ($shareCountStmt->fetchColumn() > 0) || ($art['permission'] !== null);

            if ((bool)$art['is_locked'] && !$isOwner && !$isUnlocked) {
                $art['content'] = '*** Nội dung được bảo vệ bằng mật khẩu ***';
                $art['excerpt'] = 'Nội dung này đã được khóa bằng mật khẩu riêng.';
                $art['requires_password'] = true;
            } else {
                $art['requires_password'] = false;
            }

            // Gắn danh sách nhãn của bài viết
            $lblStmt = $pdo->prepare('SELECT l.id, l.name FROM labels l JOIN article_labels al ON l.id = al.label_id WHERE al.article_id = :aid');
            $lblStmt->execute(['aid' => $art['id']]);
            $art['labels'] = $lblStmt->fetchAll();

            // Gắn danh sách tệp đính kèm
            $attStmt = $pdo->prepare('SELECT id, file_path, file_type, file_name FROM attachments WHERE article_id = :aid');
            $attStmt->execute(['aid' => $art['id']]);
            $art['attachments'] = $attStmt->fetchAll();
        }

        json_response(['articles' => $articles]);
    }

    // Tạo bài viết mới (Form ban đầu CHỈ BẮT BUỘC Title và Content!)
    if ($method === 'POST' && $articleId === null) {
        require_csrf();
        $input = request_json();
        $title = trim((string) ($input['title'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $category = trim((string) ($input['category'] ?? 'Tin tức'));
        $color = (string) ($input['color'] ?? '#ffffff');
        $imageUrl = trim((string) ($input['image_url'] ?? ''));

        if ($title === '' || $content === '') {
            json_response(['error' => 'Tiêu đề và nội dung là hai trường bắt buộc.'], 422);
        }

        $excerpt = mb_substr(strip_tags($content), 0, 150) . '...';
        $publishedAt = date('d/m/Y • H:i');

        $stmt = $pdo->prepare('INSERT INTO articles (author_id, title, category, excerpt, content, image_url, color, published_at, created_at, updated_at)
            VALUES (:uid, :title, :cat, :exc, :content, :img, :color, :pub, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        $stmt->execute([
            'uid' => $user['id'],
            'title' => $title,
            'cat' => $category ?: 'Tin tức',
            'exc' => $excerpt,
            'content' => $content,
            'img' => $imageUrl,
            'color' => $color,
            'pub' => $publishedAt,
        ]);
        $newId = (int) $pdo->lastInsertId();

        // Xử lý nhãn nếu có truyền vào
        if (!empty($input['label_ids']) && is_array($input['label_ids'])) {
            $insLbl = $pdo->prepare('INSERT OR IGNORE INTO article_labels (article_id, label_id) VALUES (:aid, :lid)');
            foreach ($input['label_ids'] as $lid) {
                $insLbl->execute(['aid' => $newId, 'lid' => (int)$lid]);
            }
        }

        json_response(['id' => $newId, 'message' => 'Tạo bài viết thành công!'], 201);
    }

    // Kiểm tra quyền hạn đối với bài viết cụ thể
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = :id');
    $stmt->execute(['id' => $articleId]);
    $article = $stmt->fetch();
    if ($article === false) {
        json_response(['error' => 'Không tìm thấy bài viết.'], 404);
    }

    $isOwner = (int)$article['author_id'] === $user['id'];
    $shareStmt = $pdo->prepare('SELECT permission FROM article_shares WHERE article_id = :aid AND shared_with_user_id = :uid');
    $shareStmt->execute(['aid' => $articleId, 'uid' => $user['id']]);
    $sharedPermission = $shareStmt->fetchColumn(); // 'read', 'edit' hoặc false
    $canEdit = $isOwner || ($sharedPermission === 'edit');

    // Xem chi tiết một bài viết
    if ($method === 'GET' && $action === '') {
        $isUnlocked = in_array($articleId, $_SESSION['unlocked_articles'] ?? [], true);
        if ((bool)$article['is_locked'] && !$isOwner && !$isUnlocked) {
            unset($article['content'], $article['lock_password_hash']);
            $article['requires_password'] = true;
        } else {
            unset($article['lock_password_hash']);
            $article['requires_password'] = false;
        }
        $article['can_edit'] = $canEdit;
        $article['is_owner'] = $isOwner;

        // Đính kèm labels
        $lblStmt = $pdo->prepare('SELECT l.id, l.name FROM labels l JOIN article_labels al ON l.id = al.label_id WHERE al.article_id = :aid');
        $lblStmt->execute(['aid' => $articleId]);
        $article['labels'] = $lblStmt->fetchAll();

        // Đính kèm attachments
        $attStmt = $pdo->prepare('SELECT id, file_path, file_type, file_name FROM attachments WHERE article_id = :aid');
        $attStmt->execute(['aid' => $articleId]);
        $article['attachments'] = $attStmt->fetchAll();

        // Đính kèm danh sách chia sẻ (nếu là chủ bài)
        if ($isOwner) {
            $shStmt = $pdo->prepare('SELECT s.id, s.permission, s.created_at, u.email, u.display_name
                                     FROM article_shares s JOIN users u ON s.shared_with_user_id = u.id WHERE s.article_id = :aid');
            $shStmt->execute(['aid' => $articleId]);
            $article['shares'] = $shStmt->fetchAll();
        }

        json_response(['article' => $article]);
    }

    // Auto-save trực tiếp (Debounce lưu ngầm)
    if ($method === 'POST' && $action === 'autosave') {
        require_csrf();
        if (!$canEdit) {
            json_response(['error' => 'Bạn không có quyền chỉnh sửa bài viết này.'], 403);
        }
        $input = request_json();
        $title = trim((string) ($input['title'] ?? $article['title']));
        $content = (string) ($input['content'] ?? $article['content']);

        if ($title === '' || $content === '') {
            json_response(['error' => 'Tiêu đề và nội dung không được để trống.'], 422);
        }

        $excerpt = mb_substr(strip_tags($content), 0, 150) . '...';
        $pdo->prepare('UPDATE articles SET title = :title, content = :content, excerpt = :excerpt, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute(['title' => $title, 'content' => $content, 'excerpt' => $excerpt, 'id' => $articleId]);

        json_response(['ok' => true, 'updated_at' => date('H:i:s')]);
    }

    // Cập nhật bài viết đầy đủ (Chung giao diện với Tạo bài)
    if (($method === 'PATCH' || $method === 'PUT') && $action === '') {
        require_csrf();
        if (!$canEdit) {
            json_response(['error' => 'Bạn không có quyền chỉnh sửa bài viết này.'], 403);
        }
        $input = request_json();
        $title = trim((string) ($input['title'] ?? $article['title']));
        $content = (string) ($input['content'] ?? $article['content']);
        $category = trim((string) ($input['category'] ?? $article['category']));
        $color = (string) ($input['color'] ?? $article['color']);
        $imageUrl = trim((string) ($input['image_url'] ?? $article['image_url']));

        if ($title === '' || $content === '') {
            json_response(['error' => 'Tiêu đề và nội dung là bắt buộc.'], 422);
        }

        $excerpt = mb_substr(strip_tags($content), 0, 150) . '...';
        $pdo->prepare('UPDATE articles SET title = :title, category = :cat, content = :content, excerpt = :exc,
            image_url = :img, color = :color, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([
                'title' => $title,
                'cat' => $category ?: 'Tin tức',
                'content' => $content,
                'exc' => $excerpt,
                'img' => $imageUrl,
                'color' => $color,
                'id' => $articleId,
            ]);

        // Cập nhật nhãn
        if (isset($input['label_ids']) && is_array($input['label_ids'])) {
            $pdo->prepare('DELETE FROM article_labels WHERE article_id = :aid')->execute(['aid' => $articleId]);
            $insLbl = $pdo->prepare('INSERT OR IGNORE INTO article_labels (article_id, label_id) VALUES (:aid, :lid)');
            foreach ($input['label_ids'] as $lid) {
                $insLbl->execute(['aid' => $articleId, 'lid' => (int)$lid]);
            }
        }

        json_response(['ok' => true, 'message' => 'Cập nhật bài viết thành công!']);
    }

    // Ghim hoặc Bỏ ghim bài viết (Multi-pin sắp xếp theo pinned_at)
    if ($method === 'POST' && $action === 'pin') {
        require_csrf();
        if (!$isOwner) {
            json_response(['error' => 'Chỉ tác giả mới được ghim bài viết.'], 403);
        }
        $newPinned = (int)$article['is_pinned'] === 1 ? 0 : 1;
        $pinnedAt = $newPinned === 1 ? date('Y-m-d H:i:s') : null;

        $pdo->prepare('UPDATE articles SET is_pinned = :pinned, pinned_at = :pat WHERE id = :id')
            ->execute(['pinned' => $newPinned, 'pat' => $pinnedAt, 'id' => $articleId]);

        json_response(['is_pinned' => (bool)$newPinned]);
    }

    // Bật / Tắt / Đổi mật khẩu khóa bài viết
    if ($method === 'POST' && $action === 'lock') {
        require_csrf();
        if (!$isOwner) {
            json_response(['error' => 'Chỉ tác giả mới có quyền khóa bài viết.'], 403);
        }
        $input = request_json();
        $lock = filter_var($input['locked'] ?? false, FILTER_VALIDATE_BOOL);
        $password = (string) ($input['password'] ?? '');

        if ($lock) {
            if (strlen($password) < 4) {
                json_response(['error' => 'Mật khẩu bảo vệ bài viết phải có ít nhất 4 ký tự.'], 422);
            }
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare('UPDATE articles SET is_locked = 1, lock_password_hash = :hash WHERE id = :id')
                ->execute(['hash' => $hash, 'id' => $articleId]);
        } else {
            $pdo->prepare('UPDATE articles SET is_locked = 0, lock_password_hash = NULL WHERE id = :id')
                ->execute(['id' => $articleId]);
        }

        json_response(['is_locked' => $lock]);
    }

    // Mở khóa bài viết bằng mật khẩu
    if ($method === 'POST' && $action === 'unlock') {
        require_csrf();
        $input = request_json();
        $password = (string) ($input['password'] ?? '');

        if (!password_verify($password, (string)$article['lock_password_hash'])) {
            json_response(['error' => 'Mật khẩu bài viết không chính xác.'], 403);
        }

        $_SESSION['unlocked_articles'] ??= [];
        if (!in_array($articleId, $_SESSION['unlocked_articles'], true)) {
            $_SESSION['unlocked_articles'][] = $articleId;
        }

        json_response(['ok' => true, 'content' => $article['content']]);
    }

    // Chia sẻ bài viết qua email
    if ($method === 'POST' && $action === 'share') {
        require_csrf();
        if (!$isOwner) {
            json_response(['error' => 'Chỉ tác giả mới được chia sẻ bài viết này.'], 403);
        }
        $input = request_json();
        $targetEmail = filter_var(trim((string) ($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $permission = in_array($input['permission'] ?? '', ['read', 'edit'], true) ? $input['permission'] : 'read';

        if ($targetEmail === false) {
            json_response(['error' => 'Vui lòng nhập email hợp lệ.'], 422);
        }

        // Tìm user đích
        $uStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
        $uStmt->execute(['email' => $targetEmail]);
        $targetUser = $uStmt->fetch();

        if ($targetUser === false) {
            json_response(['error' => 'Không tìm thấy người dùng có email này.'], 404);
        }
        if ((int)$targetUser['id'] === $user['id']) {
            json_response(['error' => 'Bạn không thể chia sẻ bài viết cho chính mình.'], 422);
        }

        $pdo->prepare('INSERT INTO article_shares (article_id, shared_with_user_id, permission)
            VALUES (:aid, :uid, :perm)
            ON CONFLICT(article_id, shared_with_user_id) DO UPDATE SET permission = excluded.permission')
            ->execute(['aid' => $articleId, 'uid' => $targetUser['id'], 'perm' => $permission]);

        json_response(['message' => 'Đã chia sẻ bài viết thành công!']);
    }

    // Hủy chia sẻ bài viết
    if ($method === 'DELETE' && $action === 'share') {
        require_csrf();
        if (!$isOwner) {
            json_response(['error' => 'Chỉ tác giả mới được hủy chia sẻ.'], 403);
        }
        $targetUserId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        $pdo->prepare('DELETE FROM article_shares WHERE article_id = :aid AND shared_with_user_id = :uid')
            ->execute(['aid' => $articleId, 'uid' => $targetUserId]);
        json_response(['ok' => true]);
    }

    // AI Tóm tắt bài viết (AI Summary)
    if ($method === 'POST' && $action === 'summarize') {
        require_csrf();
        $summary = ai_summarize_content($article['content']);
        json_response(['summary' => $summary]);
    }

    // Tải tệp đính kèm lên bài viết (Criteria 15)
    if ($method === 'POST' && $action === 'attachments') {
        require_csrf();
        if (!$canEdit) {
            json_response(['error' => 'Bạn không có quyền đính kèm tệp.'], 403);
        }
        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
            json_response(['error' => 'Chưa chọn file hoặc tải file bị lỗi.'], 422);
        }

        $file = $_FILES['attachment'];
        if ($file['size'] > 15 * 1024 * 1024) {
            json_response(['error' => 'Dung lượng file đính kèm không vượt quá 15MB.'], 422);
        }

        $fileName = basename($file['name']);
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $savedName = 'att_' . $articleId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = __DIR__ . '/Assets/Uploads/attachments/' . $savedName;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $filePath = '/Assets/Uploads/attachments/' . $savedName;
            $fileType = mime_content_type($dest) ?: 'application/octet-stream';

            $stmt = $pdo->prepare('INSERT INTO attachments (article_id, file_path, file_type, file_name) VALUES (:aid, :path, :type, :name)');
            $stmt->execute(['aid' => $articleId, 'path' => $filePath, 'type' => $fileType, 'name' => $fileName]);

            json_response([
                'id' => (int)$pdo->lastInsertId(),
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_type' => $fileType,
            ], 201);
        }

        json_response(['error' => 'Không thể lưu file đính kèm.'], 500);
    }

    // Xóa bài viết
    if ($method === 'DELETE' && $action === '') {
        require_csrf();
        if (!$isOwner) {
            json_response(['error' => 'Chỉ tác giả mới có quyền xóa bài viết này.'], 403);
        }
        $pdo->prepare('DELETE FROM articles WHERE id = :id')->execute(['id' => $articleId]);
        json_response(['ok' => true]);
    }

    json_response(['error' => 'Thao tác bài viết không hợp lệ.'], 405);
}

json_response(['error' => 'Không tìm thấy endpoint yêu cầu.'], 404);
