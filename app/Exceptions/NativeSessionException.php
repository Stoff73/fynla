<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class NativeSessionException extends HttpException
{
    public function __construct(public readonly string $errorCode)
    {
        parent::__construct(401, $errorCode);
    }
}
