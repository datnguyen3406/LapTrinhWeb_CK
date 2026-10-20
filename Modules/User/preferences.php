<?php

declare(strict_types=1);

require_once __DIR__ . '/../../Includes/bootstrap.php';
$user = current_user();
if ($user === null) {
    header('Location: /Modules/Auth/login.php');
    exit;
}
$statement = $pdo->prepare('SELECT theme, font_size, note_color FROM user_preferences WHERE user_id = :id');
$statement->execute(['id' => $user['id']]);
$preferences = $statement->fetch() ?: ['theme' => 'light', 'font_size' => 16, 'note_color' => '#fff3b0'];
$pageTitle = 'Tùy chọn';
require __DIR__ . '/../../Includes/header.php';
?>
<main class="form-container">
    <h1>Tùy chọn giao diện</h1>
    <form data-api-form="/api/user/preferences" data-method="PUT">
        <label>Giao diện
            <select name="theme">
                <option value="light" <?= $preferences['theme'] === 'light' ? 'selected' : '' ?>>Sáng</option>
                <option value="dark" <?= $preferences['theme'] === 'dark' ? 'selected' : '' ?>>Tối</option>
            </select>
        </label>
        <label>Cỡ chữ (12–24px) <input type="number" name="font_size" min="12" max="24" value="<?= (int) $preferences['font_size'] ?>"></label>
        <label>Màu ghi chú <input type="color" name="note_color" value="<?= escape($preferences['note_color']) ?>"></label>
        <button type="submit">Lưu tùy chọn</button>
        <p data-form-message role="status"></p>
    </form>
</main>
<?php require __DIR__ . '/../../Includes/footer.php'; ?>
