### Task 5: The director saves a form answer through the property handler

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` — `handleUserMessage` signature and head; new `handleFormTurn`; new `advanceAfterCapture` extracted from `handleAssetCaptureTurn` (the block from `$this->recordProgress(` after the `capture_complete` yield through the next-state emission that follows `onboarding_advance`, ~lines 4155-4200)
- Test: `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` (append)

**Interfaces:**
- Consumes: `CaptureForms::toolInputs`, `CaptureForms::kindLabel`, `CoordinatingAgent::executeTool(..., confirmedFacts: [...])`
- Produces:
  - `handleUserMessage(User $user, AiConversation $conversation, string $message, ?string $currentRoute = null, bool $persistUserMessage = true, ?array $form = null)`
  - Events on success: per kind `tool_use running`, `entity_created`, `tool_use complete`; then `capture_complete`, `onboarding_advance`, the next turn, `done`
  - Events on any failure: `['type' => 'capture_form_errors', 'form' => 'property', 'errors' => ['<kind>' => ['message' => string, 'fields' => array<string,string>]]]`, one `content` line, `done`; the step stays parked; landed kinds stay saved
  - A `form` answer on a state that is not this form: one `content` line "That form is no longer open — let's carry on from where we are." then the current turn re-emitted.

- [ ] **Step 1: Write the failing tests** (append to `PropertyCaptureFormTurnTest.php`)

```php
function propertyAnswers(array $overrides = []): array
{
    return ['name' => 'property', 'answers' => array_replace_recursive([
        'main_residence' => ['current_value' => 750000, 'mortgage_outstanding_balance' => 325000, 'ownership_type' => 'joint', 'ownership_percentage' => 50],
        'buy_to_let' => ['current_value' => 450000, 'mortgage_outstanding_balance' => null, 'monthly_rental_income' => 1000, 'ownership_type' => 'individual'],
    ], $overrides)];
}

it('saves both kinds from a form answer with no model call and advances to verify', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind(); // no turns queued: any model call fails the test

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()
    ), false);

    $home = Property::where('user_id', $user->id)->where('property_type', 'main_residence')->first();
    $btl = Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->first();
    expect($home)->not->toBeNull()
        ->and((float) $home->current_value)->toBe(750000.0)
        ->and((float) $home->outstanding_mortgage)->toBe(325000.0)
        ->and($home->ownership_type)->toBe('joint')
        ->and((float) $home->ownership_percentage)->toBe(50.0)
        ->and($home->tenure_type)->toBe('freehold')
        ->and($btl)->not->toBeNull()
        ->and((float) $btl->current_value)->toBe(450000.0)
        ->and((float) $btl->outstanding_mortgage)->toBe(0.0)
        ->and((float) $btl->monthly_rental_income)->toBe(1000.0)
        ->and($btl->ownership_type)->toBe('individual')
        ->and(collect($events)->where('type', 'entity_created'))->toHaveCount(2)
        ->and(collect($events)->first()['type'])->toBe('form_received')
        ->and(collect($events)->first()['text'])->toBe(CaptureForms::summarise(propertyAnswers()))
        ->and(collect($events)->firstWhere('type', 'capture_complete'))->not->toBeNull()
        ->and(collect($events)->firstWhere('type', 'capture_form_errors'))->toBeNull()
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce');

    $userRow = AiMessage::where('conversation_id', $conversation->id)->where('role', 'user')->latest('id')->first();
    expect($userRow->content)->toContain('Home worth £750,000')
        ->and($userRow->metadata['form'])->toBe(propertyAnswers());
});

it('saves one kind alone', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();
    $form = ['name' => 'property', 'answers' => ['buy_to_let' => ['current_value' => 200000, 'mortgage_outstanding_balance' => 50000, 'monthly_rental_income' => 850, 'ownership_type' => 'tenants_in_common', 'ownership_percentage' => 60]]];

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise($form), null, true, $form), false);

    $btl = Property::where('user_id', $user->id)->first();
    expect(Property::where('user_id', $user->id)->count())->toBe(1)
        ->and($btl->ownership_type)->toBe('tenants_in_common')
        ->and((float) $btl->ownership_percentage)->toBe(60.0)
        ->and($user->fresh()->onboarding_fyn_step)->toBe('campaign_verify_announce');
});

