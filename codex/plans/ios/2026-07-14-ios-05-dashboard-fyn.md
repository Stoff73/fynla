# iOS Package 5: Dashboard, Navigation, Gamification and Fyn Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Use `superpowers:test-driven-development`, `systematic-debugging` for every failure, `verification-before-completion` before the gate, and `verify-m` for all shared dashboard/Fyn changes. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the first complete native product vertical slice: unlock, dashboard, approved gamification, navigation, achievements and one native Fyn conversation that handles onboarding, advice, delegated writes and queued turns.

**Architecture:** Swift decodes the existing `/api/v1/mobile/dashboard`, gamification and Fyn contracts. Dashboard and Fyn own independent observable models backed by shared API/SSE actors. Fyn events reduce into deterministic transcript state; the client never selects onboarding versus advice mode.

**Tech Stack:** SwiftUI, Observation, URLSession SSE, Swift Testing, XCTest UI tests; existing Laravel mobile dashboard, gamification and AI chat endpoints.

## Global Constraints

- Do not change `MobileDashboardAggregator` or Fyn response shapes merely to make Swift decoding easier; use the frozen contracts from Package 1.
- Preserve the approved Level wheel, “X of Y actions complete” progress and percentile. These are explicit gamification carve-outs, not financial-quality scores.
- Do not display `adequacy_score`, `diversification_score` or similar financial ratings.
- Fyn has one surface and one message endpoint. No client-side onboarding/advice persona flag, label or prompt.
- `done` completes the assistant reply but does not terminate reading; `level_up` after `done` must still arrive.
- A `202` message response is queued, not failed.
- A capture is successful only when server events prove persistence.
- Unknown typed events are recorded as redacted event names and ignored without losing text.
- No decorative icon, emoji, mascot, character image or Fyn chat icon. Any functionally necessary mobile navigation icon requires the existing approved design or separate CSJ approval.
- Markdown rendering accepts a constrained subset and never renders arbitrary HTML.

## File map

| Path | Responsibility |
|---|---|
| `ios-native/Fynla/Features/Dashboard/` | Dashboard DTO, model, view and cards |
| `ios-native/Fynla/Features/Navigation/` | Menu and typed route presentation |
| `ios-native/Fynla/Features/Gamification/` | Level/progress/percentile and celebration state |
| `ios-native/Fynla/Features/Achievements/` | Completed actions and activity history |
| `ios-native/Fynla/Features/Fyn/` | Conversation DTOs, event reducer, transcript and composer |
| `ios-native/Fynla/Features/BugReport/` | Allowlisted diagnostics/report sheet |
| `ios-native/FynlaTests/Fixtures/Dashboard/` | Sanitised dashboard states |
| `ios-native/FynlaTests/Fixtures/Fyn/` | Transcript, streaming and queued event fixtures |
| existing Laravel mobile/AI tests | Shared regression authority |

### Task 1: Decode every dashboard state without fabricated defaults

**Files:** Create `DashboardModels.swift`, `DashboardClient.swift`, `DashboardModel.swift`; create fixtures/tests.

- [ ] Capture sanitised JSON fixtures for populated, new-user, partially configured, module-unavailable and Free-gated dashboards from the Package 1 contract.
- [ ] Write failing decode tests before DTOs.
- [ ] Model modules as a known-key structure or typed dictionary whose unavailable state remains explicit; do not turn missing/invalid currency into zero.
- [ ] Decode net worth, alerts, Fyn insight, focus areas, next actions, level, percentile, milestones and cache timestamp.
- [ ] Use `Decimal` for money DTOs. Convert to `Double` only for presentation geometry after clamping an explicit percentage.
- [ ] Define view state:

```swift
enum DashboardViewState: Sendable, Equatable {
    case idle
    case loading
    case loaded(DashboardSnapshot)
    case offline(previous: DashboardSnapshot?)
    case unauthenticated
    case failed(requestID: String?)
}
```

