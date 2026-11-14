<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$user = require_login_page();

$articleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($articleId === false || $articleId === null || $articleId < 1) {
    http_response_code(404);
    $article = null;
} else {
    $stmt = $pdo->prepare('SELECT a.*, u.display_name AS author_name, u.avatar_url AS author_avatar
                           FROM articles a LEFT JOIN users u ON a.author_id = u.id WHERE a.id = :id');
    $stmt->execute(['id' => $articleId]);
    $article = $stmt->fetch();
}

$pageTitle = $article['title'] ?? 'Chi tiết bài viết';

$isOwner = $article !== null && (int)$article['author_id'] === $user['id'];
$isUnlocked = $article !== null && (!(bool)$article['is_locked'] || $isOwner || in_array((int)$article['id'], $_SESSION['unlocked_articles'] ?? [], true));

$attachments = [];
if ($article !== null) {
    $attStmt = $pdo->prepare('SELECT * FROM attachments WHERE article_id = :aid');
    $attStmt->execute(['aid' => $article['id']]);
    $attachments = $attStmt->fetchAll();
}

require __DIR__ . '/../../Includes/header.php';
?>
<div class="container my-5" style="max-width: 860px;">
    <a href="/index.php" class="btn btn-outline-danger btn-sm mb-4 rounded-pill font-weight-500 px-3">&larr; Quay lại Bảng tin</a>

    <?php if ($article === null): ?>
        <div class="card shadow-sm border-0 p-5 text-center">
            <h2 class="font-weight-bold text-muted mb-2">404 - Không tìm thấy bài viết</h2>
            <p class="text-muted">Bài viết này không tồn tại hoặc đã bị xóa khỏi hệ thống.</p>
        </div>
    <?php elseif (!$isUnlocked): ?>
        <div class="card shadow-sm border-0 p-4 p-md-5 text-center">
            <h3 class="font-weight-bold mb-3">🔒 Bài viết được bảo vệ bằng mật khẩu</h3>
            <p class="text-muted mb-4">Vui lòng nhập mật khẩu bài viết để mở khóa nội dung:</p>
            <form data-unlock-article="<?= (int)$article['id'] ?>" class="mx-auto" style="max-width: 360px;">
                <div class="form-group mb-3">
                    <input type="password" name="password" class="form-control" required placeholder="Nhập mật khẩu bài viết">
                </div>
                <button type="submit" class="btn btn-danger btn-block font-weight-bold">Mở khóa bài viết</button>
                <p data-form-message role="status" class="mt-2 mb-0"></p>
            </form>
        </div>
    <?php else: ?>
        <article class="card shadow-sm border-0 overflow-hidden" style="background-color: <?= escape($article['color'] ?? '#ffffff') ?>;">
            <?php if (!empty($article['image_url'])): ?>
                <img src="<?= escape($article['image_url']) ?>" alt="Thumbnail" class="card-img-top" style="max-height: 420px; object-fit: cover;">
            <?php endif; ?>

            <div class="card-body p-4 p-md-5">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="badge badge-danger text-uppercase font-weight-bold px-3 py-1"><?= escape($article['category']) ?></span>
                    <span class="small text-muted"><?= escape($article['updated_at']) ?></span>
                </div>

                <h1 class="font-weight-bold mb-3" style="font-size: 32px; line-height: 1.3;"><?= escape($article['title']) ?></h1>

                <div class="d-flex align-items-center text-muted small pb-3 mb-4 border-bottom">
                    <span>Tác giả: <strong class="text-dark"><?= escape($article['author_name'] ?? 'Ban biên tập') ?></strong></span>
                    <?php if ((bool)$article['is_pinned']): ?>
                        <span class="ml-3 badge badge-warning">📌 Đã ghim</span>
                    <?php endif; ?>
                    <?php if ((bool)$article['is_locked']): ?>
                        <span class="ml-2 badge badge-danger">🔒 Có mật khẩu</span>
                    <?php endif; ?>
                </div>

                <!-- Các nút hành động trên bài viết -->
                <div class="mb-4 d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-detail-ai-summary" data-article-id="<?= (int)$article['id'] ?>">✨ Tóm tắt bài viết bằng AI</button>
                    <?php if ($isOwner): ?>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.location.assign('/index.php?edit_id=<?= (int)$article['id'] ?>')">✏️ Chỉnh sửa bài viết</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-delete-article="<?= (int)$article['id'] ?>">🗑️ Xóa bài viết</button>
                    <?php endif; ?>
                </div>

                <div id="detail-ai-summary-box" class="ai-box mb-4" style="display: none;"></div>

                <div class="article-content" style="font-size: 18px; line-height: 1.8; white-space: pre-line;">
                    <?= escape($article['content']) ?>
                </div>

                <?php if (!empty($attachments)): ?>
                    <hr class="my-5">
                    <h5 class="font-weight-bold mb-3">Tệp tin đính kèm</h5>
                    <div class="list-group list-group-flush">
                        <?php foreach ($attachments as $att): ?>
                            <a href="<?= escape($att['file_path']) ?>" target="_blank" download class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
                                <span>📎 <?= escape($att['file_name']) ?></span>
                                <span class="badge badge-secondary"><?= escape($att['file_type']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
