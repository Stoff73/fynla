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
- `app/Http/Controllers/Api/Estate/LifePolicyController.php:45`
- `app/Http/Controllers/Api/Estate/GiftingController.php:81` and `:272`
- `app/Http/Controllers/Api/Estate/TrustController.php:186` and `:201`
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
