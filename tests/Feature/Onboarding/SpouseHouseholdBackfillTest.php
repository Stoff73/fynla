<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Live prod 2026-09-11: the model's capture_spouse_household_data call kept
 * the income and ISA but dropped the pot on one run and the £500-a-month
 * contributions on the next. The director backfills both from the user's
 * words through the same handler.
 */
it('backfills the pot and the yearly contributions the model dropped, through the same handler', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'onboarding_completed' => false]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director'])->fresh();
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'household_calculation_mode' => 'dual_earner', 'spouse_annual_income' => 65000, 'spouse_isa_balance' => 6700]);

    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'backfillSpouseHouseholdFromWords');
    $m->setAccessible(true);
    $details = $m->invoke($director, $user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD,
        '65000, an ISA with Halifax with 6700 in it, no contributions, and an Aviva pension with 75680 in it, she contributes 500 per month',
        ['spouse_annual_income' => 65000, 'spouse_isa_balance' => 6700]);

    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->firstOrFail();
    expect((float) $row->spouse_existing_pension_balance)->toBe(75680.0)
        ->and((float) $row->spouse_pension_input_annual)->toBe(6000.0)
        ->and((float) $row->spouse_annual_income)->toBe(65000.0)
        ->and($details['spouse_existing_pension_balance'])->toBe(75680.0)
        ->and($details['spouse_pension_input_annual'])->toBe(6000.0)
        ->and($row->spouse_isa_provider)->toBe('Halifax')
        ->and($row->spouse_pension_provider)->toBe('Aviva');
});

it('leaves the details alone on other states and when the model already captured both', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director'])->fresh();
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'backfillSpouseHouseholdFromWords');
    $m->setAccessible(true);

    $full = ['spouse_existing_pension_balance' => 1.0, 'spouse_pension_input_annual' => 2.0];
    expect($m->invoke($director, $user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD, 'pension with 75680 in it, contributes 500 per month', $full))->toBe($full)
        ->and($m->invoke($director, $user, $conversation, OnboardingStateMachine::STATE_BASE_WORK, 'pension with 75680 in it', []))->toBe([])
        ->and(TaxStrategyHouseholdInput::where('user_id', $user->id)->exists())->toBeFalse();
});
