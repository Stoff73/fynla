# Contextual Fyn and Conversation History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking. In this workspace, use subagents only after the user explicitly authorises delegation.

**Goal:** Deliver PR 2 of the approved iOS and `/m` parity programme: every supported Add/Edit action starts a fresh, server-authorised contextual Fyn conversation; onboarding and surface-action conversations remain isolated; existing financial facts are rehydrated only by Laravel; and both mobile clients expose safe, useful Conversation History.

**Architecture:** Add one authenticated contextual-conversation endpoint beside the legacy generic conversation endpoint. The request accepts only allowlisted action/resource/navigation identifiers. Laravel ownership-filters entity lookup, stores identifier-only conversation metadata, persists a server-authored opening turn, and rehydrates canonical resource context at every Fyn turn. Conversation metadata fixes the dispatch mode for its lifetime, so a surface action never enters the onboarding director. The existing `AdviceFyn -> OnboardingChatDirector::handleInlineCapture` path remains the sole validated mutation boundary. `/m` and Swift share the JSON contract, but each owns its local presentation and routing.

**Tech Stack:** PHP 8.3, Laravel 11, Eloquent, Sanctum, Pest/PHPUnit, Vue 3, Vue Router 4, Vitest, Swift 6, SwiftUI, Swift Testing, XCUITest, Xcode iOS Simulator, installed Google Chrome.

## Global Constraints

- Implement only PR 2 and ledger items M-08 (contextual half), M-11, M-31, and M-32 from `docs/superpowers/specs/2026-08-09-ios-m-parity-debugging-design.md`.
- Do not implement PR 3 detail-page restructuring or PR 4 portfolio/allocation/drift calculations in this branch.
- Clients may send `action`, `resource_type`, `resource_id`, `current_destination`, and identifier-only `origin`; they must never send balances, values, contributions, premiums, rates, financial labels, or other facts as authoritative context.
- Reject unexpected request keys and non-identifier destination parameters. Do not silently discard attempted financial context.
- Ownership-filter before lookup and return the same safe not-found response for missing and cross-user resources. Household access may be added only through an existing explicit household authorisation rule.
- Persist identifiers and provenance metadata, not a financial snapshot. Rehydrate canonical facts on each turn so context remains fresh.
- A contextual opening message may use the server-resolved entity label but must not include balances or other sensitive values.
- Conversation mode comes from immutable conversation metadata. Onboarding conversations retain deterministic director routing; surface-action conversations use advice/capture even while the user is globally mid-onboarding; legacy conversations retain the existing fallback predicate.
- Preserve the existing validated capture/write workflow as the only mutation path. No contextual service writes financial records.
- Conversation History separates onboarding and contextual conversations and provides an explicit safe fallback when a related entity is deleted or inaccessible.
- Browser automation, screenshots, and visual acceptance use the user's installed Google Chrome through the Chrome connector only. Never use Chromium, bundled Playwright Chromium, or the in-app browser.
- Run native user journeys in the Xcode iOS Simulator. Record every failure, classification, root cause, regression test, fix, and green rerun in `docs/testing/2026-08-10-contextual-fyn-conversation-history-evidence.md`.
- The user has given standing approval for phased commits, ready PR creation, CI repair, and merge when green. Keep commits coherent and branch-scoped.

---

### Task 1: Define and enforce the identifier-only contextual request contract

**Files:**
- Create: `app/Http/Requests/AI/CreateContextualConversationRequest.php`
- Create: `app/Services/AI/ContextualConversation/ContextualResource.php`
- Create: `app/Services/AI/ContextualConversation/ContextualResourceResolver.php`
- Create: `tests/Feature/AI/ContextualConversationContractTest.php`
- Modify: `routes/api.php`

**Interfaces:**
- Produces: `POST /api/ai-chat/contextual-conversations`.
- Accepts: `action`, `resource_type`, optional `resource_id`, `current_destination.screen`, identifier-only `current_destination.params`, `current_destination.fallback`, `origin.kind`, and optional `origin.recommendation_id`.
- Produces resolver value: `ContextualResource { resourceType, resourceId, label, overviewScreen, canonicalFacts }`.

- [x] **Step 1: Write failing contract tests**

