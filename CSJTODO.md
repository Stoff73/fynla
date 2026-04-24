# CSJTODO — Fynla

*Last updated: 24 April 2026 — session 72 (context-clear: Sprint 0.2 sidecar deletion + phpunit 4G memory bump)*
*Previous session: 24 April 2026 — session 71 (Sprint 0.1 rebase + main-test-fixes PR #235)*

---

## Session 72 (24 April late evening) — Sprint 0 Task 0.2 + phpunit infra fix

**Context-clear wrap (not end-of-day)** — next session same day.

### Completed

#### Sprint 0 Task 0.2 — delete stale OpenAI config + dead Python sidecar (single commit `2b1f347`)
- [x] **15 files changed (+106 / −774)** on `feature/fyn-persona-split`.
- [x] Deleted `scripts/fynla_agent/` (6 files: `__init__.py`, `agent.py`, `config.py`, `hooks.py`, `schemas.py`, `tools.py`), `scripts/run_agent.py`, `scripts/requirements.txt`.
- [x] Deleted `app/Http/Controllers/Api/AgentInternalController.php` (6 privileged endpoints with `user_id`-query-param impersonation via shared secret) + `app/Http/Middleware/AgentTokenAuth.php` (`X-Agent-Token` header auth).
- [x] `config/services.php` — removed `openai` block (zero callers) AND dead `agent_internal_token` entries from `anthropic` + `xai` blocks (only `AgentTokenAuth` consumed them; required for arch test grep to pass — scope expansion beyond the plan's original "lines 34-38" but necessary and correct).
- [x] `routes/api.php` — removed `/api/internal/agent/*` prefix group (6 routes).
- [x] `app/Http/Kernel.php` — removed `'agent.token'` middleware alias.
- [x] `.env.example` — removed `AGENT_INTERNAL_TOKEN` entry (no `OPENAI_*` entries existed there; plan clause was a no-op).
- [x] Created `tests/Architecture/NoStaleReferencesTest.php` — 5 arch assertions grepping `app/` + `config/` + `routes/` for `AgentInternalController`, `AgentTokenAuth`, `AGENT_INTERNAL_TOKEN`, `OPENAI_CHAT_MODEL`, and `/internal/agent` route prefix. TDD: wrote test → failing 5/5 → applied deletions → passing 5/5.
- [x] Pushed to `origin/feature/fyn-persona-split`.
- [x] **Attack surface reduced**: no more shared-secret privileged endpoint with impersonation-by-query-param, no audit trail bypass. Per `audit-synthesis.md §8` CSJ decision 4.

#### PR #236 `feature/csj/phpunit-memory-bump` → `dev` → merged + deleted
- [x] Branch `feature/csj/phpunit-memory-bump` off `origin/dev`, single commit `58aeb47` bumping `phpunit.xml` `<ini name="memory_limit" value="1G" />` to `4G`.
- [x] 1G was causing OOM in `nikic/php-parser` during the Architecture phase (~2,500+ tests). 4G matches the session 71 operating baseline and CSJTODO note "240s at 4GB memory_limit".
- [x] Verified at 4G off dev: Architecture suite 94 tests / 195 assertions / 0 fail / 31s.
- [x] PR [#236](https://github.com/Stoff73/fynla/pull/236) admin-squash-merged to dev as `58aeb47`. Branch auto-deleted.
- [x] `origin/dev` merged into `feature/fyn-persona-split` as `0409272` (trivial, one-line phpunit.xml conflict-free merge) — local persona-split now has 4G too.

#### Full Pest suite on `feature/fyn-persona-split` (post-merge, 4GB)
- [x] **2,663 pass / 1 fail / 11,223 assertions / 205s.**
- [x] Architecture suite on dev baseline: **94 tests / 195 assertions / 0 fail / 31s.**
- [x] Architecture suite on persona-split (after 0.2): **99 tests / 200 assertions / 0 fail / 32s** — the +5 assertions/tests are the new `NoStaleReferencesTest`.

### NOT Done — Outstanding for next session

#### Known flake — `WillBuilderApiTest::pre-populate` (pre-existing, 30% fail rate)
- [ ] `tests/Feature/Estate/WillBuilderApiTest.php:17-21` creates user with only `first_name = 'James'` + `surname = 'Carter'`. `UserFactory.php:30` sets `middle_name` via `fake()->optional(0.3)->firstName()`. `User.php:292` concatenates first + middle + surname in `full_name` accessor. Test asserts `'James Carter'` → fails 30% of the time when faker rolls a middle name producing `'James Roberta Carter'`. **Fix: add `'middle_name' => null` to the factory override in the test.** One-line PR, separate from Sprint 0 work. Not my Sprint 0.2 scope — flagged for a later tidy.

#### Deploy PR #235 main-test-fixes to dev (still pending CSJ action from session 71)
- [ ] Upload 9 files per [[April/April25Updates/deployInherit|deployInherit]] to `~/www/csjones.co/public_html/fynla/`.
- [ ] SSH + cache clears on dev (no migrations needed).
- [ ] Browser smoke Estate + Holdings on `csjones.co/fynla`.
- [ ] After dev soak: CSJ opens `dev → main` PR (will include PR #236 phpunit bump alongside PR #235 fixes), merges, uploads to `fynla.org`, runs SSH cache clears.
- [ ] Monitor `storage/logs/laravel.log` 10-15 min post-deploy on each environment.

#### Sprint 0 continuation — start with Task 0.3
- [ ] **Sprint 0 Task 0.3 — two-Fyn collapse (architecture core).** Biggest code task in Sprint 0. See `April/April24Updates/plan/10-sprint-0-plan.md` slice **S0.3** (lines 49-73). Scope:
  - CREATE `app/Services/AI/AdviceFyn.php` (spec lines 297-364) — constructor-injected `CoordinatingAgent` + tool-definition classes; `handle(User, AiConversation, string $message, ?string $currentRoute = null): \Generator`; `buildToolList(User): array` returns all-tools minus 26-element `WRITE_TOOLS` constant.
  - CREATE `app/Services/AI/HandoffPayloadValidator.php` (spec 369-407) — static `validateDelegateToCapture` + `validateCaptureComplete` returning error-key string or null.
  - MODIFY `app/Services/Onboarding/OnboardingChatDirector.php` — append `handleInlineCapture(User, AiConversation, string $message, CaptureContext, ?string $currentRoute = null): \Generator` (spec 414-461). Filters `onboarding_layout_change` + `quick_replies` events per INV-2.4.2. Ports `emitGapFillFromCaptureContext` + `runExtractorForFocus` from `FynPersonaInvoker` before deleting it.
  - MODIFY `app/Http/Controllers/Api/AiChatController.php` — rewrite `sendMessage` dispatch (spec 489-514): early returns retained; `$inOnboarding = $user->onboarding_completed === false && (bool) config('onboarding.fyn_flow_enabled', true)`; single `StreamedResponse` delegating to director OR `AdviceFyn::handle`. Inject `AdviceFyn`; remove `FynPersonaOrchestrator` dependency + `wrapWithMultiEntityGapFill` wrapper.
  - DELETE `FynPersonaOrchestrator.php` (415 lines) + `FynPersonaInvoker.php` (518 lines) + `FynPersonaRegistry.php` (104 lines) + `Prompts/DataCapturePromptBuilder.php` (110 lines) + `config/fyn_personas.php` (91 lines).
  - MODIFY `app/Providers/AppServiceProvider.php` — remove orchestrator bindings; add `$this->app->singleton(\App\Services\AI\AdviceFyn::class)`.
  - CREATE migration `database/migrations/2026_04_25_000001_clear_stale_persona_state.php` — `up()` sets `ai_conversations.persona_state = null`; `down()` no-op.
  - DELETE ~10 stale test files: `tests/Feature/AI/PersonaSplit/{CancelMidCapture,CaptureTimeout,ClassifierFastPath,PreviewMode,KycGateFlow}Test.php`, `tests/Unit/Services/AI/{FynPersonaInvoker,FynPersonaOrchestrator,FynPersonaRegistry}Test.php`, `tests/Unit/Services/AI/Prompts/DataCapturePromptBuilderTest.php`, `tests/Unit/Services/AI/AdvicePromptBuilderPersonaSplitTest.php`.
  - PORT (rename + namespace fix): `tests/Feature/AI/PersonaSplit/{CreateWillTool,CreatePowerOfAttorneyTool,InlineCaptureFlow}Test.php` → `tests/Feature/Fyn/*Test.php`.
  - CREATE 5 new tests: `tests/Feature/Fyn/DispatchRoutingTest.php`, `AdviceFynToolListTest.php`, `HandoffInvisibilityTest.php`, `HandoffPayloadValidationTest.php`, `tests/Architecture/PersonaMachineryAbsentTest.php`.
  - Acceptance: all new Fyn + Architecture tests green; `grep -rn "FynPersonaOrchestrator|FynPersonaInvoker|FynPersonaRegistry|DataCapturePromptBuilder" app/ config/ tests/` → 0; migration clean; commit `feat(fyn): two-Fyn collapse — AdviceFyn + handleInlineCapture + delete orchestrator stack`.
- [ ] Sprint 0 Tasks 0.4–0.17 thereafter per plan (`10-sprint-0-plan.md`).

### Context for next session

1. **Branch**: already on `feature/fyn-persona-split` at `0409272` (Sprint 0.1 rebase + Sprint 0.2 sidecar deletion + merged dev with phpunit 4G). Working tree clean. Pushed.
2. **Start Sprint 0.3**: biggest code change in Sprint 0. Read `April/April24Updates/plan/10-sprint-0-plan.md` slice **S0.3** (lines 49-73) — it enumerates every file to create/modify/delete with code citations. Use **subagent-driven-development** skill if it makes sense to parallelise the test creation vs code deletion; else sequential execution-plans is fine.
3. **Sprint 0.3 mode**: likely 4-6 hour task. Checkpoint after `AdviceFyn` + `HandoffPayloadValidator` + `OnboardingChatDirector::handleInlineCapture` + `AiChatController::sendMessage` rewrite. Then the deletions. Then tests. Each commit can be its own logical unit.
4. **Memory baseline**: phpunit.xml is 4G on both dev and persona-split. `./vendor/bin/pest` works without `-d memory_limit=` workaround now.
5. **Scheduled agent** `trig_015ggy6qz1M3axH6Shvv5Wfw` still pending for Sun 26 Apr 09:00 BST — verifies the 22 fixes on main post `dev → main` merge.

### Deploy Status

- **PR #236 phpunit-memory-bump**: merged to `dev` branch on GitHub. Not applicable to server (`phpunit.xml` is not copied to production).
- **PR #235 main-test-fixes**: on `dev` branch, NOT yet deployed (awaits CSJ upload per session 71's `deployInherit.md`). Not on main.
- **Sprint 0.2 sidecar deletion**: on `feature/fyn-persona-split` tip `0409272`. No user-visible behaviour change; just attack-surface reduction. No deploy action for now — will go out as part of whatever PR eventually takes persona-split → dev.
- **Sprint 0.1 rebase**: on `feature/fyn-persona-split` (no user-visible behaviour; foundation for Sprint 0.3+).

### Pest baseline (current)

- **On `feature/fyn-persona-split` at `0409272` (4G)**: 2,663 pass / 1 fail (pre-existing WillBuilder faker flake) / 11,223 assertions / 205s.
- **On `dev` branch at `58aeb47` (4G)**: Architecture suite green (94/195/0). Full suite not rerun — last measured on PR #235 fix-branch: 2,370 pass / 0 fail / 10,314 assertions / 240s.

### Memory-rule adherence this session

- ✅ `feedback_main_via_dev_only.md` — PR #236 targeted `dev`, CSJ-authorised admin-merge in current turn ("commit, push and merge") was explicit authorisation per the rule's exception clause.
- ✅ `feedback_never_raw_vite_build.md` — no vite builds attempted.
- ✅ `feedback_deploy_guide_completeness.md` — no new deploy guide needed (phpunit.xml is test-infra; Sprint 0.2 is on persona-split not yet deployed).
- ✅ `feedback_deploy_guides_both_locations.md` — n/a this session.
- ✅ `feedback_never_hardcode_tax_values.md` — no tax values touched.
- ✅ `feedback_never_touch_env_or_db.md` — no `.env` changes beyond removing the stale `AGENT_INTERNAL_TOKEN` entry in `.env.example` (which was in scope per Sprint 0.2 plan), no DB hand-inserts.
- ✅ `critical_browser_testing_law.md` — no user-facing behaviour changed; no browser claims made. Pest is the verification surface for an infrastructure-only session.
- ✅ `feedback_never_skip_testing.md` — full Pest re-run after every code change. OOM diagnosed → 4G bump shipped as separate PR; not silently worked around.
- ✅ `feedback_never_claim_verified.md` — the WillBuilder faker flake was diagnosed but NOT silently fixed; flagged for next session.
- ✅ `feedback_no_self_approval.md` — PR #236 admin-merge happened only after explicit CSJ in-turn instruction ("merge").
- ✅ `feedback_never_switch_branches.md` — switched briefly to `feature/csj/phpunit-memory-bump` for the one-line bump (scoped, isolated), returned to persona-split immediately. Not a parallel-agent scenario.

---

## Decision register snapshot (unchanged from session 70, still locked)

1. Two Fyns, no Orchestrator class. Delete orchestrator/invoker/registry/data_capture prompt builder.
2. All 17 fill_form handlers → direct-write (Q1=a).
3. Provider parity. 40 tools post-Sprint-0 (+14 batch = 54 post-Sprint-2).
4. FCA: guidance-only. Signposting: *"For regulated advice personal to your circumstances, speak to a qualified financial adviser."*
5. Out-of-remit: *"I'm able to help you with your finances. {context} is out of scope."*
6. Memory model: 3 stores + 1 index. `MemoryRetrieverService` retrieval order DB → parked → current → index.
7. SSE abort: keep partial writes, instrument.
8. Python sidecar: delete. **[DONE session 72 commit `2b1f347`]**
9. Deploy gate: local-first unambiguous.
10. Rubric-A + Rubric-B: build both.
11. Multi-entity thresholds: 95% baseline recall/precision; 100% hard-fail floors.
12. Advice response shape: new `advice_response` SSE event + `AdviceResponsePanel.vue`.
13. Recommendation engine: existing `orchestrateAnalysis` pipeline — reused, not replaced.
14. Entry-source mapping: config-driven + extensible; 4 initial mappings.
15. Document extraction: UI-only CTA flow; not an Advice Fyn tool.
16. Estate/Holding `decimal:2` casts: deferred per arch exception; API Resource layer when it lands.

---

## Outstanding — Tech Debt Deferred

- `WillBuilderApiTest::pre-populate` faker `middle_name` flake (see above) — one-line factory override in the test.
- Tracked in `MonetaryCastsArchitectureTest::ALLOWED_FLOAT_COLUMNS` header comment (from session 71): when API Resource layer lands, remove each of the 16 exempt column entries and reinstate `decimal:2` on Estate Asset/Liability/Gift/IHTProfile + Investment Holding.

---

## Known Issues

- `WillBuilderApiTest::GET /estate/will-builder/pre-populate → it returns pre-populated data` — 30% flake rate (faker `middle_name`). Does not block Sprint 0 work.
- `SavingsAgentGoalsTest` + `AutoRiskCalculatorTest` — older known flakes per session 71 CSJTODO. Unrelated to Sprint 0.

---
