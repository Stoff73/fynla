<?php

declare(strict_types=1);

namespace App\Data\Billing\Apple;

use Carbon\CarbonImmutable;

final readonly class VerifiedAppleTransaction
{
    public function __construct(
        public string $transactionId,
        public string $originalTransactionId,
        public string $bundleId,
        public string $environment,
        public string $productId,
        public string $appAccountToken,
        public ?CarbonImmutable $purchaseDate,
        public ?CarbonImmutable $expiresDate,
        public ?CarbonImmutable $revocationDate,
        public ?string $ownershipType,
        public ?string $transactionReason,
        public ?CarbonImmutable $signedDate,
    ) {}
}
