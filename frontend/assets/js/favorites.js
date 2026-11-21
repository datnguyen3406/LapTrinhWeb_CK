(() => {
  const articlesList = document.getElementById("favorites-list");
  let favorites = [];
  let category = "";

  function renderFavorites() {
    const visible = favorites.filter((favorite) => {
      const articleCategory = typeof favorite.article.category === "string"
        ? favorite.article.category
        : favorite.article.category?.slug;
      return !category || articleCategory === category;
    });

    articlesList.replaceChildren();
    if (!visible.length) {
      App.ui.renderState(
        articlesList,
        category ? "Không có bài yêu thích trong danh mục này." : "Bạn chưa lưu bài báo nào.",
      );
      return;
    }

    for (const favorite of visible) {
      const row = document.createElement("article");
      row.className = "favorite-item";
      const title = document.createElement("a");
      const url = new URL(App.url("pages/Read.html"));
      url.searchParams.set("id", favorite.article.id);
      title.href = url.href;
      title.textContent = favorite.article.title;
      row.append(title);

      const remove = document.createElement("button");
      remove.type = "button";
      remove.textContent = "Bỏ yêu thích";
      remove.addEventListener("click", () => removeFavorite(favorite.article.id, remove));
      row.append(remove);
      articlesList.append(row);
    }
  }

  async function removeFavorite(articleId, button) {
    button.disabled = true;
    try {
      await App.api.delete(`me/favorites/${encodeURIComponent(articleId)}`);
      favorites = favorites.filter((item) => item.article.id !== articleId);
      renderFavorites();
      App.ui.toast("Đã bỏ bài báo khỏi yêu thích.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
      button.disabled = false;
    }
  }

  async function load() {
    App.ui.renderState(articlesList, "Đang tải bài báo...");
    try {
      const favoriteResult = await App.api.get("me/favorites");
      if (!Array.isArray(favoriteResult.items)) {
        throw new Error("Danh sách bài yêu thích không đúng định dạng.");
      }
      favorites = favoriteResult.items;
      renderFavorites();
    } catch (error) {
      App.ui.renderState(articlesList, error.message, "error");
    }
  }

  document.getElementById("category-nav").addEventListener("click", (event) => {
    const button = event.target.closest("[data-category]");
    if (!button) return;
    category = category === button.dataset.category ? "" : button.dataset.category;
    renderFavorites();
  });

  App.ui.initAccountNavigation();
  App.auth.requireUser().then((user) => {
    if (user) load();
  });
})();
