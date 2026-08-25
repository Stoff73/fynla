---
id: W-0244
title: The retirement module reports "not yet set up" for a household with £500,000 of pensions, because there is no retirement_profiles row
mission: persona-run-peak_earners-2026-08-20
branch: F-0024
owner: build-lead
status: gated
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T20:45:00Z
claimed: 2026-08-22T21:05:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-22
prior_art_found: [W-0238, F-0001]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found while fixing **W-0238** and **worked around at the card level, not fixed** —
the fix belongs inside `RetirementAgent` and its blast radius is every consumer of
retirement analysis, which is more than this cycle's items.

### The defect

`app/Agents/RetirementAgent.php:110-114`:

```php
$hasProfile = $profile !== null;
…
if (! $hasProfile) {
    return $this->response(false, 'No retirement profile found', []);
}
```

`success: false` with an **empty data array**. Not "we cannot project without a
target" — nothing at all: no pot, no schemes, no state pension, no count.

Both peak_earners accounts are in this state. Verified against the live local
database: `retirement_profiles` holds **zero rows for users 16 and 17**, while they
hold two defined contribution pensions worth £500,000, an NHS final salary scheme
paying £35,000 a year, and a State Pension forecast.

The readiness gate is **not** what blocks them — `RetirementDataReadinessService::
assess()` returns `can_proceed: true` for both, with the missing target age and
target income correctly classified as warnings. The profile check below it is
absolute.

### Why the distinction matters

**What a user HAS is a fact about their pension records. What they are AIMING AT is
a fact about their profile.** Conflating them means a household that has entered
every pension it owns is told it has not started, and any consumer asking "does
this user have retirement provision" gets "no".

### The workaround now in place, and its cost

W-0238 made `MobileDashboardAggregator::extractRetirementSummary()` read the pension
records directly when the agent declines to answer, so the dashboard card is right.
**Every other consumer of `RetirementAgent::analyze()` still gets nothing** — the
retirement module page, the plans, and Fyn's retirement context. That asymmetry is
the cost of not fixing this here, and it is why this is filed as high.

### The likely correct shape

Return `success: true` with the pension facts and a null projection, so
"I have no target" and "I have no pensions" stop being the same answer. **Every
consumer that branches on `success === false` for retirement must be enumerated
before that lands** — several render "not yet set up" from it.

## Acceptance

1. A user with pensions and no `retirement_profiles` row gets their pot, schemes and
   guaranteed income from `RetirementAgent::analyze()`.
2. Every `success === false` retirement consumer enumerated and checked.
3. The dashboard's direct pension read in `extractRetirementSummary()` is removed
   once the agent answers, so the figure has one home again.
4. Whether onboarding should be writing a `retirement_profiles` row for these users
   at all — a separate question this item raises but does not answer.

---

## CSJ RULING, 2026-08-22 — FIX IT PROPERLY, NOW

**Decided by CSJ. Not open for re-litigation (Rule 18).**

CSJ chose the full fix over keeping the card-level workaround, accepting the blast
radius knowingly.

**The shape, as this item proposed it:** `RetirementAgent::analyze()` returns
`success: true` carrying the pension facts with a **null projection**, so that
*"I have no target"* and *"I have no pensions"* stop being the same answer.
**What a user HAS is a fact about their pension records. What they are AIMING AT is a
fact about their profile.** The absolute profile check at `RetirementAgent.php:110-114`
must stop gating the facts.

**In scope — every consumer, not just the card:**

1. `RetirementAgent::analyze()` itself.
2. **Remove the W-0238 workaround** in `MobileDashboardAggregator::extractRetirementSummary()`
   once the agent answers properly — it must not survive as a parallel mechanism
   (Rule 20). The card's current correctness is a patch, not the fix.
3. The **retirement module page**, the **plans**, and **Fyn's retirement context** —
   all three currently receive nothing. Enumerate every `success === false` consumer
   before changing the return shape, and fix each.
4. Web, `/m` and native (Rule 19), both accounts.

**Acceptance:** a household holding £500,000 of Defined Contribution pensions, an NHS
final salary scheme and a State Pension forecast is never told it has no retirement
provision, on any surface — while a household that genuinely has no pensions still is.
**Assert both directions**; a test that only checks the populated case cannot fail
(`tests/CLAUDE.md` §4, fixture variant).

**Interaction with W-0241:** that item rules defined benefit schemes OUT of the net
worth capital figure. This item is about **provision**, not valuation — the two do not
conflict. A user with only a Defined Benefit scheme has retirement provision and a
£0 pensions capital line, and both statements must be true at once.

**Sequencing:** queued behind the W-0228 ownership batch, which is live in
`MobileDashboardAggregator.php`.


