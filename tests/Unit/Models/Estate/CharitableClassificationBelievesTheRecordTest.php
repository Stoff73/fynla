<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;

/**
 * W-0394's last acceptance — is the name-indicator list a defensible fallback for
 * the reduced-rate test, now that it also decides what is STORED?
 *
 * Reviewed 2026-09-01, and the answer was no while it ran at read time. The list
 * holds generic words — `foundation`, `shelter`, `heart` — and one that is also a
 * common surname, `macmillan`. Consulting it in `isCharitable()` overrode an
 * explicit answer: a user who chose Individual for a gift to "Shelter Macmillan"
 * was reclassified as charitable, which inflates the charitable total that decides
 * the 36% reduced rate (IHTA 1984 Sch 1A) and so **understates** the tax bill. The
 * user could not correct it, because the field they would correct was the field
 * being ignored.
 *
 * It is defensible where it belongs — at write time, filling a silence — and these
 * pin the split so it cannot drift back.
 */
it('believes an explicit individual over a charitable-looking name', function () {
    $bequest = new Bequest([
        'beneficiary_name' => 'Shelter Macmillan',
        'beneficiary_type' => 'individual',
    ]);

    expect($bequest->isCharitable())->toBeFalse();
});

it('believes an explicit charity whose name says nothing', function () {
    $bequest = new Bequest([
        'beneficiary_name' => 'Guide Dogs for the Blind Association',
        'beneficiary_type' => 'charity',
    ]);

    expect($bequest->isCharitable())->toBeTrue();
});

it('accepts a registration number as the statement it is', function () {
    $bequest = new Bequest([
        'beneficiary_name' => 'The Trussell Trust',
        'beneficiary_type' => 'individual',
        'charity_registration_number' => '1110522',
    ]);

    expect($bequest->isCharitable())->toBeTrue();
});

it('still infers a charity at write time, where the list belongs', function () {
    expect(Bequest::inferBeneficiaryType('Cancer Research UK'))->toBe('charity')
        ->and(Bequest::inferBeneficiaryType('Margaret Wilson'))->toBe('individual');
});

it('never lets a family trust read as charitable', function () {
    // A gift into a family trust is a chargeable transfer, not an exempt one. An
    // earlier copy of this rule treated 'trust' as a charity indicator and could
    // push a household onto a reduced rate it does not qualify for.
    expect(Bequest::nameLooksCharitable('Smith Family Trust'))->toBeFalse()
        ->and((new Bequest(['beneficiary_name' => 'Smith Family Trust', 'beneficiary_type' => 'trust']))->isCharitable())
        ->toBeFalse();
});

it('does not consult the name list at read time at all', function () {
    // The guard that matters: a read-time fallback is what made an explicit answer
    // unenforceable, and a future edit restoring it would pass every test above
    // except this one.
    $source = file_get_contents(__DIR__.'/../../../../app/Models/Estate/Bequest.php');
    $isCharitable = substr($source, strpos($source, 'public function isCharitable'));
    $isCharitable = substr($isCharitable, 0, strpos($isCharitable, "\n    }"));

    expect($isCharitable)->not->toContain('nameLooksCharitable');
});
