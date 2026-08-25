# W-0452 — build-lead (`fix-cycle4-figures`) → quality-lead

**Branch doc:** `workforce/branches/fixes/F-0033-cycle4-the-charitable-saving-and-the-percentage-denominator.md`
**Statutory gate:** CLEARED WITH CONDITIONS — `workforce/ops/handoffs/W-0451/tax-compliance-reviewer-verdict-2026-08-23.md` (one verdict covers both items). **Q2 answered YES; C1 was blocking and is discharged.**

## Done

**This item had TWO axes, not the one the dispatch named**, and both had to move
or the page still contradicts itself:

- **numerator** — the survivor's will versus the logged-in user's. Coincides on
  this persona (£10,000 each), so no fixture in the codebase could express it.
- **denominator** — the household net estate versus the individual's. This was the
  5× the browser measured: `EstateAgent:211` passed `$assetSummary['net_estate']`
  (one person's assets) with `$ihtCalculation['nrb_available']` (two people's
  band). **A mongrel baseline that was nobody's.**

**Both close by one routing.** `WillAnalysisService::analyzeCharitableBequests()`
no longer computes a baseline, threshold or donated amount from anything — it is
handed the position `IHTCalculationService` settled.
`EstatePlanService::charitablePercentage()` is deleted. **There is now exactly one
division producing this percentage in the application** (`IHTCalculationService:1466`),
and the reviewer verified that.

**Acceptance, item by item:**

1. **One numerator, the survivor's, on both surfaces** — done, verified.
2. **`survivingMember()` exposed once, not re-implemented** — satisfied by the
   `(or an equivalent)` clause: the resolution stays in one place and its *answer*
   is published, so no second caller re-derives the pooling predicate. **The
   reviewer accepted the substitution and required the identity be published too;
   that was C1 and it is done.**
3. **A fixture where the logged-in user is NOT the survivor and the legacies
   differ** — the Bennett household. Harold £4,930, Patricia £31,750, estates
   £903,000 / £412,000 the *opposite* way round so no wrong reading lands on the
   right number by accident.
4. **Verified from both spouses' sessions, figure does not move** — 0.8% from
   both, where Sarah's read 4.2%. Evidence `165-`, `169-`, `170-`.
5. **Tax reviewer confirms the survivor on the plans surface** — YES (Q2).

## Not done, and why

- **The `actions` array is not rendered on any page I could find** —
  `/actions/estate/*` prints the trace and description, `/plans/estate` prints the
  description. Covered by test only, stated rather than implied.
- **W-0139's acceptance 1 still needs re-wording** — it asks for the pooled
  household total to feed the rate test, which the 2026-08-21 ruling forbids.
  Team-lead has annotated it; I did not edit an item I do not own.

## What you need that is not obvious

- **The persona cannot demonstrate the numerator axis.** Both spouses leave
  £10,000, so the page proves the percentage no longer moves between sessions and
  **cannot prove which will it came from.** Only the Bennett fixture expresses it,
  and only mutations M4 and M9 kill it. **Do not accept a screenshot as evidence
  for that criterion.**
- **The percentage a user is shown is no longer the number they typed.** With
  W-0432's C2 closed, a profile entry of 5% displays as 7.0% on this household —
  statutorily correct (Schedule 1A measures against the baseline) and the whole
  point of closing C2, but input and output now share a name and differ in value.
  Reviewer advisory C7, recorded on W-0433. **No fixture reaches it: 0 of 2
  `iht_profiles` rows carry a value.**
- **A predicate divergence now sits under these figures.** `IHTController:52` and
  `EstateAgent:146` derive household pooling differently, so the two surfaces
  could disagree about who is in the household — and therefore about who the
  survivor is. Unreachable across all 12 linked users today; recorded on W-0154.

## Assumptions I made

- **That the denominator axis was in scope**, though the acceptance names only the
  numerator. The measured defect was the denominator, and fixing one without the
  other leaves a page that still cannot derive its own percentage. Flagged to
  team-lead before starting rather than discovered in review.
- **That routing `/plans/estate` onto the published figure is better than
  exposing the private helper.** Reviewer agreed, with the identity condition.

## Surfaces covered / not covered

**Covered:** desktop web — `/estate`, `/plans/estate`, both spouses' sessions.
**Not applicable:** `/m` and native — zero consumers of any charitable key,
re-verified independently by me and by the reviewer. Matches `surfaces: [web]`.
