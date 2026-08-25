---
id: W-0280
title: Census — user_id-only queries over records that can be shared. Every line is a code-read hypothesis until measured; the first one I published was WRONG
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0024-cycle4-risk-engine-reach-and-fraction.md
owner: build-lead
status: queued
severity: medium
surfaces: [web, m, ios]
created: 2026-08-22T22:10:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [F-0019, F-0022, W-0226, W-0186, W-0271, W-0272, W-0273]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Requested by team-lead alongside W-0271 – W-0273, on the tester's prediction that more
sites exist. Full working in **F-0024 §10**.

`grep -rn "where('user_id', \$user" app/Services app/Agents` → **486 hits across 128
files**. The count is not the finding: most are correctly user-scoped (own profile,
own pension — individual by law — own ISA subscriptions, write paths, tier caps).
**The finding is the subset querying a model that can be SHARED**:

| Model | `user_id`-only sites |
|---|---|
| `InvestmentAccount` | **59** |
| `LifeInsurancePolicy` | 30 |
| `FamilyMember` | 20 (→ W-0275) |
| `Goal` | 15 |
| `Liability` | 8 |
| `SavingsAccount` / `Property` / `Mortgage` | 6 each |
| `BusinessInterest` / `Chattel` | 3 each |
| `CashAccount` / `LifeEvent` | 2 each |

### READ FIRST — a grep finds a shape, not a defect

I published three findings from **reading** code. Team-lead escalated the first to a
priority batch with a tax-compliance review attached. **When I measured it, it was
false.** That correction is the most useful thing in this item.

**1. `Estate/IHTCalculationService::getCurrentInvestmentValue()` — I claimed a household
DOUBLE COUNT. THERE IS NONE. My claim was WRONG. Do not dispatch a tax review on it.**

`where('user_id', $user->id)` never matches `joint_owner_id`. A row has exactly one
`user_id`, so the user's query and the spouse's query are **disjoint** and nothing was
ever counted twice. Measured, sharing on: both members' household figure reads
**£305,000** — the correct household total, and the full value of every record. **No
inflated liability.**

What was actually wrong is the same reach/fraction disease as the rest of the cycle —
a member's own account taken whole, their share of the other member's account not taken
at all — with the two errors **cancelling** while sharing is on. The live exposure is a
household with sharing **off**, where the joint account lands entirely in the recorder's
estate. **Another agent has already fixed it.**

**Why it fooled me:** at the level I looked, the right answer and the wrong answer were
the same number. That is the **Collision** variant from `tests/CLAUDE.md` §4, applied to
an analysis rather than a test. The question I failed to ask: *if the mechanism I am
accusing did nothing at all, would the figure differ?*

**2. `Estate/EstateAssetAggregatorService::getExistingLifeCover():277` — MEASURED,
confirmed, unfixed. This is the entry that deserves priority.** Policy 7 is David's,
`joint_life = true`, £500,000. `getExistingLifeCover(Sarah)` returns **£0** while
`LifeCoverReach` correctly reports her covered for **£500,000**. Her estate plan is
built on the premise that she has no life cover — on the one product whose purpose is
covering them both. The exact reach `LifeCoverReach` was built for (W-0186); same
module as **W-0278**.

**3. `Estate/EstateActionDefinitionService::estateValue():353-375` — re-read, unchanged,
NOT measured.** Investments, cash accounts, estate assets and life cover at 100%, while
property and savings are deliberately filtered **back down** to primary-only after a
joint-aware store read. Two ownership semantics in one method. Needs a decision about
what an estate contains before it needs a sweep.

## Acceptance

1. **Every site MEASURED before it is called a defect.** Six lines of `tinker` per
   claim. The one entry in this census that skipped that step was the one that was
   wrong, and it nearly consumed a tax-compliance review.
2. `InvestmentAccount` looked at first — largest, and it feeds tax, estate and plan
   figures. **A place to look, not 59 known defects.**
3. Each site classified **route / correct-as-is / decision-needed** rather than
   rewritten mechanically: an ISA subscription total is correctly `user_id`-only
   because ISA allowances are individual, and a tier cap counts primary ownership by
   design.
4. `getExistingLifeCover` treated as the priority entry — measured, unfixed, and it
   tells a surviving spouse she has no life cover.

## CORRECTION — build-lead (`fix-cycle4-doublecount`), 2026-08-22

**Read this before sweeping the 59 sites. §1 above states a mechanism that cannot
occur, and the sweep's classification depends on getting it right.**

§1 says summing `where('user_id', $user->id)` then `where('user_id', $spouse->id)`
counts a joint record once from each side — *"£190,000 of a £95,000 record"*.

**A row carries exactly one `user_id`. Those two queries are disjoint and no row can
match both.** The joint account is matched by the recording spouse's query only, at
100%, and by the other's not at all: counted ONCE. Verified numerically — household
investments are **£305,000** under both the original code and
`CrossModuleAssetAggregator::calculateInvestmentTotal`, and fixing the five sites
moved the persona's Inheritance Tax by **£0**. Full working: **W-0331**, and
`F-0026` §2.

**What the pattern actually is — and what to look for at each of the 59 sites:**

1. **Fraction.** A record is taken at 100% by whoever records it, so a share
   belonging to someone with no account here (`joint_owner_id` NULL on a shared
   record) enters the household's figure. This is the live, large one — it is where
   **W-0333's £177,000** comes from.
2. **Reach.** The non-recording side sees nothing of a record they part-own. Visible
   whenever the two members are not being pooled.
3. **Double count, but only between two DIFFERENT readers** — one share-aware, one
   not, summed together. That is real and it bit in `projectInvestmentsMonteCarlo`,
   where the simulation applies the share and the fallback did not.

A sweeper hunting a same-shape double count will classify sites wrongly. Ask instead:
*can a third party's share enter here, and is the non-recording side reachable?*

**Also relevant to the sweep:** `tenants_in_common` is **property-only** —
`investment_accounts.ownership_type` is `enum('individual','joint','trust')` — so on
non-property models a third-party share is recorded as `joint` with a NULL
`joint_owner_id`. The persona holds a mortgage in exactly that shape.

Filed as **W-0337** so the correction is not buried in a working note.
