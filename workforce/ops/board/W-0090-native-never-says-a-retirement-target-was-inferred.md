---
id: W-0090
title: Native never says a retirement target was inferred — it shows nothing at all, where web and /m now show the derived figure and label it
mission: M-0002-persona-fidelity
owner: build-lead
reviewers: [product-lead]
status: queued
severity: medium
surfaces: [ios]
created: 2026-08-21T19:45:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0035 (the entry point and the derived-figure caption on web + /m), W-0110 (same shape on the estate side — creatable everywhere, readable on fewer surfaces)]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: raised by fix-batch-E while closing W-0035, 2026-08-21, at team-lead's direction — the detail is established, so the item is raised rather than recommended
---

## Intent

**W-0035 is not broken on native, and this item is deliberately not a claim that it
is.** A target the user has stated displays correctly on native today, and native can
set one through Fyn, which since W-0035 writes through the same `RetirementProfileStore`
as every other surface. What is missing is narrower and worth its own item: **native
never tells the user when the number in front of them was inferred rather than chosen.**

### What native actually does

`ios-native/Fynla/Features/Retirement/RetirementModels.swift:52-56`:

```swift
var targetIncome: Decimal? {
    if let target = analysis?.targetIncome, target > 0 { return target }
    if let target = index.profile?.targetRetirementIncome, target > 0 { return target }
    return nil
}
```

Two sources, then `nil`. `analysis.targetIncome` comes from
`RetirementAgent::analyze()`, which reads `$profile->target_retirement_income` directly
with **no fallback** (`app/Agents/RetirementAgent.php:121`). So for a user who has never
stated a target, both sources are empty and native shows nothing.

**This is exactly what `/m` did before W-0035**, and it was fixed there by fetching
`GET /api/retirement/required-capital` and reading two fields from it:
`required_income` (the derived figure) and `income_source` (`profile` when the user
stated it, `calculated` when `RequiredCapitalCalculator` fell back to a proportion of
their income). Native calls neither.

### Why it matters more than a blank field

The fallback is `(gross income − pension contributions) × 75%`, and it drives required
capital, the income projection, decumulation, capital adequacy and the income gap. On
web and `/m` a user now sees that figure **with a caption saying it was worked out for
them, and an entry point to replace it**. On native they see a gap where the target
should be, with no indication that every projection below it is being computed from a
number the app chose.

Showing nothing is safer than the pre-W-0035 web behaviour — which presented the derived
figure as the user's own, and before that a hardcoded £35,000 — but it is not correct,
and it leaves native as the one surface where a user cannot discover that a target
exists to be set.

### Same shape as W-0110

W-0110 is the estate-side instance: a Lasting Power of Attorney that Fyn can create from
every surface and that only web can read back. Here the record is writable from every
surface (Fyn, and now the web and `/m` forms) and the *derivation* is legible on only
two. Both are the same failure mode — **a surface that can write a record but cannot
explain it** — and whoever picks up either should read the other.

## Acceptance

- [ ] Native shows the derived retirement target when the user has not stated one,
      taking it from `GET /api/retirement/required-capital` — the same endpoint web and
      `/m` read, not a native re-derivation (Rule 20).
- [ ] Native distinguishes a stated target from a derived one in words the user can act
      on, matching the sense of the web and `/m` captions rather than inventing a third
      wording.
- [ ] A user who has stated a target still sees exactly what they see today — this must
      not regress the working case.
- [ ] Whether native gets its own entry point for *setting* the target, or continues to
      route that through Fyn, is **a product decision and is not assumed here.** Fyn
      already writes to the shared store, so routing through it is coherent; a native
      form would be the third entry point and needs the Rule 20 argument made
      explicitly before it is built.
- [ ] Verified on a native build, not inferred from the Swift source.

## Working notes

(append-only)

- 2026-08-21 fix-batch-E: raised from the W-0035 close-out. Deliberately **not** fixed
  in that batch: native is a separate codebase with its own release cadence and
  TestFlight sign-off, and the change needs a new client call, a new model and a view
  change that cannot be built or run from the environment that batch was working in.
  Editing Swift blind and unrun would have been worse than naming the gap.
- 2026-08-21 fix-batch-E: the two fields to read are already in the payload —
  `RequiredCapitalCalculator::getIncomeSource()` returns `profile` or `calculated`
  (`app/Services/Retirement/RequiredCapitalCalculator.php:138-145`) and `required_income`
  sits beside it. No backend work is needed for this item.
