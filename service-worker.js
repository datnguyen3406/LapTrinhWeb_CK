const CACHE_NAME = "genk-shell-v3";
const SHELL = [
    "/Assets/CSS/style.css",
    "/Assets/CSS/ReadForm.css",
    "/Assets/JS/feature.js",
    "/Assets/JS/app.js",
    "/manifest.webmanifest",
    "/Assets/Images/app-icon.svg",
    "/offline.html"
];

self.addEventListener("install", event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(SHELL)));
    self.skipWaiting();
});

self.addEventListener("activate", event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key.startsWith("genk-shell-") && key !== CACHE_NAME)
                .map(key => caches.delete(key))
        ))
    );
    self.clients.claim();
});

self.addEventListener("fetch", event => {
    const request = event.request;
    const url = new URL(request.url);
    if (request.method !== "GET" || url.origin !== self.location.origin || url.pathname.startsWith("/api/")) {
        return;
    }
    if (request.mode === "navigate") {
        event.respondWith(fetch(request).then(response => {
            if (response.ok && url.pathname === "/index.php" && !response.headers.get("Cache-Control")?.includes("no-store")) {
                const copy = response.clone();
                caches.open(CACHE_NAME).then(cache => cache.put(request, copy));
            }
            return response;
        }).catch(async () => await caches.match(request) || await caches.match("/index.php") || await caches.match("/offline.html")));
        return;
    }
    event.respondWith(caches.match(request).then(cached => cached || fetch(request)));
});

self.addEventListener("sync", event => {
    if (event.tag === "genk-sync-drafts") {
        event.waitUntil(self.clients.matchAll({ type: "window", includeUncontrolled: true })
            .then(clients => clients.forEach(client => client.postMessage({ type: "SYNC_DRAFTS" }))));
    }
});
