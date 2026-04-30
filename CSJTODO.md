# CSJTODO — Fynla

*Last updated: 30 April 2026 — session 115 (Fyn AI audit + 15-finding fix cycle, all P1/P2 closed).*
*Previous session: 114 (29 April — BS-27 + BS-28 root-cause fixes shipped, both live GREEN).*

---

## Session 115 (30 April 2026) — Fyn AI audit + 15-finding fix cycle

**Branch:** `feature/fyn-persona-split` (51 commits ahead of `origin/main`, all pushed). **1 commit this session:** `0434fa1`.

### Completed this session

#### Phase 1 — Architectural mapping

- [x] **Fyn AI architecture map written** at `April/April30pdates/fyn-ai-and-savetax-architecture-map.md` (also synced to vault as `April30Updates/`). Two-part doc: (a) Onboarding vs Advice — single dispatch in `AiChatController:175-183`, 4-layer Onboarding prompt vs 12-layer Advice prompt, `WRITE_TOOLS` blacklist + `wrapStream` handoff interception, sized engine calls; (b) SaveTax campaign — trigger via `?from=savetax`, 9-state flow, deterministic `TaxStrategyCalculator` post-onboarding (no LLM).

#### Phase 2 — Audit against canonical contract

- [x] **Audit report written** at `April/April30pdates/fyn-ai-audit.md`. 15 findings ranked P1/P2 against `April/April24Updates/spec/00-canonical.md` + `01-invariants.md` + `10-sprint-0-plan.md`. Identified INV-2.4.5 as the only Sprint 0 deviation (HandoffPayloadValidator existed but was never invoked from production code). Conformance scorecard: 33/35 invariants conformant pre-fix.

#### Phase 3 — Ship all 15 fixes (commit `0434fa1`)

**P1 fixes (4):**
- [x] **F-1 INV-2.4.5 enforced** — `HandoffPayloadValidator` wired into `AdviceFyn::wrapStream`. Hard malformations emit `handoff_error` SSE event (Vuex handler added) instead of silently dropping. Soft `reason`-missing recovers via `CaptureContext` synthesis.
- [x] **F-2 UserContentSanitiser preserves Unicode** — switched whitelist `[A-Za-z0-9\s'.,\-]` to denylist of injection-relevant chars. François / 李 / Müller / Алексей now survive into prompts unchanged.
- [x] **F-3 Tool results compressed before LLM re-injection** — new `compressToolResultForModel` + recursive `trimForModel` (list arrays >10 items, depth >3, strings >200 chars). Errors and direct-write results pass through unchanged.
- [x] **F-6 WriteIntentClassifier interrogative guard** — questions ending with `?` or starting with `should i / can i / how do i / what is / where should / tell me / explain / show me` no longer mis-route as write intents.

**P2 fixes (11):**
- [x] **F-4** Anthropic `cache_read_input_tokens` captured — `cache_hit_rate` metric now reflects reality on Anthropic provider.
- [x] **F-5** Onboarding prompt re-ordered (`known_facts` last) — static prefix benefits from prefix cache across turns.
- [x] **F-7** Dead `getDataCreationGuidance` method deleted from `FcaProcessInstructions`.
- [x] **F-8** System prompt persisted as `sha256:` hash instead of full text — PII data minimisation + DB bloat reduction.
- [x] **F-9** New `AdvicePromptCacheInvalidator` wired into `appendAuditCompletion` — every successful write invalidates `ai_existing_records` and `ai_financial_context` caches.
- [x] **F-10** `engineCallLevelFor()` default flipped from `holistic` to `factual` for unknown primaries.
- [x] **F-11** Duplicate-check guard added to onboarding `handleAssetCaptureTurn` (mirrors AdviceFyn pattern).
- [x] **F-12** New `EvalBypassGate` requires `X-Eval-Run-Id` header alongside `bypass-preview-mode` ability — defence-in-depth so a leaked token alone cannot bypass preview filtering.
- [x] **F-13** Single 1.5s retry on transient LLM errors (429/529/timeout). Only when no partial output exists.
- [x] **F-14** Layer 5/6 (`financial_context`, `existing_records`) skipped on factual queries (BILLING/NAVIGATION/OUT_OF_REMIT/INCOME/GENERAL/DATA_ENTRY).
- [x] **F-15** Tool-call cap dynamic by engine level (holistic=8, module=5, factual=3) replacing constant of 5.

#### Tests + verification

- [x] **New tests:** `WriteIntentClassifierTest.php` (15 cases) for F-6, `AdviceFynHandoffErrorTest.php` (3 cases) for F-1.
- [x] **Extended:** `UserContentSanitisationTest.php` with Unicode coverage (François / 李 / Cyrillic).
- [x] **Test sweep:** 58/58 across the AI/Fyn slice + 95/95 architecture green. Pre-existing time-flake in `AdviceReviewServiceTest::annual review due` (calendar arithmetic) noted but unrelated.
- [x] **Pint clean** on all changed files. PHP lint clean.
- [x] **Audit fix records** written at `April/April30pdates/auditFixes.md` (also synced to vault).
- [x] **Deploy notes** written at `April/April30pdates/deploy.md`.

### Conformance update

- INV-2.4.5 closed (validator wired, `handoff_error` event live)
- INV-2.10.4 inclusivity gap closed (Unicode-safe sanitiser)
- INV-2.3.5 still deferred to Sprint 1 per spec
- **Net Sprint 0: 33/35 → 34/35 invariants conformant**

### Cost projection

- SaveTax campaign per user: **2p → 0.8p (~60% reduction)**
- Holistic chat: **$0.012 → $0.0055 per turn (~55% reduction)**
- Onboarding turn 2-N: **$0.0008 → $0.0003 per turn (~62% reduction)**

