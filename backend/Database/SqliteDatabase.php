<?php
declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class SqliteDatabase
{
    private PDO $pdo;

    public function __construct(string $root)
    {
        $env = Environment::load($root . '/.env');
        $databasePath = $env['SQLITE_PATH'] ?? './data/news_reader.sqlite';
        $databasePath = $databasePath === ':memory:'
            ? $databasePath
            : (str_starts_with($databasePath, '/') ? $databasePath : $root . '/Database/' . $databasePath);

        if ($databasePath !== ':memory:') {
            $directory = dirname($databasePath);
            if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
                throw new RuntimeException('Không thể tạo thư mục database.');
            }
        }

        $this->pdo = new PDO('sqlite:' . $databasePath, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        if ($databasePath !== ':memory:') {
            $this->pdo->exec('PRAGMA journal_mode = WAL');
        }
        $this->initializeSchema($root);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function initializeSchema(string $root): void
    {
        $schemaRoot = $root . '/Database/schemas/sqlite';
        foreach (['schema', 'indexes', 'triggers'] as $group) {
            $files = glob($schemaRoot . '/' . $group . '/*.sql') ?: [];
            sort($files, SORT_STRING);
            foreach ($files as $file) {
                $sql = file_get_contents($file);
                if ($sql === false) {
                    throw new RuntimeException('Không thể đọc schema database.');
                }
                $this->pdo->exec($sql);
            }
        }

        $migrations = [
            2 => [
                'PHP API accounts, folders and reading history',
                require $root . '/Database/migrations/002_api_support.php',
            ],
            3 => [
                'Invalidate user sessions after password reset',
                require $root . '/Database/migrations/003_session_version.php',
            ],
            4 => [
                'Migrate accounts to usernames',
                require $root . '/Database/migrations/004_username_accounts.php',
            ],
            5 => [
                'Article metadata, categories and synchronization status',
                require $root . '/Database/migrations/005_articles_and_categories.php',
            ],
            6 => [
                'Fix bookmarks and reading history article_id constraints',
                require $root . '/Database/migrations/006_fix_bookmarks_and_history.php',
            ],
        ];
        $migration = $this->pdo->prepare('SELECT 1 FROM schema_migrations WHERE version = ?');
        $insert = $this->pdo->prepare(
            'INSERT INTO schema_migrations (version, description) VALUES (?, ?)',
        );
        foreach ($migrations as $version => [$description, $statements]) {
            $migration->execute([$version]);
            if ($migration->fetchColumn()) {
                continue;
            }
            $this->pdo->beginTransaction();
            try {
                foreach ($statements as $statement) {
                    $this->pdo->exec($statement);
                }
                $insert->execute([$version, $description]);
                $this->pdo->commit();
            } catch (\Throwable $error) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $error;
            }
        }
    }
}
