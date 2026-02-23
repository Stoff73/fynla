<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\SavingsAccount;
use Illuminate\Support\Facades\Log;

/**
 * Observer that auto-records goal contributions when a linked savings account balance changes.
 *
 * When a SavingsAccount's current_balance increases, this observer checks if any goals
 * are linked to this account (via linked_savings_account_id) and records the delta
 * as an automatic contribution.
 */
class SavingsAccountGoalObserver
{
    public function updated(SavingsAccount $account): void
    {
        $changedFields = array_keys($account->getChanges());

        if (! in_array('current_balance', $changedFields)) {
            return;
        }

        $oldBalance = (float) $account->getOriginal('current_balance');
        $newBalance = (float) $account->current_balance;
        $delta = $newBalance - $oldBalance;

        // Only record positive deltas as contributions
        if ($delta <= 0) {
            return;
        }

        // Find all active goals linked to this savings account
        $linkedGoals = Goal::where('linked_savings_account_id', $account->id)
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
                    'notes' => "Auto-tracked from {$account->institution} ({$account->account_name})",
                    'goal_balance_after' => $newAmount,
                    'streak_qualifying' => true,
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
