# Final whole-branch review — Save Tax property capture form

**Range:** e4afd0d1c..1b8458089 (25 commits, 29 files, ~4,200 lines)
**Reviewer:** senior code review, read-only, four passes (PHP surface, corpus/state machine, web client, `/m` client + tests)
**Worktree:** `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/e477a7db-3e53-4b63-a5fb-544f1f34b78a/scratchpad/fix-joint` (clean, HEAD `1b8458089`)
**Verification run here:** `vendor/bin/pint --test` on all eight changed/added PHP files — passed. No Pest or Vitest run (a full run had just completed).

---

## Strengths

Genuinely good work, and the parts that are usually got wrong are right here.

- **One schema, one home.** `app/Services/Onboarding/CaptureForms.php:18` is a clean final class of plain data. Both renderers draw kinds, labels, types, options and the asterisk rule from it, so neither client knows the word "property". The canonical enums are in the schema itself (`CaptureForms.php:170-187`), not restated anywhere.
- **Rule 20 is actually satisfied server-side.** All four director entry points set the capability flag: `AiChatController.php:232` (send), `:450` (queued stream), `:842` (start), `:935` (action). I grepped every `onboardingDirector->` call — there is no fifth path. The Ruling 6 catch (the `continue` press on a resumed conversation) was the one real gap and it is closed and tested at `tests/Feature/AI/SendAiChatMessageFormRequestTest.php:121`.
- **One write path, no model.** `handleFormTurn` (`OnboardingChatDirector.php:3677`) goes through `CoordinatingAgent::executeTool('create_property', …)` per kind with the answers as the gate's confirmed facts, then hands off to the shared `advanceAfterCapture` extracted from the typed path (`:4356`). The extraction is faithful — the tail of `handleAssetCaptureTurn` is now one `yield from` and nothing was left behind.
- **The NOT NULL trap from #857 is respected.** `toolInputs()` omits unstated optionals rather than sending null (`CaptureForms.php:105-121`), and the unit test pins that with `->not->toHaveKey('ownership_percentage')`.
- **Ruling 1 (the shared mixin) is the right call and follows an existing precedent** — `FynQuickReplies.vue:58` already imports from the mobile bundle the same way. All state, validation and payload logic lives once in `resources/mobile/utils/captureFormState.js`; both SFCs carry template and styling only. A behaviour change is genuinely made once.
- **Tests verify real behaviour, not mocks.** `PropertyCaptureFormTurnTest` drives the director end to end and asserts actual `Property` rows, ownership share, tenure default, the tier-cap refusal keeping the landed record, and `onboarding_fyn_step` after the advance. `SendAiChatMessageFormRequestTest` drives real HTTP with and without the header on three endpoints. The `/m` spec covers the Ruling 13 reopen and the Ruling 9 answers lookup. I found no test that asserts nothing and none that shares the code's misconception, with one exception noted below (C1).
- **Palette and class discipline.** Every token used exists: `raspberry-50/500/600`, `horizon-500`, `light-gray`, `neutral-500` in `tailwind.config.js`; `--raspberry-50/600`, `--horizon-200`, `--neutral-500`, `--radius-md`, `--font-primary`, `.m-field`, `.m-btn` in `resources/mobile/style.css`. No hex in either `<style>`, no icons, no emoji, no scores, British copy throughout.
- **The corpus stays the data home.** `form:` is a new DATA key and `OnboardingWorkflowTable::fromProcedure` has no key allow-list, so it carries through `mergeTable` untouched (`OnboardingStateMachine.php:850-860`). No PHP copy of the prompt. The golden-master state-set check is unaffected.
- **The `clientSupportsForms` flag cannot leak.** `OnboardingChatDirector` has no `singleton`/`scoped` binding in `AppServiceProvider` (checked), so it is a transient resolved into a per-request controller. Under Octane non-singleton bindings are re-resolved per request, and no queue worker resolves the director. It is request-scoped in practice.

---

## Issues

### Critical