---

## HANDOFF → quality-lead, 2026-08-22 (build-lead, `fix-cycle4-pensions`)

**Branch document: `workforce/branches/fixes/F-0024-cycle4-pension-provision-and-valuation.md`.**
Read §6.1 before anything else — it is the part of this fix that is not in the item.

### What was done

`RetirementAgent::analyze()` returns `success: true` with the pension facts and a
null projection, exactly as the ruling specified. `summary.has_retirement_target`
is the flag consumers branch on; `success` is no longer that flag. The
**W-0238 workaround in `MobileDashboardAggregator::extractRetirementSummary()` is
deleted**, along with the two constructor dependencies it needed.

**All eleven `success === false` consumers were enumerated before the shape
changed, then exercised against the real new shape.** The table is F-0024 §9. Four
needed changes; the rest were verified correct, not assumed.

### What was NOT done, and why

1. **The native retirement card is W-0243's, not this item's.** `FinancePanelsView.swift:91-93`
   still reads £0 for a Defined-Benefit-only household, because `pot_value` is
   present-and-zero so its `??` never fires. W-0243 already specifies the fix in
   detail and is `blocked_by: [W-0238]`, which is now done — **it is unblocked and
   ready to claim.** The backend it needs is live. Nothing breaks meanwhile:
   `Codable` ignores the unlisted key.
2. **"Guaranteed income" still has two implementations.** The backend computes it
   from `PensionProjector` (revalued); `PensionList.vue` computes it client-side
   from raw columns. They agree on this persona **only** because Sarah's scheme has
   `inflation_protection = 'none'`. F-0022 assigned that reconciliation to
   **W-0245**; doing it here would have moved a user-visible number under cover of a
   fix that promised not to.
3. **`LifeStageService.php` untouched** — W-0242's agent had already removed the
   phantom reader. Verified, not assumed.

### What the receiver needs that is not obvious

- **The readiness gate was a second, independent path to the same wrong answer.**
  It blocks on missing *income* and returned `summary: null`. F-0022's workaround
  happened to cover it; deleting the workaround without covering it would have
  regressed exactly what F-0022 fixed, and would have looked like a clean deletion.
  Both ends are fixed, with a test named for that branch.
- **Unblocking a dead code path runs code that has never seen this input.** The
  retirement plan promised *"retirement at age 0 with £0 per month"* and
  *"projected to meet your retirement income target"* with `on_track: true`, to a
  household with no target. Both fixed. A null income gap is an absent measurement,
  not a surplus, and `$incomeGap <= 0` cannot tell them apart.
- **`?? 0` in `RetirementController` turned every null into a confident zero**, and
  `/m` tests `Number.isFinite()` to choose between a figure and an em dash — zero is
  finite. Target-derived fields now pass null through; record-derived fields keep
  their zero.

### Assumptions made

- **"A null projection" was read as: everything derived from the TARGET is null,
  everything derived from the RECORDS is present.** `income_projection` is therefore
  populated — it is entirely record-derived, and nulling it would have discarded the
  Defined Benefit and State Pension income that makes this case work.
- A readiness-blocked household **with** a profile reports `has_retirement_target: true`.
- "Not configured" now means holding no pensions, not lacking a target.

### Evidence

Both accounts on the local `laravel` database, caches flushed, before and after:
**no net worth figure moved and both retirement cards are byte-identical** — which
is the proof the agent took over from the deleted workaround. `tests/Feature/Retirement/PensionProvisionAndValuationTest.php`
is 13 passing, **asserting both directions**. Full runs in F-0024 §10.

**Not browser-verified.** The single Playwright tab is held by another agent and
was not released to this batch. Stated as a gap, not signed off.

### BROWSER VERIFIED — 2026-08-22, both accounts, web and `/m`

Full evidence in F-0024 §11. The acceptance sentence, tested on screen:

**A household holding an NHS final salary scheme is never told it has no retirement
provision.** Sarah (17), who has £0 in Defined Contribution pensions:

- web dashboard — RETIREMENT **£35,000/year, "Guaranteed retirement income"**
- web `/net-worth/retirement` — **"Guaranteed Retirement Income · £35,000/year"**
- `/m` retirement — hero **"Guaranteed retirement income £35,000 a year"**, replacing
  "Projected retirement income £0 a year", with the shortfall measured against the
  secured income (£96,660 − £35,000 = £61,660)
- `/m` pensions category — the scheme listed at **"£35,000 a year"**

**And the other direction:** David (16) leads with **£500,000 "Your pension pot"**,
because he has a pot — the guaranteed-income headline correctly does not fire for him.

**Superseded the earlier "not browser-verified" note in this handoff.**
