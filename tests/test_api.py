import base64
import http.cookiejar
import json
import sys
import time
import urllib.error
import urllib.parse
import urllib.request


BASE = "http://localhost:8000"
API = BASE + "/api"
reader = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
)
admin = urllib.request.build_opener(
    urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar())
)
results = []


def request(opener, method, path, body=None, csrf=None, multipart=None):
    url = API + path
    headers = {"Accept": "application/json"}
    data = None
    if multipart:
        boundary = "----CopilotTestBoundary"
        chunks = []
        name, filename, content, mime = multipart
        chunks.append((
            f"--{boundary}\r\n"
            f'Content-Disposition: form-data; name="{name}"; filename="{filename}"\r\n'
            f"Content-Type: {mime}\r\n\r\n"
        ).encode())
        chunks.append(content)
        chunks.append(f"\r\n--{boundary}--\r\n".encode())
        data = b"".join(chunks)
        headers["Content-Type"] = f"multipart/form-data; boundary={boundary}"
    elif body is not None:
        data = json.dumps(body).encode()
        headers["Content-Type"] = "application/json"
    if csrf:
        headers["X-CSRF-Token"] = csrf
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with opener.open(req, timeout=15) as response:
            status = response.status
            raw = response.read()
    except urllib.error.HTTPError as error:
        status = error.code
        raw = error.read()
    payload = json.loads(raw.decode() or "null")
    return status, payload


def csrf(opener):
    _, payload = request(opener, "GET", "/auth/csrf")
    return payload["data"]["csrfToken"]


def check(name, condition, detail=""):
    status = "PASS" if condition else "FAIL"
    results.append((name, status, detail))
    print(f"[{status}] {name}" + (f" - {detail}" if detail else ""))


def data(payload):
    return payload.get("data", {})


