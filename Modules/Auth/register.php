<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$pageTitle = 'Tạo tài khoản';
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <h1>Tạo tài khoản</h1>
    <form data-api-form="/api/auth/register">
        <label>Tên hiển thị <input name="display_name" required minlength="2" maxlength="80" autocomplete="name"></label>
        <label>Email <input type="email" name="email" required autocomplete="email"></label>
        <label>Mật khẩu (ít nhất 10 ký tự) <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
        <button type="submit">Đăng ký</button>
        <p data-form-message role="status"></p>
    </form>
    <p><a href="/Modules/Auth/login.php">Đã có tài khoản? Đăng nhập</a></p>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
