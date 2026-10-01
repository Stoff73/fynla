<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\RetirementDataReadinessService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function incomeCheckFor(User $user): array
{
    $assessment = app(RetirementDataReadinessService::class)->assess($user->fresh());
    foreach (['blocking', 'warnings', 'info'] as $level) {
        foreach ((array) ($assessment[$level] ?? []) as $check) {
            if (($check['key'] ?? null) === 'income') {
                return $check;
            }
        }
    }

    return ['passed' => true];
}

it('does not block someone drawing their pension for having no earnings, with no retirement profile', function () {
    // csjones Pat (439): retired, drawing £30,000, no retirement profile, was
    // stopped by "Gross annual income is required" (2026-10-01, item 7 D4).
    $user = User::factory()->create([
        'employment_status' => 'retired', 'annual_employment_income' => 0, 'date_of_birth' => '1958-03-10',
    ]);
    DCPension::create([
        'user_id' => $user->id, 'scheme_name' => 'SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
        'current_fund_value' => 200000, 'annual_drawdown_income' => 30000,
    ]);

    expect(incomeCheckFor($user)['level'] ?? 'warning')->toBe('warning');
});

it('still blocks someone saving for retirement with no income recorded', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 0, 'date_of_birth' => '1985-03-10',
    ]);

    $check = incomeCheckFor($user);
    expect($check['level'])->toBe('blocking')->and($check['passed'])->toBeFalse();
});
