<?php

declare(strict_types=1);

use App\Services\Stores\TierConfigurationStore;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the approved Free and Premium commercial matrix exactly', function () {
    $this->seed(TierConfigurationSeeder::class);

    $store = app(TierConfigurationStore::class);
    $free = $store->forTier('free');
    $premium = $store->forTier('premium');

    $freeCapabilities = [
        'dashboard' => 'full',
        'protection' => 'full',
        'income' => 'full',
        'liabilities' => 'full',
        'expenditure' => 'full',
        'expenditure_detailed' => 'none',
        'tax_strategy' => 'full',
        'risk_profile' => 'full',
        'future_value_projections' => 'full',
        'savings_account' => 'limited',
        'investment' => 'limited',
        'pension_account' => 'limited',
        'property' => 'limited',
        'goals' => 'limited',
        'life_events' => 'limited',
        'chattels' => 'full',
        'estate' => 'teaser',
        'joint_household_view' => 'none',
        'letter_to_spouse' => 'none',
        'investments_exotic' => 'none',
        'property_buy_to_let_analysis' => 'none',
        'retirement_decumulation' => 'none',
        'what_if' => 'none',
        'holistic_plan' => 'none',
        'document_upload' => 'none',
        'statement_upload' => 'none',
        'advisor_export' => 'none',
        'investment_cost_analysis' => 'none',
        'benefits_child' => 'full',
        'family_module' => 'full',
    ];

    expect($free->capability_matrix)->toMatchArray($freeCapabilities)
        ->and($free->count_caps)->toMatchArray([
            'savings_account' => 2,
            'investment' => 2,
            'pension_account' => 2,
            'property' => 1,
            'mortgage' => 10,
            'goal' => 2,
            'life_event' => 1,
        ])
        ->and($free->document_upload_allowance)->toBe(0)
        ->and($free->fyn_weekly_token_budget)->toBe(100_000)
        ->and($free->snapshot_surfacing_window_days)->toBe(90)
        ->and($premium->price_monthly_pence)->toBe(699)
        ->and($premium->price_annual_pence)->toBe(5999)
        ->and($premium->count_caps)->toMatchArray([
            'savings_account' => null,
            'investment' => null,
            'pension_account' => null,
            'property' => null,
            'mortgage' => null,
            'goal' => null,
            'life_event' => null,
        ])
        ->and($premium->document_upload_allowance)->toBeNull()
        ->and((float) $premium->document_storage_gb)->toBe(1.00)
        ->and($premium->fyn_weekly_token_budget)->toBe(500_000)
        ->and($premium->snapshot_surfacing_window_days)->toBeNull();

    foreach (array_keys($freeCapabilities) as $capability) {
        expect($premium->capability_matrix[$capability] ?? null)
            ->toBe('full', "Premium capability {$capability} must be full.");
    }
});
