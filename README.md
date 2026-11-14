# HỆ THỐNG QUẢN LÝ BÀI VIẾT & TẠP CHÍ CÔNG NGHỆ GENK MAGAZINE
Môn học: Lập trình và Ứng dụng Web (503073) - Học kỳ I/2026-2027

---
## 1. TÀI KHOẢN ĐÁNH GIÁ (PRE-LOADED ACCOUNTS)

- **Tài khoản chính (Admin / Author 1):**
  - Email: `admin@example.com`
  - Mật khẩu: `Admin@123456`
  - Tình trạng: Đã kích hoạt đầy đủ, có sẵn 5 bài viết mẫu với hình ảnh, nhãn và trạng thái ghim.

- **Tài khoản thử nghiệm chia sẻ & cộng tác (Author 2):**
  - Giảng viên có thể đăng ký tài khoản mới trực tiếp tại `/Modules/Auth/register.php` (nhập mật khẩu 2 lần, tự động đăng nhập).

---
## 2. HƯỚNG DẪN CHẠY DỰ ÁN

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
## 3. DANH SÁCH TÍNH NĂNG ĐÁP ỨNG 32 TIÊU CHÍ RUBRIC

1. **Quản lý Tài khoản (8 tiêu chí):**
   - Đăng ký (nhập mật khẩu 2 lần, bcrypt hash, tự động đăng nhập).
   - Kích hoạt tài khoản qua link email & banner cảnh báo nổi bật.
   - Đăng nhập, đăng xuất an toàn (tái tạo session ID, bảo vệ CSRF).
   - Quên mật khẩu qua email token.
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
