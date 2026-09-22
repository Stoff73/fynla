<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * MB-33. POST /api/onboarding/step validated only step_name and that data was
 * an array; the personal and income steps then wrote the array straight to
 * users.* columns every calculation reads. The boundary now applies the same
 * rules as the profile endpoints for those two steps.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // The wizard sets the life stage on its first screen; the step endpoint
    // computes the next step from it and refuses without one.
    $this->user = User::factory()->create([
        'is_preview_user' => false,
        'life_stage' => 'estate',
        'marital_status' => 'single',
        'annual_employment_income' => 40000,
    ]);
    Sanctum::actingAs($this->user);
});

it('rejects personal details the profile endpoint would reject, and writes nothing', function () {
    $this->postJson('/api/onboarding/step', [
        'step_name' => 'personal_info',
        'data' => [
            'date_of_birth' => now()->addDay()->format('Y-m-d'),
            'marital_status' => 'partnered',
            'gender' => 'x',
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.date_of_birth', 'data.marital_status', 'data.gender']);

    expect($this->user->fresh()->marital_status)->toBe('single');
});

it('rejects income figures the profile endpoint would reject', function () {
    $this->postJson('/api/onboarding/step', [
        'step_name' => 'income',
        'data' => [
            'employment_status' => 'gig',
            'annual_employment_income' => -5,
            'target_retirement_age' => 12,
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.employment_status', 'data.annual_employment_income', 'data.target_retirement_age']);

    expect((float) $this->user->fresh()->annual_employment_income)->toBe(40000.0);
});

it('still accepts a valid personal step, including a civil partnership, and writes the columns', function () {
    $this->postJson('/api/onboarding/step', [
        'step_name' => 'personal_info',
        'data' => [
            // The wizard sends the name and email fields too, which this
            // step does not write; the email uniqueness rule must not be
            // applied under the data. prefix (live 2026-09-14: 500,
            // "Unknown column 'data.email'").
            'first_name' => 'Pause',
            'surname' => 'Tester',
            'email' => $this->user->email,
            'date_of_birth' => '1990-01-15',
            'marital_status' => 'civil_partnership',
            'gender' => 'other',
            'postcode' => '',
            'phone' => null,
        ],
    ])->assertOk();

    $fresh = $this->user->fresh();
    expect($fresh->marital_status)->toBe('civil_partnership')
        ->and($fresh->date_of_birth->format('Y-m-d'))->toBe('1990-01-15');
});

it('still accepts a valid income step', function () {
    $this->postJson('/api/onboarding/step', [
        'step_name' => 'income',
        'data' => [
            'employment_status' => 'full_time',
            'occupation' => 'Teacher',
            'annual_employment_income' => 46000,
            'target_retirement_age' => 67,
        ],
    ])->assertOk();

    expect((float) $this->user->fresh()->annual_employment_income)->toBe(46000.0);
});

it('accepts a phone number typed with spaces and stores the digits, as the profile endpoint does', function () {
    $this->postJson('/api/onboarding/step', [
        'step_name' => 'personal_info',
        'data' => ['phone' => '07700 900123'],
    ])->assertOk();

    expect($this->user->fresh()->phone)->toBe('07700900123');
});

it("names the field's own message for a bad phone number, not the data.phone attribute", function () {
    $this->postJson('/api/onboarding/step', [
        'step_name' => 'personal_info',
        'data' => ['phone' => '12345'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['data.phone' => 'Please enter a valid UK phone number']);
});

it('stores childcare and charitable donations from the onboarding expenditure step in Simple View', function () {
    $this->postJson('/api/onboarding/step', [
        'step_name' => 'expenditure',
        'data' => [
            'use_simple_entry' => true,
            'expenditure_entry_mode' => 'simple',
            'monthly_expenditure' => 2500,
            'annual_expenditure' => 30000,
            'childcare' => 700,
            'charitable_donations' => 40,
            'is_gift_aid' => true,
        ],
    ])->assertOk();

    $fresh = $this->user->fresh();
    expect((float) $fresh->childcare)->toBe(700.0)
        ->and((float) $fresh->charitable_donations)->toBe(40.0)
        ->and($fresh->is_gift_aid)->toBeTrue()
        ->and((float) $fresh->monthly_expenditure)->toBe(2500.0);
});
