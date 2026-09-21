<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use Carbon\Carbon;

/**
 * Every user-facing string the strip shows, on every surface. British English,
 * no acronyms the surface has not spelt out, no scores, no icons.
 */
final class ThresholdCopy
{
    public static function pounds(float $n): string
    {
        return '£'.number_format(round($n));
    }

    public static function into(float $distance, string $bandName): string
    {
        return $distance > 0
            ? sprintf('You are %s into the %s', self::pounds($distance), $bandName)
            : sprintf('You are %s under the %s', self::pounds(-$distance), $bandName);
    }

    public static function estate(float $distance): string
    {
        return $distance > 0
            ? sprintf('Your estate is %s over the nil rate band', self::pounds($distance))
            : sprintf('Your estate is %s under the nil rate band', self::pounds(-$distance));
    }

    public static function taperBody(float $distance, int $effectivePct, float $threshold): string
    {
        return $distance > 0
            ? sprintf('The next %s you earn costs %dp in the pound. A pension contribution is the only way back under %s.', self::pounds($distance), $effectivePct, self::pounds($threshold))
            : sprintf('Every pound above %s costs %dp. A pay rise, bonus or share vest of %s would take you over it.', self::pounds($threshold), $effectivePct, self::pounds(-$distance));
    }

    public static function taperExplanation(float $threshold, int $higherPct, int $lostPct, float $taperRate): string
    {
        return sprintf('For every %s you earn above %s you lose £1 of your Personal Allowance. That lost allowance was being taxed at %d%%, so the pound you earned costs %dp and the allowance costs another %dp.', self::perPoundLost($taperRate), self::pounds($threshold), $higherPct, $higherPct, $lostPct);
    }

    /**
     * How much has to be earned, or how much estate has to be held, for £1 of an
     * allowance to be withdrawn. Rule 2: the ratio is the seeded taper rate, never
     * the literal "£2" the current rates happen to produce — the copy follows the
     * config the day a Budget changes it.
     */
    public static function perPoundLost(float $taperRate): string
    {
        return $taperRate > 0 ? self::pounds(1 / $taperRate) : '£0';
    }

    /** @param  array{date: Carbon, value: float}|null  $nextVest */
    public static function lockedUntil(int $age, ?array $nextVest): string
    {
        $text = sprintf('The money is locked until you are %d.', $age);
        if ($nextVest !== null) {
            $text .= sprintf(' Your %s share vest of %s cannot be sacrificed and will push you back over.', $nextVest['date']->format('F'), self::pounds($nextVest['value']));
        }

        return $text;
    }
}
