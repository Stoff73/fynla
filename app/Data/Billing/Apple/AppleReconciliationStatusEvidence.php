<?php

declare(strict_types=1);

namespace App\Data\Billing\Apple;

final readonly class AppleReconciliationStatusEvidence
{
    public const ACTIVE = 1;

    public const EXPIRED = 2;

    public const BILLING_RETRY = 3;

    public const BILLING_GRACE_PERIOD = 4;

    public const REVOKED = 5;

    public const VALUES = [
        self::ACTIVE,
        self::EXPIRED,
        self::BILLING_RETRY,
        self::BILLING_GRACE_PERIOD,
        self::REVOKED,
    ];

    public function __construct(
        public string $originalTransactionId,
        public int $subscriptionStatus,
        public ?AppleReconciliationTransactionEvidence $transaction,
        public ?AppleReconciliationRenewalEvidence $renewal,
    ) {}
}
