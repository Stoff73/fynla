---
id: W-0368
title: No joint-property discount is applied to an undivided share held with a non-spouse, over-valuing the very property W-0333 was about
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0026-cycle4-iht-projection-ownership-and-savings-getters.md
owner: build-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-23T01:05:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-23
prior_art_found: [F-0026, W-0333, tax-compliance-review]
prior_art_outcome: none
constitution_refs: [07-quality-bar, 05-perimeter]
---

## Intent

From the tax-compliance review of W-0333.

`CalculatesOwnershipShare:90-92` values a share as a straight percentage of the whole,
reaching the Inheritance Tax figure via `IHTCalculationService:888`. Property 20 is
therefore valued at 40% × £295,000 = £118,000.

For Inheritance Tax, an **undivided share** in land co-owned with a **non-spouse** is
valued with a discount for the restricted marketability of a part-share — HMRC practice
is typically **10%**, or **15%** where the co-owner is in occupation and not a spouse
(IHTM15071, SVM113040). On this share that is roughly **£11,800–£17,700** of
over-valuation today, and ~£34,000–£51,000 grown into the projected estate.

**Correctly NOT applicable to Properties 9 and 19:** the **related property rules
(IHTA 1984 s161)** deny the discount between spouses, and both are held David/Sarah. So
the discount applies to Property 20 and **only** Property 20 — the same row W-0333 was
about.

Erring without it is **conservative** (it overstates tax), which is why this is a
refinement rather than a blocker.

## Acceptance

1. The discount applies only where the co-owner is not a spouse (s161 respected).
2. The percentage comes from configuration, not a literal (Rule 2).
3. Applied consistently to the current and projected columns — **the two must not
   diverge again** (see F-0026 §1).
