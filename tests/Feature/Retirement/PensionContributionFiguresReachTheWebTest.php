<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

/*
 * The web pension card and detail read these four figures from the retirement
 * payload (DCPensionResource). The walk on 2026-09-26 showed "5.00% (£0/mo)"
 * because the resource did not carry them. Form-captured shape: percentages,
 * no scheme salary; a personal pension paid net (FA 2004 s192).
 */
beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('sends the server contribution figures for a form-captured workplace and personal pension', function () {
    $user = User::factory()->create(['annual_employment_income' => 60000, 'onboarding_completed' => true]);
    DCPension::factory()->for($user)->create([
        'scheme_name' => 'Nest workplace pension', 'scheme_type' => null, 'pension_type' => 'occupational',
        'salary_sacrifice' => false, 'annual_salary' => null, 'monthly_contribution_amount' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);
    DCPension::factory()->for($user)->create([
        'scheme_name' => 'Vanguard personal pension or SIPP', 'scheme_type' => null, 'pension_type' => 'personal',
        'annual_salary' => null, 'monthly_contribution_amount' => 200,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    Sanctum::actingAs($user);

    $pensions = collect($this->getJson('/api/retirement')->assertOk()->json('data.dc_pensions'))->keyBy('scheme_name');
    $nest = $pensions['Nest workplace pension'];
    $sipp = $pensions['Vanguard personal pension or SIPP'];

    expect((float) $nest['monthly_employee_contribution'])->toBe(250.0)
        ->and((float) $nest['monthly_employer_contribution'])->toBe(150.0)
        ->and((float) $nest['monthly_contribution'])->toBe(400.0)
        ->and($nest['contribution_includes_relief'])->toBeFalse()
        ->and((float) $sipp['monthly_contribution'])->toBe(250.0)
        ->and($sipp['contribution_includes_relief'])->toBeTrue();
});
