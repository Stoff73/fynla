# Task 7 Review: Web store and API — the form event, the form submission, history

Base: d3bd4b114 | Head: 5ba0d9759

## Spec Compliance
- ✅ All stated global constraints are met: the `X-Fynla-Forms: 1` header is added to all four SSE `fetch()` calls (`resources/js/services/aiChatService.js:64,92,114,136`); `capture_form`, `capture_form_errors`, `form_received` are consumed in all four store switches; the one-send-path body contract (`form` XOR `message`, never both) is implemented exactly (`resources/js/services/aiChatService.js:51-56`); the `capture_form` message row shape matches the spec exactly (`resources/js/store/modules/aiChat.js:167-175`); history load splits a persisted form turn into an assistant text row plus a `capture_form` row, preserving `onboarding_step` on the text row via metadata spread (`resources/js/store/modules/aiChat.js:456-469`); no emoji/Unicode-as-icons introduced; British copy ("Saving your property details…").
- ❌ Issues found: two functional gaps in the `capture_form_errors` path (both traceable verbatim to the brief's own pseudocode — see Important, labeled plan-mandated).
- ⚠️ Cannot verify from diff: the wider-suite results (830 tests / 75 files, eslint, policy-lint) claimed in the report — plausible given the diff's scope, but not independently re-run.

## Strengths
- The implementer caught and correctly fixed two factual errors in the brief's own Step-1 test snippet (`state()` called as a factory when the module exports a plain object; a `getConversation` mock shape that doesn't match the controller's actual `{ success, data: { conversation, messages } }` envelope). Verified both corrections against the real code: `resources/js/store/modules/aiChat.js:49` (`const state = {`) and `app/Http/Controllers/Api/AiChatController.php:129-134`. Without the fix the fourth test would have passed vacuously against an empty `raw = []`.
- The `producedNewMessages` empty-response guard correctly covers the `capture_form` case (it does `ADD_MESSAGE`), which is exactly what the first new test verifies (`tests/frontend/store/aiChatCaptureForm.test.js:543-557`) — confirmed by tracing `resources/js/store/modules/aiChat.js:963-973`.
- The four-switch duplication of the three new cases follows the file's own pre-existing convention (the same file already duplicates `onboarding_advance`/`capture_complete` handling across all four SSE consumers) rather than introducing a new pattern.
- Header addition, `sendMessage`'s string-or-`{form}` dispatch, and the mutation/action wiring all match the brief precisely, verified line-by-line against the diff.

## Risk checks requested by the review brief
**(a) `sendMessage` callers today.** Grepped `resources/js` and `resources/mobile` for every call site: `resources/js/components/Shared/AiChatPanel.vue:1174,1205,1243` all pass a string (`message` or `label`), never an object. No existing caller would be misread as a form submission by the new `arg && typeof arg === 'object'` branching (`resources/js/store/modules/aiChat.js:288`). Not a risk today; Task 8 is expected to add the first `sendMessage({ form })` call site.

**(b) Placeholder user row's `content` used elsewhere.** Checked `trackChatMessageSent` (`resources/js/services/analyticsService.js:142`) — called from `AiChatPanel.vue` with the typed `message`/`label` string directly at the call site, not read from the store's placeholder row, so unaffected. Checked the queued-turn resume path (`streamNextQueued`, `resources/js/store/modules/aiChat.js:905-927`) — it resumes by `queued.id` (a real backend id) via `aiChatService.streamQueuedMessage(conversationId, queued.id, ...)`, never re-reading `content` to resend. The placeholder's `content` is display-only. No other reads found.

## Issues

### Critical (Must Fix)
None.

### Important (Should Fix) — both plan-mandated (implemented verbatim from the brief's own pseudocode)

**1. A rejected form submission fires the generic "empty response" error banner alongside the correct field-level errors.**
`sendMessage`'s `capture_form_errors` case only calls `SET_CAPTURE_FORM_ERRORS`, which mutates an existing row in place and never calls `ADD_MESSAGE` (`resources/js/store/modules/aiChat.js:742-746`). The finally block's empty-response guard treats "no new message pushed" as "Fyn never replied" (`resources/js/store/modules/aiChat.js:963-973`):
```js
const producedNewMessages = state.messages.length > preStreamMessageCount;
if (!authExpired && state.streaming && !state.streamingText && !producedNewMessages
    && !state.error && !state.tokenLimitReached && !state.consentRequired) {
    commit('SET_ERROR', 'Fyn couldn\'t generate a response...');
}
```
Traced end to end: if a stream's only events are `capture_form_errors` then `done` — exactly the sequence the task's own third test uses (`tests/frontend/store/aiChatCaptureForm.test.js:574-584`) — `producedNewMessages` stays `false`, `state.error` stays `null`, `tokenLimitReached`/`consentRequired` default `false` (confirmed at `resources/js/store/modules/aiChat.js:58-79`), so the guard's full condition is true and it commits `"Fyn couldn't generate a response. This can happen with longer conversations — try starting a new one."` on top of the correctly-rendered "You have reached your plan's property limit" form error. This is the same banner-collision bug class the surrounding comment documents as previously shipped (BS-13 — see the comment at `resources/js/store/modules/aiChat.js:956-962`), reproduced for this new event. The existing test doesn't catch it because it never asserts on `ctx.state.error`.
*Fix:* have the `capture_form_errors` case also mark the turn as "replied" for the empty-response guard — either commit something the guard already checks (e.g. treat a non-null/non-empty `errors` payload the same as `producedNewMessages`), or add a small dedicated flag, so the generic banner never fires when field-level errors were just attached.

**2. A rejected form submission leaves a stale, misleading placeholder in the transcript permanently.**
The optimistic user bubble is created with the text "Saving your property details…" (`resources/js/store/modules/aiChat.js:298`, i.e. `displayMessage = form ? 'Saving your property details…' : stripTags(message)`) and is only ever corrected by `SET_TEMP_USER_CONTENT`, which fires solely on `form_received` (success) — `resources/js/store/modules/aiChat.js:719-724`. `capture_form_errors` never calls it (`resources/js/store/modules/aiChat.js:742-746`), so after a failed submission the chat history keeps a bubble reading "Saving your property details…" — worded as an in-progress/successful save — with no correction. A second failed retry adds another such bubble, each equally uncorrected, stacking confusing near-duplicate "Saving…" rows above the one form row that carries the actual errors.
*Fix:* on `capture_form_errors`, also rewrite (or clear) the `tempId` placeholder — e.g. to something neutral like "Property details" or the literal answers submitted — rather than leaving the present-progressive "Saving…" wording stuck as if it succeeded.

Both defects stem directly from the brief's literal Step 3 pseudocode for `case 'capture_form_errors'`, which the implementer followed exactly as written (their own report confirms: "Everything else... was implemented verbatim as specified"). The fix belongs in this task (or a tight follow-up to it) rather than being deferred to Task 8's renderer, since both are store-level state defects (`state.error`, a message row's `content`) that the renderer cannot correct after the fact without reaching back into store internals.

### Minor (Nice to Have)
- The three-case block (`form_received`/`capture_form`/`capture_form_errors`) is duplicated verbatim across all four switches (`resources/js/store/modules/aiChat.js` ~726, ~1085, ~1345, ~1654). Consistent with this file's pre-existing duplication pattern for other event types (`onboarding_advance`, `capture_complete` are duplicated the same way), so not new debt this task introduces, but a candidate for extraction into a shared handler in a future refactor.
- No test covers the "streaming text gets flushed into its own row before the `capture_form` row" branch (`resources/js/store/modules/aiChat.js:730-738`) — only the no-streaming-text path is exercised by the new tests.
- `captureFormMessage`'s id (`'cf_' + Date.now()`) and the history-normalisation row id (`` `cf_${m.id}` ``) use different id schemes; harmless today (no collision), but worth a comment if a future change ever needs to correlate a live-rendered form row back to a persisted message id.

## Assessment
**Task quality:** Needs fixes
**Reasoning:** The wiring, header, one-send-path contract, and history normalisation are all correctly and faithfully implemented per spec, with good independent verification work by the implementer (catching two errors in the brief's own test snippet). But the `capture_form_errors` branch — implemented verbatim from the brief — produces two real, traceable user-facing defects (a false "empty response" banner and a permanently-wrong "Saving…" placeholder) on the exact rejection path the task's own test exercises, and both need a small correction before this is trustworthy.
