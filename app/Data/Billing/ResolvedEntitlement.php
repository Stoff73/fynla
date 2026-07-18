<?php

declare(strict_types=1);

namespace App\Data\Billing;

use Carbon\CarbonImmutable;

final readonly class ResolvedEntitlement
{
    public function __construct(
        public string $tier,
        public ?string $provider,
        public string $status,
        public bool $renews,
        public ?CarbonImmutable $periodEndsAt,
    ) {}
}
