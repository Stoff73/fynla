<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\SavingsActionDefinition;
use App\Models\User;
use App\Services\Savings\SavingsActionDefinitionService;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Regression walk 2026-10-09, R10: the onboarding Cash ISA rate is optional;
// left blank it was stored as 0 (NOT NULL DEFAULT 0.0000) and shown as 0.00%.

function callPrivate(object $target, string $method, array $args): mixed
{
    $ref = new ReflectionMethod($target, $method);

    return $ref->invokeArgs($target, $args);
}

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
});

it('stores a rate the user never gave as not recorded, not 0', function () {
    $user = User::factory()->create();

    $account = app(SavingsStore::class)->create([
        'institution' => 'Nationwide',
        'account_type' => 'cash_isa',
        'is_isa' => true,
        'current_balance' => 12000,
    ], $user, IngestSource::FYN_AI);

    expect($account->fresh()->interest_rate)->toBeNull();
});

it('raises no "earning 0%" card for a rate that was never given, but still does for a stored 0', function () {
    $user = User::factory()->create();
    $unknown = SavingsAccount::factory()->create(['user_id' => $user->id, 'interest_rate' => null, 'current_balance' => 12000, 'account_type' => 'easy_access', 'is_isa' => false]);
    $zero = SavingsAccount::factory()->create(['user_id' => $user->id, 'interest_rate' => 0, 'current_balance' => 5000, 'account_type' => 'easy_access', 'is_isa' => false]);

    $definition = SavingsActionDefinition::where('key', 'zero_rate_account')->firstOrFail();
    $results = callPrivate(app(SavingsActionDefinitionService::class), 'evaluateZeroRateAccount', [
        $definition, [], collect([$unknown->fresh(), $zero->fresh()]), 50,
    ]);

    expect(collect($results)->pluck('account_id')->all())->toBe([$zero->id]);
});

it('describes a stored rate as the percentage it is, and a missing one as not recorded', function () {
    $user = User::factory()->create();
    $known = SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Santander', 'interest_rate' => 4.25, 'current_balance' => 18000]);
    $unknown = SavingsAccount::factory()->create(['user_id' => $user->id, 'institution' => 'Nationwide', 'interest_rate' => null, 'current_balance' => 12000]);

    $service = app(SavingsActionDefinitionService::class);

    // It read "425.00%" (a percentage multiplied by 100 again).
    expect(callPrivate($service, 'formatAccountDescription', [$known->fresh()]))->toContain('£18,000, 4.25%')
        ->and(callPrivate($service, 'formatAccountDescription', [$unknown->fresh()]))->toContain('£12,000, rate not recorded');
});
