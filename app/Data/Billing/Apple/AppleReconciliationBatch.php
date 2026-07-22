<?php

declare(strict_types=1);

namespace App\Data\Billing\Apple;

final readonly class AppleReconciliationBatch
{
    /**
     * @param  list<AppleReconciliationTransactionEvidence>  $transactions
     * @param  list<AppleReconciliationStatusEvidence>  $statuses
     */
    public function __construct(
        public string $originalTransactionId,
        public array $transactions,
        public array $statuses,
    ) {}
}
