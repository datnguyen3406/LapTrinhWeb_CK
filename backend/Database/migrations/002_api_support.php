<?php
declare(strict_types=1);

return [
    'ALTER TABLE users ADD COLUMN email TEXT',
    'ALTER TABLE users ADD COLUMN is_locked INTEGER NOT NULL DEFAULT 0 CHECK (is_locked IN (0, 1))',
    'CREATE UNIQUE INDEX users_email_unique ON users(email COLLATE NOCASE) WHERE email IS NOT NULL',
    'ALTER TABLE user_preferences ADD COLUMN font_size_px INTEGER NOT NULL DEFAULT 16 CHECK (font_size_px BETWEEN 12 AND 24)',
    'ALTER TABLE user_preferences ADD COLUMN system_theme INTEGER NOT NULL DEFAULT 0 CHECK (system_theme IN (0, 1))',
    <<<'SQL'
        CREATE TABLE activation_tokens (
            user_id TEXT PRIMARY KEY NOT NULL,
            token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
        SQL,
    <<<'SQL'
        CREATE TABLE password_reset_tokens (
            user_id TEXT PRIMARY KEY NOT NULL,
            token_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
        SQL,
    <<<'SQL'
        CREATE TABLE favorite_folders (
            id TEXT PRIMARY KEY NOT NULL,
            user_id TEXT NOT NULL,
            name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 80),
            created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
            UNIQUE (user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
        SQL,
    <<<'SQL'
        CREATE TABLE reading_history (
            user_id TEXT NOT NULL,
            article_id TEXT NOT NULL,
            read_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
            PRIMARY KEY (user_id, article_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
        SQL,
    'CREATE INDEX favorite_folders_user_idx ON favorite_folders(user_id, created_at)',
    'CREATE INDEX reading_history_user_idx ON reading_history(user_id, read_at DESC)',
    <<<'SQL'
        CREATE TABLE user_categories (
            id TEXT PRIMARY KEY NOT NULL,
            user_id TEXT NOT NULL,
            name TEXT NOT NULL CHECK (length(trim(name)) BETWEEN 1 AND 80),
            created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
            UNIQUE (user_id, name),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
        SQL,
    <<<'SQL'
        CREATE TABLE IF NOT EXISTS bookmark_folders (
            user_id TEXT NOT NULL,
            article_id TEXT NOT NULL,
            folder_id TEXT,
            PRIMARY KEY (user_id, article_id),
            FOREIGN KEY (user_id, article_id) REFERENCES bookmarks(user_id, article_id) ON DELETE CASCADE,
            FOREIGN KEY (folder_id) REFERENCES favorite_folders(id) ON DELETE SET NULL
        )
        SQL,
    'CREATE INDEX bookmark_folders_folder_idx ON bookmark_folders(folder_id)',
];
