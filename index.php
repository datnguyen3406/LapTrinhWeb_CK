<?php

declare(strict_types=1);

require_once __DIR__ . '/Includes/bootstrap.php';

// Quy định bôi đỏ: Bắt buộc chuyển hướng đến trang đăng nhập nếu chưa đăng nhập
$user = require_login_page();

$search = trim((string) ($_GET['q'] ?? ''));
$tab = trim((string) ($_GET['tab'] ?? 'my'));
$activeCategory = trim((string) ($_GET['category'] ?? ''));
$labelId = filter_input(INPUT_GET, 'label_id', FILTER_VALIDATE_INT);

// Lấy danh sách nhãn của người dùng
$labelStmt = $pdo->prepare('SELECT id, name FROM labels WHERE user_id = :uid ORDER BY name ASC');
$labelStmt->execute(['uid' => $user['id']]);
$userLabels = $labelStmt->fetchAll();

$pageTitle = 'GenK Magazine - Bảng tin bài viết & Quản lý nội dung';
require __DIR__ . '/Includes/header.php';
?>
<!-- Toolbar điều khiển: Tìm kiếm, Bộ lọc và Chế độ xem bằng Bootstrap 4 -->
<div class="container-fluid max-w-1240 my-3">
    <div class="bg-white p-3 rounded shadow-sm border d-flex flex-wrap justify-content-between align-items-center gap-2">
        <!-- Live search quét Title & Content (Debounce 300ms) -->
        <div class="flex-grow-1 mr-lg-3 my-1" style="min-width: 260px;">
            <input type="search" id="article-search" class="form-control rounded-pill" placeholder="🔍 Tìm bài viết (Live search cả tiêu đề và nội dung)..." data-live-search autocomplete="off">
        </div>

        <div class="d-flex align-items-center flex-wrap my-1">
            <!-- Nút chuyển đổi Grid View và List View (Mặc định Grid View theo đề bài) -->
            <div class="btn-group mr-2" role="group">
                <button type="button" class="btn btn-outline-danger btn-sm active" id="btn-grid-view" title="Hiển thị dạng lưới">⊞ Lưới</button>
                <button type="button" class="btn btn-outline-danger btn-sm" id="btn-list-view" title="Hiển thị dạng danh sách">☰ Danh sách</button>
            </div>

            <!-- Dropdown sắp xếp bài viết -->
            <div class="mr-2">
                <select id="sort-select" class="custom-select custom-select-sm">
                    <option value="pinned">Ghim & Mới nhất</option>
                    <option value="updated">Mới cập nhật</option>
                    <option value="newest">Ngày tạo mới nhất</option>
                </select>
            </div>

            <!-- Nút mở Trợ lý AI Hỏi & Đáp -->
            <button type="button" class="btn btn-primary btn-sm shadow-sm" id="open-ai-qa-btn">✨ Trợ lý AI Hỏi Đáp</button>
        </div>
    </div>
</div>

