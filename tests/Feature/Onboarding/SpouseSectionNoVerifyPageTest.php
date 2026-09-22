<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * CSJ 2026-09-16: on the Save Tax walk the spouse section no longer visits
 * a details page. Fyn repeats back what was saved in chat and moves on.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

afterEach(function (): void {
    Mockery::close();
});

function spouseStepUser(string $step, string $mode): User
{
    return User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'marital_status' => 'married',
        'household_calculation_mode' => $mode,
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => $step,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['isa']],
    ]);
}

it('repeats a working spouse\'s figures back and moves on without a details page', function (): void {
    $user = spouseStepUser(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD, 'dual_earner');
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
    FynStreamHarness::fake()
        ->toolTurn('capture_spouse_household_data', ['spouse_annual_income' => 45000, 'spouse_isa_balance' => 12000, 'spouse_pension_input_annual' => 3000], 'toolu_sp')
        ->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, 'She earns 45000, has 12000 in an ISA and pays 3000 a year into her pension'
    ), false);

    $text = collect($events)->where('type', 'content')->pluck('text')->implode(' ');
    expect($text)->toContain('your spouse earns £45,000 a year, has £12,000 in ISAs and pays £3,000 a year into their pension.')
        ->not->toContain('take you to')
        ->and(collect($events)->firstWhere('type', 'navigate'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->not->toBe('campaign_verify_navigate')
        ->and($user->fresh()->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD);
});

it('tells a single-earner couple what the spouse holds in their own name, or that they hold nothing', function (): void {
    $user = spouseStepUser(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS, 'single_earner_couple');
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
    FynStreamHarness::fake()
        ->toolTurn('capture_spouse_non_working_assets', ['spouse_existing_savings_balance' => 8000, 'spouse_existing_isa_balance' => 0, 'spouse_existing_investment_balance' => 0], 'toolu_sa')
        ->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, 'About 8000 in savings, nothing else'
    ), false);

    expect(collect($events)->where('type', 'content')->pluck('text')->implode(' '))
        ->toContain('your spouse has £8,000 in savings in their own name.')
        ->and($user->fresh()->onboarding_fyn_step)->not->toBe('campaign_verify_navigate');
});

// CSJ 2026-09-22: the form asks what the spouse receives and pays in, so the
// acknowledgement repeats both rather than only the balances.
it('repeats a non-working spouse\'s dividends and non-earner contribution back', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $user = spouseStepUser(OnboardingStateMachine::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS, 'single_earner_couple');
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
    FynStreamHarness::fake()
        ->toolTurn('capture_spouse_non_working_assets', ['spouse_existing_investment_balance' => 50000, 'spouse_annual_dividends' => 1500, 'spouse_pays_non_earner_maximum' => true], 'toolu_sb')
        ->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, '50k invested paying 1500 in dividends, and they pay the max into a pension'
    ), false);

    $net = number_format(CaptureForms::nonEarnerNetContribution());
    expect(collect($events)->where('type', 'content')->pluck('text')->implode(' '))
        ->toContain('your spouse has £50,000 in investments in their own name, and receives £1,500 a year in dividends and pays £'.$net.' a year into their pension.');
});
