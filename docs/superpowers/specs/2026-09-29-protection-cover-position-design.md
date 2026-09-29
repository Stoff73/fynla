# Protection cover position: design

**Status:** for CSJ review. No code until approved.
**Date:** 2026-09-29
**Decisions (CSJ, 2026-09-29):**
- Show the overlap and consolidate it, so the user sees where they are under-insured, over-insured or dependent on their job for their insurance.
- It lives in the cards and on the page: one calculation feeds both, on web and `/m`.
- Folded into the consolidated cards: the gap cards and the reliance cards. Per-policy cards stay separate.
- Over-insured: the card becomes a review action, and the page shows "Over by £X".

## The problem

Protection now shows the action definitions as cards (#972). Several of them describe the same shortfall from different angles, so one household gets up to eight overlapping cards. csjones user 402 has these five, all about the same missing cover:
- `life_insurance_gap`
- `mortgage_no_decreasing_term`
- `critical_illness_gap`
- `no_ci_with_mortgage`
- `income_protection_gap`

Nothing shows the whole position for a cover type: what you need, what your own policies give you, what your job gives you, and whether you are short or over.

## What the user sees

Three positions, one each for life cover, critical illness and income protection. Each position shows:

| Row | Source |
|---|---|
| You need | `CoverageGapAnalyzer::calculateProtectionNeeds` (the Protection page's need) |
| Your own policies | cover from policies you hold (`LifeCoverReach` for life) |
| Through your job | `CoverageGapAnalyzer::calculateTotalCoverage()['employer_benefits']`: death in service, group income protection, group critical illness. Always labelled "ends if you leave". |
| Short by / Over by | need minus total cover |

It also shows one status per position:
- **Short** when the total cover is below the need.
- **Over** when the total cover is above the need.
- **Depends on your job** when the job's share of the cover is above the reliance threshold. This can apply alongside Short or Over.

Income protection is shown as a monthly figure everywhere. The analyser holds group income protection as an annual amount (`CoverageGapAnalyzer`, `groupIpCoverage = salary x percent`), so the position divides it by 12 at the boundary, once, in the open (data-integrity trap 7).

## Architecture

**One calculation: `App\Services\Protection\ProtectionCoverPosition`.**
- `forUser(User): array` returns the three positions:
  - `life`
  - `critical_illness`
  - `income_protection`
- Each position carries:
  - `need`, `own_cover`, `employer_cover`, `total_cover`, `short_by`, `over_by`
  - `employer_share`, `depends_on_job`
  - `unit` (`lump_sum` or `monthly`)
  - `reasons`
- It builds on `CoverageGapAnalyzer` and `LifeCoverReach`, the same reads the page and the agent use, so the figures cannot drift (Rule 20).
- `reasons` lists the folded definitions that fired for this cover type, each with its key, title and figures. They come from `ProtectionActionDefinitionService::evaluateActions`.

**The reliance threshold goes into configuration.** Today `protection.dis_reliance_percent` is null in the active tax configuration, so a hardcoded 0.50 is what runs, in two places:
- `CoverageGapAnalyzer`, around line 221;
- `ProtectionActionDefinitionService`, around line 633.

This change seeds the key and removes both fallbacks (Rule 2).

**Cards.** `ProtectionActionDefinitionService::evaluateActions` still evaluates every definition. Then:
- **Folded definitions become reasons, not cards.**
  - Life: `life_insurance_gap`, `dependants_no_life_cover`, `mortgage_no_decreasing_term`, `education_funding_gap`, `dis_reliance_warning`, `non_earning_spouse_no_cover`.
  - Critical illness: `critical_illness_gap`, `no_ci_with_mortgage`, `ci_combined_risk`.
  - Income: `income_protection_gap`, `ip_gap_after_state_benefits`, `self_employed_no_ip`, `ip_any_occupation_definition`, `group_ip_any_occupation`, `ip_short_benefit_period`, `ip_long_deferred_period`.
- **Three new definitions** carry the consolidated cards, seeded like the others: `life_cover_position`, `critical_illness_position` and `income_protection_position`.
  - A position card shows when its cover type is short, over or depends on your job, or when any of its reasons fired.
  - Its title states the position. For example: "Your life cover is £357,538 short", "Your income protection is £400 a month more than you need", or "Most of your life cover depends on your job".
  - Its figures are the position's rows, and its description lists the reasons.
- **Separate cards stay as they are:**
  - `policy_not_in_trust`, `policy_expiring_soon`, `policy_expired`, `policy_not_joint_married`;
  - `no_policies_warning`, `high_premium_cost`, `premium_affordability_warning`;
  - `review_existing_policies`, `consolidate_policies`;
  - `no_employer_benefits_recorded`.
- **Ids:** `protection_life_cover_position`, `protection_critical_illness_position` and `protection_income_protection_position`, stable across figure changes. Done marks on the folded cards lapse once. They were created today, in #972.

**Page.**
- `ProtectionController::index` adds `cover_position` from the same service. `/m` already reads `/api/protection`.
- Web gets a "Your cover" section on the Protection page with three rows. Each row expands to show the need, own, job and short-or-over figures and the reasons.
- `/m` gets the same section on its Protection screen.
- The existing need-component breakdown (`ProtectionGapPresentationService`) stays underneath, unchanged.

**Fyn.** The cards reach Fyn through the one actions list, as today. No prompt change.

## How-tos

Each position card gets one how-to in `protection.md`:
- It branches on the reasons that fired (`when life_insurance_gap:` and so on), using the grammar's conditions over the card's facts.
- Its text is rebuilt from the entries CSJ approved today.
- Over-insured branch: check whether the extra cover is still needed before the next renewal, and keep every policy until any change has started. Guidance, never "cancel".
- Job-dependent branch: the death in service steps approved today (ABI group life cover source).

The rebuilt entries go in as `draft` for CSJ's approval. The folded keys' entries stay in the file, marked as folded, so their approved text remains the source.

## Out of scope

- Changing how the need is worked out.
- Per-policy cards.
- Native iOS rendering: it reads the same cards, but the new page section is web and `/m`.

## Testing

- **Unit, `ProtectionCoverPosition`:**
  - short, over and job-dependent cases;
  - monthly income conversion;
  - the joint-life reach (the W-0401 household fixture);
  - the threshold read from config.
- **Cards:**
  - a household with the five overlapping cards gets one life, one critical illness and one income card, carrying those reasons;
  - separate cards are unchanged;
  - ids are stable when a figure changes.
- **Live walk on csjones (user 402), web and `/m`:**
  - the three positions on the page;
  - the three cards, and the figures matching between card and page;
  - over-insured and job-dependent shown on real data.
