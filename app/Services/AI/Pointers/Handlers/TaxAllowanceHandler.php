<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\TaxConfigService;

/** Config archetype — UK allowance figures, live from TaxConfigService (Rule #3). */
final class TaxAllowanceHandler implements FetchHandler
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

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

        return FetchResult::make($value, 'TaxConfigService', $year);
    }

    private function fmt(mixed $amount): string
    {
        return is_numeric($amount) ? '£'.number_format((float) $amount) : 'unavailable';
    }
}
