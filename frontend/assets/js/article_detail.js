(() => {
  const container = document.getElementById("article-detail-container");
  const state = document.getElementById("article-state");
  const articleId = new URLSearchParams(window.location.search).get("id");
  const addButton = document.getElementById("btn-add-to-folder");
  const folderSelect = document.getElementById("favorite-folder");

  async function loadArticle() {
    if (!articleId) {
      App.ui.showMessage(state, "Không tìm thấy mã bài báo.");
      return;
    }

    state.textContent = "Đang tải bài báo...";
    state.className = "page-state is-info";
    state.hidden = false;
    try {
      const result = await App.api.get(`articles/${encodeURIComponent(articleId)}`);
      const article = result.article;
      if (!article) throw new Error("Không tìm thấy nội dung bài báo.");

      document.getElementById("article-category").textContent =
        typeof article.category === "string" ? article.category : article.category?.name || "";
      document.getElementById("article-title").textContent = article.title || "";
      document.getElementById("article-source").textContent =
        typeof article.source === "string" ? article.source : article.source?.name || "";
      const date = document.getElementById("article-date");
      date.dateTime = article.publishedAt || "";
      date.textContent = article.publishedAt
        ? new Date(article.publishedAt).toLocaleDateString("vi-VN")
        : "";
      document.getElementById("article-summary").textContent = article.summary || "";
      document.getElementById("article-content").textContent = article.content || "";
      const contentMedia = document.createElement("div");
      contentMedia.className = "article-media-list";
      for (const media of Array.isArray(article.media) ? article.media : []) {
        if (media.type === "image") {
          const url = App.safeUrl(media.url || "");
          if (!url) continue;
          const image = document.createElement("img");
          image.src = url;
          image.alt = article.title || "";
          image.loading = "lazy";
          contentMedia.append(image);
        } else if (media.type === "video") {
          const url = App.safeUrl(media.url || "");
          if (!url) continue;
          const video = document.createElement("video");
          video.src = url;
          video.controls = true;
          video.preload = "metadata";
          contentMedia.append(video);
        }
      }
      document.getElementById("article-content").append(contentMedia);

      const thumbnail = document.getElementById("article-thumbnail");
      thumbnail.replaceChildren();
      const thumbnailUrl = App.safeUrl(article.thumbnailUrl || "");
      if (thumbnailUrl) {
        const image = document.createElement("img");
        image.src = thumbnailUrl;
        image.alt = article.title || "";
        image.loading = "lazy";
        thumbnail.append(image);
      }
      container.dataset.articleId = article.id;
      container.hidden = false;
      state.hidden = true;
      addButton.textContent = article.isFavorite ? "Đã lưu yêu thích" : "Thêm vào yêu thích";
      addButton.dataset.saved = String(Boolean(article.isFavorite));
      try {
        const folders = await App.api.get("me/favorite-folders");
        for (const folder of folders.items) {
          const option = document.createElement("option");
          option.value = folder.id;
          option.textContent = folder.name;
          folderSelect.append(option);
        }
      } catch (error) {
        if (error.status !== 401 && error.status !== 0) App.ui.toast(error.message, "error");
      }
      App.api.post("me/reading-history", { articleId: article.id }).catch((error) => {
        if (error.status !== 401 && error.status !== 0) App.ui.toast(error.message, "error");
      });
    } catch (error) {
      App.ui.showMessage(state, error.message);
    }
  }

  addButton.addEventListener("click", async () => {
    if (!container.dataset.articleId) return;
    addButton.disabled = true;
    try {
      if (addButton.dataset.saved === "true") {
        await App.api.delete(`me/favorites/${encodeURIComponent(container.dataset.articleId)}`);
        addButton.dataset.saved = "false";
        addButton.textContent = "Thêm vào yêu thích";
        App.ui.toast("Đã bỏ bài báo khỏi yêu thích.", "success");
      } else {
        await App.api.post("me/favorites", {
          articleId: container.dataset.articleId,
          folderId: folderSelect.value || null,
        });
        addButton.dataset.saved = "true";
        addButton.textContent = "Đã lưu yêu thích";
        App.ui.toast("Đã lưu bài báo vào mục yêu thích.", "success");
      }
    } catch (error) {
      if (error.status === 401) App.auth.redirectToLogin();
      else App.ui.toast(error.message, "error");
    } finally {
      addButton.disabled = false;
    }
  });

  async function loadCategories() {
    const navList = document.getElementById("nav-categories");
    if (!navList) return;
    try {
      const result = await App.api.get("categories");
      if (Array.isArray(result.items) && result.items.length > 0) {
        navList.replaceChildren();
        for (const cat of result.items) {
          const li = document.createElement("li");
          li.className = "nav-category-item";
          const btn = document.createElement("button");
          btn.type = "button";
          btn.dataset.category = cat.slug;
          btn.textContent = cat.name.toUpperCase();
          btn.addEventListener("click", () => {
            window.location.assign(App.url(`pages/index.html?category=${encodeURIComponent(cat.slug)}`));
          });
          li.append(btn);
          navList.append(li);
        }
      }
    } catch {}
  }

  App.ui.initAccountNavigation();
  loadCategories();
  loadArticle();
})();
