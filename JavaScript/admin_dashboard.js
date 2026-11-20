(() => {
  const stats = document.getElementById("admin-stats-slot");

  function addStat(label, value) {
    const card = document.createElement("article");
    card.className = "stat-card";
    const title = document.createElement("h2");
    title.textContent = label;
    const count = document.createElement("p");
    count.textContent = String(value ?? 0);
    card.append(title, count);
    return card;
  }

  App.auth.requireUser("admin").then(async (user) => {
    if (!user) return;
    App.ui.renderState(stats, "Đang tải thống kê...");
    try {
      const result = await App.api.get("admin/dashboard");
      stats.replaceChildren(
        addStat("Tài khoản", result.usersCount),
        addStat("Bài báo", result.articlesCount),
        addStat("Bài đang hiển thị", result.visibleArticlesCount),
      );
    } catch (error) {
      App.ui.renderState(stats, error.message, "error");
    }
  });
})();
