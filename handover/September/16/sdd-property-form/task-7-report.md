# Task 7 report: Web store and API — the form event, the form submission, history

## Fix report — review round 1

### Finding 1 (Important, fixed): errors trip the empty-response banner

The director always emits `form_received` before `capture_form_errors` on a refused
form submission (`OnboardingChatDirector.php:3690` then `:3697`/`:3740`). Neither
event calls `ADD_MESSAGE` — `SET_TEMP_USER_CONTENT` rewrites the temp row in place
and `SET_CAPTURE_FORM_ERRORS` mutates the existing form row's metadata — so
`sendMessage`'s finally-block empty-response guard (`resources/js/store/modules/aiChat.js`,
was ~:963-973, now ~:968-983) saw no new message, no `streamingText`, and no
`state.error`, and committed `"Fyn couldn't generate a response…"` on top of the
correctly rendered form errors.

**What changed:**
- Added `let formErrorsReceived = false;` next to the existing `authExpired` flag
  in `sendMessage` (`resources/js/store/modules/aiChat.js:558-565`).
- The `capture_form_errors` case in `sendMessage`'s switch now sets
  `formErrorsReceived = true` after committing `SET_CAPTURE_FORM_ERRORS`
  (`:746-754`) — the errors are Fyn's reply to the turn.
- The finally block's guard now reads
  `const producedNewMessages = state.messages.length > preStreamMessageCount || formErrorsReceived;`
  (`:975`), so a refused form submission is treated as answered.

**Checked the other three switches** (`streamNextQueued`, `postAction`,
`startOnboardingConversation`) for an equivalent empty-response guard — none
exists; the "Fyn couldn't generate a response" banner and `preStreamMessageCount`
snapshot are unique to `sendMessage`. No parallel fix needed elsewhere.

**Covering test:** extended the third test in
`tests/frontend/store/aiChatCaptureForm.test.js` ("attaches capture_form_errors to
the latest form row and never trips the empty-response banner") to stream the real
`form_received` → `capture_form_errors` → `done` sequence and assert
`ctx.state.error` is `null` afterwards.

Verified the test actually catches the regression: temporarily reverted the
`producedNewMessages` line to drop `|| formErrorsReceived` and re-ran the file —
the new assertion failed with exactly the reported banner text
(`AssertionError: expected 'Fyn couldn't generate a response. Th…' to be null`),
while the other three tests stayed green. Restored the fix and re-ran to confirm
GREEN again.

**Command + output (after the fix restored):**
```
$ npx vitest run tests/frontend/store
 ✓ tests/frontend/store/aiChatCaptureForm.test.js (4 tests) 13ms
 ✓ tests/frontend/store/aiChatAuthExpiry.test.js (5 tests) 13ms
 ✓ tests/frontend/store/aiChatEvents.test.js (4 tests) 13ms
 ✓ tests/frontend/store/savingsIsaAllowance.test.js (6 tests) 11ms
 ✓ tests/frontend/store/investmentHoldings.test.js (4 tests) 8ms
 ✓ tests/frontend/store/savingsEmergencyFundGetters.test.js (10 tests) 6ms

 Test Files  6 passed (6)
      Tests  33 passed (33)
```

Also re-ran the full frontend suite for regressions: `npx vitest run tests/frontend`
→ **830 tests passed, 75 files passed**. `npx eslint resources/js/store/modules/aiChat.js
tests/frontend/store/aiChatCaptureForm.test.js` and `bash scripts/quality/policy-lint.sh`
both clean (no output).

### Finding 2 (ruled no change, Ruling 8): placeholder allegedly stuck

No code change — `form_received` always precedes the errors on every live path, so
the "Saving your property details…" placeholder is already rewritten to the
server's summary line before the errors arrive. Confirmed nothing to do here.

### Fix commit

`414350f70 fix(web): a refused form submission no longer trips the empty-response banner`

---

## What I implemented

### `resources/js/services/aiChatService.js`
- Added `FORMS_HEADER = { 'X-Fynla-Forms': '1' }` constant after the imports.
- `sendMessageStream(conversationId, message, currentRoute, { signal, form = null })` now builds the body as `{ current_route }` plus either `form` (when given) or `message` — `message` is omitted from the body on a form submission.
- Added `...FORMS_HEADER` to the `headers` object in all four SSE `fetch()` calls: `sendMessageStream`, `streamQueuedMessage`, `startOnboardingStream`, `postActionStream`.

### `resources/js/store/modules/aiChat.js`
- New builder `captureFormMessage(event)` next to `entityWriteMessage`, producing `{ id: 'cf_'+Date.now(), role: 'capture_form', content: prompt_text, metadata: { capture_form: form, errors: null }, created_at }`.
- New mutations next to `ADD_MESSAGE`:
  - `SET_TEMP_USER_CONTENT(state, { id, content })` — rewrites a message's content in place (used to replace the "Saving your property details…" placeholder with the server's `form_received` summary).
  - `SET_CAPTURE_FORM_ERRORS(state, errors)` — walks `state.messages` from the end and merges `errors` into the metadata of the most recent `capture_form` row.
