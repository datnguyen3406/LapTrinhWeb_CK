(() => {
  const grid = document.getElementById("articles-grid-slot");
  const featuredSlot = document.getElementById("featured-article-slot");
  const searchForm = document.getElementById("header-search-form");
  const searchInput = document.getElementById("header-search-input");
  const sourceSelect = document.getElementById("source-filter");
  const queryParams = new URLSearchParams(window.location.search);
  searchInput.value = queryParams.get("q") || "";
  let category = queryParams.get("category") || "";
  let debounceTimer;
  let latestRequest = 0;

  const textOf = (value) => typeof value === "string" ? value : value?.name || "";

  function articleLink(article) {
    const url = new URL(App.url("pages/Read.html"));
    url.searchParams.set("id", article.id);
    return url.href;
  }

  function createCard(article, featured = false) {
    const card = document.createElement("article");
    card.className = `article-card${featured ? " is-featured" : ""}`;

    const thumbnailUrl = App.safeUrl(article.thumbnailUrl || "");
    if (thumbnailUrl) {
      const image = document.createElement("img");
      image.className = "article-thumbnail";
      image.src = thumbnailUrl;
      image.alt = "";
      image.loading = "lazy";
      card.append(image);
    }

    const categoryLabel = document.createElement("p");
    categoryLabel.className = "article-category-badge";
    categoryLabel.textContent = textOf(article.category);
    card.append(categoryLabel);

    const title = document.createElement("h2");
    const link = document.createElement("a");
    link.href = articleLink(article);
    link.textContent = article.title || "Chưa có tiêu đề";
    title.append(link);
    card.append(title);

    if (article.summary) {
      const summary = document.createElement("p");
      summary.textContent = article.summary;
      card.append(summary);
    }

    const metadata = document.createElement("p");
    metadata.className = "article-meta";
    metadata.textContent = [textOf(article.source), article.publishedAt]
      .filter(Boolean)
      .join(" · ");
    card.append(metadata);

    const save = document.createElement("button");
    save.type = "button";
    save.dataset.favoriteId = article.id;
    save.dataset.saved = String(Boolean(article.isFavorite));
    save.textContent = article.isFavorite ? "Bỏ yêu thích" : "Lưu yêu thích";
    card.append(save);
    return card;
  }

  async function loadArticles() {
    const requestId = ++latestRequest;
    App.ui.renderState(grid, "Đang tải bài báo...");
    const params = new URLSearchParams();
    const query = searchInput.value.trim();
    if (query) params.set("q", query);
    if (category) params.set("category", category);
    if (sourceSelect.value) params.set("source", sourceSelect.value);

    try {
      const result = await App.api.get(`articles?${params}`);
      if (requestId !== latestRequest) return;
      const items = result.items;
      if (!Array.isArray(items)) throw new Error("Danh sách bài báo không đúng định dạng.");

      const featured = result.featured || items.find((item) => item.isFeatured) || items[0];
      featuredSlot.replaceChildren();
      if (featured) featuredSlot.append(createCard(featured, true));

      const others = items.filter((item) => item.id !== featured?.id);
      if (!items.length) {
        App.ui.renderState(grid, "Chưa có bài báo phù hợp với điều kiện tìm kiếm.");
      } else if (!others.length) {
        grid.replaceChildren();
      } else {
        grid.replaceChildren(...others.map((item) => createCard(item)));
      }
    } catch (error) {
      if (requestId === latestRequest) {
        featuredSlot.replaceChildren();
        App.ui.renderState(grid, error.message, "error");
      }
    }
  }

  async function loadSources() {
    try {
      const result = await App.api.get("sources");
      if (!Array.isArray(result.items)) throw new Error("Danh sách nguồn báo không đúng định dạng.");
      for (const source of result.items) {
        const option = document.createElement("option");
        option.value = source.id;
        option.textContent = source.name;
        sourceSelect.append(option);
      }
    } catch (error) {
      App.ui.toast(error.message, "error");
    }
  }

  async function toggleFavorite(button) {
    const id = button.dataset.favoriteId;
    button.disabled = true;
    try {
      if (button.dataset.saved === "true") {
        await App.api.delete(`me/favorites/${encodeURIComponent(id)}`);
      } else {
        await App.api.post("me/favorites", { articleId: id });
        try {
          await App.api.get(`articles/${encodeURIComponent(id)}`);
        } catch (error) {
          App.ui.toast(`Đã lưu yêu thích nhưng chưa lưu được bản đọc ngoại tuyến: ${error.message}`, "error");
        }
      }
      App.ui.toast("Đã cập nhật danh sách yêu thích.", "success");
      await loadArticles();
    } catch (error) {
      if (error.status === 401) App.auth.redirectToLogin();
      else App.ui.toast(error.message, "error");
    } finally {
      button.disabled = false;
    }
  }

  searchForm.addEventListener("submit", (event) => {
    event.preventDefault();
    loadArticles();
  });
  searchInput.addEventListener("input", () => {
    window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(loadArticles, 300);
  });
  sourceSelect.addEventListener("change", loadArticles);
  document.getElementById("category-nav").addEventListener("click", (event) => {
    const button = event.target.closest("[data-category]");
    if (!button) return;
    category = category === button.dataset.category ? "" : button.dataset.category;
    document.querySelectorAll("#category-nav [data-category]").forEach((item) => {
      item.classList.toggle("is-active", item.dataset.category === category);
    });
    loadArticles();
  });
  document.addEventListener("click", (event) => {
    const button = event.target.closest("[data-favorite-id]");
    if (button) toggleFavorite(button);
  });

  App.ui.initAccountNavigation();
  document.querySelectorAll("#category-nav [data-category]").forEach((item) => {
    item.classList.toggle("is-active", item.dataset.category === category);
  });
  loadSources();
  loadArticles();
})();
