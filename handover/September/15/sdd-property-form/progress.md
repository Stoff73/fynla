# SDD ledger — plan: docs/superpowers/plans/2026-09-15-savetax-property-capture-form.md
Spec: docs/superpowers/specs/2026-09-15-savetax-property-capture-form-design.md (read; binding authority)
Worktree: /private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint  branch feat/savetax-property-capture-form  start HEAD 3b05703a2

## Pre-flight scan (2026-09-15 17:50 BST)

| Pair / task | Produces vs consumes | Finding |
|---|---|---|
| T1 -> T2 | `CaptureForms::names()`, `rules()` keyed `<kind>.<field>`; T2 nests them under `form.answers` | consistent |
| T1 -> T5 | `toolInputs()` keys = handler field names; `kindLabel()`; `summarise()` | consistent |
| T1 -> T6 | `summarise()` composes the user message | consistent |
| T3 -> T4 | corpus `turn_type: form`, `form: property`; T4 reads `$state['form']` | consistent; `form` is a corpus DATA key, absent from PHP (golden master ok) |
| T4 -> T5 | T4 makes `emitTurnForState` public, adds `setClientSupportsForms`; T5 adds `$form` param + `handleFormTurn` + `advanceAfterCapture`; both edit the director sequentially | consistent (T5 dispatched after T4 lands) |
| T5 -> T6 | `handleUserMessage(..., ?array $form)`; T6 passes it on send/stream, sets the flag on send/stream/start | consistent |
| T5 -> T7/T9 | events `form_received{text}`, `capture_form{prompt_text,form}`, `capture_form_errors{form,errors}` | consistent |
| T6 -> T7/T9 | header `X-Fynla-Forms: 1`; body `{form}` with `message` omitted; T2 rule `message required_without:form` | consistent |
| T7 -> T8 | `sendMessage(ctx, {form})`; row role `capture_form`, `metadata.capture_form`, `metadata.errors`; user row `metadata.form.answers` | consistent |
| T9 -> T10 | row `m.form = {schema, errors, answers, locked}`; `submitCaptureForm(form)` | consistent |
| T8 vs T10 | T10 says "the `<script>` is the same code, copied" | rubric conflict: verbatim duplication of a logic block. See Ruling 1 |
| T5 internal | test expects first event `form_received`; `handleUserMessage` yields nothing before the form branch | consistent |
| T5 internal | tier test seeds one property, then form saves home (2nd) and refuses BTL (3rd) under Free cap 2 | consistent with TierConfigurationSeeder property=2 |
| T11 | deploy + watched browser walk; `verify-m` skill path for /m | procedural, no code conflict |

Ruling 1: the form's state/validation/payload logic lives ONCE in `resources/mobile/utils/captureFormState.js` as an exported Vue mixin (`captureFormMixin`: props, emits, data, computed, methods). Both `FynCaptureForm.vue` files use `mixins: [captureFormMixin]` and carry only their template + styling; the web SFC imports it by relative path exactly as `FynQuickReplies.vue:58` imports `renderFynText` from the mobile bundle — the existing precedent for one-place-for-all-surfaces. Why: Rule 20 and the reviewer rubric both forbid a copied logic block. Cost if wrong: one cross-bundle import to unwind.

