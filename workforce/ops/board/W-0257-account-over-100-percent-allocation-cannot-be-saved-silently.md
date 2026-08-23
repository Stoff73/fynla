---
id: W-0257
title: An investment account whose holdings exceed 100% allocation cannot be saved and nothing tells the user why — the Update button silently does nothing
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: handoff
severity: high
surfaces: [web]
created: 2026-08-22T21:40:00Z
claimed: 2026-08-22T22:30:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [RiskLevelSelector, AccountForm, StandardInvestmentFields]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found while attempting to verify W-0252 on David's ISA (account 26).

Each holding's allocation input carries `max = 100 − (sum of the OTHER holdings)`, which
**excludes the field's own current value**. While the allocations total exactly 100% this
is self-consistent and invisible. The moment the total exceeds 100%, **every** input is
below its own value and therefore invalid:

```
Vanguard FTSE All-World  value 36.90  max 31.9
Fundsmith Equity         value 36.80  max 31.8
Scottish Mortgage        value 26.30  max 21.3
Baillie Gifford Managed  value  5.00  max  0
```

`form.reportValidity()` returns false, the submit never fires, **no request is made, no
message is shown, and the Update Account button appears simply not to work.** The account
becomes uneditable — including its risk level, fees and value — with no route to recovery
through the interface.

The app permits the over-100% state in the first place: a fourth holding was added to an
account already at 100% and was accepted.

## Acceptance

1. An account whose allocations exceed 100% can still be opened, corrected and saved.
2. A blocked submit tells the user which field is wrong and why. A button that does nothing
   is not an error message.
3. Either the allocation total is enforced on write, or the form copes with exceeding it —
   not neither.

## Working notes

- Reproduced in the browser, David (16), account 26, 2026-08-22 ~21:40.
- Discovered because a concurrent agent inserted a fifth holding at 21:28 taking the total
  to 105%. **The contamination revealed the defect; it is not the defect.**
- Account 26 is still at 105% — another agent's row, left in place.
- W-0252 was verified on Sarah instead, whose holdings sum to exactly 100%.

- 2026-08-22 build-lead (`fix-cycle4-columns`), F-0025: **fixed, and the repro is
  more ordinary than this item records.**

  **Account 26 is no longer contaminated** — it totals exactly 100 (Fundsmith
  36.80 + Scottish Mortgage 26.30 + Vanguard 36.90), and the fifth holding does
  not exist, live or soft-deleted. Rows 65/66/67 were created 19:52:16; nothing
  was written to account 26 at 21:28.

  **It did not need to be.** `maxAllocation(index)` returns `100 − sum(others)`,
  so at a total of exactly 100 **each field's max equals its own current value**.
  A user cannot raise ANY holding's allocation, ever, without first lowering
  another — and the moment they try, that input is invalid, the browser refuses to
  fire `submit`, and the Update button dies with no message. **Raising one holding
  before lowering another is completely ordinary editing.** The contamination
  revealed the defect; it was never required to reach it.

  **A third cause of the silence, beyond the two in this item: a clamp.**
  `remainingPercent` is `Math.max(0, 100 - totalAllocated)`, so an account 5% over
  and an account exactly full both render as "nothing left over" and the "Cash
  (auto-allocated)" row simply vanishes. **The UI could not show the
  over-allocation because the only quantity it computed had already discarded
  it** — `tests/CLAUDE.md` §4's clamp variant, occurring in production code rather
  than in a test. The fix measures the excess on the side of 100 the clamp throws
  away.

  **Both parents were affected, so the rule lives in one place.**
  `InlineHoldingsEditor` is used by `AccountForm` (investment) AND `DCPensionForm`
  (retirement). Per Rule 20 the answer is in
  `resources/js/utils/holdingsAllocation.js` and all three import it; editing three
  copies in lockstep would have been the violation, not the fix.

  **Found in the same method and filed separately: W-0322**, where collapsing
  "Additional information" sent `holdings: []`, which the controller read as
  "delete everything" and answered with a single 100% Cash holding. Frontend half
  fixed here; the controller's contract is W-0322.

  Acceptance 3 is met by "the form copes". **Enforcement on write is still open as
  W-0321** — a client-side guard is not enforcement, and `/m`, native and Fyn
  capture all post to the same unguarded endpoints.

- 2026-08-23 build-lead (`fix-cycle4-columns`): **BROWSER VERIFIED, acceptance met.**

  Account 26, three holdings at exactly 100 (36.80 / 26.30 / 36.90), David (16),
  `localhost:8000`, identity read from `fynla-state.auth.user`.

  **Fault 1 fixed:** all three allocation inputs render `max="100"`. Pre-fix each
  carried its own value.

  **The behaviour:** raising Vanguard 36.90 → 40 leaves all three inputs
  `valid: true`, `rangeOverflow: false`, so submit fires and code runs. Pre-fix
  all three were invalid and the browser refused to fire submit at all.

  **Fault 2 fixed:** the message appears at the field AND above the button, and
  names the excess — *"These holdings add up to 103.1% of the account. Reduce them
  by 3.1% so they total 100% or less."* Submit blocked with **no network request**;
  the button is enabled, not dead.

  **The clamp is visible in the same view:** the header still reads "103.1%
  allocated • 0% remaining (£0)". That is the quantity the new message recovers.

  **Acceptance 1:** corrected to 33.70 / 26.30 / 40.00, saved, three live holdings
  totalling exactly 100. **Account 26 is left valid.**

  **A defect in the fix, found in the browser:** after a blocked submit the
  field-level message cleared at 100% while the footer still read "103.1%" —
  `errors.holdings` is only reset on the next submit. A stale instruction to fix
  something already fixed. Corrected so `errors.holdings` records only that a
  submit was blocked while the text comes from a live computed sharing the
  editor's single source; both now clear the instant the total returns to 100,
  re-verified live at 106.2% → 100%.
