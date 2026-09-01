---
id: W-0337
title: W-0280 §1 and F-0024 §10 state a double-count mechanism that cannot occur, and a 59-site sweep is queued behind it
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: done
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T23:25:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0280, F-0024, W-0331]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

W-0280 §1 and F-0024 §10 both state that summing `where('user_id', $user->id)` then
`where('user_id', $spouse->id)` counts a joint record **once from each side**, giving
*"£190,000 of a £95,000 record"*.

**A row carries exactly one `user_id`. The two queries are disjoint and no row can
match both.** Verified numerically in W-0331: household investments are £305,000
under both the original code and the reach-and-share reader.

This matters beyond the record: **W-0280 queues a sweep of 59 `InvestmentAccount`
sites plus 30 `LifeInsurancePolicy`, 15 `Goal` and the rest**, and its acceptance
tells the sweeper to classify each site route / correct-as-is / decision-needed. A
sweeper looking for a double count will classify wrongly — the pattern is a
**fraction and reach** failure, and at each site the question is whether a
third-party share can enter and whether the non-recording side is reachable.

A correction note has been appended to W-0280 itself so the sweeper does not have to
find this item first.

## Acceptance

1. W-0280 §1 and F-0024 §10 carry the correction.
2. The sweep's classification criteria name reach and fraction, not double count.

---

## Closed 2026-09-01 — both copies corrected, and the claim measured false

**Acceptance 1 — the correction is carried in both places.**

- `workforce/ops/board/W-0280...md` already held it, under `## CORRECTION —
  build-lead`, and W-0280's own close-out (2026-09-01) now carries the **measurement**
  behind it as well.
- `workforce/branches/fixes/F-0024-cycle4-risk-engine-reach-and-fraction.md:687` was
  the second copy and still asserted the double count. Struck through in place, with
  the reason and the measurement, rather than deleted — a reader who arrives at the old
  claim from a link needs to see it was wrong, not find it missing.

**The claim is not merely unproven; it is impossible.** A row carries exactly one
`user_id`, so `where('user_id', $user->id)` and `where('user_id', $spouse->id)` are
disjoint and no row can match both. Measured 2026-09-01 on account #66, a £95,000 joint
investment account: the recorder's `user_id`-only sum is £220,000 and the joint owner's
is £85,000. The account appears **once**, at 100%, on one side and not at all on the
other. Household total £305,000 — correct. Both member figures — wrong.

**Acceptance 2 — the criteria name reach and fraction.** W-0280's classification block
(`:112-125`) already does: fraction, reach, and the one place a genuine double count
does occur — between two *different* readers where one applies the share and the other
does not, which is the `projectInvestmentsMonteCarlo` case. It closes with the question
a sweeper should actually ask: *can a third party's share enter here, and is the
non-recording side reachable?*

**Why this mattered enough to be its own item:** the false claim was escalated to a
priority batch with a tax-compliance review attached. Left standing in F-0024, it would
have mis-classified the 59-site sweep queued behind it — a sweeper hunting a
same-shape double count finds none, concludes the sites are fine, and leaves every
member-level figure wrong.

No code changed; the defect was in the record, which is what the sweep reads.
