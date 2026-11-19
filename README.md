# HỆ THỐNG QUẢN LÝ BÀI VIẾT & TẠP CHÍ CÔNG NGHỆ GENK MAGAZINE
Môn học: Lập trình và Ứng dụng Web (503073) - Học kỳ I/2026-2027

---
## 1. TÀI KHOẢN ĐÁNH GIÁ (PRE-LOADED ACCOUNTS)

- **Tài khoản Master (quản trị toàn hệ thống):**
  - Email: `admin@example.com`
  - Mật khẩu: `1`
  - Tình trạng: Đã kích hoạt; có thể quản lý tài khoản, phân quyền Reader/Author và quản lý mọi bài viết.

- **Tài khoản mẫu:**
  - Author: `author@example.com` / `1`
  - Reader: `reader@example.com` / `1`
  - Các mật khẩu `1` chỉ phù hợp cho môi trường demo/local. Hãy đổi mật khẩu trước khi triển khai công khai.

- **Phân quyền tài khoản:**
  - Đăng ký mới tạo tài khoản Reader; Master có thể nâng lên Author trong trang Quản trị.
  - Author được đăng bài và quản lý bài viết của mình; Reader chỉ xem bài của mình hoặc bài được chia sẻ.
  - Master xem, tạo, sửa và xóa mọi bài viết; đồng thời quản lý quyền tài khoản.

---
## 2. CẤU TRÚC THƯ MỤC VÀ CHỨC NĂNG

```text
CK_web/
├── index.php                    # Bảng tin, danh sách bài viết và editor chính
├── api.php                      # Các API JSON cho xác thực, người dùng, bài viết...
├── router.php                   # Điều hướng URL, chuyển /api/* vào api.php
├── 404.php                      # Trang không tìm thấy
├── Includes/                    # Thành phần PHP dùng chung
├── Modules/
│   ├── Admin/manage.php         # Trang Master phân quyền Reader/Author
│   ├── Articles/                # Chi tiết bài viết và trang chuyển hướng editor
│   ├── Auth/                    # Đăng ký, đăng nhập, kích hoạt, khôi phục mật khẩu
│   └── User/                    # Hồ sơ và tùy chọn người dùng
├── Database/                    # Schema và SQLite runtime
├── Assets/
│   ├── CSS/                     # Giao diện
│   ├── JS/                      # Tương tác trình duyệt và gọi API
│   ├── Images/                  # Biểu tượng ứng dụng
│   └── Uploads/                 # Avatar và tệp đính kèm tải lên
├── Realtime/                    # Thành phần WebSocket
├── bin/                         # Lệnh khởi chạy WebSocket server
├── vendor/                      # Thư viện Composer, không sửa trực tiếp
├── Dockerfile
├── docker-compose.yml
├── composer.json / composer.lock
├── manifest.webmanifest
├── service-worker.js
└── offline.html
```

### File ở thư mục gốc

| File | Chức năng |
|---|---|
| `index.php` | Trang chính sau đăng nhập: tìm/lọc bài viết, nhãn, danh sách và hộp soạn/sửa bài viết thống nhất. |
| `api.php` | Backend JSON: đăng nhập/đăng ký, hồ sơ, phân quyền Master, nhãn, bài viết, chia sẻ và AI. |
| `router.php` | Front controller cho PHP built-in server; định tuyến `/api/...`, trang chính và file tĩnh, chặn truy cập thư mục riêng. |
| `404.php` | Giao diện lỗi khi URL không tồn tại. |
| `.htaccess` | Cấu hình Apache: rewrite API và chặn truy cập các thư mục/file nội bộ. |
| `Dockerfile`, `docker-compose.yml` | Build và chạy ứng dụng PHP/Apache cùng WebSocket server bằng Docker. |
| `composer.json`, `composer.lock` | Khai báo và khóa phiên bản thư viện PHP (Ratchet/WebSocket). |
| `README.md` | Tài liệu cài đặt, tài khoản mẫu, chức năng và cấu trúc dự án. |
| `manifest.webmanifest`, `service-worker.js`, `offline.html` | Khai báo PWA, cache giao diện tĩnh và trang dự phòng khi ngoại tuyến. |
| `503073-FinalProject-V1.docx`, `AD_WebCK.drawio.png` | Tài liệu báo cáo và sơ đồ thiết kế của đồ án. |

