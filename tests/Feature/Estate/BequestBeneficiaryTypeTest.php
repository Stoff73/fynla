<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0394 — `beneficiary_type` and `charity_registration_number` were absent from
 * both `StoreBequestRequest` and `UpdateBequestRequest`, so `validated()` dropped
 * them and every bequest this controller wrote took the schema default
 * `individual`. Both of the peak_earners household's charitable legacies were
 * stored as gifts to a person.
 *
 * It hid because `Bequest::isCharitable()` re-derives the answer from the
 * beneficiary NAME on every read, and its list happens to contain 'cancer' and
 * 'heart'. A charity the list does not name — Guide Dogs, an air ambulance, a
 * local hospice trust registered under a family surname — had no second chance:
 * it was an individual in the database and an individual to the charitable
 * total, which is what decides whether the reduced Inheritance Tax rate applies.
 *
 * Also here, because the same request classes are the answer to it: whether the
 * bequest save path silently drops PERCENTAGE bequests. The peak_earners wills
 * carry only their charitable gift, and the persona lists two percentage
 * bequests per will to the two children. It was not clear whether that was
 * unfinished data entry or a save path dropping them. It is unfinished data
 * entry — see the round-trip cases below.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function premiumTestator(): User
{
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Will::firstOrCreate(['user_id' => $user->id], ['has_will' => true]);

    return $user;
}

describe('POST /api/estate/bequests records what kind of beneficiary this is', function () {
    it('stores a charity as a charity and a person as a person, in one household', function () {
        $user = premiumTestator();

        // Asymmetric on purpose: two gifts of DIFFERENT amounts to beneficiaries
        // of DIFFERENT kinds. If both rows were classified the same way — which
        // is exactly what the defect did — this cannot pass.
        $this->actingAs($user)->postJson('/api/estate/bequests', [
            'beneficiary_name' => 'British Heart Foundation',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 10000,
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/estate/bequests', [
            'beneficiary_name' => 'Charlotte Jones',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 25000,
        ])->assertCreated();

        $charity = Bequest::where('user_id', $user->id)->where('beneficiary_name', 'British Heart Foundation')->sole();
        $daughter = Bequest::where('user_id', $user->id)->where('beneficiary_name', 'Charlotte Jones')->sole();

        expect($charity->beneficiary_type)->toBe('charity')
            ->and($daughter->beneficiary_type)->toBe('individual')
            ->and($charity->beneficiary_type)->not->toBe($daughter->beneficiary_type);
    });

    it('believes the caller over the name when a type is stated', function () {
        // The inference fills a silence; it does not overrule a user. A charity
        // whose name reads as a person, and a person whose name reads as a
        // charity, are both entirely possible.
        $user = premiumTestator();

        $this->actingAs($user)->postJson('/api/estate/bequests', [
            'beneficiary_name' => 'Hartley Nephew Trustees',
            'beneficiary_type' => 'charity',
            'charity_registration_number' => '1089464',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 5000,
        ])->assertCreated();

        $bequest = Bequest::where('user_id', $user->id)->sole();

        expect($bequest->beneficiary_type)->toBe('charity')
            ->and($bequest->charity_registration_number)->toBe('1089464');
    });

    it('classifies a charity its name list does not know, when the user says so', function () {
        // The case the name list cannot reach, and the reason the stored column
        // has to be right rather than re-derived. 'Guide Dogs' contains none of
        // the indicators.
        $user = premiumTestator();

        expect(Bequest::nameLooksCharitable('Guide Dogs for the Blind Association'))->toBeFalse();

        $this->actingAs($user)->postJson('/api/estate/bequests', [
            'beneficiary_name' => 'Guide Dogs for the Blind Association',
            'beneficiary_type' => 'charity',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 7500,
        ])->assertCreated();

        $bequest = Bequest::where('user_id', $user->id)->sole();

        expect($bequest->beneficiary_type)->toBe('charity')
            ->and($bequest->isCharitable())->toBeTrue();
    });
});

