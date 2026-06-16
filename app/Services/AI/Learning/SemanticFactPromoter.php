<?php

declare(strict_types=1);

namespace App\Services\AI\Learning;

use App\Models\ProposedSemanticFact;
use App\Services\AI\Memory\UserSemanticStore;

/**
 * The ONLY path that writes a per-user semantic fact — and only from an approved
 * proposal. Never touches the global corpus. (CoALA Phase 6, no-auto-apply.)
 */
final class SemanticFactPromoter
{
    public function __construct(private readonly UserSemanticStore $store) {}

    public function approve(ProposedSemanticFact $fact, int $reviewerId): void
    {
        $this->store->put($fact->user_id, $fact->fact_id, $fact->title, $fact->body);

        $fact->forceFill(['status' => 'approved', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()])->save();
    }

    public function reject(ProposedSemanticFact $fact, int $reviewerId): void
    {
        $fact->forceFill(['status' => 'rejected', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()])->save();
    }
}
