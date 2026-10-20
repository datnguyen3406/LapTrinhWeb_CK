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
if ($schema === false) {
    throw new RuntimeException('Unable to read Database/schema.sql.');
}
$pdo->exec($schema);

$articleColumns = array_column($pdo->query('PRAGMA table_info(articles)')->fetchAll(), 'name');
if (!in_array('author_id', $articleColumns, true)) {
    $pdo->exec('ALTER TABLE articles ADD COLUMN author_id INTEGER REFERENCES users(id) ON DELETE SET NULL');
}
if (!in_array('is_pinned', $articleColumns, true)) {
    $pdo->exec('ALTER TABLE articles ADD COLUMN is_pinned INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('updated_at', $articleColumns, true)) {
    $pdo->exec("ALTER TABLE articles ADD COLUMN updated_at TEXT NOT NULL DEFAULT ''");
    $pdo->exec("UPDATE articles SET updated_at = CURRENT_TIMESTAMP WHERE updated_at = ''");
}
if (!in_array('is_locked', $articleColumns, true)) {
    $pdo->exec('ALTER TABLE articles ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0');
}
if (!in_array('lock_password_hash', $articleColumns, true)) {
    $pdo->exec('ALTER TABLE articles ADD COLUMN lock_password_hash TEXT');
}
