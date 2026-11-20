(() => {
  const profileForm = document.getElementById("form-profile");
  const passwordForm = document.getElementById("form-change-password");
  const favorites = document.getElementById("favorite-articles-list");
  const history = document.getElementById("reading-history-list");
  const maxAvatarBytes = 5 * 1024 * 1024;
  const allowedTypes = new Set(["image/jpeg", "image/png", "image/webp"]);

  function renderArticles(container, items, emptyText) {
    container.replaceChildren();
    if (!items.length) {
      App.ui.renderState(container, emptyText);
      return;
    }
    for (const item of items) {
      const article = item.article || item;
      const link = document.createElement("a");
      const url = new URL(App.url("pages/Read.html"));
      url.searchParams.set("id", article.id);
      link.href = url.href;
      link.textContent = article.title || "Bài báo";
      const row = document.createElement("p");
      row.append(link);
      container.append(row);
    }
  }

  async function loadReader(user) {
    document.getElementById("profile-name").value = user.displayName || "";
    const [favoriteResult, historyResult] = await Promise.all([
      App.api.get("me/favorites"),
      App.api.get("me/reading-history"),
    ]);
    renderArticles(favorites, favoriteResult.items, "Bạn chưa lưu bài báo nào.");
    renderArticles(history, historyResult.items, "Lịch sử đọc đang trống.");
  }

  profileForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const file = document.getElementById("profile-avatar-file").files[0];
    if (file && !allowedTypes.has(file.type)) {
      App.ui.toast("Avatar phải là ảnh JPEG, PNG hoặc WebP.", "error");
      return;
    }
    if (file && file.size > maxAvatarBytes) {
      App.ui.toast("Avatar không được lớn hơn 5 MB.", "error");
      return;
    }
    App.ui.setBusy(profileForm, true, "Đang lưu...");
    try {
      const displayName = document.getElementById("profile-name").value.trim();
      await App.api.patch("me", { displayName });
      if (file) {
        const data = new FormData();
        data.append("avatar", file);
        await App.api.upload("me/avatar", data);
      }
      App.ui.toast("Đã lưu hồ sơ.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    } finally {
      App.ui.setBusy(profileForm, false);
    }
  });

  passwordForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    const currentPassword = document.getElementById("old-password").value;
    const newPassword = document.getElementById("new-password").value;
    const confirmation = document.getElementById("confirm-password").value;
    if (newPassword !== confirmation) {
      App.ui.toast("Mật khẩu mới nhập lại không khớp.", "error");
      return;
    }
    App.ui.setBusy(passwordForm, true, "Đang đổi...");
    try {
      await App.api.post("me/password", { currentPassword, newPassword });
      passwordForm.reset();
      App.ui.toast("Đã đổi mật khẩu.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    } finally {
      App.ui.setBusy(passwordForm, false);
    }
  });

  document.getElementById("btn-clear-history").addEventListener("click", async (event) => {
    if (!window.confirm("Bạn có chắc muốn xóa toàn bộ lịch sử đọc?")) return;
    const button = event.currentTarget;
    button.disabled = true;
    try {
      await App.api.delete("me/reading-history");
      renderArticles(history, [], "Lịch sử đọc đang trống.");
      App.ui.toast("Đã xóa lịch sử đọc.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    } finally {
      button.disabled = false;
    }
  });

  document.getElementById("btn-create-label").addEventListener("click", async () => {
    const input = document.getElementById("new-category-label");
    const name = input.value.trim();
    if (!name) {
      App.ui.toast("Nhập tên phân loại trước.", "error");
      return;
    }
    try {
      await App.api.post("me/categories", { name });
      input.value = "";
      App.ui.toast("Đã tạo phân loại.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    }
  });

  const preferenceKey = "genk-reader-preferences";
  let savedPreferences = {};
  try {
    savedPreferences = JSON.parse(localStorage.getItem(preferenceKey) || "{}");
  } catch {
    savedPreferences = {};
  }
  let currentFontSize = Math.min(24, Math.max(12, Number(savedPreferences.fontSize) || 16));
  let currentTheme = ["light", "dark"].includes(savedPreferences.theme)
    ? savedPreferences.theme
    : "light";
  const fontValue = document.getElementById("font-size-value");
  function applyFontSize(value) {
    currentFontSize = Math.min(24, Math.max(12, value));
    document.documentElement.style.setProperty("--user-font-size", `${currentFontSize}px`);
    fontValue.textContent = `${currentFontSize}px`;
    saveLocalPreferences();
  }
  function saveLocalPreferences() {
    localStorage.setItem(preferenceKey, JSON.stringify({
      fontSize: currentFontSize,
      theme: currentTheme,
    }));
  }
  function saveFontSize(value) {
    applyFontSize(value);
    App.api.patch("me/preferences", { fontSize: currentFontSize }).catch((error) => {
      App.ui.toast(`Đã lưu trên thiết bị; chưa đồng bộ được máy chủ: ${error.message}`, "error");
    });
  }
  document.getElementById("btn-font-decrease").addEventListener("click", () => {
    saveFontSize(currentFontSize - 1);
  });
  document.getElementById("btn-font-increase").addEventListener("click", () => {
    saveFontSize(currentFontSize + 1);
  });
  document.getElementById("btn-toggle-theme").addEventListener("click", () => {
    currentTheme = currentTheme === "dark" ? "light" : "dark";
    document.body.dataset.theme = currentTheme;
    saveLocalPreferences();
    App.api.patch("me/preferences", { theme: currentTheme }).catch((error) => {
      App.ui.toast(`Đã lưu trên thiết bị; chưa đồng bộ được máy chủ: ${error.message}`, "error");
    });
  });

  App.auth.requireUser().then((user) => {
    if (!user) return;
    if (user.preferences) {
      currentFontSize = Math.min(24, Math.max(12, Number(user.preferences.fontSize) || currentFontSize));
      currentTheme = ["light", "dark"].includes(user.preferences.theme)
        ? user.preferences.theme
        : currentTheme;
    }
    document.body.dataset.theme = currentTheme;
    applyFontSize(currentFontSize);
    loadReader(user).catch((error) => {
      App.ui.renderState(favorites, error.message, "error");
      App.ui.renderState(history, error.message, "error");
    });
  });
})();
