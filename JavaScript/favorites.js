(() => {
  const folderForm = document.getElementById("form-create-folder");
  const folderName = document.getElementById("folder-name-input");
  const foldersList = document.getElementById("folders-list");
  const articlesList = document.getElementById("folder-articles-list");
  const selectedTitle = document.getElementById("selected-folder-title");
  let folders = [];
  let favorites = [];
  let selectedFolderId = "";
  let category = "";

  function renderFolders() {
    foldersList.replaceChildren();
    if (!folders.length) {
      App.ui.renderState(foldersList, "Bạn chưa tạo thư mục yêu thích.");
      return;
    }
    for (const folder of folders) {
      const button = document.createElement("button");
      button.type = "button";
      button.className = "folder-button";
      button.textContent = folder.name;
      button.classList.toggle("is-active", folder.id === selectedFolderId);
      button.addEventListener("click", () => {
        selectedFolderId = folder.id;
        selectedTitle.textContent = `Bài báo trong thư mục: ${folder.name}`;
        renderFolders();
        renderFavorites();
      });
      foldersList.append(button);
    }
  }

  function renderFavorites() {
    const visible = favorites.filter((favorite) => {
      const matchesFolder = !selectedFolderId || favorite.folderId === selectedFolderId;
      const articleCategory = typeof favorite.article.category === "string"
        ? favorite.article.category
        : favorite.article.category?.slug;
      return matchesFolder && (!category || articleCategory === category);
    });

    articlesList.replaceChildren();
    if (!visible.length) {
      App.ui.renderState(articlesList, "Thư mục này chưa có bài báo yêu thích.");
      return;
    }

    for (const favorite of visible) {
      const row = document.createElement("article");
      row.className = "favorite-item";
      const title = document.createElement("a");
      const url = new URL(App.url("HTML/Read.html"));
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
    App.ui.renderState(foldersList, "Đang tải thư mục...");
    App.ui.renderState(articlesList, "Đang tải bài báo...");
    try {
      const [folderResult, favoriteResult] = await Promise.all([
        App.api.get("me/favorite-folders"),
        App.api.get("me/favorites"),
      ]);
      if (!Array.isArray(folderResult.items) || !Array.isArray(favoriteResult.items)) {
        throw new Error("Dữ liệu thư mục yêu thích không đúng định dạng.");
      }
      folders = folderResult.items;
      favorites = favoriteResult.items;
      if (!folders.some((folder) => folder.id === selectedFolderId)) {
        selectedFolderId = "";
      }
      renderFolders();
      renderFavorites();
    } catch (error) {
      App.ui.renderState(foldersList, error.message, "error");
      App.ui.renderState(articlesList, error.message, "error");
    }
  }

  folderForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const name = folderName.value.trim();
    if (!name) return;
    App.ui.setBusy(folderForm, true, "Đang tạo...");
    try {
      const result = await App.api.post("me/favorite-folders", { name });
      if (!result.folder) throw new Error("Máy chủ chưa trả về thư mục vừa tạo.");
      folders.push(result.folder);
      selectedFolderId = result.folder.id;
      selectedTitle.textContent = `Bài báo trong thư mục: ${result.folder.name}`;
      folderForm.reset();
      renderFolders();
      renderFavorites();
      App.ui.toast("Đã tạo thư mục yêu thích.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    } finally {
      App.ui.setBusy(folderForm, false);
    }
  });

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
