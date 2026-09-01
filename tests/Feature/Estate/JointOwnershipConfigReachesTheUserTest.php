<?php

declare(strict_types=1);

use App\Services\TaxConfigSnapshotService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * W-0498. `property_ownership.joint_ownership_types` is live and populated, its four
 * `TaxConfigService` accessors had zero callers, and its `notes` are written as
 * user-facing sentences.
 *
 * **The item offered three classifications — gap, deliberately unused, dead — and the
 * evidence says none of them, as stated.** The concept IS shown to the user:
 * `AssetForm.vue:299-301` carried *"Automatically passes to surviving owner on death"*
 * and *"Your share passes via your will"*, hardcoded, beside a configured version of the
 * same two sentences that nothing read. So it was a Rule 20 duplicate with a boundary in
 * the middle: the cluster was published to the backend config and never to the frontend
 * snapshot, so the one consumer that needed it reimplemented it.
 *
 * The classification is therefore per accessor, which the item allowed for:
 *   getPropertyOwnership()  / getJointOwnershipType() — GAP, now wired through the snapshot
 *   hasSurvivorshipRights() / allowsWillOverride()    — DELIBERATELY UNUSED, see below
 */
it('publishes the joint-ownership descriptions the form needs', function () {
    $snapshot = app(TaxConfigSnapshotService::class)->build();

    expect($snapshot)->toHaveKey('property_ownership')
        ->and($snapshot['property_ownership']['joint_ownership_types'])
        ->toHaveKeys(['joint_tenancy', 'tenants_in_common']);

    $jointTenancy = $snapshot['property_ownership']['joint_ownership_types']['joint_tenancy'];

    expect($jointTenancy)->toHaveKeys(['name', 'description', 'notes'])
        ->and($jointTenancy['notes'])->toContain('surviving owner');
});

it('leaves no hardcoded copy of those sentences in the form', function () {
    // The duplicate is the defect. A consumer that reads the config while a second copy
    // sits beside it has not closed anything — the two just drift more slowly.
    $form = (string) file_get_contents(base_path('resources/js/components/Estate/AssetForm.vue'));

    expect($form)->not->toContain('Automatically passes to surviving owner on death')
        ->and($form)->not->toContain('Your share passes via your will');
});

it('keeps survivorship off the second-death estate path', function () {
    // Acceptance 3, and W-0375's warning. `EstateAssetAggregatorService` produces a
    // SECOND-death estate: there is no survivor left for a joint tenancy to pass to, so
    // consulting survivorship there would be wrong. This item must not reopen that, and
    // the two boolean accessors stay caller-less BY DESIGN rather than by omission —
    // which is the "deliberately unused" classification, stated instead of silent.
    $aggregator = (string) file_get_contents(base_path('app/Services/Estate/EstateAssetAggregatorService.php'));

    expect($aggregator)->not->toContain('->hasSurvivorshipRights(')
        ->and($aggregator)->not->toContain('->allowsWillOverride(');
});

it('records that the two boolean accessors are deliberately unused', function () {
    // Acceptance 2's second branch. Silence is what let this cluster sit, so the
    // absence of a caller is asserted here with its reason rather than left to the next
    // reader to rediscover as suspicious dead code.
    $service = (string) file_get_contents(base_path('app/Services/TaxConfigService.php'));

    expect($service)->toContain('W-0498');
});