Cover unauthenticated access; `add|edit` allowlisting; supported overview and entity resource types; required IDs for entity resources; rejected unknown top-level/nested keys; rejected balance/value/rate/contribution/premium keys; scalar identifier parameters only; same-user ownership; cross-user non-disclosure; and successful resolution of savings, investment, DC/DB/state pension, goal, and each protection policy type.

- [x] **Step 2: Run the contract test and confirm RED**

Run: `./vendor/bin/pest tests/Feature/AI/ContextualConversationContractTest.php`

Expected: FAIL because the endpoint, request, and resolver do not exist.

- [x] **Step 3: Implement strict validation and ownership-filtered resolution**

Register the fixed route before `/conversations/{id}`. In the form request, compare actual keys recursively against allowlists and validate destination params as named IDs/enums only (`*_id`, `policy_type`, `pension_type`). In the resolver, support overview contexts without an ID and entity contexts through `where('user_id', $user->id)->findOrFail($id)`. Return canonical facts from the model loaded on the server; never accept them from the request.

- [x] **Step 4: Run focused tests and formatter**

Run:

```bash
./vendor/bin/pest tests/Feature/AI/ContextualConversationContractTest.php
./vendor/bin/pint app/Http/Requests/AI/CreateContextualConversationRequest.php app/Services/AI/ContextualConversation tests/Feature/AI/ContextualConversationContractTest.php routes/api.php
```

Expected: PASS and formatter exit 0.

- [x] **Step 5: Commit the contract boundary**

Run: `git add app/Http/Requests/AI/CreateContextualConversationRequest.php app/Services/AI/ContextualConversation tests/Feature/AI/ContextualConversationContractTest.php routes/api.php docs/superpowers/plans/2026-08-10-contextual-fyn-conversation-history.md && git commit -m "feat: add trusted contextual conversation contract"`

---

### Task 2: Create fresh contextual conversations with provenance and safe openings

**Files:**
- Create: `app/Services/AI/ContextualConversation/ContextualConversationService.php`
- Modify: `app/Http/Controllers/Api/AiChatController.php`
- Modify: `app/Models/AiConversation.php`
- Modify: `tests/Feature/AI/ContextualConversationContractTest.php`

**Interfaces:**
- Produces response: `{success:true,data:{conversation,opening_message}}` with HTTP 201.
- Stores metadata: `source=surface_action`, `mode=surface_action`, action/resource identifiers, semantic destination, identifier-only origin, and `context_provenance.authority=server` plus rehydration timestamp.
- Persists the opening as an assistant `AiMessage` so immediate response and later transcript are identical.

- [x] **Step 1: Extend tests before implementation**

Assert two identical taps create two different conversation IDs; metadata contains identifiers/provenance but no submitted or canonical financial values; the opening is personalised from the owned server record; the opening contains no balance; it is the first persisted assistant message; and a failed lookup creates neither conversation nor message.

- [x] **Step 2: Run the test and confirm RED**

Run: `./vendor/bin/pest tests/Feature/AI/ContextualConversationContractTest.php --filter='creates|opening|provenance|fresh'`

Expected: FAIL because creation is not implemented.

- [x] **Step 3: Implement transactional creation**

Resolve before write, then create the conversation and assistant message in one transaction. Derive title, purpose, related label, fallback screen, and opening from the server-resolved resource. Increment message count and timestamps consistently with existing conversation behaviour.

- [x] **Step 4: Run focused and compatibility tests**

Run:

```bash
./vendor/bin/pest tests/Feature/AI/ContextualConversationContractTest.php tests/Feature/Contracts/ClientCompatibilityContractTest.php
./vendor/bin/pint app/Http/Controllers/Api/AiChatController.php app/Models/AiConversation.php app/Services/AI/ContextualConversation/ContextualConversationService.php tests/Feature/AI/ContextualConversationContractTest.php
```

Expected: PASS; legacy `POST /api/ai-chat/conversations` remains compatible.

- [x] **Step 5: Commit fresh conversation creation**

Run: `git add app/Http/Controllers/Api/AiChatController.php app/Models/AiConversation.php app/Services/AI/ContextualConversation/ContextualConversationService.php tests/Feature/AI/ContextualConversationContractTest.php && git commit -m "feat: create fresh contextual Fyn conversations"`

---

