<?php

declare(strict_types=1);

use App\Models\Estate\Gift;
use App\Models\Estate\Trust;
use App\Models\User;
use App\Services\Estate\FailedGiftTaxCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0528 — a trust settlement withholds the settlor's nil rate band, and it has to
 * keep pace with the trust.
 *
 * **CSJ, 2026-08-29:** *"any trust transfer uses the [nil rate band] for the person
 * who transferred the assets into the trust so it is not available for the estate
 * until the ... 7 year reset"*, and *"we do need to sort the edit and delete of
 * trusts so the estate is updated correctly as well as the gifting"*.
 *
 * `TrustObserver` handled `created` only, so the gift — and therefore the band —
 * froze at whatever the trust looked like on the day it was first saved. These
 * assert the band itself rather than the gift row, because the band is the thing
 * the user is shown and the thing that was wrong.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    // Premium: the gifting write endpoints sit behind `estate.full`.
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->nrb = 325_000.0;

    $this->trust = Trust::create([
        'user_id' => $this->user->id,
        'trust_name' => 'Settlement Trust',
        'trust_type' => 'discretionary',
        // Two years back, so it is inside the seven-year window either way.
        'trust_creation_date' => today()->subYears(2)->toDateString(),
        'initial_value' => 200_000,
        'current_value' => 200_000,
        'settlor' => 'Settlor',
    ]);

    $this->bandWithheld = fn (): float => app(FailedGiftTaxCalculator::class)
        ->forMember($this->user->fresh(), $this->nrb)['total_nrb_used'];
});

it('withholds the settlor band for a trust settlement', function () {
    expect(($this->bandWithheld)())->toBe(200_000.0);
});

it('withholds the edited amount, not the amount first settled', function () {
    $this->trust->update(['initial_value' => 300_000]);

    // Withheld £200,000 before, so £100,000 of band was available to the estate
    // that the settlement had already used — £40,000 of understated tax at 40%.
    expect(($this->bandWithheld)())->toBe(300_000.0);
});

it('gives the band back when the trust is deleted', function () {
    $this->trust->delete();

    // The gift used to outlive the trust, withholding £200,000 for a settlement
    // that no longer existed.
    expect(($this->bandWithheld)())->toBe(0.0);
});

it('gives the band back when the settled amount is edited down to nothing', function () {
    $this->trust->update(['initial_value' => 0]);

    expect(($this->bandWithheld)())->toBe(0.0);
});

it('withholds the band again when a deleted trust is restored', function () {
    $this->trust->delete();
    $this->trust->restore();

    expect(($this->bandWithheld)())->toBe(200_000.0);
});

describe('the gifting module cannot move a band the trust owns', function () {
    it('refuses to delete the settlement gift, and the band stays withheld', function () {
        $gift = Gift::where('trust_id', $this->trust->id)->firstOrFail();

        $this->actingAs($this->user)->deleteJson("/api/estate/gifts/{$gift->id}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        expect(($this->bandWithheld)())->toBe(200_000.0);
    });

    it('refuses to edit the settlement gift, and the band stays withheld', function () {
        $gift = Gift::where('trust_id', $this->trust->id)->firstOrFail();

        $this->actingAs($this->user)->putJson("/api/estate/gifts/{$gift->id}", [
            'gift_value' => 10_000,
            'gift_type' => 'clt',
            'gift_date' => today()->subYears(2)->toDateString(),
            'recipient' => 'Settlement Trust',
        ])->assertStatus(422);

        expect(($this->bandWithheld)())->toBe(200_000.0);
    });

    it('still lets the user manage a gift they entered themselves', function () {

        $own = Gift::create([
            'user_id' => $this->user->id,
            'gift_date' => today()->subYear()->toDateString(),
            'recipient' => 'Nephew',
            'gift_type' => 'pet',
            'gift_value' => 50_000,
        ]);

        $this->actingAs($this->user)->deleteJson("/api/estate/gifts/{$own->id}")->assertOk();

        expect(($this->bandWithheld)())->toBe(200_000.0);
    });
});
