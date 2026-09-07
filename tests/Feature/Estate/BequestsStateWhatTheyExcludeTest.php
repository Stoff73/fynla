<?php

declare(strict_types=1);

use App\Models\Estate\Will;
use App\Models\User;
use App\Services\Estate\WillDocumentService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0398. `syncBequests()` deliberately excludes residuary beneficiaries, and the reason
 * is sound: a residuary is a share of what REMAINS after the specific gifts, not a
 * percentage of the estate, and `Will::getNonSpouseAllocationPercentage()` sums exactly
 * the `percentage` rows — so storing one there would report a mirror will leaving
 * everything to a partner as a 100% NON-partner allocation.
 *
 * **The exclusion is right. Its consequence was the defect.** The persona's children ARE
 * provided for, as the residuary's substitution beneficiary in free text, and every
 * consumer of the `bequests` table saw nothing — so the household read as though its
 * children were unprovided for, and `/m`'s "1 bequest" was accurate about the table and
 * misleading about the will.
 *
 * The sentence is served with the payload rather than written into each screen: `/m` is
 * an isolated bundle that cannot import from `resources/js`, so a frontend-held sentence
 * would be two copies from the day it was written.
 */
beforeEach(function () {
    // Estate is a full-Estate route (spec §10.2), so the acting user must be on a
    // full-Estate tier or every request 404s at the gate rather than reaching the
    // controller — the same setup the LPA feature tests use.
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Sanctum::actingAs($this->user);
});

it('tells the reader what the list does not contain', function () {
    Will::factory()->create(['user_id' => $this->user->id]);

    $this->getJson('/api/estate/bequests')
        ->assertOk()
        ->assertJsonPath('residuary_note', WillDocumentService::BEQUESTS_EXCLUDE_RESIDUARY_NOTE);
});

it('says it even when there is no will yet, so an empty list is not read as a whole one', function () {
    $this->getJson('/api/estate/bequests')
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('residuary_note', WillDocumentService::BEQUESTS_EXCLUDE_RESIDUARY_NOTE);
});

/**
 * Rule 9 and the reason the sentence is worded as it is: a bereaved reader should not
 * have to know what "residuary" means to understand that something is missing from the
 * list.
 */
it('explains the residue without requiring the reader to know the word', function () {
    expect(WillDocumentService::BEQUESTS_EXCLUDE_RESIDUARY_NOTE)
        ->toContain('specific gifts')
        ->toContain('left over')
        ->toContain('will document');
});

/**
 * The guard on the exclusion itself. If someone later "fixes" W-0398 by writing
 * residuary rows into `bequests` as percentages, this is what goes red — and it is the
 * live answer that would be corrupted, not a style rule.
 */
it('keeps a residuary out of the percentage rows the allocation check sums', function () {
    // A mirror will: everything to the partner via the residue, no specific gifts.
    // `spouse_primary_beneficiary` is NOT NULL, so the factory's default is passed
    // explicitly rather than left to the column.
    $will = Will::factory()->create([
        'user_id' => $this->user->id,
        'spouse_primary_beneficiary' => true,
        'spouse_bequest_percentage' => 100,
    ]);

    expect($will->getNonSpouseAllocationPercentage())->toBe(0.0);
});
