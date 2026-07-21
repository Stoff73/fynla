<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Models\Investment\InvestmentAccount;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Prompts\UserContentSanitiser;
use App\Services\Savings\ISATracker;
use App\Services\Stores\SavingsStore;
use App\Services\TaxConfigService;

/** Config archetype — UK allowance figures, live from TaxConfigService (Rule #3). */
final class TaxAllowanceHandler implements FetchHandler
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly ISATracker $isaTracker,
        private readonly SavingsStore $savingsStore,
    ) {}

    public function id(): string
    {
        return 'tax_allowance';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $year = $this->taxConfig->getTaxYear();

        $isaAllowance = $isa['annual_allowance'] ?? null;
        $pensionAllowance = $pension['annual_allowance'] ?? null;

        $value = 'ISA annual allowance for '.$year.': '.$this->fmt($isaAllowance).'. '
            .'Pension annual allowance: '.$this->fmt($pensionAllowance).'.';

        if (preg_match('/\bisa\b/i', $ctx->query) === 1) {
            $status = $this->isaTracker->getISAAllowanceStatus($ctx->user->id, $year);
            $value .= ' Recorded ISA subscriptions for '.$year.': '
                .$this->fmt($status['total_used']).' used; '
                .$this->fmt($status['remaining']).' remaining. '
                .'Breakdown: Cash ISA '.$this->fmt($status['cash_isa_used']).'; '
                .'Stocks & Shares ISA '.$this->fmt($status['stocks_shares_isa_used']).'; '
                .'Lifetime ISA '.$this->fmt($status['lisa_used']).'.';

            $accounts = $this->subscriptionAccounts($ctx, $year);
            $value .= $accounts === []
                ? ' No account-level current-year subscription is recorded.'
                : ' Subscription accounts: '.implode('; ', $accounts).'.';
        }

        return FetchResult::make($value, 'TaxConfigService and saved ISA records', $year);
    }

    /** @return list<string> */
    private function subscriptionAccounts(FetchContext $ctx, string $year): array
    {
        $accounts = $this->savingsStore->forUser($ctx->user)
            ->filter(fn ($account): bool => (bool) $account->is_isa
                && $account->isa_subscription_year === $year
                && (float) ($account->isa_subscription_amount ?? 0) > 0)
            ->map(function ($account): string {
                $name = trim((string) ($account->account_name ?: $account->institution ?: 'Cash ISA'));

                return UserContentSanitiser::wrap($name).' — '
                    .$this->fmt((float) $account->isa_subscription_amount).' subscribed';
            })
            ->values()
            ->all();

        $investmentAccounts = InvestmentAccount::where('user_id', $ctx->user->id)
            ->where('account_type', 'isa')
            ->get()
            ->filter(fn (InvestmentAccount $account): bool => ($account->tax_year === null || $account->tax_year === $year)
                && (float) ($account->isa_subscription_current_year ?? 0) > 0)
            ->map(function (InvestmentAccount $account): string {
                $name = trim((string) ($account->account_name ?: $account->provider ?: 'Stocks & Shares ISA'));

                return UserContentSanitiser::wrap($name).' — '
                    .$this->fmt((float) $account->isa_subscription_current_year).' subscribed';
            })
            ->values()
            ->all();

        return [...$accounts, ...$investmentAccounts];
    }

    private function fmt(mixed $amount): string
    {
        return is_numeric($amount) ? '£'.number_format((float) $amount) : 'unavailable';
    }
}
