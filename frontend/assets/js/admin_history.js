(() => {
  const tbody = document.getElementById("history-table-body");
  const filterBar = document.getElementById("history-filter-bar");

  function cell(row, value) {
    const item = document.createElement("td");
    item.textContent = value || "";
    row.append(item);
  }

  async function loadLogs() {
    App.ui.renderState(tbody, "Đang tải lịch sử...");
    const params = new URLSearchParams();
    const query = document.getElementById("history-search-input").value.trim();
    const action = document.getElementById("history-action-filter").value;
    const date = document.getElementById("history-date-filter").value;
    if (query) params.set("q", query);
    if (action) params.set("action", action);
    if (date) params.set("date", date);

    try {
      const result = await App.api.get(`admin/activity-logs?${params}`);
      if (!Array.isArray(result.items)) throw new Error("Lịch sử trả về không đúng định dạng.");
      if (!result.items.length) {
        App.ui.renderState(tbody, "Chưa có hoạt động phù hợp.");
        return;
      }
      const fragment = document.createDocumentFragment();
      for (const log of result.items) {
        const row = document.createElement("tr");
        cell(row, log.createdAt ? new Date(log.createdAt).toLocaleString("vi-VN") : "");
        cell(row, log.actorUsername);
        cell(row, log.action);
        cell(row, log.target);
        cell(row, log.details);
        fragment.append(row);
      }
      tbody.replaceChildren(fragment);
    } catch (error) {
      App.ui.renderState(tbody, error.message, "error");
    }
  }

  document.getElementById("btn-filter-history").addEventListener("click", loadLogs);
  document.getElementById("history-search-input").addEventListener("keydown", (event) => {
    if (event.key === "Enter") loadLogs();
  });
  document.getElementById("btn-clear-history-log").addEventListener("click", async (event) => {
    if (!window.confirm("Bạn có chắc muốn xóa toàn bộ lịch sử hoạt động?")) return;
    const button = event.currentTarget;
    button.disabled = true;
    try {
      await App.api.delete("admin/activity-logs");
      await loadLogs();
      App.ui.toast("Đã xóa lịch sử hoạt động.", "success");
    } catch (error) {
      App.ui.toast(error.message, "error");
    } finally {
      button.disabled = false;
    }
  });

  App.auth.requireUser("admin").then((user) => {
    if (user) loadLogs();
  });
})();
