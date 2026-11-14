<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$pageTitle = 'Khôi phục mật khẩu - GenK Magazine';
require __DIR__ . '/../../Includes/header.php';
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="font-weight-bold text-center mb-1 text-danger">Khôi phục mật khẩu</h2>
                    
                    <?php if ($token === ''): ?>
                        <p class="text-center text-muted small mb-4">Nhập địa chỉ email để nhận liên kết đặt lại mật khẩu.</p>
                        <form data-api-form="/api/auth/reset-request">
                            <div class="form-group mb-3">
                                <label class="font-weight-500 small">Địa chỉ Email</label>
                                <input type="email" name="email" class="form-control" required autocomplete="email" placeholder="name@example.com">
                            </div>
                            <button type="submit" class="btn btn-danger btn-block font-weight-bold py-2 mt-4 shadow-sm">Gửi yêu cầu khôi phục</button>
                            <p data-form-message role="status" class="mt-3 text-center mb-0"></p>
                        </form>
                    <?php else: ?>
                        <p class="text-center text-muted small mb-4">Nhập mật khẩu mới cho tài khoản của bạn.</p>
                        <form data-api-form="/api/auth/reset">
                            <input type="hidden" name="token" value="<?= escape($token) ?>">
                            <div class="form-group mb-3">
                                <label class="font-weight-500 small">Mật khẩu mới</label>
                                <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Tối thiểu 6 ký tự">
                            </div>
                            <div class="form-group mb-3">
                                <label class="font-weight-500 small">Xác nhận mật khẩu mới</label>
                                <input type="password" name="password_confirmation" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Nhập lại mật khẩu mới">
                            </div>
                            <button type="submit" class="btn btn-danger btn-block font-weight-bold py-2 mt-4 shadow-sm">Lưu mật khẩu mới</button>
                            <p data-form-message role="status" class="mt-3 text-center mb-0"></p>
                        </form>
                    <?php endif; ?>

                    <hr class="my-4">
                    <div class="text-center small">
                        <a href="/Modules/Auth/login.php" class="text-danger font-weight-bold">&larr; Quay lại Đăng nhập</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