### Task 3: Isolate conversation modes and rehydrate trusted context each turn

**Files:**
- Create: `app/Services/AI/ContextualConversation/ConversationModeResolver.php`
- Modify: `app/Http/Controllers/Api/AiChatController.php`
- Modify: `app/Services/AI/ContextualConversation/ContextualResourceResolver.php`
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php`
- Create: `tests/Feature/AI/ContextualConversationDispatchTest.php`
- Create: `tests/Unit/Services/AI/Fyn/ContextualResourceContextTest.php`
- Modify: `tests/Feature/AI/CampaignReentryDispatchTest.php`

**Interfaces:**
- Produces: `ConversationModeResolver::routesToOnboarding(AiConversation $conversation, User $user): bool`.
- Produces: a `<surface_action>` context block containing server authority/provenance, action and identifiers, current canonical facts, and explicit proposed-fact/write-boundary instructions.

- [x] **Step 1: Write failing dispatch and prompt tests**

Prove a `surface_action` conversation uses `AdviceFyn` while `onboarding_completed=false`; the original `fyn_onboarding` conversation still uses `OnboardingChatDirector`; queued-stream and action endpoints make the same decision; legacy metadata falls back to the existing user predicate; changing a model value after conversation creation changes the next assembled context; cross-user/deleted resource context fails closed without leaking data.

- [x] **Step 2: Run the focused tests and confirm RED**

Run:

```bash
./vendor/bin/pest tests/Feature/AI/ContextualConversationDispatchTest.php tests/Unit/Services/AI/Fyn/ContextualResourceContextTest.php
```

Expected: FAIL because dispatch is user-global and the trusted block is absent.

- [x] **Step 3: Implement immutable mode routing at all three controller seams**

Replace direct calls to the current user predicate in message, queued-stream, and action handling. `metadata.source=surface_action` always resolves to advice/capture; `metadata.source=fyn_onboarding` always resolves to onboarding; only untyped legacy conversations use the historical predicate.

- [x] **Step 4: Inject freshly resolved canonical context**

When `FynTurnContext::conversation` is contextual, reload the resource through the ownership-filtered resolver and render a sanitised `<surface_action>` sibling inside `<context>`. Include no request-supplied financial content. If the entity no longer resolves, render a safe unavailable-resource directive and canonical fallback rather than stale facts.

- [x] **Step 5: Run regression suites**

Run:

```bash
./vendor/bin/pest tests/Feature/AI/ContextualConversationDispatchTest.php tests/Unit/Services/AI/Fyn/ContextualResourceContextTest.php tests/Feature/AI/CampaignReentryDispatchTest.php tests/Feature/Onboarding/OnboardingInterruptionTest.php tests/Feature/Fyn/DispatchRoutingTest.php
./vendor/bin/pint app/Http/Controllers/Api/AiChatController.php app/Services/AI/ContextualConversation app/Services/AI/Fyn/FynContextAssembler.php tests/Feature/AI/ContextualConversationDispatchTest.php tests/Unit/Services/AI/Fyn/ContextualResourceContextTest.php
```

Expected: PASS.

- [x] **Step 6: Commit mode isolation and rehydration**

Run: `git add app/Http/Controllers/Api/AiChatController.php app/Services/AI/ContextualConversation app/Services/AI/Fyn/FynContextAssembler.php tests/Feature/AI/ContextualConversationDispatchTest.php tests/Unit/Services/AI/Fyn/ContextualResourceContextTest.php tests/Feature/AI/CampaignReentryDispatchTest.php && git commit -m "fix: isolate and rehydrate contextual Fyn turns"`

---

### Task 4: Enrich the shared Conversation History contract

**Files:**
- Create: `app/Services/AI/ContextualConversation/ConversationHistoryService.php`
- Modify: `app/Http/Controllers/Api/AiChatController.php`
- Modify: `app/Constants/GateRoutes.php`
- Modify: `tests/Feature/AI/ConversationIndexPopulationTest.php`
- Modify: `tests/Architecture/GateRoutesTest.php`

**Interfaces:**
- Extends each index item with: `mode`, `purpose`, `related_entity`, `status`, `created_at`, `updated_at`, `last_message_at`, `last_message_summary`, and `fallback_destination`.
- Adds semantic screen: `conversation_history` with mobile path `/conversation-history`.

- [ ] **Step 1: Add failing history tests**

Create active/paused onboarding, contextual, and legacy conversations. Assert grouping fields, truncation-safe last-message summaries, no system/tool content, no cross-user rows, stable ordering, and a deleted/inaccessible contextual entity returning `related_entity.available=false` with the canonical overview fallback.

- [ ] **Step 2: Run focused tests and confirm RED**

Run: `./vendor/bin/pest tests/Feature/AI/ConversationIndexPopulationTest.php tests/Architecture/GateRoutesTest.php`

Expected: FAIL because enriched fields and destination are absent.

- [ ] **Step 3: Implement history projection without exposing financial values**

Use a constrained latest visible-message relationship or grouped query to avoid N+1 reads. Derive mode and purpose from typed metadata. Re-resolve only availability/label for contextual entities and produce the safe semantic fallback when unavailable.

- [ ] **Step 4: Run tests and formatter**

Run:

```bash
./vendor/bin/pest tests/Feature/AI/ConversationIndexPopulationTest.php tests/Architecture/GateRoutesTest.php tests/Feature/Fyn/InactivityPauseTest.php
./vendor/bin/pint app/Constants/GateRoutes.php app/Http/Controllers/Api/AiChatController.php app/Services/AI/ContextualConversation/ConversationHistoryService.php tests/Feature/AI/ConversationIndexPopulationTest.php tests/Architecture/GateRoutesTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit the history projection**

