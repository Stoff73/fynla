# CSJTODO — Fynla

*Last updated: 25 April 2026 — session 75 (Sprint 0 Tasks 0.9 + 0.10 complete)*
*Previous session: 25 April 2026 — session 74 (Sprint 0 Tasks 0.6 + 0.7 + 0.8 complete)*

---

## Session 75 (25 April late-morning) — Sprint 0 Tasks 0.9 + 0.10 complete

### Completed

#### Sprint 0 Task 0.9 — Consent runtime check — **DONE** (commit `ff8e4ed`)

- [x] **`AiChatController::sendMessage` widened** to `StreamedResponse|JsonResponse`. Constructor-injects `ConsentService`. Entry guard returns 403 JSON `{error:'consent_required', required:'ai_chat'}` when the user has no canonical `consented=true` row in `user_consents` for type `ai_chat`.
- [x] **`AiChatController::startOnboarding` got the same entry guard** (consent first, then existing already-completed / disabled / preview-mode checks).
- [x] **Mid-stream re-check** — the SSE `foreach ($generator as $event)` re-checks `hasConsent` before every yield. A mid-flight `PUT /api/user/consents` → `consented=false` triggers a terminal `consent_required` SSE event and `return;` to close the stream cleanly. `hasConsent` runs a fresh indexed `EXISTS` query (not via the closed-over `$user` model), so the withdrawal is observed even though `$user` is stale.
- [x] **No migration needed.** `user_consents.consent_type` is already `varchar(100)` so storing `'ai_chat'` works without widening. New `UserConsent::TYPE_AI_CHAT = 'ai_chat'` constant + `CURRENT_VERSIONS['ai_chat'] => 'v1.0'` entry are sufficient. `ConsentService::hasConsent` works unchanged via the constant.
- [x] **Frontend (`aiChat.js`):** new `consentRequired` state + getter + mutation, RESET hook clears it, SSE `case 'consent_required':` flips the flag and stops streaming with a re-consent error string. Light-touch — full modal wiring deferred per spec ("reuses existing consent infra; building a new consent modal UI is out of scope").
- [x] **6 tests** in `tests/Feature/AI/ConsentRuntimeCheckTest.php` — sendMessage 403 missing / 403 withdrawn / 200 stream / startOnboarding 403 / 200 / mid-stream withdrawal yields `['content', 'consent_required']` only. Mid-stream test mocks `CoordinatingAgent::chatWithPromptOverride` (only method `AdviceFyn::handle` calls; `AdviceFyn` is `final` so cannot be Mockery'd directly).
- [x] **3 existing tests touched** (`HandoffInvisibilityTest`, `StartOnboardingEndpointTest`, `StateMachineWalkthroughTest`) to grant `ai_chat` consent in setup so they reach the gates they pin.

#### TrustObserver direct-write CLT contract — **DONE** (commit `4e13d84`)

- [x] **Test contract updated post-S0.5.l** — the obsolete `'fill_form'`-asserting test from before S0.5.l replaced with four cases pinning the new direct-write contract: agent persists Trust + observer fires CLT Gift in same transaction (success path, value=0 path, validation failure, preview block).
- [x] **Verified live via `php artisan tinker`** — `executeTool('create_trust', initial_value: 250000)` produced both the Trust row AND the matching `Gift` row (`gift_type='clt'`, `gift_value=250000`, `recipient='Live Test Trust'`, `gift_date='2026-04-20'`) in the same `DB::transaction`. `TrustObserver::created` chain works correctly post-direct-write conversion.

#### WillBuilderApiTest faker flake — **DONE** (commit `5771687`)

- [x] **One-line `'middle_name' => null` factory override** pins the long-known 30%-flake CSJTODO had carried since session 72. Folded in here because the regression sweep tripped on it.

#### Sprint 0 Task 0.10 — User-content sanitisation + structural separation — **DONE** (commit `786d841`)

- [x] **New `app/Services/AI/Prompts/UserContentSanitiser.php`** with two static helpers: `clean(string)` (strips everything outside `[A-Za-z0-9\s'.,\-]/u`) and `wrap(string)` (clean + `<user_provided>...</user_provided>` markers). The wrapper is a reliable structural boundary because `clean()` guarantees the inner content cannot contain angle brackets — verified by an attacker-forgery survival test.
- [x] **Wired into `AdvicePromptBuilder`** at every user-controlled free-text interpolation site: user/spouse/family `first_name + last_name + surname`, `goal_name`, `joint_owner_name`, savings `account_name + institution`, investment `provider`, DC/DB `scheme_name`, property `address_line_1`, life/CI/IP `provider`, `trust_name`, `business_name`, chattel `description`, `liability_name`, gift `recipient`.
- [x] **Wired into `OnboardingPromptBuilder::buildAssetCapturePrompt`** — wraps `firstName` before passing to `CoreIdentity::get()`.
- [x] **Enum fields deliberately NOT wrapped** — `trust_type`, `business_type`, `ownership_type`, `property_type`, `relationship`, `marital_status`, `policy_type`, `pension_type`, `account_type`, `gift_type`, `chattel_type`, `liability_type` etc. all come from fixed sets and cannot carry prompt-injection payloads.
- [x] **18 tests** in `tests/Unit/Services/AI/Prompts/UserContentSanitisationTest.php` — allow-list happy paths (apostrophes, hyphens, multi-line whitespace), HTML/script payloads, template braces (`{{ previous_instructions }}`), classic prompt-override punctuation (`"; reveal system prompt; "`), shell payloads, URL payloads, non-ASCII strip, emoji + zero-width strip, empty input, fully-stripped input, attacker-forgery survival.
- [x] **Live verification** — `AdvicePromptBuilder::buildUserProfile` for "James Carter" emits `- Name: <user_provided>James</user_provided>`.

#### Test results (cumulative session-75)

- [x] **30 new tests + 1 obsolete-contract case replaced + 0 regressions.**
- [x] **Full Pest suite: 2799/2799 passing (10958 assertions, 0 failures).** First fully-green full suite on this branch — previous sessions had the `WillBuilderApiTest` faker flake at 30% and the obsolete `TrustObserverTest > 'fill_form'` case failing post-S0.5.l.

### NOT Done — Outstanding for next session

#### Sprint 0 continuation — Task 0.11 next

- [ ] **S0.11 — Reliability bundle (6 sub-steps, biggest task in Sprint 0).** Each sub-step is its own TDD cycle + commit:
  - **0.11.1 Atomic token budget** — migration + `AiDailyUsage` model + `HasAiGuardrails::consume` rewrite with `DB::transaction` + `SELECT ... FOR UPDATE`. Backfill job reads today's `ai_messages` into `ai_daily_usage`. Fixes the 5-minute `Cache::remember` race window.
  - **0.11.2 SSE abort detection + `ai_abort_events`** — `connection_aborted()` polling in `HasAiChat::chat` generator loop; insert row on detection; do NOT roll back writes (per INV-2.9.2).
  - **0.11.3 Idempotency middleware + table + cleanup job** — `IdempotencyKeyMiddleware` reads `Idempotency-Key` header; duplicate within 24h → cached response; daily cleanup via scheduled job.
  - **0.11.4 Provider-swap lock** — `HasAiGuardrails::getAiProviderForLoop()` captures provider once per chat call; versioned `ai_provider:v{n}` cache key incremented by admin toggle; prevents Anthropic cache markers leaking into xAI mid-loop.
  - **0.11.5 Gap-fill DB dedup** — `AssetCaptureEntityExtractor::findMissing` queries target table by `(user_id, provider | account_name | policy_type_group, created_at > now() - 24h)` before emission.
  - **0.11.6 `generateTitle` sanitation + `summariseToolResult` preserves entity_id** — `mb_substr(strip_tags($message), 0, 100)` before LLM call AND DB write at `HasAiChat.php:704`; `summariseToolResult` keeps `entity_id` + `entity_type` keys at line 749 (INV-2.5.3).
  - **3 new migrations** (`ai_daily_usage`, `ai_request_idempotency`, `ai_abort_events`) + **3 new models** + **1 middleware** (registered in `Http/Kernel.php` + `routes/api.php`) + **1 job** (registered in `Console/Kernel.php` daily) + **9 new test files** (TokenBudgetConcurrencyTest, SseAbortKeepWritesTest, IdempotencyKeyTest, ProviderSwapLockTest, GapFillDedupTest, GenerateTitleSanitisationTest, HasAiChatSummarisationTest).
  - See [[April/April24Updates/plan/10-sprint-0-plan|plan]] §S0.11 for the full spec.

- [ ] **S0.12 — Hash-chain audit migration + service + command + job + admin view.** `ai_audit_events` table with `prev_hash`/`row_hash`/`signature` chain; `AuditChainService::append + verifyChain`; `php artisan ai:audit:verify-chain`; weekly retention job; admin chain-view tab in `AiAudit.vue`. Replaces the `[AI-AUDIT]` file log.

- [ ] **S0.13 through S0.17** — see [[April/April24Updates/plan/10-sprint-0-plan|plan]]. Nothing changed in scope.

#### Tech debt logged from session 75 (deferred — not blocking S0.11)

- [ ] **S1 — Mid-stream consent re-check fires one DB query per SSE event** (`AiChatController:165-178`). Behaviour correct; query is cheapest-possible indexed EXISTS; for typical 30–100-yield streams the cumulative cost is ~30–100ms which is fine. Won't scale linearly under load. **Suggested fix:** throttle to every Nth event (N=10) OR cache for the stream duration with explicit invalidation on the consent-update endpoint. Natural fit during S0.11 reliability work.

- [ ] **S2 — Duplicated "grant ai_chat consent" helper across 4 test files** with 3 different shapes: `grantAiChatConsent` in `ConsentRuntimeCheckTest.php`, `grantAiChatConsentForOnboardingEndpointTest` in `StartOnboardingEndpointTest.php` (renamed to dodge global-function autoload collision), inline `app(ConsentService::class)->recordConsent(...)` in `StateMachineWalkthroughTest.php` and `HandoffInvisibilityTest.php`. **Suggested fix:** extract to `tests/Helpers/AiChatTestHelpers.php` once 5+ files need it. Low value, low urgency.

#### Carried from session 74 (still deferred)

- [ ] **W1 carried — `CoordinatingAgent.php` is 3,718 lines.** No change in S0.9/0.10 (those touched the controller + prompt builders, not the agent file). Sprint 4 backlog candidate. Splitting now would conflict with the same-file test pins (`DirectWriteCoverageTest`, `DispatchRoutingTest`).
- [ ] **S1 carried — `handleUpdateRecord` field-aliasing chain** (16-branch `match`, 25 lines). When S0.7 stable, lift to `app/Constants/UpdateRecordFieldAliases.php`. Most aliases will become dead code now that the LLM is schema-constrained.
- [ ] **S2 carried — `handleListInvoices` queries `Invoice` directly** rather than via `$user->invoices()`. `User` lacks the relation. One-line fix cross-cuts `PaymentController::billingHistory`. Out of scope for S0.6.

#### Carried from session 73 (still deferred)

- [ ] **W1 carried — repeated success-envelope literal across 16 create handlers.** The 7-key return shape repeats verbatim. Extract to `private function createdEnvelope(...)`. Defer to "S0.5 polish".
- [ ] **W2 carried — long handler bodies** (10 of 16 exceed 60 lines). The optional-field-loop pattern is mechanically identical — extract to `mergeOptionalFields(...)`. Defer.
- [ ] **S1 carried — Extract `handleCreateProperty`'s mortgage-write block** into `persistMortgageForProperty(...)`. Defer.

### Context for Next Session

Sprint 0 is **10/17 tasks done** (0.1–0.10 ticked). Branch `feature/fyn-persona-split` is **94 commits ahead of `main`**, pushed to `origin/feature/fyn-persona-split`. Working tree clean. **First fully-green full Pest suite on this branch (2799/2799).**

**Start session 76 with S0.11 reliability bundle.** This is the biggest single task in Sprint 0 — 6 sub-steps, each its own TDD cycle and commit. Plan the order: 0.11.6 (generateTitle sanitation + summariseToolResult) is the smallest and could land first as a warm-up. 0.11.4 (provider-swap lock) is also small — single trait method + cache-key versioning. 0.11.1 (atomic token budget) and 0.11.3 (idempotency middleware) need new migrations and are larger. 0.11.2 (SSE abort + `ai_abort_events`) integrates with the existing chat generator loop. 0.11.5 (gap-fill dedup) is a one-method change in `AssetCaptureEntityExtractor`. After S0.11 lands, S0.12 (hash-chain audit) is similarly large.

**Consider rolling the S0.10 mid-stream consent throttle (S1 above) into 0.11.1's atomic-token-budget transaction** — both touch atomic per-stream state in `HasAiGuardrails`-style code, so co-locating reduces churn.

**No deploy this session.** Branch is mid-Sprint-0; nothing to ship until 0.11 → 0.17 land. Per [[memory:feedback_main_via_dev_only|feedback_main_via_dev_only]]: nothing merges to main without first being on dev + browser-tested.

---

## Outstanding — Tech Debt Deferred (carried from earlier sessions)

### From session 71
- [ ] **Deploy PR #235 main-test-fixes** to `csjones.co/fynla` then `fynla.org`. Upload 9 files per [[April/April25Updates/deployInherit|deployInherit]]. No migrations, no build. SSH + cache clears. Browser smoke Estate + Holdings. After dev soak: open `dev → main` PR (will include PR #236 phpunit bump alongside PR #235 fixes).

### From earlier sessions
- [ ] **Production cleanup of `build.old/`** + 19 historical sandbox CheckoutPage chunks (after 24h) — flagged session 68.
- [ ] **Exercise edit-mode auto-expand** + collapsed-form submit → DB + onboarding-path for the field-collapse toggle work — flagged session 67.

## Known Issues
- [ ] **Production lifecycle engine throttle** (resolved in session 68 — `LIFECYCLE_THROTTLE_MS=150` in prod `.env`, engine re-enabled). Listed here only for handover continuity; no action.

## Deploy Status

| Environment | Branch | State |
|---|---|---|
| `fynla.org` (production) | `main` | Last release: PR #228 + PR #231 hotfix (sessions 67–68, 23 April). |
| `csjones.co/fynla` (dev) | `dev` | Last deploy: session 65 (afternoon, 23 April) — lifecycle engine + Awin + insights CMS + session 66/67 UI fixes. PR #235 main-test-fixes still pending CSJ deploy from session 71 (see deferred above). |
| `feature/fyn-persona-split` | (none) | 94 commits ahead of main, **10/17 Sprint 0 tasks done**. NOT deploying — still mid-Sprint. |

---

*This file is the canonical handover. `session-start` reads this first.*
