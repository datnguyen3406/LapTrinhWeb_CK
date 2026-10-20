<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
if (current_user() === null) {
    header('Location: /Modules/Auth/login.php');
    exit;
}
$pageTitle = 'Bài viết của tôi';
$user = current_user();
$statement = $pdo->prepare('SELECT id, title, category, is_pinned, is_locked, updated_at FROM articles WHERE author_id = :id ORDER BY updated_at DESC');
$statement->execute(['id' => $user['id']]);
$articles = $statement->fetchAll();
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <h1>Bài viết của tôi</h1>
    <p><a href="/Modules/Articles/create.php">+ Viết bài mới</a></p>
    <ul class="article-list">
        <?php foreach ($articles as $article): ?>
            <li>
                <a href="/Modules/Articles/detail.php?id=<?= (int) $article['id'] ?>"><?= escape($article['title']) ?></a>
                <span><?= escape($article['category']) ?></span>
                <a href="/Modules/Articles/edit.php?id=<?= (int) $article['id'] ?>">Sửa</a>
                <button type="button" data-pin-article="<?= (int) $article['id'] ?>"><?= (bool) $article['is_pinned'] ? 'Bỏ ghim' : 'Ghim' ?></button>
                <input type="password" data-lock-password placeholder="Mật khẩu khóa" minlength="8" <?= (bool) $article['is_locked'] ? 'hidden' : '' ?>>
                <button type="button" data-lock-article="<?= (int) $article['id'] ?>" data-locked="<?= (int) $article['is_locked'] ?>"><?= (bool) $article['is_locked'] ? 'Mở khóa' : 'Khóa' ?></button>
                <button type="button" data-delete-article="<?= (int) $article['id'] ?>">Xóa</button>
            </li>
        <?php endforeach; ?>
    </ul>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