- [ ] Refresh through `.task` and `.refreshable`; cancellation must not replace a loaded dashboard with an error.
- [ ] Handle HTTP 304 only if Package 2's in-memory ETag support is explicitly added with tests; otherwise request a fresh no-cache response.
- [ ] Run dashboard model tests; expect PASS.

**Intended review boundary:** `feat: decode native mobile dashboard contract`

### Task 2: Build the native dashboard and approved gamification

**Files:** Create `DashboardView.swift`, `LevelProgressView.swift`, `FocusAreasView.swift`, `ModuleSummaryView.swift`, `NextMilestoneView.swift`; UI tests.

- [ ] Read `fynlaDesignGuide.md` and the current `resources/mobile/views/Dashboard.vue` immediately before UI work.
- [ ] Reproduce information hierarchy, not CSS pixels: greeting/context, Level progress, percentile, focus actions, net worth, module summaries, Fyn insight and milestones.
- [ ] Use the server level number, completed/total action counts and percentile exactly. Clamp drawing values to safe bounds but display the server value or an explicit unavailable state.
- [ ] Keep the Level wheel and action progress accessible: VoiceOver announces “Level N, X of Y actions complete” and percentile separately.
- [ ] Module cards use text and metrics only; no decorative icons and no financial-quality scores.
- [ ] Each focus action routes through typed `AppRoute` or opens Fyn with a server-provided prompt. Never parse arbitrary server URLs into navigation.
- [ ] Marking an action done uses the existing recommendation endpoint, waits for acknowledgement, then reloads dashboard/gamification.
- [ ] Add loading, empty, partial-error, offline, upgrade-required and auth-expired UI tests.
- [ ] Test XXL Dynamic Type, VoiceOver labels and Reduce Motion on iPhone 11.

**Intended review boundary:** `feat: add native dashboard and gamification`

### Task 3: Build typed menu navigation

**Files:** Create `Features/Navigation/NavigationMenuView.swift`, `NavigationDestinationFactory.swift`; modify root app/router tests.

- [ ] Mirror current `/m` route groups without inventing a tab bar or reorganising modules.
- [ ] Version 1 destinations are Dashboard, Achievements, Income, Expenditure, Net Worth, Protection, Savings, Investment, Retirement, Estate Planning, Goals, Tax Strategy, Holistic Plan and Settings.
- [ ] Use text labels. If the approved `/m` design requires an icon for an unlabeled compact state, stop and obtain CSJ approval before adding it.
- [ ] Keep unavailable Package 6 destinations visible only according to the staged internal-build plan; production cannot ship dead controls.
- [ ] Deep route values contain IDs/types only and refetch their own server state.
- [ ] Add route tests for every destination, locked session rejection and back-stack restoration.

**Intended review boundary:** `feat: add typed native navigation menu`

### Task 4: Build achievements and activity history

**Files:** Create `Features/Achievements/AchievementsModels.swift`, `AchievementsClient.swift`, `AchievementsModel.swift`, `AchievementsView.swift`; tests.

- [ ] Write decode/pagination tests for `/api/v1/mobile/achievements`, `/completed?page=N` and `/api/gamification/activity?before=cursor`.
- [ ] Preserve server ordering and cursor/page semantics; deduplicate by server identifiers.
- [ ] Represent achievement text and dates without emoji or newly invented icons. Existing server emoji/glyph data must not be forwarded into new user-facing Swift text; use the textual label supplied by the canonical contract.
- [ ] Implement loading, empty, pagination failure and refresh states.
- [ ] Add an accessible text-only level celebration that honours Reduce Motion. Do not add fireworks or symbols unless the already approved mobile design explicitly includes that native treatment and CSJ confirms it.
- [ ] Acknowledge a pending celebration only after the user dismisses it and the server call succeeds/retries safely.

**Intended review boundary:** `feat: add native achievements and activity`

### Task 5: Model Fyn conversations and transcript loading

