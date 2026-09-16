# Task 6 Review

### Spec Compliance
- ❌ Issues found: the `action` endpoint's `continue` path never threads `clientSupportsForms`, so a resumed `campaign_property` turn can never render the form regardless of client capability. See Important finding below.
- Everything else in the brief's stated scope is compliant:
  - `sendMessage`, `streamQueuedMessage`, `startOnboarding` all call `setClientSupportsForms` before the one director call each makes, matching "header read once, passed to director as a flag" (`app/Http/Controllers/Api/AiChatController.php:232`, `:450`, `:842`). Director never reads headers directly — confirmed `OnboardingChatDirector.php` has no `header(`/`Request` reference outside a comment (`OnboardingChatDirector.php:130-134`).
  - `CaptureForms::summarise` is the sole place the transcript line is composed in the controller (`AiChatController.php:225-232`); metadata round-trips through `ConcurrentTurnQueue::enqueue` (`ConcurrentTurnQueue.php:73-84`) and back out in `streamQueuedMessage` (`AiChatController.php:447-450`).
  - `metadata` is array-cast on `AiMessage` (`app/Models/AiMessage.php:43`), so the `is_array()` guards are meaningful, not dead code.
  - `enqueue()`'s new `$metadata` parameter is backward compatible (default `[]`) and has exactly one call site in the codebase (`AiChatController.php:245`), so the signature change is safe.
  - `declare(strict_types=1)`, `it()`/`RefreshDatabase` conventions followed in the test file.

### Strengths

- **Caught two real bugs during TDD**, both documented honestly in the report: a formatter hook silently stripping the unused `CaptureForms` import (caught by running the full regression pass, not just the two new tests), and `withHeader()` leaking onto a second request in the same test (fixed with `flushHeaders()`, commented for the next reader).
- **The test-setup adaptation is sound.** The brief assumed `/start` re-emits a resumed step's turn; the implementer verified (via the RED run) that the actual "already mid-flow" branch (`AiChatController.php:708-731`) only emits a bare `resume` event with no director call, and that the brief's literal fallback ("post `{message:'Continue'}`") routes to `handleAssetCaptureTurn`, not `emitTurnForState`. Driving a fresh funnel entry via a config override on `campaign_map.savetax.entry` still exercises the exact code this task changed (`setClientSupportsForms` immediately before `emitFirstTurn`), and both assertions (header → `capture_form`, no header → typed prompt) go through identical control flow in `startOnboarding` bar the header — a legitimate way to isolate the variable under test.

### Issues

#### Important (Should Fix)

**The `action` endpoint's `continue` case never gets `clientSupportsForms` set, so a resumed campaign_property turn never renders the form.** `AiChatController::action()` (`app/Http/Controllers/Api/AiChatController.php:914-939`) dispatches straight to `$this->onboardingDirector->handleAction($user, $conversation, $action)` at line 939 with no preceding `setClientSupportsForms` call. `OnboardingChatDirector::handleAction`'s `continue` case (`OnboardingChatDirector.php:662-679`) calls `emitTurnForState` directly for the user's current step — the same method `emitFirstTurn` calls, and the one this task wired for `startOnboarding`. `emitTurnForState` gates the form on `$this->clientSupportsForms` (`OnboardingChatDirector.php:1036`), which defaults to `false` (`OnboardingChatDirector.php:134`) and is never set anywhere on this call path.

This matters because `handleResumeAction`'s own docblock says it's "used by the resume flow on conversation mount" and "the web resume flow calls this on every chat open" (`OnboardingChatDirector.php:696-721`) — i.e. `action:'continue'` (the "Continue" button after the resume greeting) is the live mechanism by which a returning user actually reaches their current step's turn, not `/start` (which the implementer's own investigation showed just emits a pointer event). A user who reloads the page or resumes a session mid-`campaign_property` and clicks "Continue" will always get the typed prompt, never the capture form, even on web/`/m` where forms are fully supported. This is exactly the "resumed walk" scenario the brief's Step 1 test tried to exercise before discovering `/start` doesn't reach it — the implementer used that discovery only to adjust their test's setup, without following it one level further (a grep for other `emitTurnForState` callers, which is what surfaced this).

Fix: add `$this->onboardingDirector->setClientSupportsForms($this->clientSupportsForms($request));` in `AiChatController::action()` before line 939, and add a test exercising `action:'continue'` on a `campaign_property`-stepped user with and without the header.

#### Minor (Nice to Have)

- **The queued-turn round trip is unexercised.** This task changed `ConcurrentTurnQueue::enqueue` to carry `metadata.form` (`ConcurrentTurnQueue.php:73-84`) and `streamQueuedMessage` to read it back out (`AiChatController.php:447-450`), but no test (new or in the regression pass) enqueues a form-bearing message and then verifies `streamQueuedMessage` forwards the correct `$form` to the director. `ConcurrentTurnQueueTest.php`/`ConcurrentTurnQueueGateTest.php` only call `enqueue()` with plain strings. Low real-world likelihood (the frontend disables input mid-stream), but it's new code with zero direct coverage.
- `setClientSupportsForms` is called in `sendMessage` (`AiChatController.php:232`) even on the queued-response branch that returns before any director call is made in that request — harmless (the flag is simply unused this request) but a wasted call worth a one-line comment or moving below the `isFull`/queue check if noticed again.

### Assessment
**Task quality:** Needs fixes
**Reasoning:** The three in-scope call sites (`sendMessage`, `streamQueuedMessage`, `startOnboarding`) are wired correctly and the tests for them are sound, including a well-justified adaptation of the brief's first test. But the diff leaves the `action` endpoint's `continue` case — the actual resume-completion mechanism — without the flag, so a returning user hitting `campaign_property` never sees the capture form regardless of client capability. That's a real functional gap in the feature's primary "come back later" path, not just a coverage note.
