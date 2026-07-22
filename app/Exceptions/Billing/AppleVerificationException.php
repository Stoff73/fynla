<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use RuntimeException;

final class AppleVerificationException extends RuntimeException
{
    public readonly string $errorCode;

    public readonly bool $retryable;

    public function __construct(string $errorCode, bool $retryable = false)
    {
        if (preg_match('/\A[a-z][a-z0-9_]{0,63}\z/D', $errorCode) !== 1) {
            $errorCode = 'verifier_unavailable';
            $retryable = true;
        }

        $this->errorCode = $errorCode;
        $this->retryable = $retryable;

        parent::__construct($errorCode);
    }
}
