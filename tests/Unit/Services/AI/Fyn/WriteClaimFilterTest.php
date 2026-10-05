<?php

declare(strict_types=1);

use App\Services\AI\Fyn\CertaintyFilter;

/*
 * Fyn never says it saved something it did not save (TODO item 7a, walking
 * release #1071): asked "Actually my date of birth is 2 August 1960", Advice
 * Fyn replied "Thank you — I have updated your date of birth to 2 August
 * 1960." with no write, and the date stayed 1 August. The prompt forbids it
 * (FynSystemPrompt "never fabricate a confirmation"), so the rule is enforced
 * on the output, by the one output filter, in the read-only advice state.
 */
it('names the sentences that claim a save', function (string $sentence, bool $claims): void {
    expect(CertaintyFilter::claimsWrite($sentence))->toBe($claims);
})->with([
    ['Thank you — I have updated your date of birth to 2 August 1960.', true],
    ["I've saved your new ISA.", true],
    ['I’ve recorded that for you.', true],
    ['I updated your date of birth.', true],
    ['Recorded — Barclays easy access savings account £4,000.', true],
    ['Updated — your monthly spending is £2,600.', true],
    ['Your date of birth has been updated to 2 August 1960.', true],
    ["I've added the pension to your records.", true],
    // csjones walk of the fix: the same claim in other words.
    ['Thank you for the correction. Your date of birth is now recorded as 3 May 1990.', true],
    ['Your spending is now saved as £2,600 a month.', true],
    ["I've made that change for you.", true],
    ['The change has been made.', true],
    ['Your date of birth is recorded as 1 May 1990.', false],
    ['Your pension is now worth £45,000.', false],
    ['Once you have added your pension, I can work out your income.', false],
    ['You have recorded £20,000 of ISA contributions this year.', false],
    ['Your State Pension is recorded as being paid.', false],
    ['Would you like me to update it?', false],
    ['I can update your date of birth for you.', false],
    ['Tell me what has changed and I will save it.', false],
]);

it('replaces the first false claim with the truth and drops the rest, in advice', function (): void {
    $filter = new CertaintyFilter(writeClaimsAreFalse: true);
    $sent = '';
    foreach (['Thank you — I have upd', 'ated your date of birth to 2 August 1960. ', "I've saved it. ", 'Is there anything else you would like to change or add?'] as $chunk) {
        $sent .= $filter->push($chunk);
    }
    $sent .= $filter->flush();

    expect($sent)->toBe('That has not been saved yet. Is there anything else you would like to change or add?');
});

it('keeps a confirmation outside advice, where writes run', function (): void {
    $filter = new CertaintyFilter(writeClaimsAreFalse: false);
    $sent = $filter->push('Recorded — date of birth 14 March 1981.').$filter->flush();

    expect($sent)->toBe('Recorded — date of birth 14 March 1981.');
});
