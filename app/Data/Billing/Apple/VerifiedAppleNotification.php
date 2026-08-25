<?php

declare(strict_types=1);

namespace App\Data\Billing\Apple;

final readonly class VerifiedAppleNotification
{
    public function __construct(
        public string $notificationUuid,
        public string $notificationType,
        public ?string $subtype,
        public string $environment,
        public ?VerifiedAppleTransaction $transaction,
        public ?VerifiedAppleRenewal $renewal,
        public ?string $transactionSignedPayloadSha256 = null,
        public ?string $renewalSignedPayloadSha256 = null,
    ) {}
}