**C1. A client that cannot render forms is told to "fill in the boxes below and tap Save" — with no boxes. Native iOS is that client, and it points at production.**

- Where: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:168-171` (the prompt was rewritten), consumed by `app/Services/Onboarding/OnboardingChatDirector.php:1035` (form branch) and the `else` fallback at `:1119`.
- What: the step's single `prompt_text` was changed from the detailed typed instruction ("For each one: is it your home, a second home or a buy-to-let; roughly what it's worth; whether there's a mortgage and how much is left on it; and whether you own it individually or jointly? If jointly, who owns it with you and your share.") to form-shaped wording. A request without `X-Fynla-Forms: 1` falls to the content branch and streams that same new wording. `ios-native/Fynla/Features/Fyn/FynClient.swift:173,190,223` uses exactly these endpoints and sends no such header.
- Why it matters: the spec is explicit — §2 line 60, "A client without it (native today) gets the existing typed prompt for the same step, **so nothing changes for iOS**." It changed. A TestFlight user at the property step sees instructions referring to a control that does not exist, and the extractor behind the typed path loses the question that used to elicit value, mortgage and ownership — so the free-text capture gets materially worse on that surface. Both native schemes point at fynla.org, so this ships straight to real accounts.
- Also note the existing test cannot catch it: `tests/Feature/Onboarding/PropertyCaptureFormTurnTest.php:83` asserts only `toContain('Now your property')`, which is true of both wordings. It is the one test on this branch that shares the code's misconception.
- Fix: keep `prompt_text` as the old typed instruction and add a second corpus DATA key for the form wording (`form_prompt_text`), or add `typed_prompt_text` carrying the old words and use it in the non-form fallback. Then tighten the fallback test to assert the typed instruction's own words ("is it your home", "how much is left on it"), not the shared first three words.

### Important

**I1. The fact extractor runs on the machine-composed summary and parks a bogus annual income on every property form submission.**

- Where: `app/Services/Onboarding/OnboardingChatDirector.php:196` (`$this->factExtractor->extractAndPark($conversation, $message)`), reached before the form branch at `:220`.
- What: for a form turn, `$message` is `CaptureForms::summarise()` — e.g. "Home worth £750,000, mortgage £325,000, joint, my share 50%. …". `OnboardingFactExtractor::extractEmployment` (`:349`) fires on any `£` figure and calls `OnboardingValueInterpreter::parseIncomeAmount`, whose Pattern 1 (`OnboardingValueInterpreter.php`, `parseMoney`) matches the first `£` amount and returns 750000.0, inside the 0–99,999,999 band. That parks `employment.annual_income = 750000` into `ai_conversations.onboarding_parked_facts`.
- Why it matters: `MemoryRetrieverService::fromParkedFacts` (`:151`) flattens that bucket into the fact layer the model reads, so Fyn can later narrate a property value as the user's salary. The spec forbids it in terms — §4 line 88: "No model call and **no extractor** on this path." The extractor itself is deterministic (no model call, so that half of the claim holds), and the same flaw could fire on a typed property sentence today, but this branch composes the sentence itself and therefore triggers it on every single submission rather than occasionally.
- Fix: one line — skip the extractor when `$form !== null`, e.g. wrap `:196-204` in `if ($form === null) { … }`. Add a test asserting `onboarding_parked_facts` is untouched after a form turn.

**I2. A refused form cannot be corrected after a page reload — on either surface.**

- Where: web `resources/js/components/Shared/AiChatPanel.vue:1274-1280` (`isCaptureFormOpen`); `/m` `resources/mobile/mixins/onboardingChat.js:305-315` (the transcript mapper's `i < mapped.length - 1` lock).
- What: Ruling 13 reopens a refused form on the live turn, and that works. But the reopen lives only in client memory. After a reload the transcript is `[form turn, user row, assistant error row]`; web sees a user row after the form index and locks it (history sets `errors: null`), `/m` sees the form row is not last and locks it. The step is still parked at `campaign_property`, so the user is looking at a locked, uneditable form for a step that still wants an answer.
- Why it matters: spec §4 item 4 — "the step stays parked and the form stays open with the values intact". The tier-cap refusal at the Free limit of two properties is the most likely refusal in production, and a reload is a normal thing to do when a screen shows an error. The user is not hard-blocked (typing a sentence still works via the delegated path) but the feature's own affordance is gone.
- Fix: the director already persists the signal — the failure assistant row carries `metadata.capture_write_failed => true` (`OnboardingChatDirector.php:3712`). In both history mappers, reopen the newest form when the newest assistant row carries `capture_write_failed`. Consider persisting the error payload there too so the field messages survive as well.

**I3. Spec §6 says a form posted at a non-form state returns 422; the branch returns a friendly message instead, and no ruling covers the change.**

- Where: `app/Services/Onboarding/OnboardingChatDirector.php:220-231`, tested at `PropertyCaptureFormTurnTest.php:188`.
- What: spec §4 line 88 and the §6 table both say 422. The implementation yields "That form is no longer open — let's carry on from where we are." and re-emits the current step.
- Why it matters: I think the implemented behaviour is *better* (a second tab or a late tap should not throw an error at the user), so I am not asking for the 422. But the spec is the binding authority on this branch and every other deviation got a numbered ruling. This one silently differs, which is exactly what the ledger exists to prevent.
- Fix: add a ruling recording the decision and its reasoning, and correct the spec's §6 row. No code change.

**I4. Test gap: the web half of Ruling 13's lock rules has no test.**

- Where: `resources/js/components/Shared/AiChatPanel.vue:1267-1286` — `latestCaptureFormIndex`, `isCaptureFormOpen`, `captureFormValues`, `handleCaptureFormSubmit`.
- What: `/m` has five tests over its equivalent logic (`resources/mobile/mixins/__tests__/onboardingChat.spec.js:282-390`). The web store tests cover the SSE routing and the history split, but nothing exercises the panel's open/locked computation: that only the newest form is open, that a following user row locks it, that non-null errors reopen it, and that `captureFormValues` finds the answers on the later user row.
- Why it matters: the two surfaces use *different* implementations of the same rule (index-based on web, position-based on `/m`), and I2 above is precisely a place where they drift. The untested one is the one CSJ walks first.
- Fix: one Vitest file mounting `AiChatPanel` (or unit-testing the three methods against a messages fixture) covering those four cases.

**I5. The Save Tax walk no longer asks about a second home anywhere.**

- Where: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md:171`; the form's kinds at `app/Services/Onboarding/CaptureForms.php:170-173`.
- What: the old prompt explicitly invited "is it your home, a second home or a buy-to-let". The form offers Home and Buy to let only (spec §"What the user sees"), and the new prompt names only those two. `secondary_residence` is a live enum — the tier-cap test itself seeds one (`PropertyCaptureFormTurnTest.php:162`) — and it is still capturable by typing, but nothing prompts for it on any surface now.
- Why it matters: a user with a second home silently finishes onboarding with it missing, and the verify page will show nothing for it. This follows the spec, so it is CSJ's call rather than an implementation defect — but it is a real capture regression against the step as it shipped yesterday, and it should be a conscious decision rather than a side effect.
- Fix: either add a third kind (`secondary_residence`, same fields as Home) to the schema, or keep the two kinds and restore "a second home" to the typed instruction that C1's fix reinstates, so the sentence path still invites it.

