(() => {
  const container = document.getElementById("admin-users-list");

  function renderUsers(users) {
    if (!users.length) {
      App.ui.renderState(container, "Chưa có tài khoản.");
      return;
    }
    const table = document.createElement("table");
    table.className = "data-table";
    const head = document.createElement("thead");
    const headerRow = document.createElement("tr");
    for (const label of ["Tên đăng nhập", "Tên hiển thị", "Vai trò", "Trạng thái", "Thao tác"]) {
      const th = document.createElement("th");
      th.textContent = label;
      headerRow.append(th);
    }
    head.append(headerRow);
    const body = document.createElement("tbody");

    for (const user of users) {
      const row = document.createElement("tr");
      for (const value of [
        user.username,
        user.displayName,
        user.role,
        user.isLocked ? "Đã khóa" : "Hoạt động",
      ]) {
        const cell = document.createElement("td");
        cell.textContent = value || "";
        row.append(cell);
      }
      const actions = document.createElement("td");
      const role = document.createElement("select");
      role.setAttribute("aria-label", `Vai trò của ${user.username}`);
      for (const optionValue of ["reader", "admin"]) {
        const option = document.createElement("option");
        option.value = optionValue;
        option.textContent = optionValue === "admin" ? "Admin" : "Reader";
        role.append(option);
      }
      role.value = user.role;
      role.addEventListener("change", () => updateUser(user.id, { role: role.value }, role));
      const lock = document.createElement("button");
      lock.type = "button";
      lock.textContent = user.isLocked ? "Mở khóa" : "Khóa";
      lock.addEventListener("click", () => updateUser(user.id, { isLocked: !user.isLocked }, lock));
      actions.append(role, lock);
      row.append(actions);
      body.append(row);
    }
    table.append(head, body);
    container.replaceChildren(table);
  }

  async function updateUser(id, changes, control) {
    control.disabled = true;
    try {
      await App.api.patch(`admin/users/${encodeURIComponent(id)}`, changes);
      await loadUsers();
      App.ui.toast("Đã cập nhật tài khoản.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
      control.disabled = false;
    }
  }

  async function loadUsers() {
    App.ui.renderState(container, "Đang tải tài khoản...");
    try {
      const result = await App.api.get("admin/users");
      if (!Array.isArray(result.items)) throw new Error("Danh sách tài khoản không đúng định dạng.");
      renderUsers(result.items);
    } catch (error) {
      App.ui.renderState(container, error.message, "error");
    }
  }

  App.auth.requireUser("admin").then((user) => {
    if (user) loadUsers();
  });
})();
