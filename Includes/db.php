<?php

declare(strict_types=1);

$databasePath = __DIR__ . '/../Database/database.sqlite';
$schemaPath = __DIR__ . '/../Database/schema.sql';

$pdo = new PDO('sqlite:' . $databasePath, options: [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('PRAGMA journal_mode = WAL');
$pdo->exec('PRAGMA busy_timeout = 5000');
$pdo->exec('PRAGMA foreign_keys = ON');

$schema = file_get_contents($schemaPath);
if ($schema !== false) {
    try {
        $pdo->exec($schema);
    } catch (PDOException $e) {
        // Ignored if table already exists
    }
}

// Kiểm tra và cập nhật các cột của bảng users
$userCols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
if (!in_array('is_active', $userCols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN is_active INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('activation_token', $userCols, true)) {
    $pdo->exec('ALTER TABLE users ADD COLUMN activation_token TEXT');
}
if (!in_array('role', $userCols, true)) {
    $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
}

// Kiểm tra và cập nhật các cột của bảng articles
$artCols = array_column($pdo->query('PRAGMA table_info(articles)')->fetchAll(), 'name');
if (!in_array('color', $artCols, true)) {
    $pdo->exec("ALTER TABLE articles ADD COLUMN color TEXT NOT NULL DEFAULT '#ffffff'");
}
if (!in_array('pinned_at', $artCols, true)) {
    $pdo->exec('ALTER TABLE articles ADD COLUMN pinned_at TEXT');
}
if (!in_array('is_locked', $artCols, true)) {
    $pdo->exec('ALTER TABLE articles ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('lock_password_hash', $artCols, true)) {
    $pdo->exec('ALTER TABLE articles ADD COLUMN lock_password_hash TEXT');
}
if (!in_array('created_at', $artCols, true)) {
    $pdo->exec("ALTER TABLE articles ADD COLUMN created_at TEXT NOT NULL DEFAULT ''");
    $pdo->exec("UPDATE articles SET created_at = CURRENT_TIMESTAMP WHERE created_at = ''");
}
if (!in_array('updated_at', $artCols, true)) {
    $pdo->exec("ALTER TABLE articles ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
    $pdo->exec("UPDATE articles SET updated_at = CURRENT_TIMESTAMP WHERE updated_at = ''");
}

// Đảm bảo các bảng liên kết tồn tại
$pdo->exec("CREATE TABLE IF NOT EXISTS labels (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(user_id, name)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS article_labels (
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    label_id INTEGER NOT NULL REFERENCES labels(id) ON DELETE CASCADE,
    PRIMARY KEY (article_id, label_id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS article_shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    shared_with_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission TEXT NOT NULL DEFAULT 'read' CHECK (permission IN ('read', 'edit')),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(article_id, shared_with_user_id)
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS attachments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
    file_path TEXT NOT NULL,
    file_type TEXT NOT NULL,
    file_name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)");
