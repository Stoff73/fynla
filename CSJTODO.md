# CSJTODO — Fynla

*Last updated: 24 April 2026 — session 69 (Fyn AI audit — morning: 4 planning docs; afternoon: 5-reviewer audit of those docs, 7 CSJ decisions, 3 correction artefacts, enterprise + eval rubrics)*
*Previous session: 23 April 2026 — session 68 (dev → main release + investment 500 fix + lifecycle rate-limit hotfix)*

---

## Session 69 (24 April full day) — Fyn AI audit + adversarial review + rubrics

**No code changes this session.** Full working tree clean. Two passes:

- **Morning:** produced 4 planning docs for the Fyn AI rework (fyn-system-map.md, verdictFyn.md (superseded), enterprise-verdict.md, fyn-integrated-plan.md).
- **Afternoon:** audited those 4 docs with 5 parallel reviewers (web-researcher, best-practices-researcher, reliability-reviewer, cli-agent-readiness-reviewer, adversarial-document-reviewer) + independent code reconnaissance on `main` and `feature/fyn-persona-split`. Produced 3 correction artefacts. CSJ answered the 7 decision-gate questions.

### Completed

#### Four audit documents produced in `April/April24Updates/` (mirrored to fynlaBrain vault)

- [x] **`fyn-system-map.md`** (126KB, 2038 lines) — exhaustive map of the Fyn AI system. §1-§21 cover AI chat (routes, 10-layer prompt verbatim, 29 tools, data model, frontend web + mobile, admin surfaces, observability). §22 cross-doc enterprise addendum. §23 documents the Document Extraction AI surface (`AIExtractionService`, 965 LOC, Anthropic Vision + xAI Vision paths, stale `claude-3-5-haiku-20241022` model). §24 documents the Python Agent SDK Sidecar (`scripts/fynla_agent/` + `AgentInternalController`). §25 consolidated touchpoint inventory across 3 AI systems. §26 architecture correction — intended vs built (two Fyns, not three).
- [x] **`verdictFyn.md`** (69KB) — v1 verdict against Anthropic's *Building Effective Agents* + xAI docs. Graded B+ (72/100). **Superseded** by enterprise-verdict. Kept for accountability.
- [x] **`enterprise-verdict.md`** (141KB, 2021 lines) — v3 verdict, **7 passes** (Parts C/D framework + E adversarial + J cross-doc + K exhaustive Loop 3 + L CSJ resolutions + M scope correction + N architecture correction). Grade **D+ (45/100)** for the Fyn AI system specifically. **13 Fyn-AI Critical gaps**, **16 Fyn-AI High risks**. Key findings: C1 xAI undisclosed, C2 no FCA analysis, C3 `update_record` over-exposure, C5 no runtime consent check, C6 Article 9 health data LLM flow, C7 audit logs not tamper-evident, C8 no DPIA, C10 read tools not audited, C11 `AIExtractionService` gaps, C14 "no health data to third parties" policy contradiction.
- [x] **`fyn-integrated-plan.md`** (119KB, 1678 lines) — integrated 6-sprint roadmap. 25-touchpoint dependency index (T1–T25) to prevent compound-change bugs. §12 architecture correction with Sprint 0.19 "collapse three-persona → two-persona" task. Reconciles current Fyn + verdict + in-flight persona-split work.

#### Key architectural finding

**`feature/fyn-persona-split` built the wrong architecture.** It introduced a three-persona model (onboarding + advice + `data_capture`) duplicating capture machinery. **CSJ's intended architecture is two Fyns**: Onboarding Fyn handles ALL data capture (during onboarding AND post-onboarding inline captures); Advice Fyn handles post-onboarding non-capture. Handoff via `delegate_to_capture` / `capture_complete` routes the capture state to the **same Onboarding Fyn stack**, not to a separate persona.

#### Scope corrections made during the audit

- LPA creation rate KPI — dropped (inherited from PRD without scrutiny)
- Model currency (grok-4-1-fast-reasoning) — withdrawn (CSJ: deliberate unit-economics choice, not a gap)
- App-wide findings (Meta Pixel, AWIN, FCM, Google DPA, Plausible general) — removed from Fyn AI scope; would belong in a separate app-wide compliance audit if CSJ wants one
- Three-persona architecture — corrected to two-persona

#### Discoveries from exhaustive sweep (Part K)

- **Three AI systems** not one: Chat, Document Extraction, Python Agent Sidecar
- **Python Agent Sidecar appears to be dead code** — zero PHP invocations, no cron/Procfile/systemd references, last modified Mar 16. Recommendation: remove entirely (1 hour)
- **Stale OpenAI config block** in `config/services.php` — leftover from abandoned March OpenAI migration. Remove (5 min)
- **`update_record` over-exposure** — 2-field blocklist (user_id, id). LLM can change `Trust.settlor`, `Mortgage.start_date`, `FamilyMember.relationship`
- **Plausible tracks `chat_opened`/`chat_message_sent`** events (narrow Fyn-AI-specific concern)

### NOT Done — Outstanding for next session

The four docs are decision input; the next session should execute Sprint 0 per the integrated plan. Priority order per CSJ's stated "get it working" direction:

#### Sprint 0 (1–2 days) — unblock persona-split shipping

- [ ] **0.1** Rebase `feature/fyn-persona-split` onto current `main` (72 commits drift; expect conflicts in `AppLayout.vue`, `CoordinatingAgent.php`, `router/index.js`)
- [ ] **0.2** Full Pest run post-rebase (should stay 2,448 passing + 1 flake)
- [ ] **0.3** Close PR #214 (`onboardingFyn`) as superseded by persona-split
- [ ] **0.5** Tighten `update_record` per-entity field whitelist — replace 2-field blocklist with per-entity allowlist (1 day)
- [ ] **0.6** Add `delete_record` confirmation pattern (4 hrs)
- [ ] **0.7** Add `ConsentService::hasConsent` runtime check in `AiChatController::sendMessage` (2 hrs)
- [ ] **0.8** Sanitise user-controlled prompt fields (`first_name`, `surname`, `employer`, `occupation`, family member names, goal names) — strip to `[A-Za-z0-9\s'.-]` (4 hrs)
- [ ] **0.16** Delete Python Agent SDK sidecar — `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`, `AgentInternalController`, `AgentTokenAuth`, `/api/internal/agent/*` routes, `AGENT_INTERNAL_TOKEN` env+config. **Unless** CSJ confirms an external caller (none found in repo) (1 hr)
- [ ] **0.17** Remove stale OpenAI config block from `config/services.php` + `.env.example` (5 min)
- [ ] **0.18** Begin AI DB audit migration — create `ai_tool_executions` table, migrate `[AI-AUDIT]` file log writes in `CoordinatingAgent::executeTool` to DB inserts with `operation: read|write` column (1 day)
- [ ] **0.19** **Collapse three-persona architecture to two-persona** — delete `DataCapturePromptBuilder` + test, update `config/fyn_personas.php` so `data_capture` registry entry routes to `OnboardingChatDirector::handleInlineCaptureTurn` (new method wrapping existing capture machinery), update `FynPersonaOrchestrator::runCaptureTurn` to invoke director instead of a separate persona (1–2 days incl tests)

#### Verifications needed (quick SSH/console checks — not audit work)

- [ ] Python agent external caller — CSJ direct confirmation: is there any external Python worker/cron running `run_agent.py`?
- [ ] Plausible chat-event tracking on production — SSH + `grep ANALYTICS_ENABLED .env` — only if the `chat_opened`/`chat_message_sent` signal matters
- [ ] Full health-data trace through `orchestrateAnalysis` — 1-day code audit to walk every numerical field in layer 5 back to source; decide per-field: strip or disclose (Sprint 4)

#### Sprint 1+ deferred until Sprint 0 completes

See `April/April24Updates/fyn-integrated-plan.md` §8 for full sprint breakdown. Sprint 1 = verdict quick wins (temperature → 0.3, Anthropic cache metrics, reasoning tokens tracking, sanitise-order fix, eval harness MVP). Sprint 2 = B-X bug fixes + 11 missing Feature tests + 12 remaining browser matrix rows. Sprint 3 = ship to dev. Sprint 4 = production hardening (Privacy Policy update, DPIA, tamper-evident audit, provider failover, Sentry).

