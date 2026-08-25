<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('create_pension persists a DCPension when pension_category is dc', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Workplace',
        'provider' => 'Aviva',
        'scheme_type' => 'workplace',
        'current_fund_value' => 50000,
        'employee_contribution_percent' => 5,
        'employer_contribution_percent' => 4,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('dc_pension');

    $pension = DCPension::find($result['entity_id']);
    expect($pension)->not->toBeNull();
    expect($pension->scheme_name)->toBe('Aviva Workplace');
    expect($pension->provider)->toBe('Aviva');
    expect($pension->pension_type)->toBe('occupational'); // workplace -> occupational
    expect((float) $pension->current_fund_value)->toBe(50000.0);
});

it('create_pension persists a DBPension when pension_category is db', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'db',
        'scheme_name' => 'NHS Pension Scheme',
        'accrued_annual_pension' => 12000,
        'pensionable_service_years' => 8,
        'normal_retirement_age' => 65,
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_type'])->toBe('db_pension');

    $pension = DBPension::find($result['entity_id']);
    expect($pension)->not->toBeNull();
    expect($pension->scheme_name)->toBe('NHS Pension Scheme');
    expect($pension->scheme_type)->toBe('final_salary'); // default
    expect((float) $pension->accrued_annual_pension)->toBe(12000.0);
});

it('create_pension maps SIPP scheme_type to sipp pension_type for DC', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'dc',
        'scheme_name' => 'Hargreaves Lansdown SIPP',
        'scheme_type' => 'sipp',
        'current_fund_value' => 100000,
    ], $user);

    expect(DCPension::find($result['entity_id'])->pension_type)->toBe('sipp');
});

it('create_pension returns validation_failed when pension_category is missing', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'scheme_name' => 'X',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
    expect($result['error_type'])->toBe('validation_failed');
});

it('create_pension blocks preview users', function (): void {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'dc',
        'scheme_name' => 'X',
    ], $user);

    expect($result['blocked'])->toBeTrue();
});

it('create_pension return shape has no fill_form action', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'dc',
        'scheme_name' => 'X',
    ], $user);

    expect($result)->not->toHaveKey('action');
    expect($result)->not->toHaveKey('fields');
    expect($result)->not->toHaveKey('route');
});

/**
 * W-0017, the /m and native half. Those surfaces have no pension form at all —
 * every pension is entered through Fyn — and the create_pension schema exposed
 * neither the spouse benefit, the inflation protection, the lump sum, nor a
 * public-sector scheme type, while `update_record`'s db_pension allowlist could
 * not correct any of them afterwards. PensionNormaliser and PensionStore both
 * already understood every one of these fields; only the catalogue withheld them.
 */
it('create_pension records the spouse benefit, inflation protection and lump sum on a Defined Benefit scheme', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'db',
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 35000,
        'pensionable_service_years' => 18,
        'normal_retirement_age' => 60,
        'spouse_pension_percent' => 50,
        'inflation_protection' => 'cpi',
        'lump_sum_entitlement' => 105000,
    ], $user);

    expect($result['success'])->toBeTrue();

    $pension = DBPension::find($result['entity_id']);

    expect($pension->scheme_type)->toBe('career_average')
        ->and($pension->normal_retirement_age)->toBe(60)
        ->and((float) $pension->spouse_pension_percent)->toBe(50.0)
        ->and($pension->inflation_protection)->toBe('cpi')
        ->and((float) $pension->lump_sum_entitlement)->toBe(105000.0);
});

it('create_pension accepts a public sector Defined Benefit scheme type', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'db',
        'scheme_name' => 'Civil Service Alpha',
        'scheme_type' => 'public_sector',
        'accrued_annual_pension' => 9000,
    ], $user);

    expect(DBPension::find($result['entity_id'])->scheme_type)->toBe('public_sector');
});

it('create_pension refuses an inflation protection value outside the enum', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'db',
        'scheme_name' => 'NHS Pension Scheme',
        'accrued_annual_pension' => 35000,
        'inflation_protection' => 'cpi_plus_1_5',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'])->toBe('validation_failed');
});

it('update_record can correct the Defined Benefit fields Fyn could not previously touch', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_type' => 'final_salary',
        'spouse_pension_percent' => null,
        'inflation_protection' => 'none',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('update_record', [
        'entity_type' => 'db_pension',
        'entity_id' => $pension->id,
        'fields' => [
            'scheme_type' => 'career_average',
            'spouse_pension_percent' => 50,
            'inflation_protection' => 'cpi',
            'lump_sum_entitlement' => 105000,
        ],
    ], $user);

    expect($result['success'] ?? false)->toBeTrue();

    $pension->refresh();

    expect($pension->scheme_type)->toBe('career_average')
        ->and((float) $pension->spouse_pension_percent)->toBe(50.0)
        ->and($pension->inflation_protection)->toBe('cpi')
        ->and((float) $pension->lump_sum_entitlement)->toBe(105000.0);
});
