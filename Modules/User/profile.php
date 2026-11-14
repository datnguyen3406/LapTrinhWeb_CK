<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$user = require_login_page();
$pageTitle = 'Hồ sơ tác giả - GenK Magazine';
require __DIR__ . '/../../Includes/header.php';
?>
<div class="container my-5" style="max-width: 800px;">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-4">
            <div class="d-flex align-items-center mb-4 pb-3 border-bottom flex-wrap">
                <img src="<?= escape($user['avatar_url'] ?: '/Assets/Images/app-icon.svg') ?>" alt="Avatar" class="rounded-circle mr-3 border border-danger p-1" style="width: 80px; height: 80px; object-fit: cover;">
                <div>
                    <h3 class="font-weight-bold mb-1"><?= escape($user['display_name']) ?></h3>
                    <p class="text-muted small mb-1"><?= escape($user['email']) ?></p>
                    <span class="badge <?= (int)$user['is_active'] === 1 ? 'badge-success' : 'badge-warning' ?> px-2 py-1">
                        <?= (int)$user['is_active'] === 1 ? '✓ Đã kích hoạt' : '⚠️ Chưa kích hoạt' ?>
                    </span>
                </div>
            </div>

            <h5 class="font-weight-bold mb-3">Thông tin cá nhân & Ảnh đại diện</h5>
            <form id="profile-form" enctype="multipart/form-data">
                <div class="form-group mb-3">
                    <label class="small font-weight-500">Tên hiển thị</label>
                    <input type="text" name="display_name" class="form-control" required minlength="2" maxlength="80" value="<?= escape($user['display_name']) ?>">
                </div>
                <div class="form-group mb-3">
                    <label class="small font-weight-500">Tải lên ảnh đại diện mới (JPG, PNG, WebP tối đa 3MB)</label>
                    <div class="custom-file">
                        <input type="file" name="avatar_file" class="custom-file-input" id="avatarFileInput" accept="image/*">
                        <label class="custom-file-label" for="avatarFileInput">Chọn ảnh từ máy tính...</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-danger btn-sm font-weight-bold px-3">Cập nhật hồ sơ</button>
                <p data-profile-message role="status" class="mt-2 mb-0"></p>
            </form>

            <hr class="my-4">

            <h5 class="font-weight-bold mb-3">Đổi mật khẩu</h5>
            <form data-api-form="/api/user/change-password">
                <div class="form-group mb-3">
                    <label class="small font-weight-500">Mật khẩu hiện tại</label>
                    <input type="password" name="current_password" class="form-control" required minlength="6" placeholder="Mật khẩu đang sử dụng">
                </div>
                <div class="form-row">
                    <div class="col-md-6 form-group mb-3">
                        <label class="small font-weight-500">Mật khẩu mới</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6" placeholder="Tối thiểu 6 ký tự">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label class="small font-weight-500">Nhập lại mật khẩu mới</label>
                        <input type="password" name="new_password_confirmation" class="form-control" required minlength="6" placeholder="Xác nhận mật khẩu mới">
                    </div>
                </div>
                <button type="submit" class="btn btn-outline-danger btn-sm font-weight-bold px-3">Đổi mật khẩu</button>
                <p data-form-message role="status" class="mt-2 mb-0"></p>
            </form>
        </div>
    </div>
    
    <div class="text-center">
        <a href="/Modules/User/preferences.php" class="btn btn-outline-secondary btn-sm mr-2">⚙️ Tùy chọn giao diện</a>
        <a href="/index.php" class="btn btn-secondary btn-sm">&larr; Về bảng tin bài viết</a>
    </div>
</div>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
