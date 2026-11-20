(() => {
  const form = document.getElementById("form-login");
  const message = document.getElementById("login-message");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    const username = document.getElementById("login-username").value.trim();
    const password = document.getElementById("login-password").value;
    App.ui.setBusy(form, true, "Đang đăng nhập...");
    try {
      const result = await App.api.post("auth/login", { username, password });
      if (!result.user) throw new Error("Phản hồi đăng nhập không hợp lệ.");
      const requested = new URLSearchParams(window.location.search).get("next");
      const pageRoot = new URL("pages/", App.url("")).pathname;
      const next = requested?.startsWith(pageRoot)
        ? new URL(requested, App.url("")).href
        : result.user.role === "admin"
          ? App.url("pages/Admin/Dashboard.html")
          : App.url("pages/index.html");
      window.location.assign(next);
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
    }
  });
})();
