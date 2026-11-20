(() => {
  const form = document.getElementById("form-profile");
  const fileInput = document.getElementById("profile-avatar-file");
  const message = document.getElementById("profile-message");
  const avatar = document.getElementById("avatar-preview");
  const recoveryBtn = document.getElementById("btn-generate-recovery");
  const recoveryInput = document.getElementById("profile-recovery-code");
  const maxAvatarBytes = 5 * 1024 * 1024;
  const allowedTypes = new Set(["image/jpeg", "image/png", "image/webp"]);
  let selectedAvatar = null;
  let previewUrl = "";

  async function loadProfile() {
    const user = await App.auth.requireUser();
    if (!user) return;
    document.getElementById("profile-name").value = user.displayName || "";
    document.getElementById("profile-username").value = user.username || "";
    const avatarUrl = App.safeUrl(user.avatarUrl || "");
    if (avatarUrl) avatar.src = avatarUrl;
  }

  if (fileInput) {
    fileInput.addEventListener("change", () => {
      selectedAvatar = fileInput.files[0] || null;
      if (!selectedAvatar) return;
      if (!allowedTypes.has(selectedAvatar.type)) {
        fileInput.value = "";
        selectedAvatar = null;
        App.ui.showMessage(message, "Ảnh đại diện phải có định dạng JPEG, PNG hoặc WebP.");
        return;
      }
      if (selectedAvatar.size > maxAvatarBytes) {
        fileInput.value = "";
        selectedAvatar = null;
        App.ui.showMessage(message, "Ảnh đại diện không được lớn hơn 5 MB.");
        return;
      }
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(selectedAvatar);
      avatar.src = previewUrl;
      message.hidden = true;
    });
  }

  if (form) {
    form.addEventListener("submit", async (event) => {
      event.preventDefault();
      message.hidden = true;
      App.ui.setBusy(form, true, "Đang lưu thay đổi...");
      try {
        const displayName = document.getElementById("profile-name").value.trim();
        if (!displayName) throw new Error("Vui lòng nhập tên hiển thị.");
        const result = await App.api.patch("me", { displayName });
        if (result?.user) {
          document.getElementById("profile-name").value = result.user.displayName;
        }

        if (selectedAvatar) {
          const data = new FormData();
          data.append("avatar", selectedAvatar);
          const uploaded = await App.api.upload("me/avatar", data);
          const uploadedUrl = App.safeUrl(uploaded?.avatarUrl || "");
          if (!uploadedUrl) throw new Error("Máy chủ chưa trả về đường dẫn ảnh đại diện hợp lệ.");
          avatar.src = uploadedUrl;
          selectedAvatar = null;
          fileInput.value = "";
          URL.revokeObjectURL(previewUrl);
          previewUrl = "";
        }
        App.ui.showMessage(message, "Đã cập nhật thông tin hồ sơ thành công!", "success");
      } catch (error) {
        App.ui.showMessage(message, error.message);
      } finally {
        App.ui.setBusy(form, false);
      }
    });
  }

  if (recoveryBtn && recoveryInput) {
    recoveryBtn.addEventListener("click", async () => {
      recoveryBtn.disabled = true;
      try {
        const result = await App.api.post("me/recovery-code");
        recoveryInput.value = result.recoveryCode || "";
        App.ui.showMessage(
          message,
          result.message || "Đã tạo mã khôi phục mới thành công! Hãy lưu lại để sử dụng khi cần.",
          "success",
        );
      } catch (error) {
        App.ui.showMessage(message, error.message);
      } finally {
        recoveryBtn.disabled = false;
      }
    });
  }

  loadProfile().catch((error) => App.ui.showMessage(message, error.message));
})();
