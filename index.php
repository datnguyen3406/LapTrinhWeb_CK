<?php

declare(strict_types=1);

require_once __DIR__ . '/Includes/bootstrap.php';

$search = trim((string) ($_GET['q'] ?? ''));
$statement = $pdo->prepare('SELECT id, title, category, excerpt, image_url, published_at, is_pinned
    FROM articles WHERE title LIKE :query OR excerpt LIKE :query
    ORDER BY is_pinned DESC, id DESC');
$statement->execute(['query' => '%' . $search . '%']);
$articles = $statement->fetchAll();
$hero = array_shift($articles);
$user = current_user();
$pageTitle = 'GenK Magazine - Trang chủ';
require __DIR__ . '/Includes/header.php';
?>
<main class="container">
    <?php if ($user !== null): ?>
        <div class="account-bar">
            Xin chào, <?= escape($user['display_name']) ?>
            <a href="/Modules/Articles/create.php">Viết bài</a>
            <a href="/Modules/User/profile.php">Tài khoản</a>
            <button type="button" data-logout>Đăng xuất</button>
        </div>
    <?php else: ?>
        <div class="account-bar"><a href="/Modules/Auth/login.php">Đăng nhập</a> <a href="/Modules/Auth/register.php">Tạo tài khoản</a></div>
    <?php endif; ?>
    <form class="search-form" action="/index.php" method="get">
        <label for="article-search">Tìm bài viết</label>
        <input id="article-search" type="search" name="q" value="<?= escape($search) ?>" placeholder="Nhập từ khóa..." data-live-search>
        <button type="submit">Tìm</button>
    </form>
    <p id="search-summary" aria-live="polite"></p>
    <?php if ($hero !== false): ?>
        <a href="/Modules/Articles/detail.php?id=<?= (int) $hero['id'] ?>" class="hero" id="tin-ict" data-featured>
            <img src="<?= escape($hero['image_url']) ?>" alt="" class="hero-img">
            <div class="hero-content">
                <span class="category-tag"><?= escape($hero['category']) ?></span>
                <h1><?= escape($hero['title']) ?></h1>
                <p class="excerpt"><?= escape($hero['excerpt']) ?></p>
                <div class="meta"><?= escape($hero['published_at']) ?></div>
            </div>
        </a>
    <?php endif; ?>
    <section class="story-section" aria-labelledby="latest-news">
        <div class="section-heading">
            <h2 id="latest-news"><?= $search !== '' ? 'Kết quả tìm kiếm' : 'Tin công nghệ nổi bật' ?></h2>
            <p>Cập nhật những câu chuyện mới nhất về thiết bị, trí tuệ nhân tạo và công nghệ tương lai.</p>
        </div>
        <div class="grid-layout">
            <?php foreach ($articles as $article): ?>
                <a href="/Modules/Articles/detail.php?id=<?= (int) $article['id'] ?>" class="card">
                    <img src="<?= escape($article['image_url']) ?>" alt="" loading="lazy">
                    <div class="card-content">
                        <span class="category-tag"><?= escape($article['category']) ?></span>
                        <h2><?= escape($article['title']) ?></h2>
                        <p class="card-excerpt"><?= escape($article['excerpt']) ?></p>
                        <div class="meta"><?= escape($article['published_at']) ?><?= (bool) $article['is_pinned'] ? ' • Đã ghim' : '' ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
            <?php if ($hero === false && $articles === []): ?>
                <p>Không tìm thấy bài viết phù hợp.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php require __DIR__ . '/Includes/footer.php'; ?>
