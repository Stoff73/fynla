# Task 9 report — `/m` transport and mixin

## What I implemented

1. **`resources/mobile/api.js`** — `apiStream` now sends `'X-Fynla-Forms': '1'` on every `/m` SSE request (added after the `Accept: text/event-stream` header, line 108). The GET/POST/PUT/DELETE helpers are untouched.

2. **`resources/mobile/mixins/onboardingChat.js` — `handleFynEvent`** — added three branches immediately before the `quick_replies` branch, exactly per the brief:
   - `form_received` — finds the placeholder user row (`role: 'user'`, `formPlaceholder: true`) and fills in its real text from `ev.text`, then clears the flag.
   - `capture_form` — opens a fresh Fyn row if the cursor already carries streamed text (mirrors the `quick_replies` split), sets `cursor.got = true`, sets `cursor.reply.text` from `ev.prompt_text`, and attaches `cursor.reply.form = { schema: ev.form || null, errors: null, answers: null, locked: false }`.
   - `capture_form_errors` — finds the latest message carrying a `.form` and merges `errors` into it.

3. **`send`** — signature is now `send(preset, form = null)` (the mixin's real method takes a single `preset` argument, not the brief's `text`/positional name — I kept the existing parameter name and only added `form` as the second parameter, per "never restructure `send`; split only what the brief says"). When `form` is passed:
   - the guard becomes `(!form && !text) || this.sending`
   - every earlier message's bubbles are cleared as before, and every earlier `.form` is now also locked (`{ ...m.form, locked: true }`)
   - the pushed user row is `{ role: 'user', text: 'Saving your property details…', bubbles: [], formPlaceholder: true }` instead of the typed text
   - the request body carries `form` instead of `message` (`if (form) body.form = form; else body.message = text;`)
   - everything downstream (ensureConversation, apiStream call, 202-queue handling, finalizeCaptureReply, navigation, level-up) is untouched.

4. **`submitCaptureForm(form)`** — added right after `send`, returns `this.send(null, form)`, the one send path.

5. **`loadTranscript` → `loadConversationTranscript`** (see Ruling/decision below) — the mapping now:
   - records `form: { schema: captureForm, errors: null, answers: null, locked: false }` on a row whose `metadata.capture_form` is an object
   - **per Ruling 9**: walks forward through the raw server message list from each form row and takes `answers` from the first later row with `role === 'user'` and `metadata.form.answers` (not `metadata.form_answers` off the assistant row, which does not exist)
   - locks every form row except the last message's, mirroring the existing bubble-locking line

## A naming/signature decision I made and want flagged

The brief's anchor list and Step 3 both name the method `loadConversationTranscript`, and the brief's own test (`w.vm.conversationId = 7; await w.vm.loadConversationTranscript();`) calls it with **zero arguments** after setting `conversationId` directly. The actual method on this branch is `loadTranscript(conversationId)` — a required parameter, no method called `loadConversationTranscript` exists anywhere (checked `resources/js/` too — the web side uses a Vuex store with a different `capture_form` row shape, not this method name).

I checked every call site (`createContextualConversation`, `openConversation`, `retryTranscript`, `resumeOnboardingInDock`) and confirmed each already sets `this.conversationId` to the exact same value it was about to pass as the `conversationId` argument. So I renamed `loadTranscript` → `loadConversationTranscript`, dropped the parameter, and had it read `this.conversationId` directly — a behaviour-preserving rename, not a new wrapper method (avoids carrying two names for one job). I updated all 4 internal call sites accordingly.

