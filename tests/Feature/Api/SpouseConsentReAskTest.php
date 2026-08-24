<?php

declare(strict_types=1);

use App\Models\SpousePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * W-0347 findings F4 and F5, plus the CSJ decision behind the re-ask migration.
 *
 * **F4 — withdrawal was a one-way door.** `request()` refused while ANY row
 * existed, and `revoke()` deliberately leaves a `rejected` row behind rather than
 * deleting it (a deleted row reads as "never asked" and quietly re-enables
 * sharing). Between them, once sharing was off neither party could turn it back on
 * through any interface. A withdrawal a user cannot reverse is one they hesitate to
 * make, which makes the consent worth less rather than more.
 *
 * **F5 — a couple could hold a row in each direction**, and `revoke()` and
 * `User::hasAcceptedSpousePermission()` both took `first()` with no order over an
 * `orWhere`. Usually the same row. Nothing guaranteed it, and when they diverge the
 * user withdraws and sharing stays on — the exact failure that keeping the
 * `rejected` row exists to prevent.
 *
 * **The re-ask.** Rows nobody granted (`requested_at IS NULL` — forged by the old
 * `createSpousePermissions()`, or inherited from the deleted backfill) become
 * unanswered requests, so sharing is off until somebody actually accepts.
 */
function reAskCouple(): array
{
    $primary = User::factory()->create(['marital_status' => 'married']);
    $spouse = User::factory()->create(['marital_status' => 'married', 'spouse_id' => $primary->id]);
    $primary->update(['spouse_id' => $spouse->id]);

    return [$primary->fresh(), $spouse->fresh()];
}

