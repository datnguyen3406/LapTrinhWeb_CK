CREATE TABLE IF NOT EXISTS articles (
    id INTEGER PRIMARY KEY,
    author_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    category TEXT NOT NULL,
    excerpt TEXT NOT NULL,
    content TEXT NOT NULL,
    image_url TEXT NOT NULL,
    published_at TEXT NOT NULL,
    is_pinned INTEGER NOT NULL DEFAULT 0,
    is_locked INTEGER NOT NULL DEFAULT 0,
    lock_password_hash TEXT,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    avatar_url TEXT NOT NULL DEFAULT '',
    email_verified_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    theme TEXT NOT NULL DEFAULT 'light' CHECK (theme IN ('light', 'dark')),
    font_size INTEGER NOT NULL DEFAULT 16 CHECK (font_size BETWEEN 12 AND 24),
    note_color TEXT NOT NULL DEFAULT '#fff3b0'
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS jobs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    payload TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'done', 'failed')),
    attempts INTEGER NOT NULL DEFAULT 0,
    available_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at TEXT,
    last_error TEXT
);

CREATE INDEX IF NOT EXISTS jobs_fifo_idx ON jobs(status, available_at, id);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    event TEXT NOT NULL,
    payload TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at TEXT
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
