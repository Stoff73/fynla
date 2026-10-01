<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Prompts\UserContentSanitiser;
use App\Services\Savings\ISATracker;
use App\Services\TaxConfigService;

/** Config archetype — UK allowance figures, live from TaxConfigService (Rule #3). */
final class TaxAllowanceHandler implements FetchHandler
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly ISATracker $isaTracker,
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

            $accounts = $this->subscriptionAccounts($status);
            $value .= $accounts === []
                ? ' No account-level current-year subscription is recorded.'
                : ' Subscription accounts: '.implode('; ', $accounts).'.';
        }

        return FetchResult::make($value, 'TaxConfigService and saved ISA records', $year);
    }

    /**
     * The per-account lines from the same ISATracker status the totals above
     * come from (ledger-aware, tax-year scoped), so Fyn's breakdown always
     * adds up to the figure the Savings page shows (CSJ 2026-10-01 item 7a).
     *
     * @param  array<string, mixed>  $status
     * @return list<string>
     */
    private function subscriptionAccounts(array $status): array
    {
        return collect($status['account_breakdown'] ?? [])
            ->filter(fn (array $row): bool => (float) ($row['contributed'] ?? 0) > 0)
            ->map(fn (array $row): string => UserContentSanitiser::wrap(trim((string) ($row['account_name'] ?? 'ISA')))
                .' — '.$this->fmt((float) $row['contributed']).' subscribed')
            ->values()
            ->all();
    }

    private function fmt(mixed $amount): string
    {
        return is_numeric($amount) ? '£'.number_format((float) $amount) : 'unavailable';
    }
}
