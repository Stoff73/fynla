<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Support\Money;

/**
 * Every sum Fyn writes is recomputed before the user sees it. Fyn wrote
 * "£40,000 − £325,000 = £0" (csjones, 2026-10-12, #22897): a subtraction below
 * zero written as nought. A wrong figure a user can see is never minor
 * (CLAUDE.md Rule 23), and a prompt rule alone does not hold (walk fix plan
 * T5), so the arithmetic is checked on the output, in the one output filter
 * (CertaintyFilter), for every surface.
 *
 * Checked: "£A − £B = £C", "£A + £B = £C" and "£A × P% = £C", each standing
 * alone (a chain such as "£A − £B − £C = £D" is left as written, since its
 * middle cannot be read as a sum of two). A wrong result is replaced with the
 * exact one (Money::exact). A subtraction below zero written as £0 says what
 * it means: "£A is less than £B, so £0".
 */
final class ArithmeticCheck
{
    private const MONEY = '£(\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?|\d+(?:\.\d{1,2})?)';

    public static function correct(string $text): string
    {
        $money = self::MONEY;
        // "*" is not an operator here: it is markdown bold ("**£40,000").
        $pattern = '/(?<![−\-–+×]\s)(?<![−\-–+×])'.$money
            .'\s*(−|–|-|\+|×|x)\s*(?:'.$money.'|(\d+(?:\.\d+)?)%)'
            .'\s*=\s*(−|–|-)?'.$money
            .'(?!\s*[−\-–+×]\s*£)/u';

        return preg_replace_callback($pattern, static function (array $m): string {
            [$whole, $a, $op, $b, $percent, $sign, $c] = $m + array_fill(0, 7, '');
            $a = self::amount($a);
            $c = self::amount($c) * ($sign !== '' ? -1 : 1);

            if (in_array($op, ['×', 'x'], true)) {
                if ($percent === '') {
                    return $whole;
                }
                $true = round($a * (float) $percent / 100, 2);
            } elseif ($b === '') {
                return $whole;
            } elseif ($op === '+') {
                $true = round($a + self::amount($b), 2);
            } else {
                $true = round($a - self::amount($b), 2);
                if ($true < 0 && abs($c) < 0.005) {
                    return Money::exact($a).' is less than '.Money::exact(self::amount($b)).', so £0';
                }
            }

            if (abs($c - $true) < 0.005) {
                return $whole;
            }

            $at = strrpos($whole, '=');

            return substr($whole, 0, $at).'= '.Money::exact($true);
        }, $text) ?? $text;
    }

    private static function amount(string $figure): float
    {
        return (float) str_replace(',', '', $figure);
    }
}