**Files:** Create `FynModels.swift`, `FynClient.swift`, `FynConversationModel.swift`, transcript fixtures/tests.

- [ ] Write failing decode tests for conversation creation, transcript loading and message metadata/bubbles.
- [ ] Use the existing endpoints for create, load, action, message and queued stream.
- [ ] Define message identity and state:

```swift
struct FynMessage: Identifiable, Sendable, Equatable {
    enum Role: Sendable { case user, fyn }
    enum Delivery: Sendable { case persisted, submitting, streaming, queued, failed }
    let id: String
    let role: Role
    var text: String
    var replies: [FynReply]
    var delivery: Delivery
    var capture: CaptureState?
}
```

- [ ] Client-generated IDs are presentation-only. Reconcile them with server message IDs as soon as returned.
- [ ] Load history from the server on opening/resume; no transcript is persisted locally.
- [ ] Render constrained Markdown using `AttributedString(markdown:)` with inline emphasis/strong/link disabled unless the link is allowlisted. Never use `WKWebView` or HTML.
- [ ] Keep one active send task per conversation and disable repeated submission while acceptance is pending.

**Intended review boundary:** `feat: add native fyn conversation model`

### Task 6: Reduce all Fyn SSE events deterministically

**Files:** Create `FynEvent.swift`, `FynEventDecoder.swift`, `FynEventReducer.swift`; extensive tests.

- [ ] Create fixtures for text token/content frames and every currently handled `/m` event: `conversation_created`, `resume`, `onboarding_advance`, `navigation`, `onboarding_complete`, `level_up`, `token_limit`, `consent_required`, `handoff_error`, `error`, `entity_created`, `capture_complete`, `skip_link`, `quick_replies`, `done`, plus an unknown event.
- [ ] Decode with a discriminator but retain unknown event name:

```swift
enum FynEvent: Sendable, Equatable {
    case text(String)
    case conversationCreated(String)
    case resume(String)
    case onboardingAdvance
    case navigation(path: String, section: String?)
    case onboardingComplete
    case levelUp(LevelUpEvent)
    case tokenLimit(String)
    case consentRequired
    case handoffError(String)
    case error(String)
    case entityCreated(name: String?)
    case captureComplete(summary: String?)
    case quickReplies(prompt: String?, replies: [FynReply], actionReplies: Bool)
    case done
    case unknown(String)
}
```

- [ ] Reducer splits a new assistant message at `onboarding_advance` when the prior one has content/replies.
- [ ] `entity_created` is pending evidence only; `capture_complete` finalises the write and summary. Stream failure before completion must not fabricate success.
- [ ] `done` marks reply complete but leaves transport consumption active. Test `done` then `level_up`.
- [ ] Navigation allowlist is exactly Tax Strategy, Income, Expenditure, Savings, Investment and Retirement. Unknown/desktop-only routes are ignored safely.
- [ ] Same-destination navigation closes Fyn, triggers screen refetch and retains the Gate 2 transcript state.
- [ ] Unknown frames do not terminate subsequent text.
- [ ] Test every event ordering that the `/m` mixin currently depends on.

**Intended review boundary:** `feat: reduce typed fyn stream events`

### Task 7: Implement send, queue and retry invariants

**Files:** Modify `FynClient.swift`, `FynConversationModel.swift`; create concurrency tests.

- [ ] A single gesture issues one POST. Do not use automatic API replay for the message POST.
- [ ] Preserve submitted user text if network acceptance is uncertain and show a retry action that first reloads the conversation to detect an accepted server message.
- [ ] On 202, mark queued and use returned message ID to call `/messages/{messageId}/stream`.
- [ ] If queued stream returns 409, use the approved bounded schedule of eight attempts at 1.5 seconds with an injected clock; then show “still answering” without marking failure.
- [ ] Cancellation on leaving Fyn stops reading but does not delete the server message. Reopening reloads conversation state.
- [ ] Rate limit honours `Retry-After`; offline preserves draft but does not queue the financial write locally.
- [ ] Add tests for double tap, cancellation, accepted-but-response-lost, 202 then stream, 409 retries, terminal error and trailing level event.