Ruling 2: Task 10's two template insertions (Dashboard.vue, MobileChrome.vue) stand — the behaviour is in the one component; the duplicated message loop is pre-existing debt, noted for the PR, not refactored here. Cost if wrong: a later extraction of the /m message loop.
Task 1: implemented (3b4e2e29d), review dispatched 17:51
Task 1: minor (deferred): implementer report says 0 mortgage = has_mortgage true; code uses > 0 (matches CoordinatingAgent) — report wording only
Task 1: minor (deferred): trailing-zero trimming duplicated between pounds() and the share formatting in summarise()
Task 1: minor (deferred): toolInputs() reads $answers['current_value'] without a null guard (request validation guarantees it)
Task 1: ⚠️ resolved by controller: rules() must validate the standalone answers array — Task 2's brief does exactly that (ValidatorFacade::make($form['answers'], CaptureForms::rules(...)))
Task 1: complete (commits 3b05703a2..3b4e2e29d, review clean)
Task 2: implemented (9444913a4), review dispatched 18:04
Task 2: review found Important (plan-mandated): CaptureForms::rules() 'present' on money_or_none applies to every kind, so a single-kind answers array 422s on the absent kind's mortgage field; the plan's claim that the 5th test goes green in Task 6 was wrong (FormRequest 422 happens before the controller).
Ruling 3: fix in SendAiChatMessageRequest::withValidator — restrict CaptureForms::rules($name) to the kinds present in form.answers before building the nested validator; CaptureForms::rules() unchanged (Task 1's tests pin its shape). The 5th test must pass in this task. Why: the rules are relative to a submitted kind by design; the filter is the one place that knows which kinds were submitted. Cost if wrong: a rule set that silently skips a kind the client mis-keyed — covered by the unknown-kind check that precedes it.
Task 2: fix round 1/5 (dispatched; commit 2dfd7d802) 18:17
Ruling 4: Tasks 3 and 4 execute as ONE dispatch. Why: Task 3 alone flips campaign_property to turn_type form, and until Task 4's dispatch change (`in_array(turn_type, ['delegated','form'])`) a typed sentence at that step would fall to the free-text parser, breaking PropertyCaptureLiveSentenceTest between the two commits; Task 4's own tests need Task 3's corpus change. Two commits inside one dispatch (corpus first, director second), one review over both. Checked: CaptureStateToolCoverageTest keeps campaign_property in DELEGATED_STATE_TOOLS (tool check still true) and its delegated-list test only enumerates turn_type 'delegated', so no change needed there. Cost if wrong: one larger review diff.
Task 2: fix round 1/5 (1 addressed, 0 open — rules filtered to submitted kinds; commits 9444913a4..2dfd7d802)
Task 2: complete (commits 3b4e2e29d..2dfd7d802 (+44ebe55af plan note), review clean)
Task 3+4: dispatched as one (Ruling 4), base 44ebe55af $(date +%H:%M)
Task 3+4: implemented (b47a8ab5d, 39b4731b7), review dispatched 18:33
Task 3+4: minor (deferred): no test for the `$schema === null` fallback in the form branch (OnboardingChatDirector ~1023)
Task 3+4: minor (deferred): global Pest helpers formStepUser/formConversation in PropertyCaptureFormTurnTest risk name collisions later
Task 3: complete (commit b47a8ab5d, review clean)
Task 4: complete (commit 39b4731b7, review clean)
Task 5: dispatched, base f6a358e7a 18:42
Task 5: implemented (4b3e942eb), review dispatched 18:59
Task 5 note: empty form.answers never reaches the director — 'form.answers' => required_with:form + array, and Laravel's required treats [] as missing (422 at the request).
Task 5: review found Important: empty toolInputs() (no recognised kind) falls through to capture_complete + advance with zero writes.
Ruling 5: guard it in handleFormTurn — when toolInputs() is empty, yield capture_form_errors with a form-level message and park (no advance), plus one test. Why: the request rejects that shape today, but the director must not depend on a caller's validation (defence at the write path, Rule 20 keeps it in the one form handler). Cost if wrong: one unreachable branch.
Task 5: minor (deferred): 'selection' => 'savetax' hardcoded in the failure-path recordProgress (brief-mandated; property form is Save Tax only)
Task 5: fix round 1/5 (dispatched re-review; commit a31df0697) 19:15
Task 5: fix round 1/5 (1 addressed, 0 open — empty-kind guard; commits 4b3e942eb..a31df0697)
Task 5: minor (deferred): the empty-inputs guard does not call recordProgress while the partial-failure branch does (nothing captured; asymmetry only)
Task 5: complete (commits f6a358e7a..a31df0697 (+c7216000a plan note), review clean)
SESSION END 2026-09-15 19:20 BST — CSJ: handover after Task 5. Next: Task 6 (controller), brief at task-6-brief.md; then 7-12. Branch pushed at c7216000a.
