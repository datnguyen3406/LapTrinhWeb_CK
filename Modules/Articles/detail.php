<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/db.php';

$articleId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($articleId === false || $articleId === null || $articleId < 1) {
    http_response_code(404);
    $article = null;
} else {
    $statement = $pdo->prepare('SELECT title, category, content, image_url, published_at FROM articles WHERE id = :id');
    $statement->execute(['id' => $articleId]);
    $article = $statement->fetch();

    if ($article === false) {
        http_response_code(404);
        $article = null;
    }
}

$pageTitle = $article['title'] ?? 'Không tìm thấy bài viết';
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
        <div class="article-content">
            <img src="<?= htmlspecialchars($article['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" alt="">
            <p><?= nl2br(htmlspecialchars($article['content'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></p>
        </div>
    <?php endif; ?>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