<!-- Layout chính: Sidebar Phân mục/Nhãn & Khu vực Bài viết -->
<div class="container-fluid max-w-1240 mb-5">
    <div class="row">
        <!-- Sidebar quản lý nhãn và chuyên mục -->
        <aside class="col-lg-3 col-md-4 mb-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white font-weight-bold py-3 border-bottom text-uppercase text-muted" style="font-size: 13px;">
                    Phân mục
                </div>
                <div class="list-group list-group-flush">
                    <a href="/index.php" class="list-group-item list-group-item-action <?= $tab !== 'shared' && $activeCategory === '' && !$labelId ? 'active text-white' : '' ?>">
                        📰 Tất cả bài viết
                    </a>
                    <a href="/index.php?tab=shared" class="list-group-item list-group-item-action <?= $tab === 'shared' ? 'active text-white' : '' ?>">
                        👥 Được chia sẻ với tôi
                    </a>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                    <span class="font-weight-bold text-uppercase text-muted" style="font-size: 13px;">Nhãn bài viết</span>
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2 font-weight-bold" id="btn-add-label">+ Thêm</button>
                </div>
                <div class="list-group list-group-flush" id="label-list-ui">
                    <?php if (empty($userLabels)): ?>
                        <div class="p-3 text-muted small">Chưa có nhãn nào. Bấm "+ Thêm" để tạo nhãn mới.</div>
                    <?php else: ?>
                        <?php foreach ($userLabels as $lbl): ?>
                            <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 <?= $labelId === (int)$lbl['id'] ? 'bg-light font-weight-bold' : '' ?>">
                                <a href="/index.php?label_id=<?= (int)$lbl['id'] ?>" class="text-dark text-decoration-none flex-grow-1">
                                    🏷️ <?= escape($lbl['name']) ?>
                                </a>
                                <div>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-muted mr-1" data-edit-label="<?= (int)$lbl['id'] ?>" data-name="<?= escape($lbl['name']) ?>" title="Đổi tên">✏️</button>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-danger" data-delete-label="<?= (int)$lbl['id'] ?>" title="Xóa nhãn">🗑️</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <!-- Khu vực nội dung bài viết -->
        <main class="col-lg-9 col-md-8">
            <!-- Khung Soạn thảo Nhanh (Chung giao diện Editor với Sửa) -->
            <div class="card shadow-sm border-0 mb-4 bg-white" id="quick-composer" style="cursor: pointer;">
                <div class="card-body py-3 d-flex justify-content-between align-items-center" id="composer-collapsed">
                    <span class="text-muted">✍️ Đăng bài viết mới (Chỉ cần Tiêu đề & Nội dung)...</span>
                    <button type="button" class="btn btn-danger btn-sm font-weight-500">+ Viết bài</button>
                </div>
            </div>

            <!-- Tiêu đề danh sách & Tổng kết tìm kiếm -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="font-weight-bold mb-0" id="list-title">
                    <?= $tab === 'shared' ? '👥 Bài viết được chia sẻ với bạn' : '📰 Bài viết của bạn' ?>
                </h4>
                <span id="search-summary" class="badge badge-secondary py-2 px-3"></span>
            </div>

            <!-- Danh sách Bài viết (Grid View vs List View) -->
            <div class="row" id="articles-list">
                <div class="col-12 text-center py-5 text-muted">Đang tải danh sách bài viết...</div>
            </div>
        </main>
    </div>
</div>

<!-- MODAL CHUNG CHO TẠO MỚI VÀ CHỈNH SỬA BÀI VIẾT (BOOTSTRAP 4 MODAL) -->
<div class="modal fade" id="editor-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold" id="editor-modal-title">Viết bài mới</h5>
                <div class="d-flex align-items-center ml-auto mr-3">
                    <span id="autosave-status" class="badge badge-success py-1 px-2 font-weight-normal" aria-live="polite"></span>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="unified-article-form">
                <div class="modal-body p-4">
                    <input type="hidden" id="editor-article-id" name="id" value="">
                    
                    <!-- 2 trường bắt buộc theo đề bài: Title & Content -->
                    <div class="form-group mb-3">
                        <input type="text" id="editor-title" name="title" class="form-control form-control-lg font-weight-bold" placeholder="Tiêu đề bài viết..." required maxlength="240" autocomplete="off">
                    </div>

                    <div class="form-group mb-3">
                        <textarea id="editor-content" name="content" class="form-control" rows="8" placeholder="Nhập nội dung bài viết tại đây (Tự động lưu ngầm khi gõ)..." required></textarea>
                    </div>

                    <!-- Các trường tùy chọn bổ sung -->
                    <div class="form-row mb-3">
                        <div class="col-md-5 mb-2">
                            <label class="small text-muted font-weight-bold">Chuyên mục</label>
                            <input type="text" id="editor-category" name="category" class="form-control form-control-sm" placeholder="AI, Mobile, Tin ICT..." value="Tin tức">
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="small text-muted font-weight-bold">URL Hình ảnh</label>
                            <input type="url" id="editor-image-url" name="image_url" class="form-control form-control-sm" placeholder="https://...">
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="small text-muted font-weight-bold">Màu thẻ</label>
                            <input type="color" id="editor-color" name="color" class="form-control form-control-sm p-1" value="#ffffff">
                        </div>
                    </div>

                    <!-- Gán nhãn cho bài viết -->
                    <div class="form-group mb-3">
                        <label class="small text-muted font-weight-bold d-block">Gắn nhãn (Labels)</label>
                        <div class="p-2 border rounded bg-light d-flex flex-wrap" id="editor-label-selector">
                            <!-- Checkbox nhãn render động -->
                        </div>
                    </div>

                    <!-- Tệp đính kèm -->
                    <div class="form-group mb-0">
                        <label class="small text-muted font-weight-bold d-block">Tệp đính kèm (Ảnh, Video, Tài liệu)</label>
                        <div id="attachments-preview-list" class="mb-2"></div>
                        <div class="input-group input-group-sm">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="attachment-file-input">
                                <label class="custom-file-label" for="attachment-file-input">Chọn tệp...</label>
                            </div>
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-secondary" id="upload-attachment-btn">📎 Tải lên</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-danger btn-sm px-4" id="save-article-btn">Hoàn thành & Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL KHÓA MẬT KHẨU BÀI VIẾT -->
<div class="modal fade" id="lock-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
        <div class="modal-content shadow border-0">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">🔒 Khóa bài viết</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="lock-form">
                <div class="modal-body">
                    <input type="hidden" id="lock-article-id">
                    <p class="small text-muted mb-3">Đặt mật khẩu riêng để bảo vệ nội dung bài viết này khỏi người xem khác.</p>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Mật khẩu bài viết</label>
                        <input type="password" id="lock-input-password" class="form-control" minlength="4" placeholder="Nhập ít nhất 4 ký tự" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-outline-danger btn-sm mr-auto" id="btn-disable-lock" hidden>Gỡ bỏ mật khẩu</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger btn-sm">Lưu mật khẩu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL NHẬP MẬT KHẨU ĐỂ MỞ BÀI VIẾT BỊ KHÓA -->
