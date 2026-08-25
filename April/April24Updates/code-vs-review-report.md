# Report — `feature/fyn-persona-split` vs `April/April24Updates/`

**Date:** 24 April 2026
**Method:** Read `feature/fyn-persona-split` code first, then the review folder, then compared. This document is the comparison only — not a fresh audit of the code.

---

## 1. What is actually on `feature/fyn-persona-split`

Two initiatives bundled into one branch (68 ahead of `main`, **179 behind**):

**A. Persona split** — feature-flagged (`FYN_PERSONA_SPLIT`, default off). Classic supervisor/worker pattern:
- `FynPersonaRegistry` (config-driven, `config/fyn_personas.php`)
- `FynPersonaInvoker` (per-turn prompt + tool build, strips handoff SSE, buffers data-capture content, runs entity gap-fill)
- `FynPersonaOrchestrator` (advice/capturing state, classifier fast-path, cancel, timeout, preview short-circuit)
- `HandoffContract` (`delegate_to_capture`, `capture_complete` constants)
- `DataCapturePromptBuilder` + `CaptureContext` VO
- Migrations: `ai_messages.persona`, `ai_conversations.persona_state`

**B. Fyn-driven onboarding** — always-on (`onboarding.fyn_flow_enabled=true`). 1,985-line `OnboardingChatDirector` + 713-line `OnboardingStateMachine` + extractors (`AssetCaptureEntityExtractor` 665 LOC, `OnboardingFactExtractor` 286 LOC, `OnboardingValueInterpreter` 324 LOC) + `HouseholdProvisioner`, `SpouseLinkingService`. Four new `users.onboarding_fyn_*` columns, `ai_conversations.onboarding_parked_facts`, three new wills columns.

**C. Large deletions** the review docs barely mention: Insights admin (articles, templates, images, SEO), full Lifecycle engine (5 campaign classes, email templates, discount code generator, snapshot service), `CampaignPage.vue`, `InvoiceView.vue`, `QuickStartPage.vue`, `NotFoundPage.vue`, `NotificationPreferences.vue` → `Settings.vue`.

`AiChatController::sendMessage` 3-way dispatch: onboarding director → orchestrator (flag on) → legacy `CoordinatingAgent::chat`. A gap-fill wrapper wraps all three paths.

---

## 2. What the review folder says

`INDEX.md` splits the folder into **morning docs** (`fyn-system-map.md` 2,038 lines, `verdictFyn.md` superseded, `enterprise-verdict.md` 2,021 lines, `fyn-integrated-plan.md` 1,678 lines) and **afternoon correction artefacts** (`audit-evidence.md`, `audit-synthesis.md`, `fyn-rubrics.md`, updated `CSJTODO.md`). The afternoon docs are explicit that the morning docs contain load-bearing errors and should not seed a spec before a correction pass.

Headline of the afternoon synthesis: **4/40 on a new 10-dim rubric — 🔴 Pre-launch**. Projected after Sprint 0+1: 17/40. Replaces the morning's undisclosed "D+ (45/100)".

CSJ decisions the afternoon docs lock in:
1. FCA posture = guidance only, no targeted support.
2. Two Fyns — **delete** `FynPersonaOrchestrator` / `Invoker` / `Registry` / `DataCapturePromptBuilder`, keep `HandoffContract` + `CaptureContext`, promote `OnboardingChatDirector` to Onboarding Fyn and add `handleInlineCapture`.
3. 95% recall/precision baselines, 100% hard-fail floors on validity / value accuracy / consistency / fabrication.
4. Python sidecar delete.
5. Local-first deploy gate.
7. Build both rubrics.

---

## 3. Review-vs-code comparison

### Claims the code confirms

