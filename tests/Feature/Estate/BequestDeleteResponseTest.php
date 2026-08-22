<?php

declare(strict_types=1);

use App\Models\Estate\Bequest;
use App\Models\Estate\Will;
use App\Models\Estate\WillDocument;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0041, second instance — `WillController::deleteBequest()` declared
 * `: JsonResponse` and returned `response()->noContent()`. The row was deleted
 * and the response THEN threw a TypeError, so the user was shown an error for
 * an action that had already succeeded and would reasonably retry.
 *
 * Newly reachable: the /m bequests screen and the web edit/delete controls both
 * landed days before this was found, so the bug and the feature shipped together.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function premiumUserWithBequest(array $attributes = []): array
{
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $will = Will::firstOrCreate(['user_id' => $user->id], ['has_will' => true]);

    $bequest = Bequest::create(array_merge([
        'will_id' => $will->id,
        'user_id' => $user->id,
        'beneficiary_name' => 'Cancer Research UK',
        'bequest_type' => 'specific_amount',
        'specific_amount' => 10000,
        'priority_order' => 1,
    ], $attributes));

    return [$user, $bequest];
}

describe('DELETE /api/estate/bequests/{id}', function () {
    it('returns 200 with the house-standard success body', function () {
        [$user, $bequest] = premiumUserWithBequest();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/estate/bequests/{$bequest->id}")
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'message']);

        expect(Bequest::find($bequest->id))->toBeNull();
    });

    it('deletes a will-builder-sourced bequest without erroring', function () {
        // The other branch worth covering separately: rows carrying a
        // will_document_id are the ones W-0023 creates, and they are what the
        // newly shipped /m and web controls delete.
        [$user, $bequest] = premiumUserWithBequest();
        $doc = WillDocument::factory()->create([
            'user_id' => $user->id,
            'will_id' => $bequest->will_id,
            'status' => 'complete',
        ]);
        $bequest->update(['will_document_id' => $doc->id]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/estate/bequests/{$bequest->id}")
            ->assertOk()
            ->assertJson(['success' => true]);

        expect(Bequest::find($bequest->id))->toBeNull();
    });

    it('does not let one user delete another user\'s bequest', function () {
        [, $bequest] = premiumUserWithBequest();
        $other = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);

        $this->actingAs($other, 'sanctum')
            ->deleteJson("/api/estate/bequests/{$bequest->id}")
            ->assertNotFound();

        expect(Bequest::find($bequest->id))->not->toBeNull();
    });
});
