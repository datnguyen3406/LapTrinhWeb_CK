# Kịch bản kiểm thử - GenK Magazine

## 1. Thông tin kiểm thử

- **Tác giả:** Nhóm phát triển
- **Nền tảng:** Docker Compose, PHP 8.3, SQLite, MongoDB 7
- **URL:** `http://localhost:8000`
- **Tài khoản Admin mặc định:** `admin` / `Admin@123456`
- **Trình duyệt kiểm thử:** Chrome hoặc Firefox, chế độ Desktop và Mobile
- **Ngày kiểm thử:** Cập nhật theo ngày chạy test

## 2. Tiền điều kiện

1. Docker Engine và Docker Compose đã được cài đặt.
2. Chạy `docker compose up -d --build`.
3. Mở `http://localhost:8000/pages/index.html`.
4. MongoDB, SQLite và container ứng dụng đang hoạt động.

## 3. Kịch bản chức năng

| Mã | Nhóm | Tên test | Bước thực hiện | Dữ liệu test | Kết quả mong đợi | Kết quả thực tế |
|---|---|---|---|---|---|---|
| TC-001 | Công khai | Xem danh sách bài viết | Mở trang chủ | Không | Bài viết và bài nổi bật hiển thị | |
| TC-002 | Công khai | Tìm kiếm bài viết | Nhập từ khóa và nhấn Tìm | `AI`, `bài không tồn tại` | Bài phù hợp hiển thị; từ khóa sai không có kết quả | |
| TC-003 | Công khai | Lọc theo danh mục | Chọn từng danh mục trên thanh điều hướng | Mobile, AI, Internet, TIN ICT, Khám phá | Danh sách được lọc theo danh mục | |
| TC-004 | Công khai | Lọc theo nguồn | Chọn nguồn ở trang chủ | GenK, VnExpress Số Hóa | Chỉ bài của nguồn được chọn hiển thị | |
| TC-005 | Công khai | Xem chi tiết bài | Nhấn tiêu đề bài | Bài đã xuất bản | Hiển thị tiêu đề, tóm tắt, nội dung, ảnh và/hoặc video | |
| TC-006 | Đăng ký | Đăng ký hợp lệ | Mở Đăng ký, nhập đủ thông tin | Username 3-32 ký tự, mật khẩu >=8 ký tự | Tạo tài khoản Reader và hiển thị mã tài khoản/mã khôi phục | |
| TC-007 | Đăng ký | Trùng username | Đăng ký username đã tồn tại | Username vừa tạo ở TC-006 | Báo username đã được sử dụng | |
| TC-008 | Đăng ký | Mật khẩu xác nhận sai | Nhập hai mật khẩu khác nhau | `Test@123456` / `Test@654321` | Không tạo tài khoản; báo hai mật khẩu không khớp | |
| TC-009 | Đăng nhập | Đăng nhập sai | Nhập username/mật khẩu sai | Sai user và password | HTTP 401, thông báo không chính xác | |
| TC-010 | Đăng nhập | Đăng nhập Reader | Đăng nhập bằng tài khoản vừa tạo | Reader mới | Chuyển đến trang chủ Reader | |
| TC-011 | Đăng nhập | Đăng nhập Admin | Đăng nhập tài khoản admin | `admin` / `Admin@123456` | Chuyển đến Dashboard Admin | |
| TC-012 | Hệ thống | Đăng xuất | Nhấn đăng xuất | Tài khoản đang đăng nhập | Xóa phiên; chuyển hoặc khóa quyền truy cập riêng tư | |
| TC-013 | Hồ sơ | Cập nhật tên hiển thị | Mở hồ sơ, đổi tên, lưu | `Reader Đã Cập Nhật` | Tên hiển thị được cập nhật | |
| TC-014 | Hồ sơ | Upload avatar hợp lệ | Chọn JPEG/PNG/WebP nhỏ hơn 5 MB | File PNG hợp lệ | Avatar xem trước và trên máy chủ được cập nhật | |
| TC-015 | Hồ sơ | Upload avatar không hợp lệ | Chọn GIF hoặc file >5 MB | GIF hoặc >5 MB | Báo định dạng/dung lượng không hợp lệ | |
| TC-016 | Hồ sơ | Tạo mã khôi phục | Nhấn tạo mã khôi phục mới | Tài khoản Reader | Mã mới được hiển thị và lưu mã ở máy chủ dạng hash | |
| TC-017 | Mật khẩu | Đổi mật khẩu hợp lệ | Nhập mật khẩu cũ/mới/xác nhận | `Test@123456` -> `Reader@654321` | Đổi thành công | |
| TC-018 | Mật khẩu | Sai mật khẩu hiện tại | Nhập mật khẩu cũ sai | Mật khẩu sai | Không đổi; báo lỗi | |
| TC-019 | Mật khẩu | Khôi phục bằng mã | Dùng account ID và recovery code mới | Mã khôi phục mới | Đặt lại được mật khẩu | |
| TC-020 | Yêu thích | Thêm yêu thích | Mở bài và nhấn lưu | Bài đã xuất bản | Bài xuất hiện trong mục yêu thích | |
| TC-021 | Yêu thích | Tạo thư mục yêu thích | Tạo thư mục mới | `Kịch bản test` | Thư mục xuất hiện trong danh sách | |
| TC-022 | Yêu thích | Thêm bài vào thư mục | Chọn thư mục và lưu bài | Thư mục vừa tạo | Bài nằm đúng thư mục | |
| TC-023 | Yêu thích | Bỏ yêu thích | Nhấn bỏ khỏi yêu thích | Bài vừa lưu | Bài bị loại khỏi danh sách | |
| TC-024 | Yêu thích | Trùng tên thư mục | Tạo lại tên thư mục đã có | `Kịch bản test` | Báo thư mục đã tồn tại | |
| TC-025 | Lịch sử | Ghi lịch sử đọc | Mở chi tiết bài khi đã đăng nhập | Bài đã xuất bản | Bài được ghi vào lịch sử đọc | |
| TC-026 | Lịch sử | Xem lịch sử | Mở trang Reader | Tài khoản có lịch sử | Danh sách hiển thị theo lần đọc mới nhất | |
| TC-027 | Lịch sử | Xóa lịch sử | Nhấn xóa lịch sử đọc và xác nhận | Tài khoản Reader | Danh sách lịch sử trống | |
| TC-028 | Giao diện | Tăng/giảm cỡ chữ | Nhấn nút cỡ chữ | 12-24 px | Cỡ chữ thay đổi và được lưu | |
| TC-029 | Giao diện | Chuyển theme | Chuyển sáng/tối/tự động | Light/Dark/Auto | Giao diện thay đổi, trạng thái được lưu | |
| TC-030 | Phân loại Reader | Tạo phân loại cá nhân | Nhấn tạo nhãn trên trang Reader | `Kịch bản cá nhân` | Phân loại được tạo | |
| TC-031 | Admin | Xem dashboard | Đăng nhập Admin, mở Dashboard | Admin | Hiển thị số liệu bài viết, người dùng, nguồn và danh mục | |
| TC-032 | Admin người dùng | Xem danh sách | Mở Quản lý tài khoản | Admin | Danh sách tài khoản hiển thị | |
| TC-033 | Admin người dùng | Tìm/lọc người dùng | Tìm username và lọc role | Reader/Admin | Kết quả đúng điều kiện | |
| TC-034 | Admin người dùng | Thay đổi role | Đổi Reader thành Admin rồi Reader | Reader test | Role cập nhật thành công | |
| TC-035 | Admin người dùng | Khóa/mở tài khoản | Khóa rồi mở Reader test | Reader test | Khóa thì không đăng nhập; mở lại đăng nhập được | |
| TC-036 | Admin nguồn tin | Tạo nguồn | Nhập tên, slug, website | `Nguồn Test` | Nguồn mới xuất hiện | |
| TC-037 | Admin nguồn tin | Cập nhật nguồn | Sửa tên nguồn | `Nguồn Test Cập Nhật` | Tên nguồn cập nhật | |
| TC-038 | Admin nguồn tin | Tìm/lọc nguồn | Tìm tên hoặc chọn danh mục | Tên/ID danh mục | Kết quả lọc đúng | |
| TC-039 | Admin nguồn tin | Xóa mềm nguồn | Nhấn Xóa/Ẩn nguồn | Nguồn test | Nguồn không còn ở danh sách công khai | |
| TC-040 | Admin danh mục | Tạo danh mục | Gửi API/hoặc công cụ quản trị nếu có | `Danh mục Test` | Danh mục được tạo | |
| TC-041 | Admin danh mục | Cập nhật danh mục | Sửa tên danh mục | `Danh mục Test Cập Nhật` | Tên danh mục cập nhật | |
| TC-042 | Admin danh mục | Xóa mềm danh mục | Chuyển isActive=false | Danh mục test | Danh mục ẩn khỏi trang công khai | |
| TC-043 | Admin bài viết | Tạo bài có ảnh URL | Nhập đủ nội dung và URL ảnh bìa | `https://example.com/cover.jpg` | Bài được tạo, ảnh bìa hiển thị nếu URL truy cập được | |
| TC-044 | Admin bài viết | Tạo bài có ảnh trong bài | Dán URL ảnh từng dòng | URL JPG/PNG | Ảnh hiển thị trong phần nội dung | |
| TC-045 | Admin bài viết | Tạo bài có video URL | Dán URL MP4/WebM | URL video trực tiếp | Video có controls và phát được | |
| TC-046 | Admin bài viết | Upload ảnh bìa file | Chọn JPEG/PNG/WebP | File ảnh <=50 MB | Ảnh lưu bằng GridFS và hiển thị qua `/api/media/<id>` | |
| TC-047 | Admin bài viết | Upload video file | Chọn MP4/WebM/Ogg | File video <=50 MB | Video lưu và phát được qua `/api/media/<id>` | |
| TC-048 | Admin bài viết | Upload sai định dạng | Chọn GIF hoặc file >50 MB | GIF/>50 MB | Hệ thống từ chối bằng 415/413 | |
| TC-049 | Admin bài viết | Cập nhật bài | Sửa tiêu đề, tóm tắt, nội dung | Nội dung mới | Nội dung mới được lưu và hiển thị | |
| TC-050 | Admin bài viết | Ẩn bài | Nhấn Ẩn bài | Bài test | Bài không còn trong danh sách công khai | |
| TC-051 | Admin bài viết | Hiện lại bài | Nhấn Hiện bài | Bài test | Bài xuất hiện lại công khai | |
| TC-052 | Admin bài viết | Xóa bài | Nhấn Xóa và xác nhận | Bài test | Bài bị xóa/ẩn khỏi hệ thống | |
| TC-053 | Nhật ký | Xem log | Mở Lịch sử hoạt động | Bài và tài khoản test | Hiển thị thời gian, người thực hiện, hành động, đối tượng | |
| TC-054 | Nhật ký | Lọc theo hành động | Chọn create/update/archive/user.update | Từng loại | Chỉ log phù hợp hiển thị | |
| TC-055 | Nhật ký | Tìm kiếm log | Nhập từ khóa | Username/action/target | Kết quả phù hợp hiển thị | |
| TC-056 | Nhật ký | Lọc theo ngày | Chọn ngày | Ngày có log | Chỉ log trong ngày hiển thị | |
| TC-057 | Nhật ký | Xóa toàn bộ log | Nhấn Xóa toàn bộ và xác nhận | Admin | Nhật ký trống | |
| TC-058 | Phân quyền | Reader truy cập Admin | Nhập URL trang Admin khi đăng nhập Reader | Reader | Redirect hoặc chặn 403 | |
| TC-059 | Bảo mật | Gửi POST không CSRF | Gửi POST không header CSRF | `/api/me/password` | Hệ thống từ chối CSRF | |
| TC-060 | PWA | Kiểm tra manifest | Mở manifest trong DevTools | `/manifest.webmanifest` | HTTP 200 và JSON hợp lệ | |
| TC-061 | PWA | Kiểm tra service worker | Kiểm tra Application > Service Workers | `/service-worker.js` | Service worker đăng ký thành công | |
| TC-062 | PWA | Mở trang offline | Mất mạng và reload bài đã cache | Browser DevTools Offline | Trang nội dung đã cache vẫn mở; trang chưa cache mở offline fallback | |
| TC-063 | Responsive | Desktop | Thu nhỏ/mở DevTools kích thước desktop | 1366x768 | Không tràn ngang, menu rõ ràng | |
| TC-064 | Responsive | Tablet | Kiểm tra ở 768x1024 | Tablet | Card và form co theo viewport | |
| TC-065 | Responsive | Mobile | Kiểm tra ở 390x844 | Mobile | Không tràn ngang; nút và form sử dụng được | |