**This rename had one blast-radius I had to chase down and fix**: `tests/frontend/mobile/onboardingChatEvents.test.js:349` called `onboardingChat.methods.loadTranscript.call(vm, 7)` directly (a different, older test file under `tests/frontend/mobile/`, not the mixin's own `__tests__` folder — found by a repo-wide grep, not by the two commands the brief specifies). I updated it to `vm.conversationId = 7` + `onboardingChat.methods.loadConversationTranscript.call(vm)` and re-verified it passes. Full `npx vitest run` (not just the two scoped commands) was run to catch this — see below.

If this rename is unwanted, the fallback is trivial: revert the method name/signature to `loadTranscript(conversationId)` and instead add a thin `loadConversationTranscript() { return this.loadTranscript(this.conversationId); }` wrapper so the brief's test still passes; I chose the cleaner single-name option since nothing else in the repo depended on the old name after the one test fix.

## What I tested and results

**TDD — RED** (implementation reverted via `git checkout`, only the test file present):
```
npx vitest run resources/mobile/mixins
```
Result: 5 new tests failed as expected (`expected false to be true`; `submitCaptureForm is not a function`; `Cannot read properties of null (reading 'main_residence')`; `loadConversationTranscript is not a function` ×2), 13 pre-existing tests passed.

**TDD — GREEN** (implementation reapplied):
```
npx vitest run resources/mobile/mixins
```
Result: `Test Files 1 passed (1)` / `Tests 18 passed (18)`.

**Regression sweep**:
- `npx vitest run resources/mobile` → `Test Files 34 passed (34)` / `Tests 202 passed (202)`.
- `npx vitest run tests/frontend/mobile/onboardingChatEvents.test.js` → found the `loadTranscript` regression described above, fixed it, re-ran → `Test Files 1 passed (1)` / `Tests 18 passed (18)`.
- Full `npx vitest run` (whole repo, no path filter) → `Test Files 145 passed (145)` / `Tests 1343 passed (1343)`. Confirms the rename's only blast radius was the one file above (also confirmed via `grep -rn "loadTranscript\b"` across the repo excluding `node_modules`, which otherwise turns up only doc mentions in `codex/`/`July/` archives).
- `npx eslint resources/mobile/api.js resources/mobile/mixins/onboardingChat.js resources/mobile/mixins/__tests__/onboardingChat.spec.js` → no output, clean.

## Files changed

- `resources/mobile/api.js`
- `resources/mobile/mixins/onboardingChat.js`
- `resources/mobile/mixins/__tests__/onboardingChat.spec.js` (brief's 4 tests + 1 extra per Ruling 9's suggestion, see below)
- `tests/frontend/mobile/onboardingChatEvents.test.js` (regression fix from the rename, not in the brief's file list)

## Test additions beyond the brief's 4

Per Ruling 9's own suggestion ("adjust the brief's fourth test only if needed… e.g. add a following user row…"), I kept the brief's fourth test verbatim (it still passes unchanged — no later row, so `answers` stays `null` and the row is still `locked: false` as the only/last message) and added a fifth test: a persisted form row followed by a user row carrying `metadata.form.answers`, asserting the form row's `answers` match and `locked` is `true` (no longer the last row).

## Self-review

- Completeness: header ✓ (`api.js:108`); three events ✓ (`form_received`, `capture_form`, `capture_form_errors`); `send` split ✓; `submitCaptureForm` ✓; history mapping with Ruling 9's forward-lookup ✓; lock-all-but-last rule ✓ (both in `send` for live forms and in the transcript mapping for persisted ones).
- Quality: comments explain the "why" at each new branch (mirrors existing style, e.g. the `capture_form` comment references the `quick_replies` pattern it copies). No hex colours, no icons, no emoji, British copy in the placeholder string ("Saving your property details…").
- Discipline (YAGNI): did not touch `chooseBubble`, `streamQueuedReply`, `appendFynText`, or any other method beyond what the brief and Ruling 9 required. Did not build the `/m` renderer component (Task 10, out of scope).
- Testing: all 5 new tests exercise real mixin behaviour through `mount(Host)` and direct `handleFynEvent`/`submitCaptureForm`/`loadConversationTranscript` calls, per the brief's own pattern; no Vue warnings in the output; RED confirmed before GREEN.

## Issues or concerns

- The `loadTranscript` → `loadConversationTranscript` rename (see above) is the one place I deviated from a literal "modify in place" reading of the brief, because the brief's own anchor list and test both name the target method `loadConversationTranscript` with a zero-arg call. Flagging for the controller to confirm this was the intended reading rather than a wrapper method.
- I did not wait to line up with Task 10 (the `/m` renderer component) — `submitCaptureForm(form)` is ready for it to call, and `cursor.reply.form` / persisted `m.form` carry `{ schema, errors, answers, locked }` for it to render.

---

## Fix report — round 1 (3 items, 3 commits)

### Item 1 (Important, Ruling 13, `/m` side) — a refused form must reopen, not stay locked

**What changed** (`resources/mobile/mixins/onboardingChat.js`, `handleFynEvent`):
- `capture_form_errors` now also sets `locked: false` on the latest form row alongside attaching `errors` — `send()` had locked it optimistically before the request went out; a refusal must undo that.
- `form_received` now also finds the latest form row and clears its `errors` to `null` — so a successful retry after an earlier refusal doesn't leave stale errors sitting underneath a form that's now correctly locked (the lock from `send()` stands untouched because no `capture_form_errors` follows).

**Covering tests** (`resources/mobile/mixins/__tests__/onboardingChat.spec.js`, "capture forms" describe):
- `'reopens a refused form with its errors attached'` — `send(null, form)` with a mocked `apiStream` emitting `form_received` → `capture_form_errors` → `done`; asserts `formRow.form.locked === false` and the error message is attached.
- `'locks a successfully accepted form and clears any earlier refusal errors'` — same path with `form_received` → `done` only, starting from a form row that already carries stale errors from a prior refusal; asserts `locked === true` and `errors === null`.

**TDD** — RED (fix lines temporarily removed via Edit, tests appended):
```
npx vitest run resources/mobile/mixins
```
```
× capture forms > reopens a refused form with its errors attached
  → expected true to be false
× capture forms > locks a successfully accepted form and clears any earlier refusal errors
  → expected { main_residence: {…} } to be null
Tests  2 failed | 18 passed (20)
```
GREEN (fix lines reinstated):
```
npx vitest run resources/mobile/mixins
```
```
Test Files  1 passed (1)
     Tests  20 passed (20)
```

Commit: `a9737c042 fix(m): a refused capture form reopens with its errors, not locked read-only`

### Item 2 (Ruling 13, web side) — same defect, same dispatch path

**What changed**:
- `resources/js/components/Shared/AiChatPanel.vue` — `isCaptureFormOpen(idx)` now also returns `true` when `this.messages[idx].metadata?.errors` is non-null, ahead of the existing "no user row follows" check (kept intact).
- `resources/js/store/modules/aiChat.js` — `sendMessage`'s SSE switch's `form_received` case now also `commit('SET_CAPTURE_FORM_ERRORS', null)`, reusing the existing mutation (it already targets "the latest `capture_form` row" and accepts `null` to clear).

I confirmed via `grep -n "^    async \|^    [a-zA-Z]*("` that `sendMessage` (lines 529-1009) is the only action `handleCaptureFormSubmit` reaches (`this.sendMessage({ form })`); `streamNextQueued`, `postAction` and `startOnboardingConversation` each have their own near-identical switch with their own `capture_form_errors`/`form_received` cases, untouched here.

**Adjacent issue found, not fixed (flagging per "report adjacent issues rather than silently fixing them")**: `streamNextQueued`'s `form_received` case (aiChat.js ~line 1093) is a no-op by design ("No placeholder user row exists on this path… nothing to rewrite") and does not clear errors either. If a form submission gets queued (202) behind an in-flight turn and its retry resumes via `streamNextQueued` rather than `sendMessage`, a successful resubmission there would not clear a prior refusal's errors. This wasn't in the item's scope and I didn't touch it — flagging for a decision on whether it needs the same one-line fix.

**Test harness note**: the team lead's fallback referenced `tests/frontend/components` having no `AiChatPanel` harness — confirmed true, but an existing harness for a different concern lives at `resources/js/components/__tests__/Shared/AiChatPanel.conversation.spec.js` (conversation-bootstrap-before-send only, no capture-form coverage). Per the instruction ("do not build one … the store test plus the panel's one-line change are enough") I did not extend it.

**Covering tests** (`tests/frontend/store/aiChatCaptureForm.test.js`):
- Added `expect(ctx.state.messages[0].metadata.errors).not.toBeNull();` to the existing `'attaches capture_form_errors to the latest form row…'` test.
- New test `'clears a prior refusal\'s errors once form_received confirms a successful retry'` — starts a form row with stale errors already set, streams `form_received` → `done`, asserts `metadata.errors` ends `null`.

**TDD** — RED (the `commit('SET_CAPTURE_FORM_ERRORS', null)` line temporarily removed):
```
npx vitest run tests/frontend/store/aiChatCaptureForm.test.js
```
```
× capture form in the chat store > clears a prior refusal's errors once form_received confirms a successful retry
  → expected { buy_to_let: {…} } to be null
Tests  1 failed | 4 passed (5)
```
GREEN (line reinstated):
```
npx vitest run tests/frontend/store/aiChatCaptureForm.test.js
```
```
Test Files  1 passed (1)
     Tests  5 passed (5)
```

Commit: `2fc53e5e5 fix(web): a refused capture form reopens with its errors, not locked read-only`

### Item 3 (Ruling 12) — revert the `loadTranscript` rename

Restored `loadTranscript(conversationId)` with its original signature, all 4 internal call sites, and `tests/frontend/mobile/onboardingChatEvents.test.js`'s direct call (`loadTranscript.call(vm, 7)`). My two "capture forms" spec tests that had called the renamed method now call `w.vm.loadTranscript(7)` directly. The Ruling 9 answers lookup and the lock-all-but-last-row logic inside the method are byte-for-byte unchanged — only the name/signature moved back.

Verified this state in isolation (fix lines for Item 1 temporarily removed so this commit is a pure revert):
```
npx vitest run resources/mobile/mixins tests/frontend/mobile/onboardingChatEvents.test.js
```
```
Test Files  2 passed (2)
     Tests  36 passed (36)
```

Commit: `7d456c77d revert(m): loadConversationTranscript back to loadTranscript(conversationId)`

### Final verification (after all three commits)

```
npx vitest run resources/mobile tests/frontend/store tests/frontend/mobile
```
```
Test Files  48 passed (48)
     Tests  295 passed (295)
```
Full-repo sweep also re-run: `npx vitest run` → `Test Files 145 passed (145)` / `Tests 1346 passed (1346)`.
```
npx eslint resources/js/components/Shared/AiChatPanel.vue resources/js/store/modules/aiChat.js resources/mobile/mixins/__tests__/onboardingChat.spec.js resources/mobile/mixins/onboardingChat.js tests/frontend/mobile/onboardingChatEvents.test.js tests/frontend/store/aiChatCaptureForm.test.js
```
No output — clean on all six touched files.

Commits this round (newest first):
- `2fc53e5e5 fix(web): a refused capture form reopens with its errors, not locked read-only`
- `a9737c042 fix(m): a refused capture form reopens with its errors, not locked read-only`
- `7d456c77d revert(m): loadConversationTranscript back to loadTranscript(conversationId)`
