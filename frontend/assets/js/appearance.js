(() => {
  const fontValue = document.getElementById("font-size-value");
  const minFont = 12;
  const maxFont = 24;
  const storageKey = "genk-reader-preferences";
  let preferences;

  try {
    preferences = JSON.parse(localStorage.getItem(storageKey) || "{}");
  } catch {
    preferences = {};
  }
  let fontSize = Math.min(maxFont, Math.max(minFont, Number(preferences.fontSize) || 16));
  let theme = ["light", "dark", "system"].includes(preferences.theme)
    ? preferences.theme
    : "light";

  function applyPreferences() {
    document.documentElement.style.setProperty("--user-font-size", `${fontSize}px`);
    document.body.dataset.theme = theme === "system"
      ? (matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "light")
      : theme;
    fontValue.textContent = `${fontSize}px`;
  }

  async function savePreferences() {
    preferences = { fontSize, theme };
    localStorage.setItem(storageKey, JSON.stringify(preferences));
    applyPreferences();
    try {
      await App.api.patch("me/preferences", preferences);
      App.ui.toast("Đã lưu tùy chọn giao diện.", "success");
    } catch (error) {
      App.ui.toast(`Đã lưu trên thiết bị; chưa đồng bộ được máy chủ: ${error.message}`, "error");
    }
  }

  document.getElementById("btn-font-decrease").addEventListener("click", () => {
    fontSize = Math.max(minFont, fontSize - 1);
    savePreferences();
  });
  document.getElementById("btn-font-increase").addEventListener("click", () => {
    fontSize = Math.min(maxFont, fontSize + 1);
    savePreferences();
  });
  document.getElementById("btn-theme-light").addEventListener("click", () => {
    theme = "light";
    savePreferences();
  });
  document.getElementById("btn-theme-dark").addEventListener("click", () => {
    theme = "dark";
    savePreferences();
  });
  document.getElementById("btn-theme-auto").addEventListener("click", () => {
    theme = "system";
    savePreferences();
  });

  App.auth.requireUser().then((user) => {
    if (user?.preferences) {
      fontSize = Math.min(maxFont, Math.max(minFont, Number(user.preferences.fontSize) || fontSize));
      theme = ["light", "dark", "system"].includes(user.preferences.theme)
        ? user.preferences.theme
        : theme;
    }
    applyPreferences();
  });
})();
