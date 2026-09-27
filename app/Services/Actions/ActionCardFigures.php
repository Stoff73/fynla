<?php

declare(strict_types=1);

namespace App\Services\Actions;

/**
 * The "Why this matters for you" bullets for a tax action's card, read from the
 * figures its strategy already published on the composed plan item — never
 * recomputed here, never invented. A strategy type with no entry below gets no
 * bullets rather than a guessed sentence.
 */
final class ActionCardFigures
{
    /**
     * Allowances that are set per tax year, so the card shows the tax-year end
     * as its deadline. ISA: https://www.gov.uk/individual-savings-accounts/how-isas-work;
     * pension annual allowance: https://www.gov.uk/tax-on-your-private-pension/annual-allowance;
     * dividend allowance: https://www.gov.uk/tax-on-dividends.
     */
    public const ANNUAL_ALLOWANCE_TYPES = [
        'isa_topup_vs_psa', 'isa_topup_spouse', 'bed_and_isa', 'junior_isa', 'lifetime_isa',
        'pension_tax_relief', 'pension_aa_carry_forward', 'non_earner_spouse_pension',
        'dividend_allowance_harvest',
    ];

    /**
     * Tax actions that move the user's money, so the card offers "Fund from"
     * (design C; CSJ 2026-09-26).
     */
    public const FUNDED_TYPES = [
        'isa_topup_vs_psa', 'pension_tax_relief', 'non_earner_spouse_pension', 'junior_isa', 'lifetime_isa',
    ];

    /**
     * @param  array<string, mixed>  $item  a composed tax plan item
     * @return list<string>
     */
    public static function why(array $item): array
    {
        $pounds = static fn (mixed $v): string => '£'.number_format((float) $v);
        $percent = static fn (mixed $v): string => rtrim(rtrim(number_format((float) $v * 100, 1), '0'), '.').'%';

        return match ((string) ($item['type'] ?? '')) {
            'pension_tax_relief' => array_values(array_filter([
                isset($item['suggested_contribution'], $item['relief_rate'])
                    ? sprintf('%s of your income is taxed at %s', $pounds($item['suggested_contribution']), $percent($item['relief_rate']))
                    : null,
                isset($item['relief_rate'])
                    ? sprintf('Pension contributions get tax relief at that %s rate', $percent($item['relief_rate']))
                    : null,
            ])),
            'salary_sacrifice_ni' => array_values(array_filter([
                isset($item['annual_contribution'])
                    ? sprintf('You pay %s a year into your workplace pension from your pay', $pounds($item['annual_contribution']))
                    : null,
                isset($item['employee_ni_saving'])
                    ? sprintf('Under salary sacrifice that pay is not subject to your National Insurance, saving %s a year', $pounds($item['employee_ni_saving']))
                    : null,
            ])),
            default => [],
        };
    }
}
