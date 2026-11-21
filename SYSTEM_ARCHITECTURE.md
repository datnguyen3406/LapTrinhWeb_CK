# KIẾN TRÚC HỆ THỐNG GENK MAGAZINE (SYSTEM ARCHITECTURE)

## 1. TỔNG QUAN HỆ THỐNG

**Genk Magazine** là nền tảng báo điện tử hiện đại, ứng dụng kiến trúc lai (Hybrid Persistence Architecture) kết hợp giữa CSDL quan hệ **SQLite** và CSDL tài liệu **MongoDB**, kết hợp Progressive Web App (PWA) để tối ưu hóa hiệu năng đọc báo offline, độ tin cậy và khả năng mở rộng.

---

## 2. KIẾN TRÚC 6 TẦNG (6-TIER ARCHITECTURE)

Hệ thống được tổ chức phân tách trách nhiệm nghiêm ngặt theo 6 tầng:

```mermaid
flowchart TD
    subgraph Tier1["Tầng 1: Presentation & Client"]
        ClientUI["SPA & PWA Client (Vanilla JS, HTML5)"]
        CSSModules["Unified Responsive CSS (style.css)"]
        SW["Service Worker (Cache Storage genk-shell)"]
    end

    subgraph Tier2["Tầng 2: Controller & Routing"]
        Kernel["Kernel Dispatcher (backend/API/Kernel.php)"]
        HttpReq["Request / Response / ApiException"]
    end

    subgraph Tier3["Tầng 3: Business Logic Services"]
        AuthSvc["AuthService"]
        AccountSvc["AccountService"]
        ReaderSvc["ReaderService"]
        ArticleSvc["ArticleService (Saga Coordinator)"]
        CategorySvc["CategoryService"]
        AdminSvc["AdminService"]
        IntegritySvc["ArticleIntegrityService"]
        AuditLogSvc["AuditLogService"]
    end

    subgraph Tier4["Tầng 4: Cross-Cutting & Security"]
        AuthMid["AuthMiddleware (Session Validation, Version Check, Account Lock)"]
        CSRF["CSRF Protection (HMAC / Double Submit Token)"]
        Sanitizer["Audit Sanitizer (PII / Secret Redaction)"]
    end

    subgraph Tier5["Tầng 5: Data Access & Infrastructure"]
        SQLiteRepo["SQLite Repositories (Articles, Categories, Users, Reader)"]
        MongoRepo["Mongo Content Repository (MongoContentRepository)"]
        SQLiteDB[(SQLite Database - news_reader.sqlite)]
        MongoDB[(MongoDB - news_content & news_audit)]
    end

    subgraph Tier6["Tầng 6: Quality Assurance & Testing"]
        TestRunner["Full Automated Test Suite (backend/tests/run_all_tests.php)"]
        Suites["SQL, MGO, INTS, ART, AUTH, USER, FAV, CAT, LOG, API, SEC, XSS"]
    end

    ClientUI --> Kernel
    SW --> ClientUI
    Kernel --> AuthMid
    AuthMid --> CSRF
    CSRF --> Tier3
    Tier3 --> Tier5
    SQLiteRepo --> SQLiteDB
    MongoRepo --> MongoDB
    TestRunner -.-> Tier3
    TestRunner -.-> Tier5
```

---

## 3. SƠ ĐỒ USE CASE HỆ THỐNG

```mermaid
flowchart LR
    subgraph Actors
        Guest((Khách))
        Reader((Độc giả))
        Admin((Quản trị viên))
    end

    subgraph GuestUseCases["Use Case Khách"]
        UC1["Xem danh sách bài viết"]
        UC2["Tìm kiếm & lọc chuyên mục"]
        UC3["Đọc chi tiết bài báo"]
        UC4["Đăng ký tài khoản (Nhận Recovery Code)"]
        UC5["Đăng nhập"]
        UC6["Khôi phục mật khẩu (Web-only)"]
    end

    subgraph ReaderUseCases["Use Case Độc giả"]
        UC7["Đánh dấu bài viết yêu thích (Bookmarks)"]
        UC8["Xem lịch sử đọc bài (Reading History)"]
        UC9["Tùy chỉnh giao diện (Theme, Font Size)"]
        UC10["Đổi mật khẩu & Tạo lại Recovery Code dự phòng"]
        UC11["Cập nhật Profile & Upload Avatar"]
        UC12["Nghe đọc bài báo (Text-to-Speech)"]
    end

    subgraph AdminUseCases["Use Case Quản trị viên"]
        UC13["Dashboard thống kê tổng hợp"]
        UC14["Soạn thảo bài viết (Saga Multi-Phase)"]
        UC15["Quản lý vòng đời bài viết (Draft/Publish/Hide/Delete)"]
        UC16["Quản lý Danh mục (Chặn xóa khi có bài viết)"]
        UC17["Quản lý Nguồn tin (Sources)"]
        UC18["Quản lý Tài khoản (Khóa/Hạ quyền, chặn tự sát Admin)"]
        UC19["Kiểm tra tính toàn vẹn (Integrity Check)"]
        UC20["Đối soát 2 CSDL (Reconciliation Report)"]
        UC21["Xem & Lọc Nhật ký hoạt động (Audit Logs)"]
    end

    Guest --> UC1
    Guest --> UC2
    Guest --> UC3
    Guest --> UC4
    Guest --> UC5
    Guest --> UC6

    Reader --> UC7
    Reader --> UC8
    Reader --> UC9
    Reader --> UC10
    Reader --> UC11
    Reader --> UC12

    Admin --> UC13
    Admin --> UC14
    Admin --> UC15
    Admin --> UC16
    Admin --> UC17
    Admin --> UC18
    Admin --> UC19
    Admin --> UC20
    Admin --> UC21
```

