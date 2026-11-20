-- article_id is the MongoDB articles._id represented as 24 lowercase hex chars.
-- Cross-database existence and published-state validation belongs to the Service layer.
CREATE TABLE IF NOT EXISTS bookmarks (
    user_id TEXT NOT NULL,
    article_id TEXT NOT NULL,
    saved_at TEXT NOT NULL
        DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),

    PRIMARY KEY (user_id, article_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

    CHECK (length(article_id) = 24),
    CHECK (article_id = lower(article_id)),
    CHECK (article_id NOT GLOB '*[^0-9a-f]*')
);
