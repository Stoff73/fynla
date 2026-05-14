<?php

declare(strict_types=1);

namespace App\Services\Stores\Exceptions;

use RuntimeException;

class StoreValidationException extends RuntimeException
{
    public function __construct(public readonly array $errors, string $message = 'Store validation failed')
    {
        parent::__construct($message);
    }
}
