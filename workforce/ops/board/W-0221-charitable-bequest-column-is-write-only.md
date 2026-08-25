---
id: W-0221
title: users.charitable_bequest is now read by nothing and can still be written — a write-only column with a live endpoint
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0020-cycle2-auditability-figures-the-user-cannot-check.md
owner: build-lead
status: queued
claimed_by: null
severity: low
surfaces: [web]
created: 2026-08-22T07:56:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: ["W-0132 — removed the last two readers, both halves now with quality-lead", "W-0154 — the calculation reads pooled household bequests, which is the correct source"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: raised by team-lead after verifying cycle2-audit's W-0132 work; the agent asked for an id rather than riding a schema change inside a display fix
---

## Intent

**`users.charitable_bequest` is no longer read by anything in the application. It can
still be written.**

W-0132 removed the last two readers — the estate toggle's client-side model and the
family card — and the calculation now reads the recorded bequests, which is the
instrument. `cycle2-audit` correctly declined to drop the column inside a display fix.

**But "read by nothing" is not the whole state.** Verified 2026-08-22:

- **`UpdatePersonalInfoRequest:79` still validates and accepts it**, so `PATCH` on the
  personal-information endpoint can set it. **A live write path into a column nothing
  consumes.**
- `User.php:178` still casts it.
- `userProfileService.updateCharitableBequest()` is unreferenced but present.
- `EstateAgent:727,754` use the string `'charitable_bequest'` as a **category label** —
  **not** this column. Do not remove those.

**Why a write-only column is worse than an unused one.** It accepts data, returns
success, and discards it — which is the shape of half the defects on this board. And the
next feature wanting an answer about charity will find a column with a plausible name, a
cast, and a working endpoint, and will read it — reintroducing the fourth mechanism
W-0132 has just removed.

## Acceptance

- [ ] The write path is closed **before or with** the column being dropped — a column
      dropped while its endpoint still accepts the field trades a silent discard for a
      500.
- [ ] The unreferenced frontend service method goes with it.
- [ ] `EstateAgent`'s two category-label strings are **untouched** — different thing,
      same name.
- [ ] Migration only, no behaviour change. Nothing reads it, so nothing should move.
- [ ] Check `/m` and native for any reader before dropping (none found at raise time).

## Working notes

(append-only)

- 2026-08-22 team-lead: raised on `cycle2-audit`'s request. **Its judgement not to ride a
  schema change inside a display fix was right** — that is how a migration lands in a
  batch nobody expects one in.
- 2026-08-22 team-lead: the agent's report said "read by nothing in the application",
  which is **accurate**. The write path is an addition to that finding, not a correction
  to it — found by grepping for the column while verifying its work.
