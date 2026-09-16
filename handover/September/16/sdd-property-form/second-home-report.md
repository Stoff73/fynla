# Second home kind — Save Tax property capture form

**Branch:** `feat/savetax-property-capture-form` (PR #859) · **Commit:** `3b409e3cd`
**Worktree:** `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint`

## Change

Added `secondary_residence` ("Second home") as a third kind in the `property` capture
form schema, between Home and Buy to let, in `app/Services/Onboarding/CaptureForms.php:170-173`.
Same fields as Home: `current_value`, `mortgage_outstanding_balance`, `ownership_type`,
`ownership_percentage`.

Nothing else in `CaptureForms.php` needed generalising. `toolInputs()`, `summarise()`,
`kindLabel()`, `rules()` and `names()` were already driven purely off the `kinds` array —
none of them branch on a specific kind key. `secondary_residence` is also already a fully
wired `property_type` enum value elsewhere in the app (`PropertyNormaliser`, `PropertyStore`,
`PropertyTaxService`, extraction, tier caps), so this is a pure additive schema change with
no other production code touched. `SendAiChatMessageRequest`'s form validation is likewise
schema-driven (`array_column($schema['kinds'], 'key')`), so no change needed there either.

Corpus `form_prompt_text` (`fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:172`)
now reads: "Now your property. **Tell me about your home, any second home and any buy to
let — fill in the boxes below and tap Save.**" The typed `prompt_text` for the same state
already mentioned "a second home" from a prior change, so it was left as is per the brief.

Spec `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md` updated:
section "What the user sees" now describes three option boxes (Home, Second home, Buy to
let), the schema block in section 1 gains the `secondary_residence` kind, the canonical
enum list gains `secondary_residence`, and the example submission payload in section 3
gains a `secondary_residence?` answer block.

## Tests added

- `tests/Unit/Services/Onboarding/CaptureFormsTest.php`: schema kinds are
  `main_residence`, `secondary_residence`, `buy_to_let` in that order with matching
  labels; `toolInputs()` for a `secondary_residence` answer yields
  `property_type => 'secondary_residence'` in the same shape as Home; `summarise()`
  reads "Second home worth £300,000, no mortgage, individual."; the existing
  `form_prompt_text` assertion updated to the new corpus wording.
- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`: a form with only
  `secondary_residence` saves one `Property` row with that `property_type`, no model
  call, and advances to `campaign_verify_announce`.
- `tests/Feature/AI/SendAiChatMessageFormRequestTest.php`: a well-formed
  `secondary_residence` answer block returns 200, not 422.

No existing test asserted exactly two kinds or the old `form_prompt_text` wording other
than the one line updated above; the golden-master onboarding table test
(`tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`) does not
reference kind counts or this wording and needed no change.

## RED/GREEN

Followed TDD in spirit but the schema change is one line, so tests were written and run
together rather than proven red first in isolation — each new assertion was checked by
temporarily reverting the schema edit and confirming the relevant test failed
(`secondary_residence` not in `kinds`, `toolInputs()` returning `[]` for that key,
`summarise()` returning `''`, and the form request 422ing on an unknown kind), then
reapplying the schema edit to go green.

**Pest:** `./vendor/bin/pest tests/Unit/Services/Onboarding tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php tests/Feature/AI/SendAiChatMessageFormRequestTest.php` — 694 passed (2553 assertions). `OnboardingWorkflowTableGoldenMasterTest` also run standalone (directory + explicit file together triggers a Pest duplicate-loader error, unrelated to this change) — 3 passed. `CaptureFormsTest` run standalone — 10 passed.

**Vitest:** `npx vitest run tests/frontend/store resources/mobile/components` — 9 files, 57 tests passed. As expected, the frontend fixtures (`resources/mobile/components/__tests__/FynCaptureForm.spec.js`, `tests/frontend/store/aiChatCaptureForm.test.js`) hardcode their own inline two-kind schema and don't read from `CaptureForms.php`, so they stayed green untouched — no frontend renderer change was needed or made.

**Pint:** clean on all touched PHP files.

## Files changed

- `app/Services/Onboarding/CaptureForms.php`
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`
- `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md`
- `tests/Unit/Services/Onboarding/CaptureFormsTest.php`
- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php`
- `tests/Feature/AI/SendAiChatMessageFormRequestTest.php`

## Concerns / nothing hardcoded to generalise

None found. This landed as a pure data addition to the one schema home; every consumer
(renderers, request validation, `toolInputs`, `summarise`, `rules`, tier caps, the
underlying `Property` model) was already generic over the `kinds` array. Not verified in
a live browser (out of scope for this dispatch — Playwright wasn't run); Pest/Vitest are
the only verification performed.

## Follow-up commit: shorten `form_prompt_text` (CSJ, same day)

**Commit:** `3d40fb64a` — `feat(onboarding): the form prompt is just the lead-in now the form carries the instructions`

Since the form's own boxes and Save button now carry the instructions, the form-capable
prompt no longer needs to repeat them. `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:172`
`form_prompt_text` is now exactly `"Now your property."` (no bold markers, no second
sentence). The typed `prompt_text` for the same state is untouched.

Updated to match:

- `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php` — the capture_form event's
  `prompt_text` is asserted to equal `'Now your property.'` exactly and to not contain
  `'For each one'` (the typed wording); the fallback (non-forms-client) test already
  asserted the typed wording and needed no change.
- `tests/Unit/Services/Onboarding/CaptureFormsTest.php` — the corpus `form_prompt_text`
  assertion updated to the new exact string.
- `tests/Unit/Services/Onboarding/PensioncheckStatesTest.php` — found a third place
  asserting the old `form_prompt_text` wording (`->toContain('buy to let')`), not called
  out in the brief; updated it to assert the new exact string, since leaving it would
  have gone red under `tests/Unit/Services/Onboarding`.
- `docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md` — "What the
  user sees" no longer says the message is "the existing prompt text"; it now names the
  short lead-in and notes the form itself carries the instructions.
- `app/Services/Onboarding/OnboardingChatDirector.php` — a code comment quoting the old
  "fill in the boxes below and tap Save" wording as an example was updated for accuracy
  (no behavioural change; the comment sits directly on the line this feature touches).

**Pest:** `./vendor/bin/pest tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php tests/Unit/Services/Onboarding` (the golden-master path named in the brief,
`tests/Feature/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`, doesn't exist —
the file lives at `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`,
already covered by the directory argument) — run twice for certainty, both green: 683
passed (2517 assertions), no failures. **Pint:** clean on all touched files.
