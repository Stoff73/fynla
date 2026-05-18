---
type: handover
mode: context-clear
date: 2026-05-14
session: 5
branch: claude/cranky-lewin-6bc99c
trigger: context-handover skill (tripwire — ~200k tokens)
---

# Context Clear Handover — 2026-05-14, Session 5

## Immediate state

Just finished a full brainstorming session for the **Fynla major system overhaul** and wrote the design doc for **sub-project 1 of 6** (Module Canonical Store-and-Retrieve Contract). CSJ confirmed the last two open questions (Q-A: Tier 2 = 5yr / Tier 3 = 7yr surfacing windows; Q-B: pull reference data forward to pass 2) and told me to write the implementation plan. Tripwire fired before I could update the spec with those two answers or invoke `superpowers:writing-plans`.

## The thread

This was a **brainstorming session for a major system overhaul**, not implementation. Worktree is on a fresh branch (`claude/cranky-lewin-6bc99c`) off main. The thread:

1. **CSJ's ask** — major overhaul covering 7 themes: mobile-first redesign, save-tax campaign with 3 tracks (matched pension / spouse transfer / 60% trap), freemium with 3 paid tiers, gamification, tier-gated functionality, and module data integrity.
2. **Decomposition agreed** — 6 sub-projects: (1) Module canonical store *(in flight)*, (2) Freemium tier model, (3) Mobile-first via iframe-framed `/m/*`, (4) Campaign engine, (5) Track-lightweight onboarding, (6) Gamification.
3. **CSJ provided tier matrix as hand-drawn image** — captured in spec: free + 3 tiers, count caps (3 bank / 2 investments / 5 pensions on free), Fyn agent weekly token allowances (100k/250k/500k/1M with soft degrade), document storage GB-capped at tier 2+3 (free + tier 1 = extract data + discard doc).
4. **Sub-project 1 = chosen first**. Scope: 13 user-data entities + Wills/LPAs (repurposed from "builders" to store+view only) + 4 reference-data entities. Letter to Spouse OUT (derived surface).
5. **Boundary decisions** — calcs INSIDE the store (CSJ pushed back on my initial "consumer-side"); materialise as canonical columns with `*_calculated_at` timestamps; CSJ's better recalc/pruning idea adopted (retention != surfacing). Approach A picked (service facade over Eloquent, Pest architecture tests for boundary).
6. **Migration strategy** — option (b) entity-by-entity, complete each before next. Cash first → pensions second. PR-5 auto-split at 500 lines.
7. **CSJ's 5 confirmed answers** — all folded into the spec already.
8. **CSJ's last 2 answers (NOT YET in spec, captured here)** — Q-A: Tier 2 = 1825d (5yr), Tier 3 = full 2555d (7yr); Q-B: agreed, move reference data to migration pass 2.