- `loadConversation` history normalisation: before the existing `hasBubbles` split, a persisted assistant row carrying `metadata.capture_form` (an object) is now split into a plain assistant row (only if it has text) plus a separate `capture_form` row carrying the schema — mirrors the bubbles split. Used `continue` (the loop is `for (const m of raw)`, not a `forEach`) to skip the rest of that iteration.
- `sendMessage({ commit, dispatch, state, rootState }, arg)` — `arg` may be a string (unchanged behaviour) or `{ form }`. Derives `form` and `message` from `arg`; the optimistic user bubble shows a placeholder ("Saving your property details…") when it's a form submission, otherwise the existing `stripTags(message)` path. Passes `form` through to `aiChatService.sendMessageStream(..., { signal, form })`.
- Added `case 'form_received'`, `case 'capture_form'`, `case 'capture_form_errors'` to all four SSE event switches:
  - `sendMessage`'s switch (next to `quick_replies`) — `form_received` rewrites the placeholder via `SET_TEMP_USER_CONTENT` using the closure's `tempId`; `capture_form` flushes any streaming text first, then commits a `captureFormMessage` row; `capture_form_errors` commits `SET_CAPTURE_FORM_ERRORS`.
  - `streamNextQueued`'s switch (no `quick_replies` case there; inserted next to `action`/before `capture_complete`, ahead of its `default:` clause) — `form_received` is a no-op (`break;`, no placeholder row exists on this path), `capture_form` and `capture_form_errors` behave the same as above.
  - `postAction`'s switch (next to `quick_replies`) — same pattern, `form_received` a no-op.
  - `startOnboardingConversation`'s switch (next to `quick_replies`) — same pattern, `form_received` a no-op.

## What I tested and results

### TDD evidence

**RED** — `npx vitest run tests/frontend/store/aiChatCaptureForm.test.js` (before implementation):
```
 FAIL  tests/frontend/store/aiChatCaptureForm.test.js > capture form in the chat store > renders a capture_form event as a form row and never trips the empty-response banner
AssertionError: expected undefined to be truthy
 FAIL  tests/frontend/store/aiChatCaptureForm.test.js > capture form in the chat store > posts a form answer with the forms header and no message, then replaces the placeholder user row
AssertionError: expected "spy" to be called with arguments: [ 7, null, Anything, … ]
 FAIL  tests/frontend/store/aiChatCaptureForm.test.js > capture form in the chat store > attaches capture_form_errors to the latest form row
TypeError: Cannot read properties of null (reading 'buy_to_let')
 FAIL  tests/frontend/store/aiChatCaptureForm.test.js > capture form in the chat store > re-renders a persisted form on history load as text plus a form row
AssertionError: expected [ 'assistant' ] to deeply equal [ 'assistant', 'capture_form' ]

 Test Files  1 failed (1)
      Tests  4 failed (4)
```

**GREEN** — `npx vitest run tests/frontend/store/aiChatCaptureForm.test.js` (after implementation):
```
 ✓ tests/frontend/store/aiChatCaptureForm.test.js (4 tests) 11ms

 Test Files  1 passed (1)
      Tests  4 passed (4)
```

### Full store suite

