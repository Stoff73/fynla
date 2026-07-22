<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProposedSemanticFact;
use App\Models\User;
use App\Services\AI\Learning\SemanticFactPromoter;
use Illuminate\Console\Command;

class FynSemanticPromote extends Command
{
    protected $signature = 'fyn:semantic:promote {fact : proposed_semantic_facts.id} {--reviewer= : admin user id}';

    protected $description = 'Approve a staged per-user semantic fact (CoALA Phase 6).';

    public function handle(SemanticFactPromoter $promoter): int
    {
        $fact = ProposedSemanticFact::find($this->argument('fact'));
        if ($fact === null || $fact->status !== 'pending') {
            $this->error('No pending fact with that id.');

            return self::FAILURE;
        }

        $reviewerId = (int) ($this->option('reviewer') ?: User::where('email', 'chris@fynla.org')->value('id'));
        $promoter->approve($fact, $reviewerId);
        $this->info("Promoted fact {$fact->fact_id} for user {$fact->user_id}.");

        return self::SUCCESS;
    }
}
