---
id: W-0227
title: The protection debt gap panel discloses "mortgage balance £0, other debts £0" as the inputs to a £182,500 need — the disclosure contradicts the figure it claims to explain
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: build-lead
status: queued
severity: high
surfaces: [web, m]
created: 2026-08-22T04:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0187, W-0171, W-0134]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised by `cycle2-ownership` after fixing **W-0187**, and **corrected on the way**: I
first reported this as "two sources for one number, £0.00 so it contradicts nothing
today". **That was wrong.** It contradicts something today, on the live persona, and
the contradiction is visible without constructing anything.

### Actual — measured on the live persona, read-only

`ProtectionGapPresentationService::forUser(User 16)`, `debt_protection` category:

```
inputs:
  mortgage_balance      0
  other_debts           0
  calculated_debt_need  182500
need                    182500
```

**The panel states the inputs to the calculation as zero and the result as £182,500.**
A user reading it cannot reconcile the figure to anything, because the two numbers
disclosed as its inputs sum to nothing.

`ProtectionGapPresentationService.php:80-82` emits `mortgage_balance` and `other_debts`
from `ProtectionProfile` — the **user-entered override fields** — while the need beside
them now comes from the records, via
`CoverageGapAnalyzer::calculateDebtProtectionNeed()` →
`CrossModuleAssetAggregator::calculateLiabilityTotals()`.

**This predates W-0187 and was not introduced by it.** Before that fix the same panel
showed inputs `0 / 0` against a need of £365,000 — the same contradiction with a worse
number.

### The deeper problem: one source silently overrides the other

The two are not merely displayed together. `calculateDebtProtectionNeed()` **returns
early** on the profile fields:

```php
if ($mortgageBalance > 0 || $otherDebts > 0) {
    return $mortgageBalance + $otherDebts;   // the override wins outright
}
// ... otherwise compute from the records
```

So a user who once typed a mortgage balance into their protection profile has that
figure **override every mortgage record they own, permanently and invisibly**, and the
panel gives no indication which source produced the number. Records could change by
hundreds of thousands and the need would not move.

Two sources, one number, no disclosure of which won — the auditability problem of
**W-0171**, and the same class as **W-0134**: a figure and the components printed
beside it must reconcile, or neither can be trusted.

### Impact

Debt protection need is converted directly into "buy this much cover". A user is shown
a number they cannot check, explained by inputs that do not produce it.

Worse for a stale override: it is **frozen at whatever was typed**. A household that
has paid down or taken on debt since sees a need that no longer reflects anything, and
nothing on the page says so.

### Repro

1. `david.jones@example.com` → `/protection` → Debt Protection panel. Need
   **£182,500**; disclosed inputs **£0 / £0**.
2. `php artisan tinker` — `ProtectionProfile::where('user_id',16)->first()` shows
   `mortgage_balance = 0.00`, `other_debts = 0.00`, while
   `CrossModuleAssetAggregator::calculateLiabilityTotals(16)['total'] = 182500`.
3. Set the profile's `mortgage_balance` to any positive figure → the need becomes that
   figure and every mortgage record is ignored, with nothing on screen saying so.

### Acceptance

1. **The inputs disclosed are the inputs actually used.** If the need came from the
   records, the panel discloses the records — ideally per liability, the way the
   property cards and the rental composition already do.
2. **A decision on whether the override should exist at all.** It is a manual
   summary field predating the mortgage and liability records; now that both exist and
   are share-correct, an override that silently outranks them may simply be wrong.
   **This is a product call** — the options are: remove it, keep it but label it
   ("you entered this; it overrides your records"), or keep it only where no records
   exist.
3. Whichever survives, the panel names the source. A figure whose provenance is
   invisible cannot be checked by the person it is being sold to.
4. Verified in a browser on both accounts, with and without a profile override set.

- 2026-08-31 build-lead: **VERIFIED STILL LIVE against `dev`.** `CoverageGapAnalyzer::calculateDebtProtectionNeed():67-73`
  still reads the `ProtectionProfile` summary fields first and **returns early** on them:

      $mortgageBalance = (float) ($profile->mortgage_balance ?? 0);
      $otherDebts      = (float) ($profile->other_debts ?? 0);
      if ($mortgageBalance > 0 || $otherDebts > 0) {
          return $mortgageBalance + $otherDebts;
      }

  so a once-typed override still outranks every mortgage record, invisibly and permanently.
  **The records branch below it is now correct** — `:76` returns
  `CrossModuleAssetAggregator::calculateLiabilityTotals()['total']`, at the user's share — which
  is W-0187, verified closed today. So the £182,500 figure is right; the disclosure beside it,
  and the silent override, are what remain.

  **Acceptance 2 is a PRODUCT CALL and it is the blocker.** Remove the override, keep it but label
  it ("you entered this; it overrides your records"), or keep it only where no records exist.
  Raised for CSJ 2026-08-31.
