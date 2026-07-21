<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\ExpenditureProfile;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * FR-M17 — conversational retraction.
 *
 * When the user contradicts a prior answer ("actually my DOB is 12 March
 * 1985, not 1986"), the asset-capture prompt instructs the LLM to emit
 * update_profile for personal facts. This test pins the handler side:
 * update_profile with section=personal and a field dict must correctly
 * update the user's row.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('updates personal fields via update_profile tool', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '1986-01-01',
        'marital_status' => 'single',
    ]);

    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('update_profile', [
        'section' => 'personal',
        'fields' => [
            'date_of_birth' => '1985-03-12',
            'marital_status' => 'married',
        ],
    ], $user);

    expect($result['updated'] ?? null)->toBeTrue();
    expect($result['section'])->toBe('personal');

    $user->refresh();
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1985-03-12');
    expect($user->marital_status)->toBe('married');
});

it('silently drops fields outside the allow-list (PII protection)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'first_name' => 'Chris',
    ]);

    $agent = app(CoordinatingAgent::class);

    $result = $agent->executeTool('update_profile', [
        'section' => 'personal',
        'fields' => [
            'first_name' => 'UpdatedName',
            // NI number MUST NOT be writable via AI per handler comments.
            'national_insurance_number' => 'QQ123456C',
        ],
    ], $user);

    expect($result['updated'] ?? null)->toBeTrue();

    $user->refresh();
    expect($user->first_name)->toBe('UpdatedName');
    // NI number unchanged — the handler's allow-list dropped it.
    expect($user->national_insurance_number)->not->toBe('QQ123456C');
});

it('rejects updates to unknown sections', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'not_a_real_section',
        'fields' => ['date_of_birth' => '2000-01-01'],
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['message'])->toContain('Unknown profile section');
});

it('rejects empty field dict', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'personal',
        'fields' => [],
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['message'])->toContain('No fields provided');
});

it('updates Save Tax spouse household data without changing the primary user income', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 82000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 35000,
        'spouse_isa_balance' => 8000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'spouse_household',
        'fields' => ['spouse_annual_income' => 38000],
    ], $user);

    expect($result['updated'] ?? null)->toBeTrue()
        ->and((float) $user->fresh()->annual_employment_income)->toBe(82000.0)
        ->and((float) TaxStrategyHouseholdInput::where('user_id', $user->id)->value('spouse_annual_income'))->toBe(38000.0)
        ->and((float) TaxStrategyHouseholdInput::where('user_id', $user->id)->value('spouse_isa_balance'))->toBe(8000.0);
});

it('mechanically rejects a primary-income update during spouse review', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 82000,
    ]);
    $agent = app(CoordinatingAgent::class);
    $agent->setVerifyEditScope([
        'tools' => ['update_profile'],
        'records' => [],
        'profile_sections' => ['spouse_household'],
        'record_fields' => [],
        'profile_fields' => ['spouse_household' => ['spouse_annual_income']],
        'tool_fields' => [],
    ]);

    $result = $agent->executeTool('update_profile', [
        'section' => 'income_occupation',
        'fields' => ['annual_employment_income' => 38000],
    ], $user);

    expect($result['error_type'] ?? null)->toBe('verify_edit_scope_violation')
        ->and((float) $user->fresh()->annual_employment_income)->toBe(82000.0);
});

it('rejects invalid spouse household values atomically', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 35000,
        'spouse_psa_band' => 'basic',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'spouse_household',
        'fields' => [
            'spouse_annual_income' => -1,
            'spouse_psa_band' => 'invented',
        ],
    ], $user);

    $household = TaxStrategyHouseholdInput::where('user_id', $user->id)->firstOrFail();
    expect($result['error'])->toBeTrue()
        ->and($result['error_type'])->toBe('validation_failed')
        ->and((float) $household->spouse_annual_income)->toBe(35000.0)
        ->and($household->spouse_psa_band)->toBe('basic');
});

