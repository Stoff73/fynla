# April 24 Updates — Index

**Date:** 24 April 2026

## Contents

### Afternoon audit (24 April PM) — correction artefacts from 5-reviewer audit

These three supersede the morning docs where they conflict. Read FIRST before trusting the morning docs — the afternoon audit overturns several load-bearing claims.

| File | Purpose |
|---|---|
| [`audit-evidence.md`](audit-evidence.md) | **Code-grounded ground truth** with file:line anchors, §1-17 + addenda 14-17. Separates claims the morning docs get RIGHT from what they get WRONG. Covers: branch state (178 commits behind, not 72), file existence on `main` vs `feature/fyn-persona-split`, multi-entity post-onboarding gap, verified 10-layer/29-tool/22-query-type claims, invalidated cache-metrics and admin-UI claims, Privacy Policy §5/§7 direct contradictions, five-not-three third-party processors, 10-item parity gap. |
| [`audit-synthesis.md`](audit-synthesis.md) | **Consolidated verdict across 5 reviewers + CSJ decisions.** 10 sections: Headline · Correctly Planned · Invalidated by Code · Assumptions Stated as Fact · Scope Creep · Real Gaps Missed · Sprint 0 Honest Re-estimate · Multi-Entity Deep Dive · CSJ Decisions (§8 now contains answers to all 7 decision questions) · Recommendations for Correction Pass. The document of record for Sprint 0 scoping. |
| [`fyn-rubrics.md`](fyn-rubrics.md) | **Two rubrics replacing the undisclosed D+(45/100) grade.** Rubric A: Enterprise Assessment, 10 dims × 5 levels = /40 score. Fyn currently **4/40 — 🔴 Pre-launch**. Projected Sprint 0+1: ~17/40 🟠 Limited beta. Rubric B: Eval Harness, 65 golden conversations across 8 categories, Mode 1 (mocked, CI-gated, 100% required) + Mode 2 (weekly real providers, ≥97% required). Per-tool scorecard with tunable per-focus thresholds in `config/fyn_eval.php`. Non-tunable 100% hard-fail floors on entity validity, monetary value accuracy, cross-entity consistency, and fabrication rate. |
| [`CSJTODO.md`](CSJTODO.md) | Updated with the full session 69 handover (morning + afternoon) + CSJ decisions + corrected Sprint 0 (with new 0.20-0.24 reliability tasks) + Sprint 1-4 roadmap. |

### Morning docs (24 April AM) — original planning set (require correction pass)

These are the input to the afternoon audit. Several load-bearing claims are overturned; read `audit-synthesis.md §2` before trusting specific findings.

| File | Purpose |
|---|---|
| [`fyn-system-map.md`](fyn-system-map.md) | Comprehensive map of the Fyn AI chat system — routes, controller, provider abstraction, 10-layer prompt (verbatim), 29-tool catalogue, data model, frontend (web + mobile), admin surfaces, token budget, security posture, tests, observability, history & lineage. **§22 added 24 April** — cross-doc enterprise addendum. **§23/§24/§25 added after Loop 3** — Document Extraction AI surface, Python Agent SDK Sidecar AI surface, all-touchpoints consolidated inventory. Map now covers all 3 AI systems. *Afternoon audit invalidates: §21 Q2 (admin UI exists), §21 Q3 (cache metrics persisted), §7 "29 tools" count (only true on xAI path, Anthropic has 23), §1.1 happy-path flow not updated for §26 architecture correction.* |
| [`verdictFyn.md`](verdictFyn.md) | **SUPERSEDED v1 verdict.** Rated against Anthropic's *Building Effective Agents* + xAI docs. 26 gaps, 4-sprint roadmap, B+ (72/100). Kept for accountability / comparison. Use `enterprise-verdict.md` instead. |
| [`enterprise-verdict.md`](enterprise-verdict.md) | **v3 verdict — 7 passes.** Parts C/D (framework) + E (adversarial) + J (cross-doc reloop) + K (exhaustive Loop 3) + L (CSJ resolutions) + M (scope correction) + N (architecture correction). Headline **D+ (45/100)**. *Afternoon audit: grade rubric is not published / not reproducible; Critical count drifts 9/10/14/16/13 across passes; Part M scope corrections did not propagate uniformly into Parts F/K; FCA PS25/22 targeted-support regime (live 6 April 2026) not referenced. Replace headline grade with Rubric-A 4/40 🔴 Pre-launch.* |
| [`fyn-integrated-plan.md`](fyn-integrated-plan.md) | **Integrated plan** — reconciles current Fyn + verdict recommendations + in-flight `feature/fyn-persona-split` work. Three-lens delta analysis. 25-touch-point dependency index. Unified 6-sprint roadmap. *Afternoon audit: §5.1 "multi-entity solved" is false for post-onboarding path (extractor wired to onboarding director only, not to orchestrator); §0 "72 commits behind" is actually 178; Sprint 0 "1-2 days" honest estimate is 3-4 weeks; Sprint 0.19 "collapse" is NEW code not a refactor (`handleInlineCaptureTurn` doesn't exist); T18/T24/T25 scope creep not purged after Part M.* |

## Cross-references

- Vault copy: `/Users/CSJ/Desktop/fynlaBrain/April/April24Updates/fyn-system-map.md`
- Upstream report (14 April): `fynlaBrain/April/April14Updates/fynAiSystemReport.md`
- Upstream tool catalogue (14 April): `fynlaBrain/April/April14Updates/fynAiToolCatalogue.md`
- Prompt refactor origin (1 April): `fynlaBrain/April/April1Updates/fynPromptRefactor.md`
- Recent reliability fixes (16 April): `fynlaBrain/April/April16Updates/deployFynFix.md`
- Onboarding PRD (20 April): `fynlaBrain/April/April20Updates/PRD-fyn-driven-onboarding.md`

## Open questions parked for CSJ

See §21 of `fyn-system-map.md` for 8 flagged points that need a product/engineering call before planning next changes:
1. Unused `users.ai_chat_enabled` column
2. Missing admin UI for `AiAuditController`
3. Anthropic cache metrics not persisted
4. `get_module_analysis(holistic)` — no matching module
5. `CrossModuleInsights` dashboard component — decommission or repurpose
6. Mobile `FynInsightCard` / `MobileFynCard` — LLM-free but Fyn-branded
7. SSE concurrency provisioning
8. Title generation is raw user text
