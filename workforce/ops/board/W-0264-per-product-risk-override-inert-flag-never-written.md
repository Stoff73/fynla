---
id: W-0264
title: The per-product risk override has never worked for any real user — `has_custom_risk` is written only by seeders, and every reader gates on it
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0023-cycle4-validation-and-silent-data-loss.md
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T21:20:00Z
claimed: 2026-08-22T21:20:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0262, D-21]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Raised out of **W-0262** at the team lead's direction, because the scope is the
whole repository rather than the pension fix it was found inside.

### The defect

Three services gate the per-product risk override on a **pair**:

| Reader | Test |
|---|---|
| `RetirementController:865` | `$pension->has_custom_risk && $pension->risk_preference` |
| `PensionProjector:291` | same — **this is the one that changes the projection** |
| `PortfolioPresentationService:204` | same |
| `InvestmentController:1091` | `$account->has_custom_risk && $account->risk_preference` |

**`has_custom_risk` is written by nothing but seeders.** A grep of the entire
repository finds three writers, all seed data:

```
database/seeders/PreviewUserSeeder.php:935   'has_custom_risk' => ! empty($account['has_custom_risk']),
database/seeders/PreviewUserSeeder.php:996   'has_custom_risk' => ! empty($pension['has_custom_risk']),
database/seeders/ChrisUserSeeder.php:219,253 'has_custom_risk' => false,
```

No form, no controller, no normaliser, no Fyn tool and no API endpoint sets it.
The column defaults to `0` (`dc_pensions.has_custom_risk` and
`investment_accounts.has_custom_risk` are both `tinyint(1) NOT NULL DEFAULT 0`).

**So for every real user, on both pensions and investment accounts, the
per-product risk override has been inert since the column was created.** The
control renders, the value can be chosen, and nothing downstream ever reads it.

### Why this is not just a detail of W-0262

W-0262 fixed the pension half by deriving the flag in
`PensionNormaliser::fromFormDc` — choosing a level IS the act of overriding. That
covers `dc_pensions` only. **`investment_accounts` is unchanged and still inert**,
and it belongs to whoever owns the investment/projections surface.

The tester's control case is the proof the two halves are separate: they set
`investment_accounts.26.risk_preference` to `high` and it **saved** — the
investment request has always had a `risk_preference` rule. It saved, and it still
did nothing, because `has_custom_risk` stayed `0`.

### Very likely the explanation for D-21 (CRITICAL)

D-21: saving a risk change moves the label (Medium → Upper-Medium) and the caption
(5.41% → 7.07%) but the projected value does not move **by a single pound**.

That agent holds a cache-key hypothesis, explicitly flagged as unproven. **A gate
that is never true produces exactly this signature and a cache does not:**

- A cache would clear eventually; this does not.
- A cache would freeze the caption too; the caption updates and only the value is stuck.
- The label and caption are computed client-side from the selection; the projected
  value comes from `PensionProjector`, which is gated on `has_custom_risk`.

**The two hypotheses are cheap to discriminate:** set `has_custom_risk = 1` on the
row by hand and re-run the projection. If the value moves, it is the gate, not the
cache.

## Acceptance

- [x] The pension side sets the flag when a user chooses a level (W-0262).
- [ ] The investment-account side does the same — **NOT DONE, different owner.**
- [ ] D-21 is re-tested against this hypothesis before the cache hypothesis is acted on.
- [ ] A decision on whether `has_custom_risk` should exist at all: it carries no
      information that `risk_preference IS NOT NULL` does not already carry. Two
      columns encoding one fact is what allowed them to disagree.

## Working notes

- 2026-08-22 build-lead: **Found while fixing W-0262; pension half fixed there.**

  **The design question worth answering before anyone adds a second fix.**
  `has_custom_risk` is redundant. `risk_preference` is nullable on both tables, so
  "has an override" is exactly "`risk_preference` is not null". A separate boolean
  saying the same thing is a second source of truth for one fact, and it drifted
  the moment nothing wrote it. My derivation in `fromFormDc` keeps them in step at
  the write boundary, but **collapsing the pair into the nullable column is the
  fix that makes the drift impossible** — and that is a small migration plus a
  sweep of the four readers, not a normaliser change.

  I have not done that here because it reaches into the investment surface another
  agent is live in, and because deleting a column is a decision, not a fix.

  Recorded in `F-0023` §3.3.


---

## Investment side — build-lead, 2026-08-22 (F-0024)

The pension side is closed by W-0262's normaliser. **The investment side was still
inert and is now closed here, by a different route and deliberately so.**

### What was inert

| Reader | What it did with an override the user had set |
|---|---|
| `PortfolioPresentationService:204` | discarded it — recommended the **user's main profile** allocation |
| `InvestmentController:1091` | passed `null` as the account risk, so `DiversificationAnalyzer` never saw it |
| `AccountRebalancingController:220` | rebalanced toward the **main profile** target |

**16 investment accounts carry a `risk_preference`; 2 carry the flag** (both seeded).
No client has ever written it — there is no `has_custom_risk` in
`app/Http/Requests/Investment/`, in any investment store or normaliser, or in
`AccountForm.vue`.

### The route taken, and why it differs from the pension side

**The readers now read `risk_preference` itself.** Per the team lead's design note —
*do not deepen the dependence on the flag if the cleaner read is available* — this
**removes** three dependencies on the redundant column rather than adding a fourth
writer, and needs **no backfill**, because the preference is already stored correctly on
all 16 accounts. Deriving and writing the flag would have required both.

**One home:** `RiskPreferenceService::getProductRiskOverride($product): ?string` — the
override or null — with `resolveProductRiskLevel($product, $userId): string` for the full
chain. `InvestmentProjectionService` had **four** copies of that chain inline; all four
now call the one home. **Seven expressions of one rule became one.**

**The pension readers are untouched.** They are W-0262's live, browser-verified work and
cutting across them mid-flight would risk what has just been proven. Routing them to the
same home — and collapsing `has_custom_risk` entirely, since `risk_preference IS NOT NULL`
already encodes the fact — is the follow-up, and is the right shape once both branches
settle.

### Why the projection was never affected

`InvestmentProjectionService` reads `risk_preference` directly and **never mentions
`has_custom_risk`**. This is why the flag was not the cause of D-21 — see W-0251/W-0252.
The two defects are independent and both were real.

### Verified

Browser, David (16), ISA 26 (`risk_preference = high`, `has_custom_risk = 0`), user's main
profile `medium`:

- Recommended allocation: **90% equities** (High) — was 50% (Medium).
- Rebalancing panel renders **"Rebalancing Recommended — Equities 26.3% → 90.0%"**.

Guarded by `tests/Feature/Investment/AccountRiskOverrideIsHonouredTest.php`. Its first
case is the discrimination test: **setting the flag by hand must change nothing**, because
the preference alone is the fact. If the answer moved with the flag, the flag would still
be load-bearing.
