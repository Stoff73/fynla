<?php

declare(strict_types=1);

use App\Models\Estate\Liability;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Onboarding\OnboardingService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * The setup wizard (/onboarding/full) divided the rate the user typed by 100
 * before storing it, into columns that hold percentages (SavingsInterestRate;
 * liability readers band at 5 and 15). A 4% savings account read as 0.04%.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
});

it('stores a savings rate entered in the wizard as the percentage typed', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'life_stage' => 'estate']);

    app(OnboardingService::class)->saveStepProgress($user->id, 'assets', [
        'cash' => [[
            'institution' => 'Nationwide',
            'account_type' => 'savings_account',
            'current_balance' => 4000,
            'interest_rate' => 4,
            'ownership_type' => 'individual',
        ]],
    ]);

    $account = SavingsAccount::query()->where('user_id', $user->id)->sole();
    expect((float) $account->interest_rate)->toBe(4.0)
        ->and((float) $account->annual_interest_projected_gbp)->toBe(160.0);
});

it('stores a loan rate entered in the wizard as the percentage typed', function (): void {
    $user = User::factory()->create(['tier' => 'free', 'life_stage' => 'estate']);

    app(OnboardingService::class)->saveStepProgress($user->id, 'liabilities', [
        'liabilities' => [[
            'type' => 'personal_loan',
            'lender' => 'Onboarding Bank',
            'outstanding_balance' => 7500,
            'monthly_payment' => 250,
            'interest_rate' => 27,
        ]],
    ]);

    expect((float) Liability::query()->where('user_id', $user->id)->sole()->interest_rate)->toBe(27.0);
});
