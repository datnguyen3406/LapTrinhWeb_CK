HƯỚNG DẪN ĐÁNH GIÁ ĐỒ ÁN CUỐI KỲ - GENK MAGAZINE
Môn học: 503073 – Lập trình và Ứng dụng Web
Học kỳ: I/2026-2027

==================================================
1. TÀI KHOẢN ĐÁNH GIÁ (PRE-LOADED ACCOUNTS)
==================================================
Tài khoản 1 (Chính):
- Email: admin@example.com
- Mật khẩu: Admin@123456
- Tình trạng: Đã kích hoạt đầy đủ, có sẵn 5 bài viết mẫu với hình ảnh, nhãn, bài viết đã ghim.

Tài khoản 2 (Thử nghiệm chia sẻ / cộng tác):
- Giảng viên có thể đăng ký trực tiếp tại: /Modules/Auth/register.php
(Hệ thống tự động đăng nhập ngay sau khi đăng ký thành công).

==================================================
2. HƯỚNG DẪN CHẠY DỰ ÁN
==================================================
Cách 1: Sử dụng Docker Compose (Khuyến nghị - 1 lệnh duy nhất)
--------------------------------------------------
Tại thư mục gốc của dự án, mở terminal chạy:
    docker compose up -d

- Truy cập Web: http://localhost:8080
- WebSocket Realtime tự động chạy tại cổng 8081.

Cách 2: Chạy trực tiếp bằng PHP tích hợp (Local PHP)
--------------------------------------------------
Yêu cầu: PHP 8.1 trở lên với pdo_sqlite.

Terminal 1 (Khởi chạy WebSocket Server):
    composer install
    export APP_KEY="genk-secret-key-at-least-32-chars-long-2026"
    export APP_URL="http://localhost:8000"
    export APP_WS_URL="ws://127.0.0.1:8081"
    php bin/websocket-server.php

Terminal 2 (Khởi chạy Web):
    php -S localhost:8000 router.php

- Truy cập Web: http://localhost:8000

==================================================
3. DANH SÁCH 32 TIÊU CHÍ ĐÃ HOÀN THIỆN (RUBRIC)
==================================================
1. Đăng ký tài khoản (nhập mật khẩu 2 lần, bcrypt, tự động đăng nhập).
2. Kích hoạt tài khoản qua link email & banner cảnh báo nổi bật.
3. Đăng nhập / Đăng xuất an toàn.
4. Quên mật khẩu qua email token.
5. Xem thông tin hồ sơ & avatar mặc định.
6. Cập nhật hồ sơ & upload file ảnh đại diện.
7. Đổi mật khẩu trong hồ sơ cá nhân.
8. Tùy chọn giao diện (Theme Sáng/Tối, cỡ chữ bài viết, màu sắc).
9. Xem bài viết dạng Danh sách (List View).
10. Xem bài viết dạng Lưới (Grid View - mặc định).
11. Tạo bài viết (chung 1 màn hình Editor duy nhất, form bắt đầu chỉ gồm Title & Content).
12. Sửa bài viết (mở trực tiếp trên cùng giao diện Editor).
13. Xóa bài viết có hộp thoại xác nhận bắt buộc.
14. Auto-save tự động lưu bài viết khi gõ sau 500ms không cần nút bấm.
15. Đính kèm tệp tin hình ảnh / video / tài liệu vào bài viết.
16. Sắp xếp bài viết (Mới nhất, cập nhật, ghim).
17. Ghim bài viết (Pin).
18. Đa ghim bài viết (Multi-pin sắp xếp theo thời gian ghim gần nhất).
19. Live Search quét đồng thời Tiêu đề và Nội dung bài viết với độ trễ 300ms.
20. Quản lý nhãn (Thêm nhãn, đổi tên, xóa nhãn).
21. Gắn nhiều nhãn vào bài viết.
22. Lọc bài viết theo nhãn (xóa nhãn không mất bài viết).
23. Bật/tắt mật khẩu khóa bài viết.
24. Hộp thoại nhập mật khẩu bảo vệ & đổi mật khẩu bài viết.
25. Chia sẻ bài viết qua email (quyền Xem hoặc Sửa) & Tab riêng "Được chia sẻ với tôi".
26. Cộng tác thời gian thực qua WebSocket giữa các tác giả.
27. AI Summary: Tóm tắt bài viết tự động bằng LLM.
28. AI Question & Answer: Hỏi đáp thông minh trên tập bài viết, trích dẫn bài viết nguồn.
29. Thiết kế UI & UX mượt mà, trực quan, có empty state và toast notification.
30. Responsive toàn diện trên Smartphone, Tablet và Desktop.
31. PWA Offline: Service Worker cache + IndexedDB lưu và soạn nháp ngoại tuyến, tự động đồng bộ khi có mạng.
32. Đóng gói Docker Compose chạy ngay lập tức.