it('reports a refused kind on the form, keeps the landed one, and stays on the step', function (): void {
    // Free holds two properties; a third is refused by the tier cap.
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();
    Property::create(['user_id' => $user->id, 'property_type' => 'secondary_residence', 'current_value' => 100000, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'address_line_1' => 'Second home', 'city' => 'Unknown', 'postcode' => 'N/A']);

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage(
        $user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()
    ), false);

    $errors = collect($events)->firstWhere('type', 'capture_form_errors');
    expect(Property::where('user_id', $user->id)->where('property_type', 'main_residence')->exists())->toBeTrue()
        ->and(Property::where('user_id', $user->id)->where('property_type', 'buy_to_let')->exists())->toBeFalse()
        ->and($errors)->not->toBeNull()
        ->and($errors['errors'])->toHaveKey('buy_to_let')
        ->and($errors['errors']['buy_to_let']['message'])->toContain('property limit')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('Buy to let')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_PROPERTY);
});

it('never invokes the model for a form answer', function (): void {
    $user = formStepUser();
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, CaptureForms::summarise(propertyAnswers()), null, true, propertyAnswers()), false);

    expect(AiMessage::where('conversation_id', $conversation->id)->where('role', 'assistant')->whereNotNull('tool_calls')->exists())->toBeFalse();
});

it('tells the user a stale form is no longer open and re-emits the current step', function (): void {
    $user = formStepUser(OnboardingStateMachine::STATE_CAMPAIGN_DOB);
    $conversation = formConversation($user);
    FynStreamHarness::fake()->bind();

    $events = iterator_to_array(app(OnboardingChatDirector::class)->handleUserMessage($user, $conversation, 'Home worth £1', null, true, propertyAnswers()), false);

    expect(Property::where('user_id', $user->id)->exists())->toBeFalse()
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('no longer open')
        ->and(collect($events)->where('type', 'content')->pluck('text')->implode(' '))->toContain('date of birth')
        ->and($user->fresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_DOB);
});
```

If `Property::create` needs further NOT NULL columns, read `database/migrations` for `properties` and add them; do not pass null for a defaulted column.

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
Expected: the five new tests fail (`handleUserMessage` accepts 5 arguments).

- [ ] **Step 3: Implement**

Change the signature:

```php
    public function handleUserMessage(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute = null,
        bool $persistUserMessage = true,
        ?array $form = null
    ): \Generator {
        if ($persistUserMessage) {
            $this->saveMessage($conversation, 'user', $message, $form !== null ? ['metadata' => ['form' => $form]] : []);
        }
```

Immediately after the `$state === null` guard (the `Unknown onboarding step` return) insert:

```php
        if ($form !== null) {
            if (($state['turn_type'] ?? '') !== 'form' || ($state['form'] ?? null) !== ($form['name'] ?? null)) {
                // A form submitted after the step moved on (a second tab, a
                // late tap). Nothing is written; the walk carries on.
                $line = "That form is no longer open — let's carry on from where we are.";
                yield ['type' => 'content', 'text' => $line];
                $this->saveMessage($conversation, 'assistant', $line, ['metadata' => ['onboarding_step' => $currentStateId, 'turn_intent' => FynTurnIntent::CaptureClarification->value]]);
                yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state, includeTransitionHeader: false);

                return;
            }

            yield from $this->handleFormTurn($user, $conversation, $message, $currentStateId, $state, $form);

            return;
        }