describe('asking again after sharing has been switched off', function () {
    it('lets the household ask again once a request has been settled', function () {
        [$primary, $spouse] = reAskCouple();
        SpousePermission::create([
            'user_id' => $primary->id,
            'spouse_id' => $spouse->id,
            'status' => 'rejected',
            'requested_at' => now()->subDay(),
            'responded_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($primary);
        $this->postJson('/api/spouse-permission/request')->assertSuccessful();

        // Asked again on the SAME row — a second row in the other direction is
        // what F5 is about, and the unique key would permit exactly that.
        expect(SpousePermission::count())->toBe(1);
        $row = SpousePermission::first();
        expect($row->status)->toBe('pending');
        expect($row->responded_at)->toBeNull();
        expect($row->requested_at)->not->toBeNull();
    });

    it('still refuses while a request is unanswered', function () {
        [$primary, $spouse] = reAskCouple();
        SpousePermission::create([
            'user_id' => $primary->id,
            'spouse_id' => $spouse->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($primary);
        $this->postJson('/api/spouse-permission/request')->assertStatus(422);
    });

    it('still refuses while sharing is already on', function () {
        [$primary, $spouse] = reAskCouple();
        SpousePermission::create([
            'user_id' => $primary->id,
            'spouse_id' => $spouse->id,
            'status' => 'accepted',
            'requested_at' => now()->subDay(),
            'responded_at' => now(),
        ]);

        Sanctum::actingAs($primary);
        $this->postJson('/api/spouse-permission/request')->assertStatus(422);
    });

    it('does not share anything while the new request is unanswered', function () {
        [$primary, $spouse] = reAskCouple();
        SpousePermission::create([
            'user_id' => $primary->id,
            'spouse_id' => $spouse->id,
            'status' => 'rejected',
            'requested_at' => now()->subDay(),
            'responded_at' => now()->subHour(),
        ]);

        Sanctum::actingAs($primary);
        $this->postJson('/api/spouse-permission/request')->assertSuccessful();

        // Asking is not being granted. This is the whole point of the re-ask:
        // an unanswered request leaves sharing off.
        expect($primary->fresh()->hasAcceptedSpousePermission())->toBeFalse();
        expect($spouse->fresh()->hasAcceptedSpousePermission())->toBeFalse();
    });
});

describe('a couple holding a row in each direction', function () {
    // Honest about its own reach: this pins the guarantee, not the failure. The
    // defect was LATENT non-determinism — two unordered `first()` calls over the
    // same `orWhere`, which in practice return the same row. This test would not
    // have caught it, and it does not go red if the `orderBy('id')` calls are
    // removed. It exists so that a future read added over these rows without an
    // order has something asserting the two must agree.
    it('withdraws on the same row the sharing check reads', function () {
        [$primary, $spouse] = reAskCouple();
        // Deliberately contradictory, in the shape the old backfill produced.
        SpousePermission::create([
            'user_id' => $primary->id,
            'spouse_id' => $spouse->id,
            'status' => 'accepted',
            'requested_at' => now()->subDay(),
            'responded_at' => now()->subDay(),
        ]);
        SpousePermission::create([
            'user_id' => $spouse->id,
            'spouse_id' => $primary->id,
            'status' => 'accepted',
            'requested_at' => now()->subDay(),
            'responded_at' => now()->subDay(),
        ]);

        Sanctum::actingAs($primary);
        $this->deleteJson('/api/spouse-permission/revoke')->assertOk();

        // Under the defect the two unordered reads could pick different rows, and
        // the user who withdrew would still be sharing.
        expect($primary->fresh()->hasAcceptedSpousePermission())->toBeFalse();
        expect($spouse->fresh()->hasAcceptedSpousePermission())->toBeFalse();
    });
});

describe('the re-ask migration', function () {
    it('turns rows nobody granted into unanswered requests, and leaves real decisions alone', function () {
        [$primary, $spouse] = reAskCouple();
        [$other, $otherSpouse] = reAskCouple();

        // Forged: the old path wrote `accepted` with `responded_at` and no request.
        SpousePermission::create([
            'user_id' => $primary->id, 'spouse_id' => $spouse->id,
            'status' => 'accepted', 'requested_at' => null, 'responded_at' => now()->subMonth(),
        ]);
        // Its mirror — the same couple, the opposite direction.
        SpousePermission::create([
            'user_id' => $spouse->id, 'spouse_id' => $primary->id,
            'status' => 'accepted', 'requested_at' => null, 'responded_at' => now()->subMonth(),
        ]);
        // A decision somebody actually made.
        SpousePermission::create([
            'user_id' => $other->id, 'spouse_id' => $otherSpouse->id,
            'status' => 'rejected', 'requested_at' => now()->subDay(), 'responded_at' => now()->subHour(),
        ]);

        $migration = require database_path('migrations/2026_08_24_130000_reask_spouse_permissions_nobody_granted.php');
        $migration->up();

        $forged = SpousePermission::where('user_id', $primary->id)
            ->orWhere('spouse_id', $primary->id)
            ->get();
        expect($forged)->toHaveCount(1);
        expect($forged->first()->status)->toBe('pending');
        expect($forged->first()->requested_at)->not->toBeNull();
        expect($forged->first()->responded_at)->toBeNull();
        expect($primary->fresh()->hasAcceptedSpousePermission())->toBeFalse();

        $settled = SpousePermission::where('user_id', $other->id)->first();
        expect($settled->status)->toBe('rejected');
        expect($settled->responded_at)->not->toBeNull();
    });

    it('asks a linked couple who have no row at all, rather than honouring the absence', function () {
        [$primary, $spouse] = reAskCouple();

        // No row: `hasAcceptedSpousePermission()` returns true on absence, so this
        // household is sharing today without anyone having agreed.
        expect($primary->hasAcceptedSpousePermission())->toBeTrue();

        $migration = require database_path('migrations/2026_08_24_130000_reask_spouse_permissions_nobody_granted.php');
        $migration->up();

        $row = SpousePermission::first();
        expect($row)->not->toBeNull();
        expect($row->status)->toBe('pending');
        expect($primary->fresh()->hasAcceptedSpousePermission())->toBeFalse();
        expect($spouse->fresh()->hasAcceptedSpousePermission())->toBeFalse();
    });
});

describe('the state the re-ask migration actually leaves behind', function () {
    it('tells a linked requester their request is outstanding', function () {
        // W-0347 G1 — the MODAL post-migration state: reciprocally linked AND holding
        // a pending row. `status()` required `! $user->spouse_id` for the outgoing
        // branch, so this fell through with no `awaiting_*` flag, and `/m` — which
        // reads only those flags — rendered "Sharing is off. Your accounts are linked"
        // with an "Ask to share again" button that answers 422.
        [$primary, $spouse] = reAskCouple();
        SpousePermission::create([
            'user_id' => $primary->id,
            'spouse_id' => $spouse->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($primary);
        $data = $this->getJson('/api/spouse-permission/status')->assertOk()->json('data');

        expect($data['awaiting_their_response'] ?? false)->toBeTrue();
        expect($data['can_view_spouse_data'])->toBeFalse();
    });

    it('tells the other party the request is theirs to answer', function () {
        [$primary, $spouse] = reAskCouple();
        SpousePermission::create([
            'user_id' => $primary->id,
            'spouse_id' => $spouse->id,
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        Sanctum::actingAs($spouse);
        $data = $this->getJson('/api/spouse-permission/status')->assertOk()->json('data');

        expect($data['awaiting_your_response'] ?? false)->toBeTrue();
        expect($data['can_view_spouse_data'])->toBeFalse();
    });
});