| Review claim | Code reality |
|---|---|
| 179-commit rebase drift, not 72 | `git rev-list` = 179 behind, 68 ahead ✅ |
| Orchestrator / registry / invoker / handoff / capture context all exist on the branch, none on main | ✅ — verified via `git show`; none exist on `main` |
| New migrations: `ai_messages.persona`, `ai_conversations.persona_state`, `onboarding_parked_facts`, will columns, `onboarding_fyn_*` | ✅ — all four migrations present |
| `handleInlineCaptureTurn` / `handleInlineCapture` is NEW code, not a refactor target | ✅ — `git grep handleInline` returns empty on the branch |
| All 13 `create_*` tools are form pre-fillers, not DB writes | ✅ — 17 `'action' => 'fill_form'` returns in `CoordinatingAgent.php` |
| Tool catalogue divergence between xAI and Anthropic | 🟡 (v2 self-correction, post three-pass review) — actual counts via Explore-agent re-verification: **Anthropic 37 tools / xAI 33 tools**. The audit's "29 xAI / 23 Anthropic" numbers are wrong in magnitude AND direction. Anthropic has the richer catalogue, not xAI. `create_holding` + three onboarding `capture_*` tools are Anthropic-only; `list_records` and `set_expenditure` are on both. A runtime provider flip to xAI silently loses 4 tools, including the onboarding capture tools — onboarding Fyn depends on Anthropic being active. |
| `AssetCaptureEntityExtractor` coverage is only 4 focuses (protection / savings / retirement / investment) | ✅ — confirmed by reading the extractor file |
| Cache metrics already persisted at `HasAiChat.php:467-469`; `AiAudit.vue` already exists | ✅ — `AiAudit.vue` exists on both `main` and `feature/fyn-persona-split`; morning-doc "missing" claim is wrong |

### Where the review folder is itself wrong

