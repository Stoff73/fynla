<?php

declare(strict_types=1);

use App\Support\SharedOwnership;

it('treats joint and tenants in common as shared, and nothing else', function () {
    expect(SharedOwnership::isShared('joint'))->toBeTrue()
        ->and(SharedOwnership::isShared('tenants_in_common'))->toBeTrue()
        ->and(SharedOwnership::isShared('individual'))->toBeFalse()
        ->and(SharedOwnership::isShared('trust'))->toBeFalse()
        ->and(SharedOwnership::isShared(null))->toBeFalse();
});

it('gives a shared asset a 50/50 split when no share is supplied', function () {
    expect(SharedOwnership::primaryOwnerPercentage('joint', null))->toBe(50.0)
        ->and(SharedOwnership::primaryOwnerPercentage('tenants_in_common', null))->toBe(50.0)
        ->and(SharedOwnership::primaryOwnerPercentage('joint', ''))->toBe(50.0);
});

it('keeps a stated 100 exactly as stated rather than halving it', function () {
    // This used to return 50: a submitted 100 was read as the individual default
    // a form had never cleared. That made "I own all of it" and "I said nothing"
    // the same input, so a stated 100 was stored as 50 and returned 201 — while a
    // stated 0 was refused. The refusal now lives in ValidatesSharedOwnership;
    // this boundary never alters a figure the caller stated (W-0040).
    expect(SharedOwnership::primaryOwnerPercentage('joint', 100))->toBe(100.0)
        ->and(SharedOwnership::primaryOwnerPercentage('joint', '100'))->toBe(100.0)
        ->and(SharedOwnership::primaryOwnerPercentage('joint', 0))->toBe(0.0);
});

it('tells a stated share apart from one nobody stated', function () {
    expect(SharedOwnership::statedShare(null))->toBeNull()
        ->and(SharedOwnership::statedShare(''))->toBeNull()
        ->and(SharedOwnership::statedShare(0))->toBe(0.0)
        ->and(SharedOwnership::statedShare('70'))->toBe(70.0);
});

it('recognises which shares two parties can actually hold between them', function () {
    // 0 and 100 are individual ownership described with the wrong ownership_type
    // — CSJ's ruling on W-0040. Neither may be stored as a shared record.
    expect(SharedOwnership::isValidSharedSplit(50))->toBeTrue()
        ->and(SharedOwnership::isValidSharedSplit(0.5))->toBeTrue()
        ->and(SharedOwnership::isValidSharedSplit(99.9))->toBeTrue()
        ->and(SharedOwnership::isValidSharedSplit(0))->toBeFalse()
        ->and(SharedOwnership::isValidSharedSplit(100))->toBeFalse()
        ->and(SharedOwnership::isValidSharedSplit(null))->toBeFalse()
        ->and(SharedOwnership::isValidSharedSplit(''))->toBeFalse();
});

it('keeps any other explicit share on a shared asset', function () {
    expect(SharedOwnership::primaryOwnerPercentage('tenants_in_common', 60))->toBe(60.0)
        ->and(SharedOwnership::primaryOwnerPercentage('joint', 30.5))->toBe(30.5)
        ->and(SharedOwnership::primaryOwnerPercentage('joint', '75'))->toBe(75.0);
});

it('defaults a solely owned asset to 100 but keeps an explicit shareholding', function () {
    expect(SharedOwnership::primaryOwnerPercentage('individual', null))->toBe(100.0)
        ->and(SharedOwnership::primaryOwnerPercentage('trust', null))->toBe(100.0)
        ->and(SharedOwnership::primaryOwnerPercentage('individual', 60))->toBe(60.0);
});

it('applies the rule to a payload, reading the type from the payload itself', function () {
    expect(SharedOwnership::applyTo([
        'ownership_type' => 'joint',
    ]))->toMatchArray(['ownership_percentage' => 50.0]);

    expect(SharedOwnership::applyTo([
        'ownership_type' => 'individual',
    ]))->toMatchArray(['ownership_percentage' => 100.0]);
});

it('lets the caller override the ownership type when the payload omits it', function () {
    // Update paths merge the request against the stored record, so the type in
    // play is not always the one in the payload.
    expect(SharedOwnership::applyTo([], 'joint'))
        ->toMatchArray(['ownership_percentage' => 50.0]);
});

it('keeps the share already on record when an update states none', function () {
    // A form with no share input sends nothing on every update. Re-defaulting
    // there would rewrite a stored 70 to 50 — the same silent overwrite this
    // item is about, one layer along (W-0040).
    $stored = (object) ['ownership_percentage' => 70.0];

    expect(SharedOwnership::applyTo(['ownership_type' => 'joint'], null, $stored))
        ->toMatchArray(['ownership_percentage' => 70.0]);

    // A share the caller DID state still wins over the stored one.
    expect(SharedOwnership::applyTo(['ownership_type' => 'joint', 'ownership_percentage' => 60], null, $stored))
        ->toMatchArray(['ownership_percentage' => 60.0]);

    // Nothing stated and nothing stored — a create — still defaults.
    expect(SharedOwnership::applyTo(['ownership_type' => 'joint'], null, (object) ['ownership_percentage' => null]))
        ->toMatchArray(['ownership_percentage' => 50.0]);
});

it('gives the joint owner the complement of the primary owner share', function () {
    expect(SharedOwnership::jointOwnerPercentage(50.0))->toBe(50.0)
        ->and(SharedOwnership::jointOwnerPercentage(60.0))->toBe(40.0)
        ->and(SharedOwnership::jointOwnerPercentage(100.0))->toBe(0.0);
});

it('recognises a linked account as the counterparty', function () {
    expect(SharedOwnership::namesCounterparty(['joint_owner_id' => 17]))->toBeTrue();
});

it('recognises a free-text name as the counterparty', function () {
    // The persona's tenants-in-common co-owner is not on the platform; chattels,
    // properties and mortgages carry a joint_owner_name column for exactly this.
    expect(SharedOwnership::namesCounterparty(['joint_owner_name' => 'Mike Barrett']))->toBeTrue();
});

it('rejects a shared asset that names nobody', function () {
    // Neither means 50% of the asset belongs to no one — invisible to the other
    // party and missing from every household total (W-0025).
    expect(SharedOwnership::namesCounterparty([]))->toBeFalse()
        ->and(SharedOwnership::namesCounterparty(['joint_owner_id' => null]))->toBeFalse()
        ->and(SharedOwnership::namesCounterparty(['joint_owner_name' => '']))->toBeFalse()
        ->and(SharedOwnership::namesCounterparty(['joint_owner_name' => '   ']))->toBeFalse()
        ->and(SharedOwnership::namesCounterparty(['joint_owner_id' => null, 'joint_owner_name' => null]))->toBeFalse();
});