---

## NOT Done — Outstanding for next session

### Deploy combined sessions 112 + 113 + 114 + 115 to dev (csjones.co/fynla)

Session 115 modified files already in the cumulative deploy set + added 3 new ones (`AdvicePromptCacheInvalidator.php`, `EvalBypassGate.php`, `aiChat.js` Vuex change requires frontend rebuild). All deploy notes at `April/April30pdates/deploy.md` and earlier session deploy notes ([[deploy-notes]], [[savetax-section4-6-deploy-notes]]).

- [ ] Open PR `feature/fyn-persona-split → dev`, merge after Stoff73 approval
- [ ] Build with `./deploy/csjones-fynla/build.sh` (Vue store change in `aiChat.js` requires frontend rebuild)
- [ ] Upload `public/build/` + cumulative file set: ~30 PHP backend (incl. 3 new from session 115) + ~12 frontend + 4 migrations (none new in session 115)
- [ ] SSH: `php artisan migrate --force && php artisan db:seed --class=TaxConfigurationSeeder --force && php artisan cache:clear && php artisan config:clear && php artisan route:clear && php artisan optimize`
- [ ] Re-drive BS-26/27/28 against `csjones.co/fynla` per Rule #15 (live browser walkthroughs)
- [ ] Spot-check: try a prompt-injection name like "François <script>alert(1)</script>" in registration to verify F-2 preserves Unicode while stripping injection chars

### After dev green — production deploy

- [ ] Open PR `dev → main`, merge, repeat with `./deploy/fynla-org/build.sh`
- [ ] Smoke test fynla.org

### Sprint 0 verification rollup (S0.17) — branch readiness

Once the deploy lands and BS-26/27/28 stay green:

- [ ] Full Pest sweep `./vendor/bin/pest` (expect 815+ pass with 1 pre-existing time-flake)
- [ ] Architecture suite green
- [ ] `php artisan ai:audit:verify-chain` returns `chain_valid: true`
- [ ] BS-NN browser matrix: 20/20 PASS with screenshots committed
- [ ] Rubric-A re-score: target 13-15/40 per S0.17 plan
- [ ] PR body links to verification evidence

### Sprint 1 — INV-2.3.5 structured `advice_response` SSE event

Carried over from Sprint 0 deferral. Required schema in `April/April24Updates/spec/01-invariants.md §2.3.5`:
- New SSE event type `advice_response` emitted exactly once per recommendation-mode turn
- Payload: `{headline, key_figures[], breakdowns[], recommendations[], next_steps[], signposting}`
- Rendered by new `AdviceResponsePanel.vue`
- JSON-schema validation in `tests/Feature/Fyn/AdviceResponseSseShapeTest.php`

### F-12 Sprint follow-up

- [ ] Server-side `X-Eval-Run-Id` allowlist — currently the gate just requires the header to be present and non-empty. A stronger version would track in-flight runs in DB and reject unknown run-ids.

### F-4 Sprint follow-up

- [ ] Anthropic cache-hit dashboard — F-4 captures the data; rendering it in the admin UI is a follow-up so cache regressions are visible.

### F-8 Sprint follow-up

- [ ] Migration to rename `ai_messages.system_prompt` column to `system_prompt_hash` + backfill old rows. F-8 writes hashes to the existing column going forward; rename is cosmetic and out of audit scope.

---

## Outstanding — Tech Debt Deferred

- **`handleModuleAnalysis` summarisation** — INV-2.6.1 says "no `summariseToolAnalysis` stripping for this handler" but the S0.15 delivery note acknowledged it still wraps via `summariseToolAnalysis` at line 1512. Behaviour change with broader test surface; deferred follow-up.
- **Vue 3 `$listeners` warning on `<FynOnboardingChat>`** (carried from session 113-evening). Minor.
- **`StructuredResponseValidator` flagging "SIPP" as banned acronym** in LLM acks where the state's own prompt uses SIPP (carried from session 113-evening). Needs per-state allowlist or canonical exception.
- **TaxStrategyCalculator under-counts pension AA usage on initial load** — slider re-fires correctly. (Carried from session 113-evening.)

---

## Known Issues

- Pre-existing time-flake in `AdviceReviewServiceTest::annual review due` — `subMonths(14)` followed by `diffInMonths(now())` can return 13 across month-end boundaries. Unrelated to any changes; flagged for separate fix.

---

## Deploy Status

- **`feature/fyn-persona-split`** — 51 commits ahead of `origin/main`, all pushed. **Not yet deployed anywhere.**
- **`dev` branch** — last deployed commit unknown — needs catch-up to combined 112+113+114+115 work.
- **`main` / production fynla.org** — last deployed somewhere mid-April (sessions 105-107). All Sprint 0 + SaveTax + audit-fix work pending.

---

## Context for Next Session

Session 115 was a clean execute on a 15-finding audit cycle — no new bugs surfaced, no architecture decisions outstanding. The branch is now Sprint 0 invariants 34/35 conformant (INV-2.4.5 closed; INV-2.3.5 still Sprint 1). **Next session should drive the cumulative dev deploy** (sessions 112+113+114+115 in one upload) and re-verify BS-26/27/28 against `csjones.co/fynla` per Rule #15. After dev green, production deploy. After production deploy, S0.17 verification rollup → Sprint 1 (INV-2.3.5 structured `advice_response` SSE schema + AdviceResponsePanel.vue).

If CSJ wants to start something else, the audit identified three Sprint follow-ups (F-4 dashboard, F-8 column rename migration, F-12 run-id allowlist) that are all small and self-contained.