**Intended review boundary:** `feat: handle fyn queued and interrupted turns`

### Task 8: Build the native Fyn conversation interface

**Files:** Create `FynView.swift`, `FynMessageView.swift`, `FynComposerView.swift`, `FynQuickRepliesView.swift`, `FynCaptureConfirmationView.swift`; UI tests.

- [ ] Use plain-text Fyn header and controls; no icons, emoji, character image, streaming glyph or decorative typing indicator.
- [ ] Make transcript VoiceOver order stable and announce new assistant text without re-reading the entire history.
- [ ] Quick replies are text buttons, consumed after selection and routed to action versus message endpoints according to `actionReplies`.
- [ ] Composer supports multiline text, disabled state during acceptance and an explicit text Stop action during streaming.
- [ ] Show queued, rate limit, consent, token limit, offline and failure wording distinctly.
- [ ] Navigation events push an allowlisted typed route after the stream settles.
- [ ] Level-up presentation occurs after the assistant reply renders and never interrupts capture confirmation.
- [ ] Build UI tests for first onboarding, resume, bubbles, free text, delegated capture, advice, queued turn and recoverable failure.

**Intended review boundary:** `feat: add native fyn conversation surface`

### Task 9: Add allowlisted bug reporting

**Files:** Create `Features/BugReport/BugReportView.swift`, `BugReportModel.swift`; tests.

- [ ] Match the current authenticated `/api/bug-report` boundary.
- [ ] Permit user description plus allowlisted app version/build/environment, request correlation ID, native session UUID and optional conversation ID.
- [ ] Do not attach transcript, network bodies, tokens, signed transaction data or financial values.
- [ ] Let the user review exactly what metadata will be submitted.
- [ ] Handle rate limit and offline without claiming submission.

**Intended review boundary:** `feat: add privacy safe native bug reporting`

### Task 10: Full vertical-slice acceptance loop

**Files:** Tests and parity ledger evidence only unless a failure identifies a root cause.

- [ ] Register a brand-new Free user natively, verify email, opt into Face ID and start Fyn onboarding.
- [ ] Complete bubble and free-text capture steps; verify database rows and audit trail, then confirm dashboard values refresh.
- [ ] Navigate to every current allowlisted verification screen shell and return to Fyn without losing conversation state.
- [ ] Complete onboarding, ask advice and perform a delegated write; verify no client-visible persona switch/handoff event.
- [ ] Force a 202 queued turn and verify the later streamed response.
- [ ] Force a post-`done` level-up and verify dashboard/achievement state.
- [ ] Use a Premium Apple sandbox account and Free account to verify capability presentation.
- [ ] Repeat shared scenarios in desktop browser and through `verify-m`; check the same database/SSE outcomes.
- [ ] Run on an iPhone 11-family physical device for cold launch, dashboard scroll performance, memory pressure, Fyn stream interruption and background relock.

Commands:

```bash
./vendor/bin/pest tests/Feature/Mobile tests/Feature/AI tests/Feature/Auth tests/Feature/Native
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,name=iPhone 11' test CODE_SIGNING_ALLOWED=NO
```

Expected: PASS plus browser/device/database evidence for the full journey.

### Package 5 exit criteria

- [ ] Native dashboard matches the current `/m` information hierarchy.
- [ ] Approved level, action progress and percentile remain intact.
- [ ] Achievements and activity paginate correctly.
- [ ] One Fyn surface handles onboarding, advice and delegated writes.
- [ ] All current typed events, queued responses and post-`done` level events pass.
- [ ] Fyn success claims match persisted server writes.
- [ ] No decorative chat icons/emoji or financial-quality scores were introduced.
- [ ] New user vertical slice passes native, desktop and `/m` with database evidence.
- [ ] CSJ approves the vertical slice before financial feature waves begin.
