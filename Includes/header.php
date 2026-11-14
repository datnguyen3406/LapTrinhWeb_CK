<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'GenK Magazine - Tạp chí Công nghệ';
$activeCategory = trim((string) ($_GET['category'] ?? ''));
$headerPreferences = null;
$user = function_exists('current_user') ? current_user() : null;

if ($user !== null) {
    header('Cache-Control: private, no-store');
    $prefStmt = $pdo->prepare('SELECT theme, font_size, note_color FROM user_preferences WHERE user_id = :id');
    $prefStmt->execute(['id' => $user['id']]);
    $headerPreferences = $prefStmt->fetch();
}

$theme = $headerPreferences['theme'] ?? 'light';
$fontSize = $headerPreferences['font_size'] ?? 16;
$noteColor = $headerPreferences['note_color'] ?? '#ffffff';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 4.6 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <!-- Tinh gọn Custom CSS -->
    <link rel="stylesheet" href="/Assets/CSS/style.css">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#d71a21">
    <link rel="icon" href="/Assets/Images/app-icon.svg" type="image/svg+xml">
    <meta name="csrf-token" content="<?= escape($_SESSION['csrf_token'] ?? '') ?>">
</head>
<body data-theme="<?= escape($theme) ?>" style="--user-font-size: <?= (int)$fontSize ?>px; --note-color: <?= escape($noteColor) ?>;">

<?php if ($user !== null && (int)$user['is_active'] === 0): ?>
    <!-- Banner cảnh báo tài khoản chưa kích hoạt theo đề bài (Bôi đỏ) -->
    <div class="alert alert-warning mb-0 border-0 rounded-0 py-2 px-3 d-flex justify-content-between align-items-center flex-wrap" role="alert">
        <span>⚠️ <strong>Tài khoản chưa được kích hoạt:</strong> Vui lòng kiểm tra email để hoàn tất kích hoạt!</span>
        <a href="/Modules/Auth/activate.php?token=<?= urlencode((string)$user['activation_token']) ?>" class="btn btn-sm btn-dark font-weight-bold ml-2">⚡ Kích hoạt ngay (Demo)</a>
    </div>
<?php endif; ?>

<!-- Header Navigation Bootstrap 4 -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm py-2">
    <div class="container-fluid max-w-1240">
        <a class="navbar-brand font-weight-bold text-danger text-uppercase letter-spacing-1" href="/index.php">
            <span style="color: var(--genk-red); font-size: 22px;">GenK Magazine</span>
        </a>
        <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ml-auto align-items-lg-center">
                <?php if ($user !== null): ?>
                    <li class="nav-item">
                        <a class="nav-link font-weight-500 text-dark px-3" href="/index.php">📰 Bài viết</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-500 text-dark px-3" href="/index.php?tab=shared">👥 Được chia sẻ</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-500 text-dark px-3" href="/Modules/User/profile.php">👤 <?= escape($user['display_name']) ?></a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link font-weight-500 text-dark px-3" href="/Modules/User/preferences.php">⚙️ Tùy chọn</a>
                    </li>
                    <li class="nav-item ml-lg-2">
                        <button type="button" class="btn btn-outline-danger btn-sm px-3" data-logout>Đăng xuất</button>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link font-weight-500 px-3" href="/Modules/Auth/login.php">Đăng nhập</a>
                    </li>
                    <li class="nav-item ml-lg-2">
                        <a class="btn btn-danger btn-sm text-white px-3" href="/Modules/Auth/register.php">Đăng ký</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
