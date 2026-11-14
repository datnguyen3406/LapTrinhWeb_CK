(() => {
    // 1. Core API Helper
    const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? "";
    
    const api = async (url, method = "GET", body = undefined, isFormData = false) => {
        const headers = {
            "Accept": "application/json",
            ...(method !== "GET" ? { "X-CSRF-Token": getCsrfToken() } : {})
        };
        if (body !== undefined && !isFormData) {
            headers["Content-Type"] = "application/json";
        }

        const response = await fetch(url, {
            method,
            headers,
            body: isFormData ? body : (body !== undefined ? JSON.stringify(body) : undefined)
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.error || data.message || "Yêu cầu thất bại.");
        }
        return data;
    };

    const formValues = form => Object.fromEntries(new FormData(form).entries());

    // Helper đóng/mở Bootstrap Modal tương thích jQuery hoặc Vanilla JS
    const showModal = (modalId) => {
        if (window.jQuery && typeof window.jQuery.fn.modal === "function") {
            window.jQuery(`#${modalId}`).modal("show");
        } else {
            const el = document.getElementById(modalId);
            if (el) {
                el.style.display = "block";
                el.classList.add("show");
                document.body.classList.add("modal-open");
            }
        }
    };

    const hideModal = (modalId) => {
        if (window.jQuery && typeof window.jQuery.fn.modal === "function") {
            window.jQuery(`#${modalId}`).modal("hide");
        } else {
            const el = document.getElementById(modalId);
            if (el) {
                el.style.display = "none";
                el.classList.remove("show");
                document.body.classList.remove("modal-open");
            }
        }
    };

    // 2. Generic API Form Handler
    document.querySelectorAll("[data-api-form]").forEach(form => {
        form.addEventListener("submit", async event => {
            event.preventDefault();
            const messageEl = form.querySelector("[data-form-message]");
            if (messageEl) {
                messageEl.textContent = "Đang xử lý...";
                messageEl.className = "small text-muted mt-2 d-block";
            }

            try {
                const endpoint = form.dataset.apiForm;
                const method = form.dataset.method || "POST";
                const values = formValues(form);
                const data = await api(endpoint, method, values);

                if (data.csrfToken) {
                    const meta = document.querySelector('meta[name="csrf-token"]');
                    if (meta) meta.content = data.csrfToken;
                }

                if (messageEl) {
                    messageEl.textContent = data.message || "Thành công!";
                    messageEl.className = "small text-success font-weight-bold mt-2 d-block";
                }

                // Xử lý chuyển hướng trang nghiệp vụ mượt mà
                if (endpoint === "/api/auth/register") {
                    // Đăng ký thành công -> Chuyển về trang Đăng nhập chính với email điền sẵn
                    setTimeout(() => {
                        const emailParam = encodeURIComponent(values.email || "");
                        window.location.assign(`/Modules/Auth/login.php?registered=1&email=${emailParam}`);
                    }, 800);
                } else if (endpoint === "/api/auth/login") {
                    // Đăng nhập thành công -> Chuyển vào trang chủ bài viết
                    setTimeout(() => {
                        window.location.assign("/index.php");
                    }, 500);
                } else if (data.user) {
                    window.location.assign("/index.php");
                }
            } catch (error) {
                if (messageEl) {
                    messageEl.textContent = error.message;
                    messageEl.className = "small text-danger font-weight-bold mt-2 d-block";
                } else {
                    window.alert(error.message);
                }
            }
        });
    });

    // 3. Xử lý upload Profile & Avatar
    const profileForm = document.getElementById("profile-form");
    if (profileForm) {
        profileForm.addEventListener("submit", async event => {
            event.preventDefault();
            const messageEl = profileForm.querySelector("[data-profile-message]");
            if (messageEl) {
                messageEl.textContent = "Đang cập nhật hồ sơ...";
                messageEl.className = "small text-muted mt-2 d-block";
            }

            const formData = new FormData(profileForm);
            try {
                const result = await api("/api/user/profile", "POST", formData, true);
                if (messageEl) {
                    messageEl.textContent = result.message || "Cập nhật thành công!";
                    messageEl.className = "small text-success font-weight-bold mt-2 d-block";
                }
                setTimeout(() => window.location.reload(), 800);
            } catch (error) {
                if (messageEl) {
                    messageEl.textContent = error.message;
                    messageEl.className = "small text-danger font-weight-bold mt-2 d-block";
                }
            }
        });
    }

    // 4. Xử lý Đăng xuất
    document.querySelectorAll("[data-logout]").forEach(btn => {
        btn.addEventListener("click", async () => {
            try {
                await api("/api/auth/logout", "POST", {});
            } finally {
                window.location.assign("/Modules/Auth/login.php");
            }
        });
    });

    // 5. Quản lý Bài viết & Giao diện Trang chủ (index.php)
    const articlesListContainer = document.getElementById("articles-list");
    if (articlesListContainer) {
        let currentArticles = [];
        let labelsList = [];
        let autosaveTimer = null;
        let wsConnection = null;

        // View toggle (Grid vs List)
        const btnGrid = document.getElementById("btn-grid-view");
        const btnList = document.getElementById("btn-list-view");
        
        const setViewMode = (mode) => {
            if (mode === "list") {
                articlesListContainer.classList.add("articles-list-view");
                btnList.classList.add("active");
                btnGrid.classList.remove("active");
            } else {
                articlesListContainer.classList.remove("articles-list-view");
                btnGrid.classList.add("active");
                btnList.classList.remove("active");
            }
            localStorage.setItem("genk_view_mode", mode);
            renderArticlesList(currentArticles);
        };

        btnGrid?.addEventListener("click", () => setViewMode("grid"));
        btnList?.addEventListener("click", () => setViewMode("list"));
        const savedViewMode = localStorage.getItem("genk_view_mode") || "grid";
        if (savedViewMode === "list") {
            articlesListContainer.classList.add("articles-list-view");
            btnList?.classList.add("active");
            btnGrid?.classList.remove("active");
        }

        // Tải danh sách nhãn
        const loadLabels = async () => {
            try {
                const res = await api("/api/labels");
                labelsList = res.labels || [];
                renderLabelSelector();
            } catch (e) {}
        };

        const renderLabelSelector = () => {
            const selector = document.getElementById("editor-label-selector");
            if (!selector) return;
            selector.replaceChildren();
            if (labelsList.length === 0) {
                selector.innerHTML = '<span class="small text-muted">Chưa có nhãn nào. Hãy thêm ở menu sidebar!</span>';
                return;
            }
            labelsList.forEach(lbl => {
                const lblTag = document.createElement("div");
                lblTag.className = "custom-control custom-checkbox mr-3 mb-1";
                lblTag.innerHTML = `
                    <input type="checkbox" class="custom-control-input" id="lbl-check-${lbl.id}" name="label_ids" value="${lbl.id}">
                    <label class="custom-control-label small" for="lbl-check-${lbl.id}">🏷️ ${escapeHtml(lbl.name)}</label>
                `;
                selector.append(lblTag);
            });
        };

        // Tải danh sách bài viết từ API
        const loadArticles = async () => {
            const searchInput = document.getElementById("article-search");
            const sortSelect = document.getElementById("sort-select");
            const q = searchInput?.value.trim() || "";
            const sort = sortSelect?.value || "pinned";
            const tab = window.currentTab || "my";
            const category = window.currentCategory || "";
            const labelId = window.currentLabelId || "";

            const params = new URLSearchParams({ tab, sort });
            if (q) params.set("q", q);
            if (category) params.set("category", category);
            if (labelId) params.set("label_id", labelId);

            articlesListContainer.innerHTML = '<div class="col-12 text-center py-5 text-muted"><div class="spinner-border spinner-border-sm mr-2" role="status"></div> Đang tải bài viết...</div>';

            try {
                const res = await api(`/api/articles?${params.toString()}`);
                currentArticles = res.articles || [];
                renderArticlesList(currentArticles);
                
                const searchSummary = document.getElementById("search-summary");
                if (searchSummary) {
                    searchSummary.textContent = q ? `Tìm thấy ${currentArticles.length} bài viết khớp từ khóa` : `Tổng cộng: ${currentArticles.length} bài viết`;
                }

                // Lưu offline vào IndexedDB
                saveArticlesOffline(currentArticles);
            } catch (error) {
                loadArticlesOffline();
            }
        };

        // Render danh sách bài viết chuẩn Bootstrap 4 Grid / List
        const renderArticlesList = (articles) => {
            articlesListContainer.replaceChildren();
            if (articles.length === 0) {
                articlesListContainer.innerHTML = `
                    <div class="col-12 py-5 text-center text-muted">
                        <p class="h5 mb-2">📭 Không tìm thấy bài viết nào.</p>
                        <p class="small">Bấm "+ Viết bài" ở trên để tạo bài viết đầu tiên!</p>
                    </div>
                `;
                return;
            }

            const isListView = articlesListContainer.classList.contains("articles-list-view");
            const colClass = isListView ? "col-12 mb-3 article-card-col" : "col-lg-4 col-md-6 mb-4 article-card-col";

            articles.forEach(art => {
                const colWrapper = document.createElement("div");
                colWrapper.className = colClass;

                const card = document.createElement("article");
                card.className = `card article-card shadow-sm h-100 ${art.is_pinned ? "is-pinned" : ""}`;
                card.style.backgroundColor = art.color || "var(--card-bg)";
                card.dataset.articleId = art.id;

                // Icons nhận diện đặc biệt (Bôi đỏ)
                let specialIcons = "";
                if (art.is_pinned) specialIcons += '<span class="icon-pinned mr-1" title="Đã ghim">📌</span>';
                if (art.is_locked) specialIcons += '<span class="icon-locked mr-1" title="Có mật khẩu">🔒</span>';
                if (art.is_shared) specialIcons += '<span class="icon-shared mr-1" title="Được chia sẻ">👥</span>';

                // Nhãn (Labels)
                let labelsHtml = "";
                if (art.labels && art.labels.length > 0) {
                    labelsHtml = '<div class="d-flex flex-wrap gap-1 mb-2">' +
                        art.labels.map(l => `<span class="badge badge-light border text-muted mr-1 mb-1 font-weight-normal">🏷️ ${escapeHtml(l.name)}</span>`).join("") +
                        '</div>';
                }

                // Ảnh thumbnail
                const thumbHtml = art.image_url ? `
                    <div class="card-thumb-wrapper">
                        <img src="${escapeHtml(art.image_url)}" alt="" loading="lazy">
                    </div>
                ` : "";

                card.innerHTML = `
                    ${thumbHtml}
                    <div class="card-body d-flex flex-column p-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge badge-danger text-uppercase font-weight-bold px-2 py-1">${escapeHtml(art.category || "Tin tức")}</span>
                            <div class="special-icons-group">${specialIcons}</div>
                        </div>
                        <h5 class="card-title font-weight-bold mb-2" style="font-size: 16px; line-height: 1.4;">${escapeHtml(art.title)}</h5>
                        <p class="card-text text-muted small flex-grow-1 mb-3" style="line-height: 1.5;">${escapeHtml(art.excerpt || "")}</p>
                        ${labelsHtml}
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-auto small text-muted">
                            <span>${escapeHtml(art.author_name || "Tác giả")} · ${art.updated_at ? art.updated_at.substring(0, 10) : ""}</span>
                            <div class="card-actions-toolbar d-flex align-items-center" onclick="event.stopPropagation()">
                                <button type="button" data-action="pin" title="${art.is_pinned ? "Bỏ ghim" : "Ghim bài"}">${art.is_pinned ? "📌" : "📍"}</button>
                                <button type="button" data-action="lock" title="Mật khẩu bài viết">🔒</button>
                                <button type="button" data-action="share" title="Chia sẻ">👥</button>
                                <button type="button" data-action="ai-summary" title="Tóm tắt AI">✨</button>
                                <button type="button" data-action="delete" title="Xóa bài viết">🗑️</button>
                            </div>
                        </div>
                    </div>
                `;

                // Bấm vào card để mở Editor Modal
                card.addEventListener("click", () => {
                    if (art.requires_password) {
                        openUnlockModal(art.id);
                    } else {
                        openEditorModal(art.id);
                    }
                });

                // Các nút hành động nhanh trên card
                card.querySelector('[data-action="pin"]')?.addEventListener("click", async () => {
                    try {
                        await api(`/api/articles/${art.id}/pin`, "POST");
                        loadArticles();
                    } catch (e) { alert(e.message); }
                });

                card.querySelector('[data-action="lock"]')?.addEventListener("click", () => {
                    openLockModal(art.id, art.is_locked);
                });

                card.querySelector('[data-action="share"]')?.addEventListener("click", () => {
                    openShareModal(art.id);
                });

                card.querySelector('[data-action="ai-summary"]')?.addEventListener("click", () => {
                    openAiSummaryModal(art.id);
                });

                card.querySelector('[data-action="delete"]')?.addEventListener("click", async () => {
                    if (window.confirm(`Bạn có chắc chắn muốn xóa bài viết "${art.title}"?`)) {
                        try {
                            await api(`/api/articles/${art.id}`, "DELETE");
                            loadArticles();
                        } catch (e) { alert(e.message); }
                    }
                });

                colWrapper.append(card);
                articlesListContainer.append(colWrapper);
            });
        };

        // Live search với Debounce 300ms (Theo đề bài)
        const searchInput = document.getElementById("article-search");
        let searchTimer;
        searchInput?.addEventListener("input", () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(loadArticles, 300);
        });

        // Dropdown Sắp xếp
        document.getElementById("sort-select")?.addEventListener("change", loadArticles);

        // 6. Giao diện Thống nhất Tạo mới & Sửa Bài viết (Unified Modal Editor)
        const editorForm = document.getElementById("unified-article-form");
        const editorTitleInput = document.getElementById("editor-title");
        const editorContentInput = document.getElementById("editor-content");
        const autosaveStatus = document.getElementById("autosave-status");

        const openEditorModal = async (articleId = null) => {
            editorForm.reset();
            document.getElementById("editor-article-id").value = articleId || "";
            document.getElementById("editor-modal-title").textContent = articleId ? "Chỉnh sửa bài viết" : "Viết bài mới";
            autosaveStatus.textContent = "";
            renderLabelSelector();

            if (articleId) {
                try {
                    const res = await api(`/api/articles/${articleId}`);
                    const art = res.article;
                    document.getElementById("editor-title").value = art.title;
                    document.getElementById("editor-content").value = art.content;
                    document.getElementById("editor-category").value = art.category || "Tin tức";
                    document.getElementById("editor-image-url").value = art.image_url || "";
                    document.getElementById("editor-color").value = art.color || "#ffffff";

                    if (art.labels) {
                        const lblIds = art.labels.map(l => String(l.id));
                        document.querySelectorAll("#editor-label-selector input").forEach(cb => {
                            cb.checked = lblIds.includes(cb.value);
                        });
                    }

                    renderAttachmentsList(art.attachments || []);
                    connectWebSocket(articleId);
                } catch (e) {
                    alert(e.message);
                    return;
                }
            } else {
                renderAttachmentsList([]);
            }

            showModal("editor-modal");
        };

        const closeEditorModal = () => {
            hideModal("editor-modal");
            if (wsConnection) {
                wsConnection.close();
                wsConnection = null;
            }
            loadArticles();
        };

        document.getElementById("composer-collapsed")?.addEventListener("click", () => openEditorModal());

        // Cơ chế Auto-save tự động khi gõ (500ms debounce)
        const triggerAutosave = () => {
            const articleId = document.getElementById("editor-article-id").value;
            if (!articleId) return;

            const title = editorTitleInput.value.trim();
            const content = editorContentInput.value;
            if (!title || !content) return;

            autosaveStatus.textContent = "⏳ Đang lưu...";
            clearTimeout(autosaveTimer);
            autosaveTimer = setTimeout(async () => {
                try {
                    const res = await api(`/api/articles/${articleId}/autosave`, "POST", { title, content });
                    autosaveStatus.textContent = `✓ Đã tự động lưu ${res.updated_at}`;
                    
                    if (wsConnection && wsConnection.readyState === WebSocket.OPEN) {
                        wsConnection.send(JSON.stringify({ type: "content_update", title, content }));
                    }
                } catch (e) {
                    autosaveStatus.textContent = "⚠️ Lỗi lưu ngầm";
                }
            }, 500);
        };

        editorTitleInput?.addEventListener("input", triggerAutosave);
        editorContentInput?.addEventListener("input", triggerAutosave);

        // Submit form lưu bài viết đầy đủ
        editorForm?.addEventListener("submit", async event => {
            event.preventDefault();
            const articleId = document.getElementById("editor-article-id").value;
            const selectedLabels = Array.from(document.querySelectorAll("#editor-label-selector input:checked")).map(cb => parseInt(cb.value));

            const payload = {
                title: editorTitleInput.value.trim(),
                content: editorContentInput.value,
                category: document.getElementById("editor-category").value.trim(),
                image_url: document.getElementById("editor-image-url").value.trim(),
                color: document.getElementById("editor-color").value,
                label_ids: selectedLabels
            };

            try {
                if (articleId) {
                    await api(`/api/articles/${articleId}`, "PATCH", payload);
                } else {
                    await api("/api/articles", "POST", payload);
                }
                closeEditorModal();
            } catch (error) {
                alert(error.message);
            }
        });

        // 7. Đính kèm tệp tin
        const renderAttachmentsList = (attachments) => {
            const list = document.getElementById("attachments-preview-list");
            if (!list) return;
            list.replaceChildren();
            attachments.forEach(att => {
                const item = document.createElement("span");
                item.className = "badge badge-light border text-muted mr-2 p-2";
                item.innerHTML = `📎 <a href="${escapeHtml(att.file_path)}" target="_blank" class="text-dark font-weight-bold">${escapeHtml(att.file_name)}</a>`;
                list.append(item);
            });
        };

        document.getElementById("upload-attachment-btn")?.addEventListener("click", async () => {
            const articleId = document.getElementById("editor-article-id").value;
            const fileInput = document.getElementById("attachment-file-input");
            if (!articleId) {
                alert("Vui lòng lưu bài viết lần đầu trước khi tải tệp đính kèm!");
                return;
            }
            if (!fileInput.files || fileInput.files.length === 0) {
                alert("Vui lòng chọn một tệp tin!");
                return;
            }

            const formData = new FormData();
            formData.append("attachment", fileInput.files[0]);

            try {
                await api(`/api/articles/${articleId}/attachments`, "POST", formData, true);
                alert("Tải tệp đính kèm thành công!");
                fileInput.value = "";
                const res = await api(`/api/articles/${articleId}`);
                renderAttachmentsList(res.article.attachments || []);
            } catch (e) {
                alert(e.message);
            }
        });

        // 8. WebSocket Thời gian thực
        const connectWebSocket = async (articleId) => {
            try {
                const tokenRes = await api(`/api/ws-token?article_id=${articleId}`);
                wsConnection = new WebSocket(tokenRes.url);

                wsConnection.onmessage = (event) => {
                    try {
                        const data = JSON.parse(event.data);
                        if (data.type === "content_update") {
                            if (data.title && document.activeElement !== editorTitleInput) {
                                editorTitleInput.value = data.title;
                            }
                            if (data.content && document.activeElement !== editorContentInput) {
                                editorContentInput.value = data.content;
                            }
                            autosaveStatus.textContent = "🔄 Đồng bộ từ cộng tác viên";
                        }
                    } catch (e) {}
                };
            } catch (e) {}
        };

        // 9. Khóa mật khẩu & Mở khóa
        const openLockModal = (articleId, isLocked) => {
            document.getElementById("lock-article-id").value = articleId;
            document.getElementById("lock-input-password").value = "";
            const disableBtn = document.getElementById("btn-disable-lock");
            if (disableBtn) disableBtn.hidden = !isLocked;
            showModal("lock-modal");
        };

        document.getElementById("lock-form")?.addEventListener("submit", async event => {
            event.preventDefault();
            const articleId = document.getElementById("lock-article-id").value;
            const password = document.getElementById("lock-input-password").value;
            try {
                await api(`/api/articles/${articleId}/lock`, "POST", { locked: true, password });
                hideModal("lock-modal");
                loadArticles();
            } catch (e) { alert(e.message); }
        });

        document.getElementById("btn-disable-lock")?.addEventListener("click", async () => {
            const articleId = document.getElementById("lock-article-id").value;
            try {
                await api(`/api/articles/${articleId}/lock`, "POST", { locked: false });
                hideModal("lock-modal");
                loadArticles();
            } catch (e) { alert(e.message); }
        });

        const openUnlockModal = (articleId) => {
            document.getElementById("unlock-article-id").value = articleId;
            document.getElementById("unlock-input-password").value = "";
            showModal("unlock-modal");
        };

        document.getElementById("unlock-form")?.addEventListener("submit", async event => {
            event.preventDefault();
            const articleId = document.getElementById("unlock-article-id").value;
            const password = document.getElementById("unlock-input-password").value;
            try {
                await api(`/api/articles/${articleId}/unlock`, "POST", { password });
                hideModal("unlock-modal");
                openEditorModal(articleId);
            } catch (e) { alert(e.message); }
        });

        // 10. Chia sẻ bài viết
        const openShareModal = (articleId) => {
            document.getElementById("share-article-id").value = articleId;
            document.getElementById("share-email").value = "";
            showModal("share-modal");
        };

        document.getElementById("share-form")?.addEventListener("submit", async event => {
            event.preventDefault();
            const articleId = document.getElementById("share-article-id").value;
            const email = document.getElementById("share-email").value.trim();
            const permission = document.getElementById("share-permission").value;
            try {
                const res = await api(`/api/articles/${articleId}/share`, "POST", { email, permission });
                alert(res.message);
                hideModal("share-modal");
                loadArticles();
            } catch (e) { alert(e.message); }
        });

        // 11. AI Tóm tắt & AI Hỏi đáp
        let activeAiArticleId = null;

        const openAiSummaryModal = async (articleId) => {
            activeAiArticleId = articleId;
            document.getElementById("ai-summary-view").hidden = false;
            document.getElementById("ai-qa-view").hidden = true;
            document.getElementById("ai-modal-title").textContent = "✨ AI Tóm tắt bài viết";
            const resultBox = document.getElementById("ai-summary-result");
            resultBox.textContent = "⏳ Đang kết nối trí tuệ nhân tạo để tóm tắt...";
            showModal("ai-modal");

            try {
                const res = await api(`/api/articles/${articleId}/summarize`, "POST");
                resultBox.textContent = res.summary;
            } catch (e) {
                resultBox.textContent = "Không thể tạo tóm tắt: " + e.message;
            }
        };

        document.getElementById("ai-regenerate-btn")?.addEventListener("click", () => {
            if (activeAiArticleId) openAiSummaryModal(activeAiArticleId);
        });

        document.getElementById("open-ai-qa-btn")?.addEventListener("click", () => {
            document.getElementById("ai-summary-view").hidden = true;
            document.getElementById("ai-qa-view").hidden = false;
            document.getElementById("ai-modal-title").textContent = "✨ Trợ lý AI Hỏi & Đáp";
            document.getElementById("ai-qa-input").value = "";
            document.getElementById("ai-qa-result").style.display = "none";
            document.getElementById("ai-qa-references").replaceChildren();
            showModal("ai-modal");
        });

        document.getElementById("ai-qa-submit")?.addEventListener("click", async () => {
            const input = document.getElementById("ai-qa-input");
            const q = input.value.trim();
            if (!q) return;

            const resBox = document.getElementById("ai-qa-result");
            const refBox = document.getElementById("ai-qa-references");
            resBox.style.display = "block";
            resBox.textContent = "⏳ AI đang tìm kiếm và suy luận...";
            refBox.replaceChildren();

            try {
                const res = await api("/api/ai/qa", "POST", { question: q });
                resBox.textContent = res.answer;

                if (res.references && res.references.length > 0) {
                    refBox.innerHTML = "<strong class='d-block text-muted mb-1'>Nguồn tham chiếu:</strong>";
                    res.references.forEach(r => {
                        const link = document.createElement("a");
                        link.href = `/Modules/Articles/detail.php?id=${r.id}`;
                        link.textContent = `🔗 ${r.title}`;
                        link.className = "btn btn-sm btn-outline-danger mr-2 mb-1 py-0 px-2";
                        refBox.append(link);
                    });
                }
            } catch (e) {
                resBox.textContent = "Lỗi: " + e.message;
            }
        });

        // 12. Quản lý Nhãn (CRUD Labels)
        document.getElementById("btn-add-label")?.addEventListener("click", async () => {
            const name = window.prompt("Nhập tên nhãn mới:");
            if (name && name.trim()) {
                try {
                    await api("/api/labels", "POST", { name: name.trim() });
                    window.location.reload();
                } catch (e) { alert(e.message); }
            }
        });

        document.querySelectorAll("[data-edit-label]").forEach(btn => {
            btn.addEventListener("click", async () => {
                const id = btn.dataset.editLabel;
                const oldName = btn.dataset.name;
                const newName = window.prompt("Nhập tên mới cho nhãn:", oldName);
                if (newName && newName.trim() && newName.trim() !== oldName) {
                    try {
                        await api(`/api/labels/${id}`, "PATCH", { name: newName.trim() });
                        window.location.reload();
                    } catch (e) { alert(e.message); }
                }
            });
        });

        document.querySelectorAll("[data-delete-label]").forEach(btn => {
            btn.addEventListener("click", async () => {
                const id = btn.dataset.deleteLabel;
                if (window.confirm("Bạn có chắc muốn xóa nhãn này? Bài viết sẽ không bị mất.")) {
                    try {
                        await api(`/api/labels/${id}`, "DELETE");
                        window.location.reload();
                    } catch (e) { alert(e.message); }
                }
            });
        });

        // Tự mở modal nếu có tham số trên URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get("create") === "1") {
            openEditorModal();
        } else if (urlParams.get("edit_id")) {
            openEditorModal(parseInt(urlParams.get("edit_id")));
        }

        loadLabels();
        loadArticles();
    }

    // 13. PWA IndexedDB Offline Storage
    const IDB_NAME = "genk_offline_db";
    const openDb = () => new Promise((resolve, reject) => {
        const req = indexedDB.open(IDB_NAME, 1);
        req.onupgradeneeded = () => req.result.createObjectStore("articles", { keyPath: "id" });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });

    const saveArticlesOffline = async (articles) => {
        try {
            const db = await openDb();
            const tx = db.transaction("articles", "readwrite");
            const store = tx.objectStore("articles");
            articles.forEach(art => store.put(art));
        } catch (e) {}
    };

    const loadArticlesOffline = async () => {
        try {
            const db = await openDb();
            const tx = db.transaction("articles", "readonly");
            const req = tx.objectStore("articles").getAll();
            req.onsuccess = () => {
                const offlineArticles = req.result || [];
                const searchSummary = document.getElementById("search-summary");
                if (searchSummary) {
                    searchSummary.textContent = `⚡ Ngoại tuyến: ${offlineArticles.length} bài viết đã lưu`;
                }
                const container = document.getElementById("articles-list");
                if (container && offlineArticles.length > 0) {
                    container.innerHTML = offlineArticles.map(a => `
                        <div class="col-md-6 col-lg-4 mb-4">
                            <article class="card article-card shadow-sm h-100 p-3">
                                <span class="badge badge-secondary mb-2 align-self-start">Offline</span>
                                <h5 class="card-title font-weight-bold mb-2">${escapeHtml(a.title)}</h5>
                                <p class="card-text text-muted small">${escapeHtml(a.excerpt || a.content.substring(0, 100))}</p>
                            </article>
                        </div>
                    `).join("");
                }
            };
        } catch (e) {}
    };

    const escapeHtml = (str) => {
        if (!str) return "";
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    };

    // Đăng ký Service Worker
    if ("serviceWorker" in navigator) {
        window.addEventListener("load", () => {
            navigator.serviceWorker.register("/service-worker.js").catch(() => {});
        });
    }
})();
