<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\KycGateChecker;
use App\Services\AI\QueryClassifier;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('classifies on track for retirement as retirement readiness, not goals', function (): void {
    $result = app(QueryClassifier::class)->classify('Am I on track for retirement?');

    expect($result['primary'])->toBe(QuerySchemas::RETIREMENT_READINESS)
        ->and($result['related'])->not->toContain(QuerySchemas::GOALS_PROGRESS);
});

it('allows retirement advice without goals when retirement inputs exist', function (): void {
    $user = User::factory()->create([
        'date_of_birth' => '1985-01-01',
        'annual_employment_income' => 75000,
        'employment_status' => null,
        'monthly_expenditure' => null,
        'annual_expenditure' => null,
    ]);
    DCPension::factory()->for($user)->create();

    $result = app(KycGateChecker::class)->check($user, [
        'primary' => QuerySchemas::RETIREMENT_READINESS,
        'related' => [QuerySchemas::GOALS_PROGRESS],
        'modules' => ['retirement', 'goals'],
    ]);

    expect($result['passed'])->toBeTrue()
        ->and(strtolower($result['prompt_text']))->not->toContain('goals');
});

it('checks only the required module record for an account question', function (): void {
    $owner = User::factory()->create();
    $jointOwner = User::factory()->create([
        'date_of_birth' => null,
        'annual_employment_income' => 0,
        'monthly_expenditure' => null,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $owner->id,
        'joint_owner_id' => $jointOwner->id,
    ]);

    $result = app(KycGateChecker::class)->check($jointOwner, [
        'primary' => QuerySchemas::SAVINGS_ACCOUNTS,
        'related' => [QuerySchemas::AFFORDABILITY],
        'modules' => ['savings'],
    ]);

    expect($result['passed'])->toBeTrue();
});

it('never instructs an unavailable advice tool or exposes an internal route', function (): void {
    $user = User::factory()->create([
        'date_of_birth' => null,
        'annual_employment_income' => 75000,
    ]);
    DCPension::factory()->for($user)->create();

    $result = app(KycGateChecker::class)->check($user, [
        'primary' => QuerySchemas::RETIREMENT_READINESS,
        'related' => [],
        'modules' => ['retirement'],
    ]);

    expect($result['prompt_text'])
        ->not->toContain('navigate_to_page')
        ->toContain('Personal Details')
        ->not->toContain('/profile');
});

it('defines required data for every advice query type', function (): void {
    expect(array_keys(QuerySchemas::REQUIRED_DATA))->toBe(QuerySchemas::ADVICE_TYPES)
        ->and(QuerySchemas::REQUIRED_DATA[QuerySchemas::RETIREMENT_READINESS])->toBe([
            'date_of_birth',
            'income',
            'retirement',
        ])
        ->and(QuerySchemas::REQUIRED_DATA[QuerySchemas::GOALS_PROGRESS])->toBe(['goals']);
});
