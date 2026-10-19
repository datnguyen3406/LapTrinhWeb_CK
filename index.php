<?php

declare(strict_types=1);

$pageTitle = 'GenK Magazine - Trang chủ';
require __DIR__ . '/Includes/header.php';
?>

    <main class="container">
        <a href="Modules/Articles/detail.php?id=1" class="hero" id="tin-ict">
            <img src="https://picsum.photos/seed/tech/800/600" alt="Chip AI và chuỗi cung ứng bán dẫn" class="hero-img">
            <div class="hero-content">
                <span class="category-tag">Tin ICT</span>
                <h1>Bị Mỹ cấm xuất khẩu, 4,6 tỷ USD chip NVIDIA vẫn được đưa vào Trung Quốc như thế nào?</h1>
                <p class="excerpt">Một câu hỏi lớn về chuỗi cung ứng công nghệ toàn cầu khi các biện pháp cấm vận khắt khe không thể hoàn toàn ngăn chặn dòng chảy của vi mạch AI tiên tiến nhất hiện nay.</p>
                <div class="meta">21/09/2026 • 21:25</div>
            </div>
        </a>

        <section class="story-section" aria-labelledby="latest-news">
            <div class="section-heading">
                <h2 id="latest-news">Tin công nghệ nổi bật</h2>
                <p>Cập nhật những câu chuyện mới nhất về thiết bị, trí tuệ nhân tạo và công nghệ tương lai.</p>
            </div>

            <div class="grid-layout">
                <a href="Modules/Articles/detail.php?id=2" class="card" id="mobile">
                    <img src="https://picsum.photos/seed/phone/600/400" alt="Điện thoại vivo X500 series">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #3b5998;">Mobile</span>
                        <h2>vivo X500 series chính thức: Nâng cấp quá ngon, camera chụp đẹp như máy ảnh</h2>
                        <p class="card-excerpt">Cảm biến lớn, khả năng chụp đêm và các tính năng AI mới là điểm nhấn trên dòng điện thoại vừa ra mắt.</p>
                        <div class="meta">21/09/2026 • 19:59</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=3" class="card" id="ai">
                    <img src="https://picsum.photos/seed/ai/600/400" alt="Sinh viên sử dụng AI để học tập">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #f39c12;">AI</span>
                        <h2>"AI giống trợ giảng hơn là người làm bài hộ": Sinh viên dùng AI thế nào sau Thông tư 49?</h2>
                        <p class="card-excerpt">Sinh viên tận dụng AI để tóm tắt tài liệu, giải thích khái niệm khó và gợi ý ý tưởng nghiên cứu.</p>
                        <div class="meta">21/09/2026 • 19:35</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=4" class="card">
                    <img src="https://picsum.photos/seed/robot/600/400" alt="Robot hút bụi thông minh trong nhà">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #27ae60;">Gia dụng</span>
                        <h2>5 robot hút bụi cho 5 nhu cầu dọn dẹp khác nhau tại Better Choice Awards 2026</h2>
                        <p class="card-excerpt">Từ nhà có thú cưng đến căn hộ nhiều đồ đạc, mỗi nhu cầu đều có một tiêu chí chọn robot phù hợp.</p>
                        <div class="meta">21/09/2026 • 15:00</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=5" class="card">
                    <img src="https://picsum.photos/seed/laptop/600/400" alt="Laptop mỏng nhẹ dành cho người trẻ">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #8e44ad;">Tin ICT</span>
                        <h2>Laptop ngày càng mạnh, nhưng người trẻ lại cần mang theo ít hơn</h2>
                        <p class="card-excerpt">Ứng dụng web, lưu trữ đám mây và thiết kế nhẹ hơn đang thay đổi cách người dùng mang theo công nghệ.</p>
                        <div class="meta">21/09/2026 • 19:30</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=10" class="card">
                    <img src="https://picsum.photos/seed/ai2026/600/400" alt="AI tự động xử lý các tác vụ phức tạp">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #f39c12;">AI</span>
                        <h2>OpenAI và Google mở rộng mô hình suy luận đa tác vụ cho doanh nghiệp</h2>
                        <p class="card-excerpt">Các mô hình AI mới tập trung xử lý công việc nhiều bước và kiểm tra logic trước khi đưa ra kết quả.</p>
                        <div class="meta">30/09/2026 • 08:30</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=11" class="card">
                    <img src="https://picsum.photos/seed/mobilechip/600/400" alt="Vi mạch di động tiến trình 2nm">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #3b5998;">Mobile</span>
                        <h2>Apple thử nghiệm chip 2nm, đặt mục tiêu tối ưu năng lượng 40%</h2>
                        <p class="card-excerpt">Chip thế hệ mới được kỳ vọng tăng hiệu quả năng lượng và đưa thêm khả năng xử lý AI lên thiết bị.</p>
                        <div class="meta">30/09/2026 • 09:15</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=12" class="card">
                    <img src="https://picsum.photos/seed/danangchip/600/400" alt="Trung tâm nghiên cứu và kiểm thử vi mạch tại Đà Nẵng">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #8e44ad;">Tin ICT</span>
                        <h2>Đà Nẵng khánh thành trung tâm nghiên cứu và kiểm thử vi mạch</h2>
                        <p class="card-excerpt">Cơ sở mới tập trung đào tạo kỹ sư và phát triển năng lực nghiên cứu, kiểm chuẩn bán dẫn trong nước.</p>
                        <div class="meta">30/09/2026 • 10:00</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=13" class="card" id="internet">
                    <img src="https://picsum.photos/seed/starlink2026/600/400" alt="Vệ tinh kết nối trực tiếp với điện thoại">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #16a085;">Internet</span>
                        <h2>Starlink phủ sóng trực tiếp smartphone với băng thông 150 Mbps</h2>
                        <p class="card-excerpt">Kết nối vệ tinh tới điện thoại phổ thông mở rộng lựa chọn truy cập mạng ở các khu vực xa xôi.</p>
                        <div class="meta">30/09/2026 • 10:45</div>
                    </div>
                </a>

                <a href="Modules/Articles/detail.php?id=15" class="card" id="kham-pha">
                    <img src="https://picsum.photos/seed/quantum2026/600/400" alt="Mô phỏng phân tử bằng máy tính lượng tử">
                    <div class="card-content">
                        <span class="category-tag" style="background-color: #c0392b;">Khám phá</span>
                        <h2>Máy tính lượng tử 5.000 qubit giải mã chuỗi protein kháng ung thư</h2>
                        <p class="card-excerpt">Khả năng mô phỏng phân tử nhanh hơn có thể mở ra hướng nghiên cứu mới cho các hợp chất điều trị.</p>
                        <div class="meta">30/09/2026 • 11:20</div>
                    </div>
                </a>
            </div>
        </section>
    </main>
<?php require __DIR__ . '/Includes/footer.php'; ?>