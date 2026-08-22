---
id: W-0033
title: ComprehensiveProtectionPlanService reads two user properties that never exist — dead branches, and fixing them would change which source drives protection advice
mission: M-0002-persona-fidelity
owner: build-lead
status: handoff
handoff_to: quality-lead
claimed: 2026-08-21T18:20:00Z
claimed_by: fix-batch-G
branch: branches/fixes/F-0011-batch-g-native-handoff-protection-ownership.md
severity: low
surfaces: [web, m, ios]
source: found by fix-batch-C while fixing W-0006, 2026-08-21
prior_art_checked: 2026-08-21
prior_art_outcome: route
---

## Intent

`app/Services/Protection/ComprehensiveProtectionPlanService.php:193,197` test
`isset($user->smoker)` and `isset($user->good_health)`. Neither property exists — they
are the same phantom columns that caused W-0006. Both conditions are permanently
false, so both always fall through to the protection profile, which is a real working
source.

**This is dead code, not a live fault.** The service produces correct output today.

## Why it was not simply deleted

Pointing those reads at the real `health_status` / `smoking_status` columns would
change **which source drives protection advice text** — from the protection profile to
the user record. That is a Protection-module decision about which source is
authoritative, not a tidy-up. Deleting the dead branches instead is safe but should
still be a deliberate call.

## Acceptance

Decide whether the user record or the protection profile is the authoritative source
for health and smoking status in protection advice, record it, and make the code say
so — either by deleting the dead branches or by wiring them to the real columns.

## Working notes

- 2026-08-21 fix-batch-G (build-lead): **done — decision made, recorded in code.**
  Branch document: `workforce/branches/fixes/F-0011-batch-g-native-handoff-protection-ownership.md`.

  **The decision: the PROTECTION PROFILE is authoritative, and the dead branches are
  deleted.** This is the enforcing layer's answer, not a preference —
  `RecommendationEngine.php:185,232` generates the actual advice from
  `$profile->smoker_status`, `ProtectionDataReadinessService.php:199,396` gates on it,
  and two other modules read the same profile field for the same fact
  (`RetirementActionDefinitionService.php:1656`, `DecumulationPlanner.php:183`).
  **Nothing anywhere reads `users.smoking_status` for protection purposes** —
  `LifeStageService` only tests `!empty()` for profile completeness, and `UserResource`
  merely exposes it.

  **A second reason, which settles it independently: the two sources are not
  interchangeable.** `users.smoking_status` is
  `enum('never','quit_recent','quit_long_ago','yes')`; `protection_profiles.smoker_status`
  is a **boolean**. `users.health_status` is `enum('yes','yes_previous','no_previous',
  'no_existing','no_both')`; the profile's is `in(excellent,good,fair,poor)`. Repointing
  the reads would have been a vocabulary translation, not a rename, and would have put
  this summary out of step with the engine writing the advice beside it on the same page.

  **The pattern question team-lead asked — answered, and it is an incident, not a
  pattern.** `grep -rn 'isset($user->' app/` returns **only these two reads**, both in
  this file. I also checked the other user reads in the same method: `$user->name` is a
  real accessor (`User::$appends`, `getNameAttribute()`), and `gender`, `occupation`,
  `education_level`, `marital_status`, `date_of_birth` are all real columns. It shares a
  *shape* with W-0006 — both invent column names that were never there — but not a
  spread.

  **One live change, deliberately made while in the lines being edited.** With the dead
  branch gone, `$profile->smoker_status ? 'Smoker' : 'Non-smoker'` rendered a **missing**
  answer as "Non-smoker", and the `$healthStatus = 'Good'` default rendered a missing
  health answer as "Good" — definite answers nobody gave, on the document that decides
  how much life cover to recommend. Both now say **"Not provided"**, matching the idiom
  this same method already uses for an absent date of birth. Nothing downstream branches
  on the exact strings (`planPrintMixin.js:2051` only tests truthiness and prints it).

  **And the reason that change is currently invisible — raised as W-0141.**
  `protection_profiles.smoker_status` is `tinyint(1) NOT NULL DEFAULT '0'` and
  `health_status` is `varchar(255) NOT NULL DEFAULT 'good'`. An unanswered question
  **cannot be stored**: the database asserts "non-smoker, in good health" the moment a
  profile row exists, while `StoreProtectionProfileRequest:37-38` validates both as
  `nullable`. The request layer models the unknown and the column erases it. The
  "Not provided" branch is correct and unreachable until that is fixed.

  **F-0005 respected.** `ProfileEnums::EDUCATION_LEVEL_LABELS` is untouched, and the new
  test asserts it still drives the education label, since W-0033 edits the lines
  immediately above it.

  **Verification.** New test
  `tests/Unit/Services/Protection/ComprehensiveProtectionPlanProfileSourceTest.php`
  — 6 passed, including a characterisation test pinning the two column definitions
  (**it will fail when W-0141 is fixed; that is the signal**). Wider regression:
  `tests/Unit/Services/Protection/`, `tests/Feature/Protection/ProtectionApiTest.php`,
  `tests/Integration/ProtectionWorkflowTest.php` — **133 passed, 388 assertions**.
  Pint clean. **Not browser-verified** — a persona-tester closes Rule 14's loop.