---

## 4. LUỒNG ĐỒNG BỘ DỮ LIỆU SAGA 2 GIAI ĐOẠN (TWO-PHASE ARTICLE SAGA)

Nhằm giải quyết triệt để rủi ro dữ liệu mồ côi và bất nhất giữa 2 CSDL khác biệt (SQLite và MongoDB), quy trình tạo/xuất bản bài viết được thực thi theo mô hình Saga Orchestration:

```mermaid
sequenceDiagram
    autonumber
    actor Admin as Quản trị viên
    participant API as Kernel / ArticleService
    participant SQLite as SQLite (ArticlesRepository)
    participant Mongo as MongoDB (MongoContentRepository)
    participant Integrity as ArticleIntegrityService
    participant Audit as AuditLogService

    Admin->>API: POST /api/admin/articles (title, slug, blocks, publish)
    Note over API,SQLite: GIAI ĐOẠN 1: Tạo bản ghi siêu dữ liệu chờ
    API->>SQLite: create() -> status: DRAFT, sync_status: PENDING, revision: 1
    SQLite-->>API: Trả về article_id (UUID)

    Note over API,Mongo: GIAI ĐOẠN 2: Lưu trữ khối nội dung sang MongoDB
    API->>Mongo: saveContent(article_id, blocks, plainText)
    alt Ghi MongoDB Thất bại (Lỗi mạng / Timeout)
        Mongo--xAPI: Lỗi kết nối MongoDB
        API->>SQLite: setStatus(article_id, 'DRAFT', 'FAILED')
        API->>Audit: log(article.create_failed)
        API-->>Admin: 500 CONTENT_SYNC_FAILED (Session Admin an toàn)
    else Ghi MongoDB Thành công
        Mongo-->>API: Xác nhận lưu trữ _id = article_id

        Note over API,Integrity: GIAI ĐOẠN 3: Kiểm định tính toàn vẹn Cross-DB
        API->>Integrity: verify(article_id)
        Integrity->>SQLite: findById(article_id)
        Integrity->>Mongo: getContent(article_id)
        Integrity-->>API: PASS (SQLite OK, Mongo Blocks OK)

        Note over API,SQLite: GIAI ĐOẠN 4: Chuyển trạng thái sẵn sàng & Xuất bản
        API->>SQLite: setStatus(article_id, 'PUBLISHED', 'READY')
        API->>Audit: log(article.create, sanitized_details)
        API-->>Admin: 201 Created (Article Joined Payload)
    end
```

---

## 5. CƠ CHẾ KHÓA LẠC QUAN (OPTIMISTIC CONCURRENCY CONTROL)

Để ngăn chặn hai Quản trị viên cùng mở màn hình chỉnh sửa bài báo và ghi đè nội dung của nhau:
1. Mỗi bài viết trong SQLite sở hữu thuộc tính số nguyên `revision` (khởi đầu là 1).
2. Khi Quản trị viên cập nhật, request mang theo `expected_revision`.
3. Câu lệnh SQL thực thi:
   ```sql
   UPDATE articles 
   SET title = ?, revision = revision + 1, updated_at = ...
   WHERE article_id = ? AND revision = ?;
   ```
4. Nếu số dòng thay đổi bằng 0 và bài viết tồn tại `revision` khác:
   Hệ thống từ chối cập nhật và trả về ngay **HTTP 409 Conflict** (`REVISION_CONFLICT`), yêu cầu Quản trị viên tải lại dữ liệu mới nhất trước khi chỉnh sửa.
