<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Goal;
use App\Models\GoalContribution;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Trait for observers that auto-record goal contributions when a linked account balance changes.
 *
 * Used by InvestmentAccountGoalObserver and SavingsAccountGoalObserver.
 */
trait TracksGoalContributions
{
    /**
     * Handle the model updated event.
     */
    public function updated(Model $account): void
    {
        $balanceField = $this->getBalanceField();
        $changedFields = array_keys($account->getChanges());

        if (! in_array($balanceField, $changedFields)) {
            return;
        }

        $oldBalance = (float) $account->getOriginal($balanceField);
        $newBalance = (float) $account->{$balanceField};
        $delta = $newBalance - $oldBalance;

        // Only record positive deltas as contributions
        if ($delta <= 0) {
            return;
        }

        $linkedGoals = Goal::where($this->getLinkedField(), $account->id)
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
                    'notes' => $this->buildContributionNote($account),
                    'goal_balance_after' => $newAmount,
                    'streak_qualifying' => $this->isAutoContributionStreakQualifying(),
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

    /**
     * Get the balance/value field name on the model.
     */
    abstract protected function getBalanceField(): string;

    /**
     * Get the goal linked field name (e.g. 'linked_savings_account_id').
     */
    abstract protected function getLinkedField(): string;

    /**
     * Build the contribution note string.
     */
    abstract protected function buildContributionNote(Model $account): string;

    /**
     * Whether automatic contributions from this account type qualify for streaks.
     */
    protected function isAutoContributionStreakQualifying(): bool
    {
        return false;
    }
}
