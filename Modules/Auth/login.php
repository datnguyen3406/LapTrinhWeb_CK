<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$pageTitle = 'Đăng nhập';
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <h1>Đăng nhập</h1>
    <form data-api-form="/api/auth/login">
        <label>Email <input type="email" name="email" required autocomplete="email"></label>
        <label>Mật khẩu <input type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit">Đăng nhập</button>
        <p data-form-message role="status"></p>
    </form>
    <p><a href="/Modules/Auth/register.php">Tạo tài khoản</a> · <a href="/Modules/Auth/reset-password.php">Quên mật khẩu?</a></p>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
