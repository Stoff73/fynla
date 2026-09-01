---
id: W-0530
title: Spouse financial reads required a returned link but not consent, so a pending invitation disclosed the other account's money
mission: null
branch: fix/w-0530-consent-for-spouse-financial-reads
owner: build-lead
reviewers: [compliance-lead, security-reviewer]
status: done
claimed_by: null
severity: high
surfaces: [web, m, ios]
created: 2026-08-29T21:25:00Z
claimed: 2026-08-29T21:25:00Z
blocked_by: []
gate: compliance-lead
prior_art_checked: 2026-08-29
prior_art_found: [W-0350, W-0529, W-0347]
prior_art_outcome: extend
constitution_refs: [05-perimeter, 03-hard-nos]
source: CSJ, 2026-08-29 — assented to the follow-up W-0529 raised, that the Tier 1 reads want consent too
---

## Intent

W-0350 lifted the spouse financial reads to **reciprocity**, on my mistaken reading that
8 of the 12 reciprocal couples held **no** permission row and would lose their spouse
panel. W-0529 measured it properly: **0 hold no row, and 8 hold an explicit `pending`
one** — asked and unanswered. Every one of them was having the other account's figures
disclosed while the invitation sat unanswered.

**CSJ assented to closing it.** Consent now gates the financial reads, as W-0529 made it
gate the estate pooling flag.

## The line drawn

**Financial reads require consent. Identity and family reads do not.**

`DependantsReach`'s docblock makes the argument and it is right: the permission gate
governs financial data, and a child is not that. Consent decides what may be read about
MONEY; reciprocity decides whether the couple exist at all.

| Consent (`financiallySharedSpouse()`) | Reciprocity only (`reciprocalLiveSpouse()`) |
|---|---|
| `NetWorthController::getOverview` | `LetterToSpouseController` — who the spouse is |
| `RetirementIncomeService` ×2 | `UserProfileService` — the couple's children |
| `getSpouseFinancialCommitments` | `DependantsReach` — dependants |
| `getUserById` — `UserResource` carries income and expenditure | `AdvicePromptBuilder`'s name and family lines |
| `UserProfileService::incomeSources` | |
| `AdvicePromptBuilder`'s expenditure line | |
| `RecommendationPersonaliser` ×2 | |
| `UserContextBuilder`, `SavingsActionDefinitionService` ×2 | |

## Acceptance

- [x] Financial reads require a returned link AND consent.
- [x] Identity and family reads keep reciprocity, per `DependantsReach`'s own argument.
- [x] One rule: `User::financiallySharedSpouse()`, with
      `sharesFinancialDataWithSpouse()` expressed in terms of it so there is still one
      derivation.
- [x] Tests that fail with the gate reverted.

## Working notes

(append-only)

- 2026-08-29 build-lead: **WRITES deliberately stay at reciprocity, and this is a
  judgement worth challenging at review.** Requiring consent to WRITE would stop
  `HouseholdExpenditureWriter` splitting the household figure across a couple whose
  invitation is pending — so the whole household total would land on one account. That is
  a functional change dressed as a security one, and it makes the figures worse rather
  than the disclosure safer. Reciprocity already stops the unilateral-link attack the
  writes were open to (W-0350).

- 2026-08-29 build-lead: **the fail-open branch is now load-bearing for a second thing.**
  `hasAcceptedSpousePermission()` returns true when NO row exists (W-0347 G9, deliberate),
  so a reciprocal link predating the consent flow still reads. Zero such rows exist on the
  development database. Every read in the left column above now depends on that default
  being right, where before only the estate pooling did.

- 2026-08-29 build-lead: **verification.** 4,222 passed across Feature Estate, Unit
  Services, Unit Agents, Architecture and Unit Models; 539 across Feature Api and Feature
  UserProfile. Three new tests, **mutation-verified** — reverting the two controllers to
  `reciprocalLiveSpouse()` makes both refusals fail.

- 2026-08-29 build-lead: **stacked on W-0529.** `financiallySharedSpouse()` lives beside
  the derivation added there, so this branches from that one and the diff shrinks when
  #755 merges.

- 2026-08-30 build-lead: **merged to `dev` as PR #756** — consent on the spouse financial reads. Left `gated` rather than
  `done` because the reviewer gate named above has not run; `done` here would mean the
  change is on `dev`, which is true, and would hide that nobody has certified it.

- 2026-08-31 build-lead: **CLOSED — merged as PR #756, verified against `dev`.**
  `User::financiallySharedSpouse()` (app/Models/User.php:606) requires consent, not only
  a returned link, on the spouse financial reads. Writes deliberately stay at reciprocity
  — see the handover's Decisions.
