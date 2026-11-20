# Genk Magazine

Ứng dụng đọc và quản lý bài báo với giao diện HTML/CSS/JavaScript, API và nghiệp vụ PHP, SQLite, MongoDB và hỗ trợ đọc offline bằng PWA. Email không được sử dụng.

## Chạy ứng dụng

Yêu cầu Docker Engine và Docker Compose. Tại thư mục gốc chạy:

```bash
docker compose up --build
```

Mở [http://localhost:8000/pages/index.html](http://localhost:8000/pages/index.html). Lần khởi động đầu tiên sẽ tạo cơ sở dữ liệu và một tài khoản admin trong môi trường development:

| Vai trò | Tên đăng nhập | Mật khẩu |
|---|---|---|
| Admin | `admin` | `Admin@123456` |

Đây là thông tin demo, không dùng cho môi trường production. Đăng ký tài khoản mới để tạo Reader; admin có thể đổi vai trò hoặc khóa/mở tài khoản trong trang quản trị. Không có Reader được tạo sẵn.

Dừng ứng dụng bằng `Ctrl+C`, hoặc chạy nền với `docker compose up --build -d` rồi dừng bằng `docker compose down`. Dữ liệu SQLite, MongoDB và avatar nằm trong Docker volumes; **`docker compose down -v` sẽ xóa toàn bộ dữ liệu**.

## Kiểm tra chức năng

### Kiểm tra PHP và cấu hình Compose

```bash
docker compose config -q
docker compose up -d --build
docker compose exec -T app sh -lc 'find backend -name "*.php" -print0 | xargs -0 -n1 php -l'
```

### Kiểm tra thủ công

1. Đăng nhập bằng tài khoản admin ở trên; thử tạo, sửa và ẩn/hiện bài báo.
2. Đăng ký một tài khoản mới và xác nhận tài khoản đó có vai trò Reader; thử tìm kiếm, lọc và yêu thích bài viết.
3. Với Reader, yêu cầu mã đặt lại mật khẩu, nhập mã hiển thị trong môi trường development và đặt mật khẩu mới. Mã dùng một lần, hết hạn sau 15 phút; sau khi đổi mật khẩu cần đăng nhập lại.
4. Kiểm tra hồ sơ, avatar, tùy chọn giao diện/cỡ chữ và thử mở bài đã lưu khi offline.
5. Mở giao diện ở các kích thước mobile, tablet và desktop; kiểm tra không tràn ngang.

API cùng origin được phục vụ dưới `/api/`. Có thể kiểm tra nhanh danh sách bài viết bằng:

```bash
curl -i http://localhost:8000/api/articles
```

## Cấu trúc chính

- `frontend/pages/`, `frontend/assets/`: các trang, giao diện và mã JavaScript gọi API.
- `frontend/service-worker.js`, `frontend/manifest.webmanifest`, `frontend/offline.html`: hỗ trợ PWA và offline.
- `backend/API/`: định tuyến và xử lý HTTP.
- `backend/Service/`: logic nghiệp vụ tài khoản, bài viết và quản trị.
- `backend/Middleware/`: session, CSRF và kiểm tra quyền.
- `backend/Database/`: migrations, SQLite, MongoDB và repositories.
- `backend/store/uploads/`: lưu avatar được upload.

Khi nâng cấp cơ sở dữ liệu cũ, migration giữ lại tài khoản và chuyển chúng sang đăng nhập bằng username; tài khoản cũ chưa có username được gán tên dựa trên role và ID.
