<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
if (current_user() === null) {
    header('Location: /Modules/Auth/login.php');
    exit;
}
$pageTitle = 'Viết bài';
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <h1>Viết bài mới</h1>
    <form data-article-form>
        <label>Tiêu đề <input name="title" required maxlength="240"></label>
        <label>Chuyên mục <input name="category" required maxlength="80"></label>
        <label>Tóm tắt <textarea name="excerpt" rows="3"></textarea></label>
        <label>URL ảnh <input type="url" name="image_url" placeholder="https://..."></label>
        <label>Nội dung <textarea name="content" rows="14" required></textarea></label>
        <button type="submit">Xuất bản bài viết</button>
        <p data-form-message role="status"></p>
    </form>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
