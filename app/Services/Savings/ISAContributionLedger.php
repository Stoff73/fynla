<?php

declare(strict_types=1);

namespace App\Services\Savings;

use App\Models\Investment\InvestmentAccount;
use App\Models\ISAContribution;
use App\Models\SavingsAccount;
use App\Services\Stores\IngestSource;
use App\Services\TaxConfigService;

class ISAContributionLedger
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function syncSavingsAnnualSummary(SavingsAccount $account, IngestSource $source): void
    {
        if (! $account->is_isa
            || ! $account->isa_subscription_year
            || $account->isa_subscription_amount === null) {
            return;
        }

        $this->syncAnnualSummary(
            $account->user_id,
            SavingsAccount::class,
            $account->id,
            $account->isa_subscription_year,
            (float) $account->isa_subscription_amount,
            $source,
        );
    }

    public function syncInvestmentAnnualSummary(InvestmentAccount $account, IngestSource $source): void
    {
        if ($account->account_type !== 'isa' || $account->isa_subscription_current_year === null) {
            return;
        }

        $this->syncAnnualSummary(
            $account->user_id,
            InvestmentAccount::class,
            $account->id,
            $account->tax_year ?: $this->taxConfig->getTaxYear(),
            (float) $account->isa_subscription_current_year,
            $source,
        );
    }

    private function syncAnnualSummary(
        int $userId,
        string $accountType,
        int $accountId,
        string $taxYear,
        float $amount,
        IngestSource $source,
    ): void {
        ISAContribution::updateOrCreate(
            [
                'user_id' => $userId,
                'account_type' => $accountType,
                'account_id' => $accountId,
                'tax_year' => $taxYear,
                'entry_type' => 'annual_summary',
            ],
            [
                'contribution_date' => null,
                'amount' => $amount,
                'source' => $source->value,
                'provenance' => 'captured_annual_summary',
            ],
        );
    }
}