### Thành phần PHP và dữ liệu

| Đường dẫn | Chức năng |
|---|---|
| `Includes/bootstrap.php` | Nạp kết nối database và hàm dùng chung; khởi tạo session/CSRF cho các trang web. |
| `Includes/db.php` | Mở SQLite, thiết lập khóa ngoại/WAL, tạo schema và cập nhật cấu trúc database cũ. |
| `Includes/functions.php` | Escape HTML, phản hồi JSON, kiểm tra HTTP method/CSRF, người dùng hiện tại và quyền truy cập. |
| `Includes/header.php` | Phần đầu trang, menu theo vai trò và thông tin CSRF cho form/API. |
| `Includes/footer.php` | Phần cuối trang; nạp thư viện Bootstrap và `Assets/JS/app.js`. |
| `Database/schema.sql` | Định nghĩa bảng, quan hệ khóa ngoại, tài khoản Master và dữ liệu bài viết mẫu. |
| `Database/database.sqlite` | Database runtime trên máy; các file `-wal`/`-shm` là file SQLite phát sinh khi chạy. Những file database runtime được `.gitignore` loại khỏi Git. |
| `Modules/Admin/manage.php` | Trang chỉ Master được truy cập, liệt kê tài khoản và cấp/thu quyền Author. |
| `Modules/Auth/login.php`, `register.php` | Giao diện đăng nhập và đăng ký. |
| `Modules/Auth/activate.php`, `reset-password.php` | Kích hoạt tài khoản và yêu cầu/đặt lại mật khẩu. |
| `Modules/User/profile.php`, `preferences.php` | Cập nhật hồ sơ/avatar/mật khẩu và tùy chọn giao diện. |
| `Modules/Articles/detail.php` | Trang chi tiết bài viết, nội dung, file đính kèm và thao tác theo quyền. |
| `Modules/Articles/create.php`, `edit.php` | URL tương thích cũ; chuyển sang editor thống nhất trên `index.php`. |
| `Realtime/ArticleHub.php` | WebSocket hub xác thực token, phân phòng theo bài và phát nội dung cộng tác. |
| `bin/websocket-server.php` | Nạp Composer, database và khởi động WebSocket server. |
| `Assets/JS/app.js` | Xử lý form/API, editor, lưu tự động, chia sẻ, upload, PWA và tương tác giao diện. |
| `Assets/CSS/style.css`, `Assets/Images/app-icon.svg` | CSS và biểu tượng ứng dụng. |
| `Assets/Uploads/avatars/`, `Assets/Uploads/attachments/` | File người dùng tải lên; đây là dữ liệu runtime, không phải mã nguồn. |
| `vendor/` | Mã thư viện bên thứ ba do Composer cài đặt; cập nhật bằng Composer, không chỉnh tay. |

**Luồng xử lý chính:** trình duyệt tải `index.php` và `Assets/JS/app.js`; JavaScript gọi `/api/...`, được `router.php` chuyển tới `api.php`; `Includes/bootstrap.php` nạp `Includes/db.php` và các hàm dùng chung. Cộng tác thời gian thực đi qua `bin/websocket-server.php` và `Realtime/ArticleHub.php`.

---
## 3. HƯỚNG DẪN CHẠY DỰ ÁN

### Cách 1: Sử dụng Docker Compose (Khuyến nghị - 1 lệnh duy nhất)
```bash
docker compose up -d
```
- Mở trình duyệt truy cập: `http://localhost:8080`
- Cổng WebSocket Realtime: `8081`

### Cách 2: Chạy trực tiếp bằng PHP tích hợp (Local PHP)
Yêu cầu: PHP 8.1 trở lên, tiện ích mở rộng PDO SQLite (`pdo_sqlite`).

```bash
# Bước 1: Cài đặt dependencies
composer install

# Bước 2: Thiết lập biến môi trường và chạy WebSocket server (Terminal 1)
export APP_KEY="genk-secret-key-at-least-32-chars-long-2026"
export APP_URL="http://localhost:8000"
export APP_WS_URL="ws://127.0.0.1:8081"
php bin/websocket-server.php

# Bước 3: Khởi chạy Web Server tích hợp (Terminal 2)
php -S localhost:8000 router.php
```
Mở trình duyệt truy cập: `http://localhost:8000`

