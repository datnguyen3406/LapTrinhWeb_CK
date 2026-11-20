(() => {
  const form = document.getElementById("form-recover-code");
  const message = document.getElementById("recover-code-message");
  const usernameGroup = document.getElementById("recovery-username-group");
  const codeGroup = document.getElementById("recovery-code-group");
  const passwordGroup = document.getElementById("recovery-password-group");
  const submit = document.getElementById("btn-verify-code");
  const codeInput = document.getElementById("recovery-code");
  const passwordInput = document.getElementById("recovery-new-password");
  const confirmationInput = document.getElementById("recovery-confirm-password");
  let resetRequested = false;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    App.ui.setBusy(form, true, "Đang xử lý...");
    try {
      if (resetRequested) {
        const password = passwordInput.value;
        const confirmation = confirmationInput.value;
        if (password !== confirmation) throw new Error("Mật khẩu xác nhận không khớp.");
        await App.api.post("auth/password-reset/confirm", {
          token: codeInput.value.trim(),
          password,
        });
        App.ui.showMessage(message, "Đổi mật khẩu thành công! Hệ thống đang chuyển đến trang Đăng nhập...", "success");
        form.reset();
        window.setTimeout(() => window.location.assign(App.url("pages/Auth.html")), 1200);
      } else {
        const result = await App.api.post("auth/password-reset/request", {
          username: document.getElementById("recovery-username").value.trim(),
        });
        if (!result.resetCode) {
          App.ui.showMessage(message, result.message || "Không tìm thấy tài khoản.");
          return;
        }
        usernameGroup.hidden = true;
        codeGroup.hidden = false;
        passwordGroup.hidden = false;
        codeInput.required = true;
        codeInput.value = result.resetCode; // Tự động điền mã cho người dùng
        passwordInput.required = true;
        confirmationInput.required = true;
        submit.textContent = "Xác nhận & Đổi mật khẩu";
        resetRequested = true;

        App.ui.showMessage(
          message,
          `Mã khôi phục của bạn là: ${result.resetCode} (Hết hạn sau 15 phút). Đã tự động điền vào ô bên dưới, vui lòng nhập mật khẩu mới để hoàn tất!`,
          "success",
        );
      }
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
      if (resetRequested) submit.textContent = "Xác nhận & Đổi mật khẩu";
    }
  });
})();