Run: `git add app/Constants/GateRoutes.php app/Http/Controllers/Api/AiChatController.php app/Services/AI/ContextualConversation/ConversationHistoryService.php tests/Feature/AI/ConversationIndexPopulationTest.php tests/Architecture/GateRoutesTest.php && git commit -m "feat: expose safe conversation history metadata"`

---

### Task 5: Replace `/m` client-authored Add/Edit prompts with typed launches

**Files:**
- Create: `resources/mobile/fyn/contextualConversation.js`
- Create: `resources/mobile/fyn/__tests__/contextualConversation.spec.js`
- Modify: `resources/mobile/mixins/onboardingChat.js`
- Modify: `resources/mobile/components/MobileChrome.vue`
- Modify: `resources/mobile/components/__tests__/MobileChrome.spec.js`
- Modify: `resources/mobile/views/PersonalInformation.vue`
- Modify: `resources/mobile/views/modules/Savings.vue`
- Modify: `resources/mobile/views/modules/SavingsAccount.vue`
- Modify: `resources/mobile/views/modules/Investment.vue`
- Modify: `resources/mobile/views/modules/InvestmentAccount.vue`
- Modify: `resources/mobile/views/modules/Retirement.vue`
- Modify: `resources/mobile/views/modules/RetirementPension.vue`
- Modify: `resources/mobile/views/modules/Protection.vue`
- Modify: `resources/mobile/views/modules/ProtectionPolicy.vue`
- Modify: `resources/mobile/views/modules/Goals.vue`
- Delete: `resources/mobile/utils/editPrompt.js`
- Modify: affected tests under `resources/mobile/views/**/__tests__` and `tests/frontend/mobile/`

**Interfaces:**
- Produces: `buildContextualConversationRequest({action, resourceType, resourceId, currentDestination, origin})`.
- Produces mixin methods: `createContextualConversation(request)` and `openConversation(conversationID)`.
- Produces component method: `openContextualFyn(request)`.

- [ ] **Step 1: Write failing JavaScript contract/component tests**

Assert exact snake_case JSON, no balance/value/name/prompt fields, fresh POST on every Add/Edit tap, returned opening shown without sending a second user message, creation failure leaves the current screen visible with retry, contextual launch does not resume onboarding even when onboarding is active, and explicit history loading opens the requested transcript.

- [ ] **Step 2: Run the focused tests and confirm RED**

Run:

```bash
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- resources/mobile/fyn/__tests__/contextualConversation.spec.js resources/mobile/components/__tests__/MobileChrome.spec.js resources/mobile/mixins/__tests__/onboardingChat.spec.js
```

Expected: FAIL because typed launch support is absent.

- [ ] **Step 3: Implement the shared `/m` launcher**

