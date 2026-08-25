---
id: W-0337
title: W-0280 §1 and F-0024 §10 state a double-count mechanism that cannot occur, and a 59-site sweep is queued behind it
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
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
