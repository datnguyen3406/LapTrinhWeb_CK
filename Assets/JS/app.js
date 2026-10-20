(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? "";
    const api = async (url, method = "GET", body = undefined) => {
        const response = await fetch(url, {
            method,
            headers: {
                "Accept": "application/json",
                ...(body === undefined ? {} : { "Content-Type": "application/json" }),
                ...(method === "GET" ? {} : { "X-CSRF-Token": csrfToken })
            },
            ...(body === undefined ? {} : { body: JSON.stringify(body) })
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || data.message || "Yêu cầu thất bại.");
        return data;
    };

    const formValues = form => Object.fromEntries(new FormData(form).entries());
    document.querySelectorAll("[data-api-form]").forEach(form => {
        form.addEventListener("submit", async event => {
            event.preventDefault();
            const message = form.querySelector("[data-form-message]");
            try {
                const data = await api(form.dataset.apiForm, form.dataset.method || "POST", formValues(form));
                if (data.csrfToken) {
                    document.querySelector('meta[name="csrf-token"]').content = data.csrfToken;
                }
                if (data.user) {
                    message.textContent = "Thành công. Đang chuyển về trang chủ...";
                    window.location.assign("/index.php");
                } else {
                    message.textContent = data.message || "Đã lưu thay đổi.";
                }
            } catch (error) {
                message.textContent = error.message;
            }
        });
    });

    document.querySelectorAll("[data-article-form]").forEach(form => {
        form.addEventListener("submit", async event => {
            event.preventDefault();
            const message = form.querySelector("[data-form-message]");
            const body = formValues(form);
            const id = form.dataset.articleId;
            try {
                if (id) {
                    await api(`/api/articles/${id}`, "PATCH", body);
                    message.textContent = "Đã lưu bài viết.";
                } else {
                    const result = await api("/api/articles", "POST", body);
                    window.location.assign(`/Modules/Articles/detail.php?id=${result.id}`);
                }
            } catch (error) {
                if (!navigator.onLine || error instanceof TypeError) {
                    try {
                        await saveOfflineDraft({ ...body, id: id || null, savedAt: Date.now() });
                        message.textContent = "Mất kết nối. Bản nháp đã lưu trên thiết bị.";
                    } catch (storageError) {
                        message.textContent = `Không thể lưu ngoại tuyến: ${storageError.message}`;
                    }
                } else {
                    message.textContent = error.message;
                }
            }
        });

        form.querySelector("[data-autosave]")?.addEventListener("click", async () => {
            const message = form.querySelector("[data-form-message]");
            const body = formValues(form);
            try {
                const result = await api(`/api/articles/${form.dataset.articleId}/autosave`, "POST", body);
                message.textContent = `Đã đưa bản nháp vào hàng đợi #${result.job_id}.`;
            } catch (error) {
                if (!navigator.onLine || error instanceof TypeError) {
                    try {
                        await saveOfflineDraft({ ...body, id: form.dataset.articleId, savedAt: Date.now() });
                        message.textContent = "Mất kết nối. Bản nháp đã lưu trên thiết bị.";
                    } catch (storageError) {
                        message.textContent = `Không thể lưu ngoại tuyến: ${storageError.message}`;
                    }
                } else {
                    message.textContent = error.message;
                }
            }
        });
    });

    document.querySelectorAll("[data-pin-article]").forEach(button => {
        button.addEventListener("click", async () => {
            try {
                await api(`/api/articles/${button.dataset.pinArticle}/pin`, "POST", {});
                button.textContent = button.textContent.includes("Bỏ") ? "Ghim bài viết" : "Bỏ ghim";
            } catch (error) {
                window.alert(error.message);
            }
        });
    });

    document.querySelectorAll("[data-lock-article]").forEach(button => {
        button.addEventListener("click", async () => {
            const locked = button.dataset.locked === "1";
            const passwordField = button.parentElement.querySelector("[data-lock-password]");
            const password = locked ? "" : passwordField?.value || "";
            if (!locked && password.length < 8) {
                passwordField?.focus();
                window.alert("Mật khẩu khóa phải có ít nhất 8 ký tự.");
                return;
            }
            try {
                const result = await api(`/api/articles/${button.dataset.lockArticle}/lock`, "POST", { locked: !locked, password: password || "" });
                button.dataset.locked = result.is_locked ? "1" : "0";
                button.textContent = result.is_locked ? "Mở khóa bài viết" : "Khóa bằng mật khẩu";
                if (passwordField) {
                    passwordField.value = "";
                    passwordField.hidden = result.is_locked;
                }
            } catch (error) {
                window.alert(error.message);
            }
        });
    });

    document.querySelectorAll("[data-unlock-article]").forEach(form => {
        form.addEventListener("submit", async event => {
            event.preventDefault();
            const message = form.querySelector("[data-form-message]");
            try {
                await api(`/api/articles/${form.dataset.unlockArticle}/unlock`, "POST", formValues(form));
                window.location.reload();
            } catch (error) {
                message.textContent = error.message;
            }
        });
    });

    document.querySelectorAll("[data-delete-article]").forEach(button => {
        button.addEventListener("click", async () => {
            if (!window.confirm("Bạn có chắc muốn xóa bài viết này?")) return;
            try {
                await api(`/api/articles/${button.dataset.deleteArticle}`, "DELETE");
                button.closest("li")?.remove();
            } catch (error) {
                window.alert(error.message);
            }
        });
    });

    document.querySelector("[data-logout]")?.addEventListener("click", async () => {
        try {
            await api("/api/auth/logout", "POST", {});
            window.location.assign("/index.php");
        } catch (error) {
            window.alert(error.message);
        }
    });

    const searchInput = document.querySelector("[data-live-search]");
    if (searchInput) {
        let searchTimer;
        searchInput.addEventListener("input", () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(async () => {
                const query = searchInput.value.trim();
                if (!query) {
                    window.location.assign("/index.php");
                    return;
                }
                try {
                    const result = await api(`/api/articles?q=${encodeURIComponent(query)}`);
                    const grid = document.querySelector(".grid-layout");
                    const summary = document.querySelector("#search-summary");
                    const featured = document.querySelector("[data-featured]");
                    if (!grid || !summary) return;
                    if (featured) featured.hidden = true;
                    grid.replaceChildren();
                    summary.textContent = `${result.articles.length} kết quả`;
                    for (const article of result.articles) {
                        const card = document.createElement("a");
                        card.className = "card";
                        card.href = `/Modules/Articles/detail.php?id=${article.id}`;
                        const image = document.createElement("img");
                        image.src = article.image_url;
                        image.alt = "";
                        image.loading = "lazy";
                        const content = document.createElement("div");
                        content.className = "card-content";
                        const category = document.createElement("span");
                        category.className = "category-tag";
                        category.textContent = article.category;
                        const title = document.createElement("h2");
                        title.textContent = article.title;
                        const excerpt = document.createElement("p");
                        excerpt.className = "card-excerpt";
                        excerpt.textContent = article.excerpt;
                        content.append(category, title, excerpt);
                        card.append(image, content);
                        grid.append(card);
                    }
                    if (result.articles.length === 0) {
                        const empty = document.createElement("p");
                        empty.textContent = "Không tìm thấy bài viết phù hợp.";
                        grid.append(empty);
                    }
                } catch (error) {
                    const summary = document.querySelector("#search-summary");
                    if (summary) summary.textContent = error.message;
                }
            }, 250);
        });
    }

    const DB_NAME = "genk-offline";
    const openDb = () => new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);
        request.onupgradeneeded = () => request.result.createObjectStore("drafts", { keyPath: "key" });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
    const saveOfflineDraft = async draft => {
        const db = await openDb();
        await new Promise((resolve, reject) => {
            const transaction = db.transaction("drafts", "readwrite");
            transaction.objectStore("drafts").put({ ...draft, key: String(draft.id || "new") });
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error);
        });
        if ("serviceWorker" in navigator && "SyncManager" in window) {
            const registration = await navigator.serviceWorker.ready;
            await registration.sync.register("genk-sync-drafts");
        }
    };
    const deleteOfflineDraft = async key => {
        const db = await openDb();
        await new Promise((resolve, reject) => {
            const transaction = db.transaction("drafts", "readwrite");
            transaction.objectStore("drafts").delete(key);
            transaction.oncomplete = resolve;
            transaction.onerror = () => reject(transaction.error);
        });
    };

    document.querySelectorAll("[data-article-form]").forEach(async form => {
        if (navigator.onLine) return;
        const db = await openDb();
        const key = String(form.dataset.articleId || "new");
        const draft = await new Promise((resolve, reject) => {
            const request = db.transaction("drafts").objectStore("drafts").get(key);
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        if (!draft) return;
        for (const [name, value] of Object.entries(draft)) {
            const field = form.elements.namedItem(name);
            if (field && typeof value === "string") field.value = value;
        }
        form.querySelector("[data-form-message]").textContent = "Đã khôi phục bản nháp ngoại tuyến từ thiết bị.";
    });

    navigator.serviceWorker?.addEventListener("message", event => {
        if (event.data?.type === "SYNC_DRAFTS") {
            window.dispatchEvent(new Event("genk:sync-drafts"));
        }
    });
    let syncingDrafts = false;
    const syncDrafts = async () => {
        if (!navigator.onLine || syncingDrafts) return;
        syncingDrafts = true;
        try {
        const db = await openDb();
        const drafts = await new Promise((resolve, reject) => {
            const request = db.transaction("drafts").objectStore("drafts").getAll();
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
        for (const draft of drafts) {
            try {
                const body = { title: draft.title, content: draft.content, category: draft.category, excerpt: draft.excerpt, image_url: draft.image_url };
                if (draft.id && draft.jobId) {
                    const job = await api(`/api/jobs/${draft.jobId}`);
                    if (job.status === "done") await deleteOfflineDraft(draft.key);
                    else if (job.status === "failed") console.error("Offline autosave job failed:", job.error);
                    else continue;
                } else if (draft.id) {
                    const result = await api(`/api/articles/${draft.id}/autosave`, "POST", body);
                    await saveOfflineDraft({ ...draft, jobId: result.job_id });
                } else {
                    await api("/api/articles", "POST", body);
                    await deleteOfflineDraft(draft.key);
                }
            } catch (error) {
                console.error("Offline draft sync failed:", error);
                break;
            }
        }
        } finally {
            syncingDrafts = false;
        }
    };
    window.addEventListener("online", syncDrafts);
    window.addEventListener("genk:sync-drafts", syncDrafts);
    window.setInterval(syncDrafts, 3000);

    if ("serviceWorker" in navigator && location.protocol !== "file:") {
        navigator.serviceWorker.register("/service-worker.js").catch(error => console.error("PWA registration failed:", error));
    }

    if (Number.isInteger(window.articleRoomId) && window.articleRoomId > 0) {
        api(`/api/ws-token?article_id=${window.articleRoomId}`).then(({ url }) => {
            const socket = new WebSocket(url);
            let sendTimer;
            const editor = document.querySelector("[data-article-form]");
            editor?.addEventListener("input", () => {
                window.clearTimeout(sendTimer);
                sendTimer = window.setTimeout(() => {
                    const fields = formValues(editor);
                    if (socket.readyState === WebSocket.OPEN) {
                        socket.send(JSON.stringify({ type: "article.draft", title: fields.title, content: fields.content }));
                    }
                }, 250);
            });
            socket.addEventListener("message", event => {
                let message;
                try { message = JSON.parse(event.data); } catch { return; }
                if (message.type === "article.draft") {
                    const status = document.querySelector("[data-collaboration-status]");
                    if (status) status.textContent = `Cộng tác viên đang sửa: ${message.title}`;
                    const preview = document.querySelector("[data-live-preview]");
                    if (preview) preview.textContent = `${message.title}\n\n${message.content}`;
                    window.dispatchEvent(new CustomEvent("article:draft", { detail: message }));
                    return;
                }
                if (message.type === "article.updated" && message.article_id === window.articleRoomId) {
                    window.dispatchEvent(new CustomEvent("article:updated", { detail: message }));
                }
            });
            window.addEventListener("beforeunload", () => socket.close());
        }).catch(error => console.info("Realtime collaboration is not configured:", error.message));
    }
})();
