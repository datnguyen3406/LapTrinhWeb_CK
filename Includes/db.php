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
$pdo->exec((string) file_get_contents($schemaPath));
