# GenK Magazine

Website tin công nghệ dùng PHP và SQLite. Trang chủ nằm ở `index.php`; các bài viết được hiển thị qua một trang chi tiết dùng chung.

## Yêu cầu

- PHP 8.0 trở lên
- Phần mở rộng PDO SQLite (`pdo_sqlite`)

## Chạy dự án

Từ thư mục gốc dự án, chạy:

```sh
php -S localhost:8000
```

Mở <http://localhost:8000>. `Includes/db.php` tự tạo cơ sở dữ liệu từ `Database/schema.sql` khi ứng dụng được truy cập lần đầu. Tệp database và các tệp WAL được giữ cục bộ, không đưa lên Git.

## Cấu trúc

```text
Assets/
  CSS/       style.css, ReadForm.css
  JS/        feature.js
  Uploads/   Tệp tải lên
Database/    schema.sql, database.sqlite (tạo lúc chạy)
Includes/    Kết nối SQLite và giao diện dùng chung
Modules/
  Auth/      Khu vực dành cho chức năng tài khoản
  Articles/  Trang chi tiết bài viết
  User/      Khu vực dành cho hồ sơ và tùy chọn người dùng
index.php    Trang chủ
```

Các bài viết trên trang chủ liên kết đến `Modules/Articles/detail.php?id=<id>`. Dữ liệu khởi tạo được khai báo trong `Database/schema.sql`.