Reset transcript/conversation state, POST the contract, set the returned ID, load the persisted transcript, and open the dock. Keep generic chat and onboarding resume intact. Replace `editPrompt` props and `openFynWith()` calls at supported Add/Edit entry points with identifiers and semantic destinations; delete the name-bearing prompt builder after all imports are removed.

- [ ] **Step 4: Run focused and full mobile tests**

Run:

```bash
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- resources/mobile tests/frontend/mobile
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run build:mobile
```

Expected: PASS and production mobile build succeeds.

- [ ] **Step 5: Commit `/m` contextual launch parity**

Run: `git add resources/mobile tests/frontend/mobile && git commit -m "feat: launch trusted contextual Fyn on mobile web"`

---

### Task 6: Add `/m` Conversation History navigation and UI

**Files:**
- Create: `resources/mobile/views/ConversationHistory.vue`
- Create: `resources/mobile/views/__tests__/ConversationHistory.spec.js`
- Modify: `resources/mobile/router.js`
- Modify: `resources/mobile/navigation/navigationModel.js`
- Modify: `resources/mobile/navigation/semanticDestinations.js`
- Modify: `resources/mobile/navigation/__tests__/navigationModel.spec.js`
- Modify: `resources/mobile/navigation/__tests__/semanticDestinations.spec.js`

**Interfaces:**
- Adds route: `/conversation-history`.
- Displays separate Onboarding and Contextual sections with server-projected metadata.
- Opens available conversations by exact ID; unavailable related entities show the server explanation and route through the semantic fallback.

- [ ] **Step 1: Write failing route, navigation, and view tests**

Assert menu order places Conversation History after Achievements; semantic resolution; loading/error/empty states; separate sections; title/purpose/entity/status/time/summary rendering; exact transcript open; and deleted-entity fallback navigation.

- [ ] **Step 2: Run focused tests and confirm RED**

Run:

```bash
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:run -- resources/mobile/views/__tests__/ConversationHistory.spec.js resources/mobile/navigation/__tests__/navigationModel.spec.js resources/mobile/navigation/__tests__/semanticDestinations.spec.js
```

Expected: FAIL because the route/view/menu entry do not exist.

- [ ] **Step 3: Implement the view and safe actions**

Use the existing authenticated API helpers and `MobileChrome`. Do not regroup or infer entity availability client-side; render the server contract. Opening a conversation calls `openConversation(id)`. The unavailable action resolves `fallback_destination` through the existing allowlisted semantic adapter.

- [ ] **Step 4: Run mobile tests and build**

Run the commands from Task 5 Step 4.

Expected: PASS.

- [ ] **Step 5: Commit `/m` history**

Run: `git add resources/mobile && git commit -m "feat: add mobile web conversation history"`

---

### Task 7: Add the native contextual conversation client and launch model

**Files:**
- Modify: `ios-native/Fynla/Features/Fyn/FynModels.swift`
- Modify: `ios-native/Fynla/Features/Fyn/FynClient.swift`
- Modify: `ios-native/Fynla/Features/Fyn/FynConversationModel.swift`
- Modify: `ios-native/FynlaTests/FynConversationModelTests.swift`
- Modify: `ios-native/FynlaTests/FinancialPresentationTests.swift`

**Interfaces:**
- Produces: `FynContextualConversationRequest`, `FynContextualConversationResponse`, and typed `FynContextualAction`.
- Adds `FynClient.createContextualConversation(_:)`.
- Adds `FynConversationModel.startContextual(_:)` and exact-ID `start(preferredID:)` history loading.

- [ ] **Step 1: Write failing Swift model/client tests**

Decode server fixtures; assert encoded requests contain identifiers only and no financial values or entity labels; assert every contextual start calls create even when an old conversation ID exists; assert returned opening/transcript is loaded; assert failure clears pending state and exposes retry without losing route context; assert generic onboarding start behaviour is unchanged.

- [ ] **Step 2: Run focused native tests and confirm RED**

Run:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -parallel-testing-enabled NO \
  -only-testing:FynlaTests/FynConversationModelTests \
  -only-testing:FynlaTests/FinancialPresentationTests test \
  COMPILER_INDEX_STORE_ENABLE=NO