### Minor

1. **Locked money fields show raw digits, not currency.** `resources/js/components/Fyn/FynCaptureForm.vue:26-37` and `resources/mobile/components/FynCaptureForm.vue:26-37` render a disabled `<input type="number">` after save, so a saved home reads `750000`, not `£750,000`. Spec line 21 and the plan's Global Constraints both say "currency via the existing helper". Fix: when `locked`, render the value as formatted text instead of a disabled input.
2. **"tap Save" on the desktop web surface.** Same string, both surfaces (corpus `:171`). Use a device-neutral verb ("choose Save").
3. **The open-kind highlight may not render on web.** `FynCaptureForm.vue:8-9` applies `bg-white` statically and `bg-raspberry-50` conditionally; both are single-class utilities, so stylesheet order decides. Use a ternary: `:class="isOpen(kind.key) ? 'bg-raspberry-50' : 'bg-white'"`. (`/m` is fine — `.md-fyn__form-kind--open` is declared after the base rule.)
4. **Property-specific copy in schema-agnostic code.** The placeholder "Saving your property details…" is hardcoded in both generic send paths (`resources/js/store/modules/aiChat.js:541`, `resources/mobile/mixins/onboardingChat.js:640`). A second form type inherits the wrong words. Carry a `saving_label` on the schema, or use neutral wording.
5. **A FormRequest 422 surfaces as the generic error banner.** The client's `isValid` (`captureFormState.js:32-41`) does not mirror the server's `min:0` / `max:999999999.99` / `min:0.01` rules, so a negative or oversized number typed past the browser's `min` attribute produces "Chat request failed: 422" (`aiChatService.js:123`) rather than a field error, and the placeholder user row is left in the transcript. Low likelihood, ugly outcome.
6. **`emitTurnForState` was widened to `public` (`OnboardingChatDirector.php:983`) with no non-test caller.** Every production call site is internal. Either keep it private and drive the two tests through `handleAction`/`emitFirstTurn`, or note in the docblock that it is public for the form-turn tests.
7. **The form branch ignores `skip_link`.** `OnboardingChatDirector.php:1035-1057` returns before every other branch's skip-link handling, and neither the event nor the persisted metadata carries it — though spec §2 line 58 lists `skip_link?` in the event shape. `campaign_property` declares none today, so nothing is lost now; the next form-capable step that wants one will lose it silently.
8. **`BUBBLE_BREAK` markers are unhandled in the form branch.** Every other content path splits on them (`:1113-1125`). A future form prompt with a break renders the marker literally.
9. **Web component path differs from the spec.** Spec §5 names `resources/js/components/Shared/FynCaptureForm.vue`; it landed at `resources/js/components/Fyn/FynCaptureForm.vue`. The new location is better (it sits beside `FynQuickReplies.vue`) — just worth recording.
10. **The two Vitest component specs are near-identical copies** (`tests/frontend/components/Fyn/FynCaptureForm.test.js` and `resources/mobile/components/__tests__/FynCaptureForm.spec.js`). Justified — each proves its own template — but the duplicated 18-line schema fixture will drift.
11. **`cf_` + `Date.now()` row ids** (`aiChat.js:41`) collide if two form events ever land in the same millisecond. Not reachable today (one form per turn).
12. **Two test titles claim a header they never assert.** `tests/frontend/store/aiChatCaptureForm.test.js:45` and `resources/mobile/mixins/__tests__/onboardingChat.spec.js:293` both say "with the forms header"; the service/`apiStream` is mocked, so only the body is checked. The header is covered server-side, so this is a naming fix.
13. **A resumed conversation renders an empty locked form above the live one.** The transcript re-renders the persisted form turn (locked, no answers — two dead buttons and nothing else) and then the `continue` press emits a fresh one. It mirrors how a re-emitted bubbles turn already behaves, but an empty locked form reads worse than locked bubbles. Consider skipping a locked form that has no answers.
14. **The `_form` error key is rendered by neither template.** `OnboardingChatDirector.php:3697` keys the empty-form message under `_form`; both templates only iterate kinds. Unreachable via HTTP (the request rejects empty `answers`) and the same text also arrives as a `content` event, so the user does see it.
15. **`<label for>` on choice fields points at no element.** `FynCaptureForm.vue:21` (both surfaces) emits `for="fyn-form-<kind>-ownership_type"` while the radios carry only `name`. Drop the `for` for `choice`, or give each radio an id.
16. **`/m` kind heading reuses the field-label class.** `resources/mobile/components/FynCaptureForm.vue:17` uses `.md-fyn__form-label` for both the kind heading and the field labels, so they look identical; web styles the heading distinctly.
17. **Global Pest helper names are collision-prone.** `formStepUser`, `formConversation`, `propertyAnswers` at file scope in `PropertyCaptureFormTurnTest.php:29,43,98`, plus `formStepHttpUser` and `postForm` in the request test. No collision exists today (I checked every `^function ` in `tests/`), and a future duplicate is a fatal error, not a failure.
18. **`toolInputs()` casts `current_value` with no guard** (`CaptureForms.php:105`). I traced it: `answers: {main_residence: []}` is caught by the `present` rule on the mortgage field, a scalar kind is caught by `required_with`, and an explicit null is caught too — so it is unreachable through the endpoint. Still a one-character fix (`?? 0`) on a method that is public API.
19. **`'savetax'` is hardcoded on both form paths** (`OnboardingChatDirector.php:3721` and the `advanceAfterCapture` call at `:3765`) while the typed path computes `$selection`. Correct today — `campaignSections('pensioncheck')` has no property section — and it becomes wrong the moment one does.

