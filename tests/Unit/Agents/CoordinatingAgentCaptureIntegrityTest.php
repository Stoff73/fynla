<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\DCPension;
use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    // PensionStore's tier gate needs the tier configuration rows.
    $this->seed(TierConfigurationSeeder::class);
});

/**
 * WP-1 capture integrity — the 2026-07-03 "Beta Ltd Workplace Pension"
 * incident: the model zero-fills unknown fields, and validation rejected an
 * otherwise-valid pension (normal_retirement_age: 0 vs min:50) with no
 * user-visible error; capture_salary_sacrifice then failed on pension_id: 0.
 */
function executeCaptureTool(string $tool, array $input, User $user): array
{
    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, match ($tool) {
        'create_pension' => 'handleCreatePension',
        'capture_salary_sacrifice' => 'handleCaptureSalarySacrifice',
    });
    $m->setAccessible(true);

    return $tool === 'capture_salary_sacrifice'
        ? $m->invoke($agent, $input, $user, false)
        : $m->invoke($agent, $input, $user, false);
}

it('accepts the exact zero-filled workplace pension payload that failed live', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // Byte-for-byte the input from the 2026-07-03 ai_audit_events row.
    $result = executeCaptureTool('create_pension', [
        'preview' => false,
        'provider' => 'Beta Ltd',
        'scheme_name' => 'Beta Ltd Workplace Pension',
        'scheme_type' => 'workplace',
        'accrual_rate' => 0,
        'final_salary' => 0,
        'annual_salary' => 80000,
        'scheme_status' => 'Active',
        'retirement_age' => 0,
        'pension_category' => 'dc',
        'current_fund_value' => 0,
        'normal_retirement_age' => 0,
        'accrued_annual_pension' => 0,
        'pensionable_service_years' => 0,
        'monthly_contribution_amount' => 0,
        'employee_contribution_percent' => 5,
        'employer_contribution_percent' => 5,
    ], $user);

    expect($result['created'] ?? false)->toBeTrue()
        ->and(DCPension::where('user_id', $user->id)->where('scheme_name', 'Beta Ltd Workplace Pension')->exists())->toBeTrue();
});

it('still rejects a genuinely invalid retirement age', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = executeCaptureTool('create_pension', [
        'pension_category' => 'dc',
        'scheme_name' => 'Test Pension',
        'normal_retirement_age' => 30,
    ], $user);

    expect($result['error'] ?? false)->toBeTrue();
});

it('resolves salary sacrifice onto the single DC pension when no id is given', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $pension = DCPension::factory()->create(['user_id' => $user->id, 'salary_sacrifice' => false]);

    // pension_id: 0 is what the model sent live; null/absent behave the same.
    $result = executeCaptureTool('capture_salary_sacrifice', [
        'pension_id' => 0,
        'salary_sacrifice' => true,
        'employer_ni_rebate_pct' => 0,
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue()
        ->and($pension->fresh()->salary_sacrifice)->toBeTrue();
});

it('returns a retryable error when no pension exists to attach salary sacrifice to', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = executeCaptureTool('capture_salary_sacrifice', [
        'salary_sacrifice' => true,
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'])->toBe('not_found');
});

it('asks which pension when several exist and no id is given', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    DCPension::factory()->create(['user_id' => $user->id]);
    DCPension::factory()->create(['user_id' => $user->id]);

    $result = executeCaptureTool('capture_salary_sacrifice', [
        'salary_sacrifice' => true,
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'])->toBe('ambiguous');
});

it('still targets an explicit pension id when given', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    DCPension::factory()->create(['user_id' => $user->id, 'salary_sacrifice' => false]);
    $target = DCPension::factory()->create(['user_id' => $user->id, 'salary_sacrifice' => false]);

    $result = executeCaptureTool('capture_salary_sacrifice', [
        'pension_id' => $target->id,
        'salary_sacrifice' => true,
    ], $user);

    expect($result['updated'] ?? false)->toBeTrue()
        ->and($target->fresh()->salary_sacrifice)->toBeTrue();
});
