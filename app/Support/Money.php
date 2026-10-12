<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Money as a person reads it, never rounded (CSJ 2026-10-12): £100 when the
 * figure is whole, £100.15 when it has pence.
 */
final class Money
{
    public static function exact(float $amount): string
    {
        $amount = round($amount, 2);

        return ($amount < 0 ? '−' : '').'£'.(fmod($amount, 1.0) === 0.0
            ? number_format(abs($amount))
            : number_format(abs($amount), 2));
    }
}