```

Expected: compile/test failure because typed models and client methods do not exist.

- [ ] **Step 3: Implement Codable models, endpoint, and reset-before-create flow**

Keep decoding additive/tolerant for older history responses. Cancel active streaming before a contextual create, clear stale messages and draft, apply the returned ID, and load the persisted transcript. Do not manufacture an opening locally.

- [ ] **Step 4: Run focused tests and a staging build**

Run the test command above, then:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'generic/platform=iOS Simulator' build \
  COMPILER_INDEX_STORE_ENABLE=NO
```

Expected: PASS and build succeeds.

- [ ] **Step 5: Commit the native contract**

Run: `git add ios-native/Fynla/Features/Fyn ios-native/FynlaTests/FynConversationModelTests.swift ios-native/FynlaTests/FinancialPresentationTests.swift && git commit -m "feat: add native contextual Fyn client"`

---

### Task 8: Wire native Add/Edit entry points and Conversation History

**Files:**
- Replace: `ios-native/Fynla/Core/FynEditing/FynEditIntent.swift` with typed `FynContextualAction` construction (retain the path if project references require it).
- Modify: `ios-native/Fynla/App/AppRouter.swift`
- Modify: `ios-native/Fynla/App/AppRootView.swift`
- Modify: `ios-native/Fynla/Features/Navigation/NavigationDestinationFactory.swift`
- Modify: `ios-native/Fynla/Features/Navigation/NavigationMenuSection.swift`
- Modify: `ios-native/Fynla/Core/Navigation/SemanticDestination.swift`
- Create: `ios-native/Fynla/Features/Fyn/ConversationHistoryModel.swift`
- Create: `ios-native/Fynla/Features/Fyn/ConversationHistoryView.swift`
- Modify: supported overview/detail views under `ios-native/Fynla/Features/{PersonalInformation,Savings,Investment,Retirement,Protection,Goals}/`
- Modify: `ios-native/Fynla/App/FynlaApp.swift`
- Modify: `ios-native/FynlaTests/NavigationMenuTests.swift`
- Modify: `ios-native/FynlaTests/SemanticDestinationTests.swift`
- Create: `ios-native/FynlaTests/ConversationHistoryTests.swift`
- Modify: `ios-native/FynlaUITests/FynlaUITests.swift`

**Interfaces:**
- Adds `AppRoute.conversationHistory` and semantic screen `conversation_history`.
- Changes supported surface callbacks from prompt strings to typed `FynContextualAction`.
- Adds native history sections and safe unavailable-resource fallback navigation.

- [ ] **Step 1: Write failing navigation, history, and launch tests**

Assert the menu order; `/conversation-history` mapping; typed detail actions include the correct entity ID/type and no labels/values; overview actions include module context without client facts; each tap invokes `startContextual`; history groups/rendering and exact-ID open; inaccessible entities show the fallback action.

- [ ] **Step 2: Run focused tests and confirm RED**

Run:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -parallel-testing-enabled NO \
  -only-testing:FynlaTests/NavigationMenuTests \
  -only-testing:FynlaTests/SemanticDestinationTests \
  -only-testing:FynlaTests/ConversationHistoryTests \
  -only-testing:FynlaTests/FinancialPresentationTests test \
  COMPILER_INDEX_STORE_ENABLE=NO
```

Expected: compile/test failure because the history route/view and typed callbacks are absent.

- [ ] **Step 3: Implement typed launch wiring and history UI**

Have `AppRootView` distinguish generic presentation, contextual presentation, and exact history resume. Set `currentRoute`, invoke the matching model method, and present the cover without drafting client prose. Add Conversation History after Achievements. Render server fields without reconstructing financial context locally.

- [ ] **Step 4: Add the critical XCUITest journey**

Extend deterministic UI-test support so the simulator can prove: open a product detail, tap Edit, see a newly created contextual opening, close, repeat and receive a different conversation; open Conversation History; reopen the first contextual conversation; and navigate safely from an unavailable related entity.

- [ ] **Step 5: Run focused native suites and full UI test target**

Run the focused command above, then:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -parallel-testing-enabled NO test \
  COMPILER_INDEX_STORE_ENABLE=NO
```

Expected: all unit and UI tests pass, with only documented expected skips.

- [ ] **Step 6: Commit native surface parity**