**Rejected approaches (don't re-litigate):**
- Approach B (DTO+Repository) — too much boilerplate for brownfield. Approach A picked.
- Approach C (full hexagonal/DDD) — overkill for Fynla's scale.
- Option (a) big-bang migration — too risky.
- Option (c) strangler with both paths coexisting — boundary leakage during transition.
- "Freeze the column count" discipline for derived values — CSJ's recalc/pruning gate is better.

## Files touched this session

```
docs/superpowers/specs/2026-05-14-module-canonical-store-design.md  (new, 774 lines, committed)
```

Only one file. This was a pure brainstorming/design session — zero implementation.

## WIP commit

- SHA: `3f67f82` (`wip: context-handover snapshot`)
- Pushed: **yes** to `origin/claude/cranky-lewin-6bc99c`
- Branch is new — no PR opened.

## Open decisions

**None outstanding from CSJ.** The two final questions were answered in his last message:
- **Q-A (Tier 2 / Tier 3 snapshot surfacing windows):** Confirmed — Tier 2 = 5 years (1825d), Tier 3 = full 7-year retained history (2555d). These were the defaults I proposed.
- **Q-B (move reference data to pass 2):** Agreed. New migration order: (1) Savings → (2) Reference data (R1-R4) → (3) Pensions → (4) Properties → ... rest as in §15.3.

## Pick up from here (auto-continue contract)

1. **Read [docs/superpowers/specs/2026-05-14-module-canonical-store-design.md](docs/superpowers/specs/2026-05-14-module-canonical-store-design.md)** — full design.

2. **Update the spec with CSJ's final two answers** (small edit, two locations):
   - **§10.3** — fill in placeholders `tier2 => /* TBD */` and `tier3 => /* TBD */` with `1825` (5yr) and `2555` (7yr) respectively. Note in surrounding prose that Tier 3 surfacing equals retention (no gating at top tier).
   - **§15.3** — reorder the migration passes table to move **Reference data (R1–R4)** from pass 14 to pass 2 (between Savings and Pensions). Bump every subsequent pass by one. Add a one-line note that this was changed to close B2 (tax-config bug) early.
   - **§20** — collapse the "still open" section. Both questions are resolved; mark the entire §20 as "All questions resolved 2026-05-14" with a tiny resolutions table.

3. **Commit the spec amendment** with message `docs(spec): finalise sub-project 1 design with CSJ's final answers`.

4. **Invoke `superpowers:writing-plans` skill** to produce the step-by-step implementation plan for **pass 1: Savings**. The plan should list the 6–8 PRs (see §15.1 of the spec), with tests, verification gates, and Playwright browser-test steps for each PR. The plan goes to `docs/superpowers/plans/2026-05-14-sub-project-1-pass-1-savings-plan.md` (or whatever path writing-plans defaults to).

5. **After plan is written and CSJ approves**, the next action is to invoke `superpowers:executing-plans` or `superpowers:subagent-driven-development` to start PR 1 of pass 1. Do NOT start implementation in the same session that wrote the plan — split that across sessions for cache hygiene and to let CSJ review.

## What the next Claude needs to know

- **This is sub-project 1 of 6.** The other 5 (freemium tiers, mobile-first iframe, campaign engine, track onboarding, gamification) each need their own brainstorming → spec → plan cycle. Don't conflate sub-projects. Don't start sub-project 2 until sub-project 1's implementation plan is in CSJ's hands.

- **Sub-project 1 = foundation.** Everything else depends on it. Do not let scope creep from gamification / tier numbers / campaign UX leak into the Savings store work.

- **Approach A picked deliberately.** Service facade over Eloquent. Pest architecture tests enforce the boundary in CI (hard fail from PR 1 of pass 1, no soft-warn ramp-up). Do not propose DTOs or hexagonal architecture as a "simplification" — those were rejected.

- **Calcs INSIDE the store.** CSJ corrected my initial "consumer-side derive" recommendation. Derived values are materialised in canonical columns with `*_calculated_at` timestamps. Three-ingest paths (form, Fyn AI, upload) all converge at the store. This is non-negotiable.

- **Snapshot retention != surfacing.** All snapshots retained for 7 years for every user (regulatory floor). API surfaces tier-gated window: 90d / 365d / 1825d / 2555d. Upgrade widens window instantly with no recompute.

- **Existing TaxSettings admin UI is broken (B2).** [TaxSettings.vue](resources/js/components/Admin/TaxSettings.vue) + [TaxSettingsController.php](app/Http/Controllers/Api/TaxSettingsController.php) exist but "not wired correctly" per CSJ. The implementation plan for pass 2 (reference data) must audit and FIX the existing wiring, not build new admin views.

- **Don't `migrate:fresh` / `migrate:refresh`.** Standard Fynla rule.

- **Browser testing law applies** — every PR ships with Playwright verification: click + fill + submit + observe DB + UI. See `critical_browser_testing_law.md`.

- **Worktree context** — branch `claude/cranky-lewin-6bc99c` is on a new branch off main (commits already in main: a7f137a, fb315af, etc.). The branch name suggests this was spawned as a fresh worktree for the overhaul work. No PR open yet.

- **Tripwire fired at ~200k tokens.** That's CSJ's hard budget. The session was conversation-dense (brainstorming) not tool-call-dense — bear that in mind for the next session, the writing-plans output may be similarly conversation-heavy.

## Branch / deploy state

- Branch: `claude/cranky-lewin-6bc99c`
- Behind origin: 0
- Ahead of origin: 0 (just pushed)
- Deploy status: Not deployed (design doc only, no code changes)
- PR open: No
