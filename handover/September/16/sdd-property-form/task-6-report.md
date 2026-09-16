# Task 6 report: the controller carries the flag and the form through send, stream and start

## What I implemented

Exactly per the brief's Step 3:

1. **`ConcurrentTurnQueue::enqueue`** (`app/Services/AI/Loop/ConcurrentTurnQueue.php`) — added an optional `array $metadata = []` parameter; when non-empty it's written onto the created `AiMessage` row's `metadata` column (array-cast). This is how a form answer's raw payload survives from `sendMessage`'s queued-turn branch to `streamQueuedMessage`, which pops it back out.

2. **`AiChatController::sendMessage`** (line ~225) — reads `form` off the request, casts to `?array`; composes `$message` via `CaptureForms::summarise($form)` when a form was posted (falls back to the typed `message` input otherwise); sets the director's `clientSupportsForms` flag from the new `X-Fynla-Forms` header; threads `$form` through both the queued-turn `enqueue()` call (as `['form' => $form]` metadata) and the immediate-stream `handleUserMessage(..., true, $form)` call.

3. **`AiChatController::streamQueuedMessage`** (line ~446) — after popping `$message = $queued->content`, reads `metadata.form` back off the queued row, re-derives the `clientSupportsForms` flag from the (queued-turn's own) request header, and passes `$form` as the sixth arg to `handleUserMessage(..., false, $form)`.

4. **`AiChatController::startOnboarding`** — sets `clientSupportsForms` from the header immediately before constructing the `StreamedResponse` whose closure calls `emitFirstTurn` (the only director call in this method).

5. Added the private `clientSupportsForms(Request $request): bool` helper at the bottom of the controller (`trim(...) === '1'`) and the `use App\Services\Onboarding\CaptureForms;` import.

## What I tested and results

TDD per Step 1/2/4 of the brief, run from the worktree at `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint`.

### RED (Step 2)

Command: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php`

```
✓ it rejects an unknown form name
✓ it rejects a kind the schema does not have and a bad ownership valu…
✓ it requires the mortgage key to be present even when null, and a va…
✓ it still requires a message when there is no form
✓ it accepts a well-formed property answer with no message
✓ it validates only the kinds that were submitted
⨯ it streams the property form to a client declaring forms and the ty…
⨯ it saves a posted form answer and records the plain-words line as t…

FAILED > it streams the property form...
  Failed asserting that 'data: {"type":"resume","conversation_id":null,"current_step":"campaign_property"}\n\n' contains '"type":"capture_form"'.

FAILED > it saves a posted form answer...
  TypeError: App\Services\Onboarding\OnboardingChatDirector::handleUserMessage(): Argument #3 ($message) must be of type string, null given, called in .../AiChatController.php on line 276
```

Both new tests failed as expected — the TypeError confirms the controller was still passing the raw (possibly-null) `message` input straight into the director with no `$form` handling. **Deviation from the brief's stated expectation:** "Task 2's last test" (`it validates only the kinds that were submitted`) was already green before this task, not red — see "Issues or concerns" below.

Note on the brief's fallback clause: the brief's first test assumes `/start` for a resumed campaign user re-emits the step's turn. The actual current behaviour (confirmed by the RED run above) is that `startOnboarding`'s early-return "already mid-flow" branch emits only a bare `{type: resume, conversation_id, current_step}` event and never calls the director at all — so neither the primary test's premise nor its literal fallback ("post `{message: 'Continue'}`" — which I confirmed routes to `handleAssetCaptureTurn` and invokes the model, not `emitTurnForState`) can exercise the header-threading change in `startOnboarding`. I adapted the test to drive a **fresh** funnel entry landing directly on `campaign_property` (via a `config(['onboarding.campaign_map.savetax.entry' => ...])` override in the test), which exercises the actual director call this task's `startOnboarding` change touches — `emitFirstTurn`. This is documented inline in the test.

### GREEN (Step 4)

Command: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php`

```
✓ it rejects an unknown form name
✓ it rejects a kind the schema does not have and a bad ownership valu…
✓ it requires the mortgage key to be present even when null, and a va…
✓ it still requires a message when there is no form
✓ it accepts a well-formed property answer with no message
✓ it validates only the kinds that were submitted
✓ it streams the property form to a client declaring forms and the ty…
✓ it saves a posted form answer and records the plain-words line as t…

Tests:    8 passed (25 assertions)
```

Command (brief's exact Step 4, adjusted to avoid double-loading the same file — `tests/Feature/AI` already contains `SendAiChatMessageFormRequestTest.php`):
`./vendor/bin/pest tests/Feature/Onboarding/StateMachineWalkthroughTest.php tests/Feature/AI`

```
Tests:    3 skipped, 503 passed (1802 assertions)
```

(The 3 skipped are pre-existing golden-master "Capture only runs..." fixture-capture tests, unrelated to this change.)

Additional regression passes I ran given the `ConcurrentTurnQueue::enqueue` signature change and the `startOnboarding`/`streamQueuedMessage` edits:

- `tests/Feature/Fyn/ConcurrentTurnQueueTest.php` — 9 passed
- `tests/Feature/Fyn/ConcurrentTurnQueueGateTest.php` — 9 passed
- `tests/Feature/AI/CampaignReentryDispatchTest.php` — 6 passed
- `tests/Feature/AI/ContextualConversationDispatchTest.php` — 4 passed
- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` — 9 passed (51 assertions)

All green, no regressions.

## Files changed

- `app/Http/Controllers/Api/AiChatController.php`
- `app/Services/AI/Loop/ConcurrentTurnQueue.php`
- `tests/Feature/AI/SendAiChatMessageFormRequestTest.php`

## Self-review findings

- **Bug caught and fixed during TDD**: my first edit adding `use App\Services\Onboarding\CaptureForms;` to the controller was silently stripped by the PostToolUse formatter hook (it ran before I'd added any usage of the class, so an unused-import pass removed it). This produced `Class "App\Http\Controllers\Api\CaptureForms" not found` 500s on two previously-green tests once I added the `CaptureForms::summarise()` call. Caught by running the full Step-4 regression, not just the two new tests — re-added the import (now used, so it survives future formatter passes) and re-verified.
- **Bug caught and fixed in my own test**: my first version of the "streams the property form..." test called `$this->withHeader('X-Fynla-Forms', '1')` before the first `/start` call and then made a second, header-less `postJson` call expecting the header to be absent. Laravel's `withHeader()` sets a *default* header for every subsequent request in the test (`$this->defaultHeaders`), so it was still attached to the second call. Fixed by calling `$this->flushHeaders()` between the two calls, with a comment explaining why.
- No other issues found. The diff matches the brief's Step 3 code verbatim for the controller and queue changes.

## Issues or concerns

- **Brief prediction mismatch (informational, not a defect)**: the brief's Step 2 said "the two new tests and Task 2's last test fail." In this codebase state, Task 2's last test (`it accepts a well-formed property answer with no message`) was already green at RED time — that test's user has `onboarding_completed => true` with no campaign step, so it routes to `AdviceFyn::handle` (not `OnboardingChatDirector::handleUserMessage`), and `AdviceFyn::handle`'s `$message` parameter tolerates the null this task's fix later replaces with `CaptureForms::summarise()`. This is not a regression and required no fix; noting it only because it deviates from the brief's stated expectation.
- **Test adaptation for `/start`**: as described above, I used the brief's explicitly-granted latitude to adapt the first new test's harness (not its assertions) because both the brief's primary scenario and its literal fallback text don't match this codebase's actual `/start` resume-branch behaviour (a bare `resume` event with no director call). The assertions themselves — a client declaring forms gets `capture_form`, one that doesn't gets the typed "Now your property" text — are unchanged from the brief's intent.

## Fix round 1 (review finding: action endpoint missing the flag)

**Finding (Important, Ruling 6)**: `AiChatController::action()` never called `setClientSupportsForms` before dispatching to `handleAction()`. Its `continue` case (`OnboardingChatDirector::handleAction`, lines 662-679) calls `emitTurnForState`, which gates `capture_form` on the flag — so a resumed user at `campaign_property` tapping Continue after the bare `resume` event (the actual live resume path, confirmed in my Task 6 notes above) never got the form on web or `/m`.

### What changed

One line in `app/Http/Controllers/Api/AiChatController.php::action()`, immediately after the existing `routesToOnboarding` call and before the `StreamedResponse` is constructed — the same placement pattern as `sendMessage`, `streamQueuedMessage` and `startOnboarding`:

```php
$this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));
```

### Covering test

Added to `tests/Feature/AI/SendAiChatMessageFormRequestTest.php`: `it carries the forms flag through the action endpoint continue press — the live resume path`. Uses `formStepHttpUser()` (step = `campaign_property`) with a plain active director conversation, and posts `{action: 'continue'}` to `POST /api/ai-chat/conversations/{id}/action` — first with `X-Fynla-Forms: 1` (expects `capture_form`), then (after `flushHeaders()`) without it (expects no `capture_form`, and the typed "Now your property" text). Confirmed `campaign_property` carries no `reprompt_text` in the shipped corpus (`fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:168-173`), so a second `continue` on the same conversation doesn't swap in different prompt text and the assertion is safe.

### RED (fix verification)

Command: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php --filter "carries the forms flag through the action endpoint"` — run with the one-line fix temporarily removed.

