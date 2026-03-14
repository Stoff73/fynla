<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SavingsAccount;
use App\Traits\TracksGoalContributions;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer that auto-records goal contributions when a linked savings account balance changes.
 *
 * When a SavingsAccount's current_balance increases, this observer checks if any goals
 * are linked to this account via the goal_savings_account pivot table (with a fallback
 * to the legacy linked_savings_account_id FK) and records the delta as an automatic
 * contribution.
 */
class SavingsAccountGoalObserver
{
    use TracksGoalContributions;

    protected function getBalanceField(): string
    {
        return 'current_balance';
    }

    protected function getLinkedField(): string
    {
        return 'linked_savings_account_id';
    }

    protected function buildContributionNote(Model $account): string
    {
        /** @var SavingsAccount $account */
        return "Auto-tracked from {$account->institution} ({$account->account_name})";
    }

    protected function isAutoContributionStreakQualifying(): bool
    {
        return true;
    }
}