try:
    status, payload = request(reader, "GET", "/articles")
    check("TC-001 Danh sách bài viết công khai", status == 200 and isinstance(data(payload).get("items"), list),
          f"HTTP {status}")
    check("TC-002 Danh mục công khai", data(request(reader, "GET", "/categories")[1]).get("items") is not None)
    check("TC-003 Nguồn tin công khai", data(request(reader, "GET", "/sources")[1]).get("items") is not None)

    status, payload = request(reader, "POST", "/auth/login",
                              {"username": "khong-ton-tai", "password": "Sai123456"}, csrf(reader))
    check("TC-004 Đăng nhập sai trả lỗi", status == 401)

    username = f"testreader{int(time.time()) % 1000000}"
    password = "Test@123456"
    token = csrf(reader)
    status, payload = request(reader, "POST", "/auth/register", {
        "username": username,
        "displayName": "Reader Kiểm Thử",
        "password": password,
    }, token)
    register_data = data(payload)
    reader_id = register_data.get("user", {}).get("id")
    recovery_code = register_data.get("recoveryCode")
    check("TC-005 Đăng ký Reader", status == 201 and bool(reader_id) and bool(recovery_code),
          f"HTTP {status}, id={reader_id}")

    status, payload = request(reader, "POST", "/auth/register", {
        "username": username,
        "displayName": "Trùng tên",
        "password": password,
    }, csrf(reader))
    check("TC-006 Chặn trùng username", status == 409)

    token = csrf(reader)
    status, payload = request(reader, "PATCH", "/me", {"displayName": "Reader Đã Cập Nhật"}, token)
    check("TC-007 Cập nhật tên hiển thị", status == 200 and data(payload)["user"]["displayName"] == "Reader Đã Cập Nhật")

    png = base64.b64decode(
        "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII="
    )
    status, payload = request(reader, "POST", "/me/avatar", csrf=csrf(reader),
                             multipart=("avatar", "test-avatar.png", png, "image/png"))
    check("TC-008 Upload avatar PNG", status == 200 and bool(data(payload).get("avatarUrl")))

    status, payload = request(reader, "PATCH", "/me/preferences", {"fontSize": 18, "theme": "dark"}, csrf(reader))
    check("TC-009 Lưu tùy chọn giao diện", status == 200)

    status, payload = request(reader, "POST", "/me/password", {
        "currentPassword": password,
        "newPassword": "Reader@654321",
    }, csrf(reader))
    check("TC-010 Đổi mật khẩu", status == 200)

    status, payload = request(reader, "POST", "/me/recovery-code", {}, csrf(reader))
    new_recovery = data(payload).get("recoveryCode")
    check("TC-011 Tạo mã khôi phục mới", status == 200 and bool(new_recovery))

    status, payload = request(reader, "POST", "/auth/password-reset/confirm", {
        "username": username,
        "recoveryKey": "ma-khoi-phuc-sai",
        "password": password,
    }, csrf(reader))
    check("TC-011b Từ chối mã khôi phục sai", status == 400)

    status, payload = request(reader, "POST", "/auth/password-reset/confirm", {
        "username": username.upper(),
        "recoveryKey": new_recovery,
        "password": password,
    }, csrf(reader))
    check("TC-012 Khôi phục mật khẩu bằng mã", status == 200)

    token = csrf(reader)
    status, payload = request(reader, "POST", "/auth/login", {
        "username": username,
        "password": password,
    }, token)
    check("TC-012b Đăng nhập lại sau khôi phục mật khẩu", status == 200)

    status, payload = request(reader, "POST", "/me/favorite-folders",
                              {"name": "Kịch bản test"}, csrf(reader))
    check("TC-013 Không còn API tạo thư mục yêu thích", status == 404)

    _, public_payload = request(reader, "GET", "/articles")
    articles = data(public_payload).get("items", [])
    check("TC-014 Có bài viết để test yêu thích", bool(articles))
    article_id = articles[0]["id"] if articles else None

    if article_id:
        status, payload = request(reader, "POST", "/me/favorites", {
            "articleId": article_id,
        }, csrf(reader))
        check("TC-015 Thêm bài vào yêu thích", status == 201)
        status, payload = request(reader, "GET", "/me/favorites")
        favorite_items = data(payload).get("items", [])
        check("TC-016 Đọc danh sách yêu thích",
              status == 200 and any(item.get("article", {}).get("id") == article_id for item in favorite_items))

        status, payload = request(reader, "POST", "/me/reading-history", {"articleId": article_id}, csrf(reader))
        check("TC-017 Ghi lịch sử đọc", status == 201)
        status, payload = request(reader, "GET", "/me/reading-history")
        check("TC-018 Đọc lịch sử đọc", status == 200 and bool(data(payload).get("items")))

        status, payload = request(reader, "DELETE", f"/me/favorites/{article_id}", csrf=csrf(reader))
        check("TC-019 Bỏ yêu thích", status == 204)

    status, payload = request(reader, "DELETE", "/me/reading-history", csrf=csrf(reader))
    check("TC-020 Xóa lịch sử đọc", status == 204)

    status, payload = request(reader, "POST", "/me/categories", {"name": "Kịch bản cá nhân"}, csrf(reader))
    check("TC-021 Tạo phân loại cá nhân", status == 201)

    token = csrf(admin)
    status, payload = request(admin, "POST", "/auth/login", {
        "username": "admin",
        "password": "Admin@123456",
    }, token)
    check("TC-022 Đăng nhập Admin", status == 200 and data(payload).get("user", {}).get("role") == "admin")

    reader_dashboard_status = request(reader, "GET", "/admin/dashboard")[0]
    check("TC-023 Reader không truy cập dashboard",
          reader_dashboard_status in (401, 403),
          f"HTTP {reader_dashboard_status}")

    status, payload = request(admin, "GET", "/admin/dashboard")
    check("TC-024 Tải dashboard Admin", status == 200)

    status, payload = request(admin, "GET", "/admin/users")
    check("TC-025 Danh sách tài khoản", status == 200 and isinstance(data(payload).get("items"), list))

    if reader_id:
        status, payload = request(admin, "PATCH", f"/admin/users/{reader_id}",
                                  {"role": "admin"}, csrf(admin))
        check("TC-026 Admin thay đổi role người dùng", status == 200)
        status, payload = request(admin, "PATCH", f"/admin/users/{reader_id}",
                                  {"role": "reader"}, csrf(admin))
        check("TC-027a Hạ quyền Reader trước khi khóa", status == 200)
        status, payload = request(admin, "PATCH", f"/admin/users/{reader_id}",
                                  {"isLocked": True}, csrf(admin))
        check("TC-027 Khóa tài khoản Reader", status == 200)
        status, payload = request(admin, "PATCH", f"/admin/users/{reader_id}",
                                  {"isLocked": False}, csrf(admin))
        check("TC-028 Mở khóa tài khoản Reader", status == 200)

    status, payload = request(admin, "POST", "/admin/sources", {
        "name": "Nguồn Test",
        "slug": "nguon-test",
        "websiteUrl": "https://example.com",
    }, csrf(admin))
    source_id = data(payload).get("source", {}).get("id")
    check("TC-029 Tạo nguồn tin", status == 201 and bool(source_id))

    if source_id:
        status, payload = request(admin, "PATCH", f"/admin/sources/{source_id}",
                                  {"name": "Nguồn Test Cập Nhật"}, csrf(admin))
        check("TC-030 Cập nhật nguồn tin", status == 200)
        status, payload = request(admin, "DELETE", f"/admin/sources/{source_id}", csrf=csrf(admin))
        check("TC-031 Xóa mềm nguồn tin", status == 204)

    category_name = f"Danh mục Test {int(time.time()) % 1000000}"
    category_slug = f"danh-muc-test-{int(time.time()) % 1000000}"
    status, payload = request(admin, "POST", "/admin/categories", {
        "name": category_name,
        "slug": category_slug,
    }, csrf(admin))
    category_id = data(payload).get("category", {}).get("id")
    check("TC-032 Tạo danh mục", status == 201 and bool(category_id))

    if category_id:
        status, payload = request(admin, "PATCH", f"/admin/categories/{category_id}",
                                  {"name": category_name + " Cập Nhật"}, csrf(admin))
        check("TC-033 Cập nhật danh mục", status == 200)

    categories_status, categories_payload = request(admin, "GET", "/categories")
    categories = data(categories_payload).get("items", [])
    category_id_for_article = category_id or (categories[0]["id"] if categories else None)
    status, payload = request(admin, "POST", "/admin/articles", {
        "title": "Bài viết kiểm thử tự động",
        "categoryId": category_id_for_article,
        "sourceId": None,
        "summary": "Tóm tắt kiểm thử",
        "content": "Nội dung kiểm thử.",
        "visible": True,
        "coverImageUrl": "https://example.com/cover.jpg",
        "media": [
            {"type": "image", "url": "https://example.com/image.jpg"},
            {"type": "video", "url": "https://example.com/video.mp4"},
        ],
    }, csrf(admin))
    test_article_id = data(payload).get("article", {}).get("id")
    check("TC-034 Tạo bài viết với ảnh/video URL", status == 201 and bool(test_article_id))

    if test_article_id:
        status, payload = request(admin, "PATCH", f"/admin/articles/{test_article_id}",
                                  {"title": "Bài viết kiểm thử đã sửa", "summary": "Tóm tắt đã sửa"},
                                  csrf(admin))
        check("TC-035 Cập nhật bài viết", status == 200)

        status, payload = request(admin, "PATCH", f"/admin/articles/{test_article_id}",
                                  {"visible": False}, csrf(admin))
        check("TC-036 Ẩn bài viết", status == 200 and data(payload)["article"]["visible"] is False)

        status, payload = request(admin, "PATCH", f"/admin/articles/{test_article_id}",
                                  {"visible": True}, csrf(admin))
        check("TC-037 Hiện lại bài viết", status == 200 and data(payload)["article"]["visible"] is True)

        status, payload = request(reader, "GET", f"/articles/{test_article_id}")
        check("TC-038 Reader xem chi tiết bài viết",
              status == 200 and data(payload).get("article", {}).get("thumbnailUrl"))

        status, payload = request(admin, "DELETE", f"/admin/articles/{test_article_id}", csrf=csrf(admin))
        check("TC-039 Xóa bài viết", status == 204)

    if category_id:
        status, payload = request(admin, "DELETE", f"/admin/categories/{category_id}", csrf=csrf(admin))
        check("TC-040 Xóa mềm danh mục", status == 204)

    status, payload = request(admin, "GET", "/admin/activity-logs")
    logs = data(payload).get("items", [])
    check("TC-041 Xem nhật ký hoạt động", status == 200 and isinstance(logs, list))
    check("TC-042 Nhật ký có dữ liệu Reader/Admin",
          any(item.get("action") == "history.read" for item in logs))

    status, payload = request(admin, "GET", "/admin/activity-logs?action=user.update")
    check("TC-043 Lọc nhật ký theo hành động", status == 200)

    status, payload = request(admin, "DELETE", "/admin/activity-logs", csrf=csrf(admin))
    check("TC-044 Xóa toàn bộ nhật ký", status == 204)

    status, payload = request(reader, "POST", "/auth/logout", {}, csrf(reader))
    check("TC-045 Đăng xuất Reader", status == 204)

    status, payload = request(admin, "POST", "/auth/logout", {}, csrf(admin))
    check("TC-046 Đăng xuất Admin", status == 204)

    for path in ["/manifest.webmanifest", "/offline.html", "/service-worker.js"]:
        opener = urllib.request.build_opener()
        try:
            with opener.open(BASE + path, timeout=10) as response:
                check(f"TC-047 PWA tài nguyên {path}", response.status == 200,
                      f"HTTP {response.status}")
        except Exception as error:
            check(f"TC-047 PWA tài nguyên {path}", False, str(error))

except Exception as error:
    print(f"[FAIL] Lỗi không mong đợi: {error}", file=sys.stderr)
    results.append(("Lỗi không mong đợi", "FAIL", str(error)))

failed = [item for item in results if item[1] == "FAIL"]
print(f"\nKết quả: {len(results) - len(failed)}/{len(results)} test đạt")
sys.exit(1 if failed else 0)
