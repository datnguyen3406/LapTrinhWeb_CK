<?php

declare(strict_types=1);

require dirname(__DIR__) . '/Includes/db.php';

$once = in_array('--once', $argv, true);
$maxAttempts = 5;

do {
    $pdo->beginTransaction();
    $pdo->exec("UPDATE jobs SET status = 'pending', locked_at = NULL
        WHERE status = 'processing' AND locked_at < datetime('now', '-5 minutes')");
    $job = $pdo->query("SELECT id, type, payload, attempts FROM jobs
        WHERE status = 'pending' AND available_at <= CURRENT_TIMESTAMP
        AND NOT EXISTS (SELECT 1 FROM jobs WHERE status = 'processing')
        ORDER BY id LIMIT 1")->fetch();
    if ($job === false) {
        $pdo->commit();
        if (!$once) {
            usleep(500000);
        }
        continue;
    }
    $pdo->prepare("UPDATE jobs SET status = 'processing', locked_at = CURRENT_TIMESTAMP, attempts = attempts + 1 WHERE id = :id")
        ->execute(['id' => $job['id']]);
    $pdo->commit();

    try {
        $payload = json_decode($job['payload'], true, 512, JSON_THROW_ON_ERROR);
        if ($job['type'] === 'article.autosave') {
            $statement = $pdo->prepare('UPDATE articles SET title = :title, content = :content, updated_at = CURRENT_TIMESTAMP
                WHERE id = :id AND author_id = :author');
            $statement->execute([
                'title' => $payload['title'], 'content' => $payload['content'],
                'id' => $payload['id'], 'author' => $payload['author_id'],
            ]);
            if ($statement->rowCount() !== 1) {
                throw new RuntimeException('Autosave target was not found or is no longer owned by the author.');
            }
            $pdo->prepare('INSERT INTO notifications (event, payload) VALUES (:event, :payload)')
                ->execute(['event' => 'article.updated', 'payload' => json_encode(['article_id' => $payload['id']], JSON_THROW_ON_ERROR)]);
        } elseif ($job['type'] === 'article.changed') {
            $pdo->prepare('INSERT INTO notifications (event, payload) VALUES (:event, :payload)')
                ->execute(['event' => 'article.updated', 'payload' => json_encode(['article_id' => $payload['id']], JSON_THROW_ON_ERROR)]);
        } elseif ($job['type'] === 'auth.password_reset_email') {
            $appUrl = rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/');
            $url = $appUrl . '/Modules/Auth/reset-password.php?token=' . rawurlencode($payload['token']);
            $subject = '=?UTF-8?B?' . base64_encode('Đặt lại mật khẩu GenK Magazine') . '?=';
            $body = "Mở liên kết sau để đặt lại mật khẩu (liên kết hết hạn sau một giờ):\n\n" . $url . "\n";
            if (!mail($payload['email'], $subject, $body, "Content-Type: text/plain; charset=UTF-8\r\n")) {
                throw new RuntimeException('mail() failed to send password-reset email; configure a mail transport.');
            }
        } else {
            throw new RuntimeException('Unknown job type: ' . $job['type']);
        }
        $pdo->prepare("UPDATE jobs SET status = 'done', locked_at = NULL, last_error = NULL WHERE id = :id")
            ->execute(['id' => $job['id']]);
    } catch (Throwable $exception) {
        $attempts = (int) $job['attempts'] + 1;
        $delay = min(3600, 2 ** min($attempts, 10));
        $status = $attempts >= $maxAttempts ? 'failed' : 'pending';
        $pdo->prepare('UPDATE jobs SET status = :status, locked_at = NULL, available_at = datetime("now", :delay),
            last_error = :error WHERE id = :id')
            ->execute([
                'status' => $status, 'delay' => '+' . $delay . ' seconds',
                'error' => mb_substr($exception->getMessage(), 0, 1000), 'id' => $job['id'],
            ]);
        fwrite(STDERR, sprintf("Job %d failed (attempt %d/%d): %s\n", $job['id'], $attempts, $maxAttempts, $exception->getMessage()));
    }
} while (!$once);
