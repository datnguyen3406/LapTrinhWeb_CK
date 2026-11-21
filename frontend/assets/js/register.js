(() => {
  const form = document.getElementById("form-register");
  const message = document.getElementById("register-message");

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    const username = document.getElementById("register-username").value.trim();
    const displayName = document.getElementById("register-name").value.trim();
    const password = document.getElementById("register-password").value;
    const confirmation = document.getElementById("register-confirm-password").value;
    if (password !== confirmation) {
      App.ui.showMessage(message, "Mật khẩu xác nhận không khớp.");
      return;
    }
    App.ui.setBusy(form, true, "Đang tạo tài khoản...");
    try {
      const result = await App.api.post("auth/register", { username, displayName, password });
      if (!result.user) throw new Error("Phản hồi đăng ký không hợp lệ.");
      if (!result.recoveryCode) throw new Error("Không nhận được mã khôi phục.");
      App.ui.showMessage(
        message,
        `Tạo tài khoản thành công. Tên đăng nhập: ${result.user.username}\nMã khôi phục: ${result.recoveryCode}\nHãy lưu mã khôi phục ở nơi an toàn.`,
        "success",
      );
      window.setTimeout(() => window.location.assign(App.url("pages/index.html")), 700);
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
    }
  });
})();
