---
type: handover
mode: context-clear
date: 2026-07-03
session: 2
branch: main (docs) / dev @ c836fb9 (code, via fynla-fixes worktree)
---

# Context Clear Handover — 2026-07-03, Session 2

## Immediate state

WP-5c is COMPLETE and merged to dev (`c836fb9`), deployed to csjones, live-verified; the campaign playbook is written; session closed cleanly with nothing in flight.

## The thread

- Session started from the session-1 precompact handover; confirmed the morning's work (#594–#602) had actually finished post-compaction.
- CSJ asked why only 4 milestones exist → investigation → **WP-5c specced and approved** (yearly repeats YES, push flag-gated YES) → built as three slices, each full-suite green + live-verified on csjones BEFORE stacking the next (deploy-gate pattern):
  - **#603 WP-5c-i** — catalogue 5 → 18 families (+ the Fyn prompt snapshot regen #585 missed — dev's `FynSystemPromptTest` had been red since `fa068a7`).
  - **#606 WP-5c-ii** (recreation of #604, which GitHub auto-closed when #603's head branch — its base — was deleted on merge) — uncapped pages, grouped next-per-family upcoming with £ distances, Done pagination, feed cursor + infinite scroll.
  - **#605 WP-5c-iii** — nudge layer: `MilestoneCollector` (scoped), Fyn speaks mints in capture turns, flag-gated push (`GAMIFICATION_PUSH_ENABLED` default OFF), deep-link routes, dashboard hero nudge, tax-savings detection on the dashboard read via scoped `ComposedTaxPlanService` memo.
- CSJ asked for the full campaign template → **`campaign-playbook.md`** written (consolidates blueprint + gamification map + new Fyn formatting standard F1–F15 + screens inventory + per-campaign checklist + fit notes for retirement/investment/savings/property/IHT).
- CSJ said "merge 603, 4 and 5, deploy" → merged (with the #604→#606 recovery), csjones switched back to `dev` + pulled + caches cleared, dashboard live-checked on the merged tip.

## Files touched (all committed)

- Code: merged via PRs #603/#606/#605 into dev (worktree `/Users/CSJ/Desktop/fynla-fixes`, now clean on dev tip).
- Docs (main repo, committed with this handover): `July/July3Updates/` — `campaign-playbook.md`, `wp5c-milestones-spec.md`, `savetax-recs-gamification-map.md`, plus the morning's `campaign-blueprint.md`, `gamification-recs-tasks-map.md`, `issues.md`, screenshots, and the session-1 precompact handover. `CSJTODO.md` updated. All mirrored to the vault.

## What the next Claude needs to know

- **Permission gate on self-merges is real**: `gh pr merge --admin` on a self-authored PR is blocked in auto mode until CSJ explicitly says merge. The documented order is deploy-feature-branch-to-csjones → verify → merge (memory `feedback_deploy_gate_csjones_before_admin_merge`).
- **GitHub stacked-PR gotcha**: merging a stacked PR with `--delete-branch` CLOSES (not retargets) the dependent PR. Merge bottom-up retargeting each PR to dev BEFORE deleting its old base, or keep branches until the whole stack lands.
- **Pint PostToolUse hook strips just-added imports** if the code using them doesn't exist yet — add imports after (or with) the code that uses them.
- The `/m` bundle on csjones is current (built from the merged content). csjones = dev tip `c836fb9`, back on the `dev` branch.
- Milestone push notifications are wired but OFF (`GAMIFICATION_PUSH_ENABLED`); turning them on is a config/env decision, not code.
- WP-5c deferred (spec §7/§6): `pa_restored` (needs adjusted net income exposed from the tax engine), desktop achievements/milestones/history parity, email loop.

## Pick up from here

1. Nothing is in flight. The two open CSJ decisions: **dev→prod release** (now #581–#606; no new migrations in WP-5c; corpus + build + m-build + changed PHP per prod runbook) and **campaign #2 green-light** (pre-work: existing-user re-entry decision + the 6 generalisation points in `campaign-playbook.md` §6).
2. If starting new work: read `July/July3Updates/campaign-playbook.md` before anything campaign-shaped, and `wp5c-milestones-spec.md` before anything gamification-shaped.
3. Test user for /m verification: julycsj3@example.com (id 168) on csjones, password reset to <redacted-password> this session; has milestones/actions/feed data from the walks.
