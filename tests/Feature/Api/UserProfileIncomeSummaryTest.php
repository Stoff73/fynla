<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * The /m Income screen reads getCompleteProfile()'s `income_summary` block —
 * a flat per-source breakdown for the user (and spouse when linked).
 */
it('returns a flat income_summary block for the user', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 80000,
        'annual_self_employment_income' => 5000,
        'annual_dividend_income' => 1000,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
    ]);

    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk();
    $summary = $response->json('data.income_summary');

    // JSON round-trips whole floats as ints (80000.0 → "80000" → 80000), so use
    // loose equality on the numeric fields.
    expect($summary)->toHaveKeys(['user', 'spouse'])
        ->and($summary['user']['employment'])->toEqual(80000)
        ->and($summary['user']['self_employment'])->toEqual(5000)
        ->and($summary['user']['dividend'])->toEqual(1000)
        ->and($summary['user']['total'])->toEqual(86000)
        ->and($summary['user']['sources'][0])->toMatchArray([
            'key' => 'employment',
            'label' => 'Employment',
            'amount' => 80000,
            'frequency' => 'annual',
            'ownership' => 'user',
            'tax_position' => 'Taxable earned income',
        ])
        ->and($summary['user']['tax_position'])->toMatchArray([
            'total_income' => 86000,
            'adjusted_net_income' => 86000,
            'personal_allowance_label' => 'Standard personal allowance',
        ])
        ->and($summary['spouse'])->toBeNull();
});

it('returns server-owned expenditure mode and totals reconciled to category entry', function () {
    $user = User::factory()->create([
        'is_admin' => true,
        'expenditure_entry_mode' => 'category',
        'monthly_expenditure' => 9999,
        'annual_expenditure' => 119988,
        'food_groceries' => 600,
        'transport_fuel' => 200,
        'other_expenditure' => 100,
    ]);

    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk()
        ->assertJsonPath('data.expenditure.presentation.entry_mode', 'category')
        ->assertJsonPath('data.expenditure.presentation.entry_mode_label', 'Category detail')
        ->assertJsonPath('data.expenditure.presentation.active_monthly_total', 900)
        ->assertJsonPath('data.expenditure.presentation.active_annual_total', 10800)
        ->assertJsonPath('data.expenditure.presentation.detail_available', true)
        ->assertJsonPath('data.expenditure.presentation.reconciles', true)
        ->assertJsonPath('data.expenditure.presentation.summary_only_reason', null);
});

it('explains summary-only expenditure without exposing hidden category values', function () {
    $user = User::factory()->create([
        'expenditure_entry_mode' => 'simple',
        'monthly_expenditure' => 1800,
        'annual_expenditure' => 21600,
        'food_groceries' => 600,
    ]);

    $response = $this->actingAs($user)->getJson('/api/user/profile');

    $response->assertOk()
        ->assertJsonMissingPath('data.expenditure.categories')
        ->assertJsonPath('data.expenditure.presentation.entry_mode', 'summary')
        ->assertJsonPath('data.expenditure.presentation.active_monthly_total', 1800)
        ->assertJsonPath('data.expenditure.presentation.active_annual_total', 21600)
        ->assertJsonPath('data.expenditure.presentation.detail_available', false)
        ->assertJsonPath('data.expenditure.presentation.reconciles', true)
        ->assertJsonPath(
            'data.expenditure.presentation.summary_only_reason',
            'Only a monthly summary has been entered. Add category details to improve your insights.'
        );
});
