<?php

declare(strict_types=1);

use App\Services\AI\Fyn\ArithmeticCheck;
use App\Services\AI\Fyn\CertaintyFilter;

/*
 * Fyn wrote "£40,000 − £325,000 = £0" (#22897) and "£38,000 − £650,000 = £0"
 * (#22901): a subtraction that comes out below zero written as nought. Every
 * sum Fyn writes is recomputed before it is shown or saved.
 */
it('rewrites a subtraction that goes below zero as what it means', function () {
    expect(ArithmeticCheck::correct('- Taxable estate: **£40,000 − £325,000 = £0**.'))
        ->toBe('- Taxable estate: **£40,000 is less than £325,000, so £0**.')
        ->and(ArithmeticCheck::correct('Taxable amount: £38,000 - £650,000 = £0'))
        ->toBe('Taxable amount: £38,000 is less than £650,000, so £0');
});

it('replaces a wrong result with the exact one', function () {
    expect(ArithmeticCheck::correct('That is £518.40 − £59.20 = £459.30 a year.'))->toBe('That is £518.40 − £59.20 = £459.20 a year.')
        ->and(ArithmeticCheck::correct('£8,000 + £30,000 = £36,000'))->toBe('£8,000 + £30,000 = £38,000')
        ->and(ArithmeticCheck::correct('£1,210 × 40% = £480'))->toBe('£1,210 × 40% = £484');
});

it('leaves a correct sum, a negative result written as one, and a chain alone', function () {
    foreach ([
        '£518.40 − £59.20 = £459.20',
        '£8,000 + £30,000 = £38,000',
        '£1,300 × 40% = £520',
        '£40,000 − £325,000 = −£285,000',
        '£60,000 − £6,000 − £50,270 = £3,730',
    ] as $text) {
        expect(ArithmeticCheck::correct($text))->toBe($text);
    }
});

it('is applied to every streamed reply by the one output filter', function () {
    $filter = new CertaintyFilter;
    $out = $filter->push('Combined estate: £38,000. Taxable amount: **£38,000 − £650,000 = £0**. ').$filter->flush();

    expect($out)->toContain('£38,000 is less than £650,000, so £0')
        ->not->toContain('− £650,000 = £0');
});
