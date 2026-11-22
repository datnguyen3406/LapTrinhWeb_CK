<?php
declare(strict_types=1);

use App\Database\Environment;
use App\Database\MongoDatabase;
use App\Database\SqliteDatabase;

$backendRoot = dirname(__DIR__, 2);
spl_autoload_register(static function (string $class) use ($backendRoot): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $backendRoot . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

try {
    new SqliteDatabase($backendRoot);
    $env = Environment::load($backendRoot . '/.env');
    $mongo = new MongoDatabase($env);
    $contentDb = $env['MONGODB_CONTENT_DB'] ?? 'news_content';
    $auditDb = $env['MONGODB_AUDIT_DB'] ?? 'news_audit';
    $mongo->command('admin', ['ping' => 1]);

    $collectionDefinitions = [
        [$contentDb, 'articles', [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['updated_at'],
                'properties' => [
                    'blocks' => ['bsonType' => 'array'],
                    'content' => ['bsonType' => 'array'],
                    'content_plain_text' => ['bsonType' => 'string'],
                    'title' => ['bsonType' => 'string'],
                    'slug' => ['bsonType' => 'string'],
                    'summary' => ['bsonType' => 'string'],
                    'status' => ['bsonType' => 'string'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ],
        ]],
        [$contentDb, 'categories', [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['name', 'slug', 'is_active', 'sort_order', 'created_at', 'updated_at'],
                'properties' => [
                    'name' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 100],
                    'slug' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 150],
                    'is_active' => ['bsonType' => 'bool'],
                    'sort_order' => ['bsonType' => ['int', 'long', 'double']],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ],
        ]],
        [$contentDb, 'news_sources', [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['name', 'slug', 'is_active', 'created_at', 'updated_at'],
                'properties' => [
                    'name' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 160],
                    'slug' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 200],
                    'website_url' => ['bsonType' => ['string', 'null'], 'maxLength' => 2048],
                    'is_active' => ['bsonType' => 'bool'],
                    'created_at' => ['bsonType' => 'date'],
                    'updated_at' => ['bsonType' => 'date'],
                ],
            ],
        ]],
        [$contentDb, 'media.files', [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['filename', 'length', 'chunkSize', 'uploadDate'],
                'properties' => [
                    'filename' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 255],
                    'length' => ['bsonType' => ['long', 'int', 'double']],
                    'chunkSize' => ['bsonType' => ['int', 'long']],
                    'uploadDate' => ['bsonType' => 'date'],
                ],
            ],
        ]],
        [$contentDb, 'media.chunks', [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['files_id', 'n', 'data'],
                'properties' => [
                    'files_id' => ['bsonType' => 'objectId'],
                    'n' => ['bsonType' => ['int', 'long']],
                    'data' => ['bsonType' => 'binData'],
                ],
            ],
        ]],
        [$auditDb, 'activity_logs', [
            '$jsonSchema' => [
                'bsonType' => 'object',
                'required' => ['actor_role', 'action', 'target', 'details', 'created_at'],
                'properties' => [
                    'actor_user_id' => ['bsonType' => ['string', 'null']],
                    'actor_role' => ['enum' => ['guest', 'reader', 'admin', 'system']],
                    'action' => ['bsonType' => 'string', 'minLength' => 1, 'maxLength' => 120],
                    'target' => [
                        'bsonType' => 'object',
                        'required' => ['type', 'id'],
                        'properties' => [
                            'type' => ['enum' => ['user', 'article', 'category', 'source', 'media', 'auth', 'session', 'system']],
                            'id' => ['bsonType' => 'string', 'maxLength' => 128],
                        ],
                    ],
                    'details' => ['bsonType' => 'object'],
                    'created_at' => ['bsonType' => 'date'],
                ],
            ],
        ]],
    ];
    foreach ($collectionDefinitions as [$database, $collection, $validator]) {
        try {
            $mongo->command($database, [
                'create' => $collection,
                'validator' => $validator,
                'validationLevel' => 'strict',
                'validationAction' => 'error',
            ]);
        } catch (MongoDB\Driver\Exception\CommandException $error) {
            if ($error->getCode() !== 48) {
                throw $error;
            }
            $mongo->command($database, [
                'collMod' => $collection,
                'validator' => $validator,
                'validationLevel' => 'strict',
                'validationAction' => 'error',
            ]);
        }
    }

    $indexes = [
        [$contentDb, 'articles', [
            ['key' => ['slug' => 1], 'name' => 'uq_articles_slug', 'unique' => true, 'sparse' => true],
            ['key' => ['status' => 1, 'published_at' => -1], 'name' => 'idx_articles_publication'],
            ['key' => ['category_ids' => 1, 'status' => 1, 'published_at' => -1], 'name' => 'idx_articles_category_publication'],
            ['key' => ['source_id' => 1, 'status' => 1, 'published_at' => -1], 'name' => 'idx_articles_source_publication'],
            [
                'key' => ['title' => 'text', 'summary' => 'text', 'content_plain_text' => 'text'],
                'name' => 'text_articles_search',
                'weights' => ['title' => 10, 'summary' => 5, 'content_plain_text' => 2],
                'default_language' => 'none',
            ],
        ]],
        [$contentDb, 'categories', [
            ['key' => ['slug' => 1], 'name' => 'uq_categories_slug', 'unique' => true],
            ['key' => ['is_active' => 1, 'sort_order' => 1, 'name' => 1], 'name' => 'idx_categories_navigation'],
        ]],
        [$contentDb, 'news_sources', [
            ['key' => ['slug' => 1], 'name' => 'uq_news_sources_slug', 'unique' => true],
            ['key' => ['is_active' => 1, 'name' => 1], 'name' => 'idx_news_sources_active_name'],
        ]],
        [$contentDb, 'media.files', [
            ['key' => ['filename' => 1, 'uploadDate' => 1], 'name' => 'idx_media_filename_upload_date'],
        ]],
        [$contentDb, 'media.chunks', [
            ['key' => ['files_id' => 1, 'n' => 1], 'name' => 'uq_media_chunks_files_n', 'unique' => true],
        ]],
        [$auditDb, 'activity_logs', [
            ['key' => ['created_at' => -1], 'name' => 'idx_activity_created_at'],
            ['key' => ['actor_user_id' => 1, 'created_at' => -1], 'name' => 'idx_activity_actor_time'],
            ['key' => ['target.type' => 1, 'target.id' => 1, 'created_at' => -1], 'name' => 'idx_activity_target_time'],
        ]],
    ];
    foreach ($indexes as [$database, $collection, $specifications]) {
        $mongo->command($database, ['createIndexes' => $collection, 'indexes' => $specifications]);
    }
    fwrite(STDOUT, "SQLite and MongoDB schemas are ready.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Database setup failed: {$error->getMessage()}\n");
    exit(1);
}
