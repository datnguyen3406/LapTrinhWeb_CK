(() => {
  const form = document.getElementById("form-recover-code");
  const message = document.getElementById("recover-code-message");
  const codeGroup = document.getElementById("recovery-code-group");
  const passwordGroup = document.getElementById("recovery-password-group");
  const submit = document.getElementById("btn-verify-code");
  const usernameInput = document.getElementById("recovery-username");
  const codeInput = document.getElementById("recovery-code");
  const passwordInput = document.getElementById("recovery-new-password");
  const confirmationInput = document.getElementById("recovery-confirm-password");
  codeGroup.hidden = false;
  passwordGroup.hidden = false;
  codeInput.required = true;
  passwordInput.required = true;
  confirmationInput.required = true;

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    App.ui.setBusy(form, true, "Đang xử lý...");
    try {
      const password = passwordInput.value;
      if (password !== confirmationInput.value) throw new Error("Mật khẩu xác nhận không khớp.");
      const result = await App.api.post("auth/password-reset/confirm", {
        username: usernameInput.value.trim(),
        recoveryKey: codeInput.value.trim(),
        password,
      });
      App.ui.showMessage(
        message,
        `Đặt lại mật khẩu thành công. Mã khôi phục mới: ${result.recoveryCode}. Đang chuyển đến trang Đăng nhập...`,
        "success",
      );
      form.reset();
      window.setTimeout(() => window.location.assign(App.url("pages/Auth.html")), 1200);
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
    }
  });
})();
