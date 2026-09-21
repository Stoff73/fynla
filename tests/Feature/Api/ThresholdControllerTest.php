<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    Carbon::setTestNow('2026-09-21');
});

afterEach(fn () => Carbon::setTestNow());

it('requires authentication', function () {
    $this->getJson('/api/thresholds')->assertUnauthorized();
});

it('returns nothing for a user no line applies to', function () {
    Sanctum::actingAs(User::factory()->create(['annual_employment_income' => 30000]));

    $this->getJson('/api/thresholds')
        ->assertOk()
        ->assertJsonPath('data.strip', null)
        ->assertJsonPath('data.lines', []);
});

it('leads with the taper for the spec profile and keeps date lines last', function () {
    $user = User::factory()->create(['annual_employment_income' => 112400, 'childcare' => 1000]);
    FamilyMember::create(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'relationship' => 'child', 'date_of_birth' => '2023-03-01']);
    DCPension::create(['user_id' => $user->id, 'scheme_name' => 'SIPP', 'pension_type' => 'personal', 'current_fund_value' => 400000]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/thresholds')->assertOk();
    $lines = $response->json('data.lines');
    $units = array_column(array_column($lines, 'position'), 'unit');
    $labels = array_column($lines[0]['cost']['benefits'], 'label');

    expect($response->json('data.strip'))->toBe('pa_taper')
        ->and($lines[0]['headline'])->toBe('You are £12,400 into the 60% band')
        ->and($labels)->toContain('Tax-Free Childcare')
        ->and($labels)->toContain('Funded childcare hours')
        ->and($lines[0]['cost_total'])->toBe(round($lines[0]['cost']['income_tax'] + array_sum(array_column($lines[0]['cost']['benefits'], 'amount')), 2))
        ->and(array_search('days', $units, true))->toBeGreaterThan(array_search('gbp', $units, true));
});
