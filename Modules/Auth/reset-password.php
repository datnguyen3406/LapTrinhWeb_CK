<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$pageTitle = 'Đặt lại mật khẩu';
$token = trim((string) ($_GET['token'] ?? ''));
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <h1>Đặt lại mật khẩu</h1>
    <?php if ($token === ''): ?>
        <form data-api-form="/api/auth/reset-request">
            <p>Nhập email đã đăng ký. Nếu tài khoản tồn tại, hướng dẫn sẽ được gửi qua email.</p>
            <label>Email <input type="email" name="email" required autocomplete="email"></label>
            <button type="submit">Gửi hướng dẫn</button>
            <p data-form-message role="status"></p>
        </form>
    <?php else: ?>
        <form data-api-form="/api/auth/reset">
            <input type="hidden" name="token" value="<?= escape($token) ?>">
            <label>Mật khẩu mới <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
            <button type="submit">Cập nhật mật khẩu</button>
            <p data-form-message role="status"></p>
        </form>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
