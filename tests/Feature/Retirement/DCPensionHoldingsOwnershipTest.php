<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Investment\Holding;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0444 — a pension that is not yours returns 404, not 500.
 *
 * The ownership helper threw `ModelNotFoundException` **without the class being
 * imported**, so PHP resolved it against the controller's own namespace —
 * `App\Http\Controllers\Api\Retirement\ModelNotFoundException`, which does not
 * exist. Every not-found path on all five endpoints therefore raised a fatal
 * `Error` and returned a 500 carrying a class-resolution failure, instead of the
 * 404 the HTTP conventions promise (`app/Http/CLAUDE.md`: "404 — Not found or
 * access denied").
 *
 * **Why nothing caught it.** `DCPensionHoldingValuationTest` gives every case a
 * pension the acting user owns, so the branch was never entered. Nothing in that
 * file says "and no unowned pension is ever requested here" — the Fixture variant
 * in `tests/CLAUDE.md` §4, which is invisible by construction. The countermeasure
 * named there is to ask what the fixture does NOT contain, and what it did not
 * contain was a second user.
 *
 * These cases exist to enter that branch, so a second user is the whole fixture.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    $this->owner = User::factory()->create();
    $this->stranger = User::factory()->create();

    $this->pension = DCPension::factory()->create([
        'user_id' => $this->owner->id,
        'current_fund_value' => 320000,
    ]);

    $this->holding = Holding::factory()->create([
        'holdable_type' => DCPension::class,
        'holdable_id' => $this->pension->id,
        'security_name' => 'Vanguard Global Equity',
        'asset_type' => 'fund',
        'allocation_percent' => 50,
        'current_value' => 160018,
    ]);

    $this->actingAs($this->stranger, 'sanctum');
});

it('refuses to list the holdings of a pension belonging to someone else', function () {
    $this->getJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings")
        ->assertNotFound();
});

it('refuses to add a holding to a pension belonging to someone else', function () {
    $this->postJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings", [
        'security_name' => 'BlackRock Corporate Bond',
        'asset_type' => 'bond',
        'current_value' => 96360,
    ])->assertNotFound();

    // The refusal has to be a refusal, not merely a status code: a fatal error
    // raised AFTER a write would also have failed to be a 404.
    expect(Holding::query()->count())->toBe(1);
});

it('refuses to edit a holding on a pension belonging to someone else', function () {
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings/{$this->holding->id}", [
        'current_value' => 1,
    ])->assertNotFound();

    // £160,018 rather than a round figure so the untouched value cannot coincide
    // with anything a partial write would have produced.
    expect((float) $this->holding->fresh()->current_value)->toBe(160018.0);
});

it('refuses to delete a holding on a pension belonging to someone else', function () {
    $this->deleteJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings/{$this->holding->id}")
        ->assertNotFound();

    expect($this->holding->fresh()->deleted_at)->toBeNull();
});

it('refuses a bulk revaluation of a pension belonging to someone else', function () {
    $this->postJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings/bulk-update", [
        'holdings' => [['id' => $this->holding->id, 'current_value' => 1]],
    ])->assertNotFound();

    expect((float) $this->holding->fresh()->current_value)->toBe(160018.0);
});

it('still serves the holdings to the user who owns the pension', function () {
    // The other direction. Without this, deleting the ownership check entirely
    // and returning 404 unconditionally would satisfy every case above.
    $this->actingAs($this->owner, 'sanctum')
        ->getJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings")
        ->assertOk()
        ->assertJsonPath('data.0.security_name', 'Vanguard Global Equity');
});