---
## 4. DANH SÁCH TÍNH NĂNG ĐÁP ỨNG 32 TIÊU CHÍ RUBRIC

1. **Quản lý Tài khoản (8 tiêu chí):**
   - Đăng ký (nhập mật khẩu 2 lần, bcrypt hash, tự động đăng nhập với quyền Reader).
   - Phân quyền Master / Author / Reader; Master quản lý tài khoản và nội dung toàn website.
   - Kích hoạt tài khoản qua link email & banner cảnh báo nổi bật.
   - Đăng nhập, đăng xuất an toàn (tái tạo session ID, bảo vệ CSRF).
   - Khôi phục mật khẩu bằng token; môi trường demo hiển thị liên kết đặt lại trực tiếp (chưa cấu hình gửi email).
   - Xem và chỉnh sửa hồ sơ, tải lên file ảnh avatar (validate file ảnh an toàn, hiển thị avatar mặc định).
   - Đổi mật khẩu trong hồ sơ cá nhân.
   - Tùy chọn giao diện: Chế độ Sáng/Tối (Light/Dark mode), cỡ chữ bài viết (12-24px), màu sắc bài viết.

2. **Quản lý Bài viết Cơ bản (14 tiêu chí):**
   - Chuyển đổi linh hoạt giữa Dạng lưới (Grid View - mặc định) và Dạng danh sách (List View).
   - Tạo bài viết mới & Chỉnh sửa bài viết trên **cùng một giao diện Editor thống nhất** (Single unified modal). Form tạo ban đầu chỉ bắt buộc Tiêu đề và Nội dung.
   - Tự động lưu ngầm (Auto-save) khi gõ sau 500ms không cần nút bấm.
   - Xóa bài viết có hộp thoại xác nhận bắt buộc (`confirm dialog`).
   - Đính kèm một hoặc nhiều hình ảnh, video, tệp tin.
   - Sắp xếp bài viết theo thời gian tạo, cập nhật, hoặc ghim.
   - Ghim bài viết (Pin) và Đa ghim (Multi-pin) sắp xếp theo thời gian ghim.
   - Icon nhận diện trực quan cho bài viết đặc biệt: 📌 Ghim, 🔒 Khóa mật khẩu, 👥 Được chia sẻ.
   - Tìm kiếm trực tiếp (Live search) quét đồng thời cả Tiêu đề và Nội dung bài viết với debounce 300ms.
   - Quản lý nhãn: Thêm nhãn, đổi tên nhãn, xóa nhãn (xóa nhãn không xóa bài viết liên kết).
   - Gắn nhiều nhãn cho bài viết và lọc bài viết theo nhãn.

3. **Quản lý Nâng cao & AI (6 tiêu chí):**
   - Bật / tắt và đổi mật khẩu bảo vệ riêng cho từng bài viết.
   - Hộp thoại nhắc mật khẩu trước khi xem/sửa/xóa bài viết bị khóa.
   - Chia sẻ bài viết qua email người dùng với quyền Chỉ đọc (Read-only) hoặc Chỉnh sửa (Edit).
   - Tab riêng **"Được chia sẻ với tôi" (Shared with me)** hiển thị thông tin người chia sẻ, thời gian và quyền hạn.
   - Cộng tác biên tập bài viết thời gian thực (Realtime Collaboration) qua WebSocket khi nhiều người cùng mở bài viết có quyền edit.
   - AI Summary: Tóm tắt bài viết tự động bằng LLM với nút tạo lại tóm tắt.
   - AI Question & Answer: Hỏi đáp tự nhiên trên tập bài viết, tổng hợp câu trả lời kèm link tham chiếu nguồn bài viết.

4. **Giao diện, PWA & Triển khai (4 tiêu chí):**
   - Giao diện người dùng hiện đại, tinh tế, đầy đủ phản hồi Toast, trạng thái tải và empty state.
   - Thiết kế Responsive hoàn chỉnh cho Mobile (<576px), Tablet (768px-1024px) và Desktop (>1200px).
   - PWA: Service Worker lưu bộ đệm tĩnh + IndexedDB lưu trữ bài viết và soạn nháp ngoại tuyến (Offline first), tự động đồng bộ khi có mạng trở lại.
   - Triển khai công khai trực tuyến trên Cloud HTTPS và đóng gói hoàn chỉnh bằng Docker Compose.
