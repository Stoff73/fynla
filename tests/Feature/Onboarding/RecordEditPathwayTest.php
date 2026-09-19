<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

/**
 * The record edit pathway (September/September19Updates/azTest-plan.md
 * Batch 4, CSJ 2026-09-19). One mechanism, three doors: the verify page's
 * "No, change something", a "change my …" after onboarding, and "Can I
 * change that answer?" mid-walk. One record opens its form with the values
 * filled in; several are offered as a choice; the submit is an update.
 *
 * Brett, 2026-09-18 (production conversation 896): "Mortgage is 300000" on
 * the property verify page failed, and so did every message after it.
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

function editUser(?string $step, array $context = [], array $extra = []): User
{
    $user = User::factory()->create(array_merge([
        'is_preview_user' => false,
        'onboarding_completed' => $step === null,
        'first_name' => 'Brett',
        'marital_status' => 'married',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => $step,
        'onboarding_fyn_selection' => 'savetax',
        'onboarding_fyn_context' => $context,
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['bank', 'property']],
    ], $extra));
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user;
}

function editConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding', 'metadata' => ['source' => 'fyn_onboarding']])->fresh();
}

function editDirector(): OnboardingChatDirector
{
    FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    return $director;
}

function homeWithMortgage(User $user, float $value = 800000, float $balance = 400000): Property
{
    $property = Property::factory()->create(['user_id' => $user->id, 'property_type' => 'main_residence', 'current_value' => $value, 'address_line_1' => 'Main residence']);
    Mortgage::factory()->create(['user_id' => $user->id, 'property_id' => $property->id, 'outstanding_balance' => $balance, 'lender_name' => 'To be completed']);

    return $property;
}

it('opens the one property in its form, values filled in, when the verify page is answered "No, change something"', function (): void {
    $user = editUser('campaign_verify_navigate', ['verify_section' => 'property']);
    $property = homeWithMortgage($user);
    $conversation = editConversation($user);

    $events = iterator_to_array(editDirector()->handleUserMessage($user, $conversation, 'No, change something'), false);

    $form = collect($events)->firstWhere('type', 'capture_form');
    expect($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_edit')
        ->and($form)->not->toBeNull()
        ->and($form['form']['edit'])->toBeTrue()
        ->and($form['form']['submit_label'])->toBe('Save changes')
        ->and($form['form']['kinds'])->toHaveCount(1)
        ->and($form['form']['kinds'][0]['key'])->toBe('main_residence')
        ->and($form['form']['kinds'][0]['fields'])->not->toContain('ownership_type')
        ->and($form['record'])->toBe(['type' => 'property', 'id' => $property->id])
        ->and($form['values']['main_residence']['current_value'])->toBe(800000.0)
        ->and($form['values']['main_residence']['mortgage_outstanding_balance'])->toBe(400000.0);
});

it("saves Brett's mortgage correction from the form, reads it back and returns to the verify page", function (): void {
    $user = editUser('campaign_verify_edit', ['verify_section' => 'property']);
    $property = homeWithMortgage($user);
    $conversation = editConversation($user);

    $form = ['name' => 'property', 'record' => ['type' => 'property', 'id' => $property->id], 'answers' => [
        'main_residence' => ['current_value' => 800000, 'mortgage_outstanding_balance' => 300000],
    ]];
    $events = iterator_to_array(editDirector()->handleUserMessage($user, $conversation, 'Home worth £800,000, mortgage £300,000.', null, true, $form), false);

    expect((float) Mortgage::where('property_id', $property->id)->value('outstanding_balance'))->toBe(300000.0)
        ->and(Property::where('user_id', $user->id)->count())->toBe(1)
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('Updated —')
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_navigate')
        ->and(collect($events)->firstWhere('type', 'quick_replies')['prompt_text'])->toContain('does it look right?');
});

it('leaves the edit state on "It all correct", "Continue" and "Yes, that\'s right" instead of failing each one', function (string $typed): void {
    $user = editUser('campaign_verify_edit', ['verify_section' => 'property']);
    homeWithMortgage($user);
    $conversation = editConversation($user);

    $events = iterator_to_array(editDirector()->handleUserMessage($user, $conversation, $typed), false);

    expect(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->not->toContain("wasn't able to apply")
        ->and($user->fresh()->onboarding_fyn_step)->not->toBe('campaign_verify_edit')
        ->and($user->fresh()->onboarding_fyn_step)->not->toBe('campaign_verify_navigate');
})->with(['It all correct', 'Continue', "Yes, that's right"]);

it('offers two savings accounts as a choice, opens the tapped one, and saves the new balance', function (): void {
    $user = editUser('campaign_verify_navigate', ['verify_section' => 'savings']);
    $hsbc = SavingsAccount::factory()->create(['user_id' => $user->id, 'account_name' => 'HSBC current account', 'institution' => 'HSBC', 'account_type' => 'current_account', 'current_balance' => 150]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'account_name' => 'Monzo current account', 'institution' => 'Monzo', 'account_type' => 'current_account', 'current_balance' => 250]);
    $conversation = editConversation($user);
    $director = editDirector();

    $events = iterator_to_array($director->handleUserMessage($user, $conversation, 'No, change something'), false);
    $choice = collect($events)->firstWhere('type', 'quick_replies');
    expect($choice['action_bubbles'])->toBeTrue()
        ->and(collect($choice['bubbles'])->pluck('label')->all())->toBe(['HSBC current account', 'Monzo current account'])
        ->and($choice['bubbles'][0]['id'])->toBe('edit:savings_account:'.$hsbc->id);

    $opened = iterator_to_array($director->handleAction($user->fresh(), $conversation, 'edit:savings_account:'.$hsbc->id), false);
    $form = collect($opened)->firstWhere('type', 'capture_form');
    expect($form['form']['kinds'][0]['key'])->toBe('current_account')
        ->and($form['values']['current_account']['provider'])->toBe('HSBC')
        ->and($form['values']['current_account']['current_value'])->toBe(150.0);

    $submit = ['name' => 'savings', 'record' => ['type' => 'savings_account', 'id' => $hsbc->id], 'answers' => [
        'current_account' => ['provider' => 'HSBC', 'current_value' => 1500],
    ]];
    iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'HSBC current account, balance £1,500.', null, true, $submit), false);

    expect((float) $hsbc->fresh()->current_balance)->toBe(1500.0)
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(2);
});

it('after onboarding, "change my savings balance" opens the account form and the form post updates it', function (): void {
    $user = editUser(null);
    $account = SavingsAccount::factory()->create(['user_id' => $user->id, 'account_name' => 'Halifax easy access savings', 'institution' => 'Halifax', 'account_type' => 'easy_access', 'current_balance' => 4000, 'interest_rate' => 3.5]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Chat']);
    Sanctum::actingAs($user);
    FynStreamHarness::fake()->bind();

    $body = $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['message' => 'Change my Halifax savings account balance to £5,000'])
        ->assertOk()->streamedContent();
    expect($body)->toContain('"type":"capture_form"')
        ->and($body)->toContain('"record":{"type":"savings_account","id":'.$account->id.'}');

    $body = $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => [
            'name' => 'savings', 'record' => ['type' => 'savings_account', 'id' => $account->id],
            'answers' => ['easy_access' => ['provider' => 'Halifax', 'current_value' => 5000, 'interest_rate' => 3.5]],
        ]])->assertOk()->streamedContent();

    expect($body)->toContain('Updated')
        ->and((float) $account->fresh()->current_balance)->toBe(5000.0)
        ->and(SavingsAccount::where('user_id', $user->id)->count())->toBe(1);
});

it('removes a record from its edit form', function (): void {
    $user = editUser(null);
    $account = SavingsAccount::factory()->create(['user_id' => $user->id, 'account_name' => 'Monzo current account', 'institution' => 'Monzo', 'account_type' => 'current_account', 'current_balance' => 250]);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Chat']);
    Sanctum::actingAs($user);
    FynStreamHarness::fake()->bind();

    $body = $this->withHeader('X-Fynla-Forms', '1')
        ->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => [
            'name' => 'savings', 'record' => ['type' => 'savings_account', 'id' => $account->id], 'delete' => true, 'answers' => [],
        ]])->assertOk()->streamedContent();

    expect($body)->toContain('Removed Monzo current account')
        ->and(SavingsAccount::where('id', $account->id)->exists())->toBeFalse();
});

it('"Can I change that answer?" mid-walk offers the sections saved so far, opens the chosen one, and returns to the step', function (): void {
    $user = editUser('campaign_spouse_invite');
    $account = SavingsAccount::factory()->create(['user_id' => $user->id, 'account_name' => 'HSBC current account', 'institution' => 'HSBC', 'account_type' => 'current_account', 'current_balance' => 150]);
    $conversation = editConversation($user);
    $director = editDirector();

    $events = iterator_to_array($director->handleUserMessage($user, $conversation, 'Can I change that answer?'), false);
    $choice = collect($events)->firstWhere('type', 'quick_replies');
    expect($choice['prompt_text'])->toContain('What would you like to change?')
        ->and($choice['action_bubbles'])->toBeTrue()
        ->and(collect($choice['bubbles'])->pluck('id')->all())->toContain('edit_section:savings')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->not->toContain('Shall I send them an invitation')
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_spouse_invite');

    $opened = iterator_to_array($director->handleAction($user->fresh(), $conversation, 'edit_section:savings'), false);
    $form = collect($opened)->firstWhere('type', 'capture_form');
    expect($form['record'])->toBe(['type' => 'savings_account', 'id' => $account->id])
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_edit');

    $submit = ['name' => 'savings', 'record' => ['type' => 'savings_account', 'id' => $account->id], 'answers' => [
        'current_account' => ['provider' => 'HSBC', 'current_value' => 175],
    ]];
    $after = iterator_to_array($director->handleUserMessage($user->fresh(), $conversation, 'HSBC current account, balance £175.', null, true, $submit), false);

    expect((float) $account->fresh()->current_balance)->toBe(175.0)
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_spouse_invite')
        ->and(collect($after)->firstWhere('type', 'onboarding_advance')['to_step'] ?? null)->toBe('campaign_spouse_invite');
});