<div class="modal fade" id="unlock-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 400px;">
        <div class="modal-content shadow border-0">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">🔒 Mở khóa bài viết</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="unlock-form">
                <div class="modal-body">
                    <input type="hidden" id="unlock-article-id">
                    <p class="small text-muted mb-3">Bài viết này đã được khóa bằng mật khẩu riêng. Vui lòng nhập mật khẩu:</p>
                    <div class="form-group mb-0">
                        <input type="password" id="unlock-input-password" class="form-control" placeholder="Mật khẩu bài viết" required>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-danger btn-sm">Mở khóa</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL CHIA SẺ BÀI VIẾT (SHARE ARTICLE) -->
<div class="modal fade" id="share-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 420px;">
        <div class="modal-content shadow border-0">
            <div class="modal-header">
                <h5 class="modal-title font-weight-bold">👥 Chia sẻ bài viết</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="share-form">
                <div class="modal-body">
                    <input type="hidden" id="share-article-id">
                    <div class="form-group mb-3">
                        <label class="small font-weight-bold">Email người nhận</label>
                        <input type="email" id="share-email" class="form-control" placeholder="name@example.com" required>
                    </div>
                    <div class="form-group mb-0">
                        <label class="small font-weight-bold">Quyền hạn</label>
                        <select id="share-permission" class="custom-select">
                            <option value="read">Chỉ xem (Read-only)</option>
                            <option value="edit">Được chỉnh sửa & Cộng tác (Edit)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary btn-sm">Chia sẻ ngay</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- MODAL AI SUMMARY & AI Q&A -->
<div class="modal fade" id="ai-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h5 class="modal-title font-weight-bold" id="ai-modal-title">✨ Trợ lý Trí tuệ Nhân tạo (AI)</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-4">
                <!-- Tóm tắt bài viết -->
                <div id="ai-summary-view" hidden>
                    <h6 class="font-weight-bold text-muted mb-2">Tóm tắt bài viết súc tích:</h6>
                    <div id="ai-summary-result" class="ai-box mb-3">Đang tạo tóm tắt...</div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="ai-regenerate-btn">🔄 Tạo lại tóm tắt</button>
                </div>

                <!-- Hỏi đáp trên toàn bộ bài viết -->
                <div id="ai-qa-view" hidden>
                    <h6 class="font-weight-bold text-muted mb-2">Hỏi đáp thông minh trên kho bài viết:</h6>
                    <div class="input-group mb-3">
                        <input type="text" id="ai-qa-input" class="form-control" placeholder="Nhập câu hỏi bất kỳ...">
                        <div class="input-group-append">
                            <button type="button" id="ai-qa-submit" class="btn btn-primary">Hỏi AI</button>
                        </div>
                    </div>
                    <div id="ai-qa-result" class="ai-box mb-3" style="display: none;"></div>
                    <div id="ai-qa-references" class="small"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    window.currentUserId = <?= (int)$user['id'] ?>;
    window.currentTab = <?= json_encode($tab) ?>;
    window.currentLabelId = <?= json_encode($labelId) ?>;
    window.currentCategory = <?= json_encode($activeCategory) ?>;
</script>

<?php require __DIR__ . '/Includes/footer.php'; ?>
