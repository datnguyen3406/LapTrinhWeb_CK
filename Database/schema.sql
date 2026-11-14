CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
    password_hash TEXT NOT NULL,
    display_name TEXT NOT NULL,
    avatar_url TEXT NOT NULL DEFAULT '',
    role TEXT NOT NULL DEFAULT 'user',
    is_active INTEGER NOT NULL DEFAULT 0,
    activation_token TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    theme TEXT NOT NULL DEFAULT 'light' CHECK (theme IN ('light', 'dark')),
    font_size INTEGER NOT NULL DEFAULT 16 CHECK (font_size BETWEEN 12 AND 24),
    note_color TEXT NOT NULL DEFAULT '#ffffff'
);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    user_id INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    author_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    title TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'Tin tức',
    excerpt TEXT,
    content TEXT NOT NULL,
    image_url TEXT NOT NULL DEFAULT '',
    color TEXT NOT NULL DEFAULT '#ffffff',
    is_pinned INTEGER NOT NULL DEFAULT 0,
    pinned_at TEXT,
    is_locked INTEGER NOT NULL DEFAULT 0,
    lock_password_hash TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS labels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, name)
);

CREATE TABLE IF NOT EXISTS article_labels (
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    label_id INTEGER NOT NULL REFERENCES labels(id) ON DELETE CASCADE,
    PRIMARY KEY (article_id, label_id)
);

CREATE TABLE IF NOT EXISTS article_shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    shared_with_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission TEXT NOT NULL DEFAULT 'read' CHECK (permission IN ('read', 'edit')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(article_id, shared_with_user_id)
);

CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    file_type TEXT NOT NULL,
    file_name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Seed tài khoản mẫu nếu chưa có
INSERT OR IGNORE INTO users (id, email, password_hash, display_name, role, is_active)
VALUES (1, 'admin@example.com', '$2y$10$O4bK4l.f.n4c5GkS0WcZte1s1xTkhyRzE7Z0/6hGv7Z1h1s2v3w4y', 'Ban Biên Tập GenK', 'admin', 1);

INSERT OR IGNORE INTO user_preferences (user_id, theme, font_size, note_color)
VALUES (1, 'light', 16, '#ffffff');

-- Dữ liệu bài viết mẫu
INSERT OR IGNORE INTO articles (id, author_id, title, category, excerpt, content, image_url, is_pinned, pinned_at) VALUES
    (1, 1, 'Bị Mỹ cấm xuất khẩu, 4,6 tỷ USD chip NVIDIA vẫn được đưa vào Trung Quốc như thế nào?', 'Tin ICT', 'Một câu hỏi lớn về chuỗi cung ứng công nghệ toàn cầu khi các biện pháp cấm vận khắt khe không thể hoàn toàn ngăn chặn dòng chảy của vi mạch AI tiên tiến nhất hiện nay.', 'Một câu hỏi lớn về chuỗi cung ứng công nghệ toàn cầu khi các biện pháp cấm vận khắt khe không thể hoàn toàn ngăn chặn dòng chảy của vi mạch AI tiên tiến nhất hiện nay. Các báo cáo phân tích cho thấy việc vận chuyển diễn ra qua nhiều công ty trung gian và các thị trường thứ cấp.', 'https://picsum.photos/seed/tech/800/600', 1, CURRENT_TIMESTAMP),
    (2, 1, 'vivo X500 series chính thức: Nâng cấp camera và pin cực khủng', 'Mobile', 'Cảm biến lớn, khả năng chụp đêm và các tính năng AI mới là điểm nhấn trên dòng điện thoại vừa ra mắt.', 'Cảm biến lớn, khả năng chụp đêm và các tính năng AI mới là điểm nhấn trên dòng điện thoại vừa ra mắt. Máy được trang bị chip thế hệ mới giúp tối ưu hóa thời lượng pin lên đến 2 ngày sử dụng liên tục.', 'https://picsum.photos/seed/phone/600/400', 0, NULL),
    (3, 1, '"AI giống trợ giảng hơn là người làm bài hộ": Sinh viên dùng AI thế nào?', 'AI', 'Sinh viên tận dụng AI để tóm tắt tài liệu, giải thích khái niệm khó và gợi ý ý tưởng nghiên cứu.', 'Sinh viên tận dụng AI để tóm tắt tài liệu, giải thích khái niệm khó và gợi ý ý tưởng nghiên cứu. Thay vì để AI làm thay bài tập, ngày càng nhiều bạn trẻ dùng công cụ để phản biện và rèn luyện tư duy lập luận.', 'https://picsum.photos/seed/ai/600/400', 0, NULL),
    (4, 1, '5 robot hút bụi cho 5 nhu cầu dọn dẹp khác nhau năm 2026', 'Gia dụng', 'Từ nhà có thú cưng đến căn hộ nhiều đồ đạc, mỗi nhu cầu đều có một tiêu chí chọn robot phù hợp.', 'Từ nhà có thú cưng đến căn hộ nhiều đồ đạc, mỗi nhu cầu đều có một tiêu chí chọn robot phù hợp. Khả năng tự giặt sấy giẻ lau và điều hướng bằng camera AI đang trở thành tiêu chuẩn mới.', 'https://picsum.photos/seed/robot/600/400', 0, NULL),
    (5, 1, 'Laptop ngày càng mạnh, nhưng người trẻ lại cần mang theo ít hơn', 'Tin ICT', 'Ứng dụng web, lưu trữ đám mây và thiết kế nhẹ hơn đang thay đổi cách người dùng mang theo công nghệ.', 'Ứng dụng web, lưu trữ đám mây và thiết kế nhẹ hơn đang thay đổi cách người dùng mang theo công nghệ. Các mẫu máy ultrabook pin trâu cùng màn hình OLED đang chiếm lĩnh thị phần học tập và làm việc di động.', 'https://picsum.photos/seed/laptop/600/400', 0, NULL);
