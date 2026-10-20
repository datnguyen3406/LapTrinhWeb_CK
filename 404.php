<?php

declare(strict_types=1);

http_response_code(404);
require_once __DIR__ . '/Includes/bootstrap.php';
$pageTitle = 'Không tìm thấy trang';
require __DIR__ . '/Includes/header.php';
?>
<main class="form-container">
    <h1>Không tìm thấy trang</h1>
    <p>Địa chỉ bạn truy cập không tồn tại.</p>
    <a href="/index.php">Về trang chủ</a>
</main>
<?php require __DIR__ . '/Includes/footer.php'; ?>