---

## Deferred-minor triage

One line per `minor (deferred)` in the ledger.

| Ledger minor | Verdict |
|---|---|
| T1: implementer report says 0 mortgage = `has_mortgage` true; code uses `> 0` | Ship — report wording only; the code is right and matches `CoordinatingAgent`. |
| T1: trailing-zero trimming duplicated between `pounds()` and the share format | Ship — two lines, same file, both correct (the decimal point stops `rtrim`). |
| T1: `toolInputs()` reads `current_value` with no null guard | Ship — traced unreachable through the FormRequest; see Minor 18. |
| T3+4: no test for the `$schema === null` fallback in the form branch | Ship — defensive branch, unreachable while the corpus and `CaptureForms` agree. |
| T3+4: global Pest helpers risk name collisions | Ship — verified no collision exists in `tests/` today; see Minor 17. |
| T5: `'selection' => 'savetax'` hardcoded in the failure-path `recordProgress` | Ship — correct today; see Minor 19, which also covers the success path. |
| T5: the empty-inputs guard does not `recordProgress` while the partial-failure branch does | Ship — nothing was captured, so there is nothing to record. Asymmetry only. |
| T6: no test for the queued round trip (`enqueue` metadata.form → `streamQueuedMessage` reads it back) | Ship, but it is the strongest candidate of the set. The path is real code (`ConcurrentTurnQueue.php:78-87`, `AiChatController.php:448-450`) reachable from a cross-surface double-send; a wrong metadata read would silently drop the form and run the typed path over the summary. One feature test would close it. |
| T6: `setClientSupportsForms` called on the queued branch where the director is not invoked | Ship — harmless. |
| T7: the three-case block duplicated across the four SSE switches | Ship — matches the file's existing convention for `onboarding_advance`/`capture_complete`. |
| T7: no test for the streaming-text flush before the `capture_form` row | Ship — the flush mirrors `quick_replies`, which is covered. |
| T7: `capture_form` row ids differ live (`cf_<timestamp>`) vs history (`cf_<message id>`) | Ship — see Minor 11. |
| T8/T10: the `_form` error key is shown by neither renderer | Ship — unreachable, and the same text arrives as a `content` event; see Minor 14. |
| T8: no watcher on `values`/`locked` (Ruling 11) | Ship — I verified the key stability the ruling depends on: `AiChatPanel.vue:179` keys by `msg.id`, `/m` keys by index and only appends, so the instance is never remounted mid-turn and keeps its local answers. |
| T8: `setChoice()` hardcodes `ownership_percentage` | Ship — see Minor 4's sibling concern; correct while `property` is the only schema. |
| T8: no tests for reopening a closed kind, unticking No mortgage, joint→individual→joint | Ship — I hand-traced all three through the mixin and found no defect (the share is deleted on switch-away and re-seeded to 50 on switch-back; unticking deletes the key and disables Save). Worth adding, not worth blocking. |
| T8: choice `<label for>` points at no input id | Ship — see Minor 15. |
| T9: history forward scan for answers is not bounded at the next form row | Ship — safe under the single-open-form invariant. |
| T9: `capture_form_errors` ignores `ev.form` and targets the latest form row | Ship — same invariant. |
| T9: `streamNextQueued`'s `form_received` no-op never clears a prior refusal's errors | Ship — a form submit cannot be queued while the form is disabled mid-stream. |
| T10: the `/m` kind heading reuses `.md-fyn__form-label` | Ship — see Minor 16. |

