CREATE INDEX IF NOT EXISTS idx_bookmarks_article_id
    ON bookmarks(article_id);

CREATE INDEX IF NOT EXISTS idx_bookmarks_user_saved_at
    ON bookmarks(user_id, saved_at DESC);
