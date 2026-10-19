CREATE TABLE IF NOT EXISTS articles (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    category TEXT NOT NULL,
    excerpt TEXT NOT NULL,
    content TEXT NOT NULL,
    image_url TEXT NOT NULL,
    published_at TEXT NOT NULL
);

INSERT OR IGNORE INTO articles (id, title, category, excerpt, content, image_url, published_at) VALUES
    (1, 'Bị Mỹ cấm xuất khẩu, 4,6 tỷ USD chip NVIDIA vẫn được đưa vào Trung Quốc như thế nào?', 'Tin ICT', 'Một câu hỏi lớn về chuỗi cung ứng công nghệ toàn cầu khi các biện pháp cấm vận khắt khe không thể hoàn toàn ngăn chặn dòng chảy của vi mạch AI tiên tiến nhất hiện nay.', 'Một câu hỏi lớn về chuỗi cung ứng công nghệ toàn cầu khi các biện pháp cấm vận khắt khe không thể hoàn toàn ngăn chặn dòng chảy của vi mạch AI tiên tiến nhất hiện nay.', 'https://picsum.photos/seed/tech/800/600', '21/09/2026 • 21:25'),
    (2, 'vivo X500 series chính thức: Nâng cấp quá ngon, camera chụp đẹp như máy ảnh', 'Mobile', 'Cảm biến lớn, khả năng chụp đêm và các tính năng AI mới là điểm nhấn trên dòng điện thoại vừa ra mắt.', 'Cảm biến lớn, khả năng chụp đêm và các tính năng AI mới là điểm nhấn trên dòng điện thoại vừa ra mắt.', 'https://picsum.photos/seed/phone/600/400', '21/09/2026 • 19:59'),
    (3, '"AI giống trợ giảng hơn là người làm bài hộ": Sinh viên dùng AI thế nào sau Thông tư 49?', 'AI', 'Sinh viên tận dụng AI để tóm tắt tài liệu, giải thích khái niệm khó và gợi ý ý tưởng nghiên cứu.', 'Sinh viên tận dụng AI để tóm tắt tài liệu, giải thích khái niệm khó và gợi ý ý tưởng nghiên cứu.', 'https://picsum.photos/seed/ai/600/400', '21/09/2026 • 19:35'),
    (4, '5 robot hút bụi cho 5 nhu cầu dọn dẹp khác nhau tại Better Choice Awards 2026', 'Gia dụng', 'Từ nhà có thú cưng đến căn hộ nhiều đồ đạc, mỗi nhu cầu đều có một tiêu chí chọn robot phù hợp.', 'Từ nhà có thú cưng đến căn hộ nhiều đồ đạc, mỗi nhu cầu đều có một tiêu chí chọn robot phù hợp.', 'https://picsum.photos/seed/robot/600/400', '21/09/2026 • 15:00'),
    (5, 'Laptop ngày càng mạnh, nhưng người trẻ lại cần mang theo ít hơn', 'Tin ICT', 'Ứng dụng web, lưu trữ đám mây và thiết kế nhẹ hơn đang thay đổi cách người dùng mang theo công nghệ.', 'Ứng dụng web, lưu trữ đám mây và thiết kế nhẹ hơn đang thay đổi cách người dùng mang theo công nghệ.', 'https://picsum.photos/seed/laptop/600/400', '21/09/2026 • 19:30'),
    (10, 'OpenAI và Google mở rộng mô hình suy luận đa tác vụ cho doanh nghiệp', 'AI', 'Các mô hình AI mới tập trung xử lý công việc nhiều bước và kiểm tra logic trước khi đưa ra kết quả.', 'Các mô hình AI mới tập trung xử lý công việc nhiều bước và kiểm tra logic trước khi đưa ra kết quả.', 'https://picsum.photos/seed/ai2026/600/400', '30/09/2026 • 08:30'),
    (11, 'Apple thử nghiệm chip 2nm, đặt mục tiêu tối ưu năng lượng 40%', 'Mobile', 'Chip thế hệ mới được kỳ vọng tăng hiệu quả năng lượng và đưa thêm khả năng xử lý AI lên thiết bị.', 'Chip thế hệ mới được kỳ vọng tăng hiệu quả năng lượng và đưa thêm khả năng xử lý AI lên thiết bị.', 'https://picsum.photos/seed/mobilechip/600/400', '30/09/2026 • 09:15'),
    (12, 'Đà Nẵng khánh thành trung tâm nghiên cứu và kiểm thử vi mạch', 'Tin ICT', 'Cơ sở mới tập trung đào tạo kỹ sư và phát triển năng lực nghiên cứu, kiểm chuẩn bán dẫn trong nước.', 'Cơ sở mới tập trung đào tạo kỹ sư và phát triển năng lực nghiên cứu, kiểm chuẩn bán dẫn trong nước.', 'https://picsum.photos/seed/danangchip/600/400', '30/09/2026 • 10:00'),
    (13, 'Starlink phủ sóng trực tiếp smartphone với băng thông 150 Mbps', 'Internet', 'Kết nối vệ tinh tới điện thoại phổ thông mở rộng lựa chọn truy cập mạng ở các khu vực xa xôi.', 'Kết nối vệ tinh tới điện thoại phổ thông mở rộng lựa chọn truy cập mạng ở các khu vực xa xôi.', 'https://picsum.photos/seed/starlink2026/600/400', '30/09/2026 • 10:45'),
    (15, 'Máy tính lượng tử 5.000 qubit giải mã chuỗi protein kháng ung thư', 'Khám phá', 'Khả năng mô phỏng phân tử nhanh hơn có thể mở ra hướng nghiên cứu mới cho các hợp chất điều trị.', 'Khả năng mô phỏng phân tử nhanh hơn có thể mở ra hướng nghiên cứu mới cho các hợp chất điều trị.', 'https://picsum.photos/seed/quantum2026/600/400', '30/09/2026 • 11:20');
