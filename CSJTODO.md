# CSJTODO — Fynla

*Last updated: 25 April 2026 — session 78 (Sprint 0 Tasks 0.15 + 0.16a complete)*
*Previous session: 25 April 2026 — session 77 (Sprint 0 Tasks 0.12 + 0.13 + 0.14 complete)*

---

## Session 78 (25 April evening) — Sprint 0 Tasks 0.15 + 0.16a complete

### Completed

#### Sprint 0 Task 0.15 — Coverage-gap tests for 7 small invariants — **DONE** (`503ac99`)

Closes the Sprint 0 invariant test surface by pinning the seven small properties that didn't justify their own task: INV-2.2.4 / 2.2.5 / 2.2.6 / 2.4.3 / 2.6.1 / 2.6.2 / 2.7.4. Three small implementation gaps closed behind the new tests rather than left aspirational.

- [x] **`config/onboarding.php` `journey_map`** (INV-2.2.5) — `['budgeting' => 'budgeting', 'goals' => 'goals', 'protection' => 'protection', 'retirement' => 'retirement']`. Block comment documents the contract: adding a new entry source requires only a config change, no controller modification.
- [x] **`AiChatController::startOnboarding` lookup** (INV-2.2.5) — reads `request->from`, looks up `journey_map`. On match: pre-sets `onboarding_fyn_path='journey'`, `onboarding_fyn_selection=<journey>`, `onboarding_fyn_step=STATE_BASE_PERSONAL`, hands the resolved state to `emitFirstTurn`. On unknown / missing `from`: falls through to STATE_PATH_CHOICE per spec.
- [x] **`OnboardingChatDirector::emitFirstTurn` signature** — gained optional `?string $stateId = null` parameter (defaults to STATE_PATH_CHOICE). Only one caller (the controller), so no other call sites needed updating.
- [x] **`OnboardingChatDirector::flushParkedFactsForState` (new private method)** (INV-2.2.6) — maps `STATE_BASE_PERSONAL/SPOUSE/DEPENDANTS_DETAIL/WORK/EXPENDITURE → personal/spouse/dependants/employment/expenditure` buckets via a `match` expression. Defensive null/empty checks. Wired into 3 commit points: free-text persistence (`handleUserMessage` after `persistCapture`), grouped-extract success (`handleGroupedExtractTurn` after `recordProgress`), and parking-driven hydration (`hydrateFromParking` after `recordProgress`). Sets the JSON column to `null` when the last bucket is flushed.
- [x] **`AiChatPanel.vue` capture_complete border-colour alignment** (INV-2.4.3) — replaced `border-horizon-200` with `border-light-gray` in BOTH render branches (inline + docked) so the outer container's class set matches the regular assistant `messageClass()` baseline. INV-2.4.3 explicitly bans "distinct border colour" on capture_complete bubbles.
- [x] **Tests** (8 files, 51 cases, 213 assertions):
  - `tests/Feature/Onboarding/ResumeAfterDisconnectTest.php` (17 cases) — pins resume-action contract, welcome-back metadata persistence, per-state describeStep label coverage across 13 STATE_* constants + unknown fallback + no-saved-step error
  - `tests/Feature/Onboarding/EntrySourceJourneyMapTest.php` (8 cases) — canonical 4-entry map, all known mappings, unknown / missing `from`, runtime-added entries (config-driven)
  - `tests/Feature/Onboarding/ParkedFactsFlushTest.php` (5 cases) — flush via integration on STATE_BASE_EXPENDITURE, no-flush on out-of-mapping states, null-when-empty (via reflection on the private method to bypass the OnboardingFactExtractor's incidental message parking), no-op when nothing parked, sibling-bucket survival
  - `tests/Feature/Fyn/CaptureCompleteStylingTest.php` (3 cases) — border / background match against `messageClass()` baseline, no capture-mode tells (ring / outline / SVG / icon-font), same outer flex alignment
  - `tests/Feature/AI/ReadCompletenessTest.php` (5 cases) — 60-record seeds for savings_account / life_insurance / goals / life_events plus cross-user isolation
  - `tests/Feature/AI/GetRecommendationsCompletenessTest.php` (3 cases) — every metadata field round-trips byte-for-byte (anonymous-class subclass of `CoordinatingAgent` stubs `orchestrateAnalysis` to bypass the engine), nested arrays preserved, empty-list path
  - `tests/Architecture/PreviewModeToolCatalogueTest.php` (5 cases) — provider parity in preview, zero write tools on either provider (29 banned tool names checked), strict subset of non-preview, 10 canonical read / nav / billing tools retained

**Note on `handleModuleAnalysis`:** the INV-2.6.1 spec text additionally calls for `handleModuleAnalysis` to bypass `summariseToolAnalysis`, but the S0.15 plan task only scoped the list-handler completeness. The handler still wraps via `summariseToolAnalysis` at `app/Agents/CoordinatingAgent.php:1512`. Flagged in the S0.15 delivery note + W2 below as a deferred behavioural change with a broader test surface.

#### Sprint 0 Task 0.16a — Browser harness skeleton + 20 BS-NN scenario stubs — **DONE** (`bc855fd`)

S0.16 split into a (scaffolding) + b (interactive execution) because the Playwright MCP tools are agent-driven, NOT callable from `vendor/bin/pest`. The "20 scenarios" are scripts Claude reads + walks through interactively in a future session, not a CI suite.

- [x] **Harness:**
  - `tests/Browser/TestCase.php` — Pest base, `markPendingInteractiveRun()` skip helper (canonical skip path with a clear message pointing at S0.16b), `browserHealthcheck()` via Laravel `Http` facade (no `curl_exec` to keep the security hook quiet)
  - `tests/Browser/Helpers/Login.php` — login flow doc + DB plumbing for the local-dev MFA-code lookup. Actual `browser_*` Playwright calls live in the BS-NN scenario scripts; this helper documents the canonical sequence and exposes `latestVerificationCode($email)` for the MFA bridge.
  - `tests/Browser/Helpers/AssertSseEvents.php` — pure-PHP SSE event parsing + assertions: `fromNetworkRequests` (filters / decodes the chat-stream body from the MCP's `browser_network_requests` output), `assertNoEventType`, `assertEventTypeCount`, `assertEventTypeEmitted`, `windowBetween` (slice events between two type bookends — used by BS-11 to isolate the inline-capture sub-turn).
  - `tests/Browser/README.md` — explains why this is not a CI-runnable suite, how to drive a scenario interactively, the screenshot-naming convention, and the browser-testing law inheritance from root `CLAUDE.md` + memory.
- [x] **20 stub files** under `tests/Browser/scenarios/`: BS-01, 02, 04, 05, 06, 07, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23. Each carries the full spec script + assertion list as comments and a single `it()` block that calls `markPendingInteractiveRun()`. Sprint 1 BS-NN (BS-03/08/09/24) deliberately excluded — Sprint 1 Task 1.9 owns those.
- [x] **20 screenshot drop-target folders** under `docs/sprint-0-verification/BS-NN/` with `.gitkeep`.
- [x] **Suite registration:** `phpunit.xml` adds a `Browser` test suite with `suffix=".php"` override (BS-NN filenames don't end in `*Test.php` per spec); `tests/Pest.php` binds `Tests\Browser\TestCase::class` to `Browser/scenarios`. `vendor/bin/pest --testsuite=Browser` reports 20 skipped, 0 assertions, 0 failures.

#### Test results (cumulative session-78)

- AI / Fyn / Onboarding / Audit / Architecture / Unit-Constants / Unit-Services-AI / Unit-Services-Onboarding sweep: **735 / 735 passing (2833 assertions, 0 failures)** — +51 new tests vs session 77's 495.
- Browser suite: **20 skipped, 0 failures** (interactive execution pending — S0.16b).
- No new tables, no new migrations, no new artisan commands, no new scheduled jobs.

#### Plan + spec status (Sprint 0)

`April/April24Updates/plan/10-sprint-0-plan.md` updated: S0.16 split into S0.16a (✓ scaffolding done at `bc855fd`) + S0.16b (☐ interactive Playwright execution — pending). S0.15 ticked with full delivery note documenting all 4 small code edits + 8 test files. `April/April24Updates/spec/10-sprint-0-plan.md` reference-only, never edited per project convention.

```
✓ S0.1  Rebase onto main
✓ S0.2  Delete OpenAI sidecar
✓ S0.3  Two-Fyn collapse
✓ S0.4  Remove visible-handoff UI
✓ S0.5  17 fill_form → direct-write (a-q)
✓ S0.6  Billing tools (3, parity 40/40)
✓ S0.7  update_record allowlist + strict schema
✓ S0.8  delete_record two-phase confirmation
✓ S0.9  Consent runtime check
✓ S0.10 User-content sanitisation + structural separation
✓ S0.11 Reliability bundle (6 sub-steps)
✓ S0.12 Hash-chain audit
✓ S0.13 CoreIdentity rewrite + FCA signposting
✓ S0.14 Out-of-remit canonical refusal
✓ S0.15 Coverage-gap tests (7 invariants)            ← session 78
✓ S0.16a Browser harness + 20 scenario stubs         ← session 78
☐ S0.16b Interactive Playwright execution (20 scenarios)
☐ S0.17 Verification rollup + Rubric-A re-score
```

### NOT Done — Outstanding for next session

- [ ] **Sprint 0 Task 0.16b — Drive every BS-NN scenario stub through the Playwright MCP browser tools end-to-end against `./dev.sh`.** ← start here next session.
  - Pre-flight: `./dev.sh` running on :8000 / :5173, `php artisan db:seed`, `mcp__playwright__*` tools available, browser test law fresh in mind (click + fill + submit + verify, no shortcuts).
  - For each BS-NN: walk the script in the stub file, capture `browser_take_screenshot` per assertion checkpoint into `docs/sprint-0-verification/BS-NN/<step>.png`, pin every SSE / DB / DOM assertion the stub spec calls for, then update each stub's docblock with a short delivery note (date + green/red + any flake notes).
  - **Per browser-testing law (memory `critical_browser_testing_law.md` + `feedback_never_claim_verified.md`):** "20/20 PASS" claim ONLY after every scenario has been clicked / filled / submitted / verified in Playwright. No partial-evidence success. If a scenario fails, route through a dedicated Sprint 0 bug-fix sub-task against the relevant file, then re-run.
  - Estimated time: 4-8 hours of interactive session time depending on scenario complexity. Some scenarios chain (BS-17 + BS-19 share seed; BS-11 + BS-12 share trigger flow).
- [ ] **Sprint 0 Task 0.17 — Sprint 0 verification rollup + Rubric-A re-score.** Only after S0.16b green.
  - Full Pest sweep: `./vendor/bin/pest` → all green.
  - Architecture suite: `./vendor/bin/pest --testsuite=Architecture` → all green.
  - `php artisan ai:audit:verify-chain` → `{chain_valid: true, ...}`.
  - Browser matrix 20/20 PASS with screenshots committed.
  - Rubric-A re-score document at `docs/sprint-0-verification/rubric-a-score.md` — dimension-by-dimension, target 13–15/40 per spec.
  - Open PR `feature/fyn-persona-split → dev` linking to verification evidence.

### Context for Next Session

Sprint 0 invariant test surface is closed (S0.15) and the browser-test scaffolding is in place (S0.16a). The remaining work before Sprint 0 ships is interactive: drive the 20 BS-NN scenarios through Playwright MCP and capture evidence. Per memory `feedback_main_via_dev_only.md`, work continues to flow `feature/fyn-persona-split → dev → main` — do NOT merge to `dev` until S0.17 has Rubric-A re-scored 13–15/40 with all 20 browser scenarios green and screenshots committed.

After Sprint 0 closes, Sprint 1 opens with the eval harness + memory model + `<known_facts>` block. The W1/W2 carry-overs below should be addressed before the dev-→-main release PR (W1 is a future-only collision risk, W2 is a partial-INV-2.6.1 gap that needs its own follow-up sub-task).

---

## Outstanding — Tech Debt (deferred from session 78 audit)

From session-78 tech-debt-report.md (0 critical, 2 warnings, 0 suggestions):

- [ ] **W1 — Generic global helper function names (collision risk).** `function invokeProtectedMethod(...)` in `tests/Feature/AI/ReadCompletenessTest.php:121` and `function makeUserAtState(...)` in `tests/Feature/Onboarding/ParkedFactsFlushTest.php:25`. Both reusable-sounding names with no scenario-prefix; future tests could redeclare them and trigger the same fatal global-namespace collision the session 76 commit `567b8cf` ("rename makeRequest helper") fixed. Existing convention: scenario-prefixed names (`grantAiChatConsentForOnboardingEndpointTest`, `makeIdempotencyTestRequest`, `callGetAiProviderForLoop`). Fix: rename to scenario-prefixed forms OR hoist `invokeProtectedMethod` into `Tests\TestCase` since the reflection trick is generally useful. Failure mode is loud (PHP fatal at autoload), so a regression surfaces immediately — not blocking, but worth fixing before the next file in the same area lands.
- [ ] **W2 — `handleModuleAnalysis` carry-over (INV-2.6.1 partial).** INV-2.6.1's spec text says: *"`handleModuleAnalysis` returns the raw `analyze()` output for the requested module — no `summariseToolAnalysis` stripping for this handler."* The handler still returns `$this->summariseToolAnalysis($module, $analysis)` at `app/Agents/CoordinatingAgent.php:1512` rather than the raw `$analysis`. The S0.15 plan task only scoped the list-handler completeness. **Open a follow-up sub-task before S0.17 verification rollup** — switch `handleModuleAnalysis` to return raw `$analysis` and audit existing call-sites that may assume the summarised shape. Not a Sprint 0 blocker for the rest of the work but should not slip into Sprint 1 unscoped.

## Outstanding — Tech Debt carried from earlier sessions

From session-77 tech-debt-report.md:

- [ ] **W1 (session 77)** — `summariseInput` records tool input verbatim on the chain. PII (DOB, email, postcode) lands in `ai_audit_events.input_summary` for tools like `capture_personal_details` / `update_profile` / `update_record`. **Critical to do BEFORE the chain accumulates real production data — once written, redacting later breaks every subsequent hash.** Add a per-tool field redaction list inside `summariseInput` BEFORE the value reaches the chain row.
- [ ] **S1 (session 77)** — `appendAuditEvent` swallows all `Throwable`. Intentional but the catch-all hides bugs. Add an alert path so the weekly `ai:audit:verify-chain` health check can surface append-failure counts. Sprint 1.
- [ ] **S2 (session 77)** — Onboarding gap-fill call sites pass `conversation_id = null`. `OnboardingChatDirector` lines 1747 + 2148. Thread `AiConversation $conversation` through both methods. Sprint 1.
- [ ] **S3 (session 77)** — Pre-existing: `border-3` in `AiAudit.vue` spinners is a non-standard Tailwind class. Replace with `border-4`. One-line cleanup.

From session 76 tech-debt-report.md:

- [ ] **S1 (session 76)** — Duplicated provider-resolution lookup in `AdminController::getAiProvider`. Extract to `ProviderResolver::current()` static helper. Sprint 1.
- [ ] **S2 (session 76)** — `AssetCaptureEntityExtractor` now 828 lines. Split when Sprint 1's eval harness opens it.
- [ ] **S3 (session 76)** — `$fromLlm` parameter on identity-key methods is dead (pre-existing). Drop in the same split as S2.
- [ ] **S4 (session 76)** — `ai:usage:backfill` artisan command silently `updateOrCreate`s every row on rerun. Add `--dry-run` + counters when next touched.

From session 75 tech-debt-report.md:

- [ ] **S1 (session 75)** — Mid-stream consent re-check fires one DB query per SSE event. Throttle (every Nth event or once every 5s) when next touched.
- [ ] **S2 (session 75)** — Duplicated "grant ai_chat consent" helper across now 5 test files (S0.15 added a 5th — `grantAiChatConsentForJourneyMapTest`). **Threshold reached** — extract to a test trait next session.

## Known Issues

- None new from session 78.

## Deploy Status

- `feature/fyn-persona-split` is now 2 commits ahead of session 77's tip (`503ac99` + `bc855fd`); both pushed to `origin/feature/fyn-persona-split` at session-end.
- **Not yet on `dev`.** Per memory `feedback_main_via_dev_only.md`, work flows `feature/fyn-persona-split → dev → main`. Open PR `feature/fyn-persona-split → dev` only AFTER Sprint 0 closes (S0.16b + S0.17 complete with browser matrix + Rubric-A re-score).
- **No deploy guide for session 78** — both commits are tests / scaffolding only (S0.15 production code is 4 files: `config/onboarding.php`, `app/Http/Controllers/Api/AiChatController.php`, `app/Services/Onboarding/OnboardingChatDirector.php`, `resources/js/components/Shared/AiChatPanel.vue` — all small additive changes that will roll up into the eventual S0.17 verification PR rather than a standalone dev push).

---

*Generated by `/session-end` skill — 25 April 2026, session 78.*
