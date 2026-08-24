---
id: W-0477
title: A deleted spouse leaves both accounts' expenditure stored as halves, and every reader then treats those halves as whole household figures
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [quality-lead]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m]
created: 2026-08-24T10:40:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-24
prior_art_found: [W-0190, W-0202, W-0278]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: quality-lead re-certification, 2026-08-24 — recorded as an out-of-scope observation on W-0202 and filed rather than left in a report
---

## Intent

Under a `joint` household, expenditure is stored as **each account's half**: the row
IS the share (`SharedExpenditure`). Every writer divides on the way in, and every
reader trusts what is stored.

Both the writer and the Fyn agent decide whether to divide using `liveSpouse()`,
which returns null once the partner's account is deleted. **The stored halves do
not change when that happens.** So the surviving account holds £600 of groceries
that means £1,200 of household spending, and from that moment every reader — the
affordability statements, the cash-flow projection, `/m`'s expenditure screen —
treats it as the whole.

**Direction: UNDERSTATES the household's spending by half, and therefore
OVERSTATES disposable income** — which is what every affordability statement rests
on. The same shape as W-0190's original defect, arriving by a different route.

Same family as **W-0278** (`LifeCoverReach` reading a deleted partner's policies):
a value that was correct while two accounts existed and silently changes meaning
when one goes.

## Acceptance

1. Decide what a deleted spouse means for stored shares — restore the household
   figure onto the survivor, or record that the stored value is a share of a
   household that no longer exists.
2. Whichever is chosen, a household whose partner is deleted shows the same
   disposable income before and after, or a stated reason why it changed.
3. Before/after measured on a real linked pair, not reasoned.
4. `/m` matches (Rule 19) — its expenditure screen reads the same rows.

## Working notes

- 2026-08-24 — Raised by `quality-lead` while attacking W-0202's arithmetic, and
  explicitly held out of it: *"Not W-0202's to fix; worth an item."* Filed here so it
  does not stay in a handoff report.
- 2026-08-24 — Note the retention decision that makes this reachable is deliberate
  (CSJ D1/D2, 2026-08-19: retain the rows, ignore them at read time). This is not an
  argument to reverse it — it is the consequence needing its own answer.
