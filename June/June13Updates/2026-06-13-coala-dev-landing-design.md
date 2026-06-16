# CoALA — Landing on dev & Stabilisation

**Date:** 2026-06-13 (v1 — written after the landing merged; supersedes the "dev is pre-CoALA" framing everywhere)
**Status:** Landing merged to dev (PR #550); csjones + prod test/deploy and the doc-cleanup + deflection-fix workstreams below are open
**Parent spec:** `docs/superpowers/specs/2026-06-11-track2-coala-integration-design.md` — that doc designed Track 2 *onto coala* and explicitly scoped "the coala → dev landing" **out** (its §7). This doc is that landing, plus the stabilisation and stale-reference cleanup the landing forces.
**Canonical architecture:** the agent flow in the Track 2 spec §1 (CSJ, 2026-06-11) + `fynla-coala-implementation-plan.md` v0.5 + the CoALA paper (Sumers et al., arXiv:2309.02427). **Unchanged by this spec** — the landing is a merge + stabilisation, not a redesign.
**Branch:** landed on `dev` via **PR #550** (`coala-dev-landing` → `dev`, merge commit `a9ad212`; the landing merge itself is `f0ec1f7`). `dev` is now the CoALA substrate; `main`/prod is still pre-CoALA.

---

## 1. The agent flow (canonical — unchanged)

The four-stage flow (session start loads the lean prompt + semantic indexes; each turn checks semantic memory **or** invokes a procedural skill; interrupts hold to the same flow; session end writes the episode) is exactly as set out in the Track 2 spec §1 and the canonical-flow memory. **Nothing in this spec changes it.** The landing makes that flow the live substrate on `dev`; it does not re-open the design.

The **lean-prompt law** and the **v0.5 pointer model** (data with a live owner is never frozen into a prompt or corpus) continue to govern all new work.

## 2. What landed on dev — the delta (the answer to "what is the delta")

The merge is **224 commits, 415 files, +49,019 / −2,790**. It moves `dev` from the pre-CoALA two-write-state model to the CoALA substrate. Breakdown:

### 2.1 The behavioural change (the thing to regression-test)
Entry dispatch is **unchanged** — `AiChatController::sendMessage` still routes on the 3-part predicate to `OnboardingChatDirector` (onboarding write state) or `AdviceFyn` (read-only advice). **What changed:** both shells now delegate to the shared **`FynLoop`** (`AdviceFyn::handle → fynLoop->run()`; `OnboardingChatDirector` likewise). This is **Option B** — thin shells over a shared loop. The loop adds:
- **`GroundGate`** — mechanical write-safety at the dispatch boundary (rejects `AdviceFyn::WRITE_TOOLS` on the read-only advice surface, audited `ai_audit_events.status='stripped'`). This is **additive** to the existing catalogue-strip (`array_diff`), not a replacement — belt-and-braces.
- **Typed `Action` enum** (`reason | retrieve | learn | ground | no_action`) + dispatcher.
- **Planner** — present, **flag-gated, not enabled by default**; the lean reasoner path is what runs.
- **Concurrent-turn queue**, **resumption**, **per-action cost ledger**.

User-facing Fyn behaviour should be **identical** (Option B is belt-and-braces); the internal path is new, which is exactly why Fyn read + write (both modes, both surfaces) is the #1 test target.

### 2.2 Schema — 8 additive migrations (must run on every deploy)
| Migration | Purpose |
|---|---|
| `2026_05_30_000001_add_status_to_ai_messages_table` | queued/processing/answered turn states |
| `2026_05_30_000002_create_ai_cost_attribution_table` | per-action GBP cost ledger |
| `2026_05_30_000003_add_pending_resumption_to_ai_conversations_table` | half-finished session resumption |
| `2026_05_30_000004_add_paused_to_ai_conversations_status` | inactivity pause |
| `2026_06_01_000001_add_episode_columns_to_ai_messages` | episodic memory (blob path, sha, snapshot ids) |
| `2026_06_01_000002_add_hash_scheme_to_ai_audit_events` | versioned hash chain |
| `2026_06_01_000003_add_persist_operation_to_ai_audit_events` | episode persist-op discriminator |
| `2026_05_29_180000_widen_loan_to_value_pct_on_properties` | unrelated LTV precision fix riding along |

### 2.3 Backend — `app/Services/AI`: 47 files, +4,323 / −2,586
Eight new packages: `Loop/` (FynLoop), `Ground/` (GroundGate + SurfaceAllowlist), `Actions/` (typed Action + ToolActionMapper + ActionDispatcher), `Memory/` (episodic blob writer + provenance + semantic retriever + procedural store), `Pointers/` (fetch registry + dispatcher + handlers), `Cost/` (AiCostCalculator), `Fyn/` (FynContextAssembler + FynSystemPrompt + capture-turn instructions), `Prompts/`. The `−2,586` is mostly the static PHP tool arrays replaced by corpus loading.

New controllers (admin): `Api/Admin/AiCostDashboardController`, `Api/Admin/ProceduralCorpusController`. Plus episodic read endpoints on `AiAuditController`.

### 2.4 The corpus — `fyn-memory/`: 139 files, +8,571
The procedural/semantic memory now lives as reviewed `.md`: **94 `procedural/tool_schema`** (the externalised dual-provider tool catalogue — **the live tool definitions now come from here**, golden-master pinned), **21 `semantic/house_view`**, 6 `procedural/pointers`, 2 `procedural/system_prompt_overlay`, the `recommendation-routing.md` planner procedure, plus episodic/semantic/procedural scaffolding (`README`/`_TEMPLATE`/`RUBRIC`).

### 2.5 Frontend — `resources/js`: 17 files, +1,707
New views/components: `views/Admin/AiCostDashboard.vue`, `views/Admin/EpisodicComplianceLog.vue`, `views/Admin/ProceduralCorpusViewer.vue`, `components/Advisor/ClientSessionLog.vue`; plus `AiChatPanel.vue` resumption + queued-bubble wiring. **`resources/mobile/` is untouched** — `/m` Fyn rides the shared backend.

### 2.6 Routes — `routes/api.php`: +25 (all additive, no shadowing)
`GET /ai-chat/resumption`, `DELETE /conversations/{id}/resumption`, `POST /conversations/{id}/messages/{messageId}/stream` (queued-turn), `DELETE /conversations/{id}/messages/{messageId}` (cancel queued), admin `/episodes*`, `/procedural-corpus*`, `/ai-cost-dashboard`.

### 2.7 Config / env — 9 new vars, **all defaulted** (no `.env` change required to boot)
`config/fyn.php`: `FYN_PROMPT_ARCH=unified` (already the dev default), `FYN_QUEUE_DEPTH_CAP=3`, `FYN_QUEUE_TTL_MINUTES=10`, `FYN_CYCLE_CAP=8` (+`_ADVICE`/`_ONBOARDING`), `FYN_SEMANTIC_TOP_K=4`, `FYN_PROCEDURAL_RELOAD_INTERVAL=60`. `config/ai_pricing.php`: `AI_PRICING_USD_TO_GBP=0.79`. **The pricing rate is a placeholder** — cost-ledger figures are not FCA-report-ready until verified (see §6g).

### 2.8 Tests — 121 files, +18,179
Suite verified **4,966 passed / 7 skipped / 0 failed** (111,307 assertions; Browser suite excluded). The 7 skips are intentional (deferred cassette re-record; live-LLM manual-drive `EvalHttpDriverTest`).

### 2.9 The one merge resolution that carried semantic risk
dev's #548 added `create_investment_account.isa_subscription_current_year` to the static PHP tool arrays; coala replaced those arrays with corpus loading. Resolution: took coala's corpus-driven side **and ported the field into the corpus** (`fyn-memory/procedural/tool_schema/savings/create_investment_account.md` + `.xai.md`), regenerated the golden masters. Verified end-to-end in the browser (write intent → DB row carries `isa_subscription_current_year=5000`). All other dev hotfixes (#547 ISA calc, the deflection carve-out in `FynSystemPrompt`/`CoreIdentity`) were dev-only files coala never touched → they landed cleanly.

## 3. Verification done & owed

**Done (local dev):**
- Full Pest suite green (4,966 / 0).
- Tool-schema golden masters (anthropic + xai) green; #547/#548 tests green.
- Browser (web): login → dashboard renders → Fyn **read** (net worth £29,850, emergency fund 12.98 months) through FynLoop → Fyn **write** (advice→capture handoff, no deflection) → `investment_accounts` row persisted with the ported `isa_subscription_current_year=5000` → new `/ai-chat/resumption` endpoint 200.

**Owed (csjones, then prod):** see §6a/§6b. Full `/m` click-through and onboarding-mode write are owed on csjones (the established `/m` verification venue — built bundle, no HMR).

## 4. Locked facts (what actually landed — do not re-litigate)

1. **Option B landed, not Option A.** The shells (`AdviceFyn`, `OnboardingChatDirector`) still exist and own dispatch; `FynLoop` is the shared loop underneath. Write-safety is enforced by **both** the catalogue-strip and `GroundGate`. The canonical two-write-state contract is preserved.
2. **Option A (delete the shells, single loop, write-safety only in the gate) remains deferred** — a design call, the stated direction of travel, but explicitly NOT done here.
3. **Planner is off by default.** The reasoner/lean path runs; the planner is flag-gated.
4. **`FynSystemPrompt::text()` is byte-invariant** (prefix-cache contract) — untouched by the landing; the snapshot test still pins it.
5. **The tool catalogue is now corpus-driven on dev.** New tool fields land in `fyn-memory/procedural/tool_schema/*.md` (+ `.xai.md`) and require golden-master regeneration, not PHP array edits.

## 5. Sequencing — the landing is step 0; stabilisation is what remains

Step 0 (the merge) is **done and merged to dev**. Everything below is stabilisation, in order:

1. **csjones deploy + test pass** (§6a) — the "test before prod" gate CSJ called for.
2. **Doc + memory cleanup** (§6d–§6f) — can run in parallel with csjones testing; it has no code risk.
3. **The deflection fix** (§6c) — independent; lands as its own PR off dev.
4. **Prod deploy** (§6b) — only after csjones is green and CSJ calls it.

## 6. Workstreams

### 6a. csjones deploy + test pass (the immediate gate)
- **Rebuild the csjones bundle first:** `./deploy/csjones-fynla/build.sh` (local `public/build/` is currently PROD-configured — deploying it to csjones would push prod base paths).
- On csjones: `git pull origin dev`; `php artisan migrate --force` (the 8 migrations §2.2); reseed catalogues; **run the corpus reindex** (`php artisan fyn:semantic:reindex`, `php artisan fyn:pointers:reindex` — in the CLAUDE.md runbook); `config:cache` only. **Never `route:cache`/`optimize`** (catch-all shadows `/` and the `/m` iframe).
- **Test, in priority order, on web AND `/m`:**
  1. Fyn **read** (advice) end-to-end.
  2. Fyn **write** (advice→capture handoff) end-to-end — confirm a record persists, no deflection.
  3. Fyn **onboarding** write flow (a registration → bubble capture → DB).
  4. Concurrent-turn queue (greyed bubble + cancel) and resumption banner.
  5. The new admin pages render without 500 (AI Cost Dashboard, Episodic Compliance Log, Procedural Corpus Viewer).
  6. Dashboard + module pages still load (the `ai_messages`/`ai_conversations` schema changed).
- Acceptance: all green on csjones, web + `/m`; the Azlan savetax journey replays GREEN (the standing acceptance scenario).

### 6b. Prod deploy (after csjones green; CSJ's call)
- Prod is a non-git manual-upload server with accumulated drift — full-tree rsync + `composer dump-autoload -o` + `migrate:status` reconcile (see `reference_prod_accumulated_deploy_drift.md`). Run the 8 migrations + catalogue seeders + corpus reindex; `config:cache` only; monitor `storage/logs/laravel.log`.
- Note prod-specific lore: SiteGround vhost drops conditional Apache directives (per-route headers must be Laravel middleware); never `optimize`.

### 6c. The advice-Fyn / capture-turn deflection fix (the real bug, still owed)
The 2026-06-13 diagnosis (now also on dev): legitimate "add my X" requests sometimes deflect with the security refusal **not** because the advice classifier over-fires, but because the **capture turn is mis-framed**. `OnboardingChatDirector::handleInlineCapture` sets the unified capture focus from `inferFocusesFromEntityTypes($context->entityTypes)`; that map only covers **protection / savings / retirement / investment**. For **property, mortgage, liability, goal, and any estate entity type** it returns `null` → no focus → `FynCaptureTurnInstructions` is never injected → the capture-turn model falls back to the security refusal (the failure mode the code comment at `OnboardingChatDirector.php:2989` documents).
- **Fix:** map every capture entity type to a focus in `inferFocusesFromEntityTypes`, **and/or** add a fail-safe so any turn that reached `handleInlineCapture` (i.e. a write already cleared by the deterministic gate) always injects capture instructions and can never emit the security refusal.
- **Belt-and-braces:** a deterministic post-guard — if a turn we routed to capture yields the exact security-refusal string, treat it as a misfire and recover (retry as capture / deterministic ack).
- **Eval:** add a batch of legitimate capture phrasings across all entity types to the golden harness; assert deflection rate is zero.
- **Note:** investment/savings/pension/protection map fine today — which is why the §2.9 ISA write captured cleanly. Property/goal/etc. still deflect until this lands.

### 6d. Stale CLAUDE.md — the canonical Fyn section
The "Fyn AI — one prompt, two write states, converging to one Fyn" section's **"Where we are vs where we're heading"** note is now stale. It currently says the contract is "the **current dev state**: two write states, write-safety via catalogue-strip … at `AiChatController::sendMessage`" and that "the **CoALA work landing on dev soon (currently on the `coala` branch …)**" adds FynLoop + GroundGate.
- **Correction:** the CoALA substrate (FynLoop + GroundGate, Option B) is **now on dev as of PR #550 (2026-06-13)**. Write-safety on dev is enforced by **both** the catalogue-strip **and** GroundGate. The pre-CoALA framing applies to **prod/`main` only** until the prod deploy. Option A shell-deletion is still deferred. Re-point the cross-reference away from "landing soon on coala".
- **Owner note:** CLAUDE.md is CSJ-owned; this spec flags the exact text and correction — CSJ makes the edit (or approves it).

### 6e. Stale README.md
- **`README.md:504`** currently: *"CoALA memory programme … **in progress on the `coala` branch**"*. **Correction:** "landed on `dev` via PR #550 (2026-06-13); pre-CoALA on prod until deployed."

### 6f. Stale memory files (and a new one)
- **`project_coala_phase5_progress.md`** — line 10 ("CoALA PRs target `coala`, not dev") and the **2026-06-04 UPDATE** ("**Dev today is still the pre-coala two-write-state model** … no FynLoop/GroundGate on dev yet") are now false. Update to: dev has FynLoop/GroundGate as of #550; the pre-CoALA statement applies to prod only.
- **`project_track2_landed_on_coala.md`** — line 18 ("the coala→dev landing remains its own future programme") is done. Update to: landed on dev via #550 (2026-06-13).
- **`project_coala_phase1_scope_decisions.md`** — "PR #439 … awaiting CSJ review" is long merged; already soft-superseded, but tidy the index hook.
- **`MEMORY.md` index** — refresh the hooks for the two project memories above to read "now on dev (#550)".
- **New memory** (`project_coala_landed_on_dev.md`, type `project`): "CoALA (Phases 1–5 + Track 2) landed on `dev` via PR #550, 2026-06-13, merge `a9ad212`. dev now runs Option B (shells over FynLoop + GroundGate); prod still pre-CoALA. Supersedes every 'dev is pre-CoALA' / 'awaiting landing' claim." Link `[[project_track2_landed_on_coala]]`, `[[project_coala_phase5_progress]]`.

### 6g. Config hardening on deploy
- **AI pricing rate** (`AI_PRICING_USD_TO_GBP=0.79`) is a placeholder — verify against the real provider rate before any cost figure is treated as real (FCA cost reporting).
- **Corpus reindex must run on every deploy** (`fyn:semantic:reindex`, `fyn:pointers:reindex`) — add to the csjones + prod deploy checklists if not already pinned there.

### 6h. Known gaps to keep tracked (not blocking the landing)
- **Episodic fetch-provenance "unfed" follow-ups** (`project_coala_phase1_scope_decisions.md`): `semantic_snapshot_id` plumbed-but-null on the assembler path; confirm the Phase-2 collector now feeds on the live loop.
- **A1/A2 prompt overlays inactive** (`active: false`) and need `provider: xai` variants before any flip-to-active (live app runs xAI).
- **Non-tax module catalogue `required_data`/`sequencing` metadata is null by design** — only tax authored; author the rest if module-level sequencing is wanted.
- **`tests/Integration` is now wired into `phpunit.xml`** (the landing did this) — keep it in CI.
- **Cassette re-record** (`CassetteModelProvenanceTest` WARN/skip) remains deferred.
- **`ai_messages` forensic-column purge** (PII bloat) — pre-existing deferred DB-hygiene debt, now larger with episodic columns.
- **Option A shell-deletion** — the eventual single-loop step; its own future design call.

## 7. Out of scope

Option A shell-deletion; Phase 6 learning actions (episodic→semantic promotion, dense recall); planner default flip; A1/A2 overlay flip-to-active; any change to `FynSystemPrompt::text()`; any change to the write-safety contract; non-tax corpus growth; the prod deploy itself (CSJ's call, after csjones green). This spec covers the landing, its stabilisation, and the cleanup it forces — nothing further.

## 8. Success criteria

1. csjones deployed from `dev` with the 8 migrations + reseed + corpus reindex; **Fyn read + write green on web AND `/m`**, advice + onboarding; admin pages no 500; Azlan journey GREEN.
2. The deflection fix landed: property/mortgage/liability/goal/estate capture turns no longer deflect; golden eval deflection rate zero across entity types.
3. CLAUDE.md canonical section, `README.md:504`, and the three memory files updated to "CoALA on dev (#550); prod pre-CoALA"; the new `project_coala_landed_on_dev.md` memory written and MEMORY.md index refreshed.
4. AI pricing rate verified (or explicitly re-flagged as placeholder) before any cost figure is used.
5. Prod deploy (when CSJ calls it) green end-to-end, web + `/m`, with the migrations + reindex run and `laravel.log` clean.
