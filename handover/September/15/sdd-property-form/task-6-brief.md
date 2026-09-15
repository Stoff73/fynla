### Task 6: The controller carries the flag and the form through send, stream and start

**Files:**
- Modify: `app/Http/Controllers/Api/AiChatController.php` — `sendMessage` (~201-280), the stream method (~430-450), and the onboarding start method (find it with `grep -n "function startOnboarding" app/Http/Controllers/Api/AiChatController.php`)
- Modify: `app/Services/AI/Loop/ConcurrentTurnQueue.php` — `enqueue` (line 73)
- Test: `tests/Feature/AI/SendAiChatMessageFormRequestTest.php` (append) and the last case from Task 2

**Interfaces:**
- Consumes: `OnboardingChatDirector::setClientSupportsForms`, `handleUserMessage(..., form: $form)`, `CaptureForms::summarise`
- Produces: header contract `X-Fynla-Forms: 1`; queued messages keep `metadata.form`

- [ ] **Step 1: Write the failing tests** (append)

```php
function formStepHttpUser(): User
{
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => false, 'onboarding_fyn_path' => 'campaign', 'onboarding_fyn_step' => \App\Services\Onboarding\OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY, 'onboarding_fyn_selection' => 'savetax', 'funnel_answers' => ['campaign' => 'savetax', 'assets' => ['property']]]);
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return $user;
}

it('streams the property form to a client declaring forms and the typed prompt to one that does not', function (): void {
    $user = formStepHttpUser();
    Sanctum::actingAs($user);
    \Tests\Support\Fyn\FynStreamHarness::fake()->bind();

    // The start endpoint re-emits the current step's turn for a resumed walk.
    $withForms = $this->withHeader('X-Fynla-Forms', '1')->postJson('/api/ai-chat/onboarding/start')->assertOk()->streamedContent();
    expect($withForms)->toContain('"type":"capture_form"');

    $without = $this->postJson('/api/ai-chat/onboarding/start')->assertOk()->streamedContent();
    expect($without)->not->toContain('"type":"capture_form"')
        ->and($without)->toContain('Now your property');
});

it('saves a posted form answer and records the plain-words line as the user message', function (): void {
    $user = formStepHttpUser();
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'director', 'title' => 'Onboarding']);
    Sanctum::actingAs($user);
    \Tests\Support\Fyn\FynStreamHarness::fake()->bind();

    $body = $this->withHeader('X-Fynla-Forms', '1')->postJson("/api/ai-chat/conversations/{$conversation->id}/messages", ['form' => ['name' => 'property', 'answers' => [
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
    ]]])->assertOk()->streamedContent();

    expect(\App\Models\Property::where('user_id', $user->id)->count())->toBe(1)
        ->and(\App\Models\AiMessage::where('conversation_id', $conversation->id)->where('role', 'user')->latest('id')->value('content'))->toBe('Home worth £750,000, mortgage £325,000, joint, my share 50%.')
        ->and($body)->toContain('"type":"onboarding_advance"');
});
```

If the start endpoint for a resumed campaign user emits the resume greeting (Continue / Something else) rather than the step, post `['message' => 'Continue']` to the conversation it created with the same header and assert on that stream instead; the assertion is on the presence or absence of `capture_form`.

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php`
Expected: the two new tests and Task 2's last test fail.

- [ ] **Step 3: Implement**

In `ConcurrentTurnQueue::enqueue`:

```php
    public function enqueue(AiConversation $conversation, string $content, array $metadata = []): ?AiMessage
    {
        if ($this->isFull($conversation)) {
            return null;
        }

        $attributes = ['role' => 'user', 'content' => $content, 'status' => AiMessageStatus::Queued];
        if ($metadata !== []) {
            $attributes['metadata'] = $metadata;
        }

        return $conversation->messages()->create($attributes);
    }
```

In `AiChatController::sendMessage`, replace

```php
        $message = $request->input('message');
        $currentRoute = $request->input('current_route');
```

with

```php
        $form = $request->input('form');
        $form = is_array($form) ? $form : null;
        // A form answer arrives with no typed text; the transcript line is
        // composed in ONE place (CaptureForms) so every surface reads the same.
        $message = $form !== null ? CaptureForms::summarise($form) : (string) $request->input('message');
        $currentRoute = $request->input('current_route');
        $this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));
```

Change the enqueue line to `$queued = $this->queue->enqueue($conversation, $message, $form !== null ? ['form' => $form] : []);` and the director call to `$this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute, true, $form)`.

In the stream method, after `$message = $queued->content;` add:

```php
        $queuedMetadata = is_array($queued->metadata) ? $queued->metadata : [];
        $form = is_array($queuedMetadata['form'] ?? null) ? $queuedMetadata['form'] : null;
        $this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));
```

and change its director call to `$this->onboardingDirector->handleUserMessage($user, $conversation, $message, $currentRoute, false, $form)`.

In the onboarding start method, before its director call add `$this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));`.

Add the helper at the bottom of the controller and the import `use App\Services\Onboarding\CaptureForms;`:

```php
    /** The web and /m bundles send `X-Fynla-Forms: 1`; native does not yet. */
    private function clientSupportsForms(Request $request): bool
    {
        return trim((string) $request->header('X-Fynla-Forms')) === '1';
    }
```

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php tests/Feature/Onboarding/StateMachineWalkthroughTest.php tests/Feature/AI`
Expected: all pass.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Http/Controllers/Api/AiChatController.php app/Services/AI/Loop/ConcurrentTurnQueue.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git add app/Http/Controllers/Api/AiChatController.php app/Services/AI/Loop/ConcurrentTurnQueue.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php
git commit -m "feat(ai-chat): the messages endpoint carries the forms capability and a form answer through send, queue and stream"
```

---