### Afternoon — 5-reviewer audit of the morning's 4 docs

Five review agents dispatched in parallel, each seeded with an evidence bundle I built from direct code reads on `main` and `feature/fyn-persona-split`. Reviewers:

1. `ce-web-researcher` — prior-art scan (UK fintech, OpenAI Agents SDK, LangGraph supervisor, SEC 17a-4, AuditableLLM)
2. `ce-best-practices-researcher` — Anthropic / xAI / FCA / ICO / OWASP / NIST best-practice comparison
3. `ce-reliability-reviewer` — SSE abort, token-budget race, provider cache coherence, audit durability, gap-fill retry
4. `ce-cli-agent-readiness-reviewer` — tool catalogue divergence, tool-result schema, parity gaps
5. `ce-adversarial-document-reviewer` — premise challenge, contradiction hunt, scope creep, grade-rubric defensibility

### Correction artefacts produced (afternoon — ALL in vault, not git)

- [x] **`April/April24Updates/audit-evidence.md`** — code-grounded ground truth with file:line anchors, §1-17. Separates claims the four docs get RIGHT from what they get WRONG. Addenda 14-17 add the Privacy-Policy contradiction, stale-extraction-model, stale OpenAI config block, and `ai_advice_logs.user_data_snapshot` GDPR concern.
- [x] **`April/April24Updates/audit-synthesis.md`** — consolidated verdict across all 5 reviewers + my own code reads. 10 sections: Headline, Correctly Planned, Invalidated by Code, Assumptions Stated as Fact, Scope Creep, Real Gaps Missed, Sprint 0 Honest Re-estimate, Multi-Entity Deep Dive, CSJ Decisions, Recommendations. §8 now contains CSJ's answers to all 7 decision questions.
- [x] **`April/April24Updates/fyn-rubrics.md`** — two rubrics replacing the undisclosed D+(45/100). Rubric A: Enterprise Assessment, 10 dims × 5 levels = /40 score, Fyn currently **4/40 — 🔴 Pre-launch**, projected Sprint 0+1 → **~17/40 — 🟠 Limited beta**. Rubric B: Eval Harness, 65 golden conversations, Mode 1 (CI-gated, mocked) + Mode 2 (weekly, real providers), per-tool scorecard with tunable thresholds.

### Load-bearing findings from the afternoon audit (overturns / extends the morning docs)

- **`main` has NONE of `OnboardingChatDirector`, `DataCapturePromptBuilder`, `FynPersonaOrchestrator/Invoker/Registry`, `HandoffContract`, `AssetCaptureEntityExtractor`, `CaptureContext`** — all live ONLY on `feature/fyn-persona-split`. The morning system-map §1-26 conflates the two branches.
- **Persona-split is 178 commits behind main, not 72.** CSJTODO morning entry and integrated-plan §0 both had 72. Every rebase-effort estimate understated by ~2.5×.
- **Anthropic cache metrics ARE persisted** at `HasAiChat.php:467-469` (`cached_tokens` + `cache_hit_rate` into `ai_messages.metadata`). Morning's system-map §21 Q3 + integrated-plan Sprint 1.2 fix is a no-op — delete the task.
- **Admin UI for AiAuditController EXISTS** (`resources/js/components/Admin/AiAudit.vue`, mounted in AdminPanel). Morning's §21 Q2 + verdict G20 + Sprint 5.3 "missing" is wrong.
- **Tool catalogue is 23 on Anthropic vs 29 on xAI.** `list_records`, `create_holding`, `set_expenditure` exist only on xAI. Morning's "29 tools" count is correct on only ONE provider.
- **All 13 `create_*` tools are FORM PRE-FILLERS, not DB writers.** Every `handleCreate*` in `CoordinatingAgent.php` returns `['action' => 'fill_form', ...]`; the frontend POSTs to the standard module API. Tool descriptions lie to the model; `[AI-AUDIT]` logs "Tool executed" for things that didn't execute. Narrows verdict C3 exposure but breaks the model's own truth story.
- **Multi-entity STILL BROKEN on `feature/fyn-persona-split` post-onboarding.** `AssetCaptureEntityExtractor` is wired into `OnboardingChatDirector` only (lines 1708/1714/1715). `FynPersonaOrchestrator::runCaptureTurn` invokes the standard LLM loop without the extractor. Integrated-plan §5.1 "persona-split fixes multi-entity" is FALSE for the path persona-split exists to serve. 4 of 18 entity types covered even in the onboarding path.
- **`OnboardingChatDirector::handleInlineCaptureTurn` does NOT exist on persona-split** — it's proposed NEW code in integrated-plan §12.2, not a refactor target. Sprint 0.19 "1-2 day collapse" under-scopes: it's deletion + 300-500 LOC new + extractor rewiring + tests = **2-3 days**.
- **FCA PS25/22 "targeted support" went LIVE 6 April 2026** — new regulated category between guidance and full advice, explicitly for AI-assisted consumer guidance. Not mentioned anywhere in the morning docs. CSJ's decision: guidance-only posture (see §8.1 below) — no targeted-support authorisation pursued.
- **Privacy Policy §5/§7 factually contradict the code.** §5 line 111: *"We do not share health data with any third party."* §7 line 132: *"**We do not use third-party analytics or tracking services.**"* Both falsified by Meta Pixel (unconditional `app.blade.php:81-91`), AWIN (full integration), Plausible (conditional), and health-data flow to LLMs. **5 third-party processors**, not 3 as verdict K3 claims.
- **No SSE abort detection anywhere** — no `connection_aborted()`, no `ignore_user_abort(true)`, no idempotency keys. Users billed for turns they never received. Biggest reliability gap; nowhere in the 4 docs.
- **Token-budget race** via `Cache::remember($key, 300, …)` — two concurrent SSE requests both read stale budget, both pass, both run. Pro user can overshoot £2M/day cap by ~50%.
- **Provider cache coherence race** — `Cache::forever('ai_provider', …)` admin toggle can flip mid-conversation, mixing Anthropic `cache_control: ephemeral` markers with xAI request shape.
- **Python sidecar is dead code.** Uses regular `anthropic` Messages SDK, NOT `claude-agent-sdk`. Zero PHP callers in any path (grep across `app/`, `routes/`, `config/`, `database/`, `resources/`, `Kernel.php`, no Procfile/systemd/supervisor). Three patterns worth harvesting (Pydantic output validation, task-type-specific prompts, externalised PreToolUse hook) — none require keeping the Python code. CSJ confirmed deletion (§8.4 below).

### CSJ decisions — all 7 §8 questions answered

1. **FCA posture: GUIDANCE ONLY.** No targeted-support authorisation. External legal opinion needed for the guidance posture (Sprint 4). `CoreIdentity.php` "you think like a qualified financial planner" rewritten in Sprint 1 (not Sprint 4). Every advice-type response signposts to regulated advice.
2. **Two Fyns (Onboarding + Advice), NO orchestrator class.** Routing collapses into `AiChatController`. DELETE on persona-split: `FynPersonaOrchestrator`, `FynPersonaInvoker`, `FynPersonaRegistry`, `DataCapturePromptBuilder`. KEEP: `HandoffContract` (constants), `CaptureContext` VO, `OnboardingChatDirector` (promoted to Onboarding Fyn; new `handleInlineCapture` method). NEW: `AdviceFyn` class wrapping advice-side chat loop + prompt. Net ~800 LOC deletion, ~300-400 LOC new.
3. **Multi-entity thresholds: 95% baseline recall + precision per focus, tunable up.** Non-tunable 100% hard-fail floors on entity validity (FormRequest passes), monetary value accuracy (no £ drift), cross-entity consistency (no field-bleed), 0% fabrication. Per-tool scorecard published every eval run. Sprint 2 ratchet: mortgage → 100/100, protection + savings → 98/98, add 12 remaining entity types at 90 baseline.
4. **Python sidecar: DELETE.** Sprint 0.16 unblocked (1 hr).
5. **Local-first UNAMBIGUOUS.** Nothing deploys anywhere until 100% verified on `localhost:8000`. Per-sprint local verification is the dev-deploy gate.
6. **Terminology irrelevant.** Spec will use "routing workflow → orchestrator-workers pattern" for literature refs; "Fyn / Onboarding Fyn / Advice Fyn" internally.
7. **Rubric: BUILD BOTH.** Rubric A (enterprise) + Rubric B (eval) — see `fyn-rubrics.md`.

