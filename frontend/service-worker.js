const SHELL_CACHE = "genk-shell-v17";
const API_CACHE = "genk-private-v12";
const APP_ROOT = new URL("./", self.location.href);
const SHELL_FILES = [
  "pages/index.html",
  "pages/Auth.html",
  "pages/Register.html",
  "pages/Read.html",
  "pages/ForgetPassword.html",
  "pages/User/Reader.html",
  "pages/User/Favourite.html",
  "pages/User/Profile.html",
  "pages/User/Appearance.html",
  "pages/User/ChangePassword.html",
  "pages/Admin/Dashboard.html",
  "pages/Admin/Article.html",
  "pages/Admin/SourceNews.html",
  "pages/Admin/ManageUser.html",
  "pages/Admin/History.html",
  "assets/css/style.css",
  "assets/js/api.js",
  "assets/js/index.js",
  "assets/js/article_content.js",
  "assets/js/article_detail.js",
  "assets/js/auth.js",
  "assets/js/register.js",
  "assets/js/recover_code.js",
  "assets/js/reader.js",
  "assets/js/favorites.js",
  "assets/js/profile.js",
  "assets/js/appearance.js",
  "assets/js/change-password.js",
  "assets/js/admin_dashboard.js",
  "assets/js/admin_articles.js",
  "assets/js/admin_sources.js",
  "assets/js/admin_users.js",
  "assets/js/admin_history.js",
  "assets/images/default-avatar.svg",
  "assets/images/app-icon.svg",
  "manifest.webmanifest",
  "offline.html",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then((cache) => cache.addAll(
        SHELL_FILES.map((file) => {
          const url = new URL(file, APP_ROOT);
          url.searchParams.set("_sw", SHELL_CACHE);
          return url;
        }),
      ))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((key) => key.startsWith("genk-")
          && ![SHELL_CACHE, API_CACHE].includes(key))
          .map((key) => caches.delete(key)),
      ))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("message", (event) => {
  if (event.data?.type === "CLEAR_PRIVATE_CACHE") {
    event.waitUntil(caches.delete(API_CACHE));
  }
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const apiRoot = new URL("api/", APP_ROOT).pathname;
  if (request.method === "GET" && url.pathname.startsWith(apiRoot)) {
    const endpoint = url.pathname.slice(apiRoot.length);
    if (endpoint === "auth/me"
      || endpoint === "articles"
      || endpoint.startsWith("articles/")
      || endpoint === "me/favorites") {
      event.respondWith(networkFirstApi(request));
      return;
    }
  }

  if (request.method !== "GET") return;

  if (request.mode === "navigate") {
    event.respondWith(networkFirstPage(request));
    return;
  }

  const assetPath = new URL("assets/", APP_ROOT).pathname;
  const manifestPath = new URL("manifest.webmanifest", APP_ROOT).pathname;
  if (url.pathname.startsWith(assetPath) || url.pathname === manifestPath) {
    event.respondWith(cacheFirst(request));
  }
});

async function networkFirstApi(request) {
  const cache = await caches.open(API_CACHE);
  try {
    const response = await fetch(request);
    if (response.ok && response.headers.get("content-type")?.includes("application/json")) {
      await cache.put(request, response.clone());
    }
    return response;
  } catch {
    const cached = await cache.match(request);
    if (cached) return cached;
    return new Response(
      JSON.stringify({ error: { message: "Nội dung này chưa được lưu để đọc ngoại tuyến." } }),
      { status: 503, headers: { "Content-Type": "application/json" } },
    );
  }
}

async function networkFirstPage(request) {
  const cache = await caches.open(SHELL_CACHE);
  try {
    const response = await fetch(request);
    if (response.ok) await cache.put(request, response.clone());
    return response;
  } catch {
    return (await cache.match(request, { ignoreSearch: true }))
      || (await cache.match(new URL("offline.html", APP_ROOT), { ignoreSearch: true }));
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(SHELL_CACHE);
  const cached = await cache.match(request, { ignoreSearch: true });
  if (cached) return cached;
  const response = await fetch(request);
  if (response.ok) await cache.put(request, response.clone());
  return response;
}
