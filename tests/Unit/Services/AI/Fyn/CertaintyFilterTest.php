<?php

declare(strict_types=1);

use App\Services\AI\Fyn\CertaintyFilter;

/*
 * CSJ ruling 2026-09-29 (Rule 12): Fyn never states certainty; every figure must
 * be empirical. The replies below are the ones the walk produced for "talk me
 * through each action and how sure you are about each one" (PR #960).
 */

it('drops the certainty grades from the walk reply and keeps every figure', function (): void {
    $reply = "**1. Pay £3,700 more into your pension and save £1,480 in tax**\n"
        ."- £3,700 × 40 % = **£1,480** saved this tax year.\n"
        ."- The figure is worked from your recorded £60,000 income and the 40 % relief rate.\n"
        ."- Certainty: very high — the calculation follows directly from your data and the current tax rules.\n\n"
        ."**Certainty: high** — the saving repeats every year the arrangement stays in place.\n\n"
        .'Both items are marked as fixed arithmetic in the plan, so the amounts are presented as firm rather than estimates. The combined annual saving shown is £1,540.';

    $out = CertaintyFilter::clean($reply);

    expect($out)->not->toMatch('/certainty|firm rather/i')
        ->toContain('£3,700 × 40 % = **£1,480** saved this tax year.')
        ->toContain('The figure is worked from your recorded £60,000 income and the 40 % relief rate.')
        ->toContain('The combined annual saving shown is £1,540.')
        ->toContain("**1. Pay £3,700 more into your pension and save £1,480 in tax**\n");
});

it('drops first-person confidence and scores out of 10 or 100, but not dates', function (string $sentence, bool $dropped): void {
    expect(CertaintyFilter::statesCertainty($sentence))->toBe($dropped);
})->with([
    ["I'm confident this saving holds.", true],
    ['I am fairly sure the relief applies.', true],
    ['We are certain of this figure.', true],
    ['I would rate this 8/10.', true],
    ['Overall: 75 / 100.', true],
    ['There is a high degree of confidence in this plan.', true],
    ['The saving is locked in as soon as the contribution is made.', true],
    ['Pay it in by 5/10/2026 to count this year.', false],
    ['Your Personal Allowance is £12,570.', false],
    ['A judgement call: it depends on your circumstances and preferences.', false],
    ['Your guaranteed annual income from the scheme is £35,000.', false],
    ['Certain conditions apply to carry forward.', false],
    ['Both actions rest on your recorded data, so the pound amounts are fixed rather than estimates.', true],
    ['The arithmetic is fixed once your income and the tax thresholds are known.', true],
    ['Your mortgage rate is fixed until 2028.', false],
]);

it('streams sentence by sentence and never lets a split certainty sentence through', function (): void {
    $filter = new CertaintyFilter;
    $chunks = ['Paying £3,70', '0 in saves £1,4', '80. Certa', 'inty: very ', 'high — it follows from your data.', "\nPay by 5 April 2027", '.'];

    $sent = '';
    foreach ($chunks as $chunk) {
        $sent .= $filter->push($chunk);
    }
    $sent .= $filter->flush();

    expect($sent)->toBe("Paying £3,700 in saves £1,480.\nPay by 5 April 2027.");
});

it('passes a reply with no certainty through unchanged', function (): void {
    $reply = "Your pension input this year is **£7,800**:\n\n- Nest £4,800 (you pay £3,000, your employer pays £1,800)\n- Vanguard £3,000 (you pay £2,400, the provider adds £600).\n\nWould you like more detail?";

    expect(CertaintyFilter::clean($reply))->toBe($reply);
});
