<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$message = '';
$isSuccess = false;

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT id, display_name FROM users WHERE activation_token = :token');
    $stmt->execute(['token' => $token]);
    $user = $stmt->fetch();

    if ($user !== false) {
        $pdo->prepare('UPDATE users SET is_active = 1, activation_token = NULL WHERE id = :id')
            ->execute(['id' => $user['id']]);
        $isSuccess = true;
        $message = 'Tài khoản của bạn đã được kích hoạt thành công!';
    } else {
        $message = 'Mã kích hoạt không hợp lệ hoặc tài khoản đã được kích hoạt trước đó.';
    }
} else {
    $message = 'Không tìm thấy mã kích hoạt trong đường dẫn.';
}

$pageTitle = 'Kích hoạt tài khoản';
require __DIR__ . '/../../Includes/header.php';
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 text-center p-4">
                <div class="card-body">
                    <h3 class="font-weight-bold mb-3"><?= $isSuccess ? '🎉 Kích hoạt thành công' : '⚠️ Thông báo kích hoạt' ?></h3>
                    <div class="alert <?= $isSuccess ? 'alert-success' : 'alert-danger' ?> py-3 px-4 mb-4">
                        <?= escape($message) ?>
                    </div>
                    <a href="/index.php" class="btn btn-danger font-weight-bold px-4">&larr; Về trang chủ bài viết</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
