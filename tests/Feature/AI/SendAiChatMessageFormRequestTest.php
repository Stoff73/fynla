<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Property;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
    $this->conversation = AiConversation::create(['user_id' => $this->user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding']);
    Sanctum::actingAs($this->user);
});

function postForm($test, int $conversationId, array $body)
{
    return $test->postJson("/api/ai-chat/conversations/{$conversationId}/messages", $body);
}

it('rejects an unknown form name', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'bank', 'answers' => []]])
        ->assertStatus(422)->assertJsonValidationErrors(['form.name']);
});

it('rejects a kind the schema does not have and a bad ownership value', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'castle' => ['current_value' => 1],
        'main_residence' => ['current_value' => 1, 'mortgage_outstanding_balance' => null, 'ownership_type' => 'shared'],
    ]]])->assertStatus(422)->assertJsonValidationErrors(['form.answers.castle', 'form.answers.main_residence.ownership_type']);
});

it('requires the mortgage key to be present even when null, and a value for a filled kind', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'buy_to_let' => ['ownership_type' => 'individual', 'monthly_rental_income' => 900],
    ]]])->assertStatus(422)->assertJsonValidationErrors(['form.answers.buy_to_let.current_value', 'form.answers.buy_to_let.mortgage_outstanding_balance']);
});

it('still requires a message when there is no form', function (): void {
    postForm($this, $this->conversation->id, [])->assertStatus(422)->assertJsonValidationErrors(['message']);
});

it('accepts a well-formed property answer with no message', function (): void {
    // Past validation the controller streams; a 200 with a streamed body is
    // the proof the request class let it through (the director's own
    // behaviour is Task 5's test).
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
    ]]])->assertOk();
});

it('validates only the kinds that were submitted', function (): void {
    postForm($this, $this->conversation->id, ['form' => ['name' => 'property', 'answers' => [
        'buy_to_let' => ['current_value' => 200000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 850, 'ownership_type' => 'individual'],
    ]]])->assertOk();
});

function formStepHttpUser(): User
{
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_step' => OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, 'onboarding_fyn_selection' => 'savetax', 'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property']]]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user;
}

it('streams the property form to a client declaring forms and the typed prompt to one that does not', function (): void {
    // The start endpoint for an ALREADY-mid-flow user only ever emits a bare
    // `resume` event (see AiChatController::startOnboarding — it never calls
    // the director there), so a resumed-walk /start cannot exercise the
    // capture-form turn. Instead this drives a FRESH funnel entry straight at
    // the property step (config override on the savetax entry state) so
    // /start's other director call — emitFirstTurn — is the one under test.
    config(['onboarding.campaign_map.savetax.entry' => OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY]);
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property']]]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);
    Sanctum::actingAs($user);
    FynStreamHarness::fake()->bind();

    $withForms = $this->withHeader('X-Fynla-Forms', '1')->postJson('/api/ai-chat/onboarding/start')->assertOk()->streamedContent();
    expect($withForms)->toContain('"type":"capture_form"');

    // Reset so the second call takes the same fresh-entry funnel path rather
    // than falling into the mid-flow resume branch the first call left behind.
    // withHeader() sticks its header onto every subsequent request in the
    // test (Laravel's $defaultHeaders), so it must be flushed here or the
    // "without" call would still carry X-Fynla-Forms from the first one.
    $user->update(['onboarding_fyn_step' => null, 'onboarding_fyn_path' => null, 'onboarding_fyn_selection' => null]);
    $this->flushHeaders();

    $without = $this->postJson('/api/ai-chat/onboarding/start')->assertOk()->streamedContent();
    expect($without)->not->toContain('"type":"capture_form"')
        ->and($without)->toContain('Now your property');
});

it('saves a posted form answer and records the plain-words line as the user message', function (): void {
    $user = formStepHttpUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding']);
    Sanctum::actingAs($user);
    FynStreamHarness::fake()->bind();

    $body = $this->withHeader('X-Fynla-Forms', '1')->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => ['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
    ]]])->assertOk()->streamedContent();

    expect(Property::where('user_id', $user->id)->count())->toBe(1)
        ->and(AiMessage::where('conversation_id', $conversation->id)->where('role', 'user')->latest('id')->value('content'))->toBe('Home worth £750,000, mortgage £325,000, joint, my share 50%.')
        ->and($body)->toContain('"type":"onboarding_advance"');
});
