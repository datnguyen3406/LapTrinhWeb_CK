(() => {
  const form = document.getElementById("form-admin-article");
  const list = document.getElementById("admin-articles-list");
  const categorySelect = document.getElementById("admin-article-category");
  const sourceSelect = document.getElementById("admin-article-source");
  const visibleCheck = document.getElementById("admin-article-visible");
  const field = (id) => document.getElementById(id);

  function fillOptions(select, items, placeholder) {
    select.replaceChildren();
    const first = document.createElement("option");
    first.value = "";
    first.textContent = placeholder;
    select.append(first);
    for (const item of items) {
      const option = document.createElement("option");
      option.value = item.id;
      option.textContent = item.name;
      select.append(option);
    }
  }

  function renderArticles(items) {
    list.replaceChildren();
    if (!items.length) {
      App.ui.renderState(list, "Chưa có bài báo nào trong hệ thống.");
      return;
    }
    for (const article of items) {
      const card = document.createElement("div");
      card.className = "card mb-3 shadow-sm admin-article-item";

      const cardBody = document.createElement("div");
      cardBody.className = "card-body";

      const title = document.createElement("h5");
      title.className = "card-title mb-1";
      title.textContent = article.title;

      const meta = document.createElement("div");
      meta.className = "mb-2 text-muted small";
      const catName = article.category?.name || "Chưa có danh mục";
      const srcName = article.source || "Không có nguồn";
      meta.textContent = `Chuyên mục: ${catName} | Nguồn: ${srcName}`;

      const statusBadge = document.createElement("span");
      statusBadge.className = `badge mr-2 ${article.visible ? "badge-success" : "badge-secondary"}`;
      statusBadge.textContent = article.visible ? "Đang hiển thị" : "Bản nháp / Đang ẩn";

      const btnGroup = document.createElement("div");
      btnGroup.className = "mt-3 btn-group btn-group-sm";

      const editBtn = document.createElement("button");
      editBtn.type = "button";
      editBtn.className = "btn btn-outline-primary";
      editBtn.textContent = "Sửa bài";
      editBtn.addEventListener("click", () => {
        field("admin-article-id").value = article.id;
        field("admin-article-title").value = article.title || "";
        categorySelect.value = article.categoryId || "";
        sourceSelect.value = article.sourceId || "";
        field("admin-article-summary").value = article.summary || "";
        field("admin-article-content").value = article.content || "";
        if (visibleCheck) visibleCheck.checked = Boolean(article.visible);
        field("btn-save-article").textContent = "Cập nhật bài báo";
        form.scrollIntoView({ behavior: "smooth" });
      });

      const visibilityBtn = document.createElement("button");
      visibilityBtn.type = "button";
      visibilityBtn.className = `btn ${article.visible ? "btn-outline-warning" : "btn-outline-success"}`;
      visibilityBtn.textContent = article.visible ? "Ẩn bài" : "Hiện bài";
      visibilityBtn.addEventListener("click", () => setVisibility(article, visibilityBtn));

      const removeBtn = document.createElement("button");
      removeBtn.type = "button";
      removeBtn.className = "btn btn-outline-danger";
      removeBtn.textContent = "Xóa";
      removeBtn.addEventListener("click", () => deleteArticle(article.id, removeBtn));

      btnGroup.append(editBtn, visibilityBtn, removeBtn);
      cardBody.append(title, meta, statusBadge, btnGroup);
      card.append(cardBody);
      list.append(card);
    }
  }

  async function loadArticles() {
    App.ui.renderState(list, "Đang tải bài báo...");
    try {
      const result = await App.api.get("admin/articles");
      if (!Array.isArray(result.items)) throw new Error("Danh sách bài báo không đúng định dạng.");
      renderArticles(result.items);
    } catch (error) {
      App.ui.renderState(list, error.message, "error");
    }
  }

  async function loadOptions() {
    const [categories, sources] = await Promise.all([
      App.api.get("categories"),
      App.api.get("sources"),
    ]);
    if (!Array.isArray(categories.items) || !Array.isArray(sources.items)) {
      throw new Error("Danh mục hoặc nguồn báo không đúng định dạng.");
    }
    fillOptions(categorySelect, categories.items, "-- Chọn danh mục bài báo --");
    fillOptions(sourceSelect, sources.items, "-- Chọn nguồn báo --");
  }

  async function setVisibility(article, button) {
    button.disabled = true;
    try {
      await App.api.patch(`admin/articles/${encodeURIComponent(article.id)}`, {
        visible: !article.visible,
      });
      await loadArticles();
      App.ui.toast("Đã cập nhật trạng thái bài báo.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
      button.disabled = false;
    }
  }

  async function deleteArticle(id, button) {
    if (!window.confirm("Bạn có chắc muốn xóa bài báo này?")) return;
    button.disabled = true;
    try {
      await App.api.delete(`admin/articles/${encodeURIComponent(id)}`);
      await loadArticles();
      App.ui.toast("Đã xóa bài báo thành công.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
      button.disabled = false;
    }
  }

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    App.ui.setBusy(form, true, "Đang lưu bài báo...");
    const id = field("admin-article-id").value;
    const article = {
      title: field("admin-article-title").value.trim(),
      categoryId: categorySelect.value,
      sourceId: sourceSelect.value,
      summary: field("admin-article-summary").value.trim(),
      content: field("admin-article-content").value.trim(),
      visible: visibleCheck ? visibleCheck.checked : true,
    };
    try {
      if (id) await App.api.patch(`admin/articles/${encodeURIComponent(id)}`, article);
      else await App.api.post("admin/articles", article);
      form.reset();
      field("admin-article-id").value = "";
      if (visibleCheck) visibleCheck.checked = true;
      field("btn-save-article").textContent = "Lưu bài báo";
      await loadArticles();
      App.ui.toast(id ? "Đã cập nhật bài báo." : "Đã đăng bài báo thành công!", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    } finally {
      App.ui.setBusy(form, false);
    }
  });

  document.getElementById("btn-reset-article").addEventListener("click", () => {
    field("admin-article-id").value = "";
    if (visibleCheck) visibleCheck.checked = true;
    field("btn-save-article").textContent = "Lưu bài báo";
  });

  App.auth.requireUser("admin").then(async (user) => {
    if (!user) return;
    try {
      await loadOptions();
      await loadArticles();
    } catch (error) {
      App.ui.renderState(list, error.message, "error");
    }
  });
})();
