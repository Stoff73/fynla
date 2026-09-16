# Task 3 + Task 4 report — Save Tax property capture form (corpus + director)

Branch: `feat/savetax-property-capture-form`
Worktree: `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint`

## Task 3: The corpus makes `campaign_property` a form turn

**Implemented:**
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`: the `campaign_property` block now reads `turn_type: form`, `form: property`, and the new player-facing `prompt_text` ("Now your property. **Tell me about your home and any buy to let — fill in the boxes below and tap Save.**"), `capture_field: null` and `next` unchanged.
- `app/Services/Onboarding/OnboardingStateMachine.php` line 36 docblock: `turn_type` list now includes `'grouped_extract'` and `'form'` (only the docblock changed; no PHP state logic touched).
- Appended one test to `tests/Unit/Services/Onboarding/CaptureFormsTest.php`, exactly as the brief specified.

**TDD evidence:**

RED — `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php --filter="form turn"`
```
FAILED  Tests\Unit\Services\Onboarding\CaptureFormsTest > it the property step is a form turn owned by the corpus
Failed asserting that two strings are identical.
-'form'
+'delegated'
```

GREEN — `./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php tests/Unit/Services/Onboarding/CampaignSectionFlowTest.php tests/Unit/Services/Onboarding/PensioncheckSectionsTest.php`
```
Tests:    58 passed (474 assertions)
```
`php artisan fyn:procedural:validate | grep onboarding.workflow` →
```
active: onboarding.workflow.fyn-onboarding v1 (workflow/onboarding)
```
No golden-master fixture failed, so no fixture regeneration was needed.

**Files changed:**
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`
- `app/Services/Onboarding/OnboardingStateMachine.php`
- `tests/Unit/Services/Onboarding/CaptureFormsTest.php`

**Commit:** `b47a8ab5d` — `feat(onboarding): the Save Tax property step is a form turn`

**Self-review:** Diff is exactly the three files/blocks the brief specified — no stray edits. `capture_focus => 'property'` still comes from the PHP `inCodeStates()` array (`app/Services/Onboarding/OnboardingStateMachine.php:502-503`), untouched, and merges correctly over the corpus DATA fields (proven by the golden-master test). `next` and `capture_field` in the corpus block were left exactly as they were before, per the brief's replacement block.

**Concerns:** None.

---

## Task 4: The director emits the form turn

**Implemented** in `app/Services/Onboarding/OnboardingChatDirector.php`:
- New `private bool $clientSupportsForms = false;` property and public `setClientSupportsForms(bool $supports): void` setter, placed immediately after the constructor.
- `emitTurnForState` changed from `private` to `public`.
- Inside `emitTurnForState`, immediately before the `bubbles` branch: a new `if ($turnType === 'form' && $this->clientSupportsForms)` branch that resolves the schema via `CaptureForms::schema(...)` (no import needed — same namespace), yields a `capture_form` SSE event, persists the assistant message with `metadata.capture_form`/`onboarding_step`/`turn_intent`, yields `done`, and returns. Falls through unchanged to the existing `else` (plain-text) branch when the client doesn't support forms or the schema name is unknown.
- In `handleUserMessage`, the delegated dispatch now matches `in_array($state['turn_type'] ?? '', ['delegated', 'form'], true)` so a typed reply at a form step still reaches `handleAssetCaptureTurn` unchanged.
- Created `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` with the three tests from the brief, with one assertion-method fix (below).

**TDD evidence:**

RED — `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
```
Call to undefined method App\Services\Onboarding\OnboardingChatDirector::setClientSupportsForms()
Call to undefined method App\Services\Onboarding\OnboardingChatDirector::setClientSupportsForms()
Failed asserting that false is true.  (Property::where(...)->exists())
Tests:    3 failed (1 assertions)
```

GREEN — `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
```
Tests:    3 passed (11 assertions)
```

**One deviation from the brief's literal test text, with evidence:** the brief's first test asserts `$saved->metadata['capture_form']` — read back from the DB after the `'array'`-cast `metadata` JSON column round-trips — with `->toBe()` (strict, order-sensitive). Fynla's Pest suite runs against a real MySQL connection (`.env` `DB_CONNECTION=mysql`; `phpunit.xml` only overrides `DB_DATABASE`, not the connection). MySQL's native `JSON` column type does not preserve object member order; I proved this directly against the column, independent of this feature:
```
written: {"b":1,"a":2,"c":{"z":1,"y":2}}
read:    {"a": 2, "b": 1, "c": {"y": 2, "z": 1}}
```
This reorders every nesting level of the persisted `capture_form` schema (confirmed by the first failing diff — `submit_label` moved to last, `label`/`value` swapped inside each option, etc.), while the in-memory SSE event assertion two lines above (`$form['form']` — never touches the DB) passed unchanged with `->toBe()`. I changed only that one assertion from `->toBe()` to `->toEqual()` (value-equal, order-insensitive — PHPUnit's `assertEquals` on arrays), added a comment explaining why, and left every other assertion, and all implementation code, exactly as specified. This is a pre-existing MySQL JSON-column fact untouched by this feature, not a defect in the director's output — the same schema array is written in-order every time; only its round-trip read shape changes.

**Files changed:**
- `app/Services/Onboarding/OnboardingChatDirector.php`
- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` (created)

**Commit:** `39b4731b7` — `feat(onboarding): the director emits a capture_form turn to clients that render forms`

**Extra regression runs (both required green, run together):**
`./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureLiveSentenceTest.php tests/Feature/Onboarding/CaptureStateToolCoverageTest.php`
```
Tests:    10 passed (54 assertions)
```
Confirms typed text at the property step still takes the delegated path, and the turn-type change is tolerated by the tool-coverage inventory test.

**Self-review:** Diff matches the brief line-for-line (setter placement, `public` visibility change, the `form` branch inserted immediately before `bubbles`, the dispatch `in_array` change) with no additional edits. No naming collisions: `formStepUser`/`formConversation` (global Pest helper functions) are defined nowhere else in `tests/`. `pint` passed clean on both files with no reformatting beyond the tool's own house style.

**Concerns:** The `->toBe()` → `->toEqual()` test fix above is the only departure from the brief's literal text; flagging it explicitly since Rule 16 requires surfacing any deviation rather than silently shipping it. No behavioural code deviates from the brief.

---

## Combined final run (Task 3 + Task 4 required suites)

`./vendor/bin/pest tests/Unit/Services/Onboarding/CaptureFormsTest.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php tests/Unit/Services/Onboarding/CampaignSectionFlowTest.php tests/Unit/Services/Onboarding/PensioncheckSectionsTest.php tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php tests/Feature/Onboarding/PropertyCaptureLiveSentenceTest.php tests/Feature/Onboarding/CaptureStateToolCoverageTest.php`
→ all green across both runs above (58 + 13 = 71 tests, all passing; the 3 PropertyCaptureFormTurnTest + 4 PropertyCaptureLiveSentenceTest + 6 CaptureStateToolCoverageTest = 13 counted once in the second run, not double-counted).
