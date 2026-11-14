<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$user = require_login_page();

$statement = $pdo->prepare('SELECT theme, font_size, note_color FROM user_preferences WHERE user_id = :id');
$statement->execute(['id' => $user['id']]);
$preferences = $statement->fetch() ?: ['theme' => 'light', 'font_size' => 16, 'note_color' => '#ffffff'];

$pageTitle = 'Tùy chọn giao diện - GenK Magazine';
require __DIR__ . '/../../Includes/header.php';
?>
<div class="container my-5" style="max-width: 600px;">
    <div class="card shadow-sm border-0">
        <div class="card-body p-4 p-md-5">
            <h3 class="font-weight-bold mb-2">Tùy chọn giao diện</h3>
            <p class="text-muted small mb-4">Cấu hình chế độ Sáng/Tối, cỡ chữ bài viết và màu sắc thẻ hiển thị.</p>

            <form data-api-form="/api/user/preferences" data-method="PUT">
                <div class="form-group mb-3">
                    <label class="font-weight-500 small">Chế độ giao diện</label>
                    <select name="theme" class="custom-select">
                        <option value="light" <?= $preferences['theme'] === 'light' ? 'selected' : '' ?>>Chế độ Sáng (Light Mode)</option>
                        <option value="dark" <?= $preferences['theme'] === 'dark' ? 'selected' : '' ?>>Chế độ Tối (Dark Mode)</option>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="font-weight-500 small">Cỡ chữ bài viết (12px – 24px)</label>
                    <input type="number" name="font_size" class="form-control" min="12" max="24" value="<?= (int) $preferences['font_size'] ?>">
                </div>

                <div class="form-group mb-4">
                    <label class="font-weight-500 small">Màu thẻ bài viết mặc định</label>
                    <input type="color" name="note_color" class="form-control p-1" style="height: 42px;" value="<?= escape($preferences['note_color']) ?>">
                </div>

                <button type="submit" class="btn btn-danger btn-block font-weight-bold py-2 shadow-sm">Lưu tùy chọn</button>
                <p data-form-message role="status" class="mt-3 text-center mb-0"></p>
            </form>

            <hr class="my-4">
            <div class="text-center small">
                <a href="/Modules/User/profile.php" class="text-muted mr-3">👤 Hồ sơ tác giả</a>
                <a href="/index.php" class="text-danger font-weight-bold">&larr; Về trang chủ</a>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