## 4. Lệnh chạy kiểm thử API tự động

```bash
python3 tests/test_api.py
```

Các test tự động kiểm tra các API công khai, đăng ký/đăng nhập, hồ sơ, avatar, mật khẩu, yêu thích, lịch sử, quyền Admin, CRUD nguồn/danh mục/bài viết, media URL và nhật ký hoạt động.

## 5. Kết quả kiểm thử thực tế

| Hạng mục | Kết quả | Ghi chú |
|---|---|---|
| API tự động | **51/51 PASS** | Chạy bằng `python3 tests/test_api.py` |
| Đăng nhập Admin bằng giao diện | PASS | Chuyển đúng sang trang Dashboard |
| Dashboard Admin | PASS | Hiển thị thống kê tài khoản, bài viết và bài đang hiển thị |
| Tạo bài viết bằng giao diện | PASS | Tạo bài có tiêu đề, ảnh bìa URL, ảnh trong bài và video URL |
| Lưu bài viết bằng giao diện | PASS | Hiển thị toast và thêm bài vào danh sách quản trị |
| Ẩn/Archive bài viết bằng giao diện | PASS | Bài chuyển sang trạng thái bản nháp/đang ẩn |
| Nhật ký hoạt động bằng giao diện | PASS | Bảng hiển thị đúng các cột và log `article.create`, `article.archive` |
| PWA manifest | PASS | `/manifest.webmanifest` trả HTTP 200 |
| PWA offline page | PASS | `/offline.html` trả HTTP 200 |
| PWA service worker | PASS | `/service-worker.js` trả HTTP 200 |
| Dữ liệu media | PASS | URL ảnh/video được lưu và trả lại trong chi tiết bài viết |

## 6. Lỗi phát hiện trong quá trình test và đã sửa

1. Trang lịch sử Admin đang dùng sai ID `<tbody>`, làm dữ liệu load được nhưng không render.
2. Bộ lọc hành động lịch sử chưa khớp với action thực tế như `article.create`, `article.update`, `article.archive`.
3. Form quản lý bài viết chưa có trường upload/dán ảnh và video.
4. Backend media chỉ chấp nhận ảnh; đã bổ sung hỗ trợ video MP4, WebM, Ogg.
5. Khi đổi role và khóa tài khoản trong cùng một request, kiểm tra quyền khóa dùng role cũ; đã đổi sang role sau cập nhật.
6. Giới hạn upload mặc định của PHP thấp; Dockerfile đã cấu hình upload tối đa 50 MB.