```

Add the new method next to `handleAssetCaptureTurn`:

```php
    /**
     * A structured capture-form answer (CaptureForms). Every filled kind is
     * one create call through the same handler, gate, tier cap, spouse
     * memory and audit trail as a typed capture — with the user's answers
     * handed to the gate as confirmed facts. No model, no extractor.
     *
     * @param  array{name: string, answers: array<string, array<string, mixed>>}  $form
     */
    private function handleFormTurn(
        User $user,
        AiConversation $conversation,
        string $message,
        string $currentStateId,
        array $state,
        array $form
    ): \Generator {
        $recordsCreated = [];
        $errors = [];

        // The clients post the form with no typed text and show a placeholder
        // user row; this is the transcript line (composed once, in
        // CaptureForms) they replace it with.
        yield ['type' => 'form_received', 'text' => $message];

        foreach (CaptureForms::toolInputs($form) as $kind => $input) {
            yield ['type' => 'tool_use', 'tool' => 'create_property', 'status' => 'running'];
            $facts = ['ownership_type' => $input['ownership_type']];
            if (isset($input['ownership_percentage'])) {
                $facts['ownership_percentage'] = $input['ownership_percentage'];
            }
            try {
                $result = $this->coordinatingAgent->executeTool('create_property', $input, $user, $conversation->id, confirmedFacts: $facts);
            } catch (\Throwable $e) {
                Log::error('[OnboardingChatDirector] Form capture write failed', ['user_id' => $user->id, 'kind' => $kind, 'error' => $e->getMessage()]);
                $result = ['error' => true, 'message' => 'Unable to save the record. Please try again.'];
            }
            yield ['type' => 'tool_use', 'tool' => 'create_property', 'status' => 'complete'];

            if (($result['success'] ?? false) === true && isset($result['entity_id'])) {
                $row = ['type' => 'entity_created', 'entity_type' => 'property', 'entity_id' => $result['entity_id'], 'name' => CaptureForms::kindLabel($form['name'], $kind)];
                $recordsCreated[] = self::recordRowFromEvent($row);
                yield $row;

                continue;
            }

            $errors[$kind] = [
                'message' => (string) ($result['message'] ?? 'The write failed.'),
                'fields' => is_array($result['errors'] ?? null)
                    ? array_map(static fn ($m): string => is_array($m) ? (string) ($m[0] ?? '') : (string) $m, $result['errors'])
                    : [],
            ];
        }

        if ($errors !== []) {
            yield ['type' => 'capture_form_errors', 'form' => $form['name'], 'errors' => $errors];
            $lines = [];
            foreach ($errors as $kind => $error) {
                $lines[] = CaptureForms::kindLabel($form['name'], $kind).': '.rtrim($error['message'], '.').'.';
            }
            $text = ($recordsCreated !== [] ? rtrim($this->buildCaptureCompleteSummary($recordsCreated), '. ').'. ' : '')
                ."I couldn't save ".implode(' ', $lines);
            yield ['type' => 'content', 'text' => $text];
            $saved = $this->saveMessage($conversation, 'assistant', $text, ['metadata' => [
                'onboarding_step' => $currentStateId,
                'capture_write_failed' => true,
                'turn_intent' => FynTurnIntent::CaptureClarification->value,
            ]]);
            $this->recordProgress($user, $currentStateId, ['selection' => 'savetax', 'raw_message' => mb_substr($message, 0, 500)]);
            yield ['type' => 'done', 'message_id' => $saved->id];

            return;
        }

        yield [
            'type' => 'capture_complete',
            'summary' => $this->buildCaptureCompleteSummary($recordsCreated),
            'records_created' => $recordsCreated,
        ];
        yield from $this->advanceAfterCapture($user, $conversation, $currentStateId, $message, 'savetax');
    }
```

Extract the advance block. In `handleAssetCaptureTurn`, everything after the `capture_complete` yield — from `$this->recordProgress(` through the end of the method — moves verbatim into a new method and is replaced by:

```php
        yield from $this->advanceAfterCapture($user, $conversation, $currentStateId, $message, $selection);
```

The new method:

```php
    /**
     * Record the answer, move to the next state and emit its turn — the one
     * advance every capture path takes (typed, backstop, form).
     */
    private function advanceAfterCapture(User $user, AiConversation $conversation, string $currentStateId, string $message, string $selection): \Generator
    {
        // …the moved block, byte-identical, with `$selection` now a parameter…
    }
```

Move the lines with an editor cut and paste, not by retyping. Then run `git diff --stat` and read the diff of `handleAssetCaptureTurn`: it must show only the removal and the one-line `yield from`.

- [ ] **Step 4: Run the tests**

Run: `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php tests/Feature/Onboarding/PropertyCaptureLiveSentenceTest.php tests/Feature/Onboarding/CaptureRefusalRetryTest.php`
Expected: all pass (the last two prove the typed path still advances through the extracted helper).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
git commit -m "feat(onboarding): a form answer writes each kind through the property handler and advances like a typed capture"
```

---

