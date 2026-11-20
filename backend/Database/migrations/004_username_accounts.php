<?php
declare(strict_types=1);

return [
    <<<'SQL'
        ALTER TABLE users ADD COLUMN username TEXT
            CHECK (
                username IS NULL OR (
                    length(username) BETWEEN 3 AND 32
                    AND username NOT GLOB '*[^A-Za-z0-9_.-]*'
                )
            )
        SQL,
    <<<'SQL'
        UPDATE users
        SET username = CASE
            WHEN role = 'admin' AND id = (
                SELECT id FROM users
                WHERE role = 'admin'
                ORDER BY created_at ASC, id ASC
                LIMIT 1
            ) THEN 'admin'
            WHEN role = 'admin' THEN 'admin_' || substr(replace(id, '-', ''), 1, 12)
            ELSE 'reader_' || substr(replace(id, '-', ''), 1, 12)
        END
        WHERE username IS NULL
        SQL,
    'CREATE UNIQUE INDEX users_username_unique ON users(username COLLATE NOCASE)',
    'UPDATE users SET is_active = 1',
    'DROP TABLE IF EXISTS activation_tokens',
    'DROP INDEX IF EXISTS users_email_unique',
    'ALTER TABLE users DROP COLUMN email',
];
