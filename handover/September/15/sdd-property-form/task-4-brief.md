### Task 4: The director emits the form turn (and the typed prompt for clients without forms)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — `emitTurnForState` (the `if ($turnType === 'bubbles')` branch at ~line 1002), a new property + setter near the class top, and the `handleUserMessage` dispatch at ~line 382
- Test: `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` (create)

**Interfaces:**
- Produces:
  - `OnboardingChatDirector::setClientSupportsForms(bool $supports): void` — the controller calls it per request (Task 6)
  - SSE event `['type' => 'capture_form', 'prompt_text' => string, 'form' => <schema array>]`
  - Persisted assistant message: content = prompt text, `metadata.capture_form` = the schema array, `metadata.onboarding_step`, `metadata.turn_intent = 'step_prompt'`
  - A typed message at a `form` state goes to `handleAssetCaptureTurn` (the delegated path) unchanged.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Property;
use App\Models\User;
use App\Services\Onboarding\CaptureForms;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

afterEach(function (): void {
    Mockery::close();
});

function formStepUser(string $step = OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY): User
{
    return User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'first_name' => 'Chris',
        'marital_status' => 'married',
        'onboarding_fyn_path' => 'campaign',
        'onboarding_fyn_step' => $step,
        'onboarding_fyn_selection' => 'savetax',
        'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property', 'investments']],
    ]);
}

function formConversation(User $user): AiConversation
{
    return AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding'])->fresh();
}

it('emits the property form to a client that can render forms and persists the schema for history', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(true);

    $events = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)), false);

    $form = collect($events)->firstWhere('type', 'capture_form');
    expect($form)->not->toBeNull()
        ->and($form['form'])->toBe(CaptureForms::schema('property'))
        ->and($form['prompt_text'])->toContain('Now your property')
        ->and(collect($events)->where('type', 'content'))->toHaveCount(0)
        ->and(collect($events)->last()['type'])->toBe('done');

    $saved = AiMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->latest('id')->first();
    expect($saved->metadata['capture_form'])->toBe(CaptureForms::schema('property'))
        ->and($saved->metadata['onboarding_step'])->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)
        ->and($saved->metadata['turn_intent'])->toBe('step_prompt');
});

it('emits the typed prompt instead when the client has not declared forms — native stays as it was', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    $director = app(OnboardingChatDirector::class);
    $director->setClientSupportsForms(false);

    $events = iterator_to_array($director->emitTurnForState($user, $conversation, OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, OnboardingStateMachine::getState(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY)), false);

    expect(collect($events)->firstWhere('type', 'capture_form'))->toBeNull()
        ->and(collect($events)->firstWhere('type', 'content')['text'])->toContain('Now your property');
});

it('sends a typed sentence at the form step down the existing capture path', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()
        ->toolTurn('create_property', ['property_type' => 'buy_to_let', 'current_value' => 450000, 'has_mortgage' => true, 'mortgage_outstanding_balance' => 100000, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'], 'toolu_btl')
        ->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'A buy to let worth 450000, mortgage 100000, rent 1000 a month, mine'), false);

    expect(Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->exists())->toBeTrue();
});
```

`emitTurnForState` is private today; make it `public` in Step 3 (it is the seam every turn goes through, and the test drives it directly so no model is needed to reach the property step).

- [ ] **Step 2: Run it to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
Expected: FAIL — `setClientSupportsForms` undefined / `emitTurnForState` not public.

- [ ] **Step 3: Implement**

Near the top of the class (after the constructor's promoted properties) add:

```php
    /**
     * Whether the client behind this request renders capture forms
     * (`X-Fynla-Forms: 1`, sent by the web and /m bundles). Native does not
     * yet, and keeps the typed prompt for a form turn. Set per request by
     * AiChatController; never read from headers here.
     */
    private bool $clientSupportsForms = false;

    public function setClientSupportsForms(bool $supports): void
    {
        $this->clientSupportsForms = $supports;
    }
```

Change `private function emitTurnForState(` to `public function emitTurnForState(`.

In `emitTurnForState`, immediately before `if ($turnType === 'bubbles') {` insert:

```php
        if ($turnType === 'form' && $this->clientSupportsForms) {
            $schema = CaptureForms::schema((string) ($state['form'] ?? ''));
            if ($schema !== null) {
                yield [
                    'type' => 'capture_form',
                    'prompt_text' => $promptText,
                    'form' => $schema,
                ];
                $assistantMessage = $this->saveMessage($conversation, 'assistant', $promptText, [
                    'metadata' => [
                        'capture_form' => $schema,
                        'onboarding_step' => $stateId,
                        'turn_intent' => $turnIntent->value,
                    ],
                ]);
                yield ['type' => 'done', 'message_id' => $assistantMessage->id];

                return;
            }
        }
```

(A `form` turn for a client without forms, or with an unknown schema, falls through to the existing `else` branch and gets the plain text prompt — no other change.)

In `handleUserMessage`, change the delegated dispatch at ~line 382 from

```php
        if (($state['turn_type'] ?? '') === 'delegated') {
```

to

```php
        // A form turn answered with typed text takes the same delegated
        // capture path as before the form existed (the extractor and the
        // gate are the fallback, untouched).
        if (in_array($state['turn_type'] ?? '', ['delegated', 'form'], true)) {
```

`CaptureForms` is in the same namespace as the director, so no import is needed.

- [ ] **Step 4: Run the test**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
Expected: 3 passed.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git commit -m "feat(onboarding): the director emits a capture_form turn to clients that render forms"
```

---

