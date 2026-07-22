<?php

declare(strict_types=1);

namespace App\Data\Billing\Apple;

final readonly class AppleReconciliationRenewalEvidence
{
    public function __construct(
        public string $signedPayloadSha256,
        public VerifiedAppleRenewal $renewal,
    ) {}
}
