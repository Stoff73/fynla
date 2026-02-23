<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\Investment\InvestmentAccount;
use Illuminate\Support\Facades\Log;

/**
 * Observer that auto-records goal contributions when a linked investment account value changes.
 *
 * When an InvestmentAccount's current_value increases, this observer checks if any goals
 * are linked to this account (via linked_investment_account_id) and records the delta
 * as an automatic contribution.
 */
class InvestmentAccountGoalObserver
{
    public function updated(InvestmentAccount $account): void
    {
        $changedFields = array_keys($account->getChanges());

        if (! in_array('current_value', $changedFields)) {
            return;
        }

        $oldValue = (float) $account->getOriginal('current_value');
        $newValue = (float) $account->current_value;
        $delta = $newValue - $oldValue;

        // Only record positive deltas as contributions
        if ($delta <= 0) {
            return;
        }

        // Find all active goals linked to this investment account
        $linkedGoals = Goal::where('linked_investment_account_id', $account->id)
            ->where('status', 'active')
            ->get();

        if ($linkedGoals->isEmpty()) {
            return;
        }

        foreach ($linkedGoals as $goal) {
            try {
                $newAmount = (float) $goal->current_amount + $delta;

                GoalContribution::create([
                    'goal_id' => $goal->id,
                    'user_id' => $account->user_id,
                    'amount' => $delta,
                    'contribution_date' => now()->toDateString(),
                    'contribution_type' => 'automatic',
                    'notes' => "Auto-tracked from {$account->provider} ({$account->account_name})",
                    'goal_balance_after' => $newAmount,
                    'streak_qualifying' => false,
                ]);

                $goal->update([
                    'current_amount' => $newAmount,
                    'last_contribution_date' => now()->toDateString(),
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to record auto-contribution for goal {$goal->id}: {$e->getMessage()}");
            }
        }
    }
}
