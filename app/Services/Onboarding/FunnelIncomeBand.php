<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Services\TaxConfigService;
use LogicException;

/**
 * Resolves the stable SaveTax funnel income-band keys against the active tax
 * configuration so page labels, estimates, recaps and mismatch checks agree.
 */
final class FunnelIncomeBand
{
    private const KEYS = ['zero', 'upto_50270', '50271_100000', '100001_125140', 'over_125140'];

    public static function isKnown(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    public static function inBand(string $key, float $figure): bool
    {
        if (! self::isKnown($key)) {
            return false;
        }

        [$min, $max] = self::ranges()[$key];

        return $figure >= $min && ($max === null || $figure <= $max);
    }

    public static function label(string $key): string
    {
        if (! self::isKnown($key)) {
            return '';
        }

        $ranges = self::ranges();

        return match ($key) {
            'zero' => 'no income',
            'upto_50270' => 'up to '.self::money((int) $ranges[$key][1]),
            '50271_100000', '100001_125140' => self::money((int) $ranges[$key][0]).'–'.self::money((int) $ranges[$key][1]),
            'over_125140' => 'over '.self::money((int) $ranges[$key][0] - 1),
        };
    }

    public static function recapLabel(string $key): string
    {
        $label = self::label($key);

        return $label === '' || $key === 'zero' ? '' : 'earning '.$label;
    }

    /**
     * Preserve the monetary interpretation presented when the public funnel
     * was submitted. Stable keys alone are insufficient across a tax-year
     * rollover because their configured boundaries can move.
     *
     * @return array{key:string,tax_year:string,label:string,recap_label:string,minimum:float,maximum:float|null}
     */
    public static function context(string $key): array
    {
        if (! self::isKnown($key)) {
            throw new LogicException("Unsupported SaveTax income band: {$key}");
        }

        [$minimum, $maximum] = self::ranges()[$key];

        return [
            'key' => $key,
            'tax_year' => app(TaxConfigService::class)->getTaxYear(),
            'label' => self::label($key),
            'recap_label' => self::recapLabel($key),
            'minimum' => $minimum,
            'maximum' => $maximum,
        ];
    }

    public static function inContext(string $key, array $context, float $figure): bool
    {
        [$minimum, $maximum] = self::contextBounds($key, $context);

        return $figure >= $minimum && ($maximum === null || $figure <= $maximum);
    }

    public static function contextLabel(string $key, array $context, bool $recap = false): string
    {
        self::contextBounds($key, $context);
        $field = $recap ? 'recap_label' : 'label';
        $label = $context[$field] ?? null;
        if ($recap && $key === 'zero' && $label === '') {
            return '';
        }
        if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 100) {
            throw new LogicException("Invalid SaveTax income context {$field}.");
        }

        return trim($label);
    }

    /** @return array<string, string> */
    public static function pageLabels(): array
    {
        $ranges = self::ranges();

        return [
            'upto_50270' => 'Up to '.self::money((int) $ranges['upto_50270'][1]),
            '50271_100000' => self::money((int) $ranges['50271_100000'][0]).' to '.self::money((int) $ranges['50271_100000'][1]),
            '100001_125140' => self::money((int) $ranges['100001_125140'][0]).' to '.self::money((int) $ranges['100001_125140'][1]),
            'over_125140' => 'Above '.self::money((int) $ranges['over_125140'][0] - 1),
        ];
    }

    public static function assumedIncome(string $key, mixed $overBandAssumption = null): int
    {
        if (! self::isKnown($key)) {
            throw new LogicException("Unsupported SaveTax income band: {$key}");
        }

        $ranges = self::ranges();
        if ($key !== 'over_125140') {
            return (int) $ranges[$key][1];
        }

        $taperEnd = (int) $ranges['100001_125140'][1];
        if (! is_numeric($overBandAssumption) || (int) $overBandAssumption <= $taperEnd) {
            throw new LogicException('Invalid onboarding.savetax_over_band_assumed_income configuration.');
        }

        return (int) $overBandAssumption;
    }

    /** @return array<string, array{0: float, 1: float|null}> */
    private static function ranges(): array
    {
        $incomeTax = app(TaxConfigService::class)->getIncomeTax();
        $number = static function (string $key) use ($incomeTax): float {
            $value = $incomeTax[$key] ?? null;
            if (! is_numeric($value)) {
                throw new LogicException("Missing required tax configuration: income_tax.{$key}");
            }

            return (float) $value;
        };

        $bands = is_array($incomeTax['bands'] ?? null) ? $incomeTax['bands'] : [];
        $higherRateThreshold = $incomeTax['higher_rate_threshold'] ?? null;
        if (! is_numeric($higherRateThreshold)) {
            foreach ($bands as $band) {
                if (is_array($band)
                    && str_contains(mb_strtolower((string) ($band['name'] ?? '')), 'basic')
                    && is_numeric($band['upper_limit'] ?? null)) {
                    $higherRateThreshold = $band['upper_limit'];
                    break;
                }
            }
        }
        if (! is_numeric($higherRateThreshold)) {
            throw new LogicException('Missing required tax configuration: income_tax.higher_rate_threshold');
        }

        $higherRateThreshold = (float) $higherRateThreshold;
        $taperStart = $number('personal_allowance_taper_threshold');
        $personalAllowance = $number('personal_allowance');
        $taperRate = $number('personal_allowance_taper_rate');

        if ($higherRateThreshold < 0
            || $personalAllowance <= 0
            || $taperRate <= 0
            || $higherRateThreshold >= $taperStart) {
            throw new LogicException('SaveTax income thresholds are inconsistent.');
        }

        $taperEnd = round($taperStart + ($personalAllowance / $taperRate));
        if ($taperEnd <= $taperStart) {
            throw new LogicException('SaveTax Personal Allowance taper range is invalid.');
        }

        return [
            'zero' => [0.0, 0.0],
            'upto_50270' => [0.0, $higherRateThreshold],
            '50271_100000' => [$higherRateThreshold + 1, $taperStart],
            '100001_125140' => [$taperStart + 1, $taperEnd],
            'over_125140' => [$taperEnd + 1, null],
        ];
    }

    /** @return array{0: float, 1: float|null} */
    private static function contextBounds(string $key, array $context): array
    {
        if (! self::isKnown($key) || ($context['key'] ?? null) !== $key) {
            throw new LogicException('SaveTax income context does not match its band key.');
        }
        $taxYear = $context['tax_year'] ?? null;
        $minimum = $context['minimum'] ?? null;
        $maximum = $context['maximum'] ?? null;
        if (! is_string($taxYear) || trim($taxYear) === '' || ! is_numeric($minimum)
            || ($maximum !== null && ! is_numeric($maximum))) {
            throw new LogicException('SaveTax income context is incomplete.');
        }

        $minimum = (float) $minimum;
        $maximum = $maximum === null ? null : (float) $maximum;
        if ($minimum < 0 || ($maximum !== null && $maximum < $minimum)) {
            throw new LogicException('SaveTax income context boundaries are invalid.');
        }

        return [$minimum, $maximum];
    }

    private static function money(int $amount): string
    {
        return '£'.number_format($amount);
    }
}
