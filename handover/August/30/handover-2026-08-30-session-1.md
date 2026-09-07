---
type: handover
mode: session-end
date: 2026-08-30
session: 1
repo: fynla
branch: chore/board-reconciliation-30-august
---

# Session Handover — 2026-08-30, Session 1

## Where things stand

**Seven PRs merged (#750–#756), two open (#757, #758), and the session ended with CSJ
furious — correctly — because I could not give a board number that reconciled with the
code.** The engineering that landed is sound and verified. The reporting on top of it was
not, four times over, and that failure is the most important thing in this document.
**Read "THE FAILURE" before touching the board.**

The verified board position, with nothing inferred: **327 items, 130 stamped done, 197
outstanding, of which 6 are CONFIRMED still live, 11 are CONFIRMED fixed, and 180 nobody
has checked.**

## THE FAILURE — read this before reporting any board number

**CSJ asked for the board numbers at 08:47. I did not give him a defensible one by 09:48.**
Between those times I produced four numbers and every one collapsed when pushed on. The
pattern is the same each time: **I trusted a written record, or a single measurement
channel, and reported the total as fact.**

1. **Counted a register I had just proved unreliable.** I reported 327/202 while
   simultaneously discovering four wrong stamps — including a CSJ decision that never got
   pushed. Reconcile first, count second.
2. **Counted acceptance checkboxes** and told CSJ "118 items cannot be finished as
   written". They are BUG REPORTS. The acceptance on a bug is that the bug is gone. CSJ:
   *"These are bugs... so what are you talking about?"* He was right; the measure was
   noise dressed as insight.
3. **Searched commit messages only** and reported 132 items untouched. The real figure was
   **37** — most citations live in code and tests. Caught only because W-0019 appeared as
   untouched while a test I had edited the day before names it.
4. **The worst one. Claimed 100 items were "fixed and pinned" because a test names them.**
   That is the exact proxy the 29 August handover documents as false, in writing, with
   **W-0463 named as the counter-example** — 9 test citations, genuinely unfinished. I had
   read that section the same morning. W-0463 is IN the 100 I reported as fixed, and its
   four reliefs have zero implementation; CSJ deferred one of them yesterday.

**The rule for the next session: a citation is not a verification. Only reading the code
and finding the defect gone is a verification.** If a number cannot survive "show me one",
do not say the number.

**The reciprocal error, same root:** I relayed board items to CSJ as live work without
opening the code. **W-0340, W-0203, W-0255 and W-0344 were all already fixed.** Three had
been ranked by the previous handover as "most likely genuinely untouched". The board's
real problem is not unfinished work — **it is finished work nobody restamped.**

## Priorities for the next session

1. **Verify the 180 unchecked items against the code, top severity first. This is the
   whole job.** The counts below are meaningless until this is done, and CSJ has asked for
   a real number three times. Method that works: open the item, find the defect it
   describes in the code, and record FIXED (with the evidence) or LIVE (with file:line).
   Nothing inferred from citations. Restamp as you go — closing finished work is what
   shrinks the backlog.
   - `workforce/ops/reports/2026-08-30-board-evidence-audit.tsv` has one row per item.
   - Of the 180: 17 high, 53 medium, 9 low, 1 unrated carry no quoted code; the rest are
     the test-cited bucket that must NOT be assumed fixed.

2. **Fix the 6 CONFIRMED live defects** — their defect code is present verbatim:
   - **W-0432** (high) — charitable threshold, `$threshold = $baseline * getCharitableThresholdPercent()`
   - **W-0227** (high) — protection debt gap panel, `if ($mortgageBalance > 0 || $otherDebts > 0)`
   - **W-0510** (medium) — `$dcDrawdown = min($dcNeeded, $remainingFund)`
   - **W-0500** (medium) — onboarding layout event allowlist
   - **W-0330** (medium) — investment account lookup
   - **W-0461** (medium) — reduced-rate string built with `sprintf`

3. **Merge #757 and #758** once CI clears. #758 was all green at hand-over; #757 had Unit
   and Feature still running.

4. **W-0483 — implement CSJ's amended W-0228 ruling.** *"W-0228 can allow mortgage share
   that is not the same as ownership share."* Not blocked any more; it is engineering.
   Three parts, written up on the item: relax the throw in
   `CalculatesOwnershipShare::refuseRecordWhoseShareFollowsAnother()`; give the user a way
   to SAY a co-owner borrowed alone (the existing `mortgages.ownership_percentage` cannot
   just be believed — see the trap below); web AND `/m` (Rule 19).

5. **W-0530 follow-on, if CSJ wants it:** spouse WRITES still require only reciprocity, not
   consent. Deliberate — see Decisions.

## Context to load

- `workforce/ops/reports/2026-08-30-board-evidence-audit.md` — the audit method, its
  limits, and the four numbers that were wrong. **Read the limits section before
  producing any board figure.**
- `workforce/ops/reports/2026-08-30-board-evidence-audit.tsv` — one row per item:
  `id | status | severity | touched | ticked | unticked`. The worksheet for priority 1.
- `workforce/ops/board/W-0463-*.md` — the counter-example that disproves citation-based
  verification. Four reliefs, zero implementation, three test files naming it.
- `workforce/ops/board/W-0483-*.md` — the amended W-0228 ruling and the three-part fix.
- `app/Models/User.php` — `reciprocalLiveSpouse()`, `sharesFinancialDataWithSpouse()`,
  `financiallySharedSpouse()`: the three spouse-authorization rules added this session,
  with the reasoning in their docblocks.
- `tests/Feature/Estate/PeakEarnersPersonaFiguresTest.php` — the persona's figures locked
  against `tests/Persona/peak_earners.md`. £343,512 is the household bill; if it moves,
  something upstream broke.

## Completed this session

**Merged to `dev`:**

- **#750 / W-0522** — taper ladder read from configuration; trust transfer typed as a
  chargeable lifetime transfer.
- **#751 / W-0523** — the multi-cycle trust death charge now charges the EXCESS with the
  20% lifetime credit, on a death-now basis worked from the calculation date rather than
  projected life expectancy. Two CSJ rulings, both recorded on the item.
- **#752 / W-0528** — `gifts.trust_id`; the settlement gift tracks its trust for life
  (created/updated/deleted/restored/forceDeleted), and the gifting endpoints refuse to
  edit or delete a trust-owned gift. **Found and fixed a £70,000 error**: four duplicate
  £150,000 settlement gifts had capped the persona's gift deduction at the full band.
- **#753 / W-0350** — 53 spouse-id consumers, five idioms, reduced to one rule. Tier 2
  cross-account WRITES and the whole Tier 1/Tier 3 disclosure surface gated on reciprocity.
- **#754** — board status tidy-up after #753.
- **#755 / W-0529** — eight derivations of `$dataSharingEnabled`, in six shapes, collapsed
  to `User::sharesFinancialDataWithSpouse()`, with an architecture test that fails if a
  ninth appears.
- **#756 / W-0530** — CONSENT, not only a returned link, on the spouse financial reads.

**Open:** **#758** (W-0340, cache key pools as the calculation pools) — all green.
**#757** (board reconciliation + the evidence audit + CSJ's amended W-0228 ruling) — CI
running at hand-over.

**Board items closed with code evidence:** W-0203, W-0255, W-0344 — all three already
fixed, none restamped. **W-0524 deferred** by CSJ with the property-type ruling recorded.
**W-0340 and W-0483 unblocked** — neither was actually blocked.

## Verification state

- **Full suite run alone, first time in four sessions:** 8,100 passed, 2 failed, at
  `53e9f65e0`. Both failures repaired and merged — one a one-sided spouse fixture, one a
  PRE-EXISTING Decoy in `OffPlatformCoOwnerNameTest` reading the wrong tool-catalogue shape.
- Per-PR: ~4,200 (W-0530), ~3,800 (W-0350), 1,133 (W-0529), 565 (W-0340), 592 (W-0523).
- **Every behavioural fix mutation-verified** — reverted, confirmed the test fails, restored.
- **NOT verified in a browser, anywhere. No Playwright this session.**
- **NOT verified: the 180 board items in priority 1.** That is the gap.
- The persona figures are locked and green: household £1,728,780, bill £343,512.

## Decisions and dead ends

**CSJ decisions — do not re-litigate:**

- **The trust death charge is the EXCESS, not the gross** (2026-08-29). *"It would cost the
  excess, don't double count the nrb."* The band a within-band transfer consumes is charged
  in the ESTATE, where `FailedGiftTaxCalculator` withholds it for seven years. Charging it
  in both places bills one band twice.
- **Taper relieves the TAX, not the band** (2026-08-29). CSJ first ruled the band tapers
  back, then reversed: *"You are right, it does not give back band relieves the tax."*
  `FailedGiftTaxCalculator` is deliberately untouched.
- **`EstateAgent` derives the pooling flag from the permission** (2026-08-29) — *"Yes it
  should."* Delivered as one derivation for all eight sites.
- **Agricultural land is a PROPERTY TYPE, and W-0524 is deferred** (2026-08-29). The design
  decision is TAKEN; do not re-open it when the item unparks.
- **W-0228 amended** (2026-08-30): *"W-0228 can allow mortgage share that is not the same as
  ownership share."* Unblocks W-0483.

**Settled by evidence:**

- **Cohabitants are not a product question.** No spouse exemption (IHTA 1984 s18), no
  transferable nil rate band (s8A), no transferable residence band (s8G). W-0340's
  escalation framed two answers as equivalent; they never were.
- **Writes stay at reciprocity, not consent.** Requiring consent to write would stop
  `HouseholdExpenditureWriter` splitting the household figure for a pending couple and dump
  the whole total on one account — a functional regression dressed as a security fix.
- **The permission population, measured properly:** 12 reciprocal links, 4 accepted, **0
  with no row**, 8 explicitly `pending`. I first reported the 8 as "no row" and advised
  against requiring consent on that basis. Wrong, and it argued the wrong way.
- **The iOS `test-and-build` job is FLAKY, not a regression.** Red on #752, #753, #755;
  green on #756, which contains #755's commits. Do not treat a single failure as signal.

**Dead ends:**

- **Do not verify board items by citation.** See THE FAILURE.
- **`python str.replace(old, new, 1)` on a non-unique anchor.** Did this twice — once
  landing a fixture fix on the wrong test in a 2,000-line file, once putting the wrong
  assertions in the wrong test. Both times the test stayed red and caught it. Anchor on
  something unique, or rewrite the file.
- **`git checkout HEAD -- <file>` for a mutation check destroys uncommitted work.** Lost
  `HouseholdPlanningService` edits that way and had to redo them. Copy first.
- **I committed the W-0524 defer and never pushed it.** It sat on a local branch while #752
  merged without it, so CSJ's decision was missing from the board for a day. Commit AND
  push, then verify the file on the branch that merges.

## Things that will bite you

- **`TierConfigurationSeeder` is required in any test hitting `/api/*`.** Without it every
  call returns **404 "Endpoint not found"**, not 403 — the route matches and tier
  resolution fails behind it. It reads exactly like a missing route. Cost four cycles.
  Endpoints behind `estate.full` or `guardDetailedExpenditure` also need
  `User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium'])`.
- **A tier guard and a link guard both answer 403.** My first refusal test passed on the
  TIER guard and would have passed with the fix reverted. Make both parties premium so the
  403 under test is the right one.
- **`tests/Unit/Models` is not bound in `tests/Pest.php`.** A factory call there dies with
  "A facade root has not been set" — a test that cannot run, not one that fails. Bind in
  the file rather than widening the global binding.
- **Pest test files share one global namespace.** `linkedCouple()` and `ihtFor()` already
  exist elsewhere; a colliding helper is a fatal "Cannot redeclare", not a test failure.
- **`hasAcceptedSpousePermission()` FAILS OPEN on a missing permission row** — deliberate
  (W-0347 G9). Zero such rows on dev. Every spouse financial read now depends on that
  default being right, where before only the estate pooling did.
- **`spouse_permissions.status` is `enum('pending','accepted','rejected')`** — the docblock
  says "withdrawn". A test written from the docblock fails on a truncated-column error that
  reads like a code fault.
- **The persona's Manchester mortgage carries `joint 50%` on a `tenants_in_common 40%`
  property.** Do NOT make `mortgages.ownership_percentage` authoritative for existing rows
  when implementing W-0483 — it would move that household's liabilities £293,000 → £305,000
  and break a verified figure.
- **The board directory mixes filename conventions.** Early items are `W-0002.md`; later
  ones are `W-0002-some-slug.md`. A glob for `W-0002-*.md` silently matches nothing.

## Tech debt deferred

- **`getGiftTaxRate()` / `getTaperRelief()` are read from two config homes.** W-0526: the
  `fourteen_year_rule` block states `maximum_window: 14` and two 7-year lookbacks, while
  `FailedGiftTaxCalculator` derives the same window from the CLT block. **The behaviour is
  correct** — the two-window cumulation is implemented and cites IHTM14513. It is one rule
  with two configured homes, so it is a consolidation, not a gap.
- **52 unused private injections remain** outside the TaxConfigService cluster (from the
  29 August handover, untouched). Each needs the W-0520 judgement: dead, or a capability
  silently unwired.
- **`database/schema/mysql-schema.sql` is stale** — it predates this session's
  `gifts.trust_id` migration as well as the earlier enum widening. Migrations correct any
  database built from it, so it is wrong rather than harmful.
- **The gifting UI still offers edit and delete on a trust-owned gift** on web and `/m`.
  They now fail with a clear 422 rather than corrupting the band, but the control should
  not be there. Needs `trust_id` on `GiftResource` plus a change on both surfaces.
- **`TrustObserver` and `FamilyMembersController`:** whether writing into a
  `linked_user_id` account should also require reciprocity is an open, separate question.

## Branch and deploy state

- **Branch:** `chore/board-reconciliation-30-august`, tree clean, 0 unpushed.
- **`dev`** carries #750–#756. **`main`** unchanged; PR #736 still deliberately unmerged
  because merging it equals a release.
- **Migrations on `dev` not yet applied to csjones or production:**
  `2026_08_29_160000_add_trust_id_to_gifts_table` (additive, backfills) and the earlier
  `2026_08_29_110000_allow_estate_planning_in_user_assumptions_type`.
- **Nothing deployed this session.** csjones and production untouched.
