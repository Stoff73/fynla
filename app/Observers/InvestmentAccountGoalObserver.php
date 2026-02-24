<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Investment\InvestmentAccount;
use App\Traits\TracksGoalContributions;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer that auto-records goal contributions when a linked investment account value changes.
 *
 * When an InvestmentAccount's current_value increases, this observer checks if any goals
 * are linked to this account (via linked_investment_account_id) and records the delta
 * as an automatic contribution.
 */
class InvestmentAccountGoalObserver
{
    use TracksGoalContributions;

    protected function getBalanceField(): string
    {
        return 'current_value';
    }

    protected function getLinkedField(): string
    {
        return 'linked_investment_account_id';
    }

    protected function buildContributionNote(Model $account): string
    {
        /** @var InvestmentAccount $account */
        return "Auto-tracked from {$account->provider} ({$account->account_name})";
    }
}
