<?php
declare(strict_types=1);

namespace App\Service;

use App\Database\MongoContentRepository;

final class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_hash',
        'currentpassword',
        'newpassword',
        'token',
        'secret',
        'recoverycode',
        'resetcode',
        'csrf_token',
    ];

    public function __construct(
        private readonly MongoContentRepository $content,
    ) {
    }

    public function log(
        string $actorUserId,
        string $actorRole,
        string $action,
        string $targetType,
        string $targetId,
        array $details = [],
    ): void {
        $sanitizedDetails = $this->sanitize($details);
        $correlationId = $_SERVER['HTTP_X_CORRELATION_ID'] ?? $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        if ($correlationId) {
            $sanitizedDetails['_correlation_id'] = (string) $correlationId;
        }

        $this->content->recordActivity(
            actorId: $actorUserId,
            actorRole: $actorRole,
            action: $action,
            targetType: $targetType,
            targetId: $targetId,
            details: $sanitizedDetails,
        );
    }

    private function sanitize(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace(['_', '-'], '', (string) $key));
            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitize($value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
