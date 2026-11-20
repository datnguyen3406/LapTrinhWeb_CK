(() => {
  const scriptUrl = document.currentScript.src;
  const appRoot = new URL("../../", scriptUrl);
  const apiRoot = new URL("../api/", appRoot);
  let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || "";

  class ApiError extends Error {
    constructor(message, status = 0, code = "") {
      super(message);
      this.name = "ApiError";
      this.status = status;
      this.code = code;
    }
  }

  async function request(path, { method = "GET", body, headers = {} } = {}) {
    const options = {
      method,
      credentials: "include",
      headers: { Accept: "application/json", ...headers },
    };

    if (body instanceof FormData) {
      options.body = body;
    } else if (body !== undefined) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(body);
    }
    if (!["GET", "HEAD", "OPTIONS"].includes(method)) {
      if (!csrfToken) {
        let csrfResponse;
        try {
          csrfResponse = await fetch(new URL("auth/csrf", apiRoot), {
            credentials: "include",
            headers: { Accept: "application/json" },
          });
        } catch {
          showOfflineNotice();
          throw new ApiError("Không thể kết nối máy chủ. Vui lòng thử lại sau.");
        }
        const csrfPayload = await csrfResponse.json().catch(() => null);
        if (!csrfResponse.ok || !csrfPayload?.data?.csrfToken) {
          throw new ApiError(
            csrfPayload?.error?.message || "Không thể khởi tạo phiên bảo mật.",
            csrfResponse.status,
            csrfPayload?.error?.code || "",
          );
        }
        csrfToken = csrfPayload.data.csrfToken;
      }
      options.headers["X-CSRF-Token"] = csrfToken;
    }

    let response;
    try {
      response = await fetch(new URL(path.replace(/^\/+/, ""), apiRoot), options);
    } catch {
      showOfflineNotice();
      throw new ApiError("Không thể kết nối máy chủ. Vui lòng thử lại sau.");
    }

    if (response.status === 204) {
      if (path.split("?")[0] === "auth/logout") clearPrivateOfflineCache();
      return null;
    }

    let payload;
    try {
      payload = await response.json();
    } catch {
      throw new ApiError("Máy chủ trả về dữ liệu không hợp lệ.", response.status);
    }

    if (!response.ok) {
      throw new ApiError(
        payload?.error?.message || "Yêu cầu không thành công.",
        response.status,
        payload?.error?.code || "",
      );
    }

    if (!payload || typeof payload !== "object" || !("data" in payload)) {
      throw new ApiError("Phản hồi máy chủ thiếu trường data.", response.status);
    }
    if (typeof payload.data?.csrfToken === "string") {
      csrfToken = payload.data.csrfToken;
    }
    if (["auth/login", "auth/register", "auth/logout", "auth/password-reset/confirm"]
      .includes(path.split("?")[0])) {
      clearPrivateOfflineCache();
    }
    return payload.data;
  }

  function clearPrivateOfflineCache() {
    if (!("serviceWorker" in navigator)) return;
    const message = { type: "CLEAR_PRIVATE_CACHE" };
    if (navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage(message);
      return;
    }
    navigator.serviceWorker.ready.then((registration) => {
      registration.active?.postMessage(message);
    });
  }

  function showMessage(element, message, type = "error") {
    if (!element) return;
    element.textContent = message;
    element.className = `form-message is-${type}`;
    element.hidden = false;
  }

  function setBusy(form, busy, busyText = "Đang xử lý...") {
    for (const button of form.querySelectorAll('button[type="submit"]')) {
      if (busy) {
        button.dataset.idleText = button.textContent;
        button.textContent = busyText;
      } else if (button.dataset.idleText) {
        button.textContent = button.dataset.idleText;
        delete button.dataset.idleText;
      }
      button.disabled = busy;
    }
  }

  function renderState(container, message, type = "info") {
    if (!container) return;
    if (container.tagName === "TBODY") {
      const row = document.createElement("tr");
      const cell = document.createElement("td");
      cell.colSpan = container.closest("table")?.querySelectorAll("thead th").length || 1;
      cell.textContent = message;
      cell.className = `page-state is-${type}`;
      row.append(cell);
      container.replaceChildren(row);
      return;
    }
    const state = document.createElement("p");
    state.className = `page-state is-${type}`;
    state.setAttribute("role", type === "error" ? "alert" : "status");
    state.textContent = message;
    container.replaceChildren(state);
  }

  function toast(message, type = "info") {
    let region = document.getElementById("toast-region");
    if (!region) {
      region = document.createElement("div");
      region.id = "toast-region";
      region.className = "toast-region";
      region.setAttribute("aria-live", "polite");
      document.body.append(region);
    }
    const item = document.createElement("div");
    item.className = `toast is-${type}`;
    item.textContent = message;
    region.append(item);
    window.setTimeout(() => item.remove(), 4500);
  }

  function showOfflineNotice() {
    if (document.getElementById("offline-banner")) return;
    const banner = document.createElement("aside");
    banner.id = "offline-banner";
    banner.className = "offline-banner";
    banner.textContent = "Đang ngoại tuyến. Chỉ nội dung đã lưu trước đó có thể đọc được.";
    document.querySelector("main")?.before(banner);
  }

  function rootPage(path) {
    return new URL(path, appRoot).href;
  }

  function safeUrl(value) {
    if (typeof value !== "string" || !value.trim()) return "";
    try {
      const url = new URL(value, appRoot);
      return ["http:", "https:"].includes(url.protocol) ? url.href : "";
    } catch {
      return "";
    }
  }

  function redirectToLogin() {
    const next = `${window.location.pathname}${window.location.search}`;
    const login = new URL(rootPage("pages/Auth.html"));
    login.searchParams.set("next", next);
    window.location.assign(login.href);
  }

  async function currentUser() {
    const result = await request("auth/me");
    if (!result?.user) throw new ApiError("Phản hồi hồ sơ người dùng không hợp lệ.");
    return result.user;
  }

  async function requireUser(role) {
    let user;
    try {
      user = await currentUser();
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        redirectToLogin();
        return null;
      }
      renderState(document.querySelector("main"), error.message, "error");
      return null;
    }

    if (role && user.role !== role) {
      toast("Bạn không có quyền truy cập trang này.", "error");
      window.location.replace(rootPage("pages/index.html"));
      return null;
    }
    return user;
  }

  async function initAccountNavigation() {
    const nav = document.getElementById("account-nav");
    if (!nav) return;
    try {
      const user = await currentUser();
      const favorites = document.createElement("a");
      favorites.href = rootPage("pages/User/Favourite.html");
      favorites.textContent = "Yêu thích";
      const reader = document.createElement("a");
      reader.href = rootPage("pages/User/Reader.html");
      reader.textContent = "Không gian đọc";
      const profile = document.createElement("a");
      profile.href = rootPage("pages/User/Profile.html");
      profile.textContent = user.displayName || user.username;
      const logout = document.createElement("button");
      logout.type = "button";
      logout.id = "btn-logout";
      logout.textContent = "Đăng xuất";
      nav.replaceChildren(favorites, reader, profile, logout);

    } catch (error) {
      if (error instanceof ApiError && error.status === 401) return;
      if (error instanceof ApiError && error.status === 0) {
        showOfflineNotice();
      } else {
        toast(error.message, "error");
      }
    }
  }

  document.addEventListener("click", async (event) => {
    const button = event.target.closest("#btn-logout");
    if (!button) return;
    button.disabled = true;
    try {
      await request("auth/logout", { method: "POST", body: {} });
      window.location.assign(rootPage("pages/index.html"));
    } catch (error) {
      toast(error.message, "error");
      button.disabled = false;
    }
  });

  document.addEventListener("submit", (event) => {
    if (event.defaultPrevented || event.target.id !== "header-search-form") return;
    event.preventDefault();
    const query = document.getElementById("header-search-input").value.trim();
    const target = new URL(rootPage("pages/index.html"));
    if (query) target.searchParams.set("q", query);
    window.location.assign(target.href);
  });

  document.addEventListener("click", (event) => {
    if (!window.location.pathname.endsWith("/pages/Read.html")) return;
    const button = event.target.closest("#category-nav [data-category]");
    if (!button) return;
    const target = new URL(rootPage("pages/index.html"));
    target.searchParams.set("category", button.dataset.category);
    window.location.assign(target.href);
  });

  window.App = {
    api: {
      get: (path) => request(path),
      post: (path, body) => request(path, { method: "POST", body }),
      patch: (path, body) => request(path, { method: "PATCH", body }),
      delete: (path) => request(path, { method: "DELETE" }),
      upload: (path, formData) => request(path, { method: "POST", body: formData }),
    },
    auth: { currentUser, requireUser, redirectToLogin },
    ui: { showMessage, setBusy, renderState, toast, showOfflineNotice, initAccountNavigation },
    url: rootPage,
    safeUrl,
    ApiError,
  };

  if ("serviceWorker" in navigator && window.location.protocol !== "file:") {
    navigator.serviceWorker.register(new URL("service-worker.js", appRoot))
      .catch((error) => toast(`Không thể bật PWA: ${error.message}`, "error"));
  }
})();
