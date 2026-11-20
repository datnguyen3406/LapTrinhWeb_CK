(() => {
  const form = document.getElementById("form-admin-source");
  const list = document.getElementById("admin-sources-list");
  const search = document.getElementById("search-source-input");
  const categoryFilter = document.getElementById("filter-source-category");
  const field = (id) => document.getElementById(id);
  let sources = [];

  function renderSources() {
    const query = search.value.trim().toLocaleLowerCase();
    const category = categoryFilter.value;
    const visible = sources.filter((source) =>
      source.name.toLocaleLowerCase().includes(query)
      && (!category || source.category === category));
    list.replaceChildren();
    if (!visible.length) {
      App.ui.renderState(list, "Không có nguồn báo phù hợp.");
      return;
    }
    for (const source of visible) {
      const row = document.createElement("article");
      row.className = "admin-list-row";
      const name = document.createElement("h3");
      name.textContent = source.name;
      const safeSourceUrl = App.safeUrl(source.url);
      const url = safeSourceUrl ? document.createElement("a") : document.createElement("p");
      if (safeSourceUrl) {
        url.href = safeSourceUrl;
        url.target = "_blank";
        url.rel = "noopener noreferrer";
      }
      url.textContent = source.url;
      const categoryLabel = document.createElement("p");
      categoryLabel.textContent = source.category;
      const edit = document.createElement("button");
      edit.type = "button";
      edit.textContent = "Sửa";
      edit.addEventListener("click", () => {
        field("admin-source-id").value = source.id;
        field("admin-source-name").value = source.name;
        field("admin-source-url").value = source.url;
        field("admin-source-category").value = source.category;
        field("btn-save-source").textContent = "Cập nhật nguồn";
        form.scrollIntoView({ behavior: "smooth" });
      });
      const remove = document.createElement("button");
      remove.type = "button";
      remove.textContent = "Xóa";
      remove.addEventListener("click", () => deleteSource(source.id, remove));
      row.append(name, url, categoryLabel, edit, remove);
      list.append(row);
    }
  }

  async function loadSources() {
    App.ui.renderState(list, "Đang tải nguồn báo...");
    try {
      const result = await App.api.get("admin/sources");
      if (!Array.isArray(result.items)) throw new Error("Danh sách nguồn báo không đúng định dạng.");
      sources = result.items;
      renderSources();
    } catch (error) {
      App.ui.renderState(list, error.message, "error");
    }
  }

  async function deleteSource(id, button) {
    if (!window.confirm("Bạn có chắc muốn xóa nguồn báo này?")) return;
    button.disabled = true;
    try {
      await App.api.delete(`admin/sources/${encodeURIComponent(id)}`);
      await loadSources();
      App.ui.toast("Đã xóa nguồn báo.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
      button.disabled = false;
    }
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    App.ui.setBusy(form, true, "Đang lưu...");
    const id = field("admin-source-id").value;
    const source = {
      name: field("admin-source-name").value.trim(),
      url: field("admin-source-url").value.trim(),
      category: field("admin-source-category").value,
    };
    if (!App.safeUrl(source.url)) {
      App.ui.toast("Địa chỉ nguồn phải bắt đầu bằng http:// hoặc https://.", "error");
      App.ui.setBusy(form, false);
      return;
    }
    try {
      if (id) await App.api.patch(`admin/sources/${encodeURIComponent(id)}`, source);
      else await App.api.post("admin/sources", source);
      form.reset();
      field("admin-source-id").value = "";
      field("btn-save-source").textContent = "Lưu nguồn";
      await loadSources();
      App.ui.toast(id ? "Đã cập nhật nguồn báo." : "Đã thêm nguồn báo.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    } finally {
      App.ui.setBusy(form, false);
    }
  });

  document.getElementById("btn-cancel-source").addEventListener("click", () => {
    field("admin-source-id").value = "";
    field("btn-save-source").textContent = "Lưu nguồn";
  });
  document.getElementById("btn-search-source").addEventListener("click", renderSources);
  document.getElementById("btn-reset-source-filter").addEventListener("click", () => {
    search.value = "";
    categoryFilter.value = "";
    renderSources();
  });
  search.addEventListener("input", renderSources);
  categoryFilter.addEventListener("change", renderSources);

  App.auth.requireUser("admin").then((user) => {
    if (user) loadSources();
  });
})();
