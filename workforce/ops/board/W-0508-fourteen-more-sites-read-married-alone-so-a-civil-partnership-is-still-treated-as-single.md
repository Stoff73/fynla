---
id: W-0508
title: Fourteen more sites read ['married'] alone, so a civil partnership is still treated as single across the Estate API, four agents and three services
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: high
surfaces: [web, m]
created: 2026-08-28T12:00:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-28
prior_art_found: [W-0474, W-0480]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: the sweep guard added under W-0480 — tests/Architecture/MaritalStatusLiteralsArchitectureTest.php
---

## Intent

W-0474 fixed one service. Its reviewer checked the siblings and found four more, which
became W-0480. **W-0480 added a sweep, and the sweep found fourteen more.** Each reads
`['married']` alone, so a civil partnership gets the answer a single person gets — while
the Inheritance Tax figure beside it, now corrected, says they are a couple.

The canonical rule is `App\Support\HouseholdPooling::hasSpousalStatus()`. A civil
partnership is a marriage throughout Inheritance Tax: IHTA 1984 s18, s8A and s8G each
read "spouse or civil partner" (SI 2005/3229 reg 7, in force 5 December 2005).

**The Estate API is the worst of it** — these are the endpoints the estate module reads,
so the wrong answer reaches the screen directly rather than through a calculator:

- `app/Http/Controllers/Api/Estate/WillController.php:80`
- `app/Http/Controllers/Api/Estate/GiftingController.php:81` and `:272`
- `app/Http/Controllers/Api/Estate/TrustController.php:186`
- `app/Http/Controllers/Api/EstateController.php:207`

**Three services:**

- `app/Services/LifeStage/LifeStageService.php:174`
- `app/Services/Protection/CoverageGapAnalyzer.php:341`
- `app/Services/Protection/ProtectionDataReadinessService.php:421`

**Four agents**, which is what Fyn says out loud — each currently tells a civil
partnership nothing about a partner, or asks the wrong follow-up:

- `app/Agents/EstateAgent.php:433`
- `app/Agents/ProtectionAgent.php:326`
- `app/Agents/RetirementAgent.php:491`
- `app/Agents/SavingsAgent.php:260`

## Acceptance

1. Each site either reads `HouseholdPooling::hasSpousalStatus()` / `POOLING_MARITAL_STATUSES`,
   or states at the line why its own list is deliberately narrower.
2. Its baseline entry is deleted from `tests/Architecture/MaritalStatusLiteralsArchitectureTest.php`
   — the guard fails on a stale entry, so this is enforced rather than remembered.
3. Before/after for a civil partnership on each figure that moves. The direction is not
   assumed to be the same in each: these are different surfaces and one of them (the
   agents) changes what Fyn *says* rather than what it computes.
4. `tax-compliance-reviewer` on anything that moves tax. The Estate API endpoints do.
5. Rule 19 — verified on web AND `/m`, since the Estate API serves both.

## Working notes

- 2026-08-28 — Filed from the sweep added in the same change as W-0480's fix. **The four
  agent sites are a different kind of defect from the other ten:** they gate a *prompt*
  (`marital_status === 'married' && $user->spouse === null` → ask about the spouse), so
  the failure is Fyn never raising the subject with a civil partnership, not a wrong
  number. Rule 20 applies — fix it in one place, not per agent.
- 2026-08-28 — The guard has both directions: a new literal list reddens it, and so does
  a baselined site that has been fixed without pruning its entry.

## Gate findings folded in — 2026-08-28

The `tax-compliance-reviewer` gate on W-0480 went through this list. **Two of the
fourteen were pulled forward and fixed under W-0480**, because leaving them defeated the
point of that change:

- `LifePolicyController:45` (F1/F5) — it selects the second-death Inheritance Tax basis,
  and `LifeCoverCalculator::calculateLifeCoverRecommendations()` turned out to have **no
  production caller at all**, so fixing the service alone moved no user's figure.
- `TrustController:201` (F2) — byte-identical to the line fixed in
  `ComprehensiveEstatePlanService`, feeding the same `calculate()` and returning
  `iht_liability` to the client.

**What remains, with what the gate found in each:**

- **`WillController:80` (F3) is the most severe and needs a data-remediation step.**
  It is a `create()`: `spouse_primary_beneficiary` and `spouse_bequest_percentage` are
  **written to `wills`** as `false` / `0.00`, so a civil partnership gets no IHTA 1984
  s18 spouse exemption — and the wrong values persist after the predicate is corrected.
  Fixing the code alone does not fix the households already stored.
- **`GiftingController:81`, `:272` and `TrustController:186` (F4) — do NOT fix by blind
  parity.** They default `nrb_transferred_from_spouse` to the full nil rate band for a
  married user and £0 otherwise. **The `married` branch is itself wrong law**: IHTA 1984
  s8A brought-forward nil rate band arises only where the FIRST spouse or civil partner
  has already died with unused band. A living couple defaulting to a full transferred
  band over-claims by up to £325,000 of band. Right now a civil partnership accidentally
  gets the more accurate figure. The correct close makes **both** branches condition on a
  deceased partner.
- **`EstateController:207` (F18) moves no tax.** It eager-loads spouse relations and then
  calls `generateComprehensiveEstatePlan($user)`, which re-derives the spouse itself; the
  `$spouse` local is never read again. A warm cache, not a figure. Noted so nobody
  spends time on it thinking otherwise.
- **`CoverageGapAnalyzer:341`** moves no tax but excludes a partner's income from the
  protection gap.

## The guard's blind spots — recorded here, not fixed

The gate found four, all real:

1. **It scans `app/` only.** `resources/js/` has at least eight components branching on
   `'married'` alone, including `Estate/IHTPlanning.vue:1657`, which drives an
   Inheritance Tax display. Rule 19 puts `/m` in scope by default.
2. **It cannot see a Laravel `in:` rule string** — which is how W-0509's 422 escaped.
3. **It cannot see a database enum** — the other half of W-0509.
4. **Regex gaps:** double-quoted `"married"`, and `whereIn('marital_status', ['married'])`.

And a fifth worth more thought than a regex: the guard **skips any line containing
`civil_partnership`**, so the 22 hand-written `['married', 'civil_partnership']` literals
across 17 files pass. It blocks the narrow copy and blesses the correct-today copy. If
the canonical list ever gains a member the guard stays green while 22 sites diverge.
Asserting on `HouseholdPooling` usage would be the stronger shape.
