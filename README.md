# GenK Magazine

Website tin công nghệ viết bằng PHP và SQLite, theo kiến trúc client → API/backend → FIFO worker → database. Hỗ trợ tài khoản, bài viết, hồ sơ, tùy chọn giao diện, PWA/offline và cộng tác chỉnh sửa thời gian thực tùy cấu hình.

## Yêu cầu

- PHP 8.1 trở lên
- Phần mở rộng PDO SQLite (`pdo_sqlite`)
- Composer
- Node không bắt buộc; dịch vụ WebSocket cần Composer package Ratchet

## Chạy dự án

### Cài đặt và chạy

```sh
composer install
export APP_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
export APP_URL="http://localhost:8000"
export APP_WS_URL="ws://127.0.0.1:8081"
php -S localhost:8000 router.php
```

Mở <http://localhost:8000>. Schema và dữ liệu bài viết mẫu được khởi tạo tự động từ `Database/schema.sql`; SQLite bật WAL và `busy_timeout`. Dùng `router.php` để ngăn truy cập trực tiếp vào database và các file nội bộ.

### Worker và WebSocket

Chạy worker nền trong terminal riêng để xử lý FIFO autosave, gửi email khôi phục mật khẩu và phát sự kiện:

```sh
php bin/worker.php
```

Chạy một job duy nhất để kiểm thử:

```sh
php bin/worker.php --once
```

Để bật cộng tác thời gian thực, cài dependencies bằng `composer install`, rồi chạy:

```sh
php bin/websocket-server.php
```

Máy chủ mặc định chỉ bind `127.0.0.1:8081`; khi triển khai thật, đặt reverse proxy WSS và `APP_WS_URL=wss://ten-mien-cua-ban/...`. `APP_KEY` phải là chuỗi bí mật ngẫu nhiên ít nhất 32 ký tự và phải giống nhau giữa PHP API và WebSocket server.

Email đặt lại mật khẩu được gửi qua `mail()` bởi worker. Cần cấu hình mail transport của PHP; nếu gửi lỗi, job được retry tối đa 5 lần rồi đánh dấu failed và ghi lỗi ở `jobs.last_error`.

## Các chức năng

- **Auth:** đăng ký, đăng nhập/đăng xuất, băm mật khẩu, token CSRF và đặt lại mật khẩu một giờ qua email.
- **Articles:** trang chi tiết động, tạo/sửa/xóa/ghim và khóa mật khẩu bài viết của chính tác giả, autosave qua FIFO worker.
- **User:** cập nhật hồ sơ và tùy chọn theme/font/màu ghi chú.
- **PWA:** cache shell tĩnh, trang dự phòng offline, chỉ cache trang chủ công khai (không cache trang chủ khi đã đăng nhập), lưu bản nháp vào IndexedDB và đồng bộ lại khi có mạng.
- **Realtime:** WebSocket có token HMAC giới hạn theo tài khoản/bài viết, relay nội dung nháp giữa các phiên cùng chủ sở hữu.
- **Queue:** `jobs` lưu tác vụ FIFO, retry lũy tiến và ghi nhận lỗi; notification được worker WebSocket phát tới room.

API dùng đường dẫn `/api/...`; ví dụ `/api/articles`, `/api/auth/login`, `/api/user/preferences`.

## Cấu trúc

```text
Assets/
  CSS/       style.css, ReadForm.css
  JS/        feature.js, app.js
  Uploads/   Tệp tải lên
Database/    schema.sql, database.sqlite (tạo lúc chạy, bị chặn khỏi web)
Includes/    Bootstrap, SQLite, helpers, header/footer
Modules/
  Auth/      Đăng ký, đăng nhập, khôi phục mật khẩu
  Articles/  CRUD, chi tiết, autosave/quản lý bài viết
  User/      Hồ sơ và tùy chọn
Realtime/    Ratchet WebSocket article hub
bin/         Worker FIFO và WebSocket server
api.php      JSON API
router.php   Router/guard cho PHP built-in server
index.php    Trang chủ động từ SQLite
```

Các bài viết liên kết đến `Modules/Articles/detail.php?id=<id>`. `Database/database.sqlite` và các file WAL bị loại khỏi Git; không đặt thư mục dữ liệu có thông tin nhạy cảm dưới web root khi triển khai production. Dùng HTTPS/WSS, backup database và cấu hình `APP_KEY`, mail transport phù hợp trước khi public.