Run: `git add ios-native && git commit -m "feat: add native contextual actions and history"`

---

### Task 9: Prove capture/write isolation and complete cross-surface acceptance

**Files:**
- Create: `tests/Feature/AI/ContextualCaptureWriteBoundaryTest.php`
- Create: `docs/testing/2026-08-10-contextual-fyn-conversation-history-evidence.md`
- Modify: any regression test or smallest production file required by a reproduced defect.

- [ ] **Step 1: Write and run the capture/write boundary regression**

Prove creating or opening a contextual conversation never mutates a financial model; unconfirmed user text remains an `AiMessage`; confirmed capture still delegates through the existing validated inline-capture path and preserves its audit/provenance; cross-user identifiers never reach capture context.

Run: `./vendor/bin/pest tests/Feature/AI/ContextualCaptureWriteBoundaryTest.php`

Expected: RED first, then PASS after only the smallest necessary boundary fix.

- [ ] **Step 2: Run server, frontend, architecture, and build verification**

Run:

```bash
./vendor/bin/pest tests/Feature/AI tests/Feature/Fyn tests/Feature/Onboarding tests/Feature/Contracts/ClientCompatibilityContractTest.php tests/Architecture/GateRoutesTest.php
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run test:frontend
env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/bin:/bin:/usr/sbin:/sbin npm run build:mobile
git diff --check
```

Expected: PASS from fresh output.

- [ ] **Step 3: Run the `/m` journey in installed Google Chrome**

Using the seeded scenario, verify onboarding resume, contextual Add/Edit during active onboarding, repeated fresh conversations, history grouping, exact transcript resume, inaccessible-resource fallback, failure/retry, and that request payloads contain identifiers only. Capture screenshots and Chrome network evidence in the evidence document.

- [ ] **Step 4: Run the equivalent iOS Simulator journey**

Use the open Xcode installation and a dedicated simulator without altering the user's PR1 Xcode worktree. Exercise the same journey and record device/application logs and screenshots.

- [ ] **Step 5: Loop every discovered issue to green**

For each failure, record route/persona, expected, actual, classification, and logs; reproduce in isolation; add a failing regression; diagnose root cause; apply the smallest fix; rerun the isolated test and full affected journey. Continue until no in-scope defect remains.

- [ ] **Step 6: Run final native verification**

Run:

```bash
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging \
  -destination 'platform=iOS Simulator,name=Fynla iPhone 16 Pro iOS 18.6' \
  -parallel-testing-enabled NO test \
  COMPILER_INDEX_STORE_ENABLE=NO
xcodebuild -project ios-native/Fynla.xcodeproj -scheme Fynla-Production \
  -destination 'generic/platform=iOS' CODE_SIGNING_ALLOWED=NO build \
  COMPILER_INDEX_STORE_ENABLE=NO
```

Expected: tests and unsigned production build pass from fresh output.

- [ ] **Step 7: Final review, commit evidence, and publish PR2**

Run:

```bash
git status --short --branch
git diff origin/dev...HEAD --check
git diff --stat origin/dev...HEAD
git add tests/Feature/AI/ContextualCaptureWriteBoundaryTest.php docs/testing/2026-08-10-contextual-fyn-conversation-history-evidence.md
git commit -m "test: verify contextual Fyn parity journeys"
git push -u origin codex/ios-mobile-contextual-fyn
```

Request a code review, resolve every actionable finding with tests, push the final branch, create a ready pull request against `dev`, monitor every required check, repair any failure, and merge only when GitHub reports the PR mergeable and all checks green.

## Self-review checklist

- [ ] Every PR2 bullet and M-08 partial/M-11/M-31/M-32 has an implementation task, automated regression, and acceptance evidence.
- [ ] No task sends client-authored financial facts or entity labels as authoritative context.
- [ ] No contextual metadata stores a financial snapshot; each turn rehydrates from owned canonical models.
- [ ] Message, queued-stream, and action dispatch all use the same immutable mode resolver.
- [ ] The existing onboarding conversation and validated capture/write path are regression-covered.
- [ ] Deleted/cross-user entities fail safely in both history and turn assembly.
- [ ] `/m` and Swift use the same field names and semantic screens.
- [ ] No placeholder, TODO, TBD, destructive migration, or unapproved phase work remains.
