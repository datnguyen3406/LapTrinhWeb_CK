<?php

declare(strict_types=1);

namespace App\Realtime;

use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use PDO;
use SplObjectStorage;

final class ArticleHub implements MessageComponentInterface
{
    private SplObjectStorage $clients;
    private array $rooms = [];

    public function __construct(private readonly string $secret, private readonly PDO $pdo)
    {
        $this->clients = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $connection): void
    {
        parse_str($connection->httpRequest->getUri()->getQuery(), $query);
        $articleId = filter_var($query['article_id'] ?? null, FILTER_VALIDATE_INT);
        $token = (string) ($query['token'] ?? '');
        if ($articleId === false || $articleId === null || !$this->validToken($token, $articleId)) {
            $connection->close();
            return;
        }
        $this->clients->attach($connection, $articleId);
        $this->rooms[$articleId] ??= new SplObjectStorage();
        $this->rooms[$articleId]->attach($connection);
    }

    public function onMessage(ConnectionInterface $from, $message): void
    {
        if (!$this->clients->contains($from) || strlen((string) $message) > 16384) {
            return;
        }
        $articleId = $this->clients[$from];
        try {
            $payload = json_decode((string) $message, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }
        if (!is_array($payload) || ($payload['type'] ?? '') !== 'article.draft') {
            return;
        }
        $outgoing = json_encode([
            'type' => 'article.draft',
            'article_id' => $articleId,
            'title' => mb_substr((string) ($payload['title'] ?? ''), 0, 240),
            'content' => mb_substr((string) ($payload['content'] ?? ''), 0, 12000),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        foreach ($this->rooms[$articleId] as $client) {
            if ($client !== $from) {
                $client->send($outgoing);
            }
        }
    }

    public function onClose(ConnectionInterface $connection): void
    {
        if (!$this->clients->contains($connection)) {
            return;
        }
        $room = $this->clients[$connection];
        $this->rooms[$room]->detach($connection);
        if ($this->rooms[$room]->count() === 0) {
            unset($this->rooms[$room]);
        }
        $this->clients->detach($connection);
    }

    public function onError(ConnectionInterface $connection, \Exception $exception): void
    {
        error_log('WebSocket error: ' . $exception->getMessage());
        $connection->close();
    }

    public function publishPendingNotifications(): void
    {
        $notifications = $this->pdo->query("SELECT id, event, payload FROM notifications
            WHERE delivered_at IS NULL ORDER BY id LIMIT 100")->fetchAll();
        foreach ($notifications as $notification) {
            $payload = json_decode($notification['payload'], true, 32, JSON_THROW_ON_ERROR);
            $articleId = (int) ($payload['article_id'] ?? 0);
            if ($notification['event'] === 'article.updated' && isset($this->rooms[$articleId])) {
                $message = json_encode([
                    'type' => 'article.updated',
                    'article_id' => $articleId,
                ], JSON_THROW_ON_ERROR);
                foreach ($this->rooms[$articleId] as $client) {
                    $client->send($message);
                }
            }
            $this->pdo->prepare('UPDATE notifications SET delivered_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute(['id' => $notification['id']]);
        }
    }

    private function validToken(string $token, int $expectedArticle): bool
    {
        $encoded = strtr($token, '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            return false;
        }
        $parts = explode('.', $decoded);
        if (count($parts) !== 4 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) || !ctype_digit($parts[2])) {
            return false;
        }
        [$articleId, $userId, $expires, $signature] = $parts;
        $message = $articleId . '.' . $userId . '.' . $expires;
        return (int) $articleId === $expectedArticle
            && (int) $expires >= time()
            && (int) $expires <= time() + 301
            && hash_equals(hash_hmac('sha256', $message, $this->secret), $signature);
    }
}
