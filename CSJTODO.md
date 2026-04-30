# CSJTODO — Fynla

*Last updated: 30 April 2026 — session 116 (architecture map post-fix rewrite + new tool catalogue; no code changes).*
*Previous session: 115 (30 April — Fyn AI audit + 15-finding fix cycle, all P1/P2 closed, commit `0434fa1`).*

---

## Session 116 (30 April 2026) — Architecture map post-fix rewrite + tool catalogue

**Branch:** `feature/fyn-persona-split`. **Commits:** 0 (all docs in gitignored `/April/`).

CSJ asked: (a) compare `April/April30pdates/auditFixes.md` with `fyn-ai-audit.md` and the codebase; (b) update `fyn-ai-and-savetax-architecture-map.md` to reflect what's actually in the code now; (c) produce a full detailed list of every tool both AI systems use.

### Completed this session

- [x] **Verified all 15 fixes line-by-line in code** — file:line evidence for F-1 (`AdviceFyn.php:394-447` + `aiChat.js:542-557`), F-2 (`UserContentSanitiser.php:68`), F-3 (`HasAiChat.php:615, 891-954`), F-4 (`HasAiChat.php:368-374`), F-5 (`OnboardingPromptBuilder.php:79-88`), F-6 (`WriteIntentClassifier.php:100-134, 169-182`), F-7 (`FcaProcessInstructions.php:24-33` comment-only), F-8 (`HasAiChat.php:711`), F-9 (`AdvicePromptCacheInvalidator.php` + `CoordinatingAgent.php:987-989`), F-10 (`AdviceFyn.php:142`), F-11 (`OnboardingChatDirector.php:49, 1693-1717`), F-12 (`EvalBypassGate.php` + `HasAiChat.php:166-167` + `PreviewWriteInterceptor.php:142-147` + `EvalHttpDriver.php:84-85`), F-13 (`HasAiChat.php:431-444, 1110-1142`), F-14 (`AdvicePromptBuilder.php:124-151`), F-15 (`HasAiChat.php:59-63, 187-193, 654-665`). All 15 confirmed shipped exactly as `auditFixes.md` claimed.
- [x] **Rewrote `April/April30pdates/fyn-ai-and-savetax-architecture-map.md`** — surgical updates to §1.2 (F-5 cache-first prompt order + F-11 onboarding duplicate guard subsection with focus → entity_type mapping table + the SaveTax/estate/business fall-through caveat), §1.3 (F-6 interrogative guard on write-intent short-circuit, F-1 two-stage `HandoffPayloadValidator` flow with hard/soft branches + `handoff_error` SSE handler, F-10 default-`factual` engine fallback, F-14 Layer 5/6 skip on factual queries with token estimate), §1.4 (side-by-side table updated: prompt cache row, dynamic tool-cap row, transient retry row, pre-LLM dedup row), §1.5 (new sub-sections for F-3 tool-result compression, F-4 Anthropic cache telemetry, F-7 dead-method removal, F-8 system-prompt hash, F-9 cache invalidation on capture, F-12 EvalBypassGate defence-in-depth, F-13 transient retry, F-15 dynamic tool-cap; F-2 denylist sanitiser update), §2.5–2.7 (SaveTax now caches Layers 1-3 across 6-8 turns; **explicit note that F-11 does NOT activate for SaveTax — `default => null` in focus match falls through to handler-level idempotency**). Top header now references `auditFixes.md` and notes 34/35 invariants conformant.
- [x] **Created `April/April30pdates/fyn-ai-tool-catalogue.md`** — companion catalogue of all **47 distinct tools + 2 internal handoff stubs** the LLM ever sees. 12 functional sections: read tools (12), direct-write life records (12), direct-write estate (6), goals & scenarios (3), modification & deletion (2), profile & expenditure (2), onboarding base-flow extraction (4), SaveTax campaign-specific capture (4), internal handoff (2), per-Fyn matrix, preview-mode behaviour, tool result shape contract. Each tool entry has: parameters, multi-call semantics, anti-patterns, and per-persona/turn-type visibility (Advice / Onboarding-asset_capture / Onboarding-grouped_extract / Onboarding-inline-capture). Includes `toolsForFocus` map and `WRITE_TOOLS` blacklist explainer.
- [x] **Vault synced** — both updated/new docs copied to `/Users/CSJ/Desktop/fynlaBrain/April/April30Updates/`. Apr30.md commit log updated to 2 commits (added `f8bb90c` session-end docs commit). April Index `April30Updates` section updated with the new tool catalogue link + revised architecture-map description. April Index `Sessions` section now shows "April 30 (2 sessions — 1 commit)" with both Session 115 + Session 116 entries. Apr2026 Commits.md total 736 → 737 + Apr30 row 1 → 2. Home.md April 2026 row 736 → 737 + total 2,955 → 2,956.
- [x] **Dev server running in background** — Laravel on `:8000` (200 OK), Vite on `:5174` (5173 was occupied at boot; HMR responding, Chrome attached). Background task `bu5771ozs`. Two pre-existing Vite warnings unrelated to this session: `hover-blue-gradient` Tailwind utility invalid theme value, `<button>` inside `<button>` in `resources/js/components/Shared/AiChatPanel.vue:131-139`.

### Session 116 deliverables

| Artefact | Path | Size |
|---|---|---|
| Architecture map (rewritten) | `April/April30pdates/fyn-ai-and-savetax-architecture-map.md` | 38 KB |
| Tool catalogue (new) | `April/April30pdates/fyn-ai-tool-catalogue.md` | 28 KB |
| Architecture map PDF | `April/April30pdates/fyn-ai-and-savetax-architecture-map.pdf` | 566 KB |
| Tool catalogue PDF | `April/April30pdates/fyn-ai-tool-catalogue.pdf` | 582 KB |

All four also live at the equivalent path under `/Users/CSJ/Desktop/fynlaBrain/April/April30Updates/`.

### Out-of-scope notes for next session

- Cosmetic markdownlint warnings on `fyn-ai-tool-catalogue.md` (multiple H1s — section dividers `# 1.` through `# 12.` should be `## 1.`; some MD022 blank-lines-around-headings nits). Doc renders fine in Obsidian/GitHub. Skipped this session as cosmetic-only; clean up if/when the doc gets reused.

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