it('updates the canonical household branch when spouse work status is corrected', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 82000,
        'household_calculation_mode' => 'single_earner_couple',
        'marriage_allowance_eligible' => true,
    ]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_existing_isa_balance' => 5000,
        'spouse_annual_income' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'spouse_household',
        'fields' => ['spouse_works' => true],
    ], $user);

    $fresh = $user->fresh();
    $household = TaxStrategyHouseholdInput::where('user_id', $user->id)->firstOrFail();
    expect($result['updated'])->toBeTrue()
        ->and($fresh->household_calculation_mode)->toBe('dual_earner')
        ->and($fresh->marriage_allowance_eligible)->toBeFalse()
        ->and((float) $fresh->annual_employment_income)->toBe(82000.0)
        ->and($household->spouse_existing_isa_balance)->toBeNull();
});

it('refuses a spouse household update when there is no reviewed row to update', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_employment_income' => 82000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'spouse_household',
        'fields' => ['spouse_annual_income' => 38000],
    ], $user);

    expect($result['error'] ?? null)->toBeTrue()
        ->and(TaxStrategyHouseholdInput::where('user_id', $user->id)->exists())->toBeFalse()
        ->and((float) $user->fresh()->annual_employment_income)->toBe(82000.0);
});

it('updates the reviewed monthly expenditure total and its mirrored profile', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'monthly_expenditure' => 3200,
        'annual_expenditure' => 38400,
    ]);
    ExpenditureProfile::create([
        'user_id' => $user->id,
        'total_monthly_expenditure' => 3200,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'expenditure',
        'fields' => ['monthly_expenditure' => 3450],
    ], $user);

    expect($result['updated'] ?? null)->toBeTrue()
        ->and((float) $user->fresh()->monthly_expenditure)->toBe(3450.0)
        ->and((float) $user->fresh()->annual_expenditure)->toBe(41400.0)
        ->and((float) ExpenditureProfile::where('user_id', $user->id)->value('total_monthly_expenditure'))->toBe(3450.0);
});

it('rejects contradictory monthly and annual expenditure totals', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'monthly_expenditure' => 3200,
        'annual_expenditure' => 38400,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'expenditure',
        'fields' => ['monthly_expenditure' => 3500, 'annual_expenditure' => 12000],
    ], $user);

    expect($result['error'])->toBeTrue()
        ->and($result['error_type'])->toBe('validation_failed')
        ->and((float) $user->fresh()->monthly_expenditure)->toBe(3200.0);
});

it('rejects a mixed expenditure total and category payload', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'monthly_expenditure' => 3200,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'expenditure',
        'fields' => ['monthly_expenditure' => 3500, 'rent' => 1200],
    ], $user);

    expect($result['error'])->toBeTrue()
        ->and($result['error_type'])->toBe('validation_failed')
        ->and((float) $user->fresh()->monthly_expenditure)->toBe(3200.0);
});

it('does not allow direct rental-income edits that can drift from property records', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'annual_rental_income' => 12000,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'income_occupation',
        'fields' => ['annual_rental_income' => 18000],
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'] ?? null)->toBe('validation_failed')
        ->and((float) $user->fresh()->annual_rental_income)->toBe(12000.0);
});

it('requires replacement income when changing between employed and self-employed', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'employment_status' => 'employed',
        'annual_employment_income' => 82000,
        'annual_self_employment_income' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'income_occupation',
        'fields' => ['employment_status' => 'self_employed'],
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'] ?? null)->toBe('validation_failed')
        ->and($user->fresh()->employment_status)->toBe('employed')
        ->and((float) $user->fresh()->annual_employment_income)->toBe(82000.0);
});

it('switches income source atomically when status and replacement income are supplied together', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'employment_status' => 'employed',
        'annual_employment_income' => 82000,
        'annual_self_employment_income' => null,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_profile', [
        'section' => 'income_occupation',
        'fields' => [
            'employment_status' => 'self_employed',
            'annual_self_employment_income' => 40000,
        ],
    ], $user);

    $fresh = $user->fresh();
    expect($result['updated'] ?? false)->toBeTrue()
        ->and($fresh->employment_status)->toBe('self_employed')
        ->and((float) $fresh->annual_self_employment_income)->toBe(40000.0)
        ->and($fresh->annual_employment_income)->toBeNull();
});
