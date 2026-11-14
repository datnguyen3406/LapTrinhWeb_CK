<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';

$pageTitle = 'Đăng ký tài khoản tác giả - GenK Magazine';
require __DIR__ . '/../../Includes/header.php';
?>
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <h2 class="font-weight-bold text-center mb-1 text-danger">Tạo tài khoản tác giả</h2>
                    <p class="text-center text-muted small mb-4">Đăng ký tài khoản để xuất bản, lưu trữ và chia sẻ bài viết.</p>

                    <form data-api-form="/api/auth/register" id="register-form">
                        <div class="form-group mb-3">
                            <label class="font-weight-500 small">Tên hiển thị (Display Name)</label>
                            <input type="text" name="display_name" class="form-control" required minlength="2" maxlength="80" placeholder="Ví dụ: Nguyễn Văn A">
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-500 small">Địa chỉ Email</label>
                            <input type="email" name="email" class="form-control" required autocomplete="email" placeholder="name@example.com">
                        </div>
                        <!-- Bắt buộc nhập mật khẩu 2 lần theo đề bài (Bôi đỏ) -->
                        <div class="form-group mb-3">
                            <label class="font-weight-500 small">Mật khẩu</label>
                            <input type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Tối thiểu 6 ký tự">
                        </div>
                        <div class="form-group mb-3">
                            <label class="font-weight-500 small">Xác nhận lại mật khẩu</label>
                            <input type="password" name="password_confirmation" class="form-control" required minlength="6" autocomplete="new-password" placeholder="Nhập lại mật khẩu giống ở trên">
                        </div>
                        <button type="submit" class="btn btn-danger btn-block font-weight-bold py-2 mt-4 shadow-sm" id="btn-submit-register">Đăng ký tài khoản</button>
                        <p data-form-message role="status" class="mt-3 text-center mb-0"></p>
                    </form>

                    <hr class="my-4">
                    <div class="text-center small">
                        <span>Đã có tài khoản? </span>
                        <a href="/Modules/Auth/login.php" class="text-danger font-weight-bold">Đăng nhập tại đây</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
