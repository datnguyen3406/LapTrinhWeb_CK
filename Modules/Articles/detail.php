<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';

$articleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($articleId === false || $articleId === null || $articleId < 1) {
    http_response_code(404);
    $article = null;
} else {
    $statement = $pdo->prepare('SELECT id, author_id, title, category, excerpt, content, image_url, published_at, is_pinned, is_locked FROM articles WHERE id = :id');
    $statement->execute(['id' => $articleId]);
    $article = $statement->fetch();

    if ($article === false) {
        http_response_code(404);
        $article = null;
    }
}

$pageTitle = $article['title'] ?? 'Không tìm thấy bài viết';
$user = current_user();
$isOwner = $article !== null && $user !== null && (int) $article['author_id'] === (int) $user['id'];
$isUnlocked = $article !== null && (!(bool) $article['is_locked'] || $isOwner || in_array((int) $article['id'], $_SESSION['unlocked_articles'] ?? [], true));
require __DIR__ . '/../../Includes/header.php';
?>
<main class="article-container">
    <a href="/index.php" class="back-btn">&larr; Quay lại Trang chủ</a>
    <?php if ($article === null): ?>
        <div class="article-header">
            <h1>Không tìm thấy bài viết</h1>
            <p>Bài viết không tồn tại hoặc đường dẫn không hợp lệ.</p>
        </div>
    <?php else: ?>
        <div class="article-header">
            <span class="category-tag"><?= htmlspecialchars($article['category'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span>
            <h1><?= htmlspecialchars($article['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></h1>
            <div class="article-meta">Xuất bản: <?= htmlspecialchars($article['published_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
        </div>
        <?php if ($user !== null && (int) $article['author_id'] === (int) $user['id']): ?>
            <div class="article-actions">
                <a class="back-btn" href="/Modules/Articles/edit.php?id=<?= (int) $article['id'] ?>">Sửa bài viết</a>
                <button type="button" data-pin-article="<?= (int) $article['id'] ?>"><?= (bool) $article['is_pinned'] ? 'Bỏ ghim' : 'Ghim bài viết' ?></button>
                <input type="password" data-lock-password placeholder="Mật khẩu khóa" minlength="8" <?= (bool) $article['is_locked'] ? 'hidden' : '' ?>>
                <button type="button" data-lock-article="<?= (int) $article['id'] ?>" data-locked="<?= (int) $article['is_locked'] ?>"><?= (bool) $article['is_locked'] ? 'Mở khóa bài viết' : 'Khóa bằng mật khẩu' ?></button>
                <a href="/Modules/Articles/action_notes.php">Quản lý bài viết</a>
            </div>
        <?php endif; ?>
        <div class="article-content">
            <?php if ($isUnlocked): ?>
                <img src="<?= htmlspecialchars($article['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="">
                <p><?= nl2br(htmlspecialchars($article['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></p>
            <?php else: ?>
                <form data-unlock-article="<?= (int) $article['id'] ?>">
                    <p>Bài viết được bảo vệ bằng mật khẩu.</p>
                    <label>Mật khẩu <input type="password" name="password" required autocomplete="current-password"></label>
                    <button type="submit">Mở bài viết</button>
                    <p data-form-message role="status"></p>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
    <?php if ($isOwner): ?>
        <script>window.articleRoomId = <?= (int) $article['id'] ?>;</script>
    <?php endif; ?>
    <?php require __DIR__ . '/../../Includes/footer.php'; ?>
