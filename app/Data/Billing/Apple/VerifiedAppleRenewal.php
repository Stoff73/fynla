<?php

declare(strict_types=1);

namespace App\Data\Billing\Apple;

use Carbon\CarbonImmutable;

final readonly class VerifiedAppleRenewal
{
    public function __construct(
        public string $originalTransactionId,
        public string $productId,
        public string $autoRenewProductId,
        public int $autoRenewStatus,
        public ?CarbonImmutable $renewalDate,
        public ?int $expirationIntent,
        public ?CarbonImmutable $gracePeriodExpiresDate,
        public ?bool $isInBillingRetryPeriod,
        public string $environment,
        public ?CarbonImmutable $signedDate,
    ) {}
}