**None of the deferred minors must be fixed before merge.** The pre-merge work is C1, I1, I2 and (as a ledger entry, not code) I3.

---

## Rulings disputed

None. Rulings 1–13 are all defensible and I verified the load-bearing ones rather than taking them on trust:

- **Ruling 1** (shared mixin): correct, and the cross-bundle import precedent at `FynQuickReplies.vue:58` is real.
- **Ruling 3** (filter rules to submitted kinds): correct — I traced `strtok`-based filtering in `SendAiChatMessageRequest.php:59-63` against a single-kind payload and it behaves as claimed.
- **Ruling 6** (extend Task 6 to the action endpoint): this was the important catch. Without it the resume path CSJ walks would have shown the typed prompt on web.
- **Ruling 11** (no watcher): holds, for the key-stability reason above — but it holds *because* of a fact the ruling does not state. Worth a sentence in the mixin comment: if either host ever keys its message loop by content, a locked form starts rendering empty.
- **Ruling 13** (reopen a refused form): correct for the live turn. It is incomplete across a reload — that is I2, an extension of the ruling rather than a dispute with it.

One qualification on **Ruling 2** (the `/m` message-loop duplication stays): agreed for this PR, and worth noting that this branch added a third insertion point to that duplicate (the `FynCaptureForm` block now exists in both `Dashboard.vue:335` and `MobileChrome.vue:155`). The debt got slightly more expensive; the decision to defer it is still right.

