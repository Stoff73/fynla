<?php

declare(strict_types=1);

namespace App\Services\Stores\Exceptions;

use RuntimeException;

class TierLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $entityKey,
        public readonly int $currentCount,
        public readonly ?int $hardLimit,
        string $message = 'Tier limit exceeded'
    ) {
        parent::__construct($message);
    }
}
