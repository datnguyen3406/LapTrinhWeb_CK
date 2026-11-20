(() => {
  const form = document.getElementById("form-recover-code");
  const message = document.getElementById("recover-code-message");
  const token = new URLSearchParams(window.location.search).get("token");
  const emailGroup = document.getElementById("recovery-email-group");
  const passwordGroup = document.getElementById("recovery-password-group");
  const submit = document.getElementById("btn-verify-code");

  if (token) {
    emailGroup.hidden = true;
    passwordGroup.hidden = false;
    document.getElementById("recovery-new-password").required = true;
    document.getElementById("recovery-confirm-password").required = true;
    document.getElementById("recovery-email").required = false;
    submit.textContent = "Đặt lại mật khẩu";
    document.getElementById("recovery-token").value = token;
  } else {
    emailGroup.hidden = false;
    passwordGroup.hidden = true;
    document.getElementById("recovery-email").required = true;
    submit.textContent = "Gửi liên kết đặt lại";
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    App.ui.setBusy(form, true, "Đang xử lý...");
    try {
      if (token) {
        const password = document.getElementById("recovery-new-password").value;
        const confirmation = document.getElementById("recovery-confirm-password").value;
        if (password !== confirmation) throw new Error("Mật khẩu xác nhận không khớp.");
        await App.api.post("auth/password-reset/confirm", {
          token,
          password,
        });
        App.ui.showMessage(message, "Đổi mật khẩu thành công. Vui lòng đăng nhập lại.", "success");
        form.reset();
        window.setTimeout(() => window.location.assign(App.url("HTML/Auth.html")), 1200);
      } else {
        await App.api.post("auth/password-reset/request", {
          email: document.getElementById("recovery-email").value.trim(),
        });
        App.ui.showMessage(
          message,
          "Nếu email tồn tại, hệ thống sẽ gửi hướng dẫn đặt lại mật khẩu.",
          "success",
        );
        form.reset();
      }
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
    }
  });
})();
