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
 *
 * For savings accounts, goals are resolved via the goal_savings_account pivot table
 * (with a fallback to the legacy linked_savings_account_id FK column).
 * For investment accounts, goals are resolved via the linked_investment_account_id FK column.
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

        $linkedGoals = $this->resolveLinkedGoals($account);

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
     * Resolve goals linked to the given account.
     *
     * For savings accounts (linked field = 'linked_savings_account_id'), goals are
     * resolved via the goal_savings_account pivot table first, with a fallback to
     * the legacy FK column for backwards compatibility.
     *
     * For other account types, goals are resolved via the FK column directly.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Goal>
     */
    protected function resolveLinkedGoals(Model $account): \Illuminate\Database\Eloquent\Collection
    {
        $linkedField = $this->getLinkedField();

        // Savings accounts: prefer the pivot table, fall back to legacy FK
        if ($linkedField === 'linked_savings_account_id') {
            $pivotGoals = Goal::whereHas('savingsAccounts', function ($query) use ($account) {
                $query->where('savings_account_id', $account->id);
            })
                ->where('status', 'active')
                ->get();

            if ($pivotGoals->isNotEmpty()) {
                return $pivotGoals;
            }

            // Fallback: legacy FK column (will be removed once migration is fully complete)
            return Goal::where($linkedField, $account->id)
                ->where('status', 'active')
                ->get();
        }

        // Investment accounts and other types: use the FK column directly
        return Goal::where($linkedField, $account->id)
            ->where('status', 'active')
            ->get();
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
