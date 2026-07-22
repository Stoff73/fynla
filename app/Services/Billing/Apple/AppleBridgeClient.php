<?php

declare(strict_types=1);

namespace App\Services\Billing\Apple;

interface AppleBridgeClient
{
    /** @return array<string, mixed> */
    public function call(string $operation, array $payload): array;
}
