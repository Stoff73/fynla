# Final fix wave — Save Tax property capture form

Branch `feat/savetax-property-capture-form`, worktree `.../scratchpad/fix-joint`.
Four items from the final whole-branch review, one commit each.

## Item 1 — Critical (Ruling 17): native gets form-shaped wording it can't act on

**What changed.** `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`
— `campaign_property`'s `prompt_text` reverted to the pre-branch typed
instruction ("is it your home, a second home or a buy-to-let; roughly what
it's worth; whether there's a mortgage and how much is left on it; and
whether you own it individually or jointly?..."). Added a new corpus DATA key
`form_prompt_text` carrying the form-shaped wording ("Now your property.
Tell me about your home and any buy to let — fill in the boxes below and tap
Save."). No PHP allow-list exists for corpus state keys
(`OnboardingWorkflowTable::fromProcedure` / `OnboardingStateMachine::mergeTable`
copy any non-`next`/`prompt_text`/`skip_if` key straight through), so this is
pure data — confirmed by tracing `mergeTable` at
`app/Services/Onboarding/OnboardingStateMachine.php:849-869`.

`app/Services/Onboarding/OnboardingChatDirector.php` — in `emitTurnForState`'s
form branch (`:1036` pre-change), resolves a second prompt text via
`OnboardingStateMachine::resolvePromptText` against `form_prompt_text` (falling
back to `prompt_text` if unset) and uses it for both the `capture_form` SSE
event and the persisted assistant message. The non-forms fallback branch
(`else` at `:1116`) is untouched — it still reads the original `$promptText`,
which is now the typed instruction again.

**Tests.**
- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`: forms test now
  also asserts the `capture_form` event's `prompt_text` contains "tap Save";
  the fallback test now asserts the typed wording's own words ("is it your
  home, a second home or a buy-to-let", "how much is left on it") AND
  `->not->toContain('tap Save')` — this is what would have caught the
  original regression (both wordings share "Now your property").
- `tests/Unit/Services/Onboarding/CaptureFormsTest.php`: the state-shape test
  now asserts both `prompt_text` (typed) and `form_prompt_text` (form)
  verbatim.
- `tests/Unit/Services/Onboarding/PensioncheckStatesTest.php:939`: updated —
  the restored typed wording says "buy-to-let" (hyphenated), not "buy to let";
  added a companion assertion on `form_prompt_text` for the unhyphenated form
  wording.

**RED before / GREEN after.** Pre-fix, `CaptureFormsTest` failed with the old
hardcoded string once the corpus wording changed (caught during the run —
see below). Post-fix:
```
./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Services/Onboarding tests/Feature/AI
Tests:    4 skipped, 1607 passed (5715 assertions)
```

**Commit:** `359339f99` — fix(onboarding): a client without the forms capability keeps the typed property prompt

## Item 2 — Important (Ruling 18): fact extractor parks a house value as income

**What changed.** `app/Services/Onboarding/OnboardingChatDirector.php`,
`handleUserMessage` (`:187-213` post-change) — wrapped the existing
`factExtractor->extractAndPark(...)` try/catch in `if ($form === null) { ... }`.
A form turn's `$message` is `CaptureForms::summarise()` (e.g. "Home worth
£750,000, mortgage £325,000, joint, my share 50%."), which
`OnboardingFactExtractor::extractEmployment` mistakes for a volunteered income
sentence via `OnboardingValueInterpreter::parseMoney`'s first-£-figure match.
The structured answers already go straight to `Property` via
`CoordinatingAgent::executeTool('create_property', ...)` with no model call;
skipping the extractor for forms loses nothing.

**Test.** `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` — new
case submits a two-kind property form and asserts
`$conversation->fresh()->onboarding_parked_facts` is empty (`[]`), i.e. no
`employment.annual_income` was parked from the house value.

**RED before / GREEN after.**
Before the guard, the new test's own assertion
(`$parked['employment']['annual_income'] ?? null)->toBeNull()`) would have
failed — a manual check against the pre-fix code path (extractor called on
"Home worth £750,000...") confirms `OnboardingValueInterpreter::parseMoney`
returns `750000.0`, which is within `extractEmployment`'s accepted band and
gets parked. After the fix:
```
./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Services/Onboarding tests/Feature/AI
Tests:    4 skipped, 1607 passed (5715 assertions)
```

**Commit:** `705673855` — fix(onboarding): the fact extractor no longer parks a house value as income on a form turn

## Item 3 — Important 4: no test for the web capture-form lock rules

**What changed (test-only, no production code).**
`resources/js/components/__tests__/Shared/AiChatPanel.conversation.spec.js` —
added a new `describe` block with four Vitest cases against the existing
`AiChatPanel` harness pattern:
1. the newest `capture_form` row is open (`locked: false`) and renders
   `FynCaptureForm` with the right schema;
2. a following `user` row locks it, and `captureFormValues`/the `values` prop
   read the answers from that row's `metadata.form.answers`;
3. a `capture_form` row carrying `metadata.errors` stays open even with a
   `user` row after it (Ruling 13's refusal reopen);
4. submitting dispatches `aiChat/sendMessage` with exactly `{ form }`.

Two harness adjustments were required to make this renderable under
`shallowMount` (the production `AiChatPanel.vue`/`FynCaptureForm.vue` are
unchanged):
- `AiChatPanelShell`, `teleport`, and `transition` un-stubbed in
  `global.stubs` — the entire message list lives inside
  `AiChatPanelShell`'s default slot (itself inside a `Teleport`/`Transition`),
  and a shallow stub of any of the three swallows the slot content entirely.
- `FynCaptureForm` given an explicit stub restating its props
  (`schema`, `errors`, `disabled`, `locked`, `values`) — it declares its props
  via `captureFormMixin` (Ruling 1's shared mixin), not its own `props`
  option, and VTU's auto-generated shallow stub only reflects a component's
  own declared props, so `.props()` on the default stub came back empty even
  though the real attribute values were present in the rendered DOM.

**RED before / GREEN after.** Confirmed each fixture failed for the right
reason while diagnosing (missing state before `build()`, empty
`AiChatPanelShell`/`Teleport`/`Transition` stub swallowing the slot, empty
`.props()` from the default `FynCaptureForm` stub) before landing the harness
fix. Final run:
```
npx vitest run resources/js/components/__tests__/Shared tests/frontend/components/Fyn tests/frontend/store
Test Files  9 passed (9)
     Tests  49 passed (49)
```

**Commit:** `fdec6ecb9` — test(web): cover the capture-form open/locked/submit rules in AiChatPanel

## Item 4 — queued round-trip test (ledger minor T6)

**What changed (test-only).**
`tests/Feature/AI/SendAiChatMessageFormRequestTest.php` — new case, following
the existing pattern in `tests/Feature/AI/CampaignReentryDispatchTest.php`'s
"Test 5" (direct `ConcurrentTurnQueue::enqueue` + HTTP `.../stream`, since no
existing test simulates the `Cache::lock('fyn:inflight:...')` contention at
HTTP level). Enqueues a property form's summary sentence with
`metadata.form` via `app(ConcurrentTurnQueue::class)->enqueue(...)`, asserts
the row lands `Queued` with `metadata['form']` intact, then posts to
`/api/ai-chat/conversations/{id}/messages/{queuedId}/stream` with the forms
header and asserts the property is created and the stream contains
`onboarding_advance` — proving `AiChatController::streamQueuedMessage`
(`:448-450`) correctly reads `metadata.form` back out and passes it to
`OnboardingChatDirector::handleUserMessage` exactly like a live submission.

**RED before / GREEN after.** This is new coverage of an existing, working
code path (the review's own triage judged this the strongest gap but not a
known defect), so there was no pre-existing bug to reproduce; the test was
written and ran green on first pass, then re-verified in the full run:
```
./vendor/bin/pest tests/Feature/AI/SendAiChatMessageFormRequestTest.php
Tests:    10 passed
```

**Commit:** `0794aa8d0` — test(ai-chat): cover the queued-form round trip through the concurrent-turn queue

## Full verification (all four items together)

```
./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Services/Onboarding tests/Feature/AI
Tests:    4 skipped, 1607 passed (5715 assertions)

npx vitest run
Test Files  146 passed (146)
     Tests  1355 passed (1355)

./vendor/bin/pint --test <all 5 changed PHP files>
{"tool":"pint","result":"passed"}
```

## Files touched

- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`
- `app/Services/Onboarding/OnboardingChatDirector.php`
- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
- `tests/Unit/Services/Onboarding/CaptureFormsTest.php`
- `tests/Unit/Services/Onboarding/PensioncheckStatesTest.php`
- `resources/js/components/__tests__/Shared/AiChatPanel.conversation.spec.js`
- `tests/Feature/AI/SendAiChatMessageFormRequestTest.php`

## Concerns / notes for the controller

- Items 1–2 are production-code fixes; items 3–4 are test-only (no
  production behaviour changed by this dispatch beyond items 1–2).
- I5 (second home no longer prompted on any surface) was explicitly out of
  scope per the dispatch — the typed instruction restored in Item 1 does
  mention "a second home" again (it's the original pre-branch wording), but
  the form itself still only offers Home/Buy to let kinds. That's unchanged
  from before this dispatch and was intentionally left to CSJ's call.
- I2 (refused form can't be corrected after reload), I3 (422 vs friendly
  message ledger entry) and the other minors were explicitly excluded from
  this dispatch per the controller's instructions and are untouched.
