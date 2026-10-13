<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Mobile\NextActionsService;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * T23 (CSJ 2026-10-12, plan section 6b): "If the user does not enter through
 * save tax campaign, at the end of onboarding we do not show a save tax
 * report, as this is only for the save tax campaign. We do need to show
 * actions, for allowances etc … just not the plan." A Save Tax arrival is
 * recorded at registration (funnel_answers.campaign = 'savetax',
 * AuthController::register); the chat used to stamp it on everyone.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

function t23Saver(array $funnel): User
{
    $user = User::factory()->create([
        'first_name' => 'Priya', 'date_of_birth' => '1982-02-19', 'marital_status' => 'single',
        'employment_status' => 'full_time', 'annual_employment_income' => 110000, 'monthly_expenditure' => 3000,
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax', 'funnel_answers' => $funnel,
    ]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'is_isa' => false, 'current_balance' => 81000, 'interest_rate' => 3.25]);

    return $user->fresh();
}

function t23Synthesis(User $user): ?string
{
    $director = app(OnboardingChatDirector::class);
    $ref = new ReflectionMethod($director, 'buildSectionAdvice');

    return $ref->invoke($director, $user, 'synthesis');
}

it('tells a Save Tax arrival from a normal registrant by the registration record alone', function (): void {
    expect(OnboardingStateMachine::isSaveTaxArrival(t23Saver(['campaign' => 'savetax', 'income' => '100001_125140'])))->toBeTrue()
        ->and(OnboardingStateMachine::isSaveTaxArrival(t23Saver(['employment' => 'full-time'])))->toBeFalse()
        ->and(OnboardingStateMachine::isSaveTaxArrival(t23Saver([])))->toBeFalse()
        // A row from before the campaign stamp carries the /savetax page's income answer.
        ->and(OnboardingStateMachine::isSaveTaxArrival(t23Saver(['employment' => 'full-time', 'income' => '50271_100000'])))->toBeTrue();
});

it('ends a normal registrant on their top actions, the dashboard\'s own, with no tax plan', function (): void {
    $user = t23Saver(['employment' => 'full-time', 'assets' => ['savings']]);

    $text = (string) t23Synthesis($user);
    $actions = app(NextActionsService::class)->build($user->id);

    expect($actions)->not->toBeEmpty()
        ->and($text)->toStartWith("Here are your top actions, built from what you told me — in the order I'd tackle them:")
        ->and($text)->not->toContain('tax plan')
        ->and($text)->not->toContain('Together these')
        ->and($text)->toContain('qualified financial adviser');
    foreach ($actions as $action) {
        expect($text)->toContain('- '.$action['title']);
    }

    expect(OnboardingStateMachine::nextFromCampaignSynthesis('', $user))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_ACTIONS_TERMINAL);
    $terminal = OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_ACTIONS_TERMINAL);
    expect($terminal['navigate_to'])->toBe('/actions')
        ->and(OnboardingStateMachine::resolvePromptText($terminal, $user))->toBe('Your actions are ready, Priya. Open any action on the next screen to see the steps, or ask me about it here.');

    $bubble = (new ReflectionMethod(OnboardingChatDirector::class, 'terminalNavigationBubble'))->invoke(app(OnboardingChatDirector::class), '/actions');
    expect($bubble)->toBe(['id' => 'view_actions', 'label' => 'Take me to my actions', 'route' => '/actions']);
});

it('keeps the tax plan and the tax strategy screen for a Save Tax arrival', function (): void {
    $user = t23Saver(['campaign' => 'savetax', 'income' => '100001_125140']);

    expect((string) t23Synthesis($user))->toStartWith("Here's your tax plan, built from what you told me")
        ->and(OnboardingStateMachine::nextFromCampaignSynthesis('', $user))->toBe(OnboardingStateMachine::STATE_CAMPAIGN_TERMINAL);
});

it('says actions, not plan, when it offers a normal registrant the partner invite', function (): void {
    $arrival = t23Saver(['campaign' => 'savetax', 'income' => '100001_125140']);
    $normal = t23Saver(['employment' => 'full-time']);

    expect(OnboardingStateMachine::buildCampaignSpouseInvitePrompt('', $arrival))->toContain('Your plan uses the figures')
        ->and(OnboardingStateMachine::buildCampaignSpouseInvitePrompt('', $normal))
        ->toBe('One more thing. Your actions use the figures you gave me for your spouse. If they have their own Fynla account and you link up, I can use their real allowances and tax position, and your actions get more accurate. **Shall I send them an invitation?**');
});

it('no longer records a chat answer as a Save Tax arrival', function (): void {
    $user = User::factory()->create([
        'is_preview_user' => false, 'onboarding_completed' => false, 'first_name' => 'Sam', 'funnel_answers' => null,
        'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT,
    ]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding']);

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'Full-time'), false);

    expect($user->fresh()->funnel_answers)->toBe(['employment' => 'full-time']);
});
