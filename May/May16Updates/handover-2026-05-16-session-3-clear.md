---
type: handover
mode: context-clear
date: 2026-05-16
session: 3
branch: freemium
trigger: context-handover skill (context tripwire ~255k tokens)
---

# Context Clear Handover — 2026-05-16, Session 3

## Immediate state

SP2 (freemium tier model) spec + 9-PR implementation plan are **COMPLETE,
committed, and pushed to `origin/freemium`**. SP3 (mobile iframe shell)
brainstorming was started but **paused at the very first clarifying
question** — CSJ dismissed the intent question with "do not proceed, wait
for next instruction", then asked to commit/push SP2 to remote (done), then
the context tripwire fired. No SP3 artefacts written. Working tree clean.

## The thread

- Resumed from session-1 handover (freemium branch): SP2 design was
  CSJ-approved, needed transcribing into the canonical spec format.
- Wrote SP2 spec (`cd54dde`) mirroring the SP1 (2026-05-14) 23-section
  structure; grounded every code reference against live code (TierGate /
  PermissiveTierGate / StaticTierGate / TierLimitExceededException /
  HasAiGuardrails / users.plan enum / PricingPage.vue all verified).
- CSJ review-gate correction (`6da687f`): the four freemium tiers are a
  **NEW product model — NOT a relabel of legacy sub-plans, NO mechanical
  plan→tier map**. Existing paid subs grandfathered (access+price) until
  renewal; conversion tier = per-cohort CSJ decision before PR5. Prices are
  new/CSJ-set, no legacy seed. Household/spouse linking never tier-gated;
  Family module = full at ALL tiers incl. Free (closed assumption A5).
  Corrected spec §5.1/§5.2/§7/§16.2/§20/§22(A5,A8,A9,A10)/§23.
- CSJ then said "proceed on defaults" → remaining §22 assumptions
  (A1–A4,A6,A7,A10) accepted; spec status → APPROVED (`4a9d286`).
- Wrote the SP2 9-PR TDD implementation plan (`43577e9`, 1998 lines)
  mirroring SP1 pass-1 plan style; self-review embedded (full spec
  coverage, type consistency, one deliberate flagged `->todo()` in PR5
  for the real billing/price-lock path — explicitly NOT a silent gap).
- Started SP3 brainstorming: explored context — a full `/m/*` mobile
  surface ALREADY exists (`MobileLayout`, ~20 mobile views, Capacitor iOS
  app redirecting native users to `/m/*`). Asked CSJ the core intent
  question (what is the "iframe-framed /m/* shell" FOR given mobile
  already exists) — **CSJ dismissed it and said wait for instruction**.
- CSJ instruction: commit+push SP2 spec+plan to remote before SP3 →
  done (`git push origin freemium`, `a89eddf..43577e9`).

## Files touched this session

All committed + pushed to `origin/freemium`:
- `docs/superpowers/specs/2026-05-16-sub-project-2-freemium-tier-model-design.md` (new, ~560 lines)
- `docs/superpowers/plans/2026-05-16-sub-project-2-freemium-tier-model-plan.md` (new, ~1998 lines)
- `CSJTODO-freemium-series.md` (updated: SP2 spec+plan COMPLETE; decisions log appended with the new-tiers/no-map correction; SP3 row → brainstorming)

## WIP commit

- None this session — every change was a proper `docs(sp2):` commit.
- Latest commits on `freemium`: `43577e9` (plan), `4a9d286` (spec
  approved), `6da687f` (review correction), `cd54dde` (spec written).
- Pushed: **yes** — `origin/freemium` is at `43577e9` (0/0, clean).

## Open decisions

1. **SP3 intent (BLOCKING SP3 — CSJ must answer).** CSJ dismissed the
   multiple-choice intent question. The options offered were:
   (a) web phone-frame wrapper — desktop renders existing `/m/*` inside a
   phone device-frame as the primary web product;
   (b) marketing/onboarding demo — phone-framed iframe on public pages to
   preview the mobile app;
   (c) mobile-first redesign — make `/m/*` the canonical surface,
   "iframe-framed" is just the desktop presentation;
   (d) dev/preview harness — internal device-frame tooling.
   **No default chosen — CSJ explicitly said wait. Do NOT auto-pick.**
