<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$user = current_user();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($user === null) {
    header('Location: /Modules/Auth/login.php');
    exit;
}
$statement = $pdo->prepare('SELECT * FROM articles WHERE id = :id AND author_id = :author');
$statement->execute(['id' => $id ?: 0, 'author' => $user['id']]);
$article = $statement->fetch();
if ($article === false) {
    http_response_code(404);
}
$pageTitle = $article === false ? 'Không tìm thấy bài viết' : 'Chỉnh sửa bài viết';
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <?php if ($article === false): ?>
        <h1>Không tìm thấy bài viết của bạn</h1>
    <?php else: ?>
        <h1>Chỉnh sửa bài viết</h1>
        <form data-article-form data-article-id="<?= (int) $article['id'] ?>">
            <label>Tiêu đề <input name="title" required maxlength="240" value="<?= escape($article['title']) ?>"></label>
            <label>Chuyên mục <input name="category" required maxlength="80" value="<?= escape($article['category']) ?>"></label>
            <label>Tóm tắt <textarea name="excerpt" rows="3"><?= escape($article['excerpt']) ?></textarea></label>
            <label>URL ảnh <input type="url" name="image_url" value="<?= escape($article['image_url']) ?>"></label>
            <label>Nội dung <textarea name="content" rows="14" required><?= escape($article['content']) ?></textarea></label>
            <button type="submit">Lưu thay đổi</button>
            <button type="button" data-autosave>Lưu nháp nền</button>
            <p data-collaboration-status role="status">Đang chờ cộng tác viên...</p>
            <pre data-live-preview aria-live="polite"></pre>
            <p data-form-message role="status"></p>
        </form>
    <?php endif; ?>
</main>
<?php if ($article !== false): ?>
    <script>window.articleRoomId = <?= (int) $article['id'] ?>;</script>
<?php endif; ?>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
