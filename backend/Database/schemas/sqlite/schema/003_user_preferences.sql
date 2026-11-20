-- One preference row per user; created together with the account by UsersRepository.
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id TEXT PRIMARY KEY NOT NULL,

    font_size TEXT NOT NULL DEFAULT 'medium'
        CHECK (font_size IN ('small', 'medium', 'large')),

    theme TEXT NOT NULL DEFAULT 'light'
        CHECK (theme IN ('light', 'dark')),

    updated_at TEXT NOT NULL
        DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
