<?php
declare(strict_types=1);

namespace App\API\Http;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 400,
        public readonly string $errorCode = 'BAD_REQUEST',
    ) {
        parent::__construct($message);
    }
}
