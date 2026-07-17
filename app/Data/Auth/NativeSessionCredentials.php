<?php

declare(strict_types=1);

namespace App\Data\Auth;

use Carbon\CarbonImmutable;

final readonly class NativeSessionCredentials
{
    public function __construct(
        public string $accessToken,
        public CarbonImmutable $accessExpiresAt,
        public string $refreshToken,
        public CarbonImmutable $refreshExpiresAt,
        public CarbonImmutable $absoluteExpiresAt,
        public string $sessionId,
    ) {}
}