`npx vitest run tests/frontend/store`:
```
 ✓ tests/frontend/store/investmentHoldings.test.js (4 tests)
 ✓ tests/frontend/store/savingsIsaAllowance.test.js (6 tests)
 ✓ tests/frontend/store/aiChatEvents.test.js (4 tests)
 ✓ tests/frontend/store/aiChatAuthExpiry.test.js (5 tests)
 ✓ tests/frontend/store/savingsEmergencyFundGetters.test.js (10 tests)
 ✓ tests/frontend/store/aiChatCaptureForm.test.js (4 tests)

 Test Files  6 passed (6)
      Tests  33 passed (33)
```

### Wider verification (beyond the brief's Step 4, to catch regressions in files this task touches)

`npx vitest run tests/frontend/services/aiChatServiceAuthExpiry.test.js` — 6 passed (this test covers the same four `fetch()` calls I added the header to).

`npx vitest run tests/frontend` (whole frontend suite) — **830 tests passed, 75 files passed**, no regressions.

`npx eslint resources/js/services/aiChatService.js resources/js/store/modules/aiChat.js tests/frontend/store/aiChatCaptureForm.test.js` — no output (clean).

`bash scripts/quality/policy-lint.sh` — no output (clean; no hex colours, no banned patterns introduced).

## Files changed

- `resources/js/services/aiChatService.js`
- `resources/js/store/modules/aiChat.js`
- `tests/frontend/store/aiChatCaptureForm.test.js` (new)

## TDD note: deviations from the brief's literal Step-1 test text

The brief's Step 1 test used `aiChat.state()` in `makeCtx`, but the actual module exports `state` as a plain object, not a factory function (confirmed: `resources/js/store/modules/aiChat.js:49` `const state = { ... }`, and the sibling `aiChatEvents.test.js` spreads `aiChat.state` directly, not `aiChat.state()`). I used `{ ...aiChat.state, ... }` to match the real export and the sibling test's pattern.

The brief's Step-1 `getConversation` mock shape (`{ data: { data: { id: 7, messages: [...] } } }`) does not match how `loadConversation` actually reads the response (`response.data.conversation` and `response.data.messages` — confirmed against `app/Http/Controllers/Api/AiChatController.php:129-135`, which wraps the payload as `{ success, data: { conversation, messages } }`). I corrected the mock to `{ data: { conversation: { id: 7 }, messages: [...] } }` so the test actually exercises the real code path instead of accidentally passing against an empty `raw = []`. The brief's own instruction covers this: "the assertions are what matter."

Everything else — the event/mutation/action code in Step 3 — was implemented verbatim as specified.

## Self-review findings

- **Completeness**: all four switches carry `form_received`/`capture_form`/`capture_form_errors`; the header is on all four SSE fetches (verified by grep); history normalisation handles the persisted capture_form split with `continue` (correct for the `for...of` loop, not `forEach`).
- **Quality**: mirrors the existing `entityWriteMessage`/`quick_replies` patterns exactly; no new dependencies; British spelling in comments; no emoji/Unicode-as-icons introduced (the pre-existing `→` in the untouched `onboarding_advance` comment predates this change).
- **Discipline (YAGNI)**: added exactly what the brief specified — no extra mutations, no extra getters, no premature abstraction for the renderer (Task 8) or `/m` (Tasks 9–10).
- **Testing**: all four new tests exercise real behaviour (mutations run through the same `commit` wrapper the app uses, not a mock), and TDD RED→GREEN evidence is captured above. Output is pristine (no console noise from the new test file; the `stderr` lines in the full-suite run are pre-existing expected-error logging from unrelated tests, not from this file).

Fixed nothing further after self-review — no issues found.

## Issues or concerns

None. One thing to flag forward (not a defect in this task): the `sendMessage` head places `form` before `message` derivation in a way that assumes `arg.form` is only ever set together with a `null`/absent message — this matches the brief's one-send-path contract and is what Task 8's renderer will call (`sendMessage({ form })`), but Task 8 should not also pass a `message` alongside `form` since the store silently ignores it (`message = form ? null : arg`, so a string `arg` and an object `arg` are mutually exclusive by construction).
