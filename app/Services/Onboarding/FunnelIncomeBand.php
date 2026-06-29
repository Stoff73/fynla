<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * Maps the SaveTax funnel income band keys (public/pages/savetax.php) to their
 * numeric ranges so the onboarding director can tell when a captured income
 * figure contradicts the band the user picked on the website. Funnel-data
 * interpretation only — not a tax calculation (Rule #2 does not apply).
 */
final class FunnelIncomeBand
{
    /** key => [min, max] inclusive; null max = open-ended. 'zero' = exactly 0. */
    private const RANGES = [
        'zero' => [0.0, 0.0],
        'upto_50270' => [0.0, 50270.0],
        '50271_100000' => [50271.0, 100000.0],
        '100001_125140' => [100001.0, 125140.0],
        'over_125140' => [125141.0, null],
    ];

    private const LABELS = [
        'zero' => 'no income',
        'upto_50270' => 'up to £50,270',
        '50271_100000' => '£50,271–£100,000',
        '100001_125140' => '£100,001–£125,140',
        'over_125140' => 'over £125,140',
    ];

    public static function isKnown(string $key): bool
    {
        return array_key_exists($key, self::RANGES);
    }

    public static function inBand(string $key, float $figure): bool
    {
        if (! self::isKnown($key)) {
            return false;
        }

        [$min, $max] = self::RANGES[$key];

        return $figure >= $min && ($max === null || $figure <= $max);
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? '';
    }
}
