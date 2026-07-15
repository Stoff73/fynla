<?php

declare(strict_types=1);

use App\Models\TierConfiguration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('has a tier_configurations table with the canonical columns', function () {
    expect(Schema::hasTable('tier_configurations'))->toBeTrue();
    expect(Schema::hasColumns('tier_configurations', [
        'id', 'tier', 'display_name',
        'price_monthly_pence', 'price_annual_pence', 'revolut_plan_variation_id',
        'capability_matrix', 'count_caps',
        'document_upload_allowance', 'document_storage_gb',
        'fyn_weekly_token_budget', 'fyn_daily_hard_backstop',
        'currency_display_mode', 'snapshot_surfacing_window_days',
        'open_api_affordance', 'is_active', 'updated_by',
    ]))->toBeTrue();
});

it('enforces a unique tier slug', function () {
    TierConfiguration::create(tierConfigFixture('free'));
    expect(fn () => TierConfiguration::create(tierConfigFixture('free')))
        ->toThrow(QueryException::class);
});

it('accepts only canonical tier identities', function () {
    TierConfiguration::create(tierConfigFixture('free'));
    TierConfiguration::create(tierConfigFixture('premium'));

    expect(TierConfiguration::pluck('tier')->sort()->values()->all())
        ->toBe(['free', 'premium']);
});

it('rejects retired tier identities', function (string $tier) {
    expect(fn () => TierConfiguration::create(tierConfigFixture($tier)))
        ->toThrow(QueryException::class);
})->with(['tier1', 'tier2', 'tier3']);