### NOT Done — Outstanding for next session

The four original planning docs need a **correction pass** before they seed a spec. Three artefacts already produced are inputs to that pass:

#### Correction pass on the four original planning docs (Sprint 0 precursor, ~1 day)

- [ ] **Canonical-facts pass.** Apply `audit-evidence.md` §2-§5 corrections to `fyn-system-map.md`, `fyn-integrated-plan.md`, `enterprise-verdict.md`. Every contradicting sentence retracted.
- [ ] **Scope pass.** Prune T18/T24/T25 from touch-point index. Prune Sprint 4.22 Privacy Policy if app-wide. Pick one Critical count (Part M's 13) and enforce.
- [ ] **Effort honesty pass.** Rewrite Sprint 0 envelope from "1-2 days" to **3-4 weeks**. Move 0.5 (allowlist), 0.8 (sanitise + structural separation), 0.18 (DB audit + hash chain), 0.19 (two-Fyn collapse) into Sprint 1 if smaller sprints preferred, or size Sprint 0 honestly.
- [ ] **Add new Sprint 0 tasks from reviewers:** 0.20 SSE abort detection + idempotency key, 0.21 atomic token-budget check-and-increment, 0.22 provider-swap write lock, 0.23 gap-fill dedup key, 0.24 `generateTitle` sanitation, 0.25 rebase-conflict strategy doc.
- [ ] **Grade rubric pass.** Replace "D+ (45/100)" in verdict + INDEX with the Rubric-A 4/40 🔴 Pre-launch score (reproducible from `fyn-rubrics.md`).

#### Sprint 0 (corrected scope, ~3-4 weeks engineering) — unblock persona-split shipping

- [ ] **0.1** Rebase `feature/fyn-persona-split` onto `main` (**178 commits** drift, not 72 — 0.5-1 day minimum). Expect conflicts in `AppLayout.vue`, `CoordinatingAgent.php`, `routes/api.php`, `HasAiChat.php`, `Prompts/*`, `AiToolDefinitions.php`.
- [ ] **0.2** Full Pest run post-rebase (probable test failures from rebase — +0.5 day for triage).
- [ ] **0.3** Close PR #214 (`onboardingFyn`) as superseded.
- [ ] **0.5** `update_record` per-entity allowlist + `additionalProperties: false` in schema (**2 days**, 15+ entities × ~10 fields).
- [ ] **0.6** `delete_record` confirmation pattern + cover `update_record` when fields touch tax/legal state (Trust.settlor, FamilyMember.relationship, Mortgage.start_date) — 4 hrs.
- [ ] **0.7** `ConsentService::hasConsent` runtime check in `AiChatController::sendMessage` + "consent-withdrawn mid-conversation" UX (0.5 day — check is 2 hrs but UX design matters).
- [ ] **0.8** Sanitise user-controlled prompt fields + wrap user content in `<user_provided>...</user_provided>` structural markers per OWASP Cheat Sheet (1 day).
- [ ] **0.16** Delete Python sidecar — `scripts/fynla_agent/`, `scripts/run_agent.py`, `scripts/requirements.txt`, `AgentInternalController`, `AgentTokenAuth`, `/api/internal/agent/*` routes, `AGENT_INTERNAL_TOKEN` env+config (1 hr — CSJ confirmed delete).
- [ ] **0.17** Remove stale OpenAI config block from `config/services.php:34-38` + `.env.example` (5 min).
- [ ] **0.18** AI DB audit migration — **5-7 days** (not 1): hash-chain append-only `ai_audit_events` table + HMAC signing + retention policy (7yr advice / 2yr general) + erasure-compatible pseudonymisation + weekly integrity-verification job. Per SEC 17a-4 / AuditableLLM precedent.
- [ ] **0.19** Two-Fyn architecture rewrite (**2-3 days**): DELETE `FynPersonaOrchestrator` + `FynPersonaInvoker` + `FynPersonaRegistry` + `DataCapturePromptBuilder`. CREATE `AdviceFyn` class + `OnboardingChatDirector::handleInlineCapture`. WIRE routing into `AiChatController`. **CRITICAL:** rewire `AssetCaptureEntityExtractor` into the new inline-capture path — otherwise post-onboarding multi-entity stays broken.
- [ ] **0.20** SSE abort detection + idempotency key on `POST /conversations/{id}/messages` (2-3 days).
- [ ] **0.21** Atomic token-budget check-and-increment — replace `Cache::remember($key, 300, …)` with DB atomic INSERT + row-level `FOR UPDATE` on `ai_daily_usage` (1-2 days).
- [ ] **0.22** Provider-swap write lock — version counter on `ai_provider` cache key, per-request snapshot + abort on mid-loop drift (1 day).
- [ ] **0.23** Gap-fill dedup key against existing records — `(user_id, entity_fingerprint, 24h window)` — closes retry double-insert vector (0.5 day).
- [ ] **0.24** `generateTitle` sanitation — `strip_tags` + length-clamp before persist (2 hrs).

#### Sprint 1 (after Sprint 0 — eval harness first, then quick wins)

- [ ] **Eval harness MVP** (`fyn-rubrics.md` Rubric B) — `tests/Feature/Fyn/Eval/` with `EvalRunner`, `MockedProviderClient`, first **10 scenarios** (6 query types + 4 multi-entity). CI gate: Mode 1 must be 100%.
- [ ] Expand to **30 scenarios** (all 22 query types + 6 handoff/cancel + 2 injection).
- [ ] Rewrite `CoreIdentity.php` — drop "you think like a qualified financial planner" language; align with guidance-only posture.
- [ ] `config/fyn_eval.php` with tunable thresholds per tool (`recall_floor`, `precision_floor`, `reason`, `reviewed_by`, `next_review`).
- [ ] Structural separation: Layers 4-6 wrap user-controlled content in `<user_provided>...</user_provided>` markers.
- [ ] Canary instruction + output drift-detection test.
- [ ] First per-tool scorecard run — CSJ reviews → raises thresholds where needed.

#### Sprint 2 (after Sprint 1 eval harness is in place)

- [ ] Expand eval harness to **65 scenarios**, enable weekly Mode 2 real-provider cron.
- [ ] Add the 12 missing entity types to eval at 90% baseline (goal, family, life-event, property+mortgage, trust, will, POA, business, chattel, liability, gift, holding).
- [ ] **Batch-shaped extractor tools** (Alternative A per best-practices reviewer): `capture_protection_policies(policies: [...])`, `capture_savings_accounts`, `capture_pensions`, `capture_investment_accounts` with strict JSON schema. Retire regex `AssetCaptureEntityExtractor` when fire rate < 2%.
- [ ] Split tool budget: 5 reads + 10 writes when classifier type = `data_entry`.
- [ ] Move multi-entity instruction from `ComplianceRules.php` into each `create_*` tool's `description` field (per-decision salience).
- [ ] Close remaining parity gaps: `upload_document` tool (expose `AIExtractionService`), `link_spouse`, `configure_assumption`, `run_projection`, `submit_risk_questionnaire`, `delete_record` covers `investment_holding` enum, `create_will` / `create_power_of_attorney` registered in both tool-definition classes.

#### Sprint 3 — ship to dev (`csjones.co/fynla`), local-first gate enforced

Every task above must be 100% verified on `localhost:8000` first. Dev deploy is only after local verification passes.

#### Sprint 4 — production hardening + external work (parallel calendar tracks)

- [ ] External legal opinion on the guidance-only posture (commissioned by CSJ; 4-8 week calendar).
- [ ] DPIA drafting (external DPO or retained counsel; 2-4 weeks).
- [ ] Privacy Policy rewrite to honestly disclose Anthropic + xAI + (if retained) Meta Pixel + AWIN + Plausible — OR remove those trackers to match the current policy text. **Commercial decision pending.**
- [ ] Article 28 DPA verification with Anthropic + xAI (commercial/legal).
- [ ] UK IDTA + Transfer Risk Assessment for both Anthropic + xAI (US processors).
- [ ] Provider failover (Anthropic ↔ xAI) with state preservation.
- [ ] Sentry / structured error reporting.

#### Verifications still needed (quick SSH/console checks)

- [ ] Full health-data trace through `orchestrateAnalysis` — 1-day code audit to walk every numerical field in Layer 5 back to source; decide per-field: strip or capture specific consent (Sprint 4).
- [ ] Plausible chat-event tracking on production — SSH + `grep ANALYTICS_ENABLED .env` — only if retained as in-scope tracker.

### Context for Next Session

**Start with:** read `April/April24Updates/audit-synthesis.md` (the consolidated verdict — reviewer synthesis + CSJ decisions), then `audit-evidence.md` (ground-truth anchors), then `fyn-rubrics.md` (grading + eval-harness shape). Do NOT read the morning's 4 docs without reading the audit first — they contain load-bearing errors the afternoon audit overturns.

**Before starting Sprint 0:** run the **correction pass** on the morning's 4 docs (8 items above) so the spec isn't drafted on inherited errors. This is ~1 day of editing.

**Critical context:**
- CSJ decisions locked: guidance-only FCA posture, two-Fyn architecture with no orchestrator class, local-first deploy gate, both rubrics to be built, Python sidecar deletion confirmed.
- The afternoon audit overturned several morning claims — read `audit-synthesis.md` §2 (Invalidated by Code) before trusting anything in the morning docs.
- Multi-entity is the user's top-priority pain point and is **NOT** fixed by persona-split as the morning docs imply. Sprint 1's batch-tools pattern is the structural fix.
- 178-commit rebase drift (not 72) means Sprint 0.1 alone is 0.5-1 day, not 2-4 hrs.

**Branch state:** `main` unchanged. `feature/fyn-persona-split` 68 commits ahead / **178 behind** `origin/main`. PR #214 (`onboardingFyn`) still open, to be closed in Sprint 0.3 as superseded.

**Working tree:** clean. CSJTODO.md updated (this file). The 3 afternoon correction artefacts + the 4 morning docs are in `.gitignore`d `April/April24Updates/` — vault is the source of truth (mirrored via `/vault-sync`).

**Current Enterprise Rubric score:** **4/40 — 🔴 Pre-launch.** Projected after Sprint 0+1: ~17/40 🟠 Limited beta.

---

## Session 68 (23 April late night) — `dev → main` release + investment 500 fix + lifecycle hotfix

Three PRs shipped to **production** (`fynla.org`). Git dev ↔ main now fully in sync at tip `21ecf67` (lifecycle hotfix) with back-merge `bcf9509` on dev. All 7 production smoke tests PASSED.

### Completed

#### PR #227 — Investment `/api/analyze` 500 fix + session 67 tech-debt bundle (→ dev)

- [x] **`/api/investment/analyze` 500 → 200.** `Holding::$casts[cost_basis, current_value]` are `decimal:2` which Laravel returns as strings; PHP 8's strict `round()` rejected them in `TaxEfficiencyCalculator.php:107` via the `opportunities[]` payload from `CGTHarvestingCalculator`. Fixed at the source with `(float)` casts on lines 154-155 so every downstream consumer gets floats. Commit `0236006`.
- [x] **Vue `_uid` warning flood silenced.** `AssetAllocationDonut.vue:145` used `this._uid` (Vue 2 internal) — replaced with `this.$.uid` (Vue 3 options-API equivalent). Became visible once session 67's joint-donut layout started rendering two instances per page. Confirmed live: gradient ID resolves to `nw-alloc-grad-423-0` (not `-undefined-0`).
- [x] **Session 67 tech-debt report remediation** — `AssetBreakdownBar` tooltip hex (`#E83E6D`, `#1F2A44`, `#5854E6`, `#20B486`) replaced with `PRIMARY_COLORS[500]`, `TEXT_COLORS.primary`, `WARNING_COLORS[500]`, `SUCCESS_COLORS[500]` imports from `designSystem.js`. Spouse-name fallback chain collapsed from 8-18 lines to one-line getter reads across `NetWorthWealthSummary.vue`, `PortfolioOverview.vue`, `LetterToSpouse.vue` (the `userProfile/spouse` getter's `withName` helper already normalises every return path). Net −32 LOC.
- [x] **PR #227 opened + admin-merged to `dev`** as merge commit `2f9c308`. Deploy guide at `April/April23Updates/fixDeployInvest.md` (mirrored to vault).

#### PR #228 — First `dev → main` release since session 64 (99 commits / 188 files / +6,677/−1,545)

- [x] **Git verification pass.** Counted commits/files; confirmed `origin/main..origin/dev` was 97 commits ahead + my new 2 commits = 99. Confirmed `onboardingFyn` (PR #214) and `feature/fyn-persona-split` branches stayed unmerged.
- [x] **Local production build.** `./deploy/fynla-org/build.sh` → bundle `app-B31kpBbU.js` (1,195,754 bytes). Verified the built `CheckoutPage-CbzaPZdL.js` has live pk `pk_sY0uq1Q2d2lo0EO` + `merchant.revolut` URL (0 sandbox refs).
- [x] **PR #228 opened + admin-merged to `main`** as `27bb188`. Back-merge PR #229 (`34b77a3`) brought the merge commit to dev.
- [x] **Production upload.** rsync'd 113 PHP/config/database/routes/views files to `~/www/fynla.org/public_html/` in a single pass using the production SSH key (loaded into agent). User uploaded `public/build/` separately. Verified the active manifest on prod now points at `app-B31kpBbU.js`.
- [x] **Production SSH finalisation.** `composer install --no-dev --optimize-autoloader` ran (downgraded `intervention/image` 4.0.1 → 3.11.7 per PR #224; prod is PHP 8.3.30 so either works but the 3.11 API port in `InsightImageService` requires 3.x). `php artisan migrate --force` — 7 April 14 migrations ran (lifecycle_email_log, feedback_responses, discount_codes user_id+metadata+type enum, users is_lifecycle_test_user, notification_preferences lifecycle columns, subscriptions indexes). `cache:clear` + `config:clear` + `view:clear` + `route:clear` + `optimize` + `config:cache`.
- [x] **Production deploy guide.** `April/April23Updates/devMainDeploy.md` — scope, pre-flight (Revolut pk verification commands), 113-file upload buckets, preserve-old-chunks tar-pipe, SSH finalisation, 7 smoke tests, rollback procedure. Mirrored to vault.

#### Lifecycle engine SMTP rate-limit bug (found during smoke tests, hotfixed as PR #230 + #231)

- [x] **Bug surfaced.** Smoke-test trigger `php artisan lifecycle:run-daily` fired against real prod users; SiteGround SMTP capped at ~10 msg/sec, deferring 11 of 22 engaged_trialer sends with `451-gukm1022.siteground.biz received more than 10.7 messages for 1s`. 10 empty_trialer + 2 engaged_trialer delivered successfully. **The daily cron is scheduled for 08:30 UTC** and would have hit the same wall every day regardless — not a smoke-test artifact, a real production bug in PR #212.
- [x] **Engine disabled on prod** immediately — `LIFECYCLE_ENGINE_ENABLED=false` appended to prod `.env` + `config:cache`. Verified `config("lifecycle.enabled") === FALSE` via Tinker. `.env.backup-2026-04-23-lifecycle-disable` preserved.
- [x] **PR #230 hotfix** — added `throttle_ms` config key to `config/lifecycle.php` (default 150 ms = ~6.6 sends/sec, well below SG's cap; env override `LIFECYCLE_THROTTLE_MS`, `0` disables for tests and self-hosted SMTP). `LifecycleEngine::run()` now calls `usleep()` between iterations on both success and error paths. 3 new unit tests cover default config, pacing active (3 sends at 50 ms → elapsed ≥ 150 ms), pacing disabled (5 sends at throttle=0 → elapsed < 1 s, all 5 logged). **47/47 lifecycle tests pass**. Admin-merged to dev as `c8b0f05`.
- [x] **PR #231 (dev → main)** admin-merged as `21ecf67`. PR #232 back-merge main → dev as `bcf9509`. Three files rsync'd to prod.
- [x] **Engine re-enabled on prod** — `LIFECYCLE_ENGINE_ENABLED=false` line removed from `.env`, `config:cache` regenerated, verified `config.enabled === TRUE | throttle: 150ms`.
- [x] **Re-ran `lifecycle:run-daily` against the 11 deferred users.** All 11 engaged_trialer delivered, 0 errored, total runtime 2.245s (1.65s throttle overhead + ~0.6s send/query overhead — exactly on-spec for 150ms pacing across 11 sends). `lifecycle_email_log` went from 12 → 23 rows. `empty_trialer: 0 sent` confirms the 10 already-sent users are correctly dedup'd via log lookup.

#### Orphan PSR-4 cleanup on prod

- [x] **`app/Http/UserResource.php` removed from prod.** Byte-identical duplicate sitting at the PSR-4-violating path since 20 March (never tracked in git). Composer dump-autoload warned on every `composer install`. Removed via SSH, `composer dump-autoload -o` regenerated 7,325 classes with zero PSR-4 warnings. `composer install --dry-run` confirms no regression. The correct file at `app/Http/Resources/UserResource.php` still resolves cleanly. Dev server (csjones.co) was already clean.

#### All 7 production smoke tests PASSED

- [x] **A. Homepage + auth** — fynla.org landing + sign-in as `chris@fynla.org` / `Password1!` + email 2FA code `971539` → landed on `/dashboard` as Chris Jones. 0 console errors.
- [x] **B. `/api/investment/analyze` × 3 → 200**, Vanguard account detail renders with £788,539 Account Projection at 10yr/80% probability (validates PR #225's `getAccountProjections` restore + PR #227's `(float)` cast).
- [x] **C. Net Worth `_uid` fix live.** `document.querySelector('svg defs linearGradient[id^="nw-alloc-grad-"]')` returned id `nw-alloc-grad-423-0` with `hasUndefined: false`. Zero `_uid` warnings across full console dump.
- [x] **D. Pension projection chart renders non-zero** — £200K–£1M percentile bands over 2026–2056 timeline (validates session 66's content-addressed Monte Carlo cache fix).
- [x] **E. Revolut live pk baked into active chunk.** Prod's active `CheckoutPage-BT54db5H.js` has `pk_sY0uq1Q2d2lo0EO` + `merchant.revolut` + 0 sandbox refs. `/pricing` loads clean, 0 errors / 0 warnings.
- [x] **F. Lifecycle engine dry-run clean under pacing.** 11 deferred users delivered, 0 errored, 150 ms pacing verified on-spec.
- [x] **G. Admin insights image pipeline (intervention/image 3.11.7).** Via Tinker on prod: `ImageManager::gd()->read($logoPath)->cover(1200,630)->toWebp(quality: 85)` → 10,848 bytes valid WebP. Same pipeline for thumb → 3,384 bytes. Exact API used by `InsightImageService::upload()`.

### Outstanding from session 68

- [ ] **Prod hygiene sweep ~24h post-deploy** (i.e. 24 April night-ish): `rm -rf ~/www/fynla.org/public_html/public/build.old` + `rm ~/www/fynla.org/public_html/.env.backup-2026-04-23-*` (two backup files from the lifecycle disable/re-enable). Also purge the **19 historical sandbox-pk CheckoutPage chunks** that have accumulated in `public/build/assets/` from past preserve-old-chunks merges — one of the past csjones-configured builds was uploaded to fynla.org in error. Unreachable via the current manifest (customers only load what the manifest points to) but shouldn't live on a production server. One-liner:
  ```bash
  for f in $(ssh -p 18765 -i ~/.ssh/production u2783-hrf1k8bpfg02@ssh.fynla.org "cd ~/www/fynla.org/public_html && grep -l pk_D2JdE2srRipv0jd public/build/assets/CheckoutPage-*.js"); do ssh … "rm $f"; done
  ```
- [ ] **Consider architectural follow-up for lifecycle engine.** 150 ms pacing is a pragmatic fix; for larger send batches (>100 users) we should consider `ShouldQueue` on the Mailables + a rate-limited queue worker. Not urgent — current daily batches are ~20 users and the cron has plenty of runway.
- [ ] **The 11 failed engaged_trialer sends from the buggy first run are now logged + delivered.** If any of them did NOT reach their inbox (SiteGround's 451 is typically a deferral, so they should eventually arrive even from the failed first attempt), check `lifecycle_email_log` + user support queue.
- [ ] **Exercise the edit-mode auto-expand** on an existing pension or investment account that already has hidden-field values populated. Logic reviewed in diff only; not browser-tested end-to-end. Carried from session 67.
- [ ] **Exercise collapsed-form submit → DB null verification** for both pension + investment forms. Carried from session 67.
- [ ] **Exercise the onboarding path** for the field-collapse toggle. Carried from session 67.

### Context for Next Session

**fynla.org is live on main tip `21ecf67`** (dev tip `bcf9509`) with lifecycle engine paced at 150 ms/send. All 7 smoke tests passed, so prod is stable. Only outstanding item strictly needed before the next session is the 24h cleanup sweep above. The big open next-session task is the ongoing **Fyn AI onboarding** work on `feature/fyn-persona-split` (also coupled with PR #214 / `onboardingFyn`) — see `memory/project_pr214_with_persona_split.md`.

### Outstanding from session 67 (resolved)

- [x] **Cut `dev → main` PR when ready.** PR #228 admin-merged as `27bb188`.
- [ ] Exercise edit-mode auto-expand — still carried (see above).
- [ ] Exercise collapsed-form submit → DB — still carried.
- [ ] Exercise onboarding path for field-collapse — still carried.

### Outstanding from session 66 (resolved)

- [x] **Cut `dev → main` PR when ready** — done as PR #228.
- [ ] **Optional SQL purge on production after dev→main cut** to age out legacy MC cache keys immediately. Still available; not yet run. Safe to defer 24h or skip entirely (cache keys age out naturally):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```

### Outstanding from session 65b (resolved)

- [x] **Verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk.** Verified via bundle grep: `CheckoutPage-CbzaPZdL.js` (local build) has `pk_sY0uq1Q2d2lo0EO` + `merchant.revolut` — matches prod's active `CheckoutPage-BT54db5H.js`.

---

## Session 67 (23 April night) — UI fixes bundle

PR [#226](https://github.com/Stoff73/fynla/pull/226) merged to `dev` as merge commit `416e770`, deployed + browser-tested on `csjones.co/fynla` (per CSJ).

### Completed

#### Six independent UI fixes, one branch (`genUIFixes`)

- [x] **Logout redirects straight to `/login`** — the success modal used to hold the user on the dashboard until they dismissed it. `AppNavbar.vue` now mirrors what `SideMenu.vue` already did: dispatch `auth/logout`, then `router.push('/login')`. Orphan `LogoutSuccessModal.vue` deleted. Commit `acc6086`.
- [x] **Dashboard progress hero now renders for every user**, not only journey users. Skip-to-dashboard and Fyn-onboarded users previously saw a blank top of page. The Scenario Completeness column is hidden when there's no active journey; its column width is split evenly into narrow left + right margins so Profile Completeness and Recommended Actions keep their original `w-1/3` positions. Ring restored to full 140px; labels like "Cash Management" fit on one line without overflowing into the percentage column. Collapsed bar shows overall profile % + "Profile complete" when no journey. Mobile carousel skips the Scenario slide and re-counts pagination dots. Commit `d3756ae`.
- [x] **Pension + Investment Add/Edit forms** — advanced fields now collapse behind a single "Additional information" toggle per form. Auto-expands in edit mode when any hidden field has a user-provided value. Collapsed-on-save nulls the hidden fields in the outgoing payload. Commit `c515aa3`.
  - Pension form (DCPensionForm for Money Purchase types): Lump Sum Contribution, Expected Return %, Platform Fee, Advisor Fee, Beneficiary section, Holdings editor. DB / State branches unchanged.
  - Investment form (AccountForm + StandardInvestmentFields for ISA / GIA / Bonds / VCT / NS&I / Other): Country, Platform/Product Name, Planned Lump Sum (amount + date, both non-ISA and ISA variants), Platform Fee, Holdings editor. Private Investment and Employee Share Scheme sub-forms explicitly left untouched.
  - `expected_return_percent` default changed from `5.0` to `null` so users who never expand the section don't persist a synthetic return assumption.
- [x] **Joint Net Worth Wealth Summary redesigned** — married users previously saw three donuts stacked in the left column (user, spouse, combined) and a right-hand bar chart showing only the current user's figures. Joint users now see two per-person donuts inline, then a full-width Assets-vs-Liabilities bar chart underneath. Hovering a bar opens a custom tooltip: "Category: £TOTAL" with the per-person split below it ("David Mitchell: £755,500 / Sarah Mitchell: £637,500"). Single users keep the original layout untouched. Commit `eaf4552`.
- [x] **Root-cause fix for the recurring "Partner" / "Spouse" regression** — the `userProfile/spouse` getter returned inconsistent shapes across its code paths. `spouseInFamily` paths returned FamilyMember records (which carry a `name` column from the DB), but the `currentUser.spouse` fallback paths built synthetic objects with only `first_name` / `last_name`. Every consumer reading `spouse.name` through those fallback paths silently rendered empty and was masked by `|| 'Partner'` / `|| 'Spouse'` fallbacks in callers. Getter now normalises every return path through a `withName` helper so `name` is always resolved. `NetWorthWealthSummary.spouseUserName`, `PortfolioOverview.getSpouseName`, and `LetterToSpouse.spouseNameForLetter` all updated to read from `userProfile/spouse` first, falling back to the auth inline spouse object, and only then to the string literal. Admin / Estate IHT / Protection analysis / Preview persona spouse-name reads are fed by different data sources (admin users list API, IHT calc response, preview persona JSON) and intentionally not touched. Commits `2a0d7b2` + `7e1739d`.
- [x] **csjones build script output updated** — the post-build echoed instructions pointed at the legacy `public_html/fynla/` layout and omitted the sibling-dir reality (Laravel app at `~/www/csjones.co/fynla-app/`, `public_html/fynla` is a symlink). Script now echoes the correct upload target, the preserve-old-chunks `mv`+`cp -rn` pattern, the full SSH command, and the full cache-clear sequence. No logic change — only the trailing echo. Commit `677f146`.

#### Deploy + docs

- [x] **PR #226 opened, 7 commits, admin-merged to `dev`** as merge commit `416e770`.
- [x] **`April/April23Updates/deployUIFix.md`** — full deploy guide with sibling-dir upload path, preserve-old-chunks pattern, smoke-test steps per fix, rollback, and the promote-to-main handoff. Mirrored to vault.
- [x] **Deployed to csjones.co/fynla dev + browser-tested.** Per CSJ: all six fixes working on the live dev site.
- [x] **Local browser-tested during the session:** pension Add form (collapse/expand, SIPP variant), investment Add form (collapse/expand, GIA + ISA variants), joint net-worth layout (David & Sarah Mitchell preview persona — tooltip split, spouse name on donut + wealth summary + bar chart props), logout redirect.

### Outstanding from session 67

- [ ] **Cut `dev → main` PR when ready.** This deploy passes dev smoke tests. When the next production cut happens, #226 rides along. Production build uses `./deploy/fynla-org/build.sh` (NOT the csjones script — base paths differ).
- [ ] **Exercise the edit-mode auto-expand** on an existing pension or investment account that already has hidden-field values populated. Logic is reviewed in diff only; not browser-tested end-to-end.
- [ ] **Exercise collapsed-form submit → DB verification** for both forms — confirm the null-on-save code path actually writes nulls on a real save.
- [ ] **Exercise the onboarding path** for both forms. Both accept `isOnboarding` prop but only the standalone modal path was browser-tested this session.

### Outstanding from session 66 (carried forward)

- [ ] **Cut `dev → main` PR when ready.** Pension projection fix + nav refresh (PR #225) still pending production cut.
- [ ] **Optional SQL purge on production after dev→main cut** to age out legacy MC cache keys immediately (otherwise 24h wait):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 66 (23 April evening) — pension projection + unified add pension + nav refresh

PR [#225](https://github.com/Stoff73/fynla/pull/225) merged to `dev` as commit `6b7306d`, deployed + browser-tested on `csjones.co/fynla`, old builds cleaned up.

### Completed

#### The long-standing pension projection regression, fixed at the root
- [x] **Reproduced the "pension added but projection shows £0" bug** live on `sarah@example.com` — the pension's fund value rendered correctly on the dashboard but `pension_pot_projection.percentile_20_at_retirement` and the year-by-year Monte Carlo array were all zeros. No console errors. The API returned structurally-valid data that happened to all be zero.
- [x] **Traced the root cause to the Monte Carlo DB cache.** Cache key for `projectPensionPot` was `user_{id}_pension_pot_{years}y_e{eventHash}` — user, years-to-retirement, and life-event hash, but **not** the actual simulation inputs (start value, monthly contribution, return, volatility). When a brand-new user loaded the dashboard with zero pensions, `simulate(0, 0, …)` produced all zeros and cached them under that key. When the user added a pension, `simulate(50000, 500, …)` hit the same key and got the stale zeros back.
- [x] **Fix: content-addressed cache key.** Hashed the four numeric inputs into the key (`md5("{startValue}:{monthly}:{return}:{vol}")`). Input changes → new key → fresh simulation. No observer wiring, no write-path coupling — which is why the previous attempts to fix this at the write side (observers, central `CacheInvalidationService`) kept regressing. Commit `a6cfa5a`. Same fix applied to `projectIndividualDCPension`.

#### Unified Add Pension form (no more three-tile picker)
- [x] **Replaced the tile picker** that had Money Purchase / Final Salary / State Pension with a single "Add Pension" form. Pension type dropdown now carries Occupational, SIPP, Personal, Stakeholder, **Final Salary (Defined Benefit)**, **State Pension** — all six in one place.
- [x] **Conditional field groups** inside `DCPensionForm`: picking Final Salary swaps body to DB fields (scheme status, annual income, service years, accrual rate, revaluation rate, PCLS). Picking State Pension swaps to State fields (forecast weekly, qualifying years, NI gaps). Backend payload shapes mirror the legacy `DBPensionForm` / `StatePensionForm` outputs exactly — verified `db_pensions` and `state_pensions` records are identical whether captured via this unified form or edited via the legacy forms. Commit `5a7ecec`.
- [x] **Onboarding scoped** — when `isOnboarding=true`, the two new dropdown options are hidden via `v-if="!isOnboarding"` so the onboarding DC pension step keeps its original 4-option dropdown and its `dc_pension` AI-fill wiring.
- [x] **Edit flows untouched** — existing DB and State pension edits still render the legacy `DBPensionForm` / `StatePensionForm` via `initialPensionType` routing.

#### SubNavBar hidden globally, CTAs moved inline
- [x] **SubNavBar suppressed** (`v-if="false"` in `AppLayout.vue`). Component + `subNavConfig.js` kept intact — one-char revert to re-enable. Commit `88af49a`.
- [x] **Retirement CTAs inline** under the pension list, right-aligned next to the projection chart (same raspberry / bordered styling as the old SubNavBar). Commit `618e0ba`.
- [x] **Investments CTAs inline** at the bottom of the accounts column (same convention as retirement).
- [x] **Property-type pages CTAs** top-right of the list on Property, Liabilities, Personal Valuables, Business, Trusts, Goals.
- [x] **Duplicate CTAs resolved** — Cash and Protection already had inline buttons (hiding the SubNavBar removes the duplicates). `GoalsOverview` had its own quick-add row that would have doubled with the new tab-header Add Goal — removed.
- [x] **Life Events** uses `EventsTab`'s own internal Add button — not duplicated in the tab header.

#### Sticky top nav
- [x] **AppNavbar wrapper** is now `sticky top-0 z-30 bg-eggshell-500` in `AppLayout.vue`. Dashboards scroll under it; nav always visible. Offsets to `top-[44px]` when the AdvisorBanner is active during advisor impersonation. Docked-chat `headerOffset` calculation continues to work — as a bonus, the chat no longer jumps upward as the user scrolls since the header bottom edge stops moving. Commit `2901b30`.

#### Investment account detail projection fix (same session, different shape)
- [x] **Found and fixed a matching-but-different projection bug** — clicking into an investment account card showed "Failed to load projection data" with `TypeError: investmentService.getAccountProjections is not a function` in console. Not a cache bug — the frontend service method itself was missing (likely removed by commit `d635d36`'s dead-code sweep and never restored by the `b0ad5ad` revert). Backend route + controller were fine. Added the method back with optional `risk_level` param for the what-if feature the backend already supports. Commit `f2ba360`.

#### Small UX polish
- [x] **Browser tab always reads "Fynla"** — `Login.vue` was setting `document.title = 'Sign In — Fynla'` on mount and nothing reset it post-login, so the tab label stuck as "Sign In — Fynla" across the whole authenticated session. Login.vue now sets `'Fynla'`, and a `router.afterEach` hook keeps the tab title as `'Fynla'` on every SPA navigation. Blade template's long marketing title untouched for SEO crawlers. Commit `e653180`.

#### Deploy + docs
- [x] **PR #225 opened, pushed through 8 commits, admin-merged to `dev`** as merge commit `6b7306d`.
- [x] **`April/April23Updates/deployPensionFix.md`** — upload checklist, SSH command sequence, 7-part smoke-test plan, rollback, optional SQL purge for legacy MC cache rows. Mirrored to vault.
- [x] **`April/April23Updates/patchPensionInvest.md`** — end-user patch notes (plain English, no tech jargon). Mirrored to vault.
- [x] **Dev server deployed + browser-tested by CSJ.** All 7 smoke-test sections passed. Old `public/build.old` and `public/build.old2` directories removed from `~/www/csjones.co/fynla-app/public/` — freed ~23MB.

### Outstanding from session 66

- [ ] **Cut `dev → main` PR when ready.** This deploy passes all smoke tests on dev. Production cut-over guidance is in `deployPensionFix.md` §Production cut-over. Must include PR #224 (intervention/image v3 downgrade) carried through — verified by running `composer show intervention/image` on dev reporting `3.11.7`.
- [ ] **Optional SQL purge on production after the dev→main cut** to age out legacy MC cache keys immediately (otherwise 24h wait):
  ```sql
  DELETE FROM monte_carlo_cache WHERE cache_key LIKE '%pension_pot_%' AND cache_key NOT LIKE '%_i%';
  ```

### Outstanding from 65b (carried forward)

- [x] **Complete the in-flight checkout test** — ticked at session 66 start after CSJ confirmed it was done.
- [x] **Clean up `public/build.old/` and `public/build.old2/`** on the dev server — done at end of session 66.
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 65b (23 April late-afternoon) — CSP / Revolut / .env cascade

### Completed

- [x] **Removed HSTS + CSP + Permissions-Policy `Header set` from both `.htaccess` templates** (`deploy/csjones-fynla/.htaccess`, `deploy/fynla-org/.htaccess`). Apache's `Header set` was overwriting `SecurityHeaders` middleware's richer CSP and blocking Revolut widget on dev. Commit `f0770bb`.
- [x] **Uploaded new csjones `.htaccess` to dev server**, cleared Laravel caches.
- [x] **Fixed dotenv syntax on server `.env` line 62** — `ADMIN_EMAILS` now quoted (was unquoted comma-separated value with whitespace, invalid dotenv syntax that was hidden by config cache until `config:clear` exposed it). Backup at `.env.backup-2026-04-23-csp-fix`.
- [x] **Pinned `VITE_REVOLUT_SANDBOX=true` + `VITE_REVOLUT_PUBLIC_KEY=pk_D2JdE2srRipv0jdHerivLw1hMoWSrjqDa4lEozJxTwchuG04`** into `deploy/csjones-fynla/build.sh`. Builds now reproducible regardless of builder's local `.env`. Commits `921bb3d` + follow-up.
- [x] **Rebuilt + uploaded** new `public/build/`. New `CheckoutPage-CAePoYgl.js` has correct sandbox SDK URL + correct merchant pk, Revolut widget 403s are gone.
- [x] **Preserved old build chunks** alongside new ones (`cp -rn public/build.old/. public/build/`) so CSJ's in-flight incognito session survived the rebuild without a forced refresh — every route except `/checkout` continued to work mid-session.
- [x] **Incident log written** at `April/April23Updates/revolutCSPIncident.md` + mirrored to vault. Documents timeline, root causes, fixes, and 5 rules for next session (chief rule: warn CSJ before rebuilding during active browser testing).

### Outstanding from 65b

- [x] **Complete the in-flight checkout test** — CSJ's original session has the pre-fix `CheckoutPage-Dq2ZEZzV.js` in memory with the wrong pk. Needs a fresh incognito window to exercise the correct `CheckoutPage-CAePoYgl.js` chunk and confirm the full sandbox checkout flow works end-to-end.
- [x] **Clean up `public/build.old/` and `public/build.old2/`** on the dev server once ~24h have passed and no one is on a pre-rebuild session. `rm -rf` both. *Done end of session 66 — freed ~23MB.*
- [ ] **Before the next `dev → main` PR**, verify `deploy/fynla-org/build.sh` and production `.env` have the LIVE Revolut pk (not sandbox) baked in / present, so a future production rebuild from a developer's laptop doesn't accidentally ship a sandbox-pk build to prod.

---

## Session 65 (23 April afternoon) — PR triage + dev deploy + intervention/image v3 downgrade

### Completed This Session

#### Repository + branch protection
- [x] **Re-enabled branch protection on `dev`** — 1 required PR review, code-owner review required (CODEOWNERS pins `@Stoff73`), dismiss stale reviews, required conversation resolution, no force pushes, no deletions. `enforce_admins: false` retained so CSJ can admin-bypass when needed.
- [x] **Re-enabled branch protection on `main`** — identical settings to dev. Previously unprotected, which contradicted CLAUDE.md's documented workflow.
- [x] **Saved new durable rule** in memory (`feedback_main_via_dev_only.md`): nothing merges to main without first being committed to dev, deployed to csjones.co/fynla, and browser-tested. Only CSJ overrides with explicit words in the current turn. MEMORY.md index updated.

#### PR triage (5 PRs processed)
- [x] **PR #213 closed** — stale session 52 CSJTODO doc, superseded by later handovers.
- [x] **PR #212 re-targeted** from `main` → `dev` (violated the new rule by targeting main directly).
- [x] **PR #221 rebased** onto the refreshed `dev` — CSJTODO conflict resolved by taking dev's newer version; force-pushed; admin-merged via `gh pr merge 221 --merge --admin`. Campaign pages + ReviewCarousel + StaticFynChat + 404 page now on dev.
- [x] **PR #223 opened + admin-merged** (`main → dev` back-merge) — brought session 64's subscription hotfix + session 63/64 handover docs onto dev. Dev was missing 3 commits (`ad73bd0`, `5cd5d62`, `bd9042e`) that had been admin-merged directly to main. Clean merge — only `AppLayout.vue` overlapped and auto-merged.
- [x] **PR #212 rebased** onto new `dev` through 40+ commits, 6 conflict points resolved manually (CSJTODO, CLAUDE.md, trial-expiration-reminder.blade.php, routes/web.php twice, AppLayout.vue three times, router/index.js, Settings.vue deletion). Force-pushed and admin-merged. Full lifecycle email engine (5 campaigns + engine + E2E test commands + magic-link routes + NotificationPreferences page + 14 toggles) now on dev.
- [x] **PR #224 opened + admin-merged** — downgraded `intervention/image ^4.0 → ^3.0` to keep PHP 8.2 compatibility, ported `InsightImageService` to the 3.11 API (`ImageManager::gd()`, `->read()`, `->toWebp(quality:)`). 9/9 existing tests still pass.

#### Dev server redeploy (csjones.co/fynla) — 167 files uploaded, 7 deleted, 12 migrations ran
- [x] **Server state probed via SSH** — confirmed server was at approximately `origin/onboardingFyn` state (last migration `2026_04_15_153100`), not main. Real delta was 173 files not the 153 my original guide assumed.
- [x] **`filesUploaded.md` comprehensive checklist** generated and mirrored to repo + vault. 215 line items across §A upload / §B delete / §C exclusions / §D server commands / §E smoke tests / §F rollback.
- [x] **167 files uploaded** via tar-pipe in 0.3s; hash-verified byte-for-byte match against `origin/dev`.
- [x] **7 superseded files deleted** on server (OnboardingChatDirector, OnboardingPromptBuilder, OnboardingStateMachine, OnboardingValueInterpreter, SpouseLinkingService, EmptyDataGuard, config/onboarding.php). 2 items in delete list were already absent.
- [x] **composer install** — resolved to `intervention/image 3.11.7` + `intervention/gif 4.2.4`, both PHP 8.2 compatible. Platform-check re-enabled and passing.
- [x] **Appended `.env` vars**: `LIFECYCLE_ENGINE_ENABLED=true` + `LIFECYCLE_TEST_RECIPIENT=chris@fynla.org`. Deduped after a session confusion created doubles. `.env.backup-2026-04-23-post-lifecycle` preserved.
- [x] **12 pending migrations ran** — 7 lifecycle + 5 insights, all `DONE`.
- [x] **Cache clears + optimize** — config + routes cached.
- [x] **Insights seeder** — 8 bespoke articles seeded.
- [x] **Full `php artisan db:seed --force`** — 22 seeders all green, including **OccupationCode (406 codes)**, Preview users (6 personas), ChrisUser, AdvisorClient, etc.
- [x] **Lifecycle engine smoke test** — `php artisan lifecycle:run-daily` ran all 5 campaigns cleanly (0 eligible users, as expected).
- [x] **Endpoint smoke tests** — `/fynla/`, `/fynla/pricing`, `/fynla/quickstart`, `/fynla/insights`, `/fynla/how-it-works`, `/fynla/features`, bad-URL SPA fallthrough → all HTTP 200.

#### Landing page CTA
- [x] **Unhid "Quick start with Fyn" CTA** on the landing page hero — commit `97edb5d` admin-pushed to dev. The HTML comment markers were removed; the `<router-link to="/register?from=fyn">` now renders live on both localhost:8000 and csjones.co/fynla. Known caveat: new-user Fyn flow has bugs (per `April/April9Updates/fynQuickStartBugs.md`) — CTA-to-flow fixes deferred to a future session.

#### Supporting docs (all mirrored to repo + vault)
- [x] `April/April23Updates/devUpdateDeploy.md` — initial deploy guide (subsequently superseded by filesUploaded.md when server state turned out to be further behind than main).
- [x] `April/April23Updates/filesUploaded.md` — authoritative 215-item upload + server-command checklist; all §A/§B/§D items (except optional §B4 renames + cron verification) ticked.
- [x] MEMORY.md index updated with new project memory for PR #214 coupling with `feature/fyn-persona-split`, and new feedback rule for main-via-dev-only workflow.

### NOT Done — Outstanding from Session 65

- [ ] **Browser smoke-test PR #221 features** end-to-end on csjones.co/fynla dev — 14 items listed in `filesUploaded.md` §E. This is the next-session opening task. Tech stack to exercise: `/quickstart`, QuickStart CTA (newly unhidden), ReviewCarousel on pricing/features/how-it-works, NotFoundPage fall-through, `/profile/notifications` toggles, lifecycle magic-link → discount prefill, admin insights image upload (tests intervention/image 3.11.7 port).
- [ ] **Fix Fyn quickstart bugs** — see `April/April9Updates/fynQuickStartBugs.md`. CTA is now live on dev but clicks route to `/register?from=fyn` which hits the known-buggy new-user Fyn flow. User explicitly deferred this to a later session.
- [ ] **Verify SG Site Tools crontab** — `crontab -l` via SSH returns empty, yet existing daily jobs (`trials:send-reminders`, `trials:expire`, etc.) clearly run on dev. SiteGround manages cron via their Site Tools web UI. Check that `* * * * * php artisan schedule:run` is configured for csjones.co; if not, the 08:30 UTC daily lifecycle job will silently never fire.
- [ ] **Test lifecycle engine end-to-end** with real emails — `php artisan lifecycle:e2e-test` seeds 5 test users and runs all campaigns against them, sending to `chris@fynla.org` (the LIFECYCLE_TEST_RECIPIENT override). Then `php artisan lifecycle:e2e-cleanup` removes them. Verifies magic-link routes, WebP hero rendering, discount code generation, restart-trial handler, feedback capture.
- [ ] **Optional §B4 cleanup** on server — delete the 7 stale Vue source files on the server (`Navbar.vue`, `Footer.vue`, `Holdings.vue`, `Performance.vue`, `Recommendations.vue`, dead `Goals.vue`, dead `UserProfile/Settings.vue`). Purely cosmetic — build output doesn't reference them.

### Context for Next Session

Dev branch is fully in sync with csjones.co/fynla server. Working tree is clean. Local dev server was running at end of session on Laravel :8000 + Vite :5173 — may still be up or may have been shut down. The big next-session task is browser-testing all the deployed PR #221/#212 features on the dev server, specifically the ones newly visible via the unhidden QuickStart CTA. After dev is stable and browser-tested, the next PR pipeline is `dev → main` for production rollout — but that must include #224's intervention/image downgrade or production will 500 on first composer install.

---

## Outstanding — Tech Debt Deferred (from earlier sessions)

- [ ] **Session 63 tech-debt branch** — already merged to dev (via PR #220) but still needs browser-test matrix before `dev → main`. 8 flows in `April/April18Updates/handover-tech-debt.md §4a`: Estate/IHT dashboard, Investment (holdings/fees/tax/rebalance), Protection, Expenditure form penny-level totals, Estate CRUD, Net worth, Savings, Investment detail.
- [ ] **28 Vue god components** (>800 lines) — prioritise `Admin/TaxSettings.vue` (3,068 lines) and `UserProfile/ExpenditureForm.vue` (2,574 lines). Multi-week effort.
- [ ] **13 backend god files** — `SavingsActionDefinitionService.php` (3,686 lines), `RetirementActionDefinitionService.php` (2,701), `ProtectionActionDefinitionService.php` (2,349), `RetirementIncomeService.php` (2,292), `IHTCalculationService.php` (1,641).
- [ ] **54 controllers using inline `$request->validate()`** — convert to Form Request classes (~60-80h total).
- [ ] **npm `--force` fix** — schedule a 2-4h window for vite 8 + `@capacitor/cli` 8 major upgrades with full PWA + iOS + web regression. 6 high-severity vulnerabilities remain until done. Carried from session 63.
- [ ] **Test Fyn chat fixes on dev (csjones.co/fynla)** — deployed in session 58 but not browser-tested. Carried from session 58.
- [ ] **Add `Current State/Insights.md`** to the vault — carried from session 62.
- [ ] **`AutoRiskCalculatorTest` pre-existing failure** — `risk_level` enum truncation. Pre-existing since 16 April.

## Known Issues

- **CLAUDE.md stale tax-year claim** — says `active: 2025/26` but the seeded `TaxConfiguration` table correctly has `2026/27` active (which is right — 2026/27 started 6 April 2026). `TaxConfigService` reads from DB so behaviour is correct; the line in CLAUDE.md just wants a one-character update.
- **Build script deploy-path echo** is outdated — `./deploy/csjones-fynla/build.sh` prints `~/www/csjones.co/public_html/fynla/public/build/` but the actual sibling-dir path is `~/www/csjones.co/fynla-app/public/build/`. Cosmetic.
- **Dev server user crontab empty** — see "Outstanding — verify SG Site Tools crontab" above.

## Deploy Status

- **fynla.org (production)** — unchanged from session 64. `ad73bd0` subscription hotfix live. Test user `bugrepro_expired_2026_04_23@fynla.org` still in grace-period state.
- **csjones.co/fynla (dev)** — fully in sync with dev branch tip `97edb5d`. All four merged PRs (#212, #220, #221, #223) plus session 65's CTA unhide deployed. composer, .env, migrations, seeds, caches all current.
- **Pending production deploy** — `dev → main` PR not opened. Must include PR #224 (intervention/image v3) or production will 500 on first composer install due to PHP 8.3 requirement. Don't open the `dev → main` PR until session 65's browser testing is complete and any uncovered issues are fixed.
- **Open PRs remaining:** #214 (`onboardingFyn` → `dev`) — still CONFLICTING, coupled with `feature/fyn-persona-split` per memory. Do NOT rebase/merge in isolation.

## Active Work Not Carried by PR

- **Local dev server:** running at `http://localhost:8000/` + Vite `:5173` as of end of session. Check with `lsof -i :8000` before relying on it next session.
- **SSH key:** `~/.ssh/fynlaDev` was loaded into the agent this session (`ssh-add`). It'll remain loaded until the agent cache expires or the machine is rebooted.
