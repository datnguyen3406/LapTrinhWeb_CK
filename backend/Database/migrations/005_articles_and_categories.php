<?php
declare(strict_types=1);

return [
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS categories (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL UNIQUE,
        slug TEXT NOT NULL UNIQUE,
        description TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
        updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now'))
    )
    SQL,
    'CREATE UNIQUE INDEX IF NOT EXISTS uq_categories_slug ON categories(slug)',
    'CREATE INDEX IF NOT EXISTS idx_categories_active_sort ON categories(is_active, sort_order, name)',
    <<<'SQL'
    CREATE TABLE IF NOT EXISTS articles (
        article_id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        summary TEXT,
        author_id TEXT NOT NULL,
        category_id TEXT,
        source_id TEXT,
        thumbnail_url TEXT,
        publication_status TEXT NOT NULL DEFAULT 'DRAFT'
            CHECK (publication_status IN ('DRAFT', 'PUBLISHED', 'HIDDEN', 'DELETED')),
        content_sync_status TEXT NOT NULL DEFAULT 'PENDING'
            CHECK (content_sync_status IN ('PENDING', 'READY', 'FAILED', 'SYNC_PENDING')),
        revision INTEGER NOT NULL DEFAULT 1 CHECK (revision >= 1),
        published_at TEXT,
        created_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
        updated_at TEXT NOT NULL DEFAULT (strftime('%Y-%m-%dT%H:%M:%fZ', 'now')),
        FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE RESTRICT,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    )
    SQL,
    'CREATE UNIQUE INDEX IF NOT EXISTS uq_articles_slug ON articles(slug)',
    'CREATE INDEX IF NOT EXISTS idx_articles_status_sync ON articles(publication_status, content_sync_status, updated_at DESC)',
    'CREATE INDEX IF NOT EXISTS idx_articles_category ON articles(category_id, publication_status)',
    'CREATE INDEX IF NOT EXISTS idx_articles_author ON articles(author_id)',
];