2. **SP2 §22 A9 (deferred, not blocking SP2 spec/plan):** which new tier
   each legacy paid cohort converts to at renewal — per-cohort CSJ
   decision, must be settled before SP2 PR5 (Revolut sync) is executed.
   Not needed for SP3 planning.

## Pick up from here (auto-continue contract)

**SP3 brainstorming is BLOCKED on CSJ's intent answer (Open decision #1).
This is a genuine wait-for-CSJ exit, not a hand-back — CSJ explicitly said
"do not proceed, wait for next instruction".**

1. On resume, do NOT auto-continue SP3 by guessing the intent. Re-surface
   Open decision #1 (the 4 options a/b/c/d above) and wait for CSJ to pick.
2. Once CSJ answers: continue the `superpowers:brainstorming` loop for SP3
   from the clarifying-questions step (one question at a time) → propose
   2-3 approaches → present design → CSJ review gate → write spec to
   `docs/superpowers/specs/2026-05-16-sub-project-3-mobile-iframe-shell-design.md`
   → self-review → CSJ spec review gate → `superpowers:writing-plans` →
   `docs/superpowers/plans/2026-05-16-sub-project-3-mobile-iframe-shell-plan.md`.
3. Then repeat brainstorm→spec→plan for SP4 (campaign engine), SP5
   (track-lightweight onboarding), SP6 (gamification). Update
   `CSJTODO-freemium-series.md` series table + decisions log after each.
   Campaign goal (CSJTODO line 6-7): produce all SP2–SP6 spec+plan pairs,
   sequentially, before any execution. SP2 done; SP3 next.
4. Do NOT offer SP2 *execution* — the campaign is spec+plan for all five
   first. SP2 execution is deferred by design.

## What the next Claude needs to know

- **Worktree:** working dir is the `freemium` worktree at
  `.claude/worktrees/tender-bassi-375ee8`. Main repo
  `/Users/CSJ/Desktop/fynla` is on a different branch
  (`fix/advice-prompt-jointowner-lazyload`). ALL freemium-series files
  (CSJTODO, specs, plans, handovers) live on `freemium` in the worktree.
  The worktree has **no `vendor/`** — artisan/pest can't run here; this is
  fine, the series is planning-only (no code).
- `CSJTODO-freemium-series.md` is the resume anchor — read it first; it
  has the full decisions log incl. the new-tiers/no-legacy-map correction
  and the transcribed whiteboard matrix.
- SP1 already shipped the seam SP2's plan builds on: `TierGate` iface,
  `PermissiveTierGate` (bound), `StaticTierGate` (unbound, SP2 PR3
  deletes it), `TierLimitExceededException`, `SavingsStore` calls the
  gate. SP2 plan PR-by-PR is grounded in this verified code.
- SP3 context already explored (don't re-explore from scratch): full
  `/m/*` surface exists — `resources/js/mobile/` (MobileLayout, ~20
  views: MobileDashboard, MobileFynChat, module detail screens),
  router `/m` group at `resources/js/router/index.js:1421+`, native
  users redirected to `/m/*`. No existing iframe usage in Vue. SP1 spec
  calls SP3 "iframe-framed `/m/*` route" / "phone-frame iframe".
- PR #317 (dev→main release) stays parked until SP2 lands on dev — memory
  `project_pr317_gated_on_freemium_refactor`. Don't propose shipping it.
- This is a long multi-window campaign — keep CSJTODO updated every
  window so the next handover stays cheap.

## Branch / deploy state

- Branch: `freemium` (worktree `tender-bassi-375ee8`)
- Behind origin/freemium: 0
- Ahead of origin/freemium: 0 (all SP2 commits pushed)
- Deploy status: Not deployed (planning only; zero code changes this
  whole sub-project series)
