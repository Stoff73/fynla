<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Returns the public-facing tax allowance display values for the active
 * UK tax year — used by the marketing campaign pages (e.g. /savetax) so
 * the headline numbers stay in sync with the seeded TaxConfiguration.
 *
 * Intentionally NOT a dump of the full TaxConfigService — only the
 * eight headline allowances + tax year + HICBC threshold the page
 * displays. Cached for 1 hour since tax-year config changes are rare.
 */
class TaxAllowancesController extends Controller
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function show(): JsonResponse
    {
        $payload = Cache::remember(
            'public.tax-allowances.' . $this->taxConfig->getTaxYear(),
            now()->addHour(),
            fn (): array => $this->buildPayload()
        );

        return response()->json($payload);
    }

    private function buildPayload(): array
    {
        return [
            'tax_year' => $this->taxConfig->getTaxYear(),
            'income_allowances' => [
                [
                    'key' => 'personal_allowance',
                    'label' => 'Personal Allowance',
                    'note' => 'Tax-free income each year',
                    'amount' => (int) $this->taxConfig->get('income_tax.personal_allowance', 0),
                ],
                [
                    'key' => 'savings_allowance',
                    'label' => 'Savings Allowance',
                    'note' => 'Basic-rate taxpayers',
                    'amount' => (int) $this->taxConfig->get('income_tax.personal_savings_allowance.basic', 0),
                ],
                [
                    'key' => 'starting_rate_for_savings',
                    'label' => 'Starting Rate for Savings',
                    'note' => 'If non-savings income < £17,570',
                    'amount' => (int) $this->taxConfig->get('income_tax.starting_rate_for_savings.band', 0),
                ],
                [
                    'key' => 'marriage_allowance',
                    'label' => 'Marriage Allowance',
                    'note' => 'Transferable to spouse',
                    'amount' => (int) $this->taxConfig->get('income_tax.marriage_allowance.amount', 0),
                ],
            ],
            'investment_allowances' => [
                [
                    'key' => 'isa_allowance',
                    'label' => 'ISA Allowance',
                    'note' => 'Tax-free savings & investing',
                    'amount' => (int) $this->taxConfig->get('isa.annual_allowance', 0),
                ],
                [
                    'key' => 'cgt_allowance',
                    'label' => 'Capital Gains Tax Allowance',
                    'note' => 'Capital gains exempt amount',
                    'amount' => (int) $this->taxConfig->get('capital_gains_tax.annual_exempt_amount', 0),
                ],
                [
                    'key' => 'dividend_allowance',
                    'label' => 'Dividend Allowance',
                    'note' => 'Tax-free dividend income',
                    'amount' => (int) $this->taxConfig->get('dividend_tax.allowance', 0),
                ],
                [
                    'key' => 'pension_annual_allowance',
                    'label' => 'Pension Annual Allowance',
                    'note' => 'Tax-relievable contributions',
                    'amount' => (int) $this->taxConfig->get('pension.annual_allowance', 0),
                ],
            ],
            'thresholds' => [
                'hicbc_threshold' => (int) $this->taxConfig->get('income_tax.personal_allowance_taper_threshold', 0),
                'starting_rate_for_savings_income_limit' => (int) $this->taxConfig->get('income_tax.personal_allowance', 0)
                    + (int) $this->taxConfig->get('income_tax.starting_rate_for_savings.band', 0),
            ],
        ];
    }
}
