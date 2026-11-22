<?php
declare(strict_types=1);

return [
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS bookmarks_v2 (
        user_id TEXT NOT NULL,
        article_id TEXT NOT NULL,
        folder_id TEXT,
        saved_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
        PRIMARY KEY (user_id, article_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (folder_id) REFERENCES favorite_folders(id) ON DELETE SET NULL
    )
    SQL,
    'INSERT OR IGNORE INTO bookmarks_v2 (user_id, article_id, saved_at) SELECT user_id, article_id, saved_at FROM bookmarks',
    'DROP TABLE bookmarks',
    'ALTER TABLE bookmarks_v2 RENAME TO bookmarks',
    'CREATE INDEX IF NOT EXISTS bookmarks_user_idx ON bookmarks(user_id, saved_at DESC)',
    'CREATE INDEX IF NOT EXISTS bookmarks_article_idx ON bookmarks(article_id)',
];
