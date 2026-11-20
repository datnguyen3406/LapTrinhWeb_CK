(() => {
  const form = document.getElementById("form-register");
  const message = document.getElementById("register-message");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    const email = document.getElementById("register-email").value.trim();
    const displayName = document.getElementById("register-name").value.trim();
    const password = document.getElementById("register-password").value;
    const confirmation = document.getElementById("register-confirm-password").value;
    if (password !== confirmation) {
      App.ui.showMessage(message, "Mật khẩu xác nhận không khớp.");
      return;
    }
    App.ui.setBusy(form, true, "Đang tạo tài khoản...");
    try {
      const result = await App.api.post("auth/register", { email, displayName, password });
      if (!result.user) throw new Error("Phản hồi đăng ký không hợp lệ.");
      App.ui.showMessage(message, "Đăng ký thành công. Email kích hoạt đã được gửi.", "success");
      window.setTimeout(() => window.location.assign(App.url("HTML/index.html")), 900);
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
    }
  });
})();
