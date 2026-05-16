---
type: handover
mode: context-clear
date: 2026-05-16
session: 1
branch: freemium
trigger: context-handover skill (tripwire ~186k tokens)
---

# Context Clear Handover — 2026-05-16, Session 1

## Immediate state

SP2 (freemium) design just APPROVED by CSJ ("looks good"). About to write the
SP2 spec doc — paused here for the handover. No spec/plan files written yet;
all design decisions are captured in `CSJTODO-freemium-series.md` (on the
`freemium` branch, committed `1987831`).

## The thread

- CSJ asked to create branch `freemium` and produce full **separate spec +
  implementation plan for each of sub-projects 2→6** of the Fynla
  major-overhaul series (SP1 = approved canonical-store spec, already exists).
  Work them sequentially, run to completion on all five.
- Branch `freemium` created off `origin/dev` @ 9de1d04, upstream unset
  (don't leak pushes to dev), pushed to `origin/freemium`.
- Ran the `superpowers:brainstorming` loop for SP2. Explored existing infra
  (TierGate seam from SP1, Subscription/SubscriptionPlan/Revolut billing,
  HasAiGuardrails daily-token metering, no currency pref, snapshot policies).
- CSJ decisions locked (see CSJTODO "Decisions log"): rename plans→literal
  tiers; Free + 3 paid (family merged into Tier2); Fyn weekly soft-degrade +
  daily hard backstop; grandfather count caps (block new creates only);
  Estate = teaser-gate NOT add-on; Open API = flag-only; admin tier-config
  store = single source of truth driving PricingPage+invoices+Revolut.
- CSJ supplied a whiteboard photo = the canonical tier×capability matrix
  (transcribed verbatim into CSJTODO, with glare-obscured `?` cells flagged
  as assumptions to confirm at the spec review gate).
- Full 10-section SP2 design presented and approved. Architecture =
  Approach A (dedicated admin-editable `tier_configurations` reference-data
  store + `DbTierGate` replacing `PermissiveTierGate`), matching SP1 §12.
- Rejected: SubscriptionPlan.features JSON as matrix home; hardcoded config;
  à-la-carte add-on billing; building Open Banking in SP2; keeping 4 paid
  plans. Do not re-litigate these.

## Files touched this session

- `CSJTODO-freemium-series.md` (new, on `freemium`, committed) — the master
  tracker: series status table, all CSJ decisions, transcribed tier matrix,
  the approved 10-section SP2 design summary. **This is the resume anchor.**
- No code touched. No spec/plan docs written yet.

## WIP commit

- SHA: `1987831` (`wip: context-handover snapshot`)
- Pushed: yes (`origin/freemium`)

## Open decisions

None blocking — SP2 design is fully approved. Deferred-to-spec-review-gate
assumptions (NOT blockers, proceed with these defaults, CSJ corrects at the
written-spec review gate):
- Glare-obscured matrix cells: T1 investments-exotic (assume ✗),
  T1 chattels (assume ✓), T1 doc-storage (assume none/✗),
  Free benefits-child (assume ✓), Free family-module (assume ✓).
- Documents upload allowance unit (assume rolling upload count, ladder
  LIMITED/+1/+2/+3) + storage GB numbers per tier + per-tier £ prices —
  proposed values go in the spec marked "confirm at review".

## Pick up from here (auto-continue contract)

1. Still inside `superpowers:brainstorming` for SP2, at the "write design
   doc" step. Write the SP2 spec to
   `docs/superpowers/specs/2026-05-16-sub-project-2-freemium-tier-model-design.md`
   using the approved 10-section design in `CSJTODO-freemium-series.md`
   ("SP2 tier matrix" + "Decisions log" + the 10 design sections from the
   conversation). Frontmatter: `sub_project: 2 of 6`, status APPROVED,
   related_specs back to the 2026-05-14 canonical store doc.
2. Run the brainstorming spec self-review (placeholder/consistency/scope/
   ambiguity), fix inline.
3. User-review gate: ask CSJ to review the written SP2 spec before plans.
4. On approval, invoke `superpowers:writing-plans` for the SP2 implementation
   plan → `docs/superpowers/plans/2026-05-16-sub-project-2-freemium-tier-model-plan.md`.
5. Then repeat the full brainstorm→spec→plan loop for SP3 (mobile-first
   iframe `/m/*` shell), SP4 (campaign engine), SP5 (track-lightweight
   onboarding), SP6 (gamification). Update CSJTODO series table + decisions
   log after each. Do not stop until all five spec+plan pairs exist and pass
   their review gates (CSJ instruction: "continue … until we are finished
   with them all").

## What the next Claude needs to know

- **Worktree quirk:** working dir is the `freemium` worktree at
  `.claude/worktrees/tender-bassi-375ee8`. The main repo checkout
  `/Users/CSJ/Desktop/fynla` is on a *different* branch
  (`fix/advice-prompt-jointowner-lazyload`) — do NOT write freemium work
  there. All freemium-series files (CSJTODO, specs, plans, handovers) live
  on the `freemium` branch in the worktree.
- CSJTODO-freemium-series.md is the single continuity doc — read it first
  on resume; it has the full approved SP2 design and the transcribed
  whiteboard matrix.
- PR #317 (dev→main release) stays parked until SP2 lands on dev — see
  memory `project_pr317_gated_on_freemium_refactor.md`. Don't propose
  shipping #317.
- `PlanConfiguration` is financial-calc defaults, NOT the freemium matrix —
  don't overload it; the matrix gets its own `tier_configurations` store.
- SP1 already shipped the `TierGate` interface + `PermissiveTierGate`
  (bound) + `StaticTierGate` (delete it in SP2) + `TierLimitExceededException`;
  `SavingsStore` already calls the gate. SP2 makes the gate real.
- Brainstorming skill rule: spec must get a CSJ review gate BEFORE
  writing-plans. Don't skip straight to plans.
- This is a long multi-window campaign — keep CSJTODO updated every window
  so the next handover is cheap.

## Branch / deploy state

- Branch: `freemium` (off origin/dev @ 9de1d04, upstream now origin/freemium)
- Ahead of origin/freemium: 0 (WIP commit pushed)
- Behind origin/dev: 0 at branch point
- Deploy status: Not deployed (planning only; no code changes)