---

## Recommendations

1. Fix **C1** before merge. It is the only finding that would reach a real user as broken functionality, and it ships to production via TestFlight. Tighten the fallback test's assertion at the same time so the next prompt edit cannot slip past it.
2. Fix **I1** with the one-line extractor guard, and assert `onboarding_parked_facts` stays empty after a form turn. The spec asked for it and the current behaviour writes a house price into the model's income memory.
3. Fix **I2** using the `capture_write_failed` metadata that is already persisted. Do both surfaces in one change (Rule 20) — the divergent open/locked implementations are exactly where a one-surface fix would rot.
4. Record **I3** as a ruling and correct the spec's §6 row, so the ledger stays a complete account of the deviations.
5. Add the web panel lock tests (**I4**) and, if cheap, the queued round-trip feature test. Both are small and both cover paths CSJ will hit in the browser walk.
6. Decide **I5** (second home) explicitly before the Task 11 walk, since the answer changes what "correct" looks like on the verify page.
7. Production readiness, verified: no migration; the corpus file `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` must deploy alongside the PHP or `campaign_property` reverts to the shipped copy's state set (`OnboardingStateMachine::transitionTable` throws if the state sets disagree, so a partial deploy fails loudly rather than silently); no version bump is needed (`version: 1`, `effective_from: 2026-06-02` both unchanged); both bundles need rebuilding through `./deploy/csjones-fynla/build.sh`; the typed path at the step is untouched (`OnboardingChatDirector.php:416` now admits `'form'` alongside `'delegated'`, and that is the only `turn_type === 'delegated'` check in `app/`).

---

## Assessment

**Ready to merge? With fixes.**

The architecture is right, Rule 20 is genuinely satisfied on every server path and both client bundles, and the tests verify real behaviour rather than mocks. One Critical must land first — the rewritten step prompt reaches native iOS, which cannot render the form and is pointed at production — along with the one-line extractor guard the spec already required.