```
⨯ it carries the forms flag through the action endpoint continue pre…
  Failed asserting that 'data: {"type":"onboarding_layout_change","mode":"wide"}\n\n
  data: {"type":"content","text":"Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**"}\n\n
  data: {"type":"done","message_id":1}\n\n' contains '"type":"capture_form"'.

Tests:    1 failed (2 assertions)
```

Confirms the exact defect the reviewer described: even with the header sent, the flag was never set, so `capture_form` never appears.

### GREEN (fix verification)

Fix line restored. Command: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php`

```
✓ it rejects an unknown form name
✓ it rejects a kind the schema does not have and a bad ownership valu…
✓ it requires the mortgage key to be present even when null, and a va…
✓ it still requires a message when there is no form
✓ it accepts a well-formed property answer with no message
✓ it validates only the kinds that were submitted
✓ it streams the property form to a client declaring forms and the ty…
✓ it saves a posted form answer and records the plain-words line as t…
✓ it carries the forms flag through the action endpoint continue pres…

Tests:    9 passed (30 assertions)
```

Command: `./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php tests/Feature/AI/AiChatActionConsentGateTest.php tests/Feature/AI/CampaignAuditFixesTest.php tests/Feature/AI/CampaignReentryExitTest.php tests/Feature/AI/CampaignReentryDispatchTest.php tests/Feature/AI/ContextualConversationDispatchTest.php` (the reviewer's requested action-endpoint coverage, found via `grep -rln "ai-chat/conversations.*/action" tests/Feature`):

```
Tests:    50 passed (188 assertions)
```

Command (full Step-4 regression, re-run since the controller changed again): `./vendor/bin/pest tests/Feature/Onboarding/StateMachineWalkthroughTest.php tests/Feature/AI`

```
Tests:    3 skipped, 504 passed (1807 assertions)
```

(504 vs. the original 503 — the one new test. Same 3 pre-existing golden-master skips, unrelated to this change.)

Pint: `./vendor/bin/pint app/Http/Controllers/Api/AiChatController.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php` → `{"tool":"pint","result":"passed"}`, no changes needed.

### Files changed (fix round 1)

- `app/Http/Controllers/Api/AiChatController.php` (one line added to `action()`)
- `tests/Feature/AI/SendAiChatMessageFormRequestTest.php` (one new test)

Commit: `d3bd4b114 fix(ai-chat): the action endpoint's Continue press carries the forms flag too`
