# CSJTODO — Fynla

*Last updated: 2026-08-30 session 1 — seven PRs merged (#750–#756), two open (#757, #758).
Handover: handover/August/30/handover-2026-08-30-session-1.md*

## Read this before producing any board number

**Four board figures were given to CSJ this session and none of them survived scrutiny.**
The last and worst claimed 100 items were "fixed and pinned" because a test names them —
the exact proxy the 29 August handover documents as false, with **W-0463 named as the
counter-example** (three test files cite it; its four reliefs have zero implementation).

**A citation is not a verification. Only reading the code and finding the defect gone is.**
If a number cannot survive "show me one", do not say the number.

The same error ran the other way: **W-0340, W-0203, W-0255 and W-0344 were all relayed as
live work and were all already fixed.** The board's problem is not unfinished work — it is
finished work nobody restamped.

## The verified board position

| | |
|---|---|
| items | 327 |
| stamped done | 130 |
| **outstanding** | **197** |
| — confirmed still live (defect code present verbatim) | **6** |
| — confirmed fixed (defect code gone) | 11 |
| — **unchecked** | **180** |

Worksheet: `workforce/ops/reports/2026-08-30-board-evidence-audit.tsv` (one row per item).
Method and its limits: `workforce/ops/reports/2026-08-30-board-evidence-audit.md`.

## Next session starts here

- [ ] **Verify the 180 unchecked items against the code, top severity first.** This is the
      job. Every count above is provisional until it is done, and CSJ has asked for a real
      number three times. Open the item, find the defect in the code, record FIXED with
      evidence or LIVE with file:line. Restamp as you go.

- [ ] **Fix the 6 confirmed live defects.**
      `W-0432` (high, charitable threshold) · `W-0227` (high, protection debt gap panel) ·
      `W-0510` (drawdown) · `W-0500` (onboarding layout events) · `W-0330` (investment
      account lookup) · `W-0461` (reduced-rate string).

- [ ] **Merge #758** (all green at hand-over) and **#757** (CI was running).

- [ ] **`W-0483` — implement CSJ's amended W-0228 ruling** (2026-08-30): *"W-0228 can allow
      mortgage share that is not the same as ownership share."* Not blocked; it is
      engineering. Relax the throw in
      `CalculatesOwnershipShare::refuseRecordWhoseShareFollowsAnother()`; give the user a
      way to SAY a co-owner borrowed alone; web AND `/m`.
      **Trap:** do not make `mortgages.ownership_percentage` authoritative for existing
      rows — the persona carries joint 50% on a tenants-in-common 40% property, and
      believing it moves that household's liabilities £293,000 → £305,000.

- [ ] **`W-0463` — the four reliefs.** Agricultural Property Relief is **deferred** by CSJ
      (2026-08-29), and agricultural land is a **property type** — that decision is taken,
      do not re-open it. Normal Expenditure Out of Income, quick succession relief and the
      14-year rule remain. On the 14-year rule (`W-0526`): the behaviour is **correct
      already**; it is one rule with two configured homes, so a consolidation, not a gap.

## Known issues

- **The iOS `test-and-build` CI job is FLAKY, not a regression.** Red on #752, #753, #755;
  green on #756, which contains #755's commits. Do not treat one failure as signal.
- **No browser verification happened in the 29–30 August sessions.** Everything merged is
  test-verified only.
- **`main` and `dev` are still diverged.** PR #736 holds the reconciliation and is
  deliberately unmerged, because merging it equals a release.

## Deploy state

- `dev` carries #750–#756. Nothing deployed; csjones and production untouched.
- **Two migrations on `dev` not yet applied anywhere else:**
  `2026_08_29_160000_add_trust_id_to_gifts_table` (additive, backfills) and
  `2026_08_29_110000_allow_estate_planning_in_user_assumptions_type`.

## Tech debt deferred

- 52 unused private injections outside the TaxConfigService cluster; each needs the W-0520
  judgement — dead, or a capability silently unwired.
- `database/schema/mysql-schema.sql` is stale against two migrations. Wrong, not harmful.
- The gifting UI still offers edit/delete on a trust-owned gift (web and `/m`). They fail
  with a clear 422; the control should not be offered. Needs `trust_id` on `GiftResource`.
- Spouse WRITES require reciprocity but not consent — deliberate, so a pending couple's
  household expenditure still splits. Open to challenge.
