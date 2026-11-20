const SHELL_CACHE = "genk-shell-v6";
const ARTICLE_CACHE = "genk-articles-v6";
const SHELL_FILES = [
  "/HTML/index.html",
  "/HTML/Auth.html",
  "/HTML/Regisger.html",
  "/HTML/Read.html",
  "/HTML/ForgetPassword.html",
  "/HTML/User/Reader.html",
  "/HTML/User/Favourite.html",
  "/HTML/User/Profie.html",
  "/HTML/User/Appearance.html",
  "/HTML/User/ChangePassword.html",
  "/HTML/Admin/Dashboard.html",
  "/HTML/Admin/Article.html",
  "/HTML/Admin/SourceNews.html",
  "/HTML/Admin/ManageUser.html",
  "/HTML/Admin/History.html",
  "/CSS/style.css",
  "/JavaScript/api.js",
  "/JavaScript/index.js",
  "/JavaScript/article_detail.js",
  "/JavaScript/auth.js",
  "/JavaScript/register.js",
  "/JavaScript/recover_code.js",
  "/JavaScript/reader.js",
  "/JavaScript/favorites.js",
  "/JavaScript/profile.js",
  "/JavaScript/appearance.js",
  "/JavaScript/change-password.js",
  "/JavaScript/admin_dashboard.js",
  "/JavaScript/admin_articles.js",
  "/JavaScript/admin_sources.js",
  "/JavaScript/admin_users.js",
  "/JavaScript/admin_history.js",
  "/assets/default-avatar.svg",
  "/assets/app-icon.svg",
  "/manifest.webmanifest",
  "/offline.html",
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(SHELL_CACHE)
      .then((cache) => cache.addAll(
        SHELL_FILES.map((file) => `${file}?_sw=${SHELL_CACHE}`),
      ))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(
        keys.filter((key) => key.startsWith("genk-")
          && ![SHELL_CACHE, ARTICLE_CACHE].includes(key))
          .map((key) => caches.delete(key)),
      ))
      .then(() => self.clients.claim()),
  );
});

self.addEventListener("fetch", (event) => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== "GET" || url.origin !== self.location.origin) return;

  if (/\/api\/articles\/[^/]+$/.test(url.pathname)) {
    event.respondWith(networkFirstArticle(request));
    return;
  }

  if (request.mode === "navigate") {
    event.respondWith(networkFirstPage(request));
    return;
  }

  if (url.pathname.startsWith("/CSS/")
      || url.pathname.startsWith("/JavaScript/")
      || url.pathname.startsWith("/assets/")
      || url.pathname === "/manifest.webmanifest") {
    event.respondWith(cacheFirst(request));
  }
});

async function networkFirstArticle(request) {
  const cache = await caches.open(ARTICLE_CACHE);
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
      JSON.stringify({ error: { message: "Bài báo chưa được lưu để đọc ngoại tuyến." } }),
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
      || (await cache.match("/offline.html", { ignoreSearch: true }));
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