describe('PUT /api/estate/bequests/{id}', function () {
    it('reclassifies when the beneficiary is renamed', function () {
        $user = premiumTestator();
        $will = Will::where('user_id', $user->id)->sole();

        $bequest = Bequest::create([
            'will_id' => $will->id,
            'user_id' => $user->id,
            'beneficiary_name' => 'William Jones',
            'beneficiary_type' => 'individual',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 15000,
            'priority_order' => 1,
        ]);

        $this->actingAs($user)->putJson("/api/estate/bequests/{$bequest->id}", [
            'beneficiary_name' => 'Macmillan Cancer Support',
        ])->assertOk();

        expect($bequest->fresh()->beneficiary_type)->toBe('charity');
    });

    it('does not reclassify a beneficiary when only the amount changes', function () {
        // A user who deliberately recorded a beneficiary as an individual must
        // not have that overturned by an unrelated edit. The name is not in the
        // payload, so there is nothing to classify from.
        $user = premiumTestator();
        $will = Will::where('user_id', $user->id)->sole();

        $bequest = Bequest::create([
            'will_id' => $will->id,
            'user_id' => $user->id,
            'beneficiary_name' => 'Heart Cottage Holdings',
            'beneficiary_type' => 'organization',
            'bequest_type' => 'specific_amount',
            'specific_amount' => 15000,
            'priority_order' => 1,
        ]);

        $this->actingAs($user)->putJson("/api/estate/bequests/{$bequest->id}", [
            'specific_amount' => 22000,
        ])->assertOk();

        $fresh = $bequest->fresh();

        expect($fresh->beneficiary_type)->toBe('organization')
            ->and((float) $fresh->specific_amount)->toBe(22000.0);
    });
});

describe('percentage bequests survive the save path', function () {
    it('round-trips two different percentages to two different children', function () {
        // 60/40, never 50/50. A symmetric split makes one child's share and the
        // other's the same number, so a save path that dropped one row, wrote
        // one row twice, or overwrote the second with the first would be
        // indistinguishable from a correct one (tests/CLAUDE.md §4, Collision).
        $user = premiumTestator();

        $this->actingAs($user)->postJson('/api/estate/bequests', [
            'beneficiary_name' => 'William Jones',
            'bequest_type' => 'percentage',
            'percentage_of_estate' => 60,
            'conditions' => 'Receive at age 25, held in trust',
        ])->assertCreated();

        $this->actingAs($user)->postJson('/api/estate/bequests', [
            'beneficiary_name' => 'Charlotte Jones',
            'bequest_type' => 'percentage',
            'percentage_of_estate' => 40,
            'conditions' => 'Receive at age 25, held in trust',
        ])->assertCreated();

        $william = Bequest::where('user_id', $user->id)->where('beneficiary_name', 'William Jones')->sole();
        $charlotte = Bequest::where('user_id', $user->id)->where('beneficiary_name', 'Charlotte Jones')->sole();

        expect($william->bequest_type)->toBe('percentage')
            ->and((float) $william->percentage_of_estate)->toBe(60.0)
            ->and($charlotte->bequest_type)->toBe('percentage')
            ->and((float) $charlotte->percentage_of_estate)->toBe(40.0)
            // The two rows are distinct records, in the order they were written.
            ->and($william->priority_order)->toBe(1)
            ->and($charlotte->priority_order)->toBe(2)
            ->and($william->conditions)->toBe('Receive at age 25, held in trust');
    });

    it('serves both percentage bequests back to the reader', function () {
        // The half a write test cannot see: a row that saves and never returns
        // is as absent to the user as one that never saved.
        $user = premiumTestator();

        foreach ([['William Jones', 60], ['Charlotte Jones', 40]] as [$name, $share]) {
            $this->actingAs($user)->postJson('/api/estate/bequests', [
                'beneficiary_name' => $name,
                'bequest_type' => 'percentage',
                'percentage_of_estate' => $share,
            ])->assertCreated();
        }

        $response = $this->actingAs($user)->getJson('/api/estate/bequests')->assertOk();

        $returned = collect($response->json('data'))
            ->pluck('percentage_of_estate', 'beneficiary_name')
            ->map(fn ($p) => (float) $p);

        expect($returned->get('William Jones'))->toBe(60.0)
            ->and($returned->get('Charlotte Jones'))->toBe(40.0);
    });
});
