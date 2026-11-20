(() => {
  const form = document.getElementById("form-change-password");
  const message = document.getElementById("change-password-message");

  App.auth.requireUser();
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    message.hidden = true;
    const currentPassword = document.getElementById("old-password").value;
    const newPassword = document.getElementById("new-password").value;
    const confirmation = document.getElementById("confirm-password").value;
    if (newPassword !== confirmation) {
      App.ui.showMessage(message, "Mật khẩu mới nhập lại không khớp.");
      return;
    }
    App.ui.setBusy(form, true, "Đang đổi mật khẩu...");
    try {
      await App.api.post("me/password", { currentPassword, newPassword });
      form.reset();
      App.ui.showMessage(message, "Đổi mật khẩu thành công.", "success");
    } catch (error) {
      App.ui.showMessage(message, error.message);
    } finally {
      App.ui.setBusy(form, false);
    }
  });
})();
