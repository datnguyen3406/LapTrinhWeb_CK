(() => {
  const form = document.getElementById("form-login");
  const message = document.getElementById("login-message");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    const email = document.getElementById("login-email").value.trim();
    const password = document.getElementById("login-password").value;
    App.ui.setBusy(form, true, "Đang đăng nhập...");
    try {
      const result = await App.api.post("auth/login", { email, password });
      if (!result.user) throw new Error("Phản hồi đăng nhập không hợp lệ.");
      const requested = new URLSearchParams(window.location.search).get("next");
      const next = requested && requested.startsWith("/HTML/") && !requested.startsWith("//")
        ? requested
        : result.user.role === "admin"
          ? "/HTML/Admin/Dashboard.html"
          : "/HTML/index.html";
      window.location.assign(new URL(next, App.url("")).href);
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
    }
  });
})();