**The synthesis says `FynPersonaOrchestrator::runCaptureTurn` "invokes `FynPersonaInvoker::invoke` which runs the standard LLM loop with no extractor call, no gap-fill, no post-stream regex fallback"** (`audit-evidence.md` §3.2, repeated in `audit-synthesis.md` §2 #4 and §7.1 #4).

That is **incorrect**. `FynPersonaInvoker` (lines 48, 175, 200, 251, 264, 270-271) injects `AssetCaptureEntityExtractor` and calls `emitGapFillFromCaptureContext()` on every data-capture turn — it flushes the gap-fill from the `done` branch and has a safety-net flush for dropped SSE. The B-1 work (commit `37b6a4b`) explicitly wired multi-entity gap-fill into the post-onboarding capture path; that's in the commit message and in the code.

So the headline claim "multi-entity still broken on persona-split post-onboarding" is too strong. The correct statement is narrower:

- The post-onboarding gap-fill runs **only for the 4 extractor focuses** (protection / savings / retirement / investment).
- The other ~12 entity types (goals, family, life events, property+mortgage, trusts, wills, POAs, business interests, chattels, liabilities, estate gifts, holdings) still rely on raw LLM tool emission with no safety net.
- There is no gap-fill for within-tool multi-entity that is entity-specific inside the 4 focuses either, when the provider string isn't in `KNOWN_PROVIDERS`.

This matters because CSJ's Sprint-0.19 plan ("delete orchestrator/invoker, route capture into the director") is premised on the extractor **only** being wired inside the director. That premise is wrong on the code. The real decision is: the two-Fyn collapse is an architectural simplification, not a multi-entity fix. Multi-entity coverage expansion is its own body of work (batch-shaped tools, extended extractor coverage), and it needs to happen regardless of whether the orchestrator stays or goes.

### Other inherited errors in the morning docs the afternoon docs correctly flag

The audit is right about these, and they're worth trusting:

- "72 commits behind" → actually 179 ✅
- "Admin UI missing" / "cache metrics not persisted" → both already exist ✅
- "Sprint 0 is 1-2 days" → afternoon honest estimate 3-4 weeks; realistic given 0.5 (per-entity allowlist for `update_record`), 0.18 (hash-chain audit), 0.19 (two-Fyn collapse), 0.20-0.24 (SSE abort, token budget race, provider swap lock, gap-fill dedup, `generateTitle` sanitise) ✅
- Privacy Policy §5/§7 direct-quote contradictions against Meta Pixel + AWIN + Plausible + LLM health-data flow ✅
- Five third-party processors, not three (Anthropic + xAI + Meta + AWIN + Plausible, GetAddress.io the only disclosed one) ✅
- FCA PS25/22 "targeted support" went live 6 April 2026 — not mentioned in morning docs ✅
- No SSE abort detection anywhere — not flagged in morning docs ✅
- Token-budget race through `Cache::remember($key, 300, …)` ✅

### Risks the afternoon docs don't name

- **The branch is a bundle.** Persona split + onboarding rewrite + insights/lifecycle deletions are three separate initiatives on one branch. Rebasing 179 commits across `AppLayout.vue`, `CoordinatingAgent.php`, `routes/api.php`, `HasAiChat.php`, `Prompts/*`, `AiToolDefinitions.php` is painful. Deleting whole Insights + Lifecycle subsystems adds hidden blast radius at merge time — `main` could have added new code that references the deleted classes. Cherry-picking the persona split out of the onboarding rewrite isn't a supported operation here; they share the director, the extractor, the migrations, the `AiChatController` wiring.
- **`fyn_flow_enabled` defaults to `true`.** Persona split is flagged off; the onboarding rewrite is not. That means every user whose `onboarding_completed = false` immediately lands on the 1,985-line director on first sign-in after merge. The afternoon docs treat Sprint 0.19 as the risky path, but the onboarding rollout is the larger behavioural swing — and it has no kill switch wrapped around it the way persona split does.
- **If CSJ goes ahead with the Sprint 0.19 two-Fyn collapse, `FynPersonaInvoker`'s gap-fill wiring has to move into the new `handleInlineCapture` path**, not just be deleted. The afternoon docs imply this in `audit-synthesis.md` §5.6 ("`AssetCaptureEntityExtractor` rewiring — not named anywhere in the current Sprint 0.19 description") but don't mark it as a hard requirement in the TODO. It is a hard requirement — drop that wire and you silently regress the B-1 fix.

---

## 4. Recommendation

- **Trust the afternoon docs** (audit-evidence, audit-synthesis, fyn-rubrics, updated CSJTODO) as the base of record. They correct real load-bearing errors in the morning set.
- **Fix one thing in those afternoon docs before they seed a spec**: the "orchestrator has no gap-fill" claim is wrong. `FynPersonaInvoker::emitGapFillFromCaptureContext` does run the entity extractor on data-capture turns. Rewrite §3.2 of `audit-evidence.md` and §2 #4 + §7.1 #4 of `audit-synthesis.md` to narrow the multi-entity gap to "4 of 18 entity types are covered; known-provider regex only; no batch tool shape — on both onboarding and post-onboarding paths".
- **Don't treat the morning docs as safe input.** INDEX already says this, but worth restating: `fyn-system-map.md`, `verdictFyn.md`, `enterprise-verdict.md`, `fyn-integrated-plan.md` all need the canonical-facts + scope + effort-honesty passes listed in `audit-synthesis.md` §9 before a spec is drafted.
- **Before Sprint 0.1 (rebase)**: decide whether the onboarding rewrite + insights/lifecycle deletions split out as separate PRs or stay bundled. Bundled is what's on the branch; the afternoon docs assume bundled but don't argue for it. Splitting lowers merge risk and lets the onboarding rewrite deploy to `csjones.co/fynla` behind `onboarding.fyn_flow_enabled` before the persona split joins it.
- **Before Sprint 0.19 (two-Fyn collapse)**: explicitly include "move `AssetCaptureEntityExtractor` wiring from `FynPersonaInvoker` into `OnboardingChatDirector::handleInlineCapture`" as a named subtask and a test requirement. Otherwise B-1 silently regresses.
- **The 4/40 rubric score is a good forcing function.** Publishing it alongside Sprint results is a better signal than a single opaque grade, and the 100% non-tunable floors (validity / value / consistency / fabrication) are the right bar for a regulated financial app.

---

*Prepared 24 April 2026. Comparison of `feature/fyn-persona-split` code against the review set in `April/April24Updates/`. Every claim anchored in file reads or git output during the comparison session.*
