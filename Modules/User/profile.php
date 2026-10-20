<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$user = current_user();
if ($user === null) {
    header('Location: /Modules/Auth/login.php');
    exit;
}
$pageTitle = 'Hồ sơ cá nhân';
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <h1>Hồ sơ cá nhân</h1>
    <form data-api-form="/api/user/profile" data-method="PATCH">
        <label>Email <input value="<?= escape($user['email']) ?>" disabled></label>
        <label>Tên hiển thị <input name="display_name" required minlength="2" maxlength="80" value="<?= escape($user['display_name']) ?>"></label>
        <label>URL ảnh đại diện <input name="avatar_url" type="url" value="<?= escape($user['avatar_url']) ?>"></label>
        <button type="submit">Cập nhật hồ sơ</button>
        <p data-form-message role="status"></p>
    </form>
    <p><a href="/Modules/User/preferences.php">Tùy chọn giao diện</a> · <a href="/Modules/Articles/action_notes.php">Bài viết của tôi</a></p>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
