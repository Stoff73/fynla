<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T26 (N2, RC9): Fyn's pension form sends scheme_type 'occupational', which the
 * normaliser turned into pension_type only, so dc_pensions.scheme_type stayed
 * NULL. The employer-match, auto-enrolment and salary-sacrifice checks require
 * 'workplace' (RetirementActionDefinitionService :521, :1597), and production
 * Priya (pension 529, 6% + 8% through pay) was told "You and your employer pay
 * 0.0% in total". 9 of 25 occupational pensions on production had no type.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false]);
});

it('gives a pension saved through Fyn the scheme type the web form gives it', function (string $sent, string $schemeType) {
    $result = app(CoordinatingAgent::class)->executeTool('create_pension', [
        'pension_category' => 'dc', 'scheme_name' => 'Monzo pension', 'provider' => 'Nest',
        'scheme_type' => $sent, 'current_fund_value' => 18000,
        'employee_contribution_percent' => 6, 'employer_contribution_percent' => 8,
    ], $this->user);

    expect(DCPension::find($result['entity_id'])->scheme_type)->toBe($schemeType);
})->with([
    'the form\'s workplace kind' => ['occupational', 'workplace'],
    'a model saying workplace' => ['workplace', 'workplace'],
    'a SIPP' => ['sipp', 'sipp'],
    'a stakeholder pension' => ['stakeholder', 'personal'],
    'a personal pension' => ['personal', 'personal'],
]);

it('backfills a missing scheme type from the pension type, after a dry run', function () {
    $workplace = DCPension::factory()->create(['user_id' => $this->user->id, 'pension_type' => 'occupational', 'scheme_type' => null]);
    $sipp = DCPension::factory()->create(['user_id' => $this->user->id, 'pension_type' => 'sipp', 'scheme_type' => null]);
    $set = DCPension::factory()->create(['user_id' => $this->user->id, 'pension_type' => 'occupational', 'scheme_type' => 'personal']);

    $this->artisan('pensions:backfill-dc-scheme-type', ['--dry-run' => true])
        ->expectsOutputToContain('Would set the scheme type on 2 pension(s)')
        ->assertSuccessful();
    expect($workplace->fresh()->scheme_type)->toBeNull();

    $this->artisan('pensions:backfill-dc-scheme-type')->assertSuccessful();
    expect($workplace->fresh()->scheme_type)->toBe('workplace')
        ->and($sipp->fresh()->scheme_type)->toBe('sipp')
        ->and($set->fresh()->scheme_type)->toBe('personal');
});
