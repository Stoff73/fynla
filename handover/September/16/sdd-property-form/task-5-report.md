# Task 5 report — the director saves a form answer through the property handler

## Fix round 1 (post-review) — guard an empty-kind form answer

Review finding: in `handleFormTurn`, when `CaptureForms::toolInputs($form)`
returns `[]` (no recognised kind in `answers`), the `foreach` loop never
ran, `$errors` stayed `[]`, and the method fell straight through to
`capture_complete` + `advanceAfterCapture` — advancing the step with zero
writes. The request layer rejects this shape today, but the director must
not rely on a caller's validation.

**Fix applied**, exactly per the controller's ruling: introduced
`$inputs = CaptureForms::toolInputs($form);` right after the
`form_received` yield, added a guard that — when `$inputs === []` — yields
`capture_form_errors` (keyed `_form`), a `content` line ("Fill in at least
one property before saving."), saves the assistant message with
`capture_write_failed: true`, yields `done`, and returns without touching
`recordProgress`/`advanceAfterCapture`. The `foreach` below now iterates
`$inputs` instead of re-calling `CaptureForms::toolInputs($form)`.

**Test added**, verbatim per the controller's brief, appended to
`tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`:
`it('never advances on a form answer with no recognised kind', ...)` —
posts `['name' => 'property', 'answers' => ['castle' => ['current_value' => 1]]]`
(an unrecognised kind key) and asserts no `Property` row is created, the
`capture_form_errors` event's `errors` has key `_form`, no
`capture_complete` or `onboarding_advance` event fires, and the user stays
on `STATE_CAMPAIGN_PROPERTY`.

**Command and output:**

```
./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
```

```
   PASS  Tests\Feature\Onboarding\PropertyCaptureFormTurnTest
  ✓ it emits the property form to a client that can render forms and p… 53.65s
  ✓ it emits the typed prompt instead when the client has not declared…  0.54s
  ✓ it sends a typed sentence at the form step down the existing captur… 1.22s
  ✓ it saves both kinds from a form answer with no model call and advan… 0.66s
  ✓ it saves one kind alone                                              0.58s
  ✓ it reports a refused kind on the form, keeps the landed one, and st… 0.53s
  ✓ it never invokes the model for a form answer                         0.67s
  ✓ it tells the user a stale form is no longer open and re-emits the c… 0.36s
  ✓ it never advances on a form answer with no recognised kind           0.26s

  Tests:    9 passed (51 assertions)
  Duration: 59.04s
```

Also re-ran the two-file typed-capture regression set — unaffected, still
green:

```
./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureLiveSentenceTest.php tests/Feature/Onboarding/CaptureRefusalRetryTest.php
```

```
   PASS  Tests\Feature\Onboarding\PropertyCaptureLiveSentenceTest   (4 tests)
   PASS  Tests\Feature\Onboarding\CaptureRefusalRetryTest           (3 tests)

  Tests:    7 passed (36 assertions)
```

`Pint` reported `"result":"passed"` on both changed files (no rewrites).
Only `app/Services/Onboarding/OnboardingChatDirector.php` and
`tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` were staged and
committed — a concurrently modified
`docs/superpowers/plans/2026-09-15-savetax-property-capture-form.md` in
the shared worktree (not touched by me this round) was left alone.

Committed as `a31df0697` — "fix(onboarding): a form answer with no
recognised kind never advances".

## What I implemented

Exactly as specified in `task-5-brief.md`:

1. `handleUserMessage` signature gained `?array $form = null` as a sixth
   parameter. When set, the user row is saved with `metadata.form = $form`
   instead of the plain call.
2. Immediately after the `$state === null` guard, a new branch: if `$form`
   is not null, either (a) the current state isn't a `form` turn for this
   form's name — emit "That form is no longer open — let's carry on from
   where we are.", save it, and re-emit the current turn
   (`includeTransitionHeader: false`); or (b) delegate to the new
   `handleFormTurn`.
3. New private `handleFormTurn(User, AiConversation, string $message,
   string $currentStateId, array $state, array $form): \Generator` —
   yields `form_received` first, then for each kind in
   `CaptureForms::toolInputs($form)` calls
   `$this->coordinatingAgent->executeTool('create_property', $input, $user,
   $conversation->id, confirmedFacts: $facts)` between `tool_use`
   running/complete events, yielding `entity_created` on success or
   collecting the failure into `$errors`. Any errors → one
   `capture_form_errors` event, a `content` line naming each failed kind,
   `recordProgress`, `done` — the step stays parked (no advance). No
   errors → `capture_complete`, then `yield from
   $this->advanceAfterCapture(...)`.
4. Extracted `advanceAfterCapture(User $user, AiConversation $conversation,
   string $currentStateId, string $message, string $selection): \Generator`
   from the tail of `handleAssetCaptureTurn` (everything from
   `$this->recordProgress(` through the final
   `yield from $this->emitTurnForState(...)`), moved via Edit-tool
   replacement using an old_string that was the exact text read from the
   file (verified byte-identical, see below) — `handleAssetCaptureTurn`
   now ends with the single line
   `yield from $this->advanceAfterCapture($user, $conversation,
   $currentStateId, $message, $selection);`.

## One deviation from the brief, with justification

The brief's first new test asserted
`$userRow->metadata['form']->toBe(propertyAnswers())`. This is the same
MySQL native-JSON-column key-reordering fact this exact test file already
documents (in the first, pre-existing test) for `metadata['capture_form']`
— the column re-sorts object keys alphabetically at every nesting level on
round-trip, so array key ORDER differs even though every value is
identical. `->toBe` is strict identity (order-sensitive); the existing
test in the same file already uses `->toEqual` for the identical reason,
with a comment explaining why. I applied the same fix — changed the one
assertion to `->toEqual` and added a one-line comment pointing at the
existing explanation above it. This is a test-assertion correction only;
no production behaviour or the values being verified changed. Diff:

```diff
-        ->and($userRow->metadata['form'])->toBe(propertyAnswers());
+    // ->toEqual, not ->toBe, for metadata['form'] — the same MySQL native
+    // JSON column key-reordering fact documented above for capture_form.
+    $userRow = AiMessage::where('conversation_id', $conversation->id)->where('role', 'user')->latest('id')->first();
+    expect($userRow->content)->toContain('Home worth £750,000')
+        ->and($userRow->metadata['form'])->toEqual(propertyAnswers());
```

## TDD evidence

### RED — command and output

```
./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
```

```
   FAIL  Tests\Feature\Onboarding\PropertyCaptureFormTurnTest
  ✓ it emits the property form to a client that can render forms and p… 26.28s
  ✓ it emits the typed prompt instead when the client has not declared…  0.25s
  ✓ it sends a typed sentence at the form step down the existing captur… 0.68s
  ⨯ it saves both kinds from a form answer with no model call and advan… 0.74s
  ✓ it saves one kind alone                                              0.63s
  ⨯ it reports a refused kind on the form, keeps the landed one, and st… 0.56s
  ✓ it never invokes the model for a form answer                         0.72s
  ⨯ it tells the user a stale form is no longer open and re-emits the c… 0.34s

  Tests:    3 failed, 5 passed (34 assertions)
```

**Note on the RED shape vs. the brief's expectation.** The brief expected
all five new tests to fail because "`handleUserMessage` accepts 5
arguments" — implying an arity error. PHP does not raise an error for
*extra* positional arguments to a function with fewer declared parameters
(only for too few required ones), so the pre-implementation call with a
6th `$form` argument simply ignored it and fell through to the existing
delegated/typed-capture path. For "saves one kind alone" and "never
invokes the model for a form answer", `CaptureForms::summarise($form)`
happens to produce a natural-language sentence the pre-existing
deterministic property extractor (landed the same week, commits
`74b6ef3c8`/`3a54ce36b`/`d38d96a0d`) can already parse and write without
a model call — so those two incidentally passed against the OLD code
path, for the right end-state but the wrong mechanism. The other three
failed genuinely (wrong first event type, no `capture_form_errors` event,
no "no longer open" text), proving `$form` had no effect. I verified this
is the correct diagnosis by re-running the same three-and-five split after
implementation: all 5 pass, now via the new code path (see GREEN below
and the "never invokes the model" test still checks the right thing since
`handleFormTurn` genuinely never calls a model).

### GREEN — command and output

```
./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php
```

```
   PASS  Tests\Feature\Onboarding\PropertyCaptureFormTurnTest
  ✓ it emits the property form to a client that can render forms and p… 26.67s
  ✓ it emits the typed prompt instead when the client has not declared…  0.21s
  ✓ it sends a typed sentence at the form step down the existing captur… 0.64s
  ✓ it saves both kinds from a form answer with no model call and advan… 0.49s
  ✓ it saves one kind alone                                              0.46s
  ✓ it reports a refused kind on the form, keeps the landed one, and st… 0.41s
  ✓ it never invokes the model for a form answer                         0.48s
  ✓ it tells the user a stale form is no longer open and re-emits the c… 0.23s

  Tests:    8 passed (46 assertions)
```

## Three-file regression run

```
./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php tests/Feature/Onboarding/PropertyCaptureLiveSentenceTest.php tests/Feature/Onboarding/CaptureRefusalRetryTest.php
```

```
   PASS  Tests\Feature\Onboarding\PropertyCaptureFormTurnTest       (8 tests)
   PASS  Tests\Feature\Onboarding\PropertyCaptureLiveSentenceTest   (4 tests)
   PASS  Tests\Feature\Onboarding\CaptureRefusalRetryTest           (3 tests)

  Tests:    15 passed (82 assertions)
  Duration: 25.85s
```

The last two files prove the typed capture path — including the
tier-cap/refusal-retry flow — still advances correctly through the
extracted `advanceAfterCapture` helper, with zero behaviour change.

## Files changed

- `app/Services/Onboarding/OnboardingChatDirector.php`
- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`

Committed as `4b3e942eb` on `feat/savetax-property-capture-form`.

## git diff evidence — the moved block is verbatim

`git diff app/Services/Onboarding/OnboardingChatDirector.php` (full diff
reviewed) shows, inside `handleAssetCaptureTurn`, only:

```diff
+        yield from $this->advanceAfterCapture($user, $conversation, $currentStateId, $message, $selection);
+    }
+
+    /**
+     * Record the answer, move to the next state and emit its turn — the one
+     * advance every capture path takes (typed, backstop, form).
+     */
+    private function advanceAfterCapture(User $user, AiConversation $conversation, string $currentStateId, string $message, string $selection): \Generator
+    {
         // Record the step in onboarding_progress (best-effort — tool calls
```

i.e. no `-` lines inside `handleAssetCaptureTurn` for the moved body — the
old block simply now appears, unchanged, as the body of
`advanceAfterCapture` (the git diff engine matched it as unmoved context
because the text is identical; only the 39-line block's *location*
changed, plus the one new line replacing it in the caller). I additionally
hand-compared the pre-edit `Read` output of lines 4238–4287 against the
post-edit content of `advanceAfterCapture` line by line — identical,
including comments, blank lines and indentation.

## Self-review findings

- `handleFormTurn`'s FIRST yield is `form_received`, as required — no
  `onboarding_layout_change` or other event precedes it for the form path
  (unlike `emitTurnForState`, which is not invoked on this branch).
- The stale-form branch checks `$state['turn_type'] !== 'form'` OR
  `$state['form'] !== $form['name']` — covers both "the walk moved to a
  non-form step" and "moved to a *different* form step" (not exercised by
  a test here, since there is only one form today, but the guard is
  correct per the brief).
- Confirmed `handleAssetCaptureTurn`'s existing `$selection` variable
  (derived from `$user->onboarding_fyn_selection ?? 'savings'` at the top
  of that method) is passed unchanged into `advanceAfterCapture` — no
  behaviour change for the typed path.
- `Pint` reported `"result":"passed"` with no rewrites needed on either
  file.
- `php -l` clean on both files.
- Confirmed only the two intended files are staged
  (`git status` before commit).

## Concerns

1. The test-assertion deviation above (`->toBe` → `->toEqual`) — flagging
   per working-style "report adjacent issues rather than silently fixing
   them," though I judged this one safe and necessary to fix directly
   since it is a verbatim repeat of an already-documented, already-fixed
   pattern in the same file, not a new judgement call.
2. The brief's Step 2 "expected failure" description (arity error) does
   not hold in PHP for extra positional arguments; documented above so a
   future reader of this task isn't confused when re-running RED.
3. `handleFormTurn`'s partial-failure branch records progress with a
   hardcoded `'selection' => 'savetax'` (matching the brief exactly) —
   consistent with the success branch's `advanceAfterCapture(..., 'savetax')`
   call, so this is intentional per the brief, not an oversight, but
   flagging since it hardcodes the selection rather than reading
   `$user->onboarding_fyn_selection` (the property form only appears on
   the SaveTax campaign path today, so this is a non-issue in practice).
