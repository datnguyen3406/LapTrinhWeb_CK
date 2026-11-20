<?php
declare(strict_types=1);

namespace App\Database;

use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Command;
use RuntimeException;

final class MongoDatabase
{
    private Manager $manager;

    public function __construct(private readonly array $config)
    {
        if (!extension_loaded('mongodb')) {
            throw new RuntimeException('PHP MongoDB extension is required.');
        }
        $this->manager = new Manager($config['MONGODB_URI'] ?? 'mongodb://127.0.0.1:27017');
    }

    /** @return list<object> */
    public function find(string $database, string $collection, array $filter = [], array $options = []): array
    {
        $cursor = $this->manager->executeQuery(
            $database . '.' . $collection,
            new Query($filter, $options),
        );
        return iterator_to_array($cursor, false);
    }

    public function count(string $database, string $collection, array $filter = []): int
    {
        $cursor = $this->manager->executeCommand(
            $database,
            new Command(['count' => $collection, 'query' => (object) $filter]),
        );
        $results = $cursor->toArray();
        $result = $results[0] ?? null;
        return (int) ($result->n ?? 0);
    }

    public function command(string $database, array $command): object
    {
        $cursor = $this->manager->executeCommand($database, new Command($command));
        $results = $cursor->toArray();
        $result = $results[0] ?? null;
        if (!is_object($result)) {
            throw new RuntimeException('MongoDB returned an empty command response.');
        }
        return $result;
    }

    public function insert(string $database, string $collection, array $document): string
    {
        $write = new BulkWrite();
        $id = $document['_id'] ?? new \MongoDB\BSON\ObjectId();
        $document['_id'] = $id;
        $write->insert($document);
        $this->manager->executeBulkWrite($database . '.' . $collection, $write);
        return (string) $id;
    }

    public function update(
        string $database,
        string $collection,
        array $filter,
        array $changes,
        bool $upsert = false,
    ): int {
        $write = new BulkWrite();
        $write->update($filter, ['$set' => $changes], ['multi' => false, 'upsert' => $upsert]);
        $result = $this->manager->executeBulkWrite($database . '.' . $collection, $write);
        return $result->getModifiedCount() + $result->getUpsertedCount();
    }

    public function delete(string $database, string $collection, array $filter, bool $multi = false): int
    {
        $write = new BulkWrite();
        $write->delete($filter, ['limit' => $multi ? 0 : 1]);
        return $this->manager->executeBulkWrite($database . '.' . $collection, $write)->getDeletedCount();
    }

    public function readFile(string $database, string $fileId): ?array
    {
        $id = self::objectId($fileId);
        $files = $this->find($database, 'media.files', ['_id' => $id], ['limit' => 1]);
        if (!$files) {
            return null;
        }
        $file = $files[0];
        $chunks = $this->find($database, 'media.chunks', ['files_id' => $id], ['sort' => ['n' => 1]]);
        $content = '';
        foreach ($chunks as $chunk) {
            $content .= $chunk->data->getData();
        }
        return ['file' => $file, 'content' => $content];
    }

    public static function objectId(string $value): \MongoDB\BSON\ObjectId
    {
        if (!preg_match('/^[a-f0-9]{24}$/i', $value)) {
            throw new \InvalidArgumentException('Mã nội dung không hợp lệ.');
        }
        return new \MongoDB\BSON\ObjectId($value);
    }
}
