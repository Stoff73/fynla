<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\TaxConfigService;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * T78 (csjones walk, Robin 509, 2026-10-14): a Free user whose two Junior ISAs
 * fill the ISA-and-investments limit (every ISA counts with investments, CSJ
 * 2026-09-15) typed a third. The savings form opened, the limit unsaid, because
 * the check read the form's name (bank and savings accounts); and the save
 * failed with "limit of 2 savings accounts", the wrong limit.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->seed(TierConfigurationSeeder::class);
    TaxConfiguration::factory()->create(['is_active' => true]);
    app()->forgetInstance(TaxConfigService::class);
    $this->user = User::factory()->create(['tier' => 'free', 'is_preview_user' => false]);
    foreach (['Fidelity', 'Hargreaves Lansdown'] as $provider) {
        SavingsAccount::factory()->create([
            'user_id' => $this->user->id, 'institution' => $provider, 'account_type' => 'junior_isa',
            'is_isa' => true, 'current_balance' => 1000, 'beneficiary_name' => 'Leo',
        ]);
    }
});

it('checks a Junior ISA typed into the savings form against the ISA limit', function () {
    expect(OnboardingStateMachine::freeLimitForForm($this->user, CaptureForms::SAVINGS, 'junior_isa'))
        ->toBe(['limit' => 2, 'noun' => 'ISAs and investment accounts']);
});

it('keeps a bank account on the bank and savings limit', function () {
    expect(OnboardingStateMachine::freeLimitForForm($this->user, CaptureForms::SAVINGS, 'easy_access'))->toBeNull();
});

it('names the ISA limit when a Junior ISA save is refused', function () {
    $result = $this->executeCaptureToolWithEvidence('create_savings_account', [
        'account_name' => 'Vanguard Junior ISA for Leo',
        'account_type' => 'junior_isa',
        'is_isa' => true,
        'institution' => 'Vanguard',
        'current_balance' => 800,
        'ownership_type' => 'individual',
        'beneficiary_name' => 'Leo',
    ], $this->user);

    expect($result['error_type'])->toBe('tier_limit_reached')
        ->and($result['message'])->toBe("You've reached your plan's limit of 2 ISAs and investment accounts. To add more, upgrade your plan.");
});
