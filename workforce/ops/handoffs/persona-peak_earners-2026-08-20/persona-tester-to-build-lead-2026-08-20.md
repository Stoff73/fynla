# Persona run `peak_earners` Pass A — persona-tester → build-lead

Covers W-0006 … W-0016 (eleven items, all routed to build-lead). One note for the
batch because they were found in a single run and several share a root cause.

## Done

- Registered and fully linked a real two-account household on local
  (`localhost:8000`): David Jones (id 16) and Sarah Jones (id 17),
  `users.spouse_id` reciprocal, `SpousePermission` accepted **both ways**, reciprocal
  `FamilyMember` rows, two dependent children.
- Entered every persona record the free tier and the current UI allow, and verified
  each write against the MySQL row — not against the screen.
- Verified the entered household on **web and `/m`, from BOTH accounts**.
- Recomputed every line of the household wealth summary by hand against the persona
  file and the DB.

## The four items to fix together

W-0013, W-0014, W-0015 and W-0016 are all "joint ownership", and fixing them
separately will re-create the divergence. Three modules currently implement one
concept three ways:

| Module | Joint save | Result |
|---|---|---|
| Property | coerces an explicit 100 → 50 (`PropertyController.php:154-158`) | correct 50/50 |
| Investment | leaves 100 (`InvestmentController.php:346-350`) | 100/0 |
| Savings | hard-rejects, form never sends the field | cannot save at all |

…and three mechanisms compute the resulting share (`CalculatesOwnershipShare`
trait, `InvestmentList.vue:86-87` client-side, `InvestmentController.php:972`
server-side). Rule 20 says the fix is one home all three read, not three edits.

**Do not keep the `100 → 50` fallback at `CalculatesOwnershipShare.php:73`.** It is
what hid W-0014: the wealth summary looked right while the stored value was wrong. It
also makes a genuine 100/0 joint split inexpressible.

## What I already ruled out, so you don't repeat it

- **Not a browser/tooling artefact.** Every finding was confirmed against the DB row
  via tinker, and the silent ones (W-0009, W-0011) were confirmed by hooking
  `XMLHttpRequest` in the live page and reading the actual request body and status.
- **W-0009 is not a validation failure.** `UpdateHoldingRequest` accepts all those
  fields; the payload never leaves the browser (`PUT` with a `null` body, 200 back).
  It is a caller/action key mismatch, and `createHolding` is NOT affected.
- **W-0007 is not a backend fault.** `ISATracker::getISAAllowanceStatus` returns the
  correct `cash_isa_used = 10000`; only the investment page's store read is wrong.
- **The £125,140 additional-rate threshold on `/m` is NOT a hardcoded-tax defect.**
  It resolves from `TaxConfigService`; the `?? 125140` occurrences are post-read
  fallbacks. I checked before raising, and did not raise it.
- **The vault's claim that `app/Services/Retirement/ContributionOptimizer.php` has
  hardcoded tax bands is stale** — no such file exists. `PensionContributionOptimizer`
  reads from config. `Current State/Retirement.md` should be corrected.

## Assumptions I made (stated as assumptions)

- That the joint records in the persona should be owned primarily by **David** with
  Sarah as `joint_owner_id`, since the persona file names no primary owner and David
  is the primary account. If that is wrong, the ownership columns need re-entering.
- That the persona's "Remaining Term 156 months" for The Willows mortgage should be
  expressed as a maturity date of 2039-08-20 (exactly 156 months from the run date),
  since the wizard offers a date and not a term. W-0012 is written on that basis.
- That entering the NHS 2015 career-average scheme as `final_salary` was the only
  option available, not a correct classification.

## Surfaces covered / not covered

**Covered:** desktop web and `/m`, both accounts, for everything entered.

**NOT covered — say so plainly:**
- **iOS: I COULD NOT TEST THIS.** Out of scope for this dispatch by instruction — the
  native app reads the csjones staging database and cannot see local data.
- **csjones/dev: not touched.** Local only, as instructed. No PRs, no deploys.
- Anything behind the premium gate or the free-tier count caps — see the report.

## What I did NOT do

- I did not fix any of it. Diagnosis only, per the routing table.
- I did not grant a subscription tier, patch a DB row, or edit `.env` to get past the
  free-tier caps. That decision is CSJ's / team-lead's and is the run's open blocker.
- I did not tear down the test users — the household is left in place for inspection,
  as instructed.

---

## Addendum, 2026-08-21 — stand-down and priority order

Pass A is **halted by CSJ decision**, not finished. It restarts from zero on genuine
Playwright MCP after this batch lands. The entry work is disposable; these items are
the durable output.

**W-0017 added** — the four Defined Benefit form gaps folded into one item at
team-lead direction.

### Fix in this order

**Tier 1 — a faithful Pass A is impossible until these land:**

1. **W-0013 + W-0014 + W-0015 together.** One concept, three module implementations,
   three share calculations. Fixing any one alone re-creates the divergence. This is
   the run's whole reason for existing: joint ownership rendered correctly per owner.
   Land W-0014 first (storage), then remove the `100 → 50` fallback at
   `CalculatesOwnershipShare.php:73` as part of W-0015 — it is what hid W-0014.
2. **W-0011** — expenditure. Blocks emergency fund, protection gap, cashflow surplus
   and goal affordability. Four modules render LOCKED without it.
3. **W-0010** — the pension dead-end. Blocks State Pension entry outright.
4. **W-0009** — holdings. Blocks all 10 persona holdings' ticker/ISIN/units/prices/OCF.
   One-line fix in the store; highest value-per-effort in the batch.

**Tier 2 — real defects, but entry can proceed without them:**

W-0007 (high severity, but the ISA over-subscription guard does not block *entering*
data) · W-0006 · W-0008 · W-0012 · W-0016 · W-0017.

### Two things to know before you start

- **The household is live.** Users 16 and 17 are in place with real rows; every item
  quotes actual ids (`investment_accounts.id 14`, `db_pensions.id 4`,
  `holdings.id 32`, `savings_accounts.id 26/27/28`, `properties.id 9`,
  `mortgages.id 8`). Reproduce against those before changing anything.
- **Seven items have no screenshot** — all found during entry, which predates the
  run's capture rule. Their evidence is captured HTTP pairs, verbatim DB rows, or DOM
  enumerations, which is stronger for silent failures anyway. Each item says so
  explicitly in its Evidence section; none was retro-fabricated.

---

## Addendum 2, 2026-08-21 — premium sweep, five more items, revised ranking

Premium was provisioned **by team-lead** (not by me) and the newly-unlocked estate
surfaces were swept for defects. Five new items: **W-0018, W-0020, W-0021, W-0022,
W-0023**.

Numbering note: `W-0019` on the board is CSJ's mirror-wills direction, raised by
another agent at 08:32 while this sweep ran. It took the number first; my
will-builder item was renumbered to **W-0023**.

### Revised order — 17 items now

**Tier 1 — a faithful Pass A is impossible until these land:**

1. **W-0013 + W-0014 + W-0015 together.** Joint ownership, three modules, three share
   calculations. The run's whole purpose. Land W-0014 (storage) first, then delete the
   `100 → 50` fallback at `CalculatesOwnershipShare.php:73` as part of W-0015 — that
   fallback is what hid W-0014.
2. **W-0010** — pension dead-end; blocks State Pension entry outright.
3. **W-0009** — blocks all 10 persona holdings. One-line fix in the store.
4. **W-0023** — blocks all 6 persona bequests from ever reaching the `bequests` table.

**Tier 2 — real, entry proceeds without them:**

W-0020 (pair with W-0023 — a charitable cash legacy fails twice, and neither fix works
alone) · W-0022 · W-0007 · W-0006 · W-0008 · W-0011 (confirmed tier-gated; premium
saves correctly) · W-0012 · W-0016 · W-0017 · W-0021 · W-0018 (gated on CSJ).

### What the sweep found that is RIGHT — do not "fix" these

Recorded because a fix batch this size risks collateral damage:

- **IHT is correct to the pound.** Gross £1,124,280, liabilities £65,000, net
  £1,059,280, NRB×2 £650,000, RNRB×2 £350,000, taxable £59,280, liability £23,712.
  DC pensions correctly excluded; the 2027 pension amendment modelled separately and
  correctly. All allowances from `TaxConfigService`.
- **The charitable 36% baseline is right**, including the subtle part: £409,280 =
  net estate − NRB, correctly **excluding RNRB**. 10% = £40,928.
- **The holistic-plan pension recommendation is exact**: £36,800 headroom
  (£60,000 AA − 16% of £145,000) and £19,101 saving, including the Personal Allowance
  taper reclaim. I reproduced both by hand.
- **Financial commitments split joint costs correctly** — £1,250 property cost, £625
  each, counted once at household level. This is the behaviour W-0014/W-0015 should
  match.
- Will builder prefill, minor-child detection, guardian warning, legal prose; letter
  consistency checks; LPA copy.

### Two false positives I chased and did NOT raise

Recording so nobody re-investigates:

- `letters_to_spouse` has no `solicitor_*` column — the UI field labelled "Solicitor"
  is stored in **`attorney_name`/`attorney_contact`** (`LetterToSpouse.vue:136-143`).
  Confusing name, not a bug. Noted inside W-0022.
- 14 of 15 expenditure categories appeared to drop on save. That was **my** rapid
  synchronous input loop racing the component — with per-field delays all 15 persist
  and total £2,450 correctly.

---

## Addendum 3, 2026-08-21 — mirror will test (CSJ interrupt, W-0019)

CSJ interrupted the sweep to direct that married users get **mirror wills only**
(W-0019, raised by team-lead). Simple-will testing stopped; the mirror path was tested
end to end instead. One new item: **W-0024**, high.

### Fix W-0024 *before* W-0019, or with it

W-0019 makes mirror wills the **only** instrument for married users. W-0024 says the
mirror generator produces a legally incoherent document: it swaps the residuary
correctly but copies `executors` verbatim, so the spouse's will appoints **the spouse
herself** as executor, describing her as "my Spouse".

Shipping W-0019 alone converts that from an option a user might pick into the
**mandatory** outcome for every married couple. Land W-0024 first, or together.

`WillDocumentService.php` already contains the right pattern —
`swapResiduaryForMirror` at `:291` works correctly. Executors need the same treatment,
not a new mechanism.

### Also in W-0024

- **Charity legacy seeded wrong:** `specific_gifts` copied verbatim (`:311`), so the
  spouse's will pre-fills with the *other* spouse's charity. It IS editable — I proved
  that by changing it to British Heart Foundation and regenerating — so it is a silently
  wrong default, not an impossibility. That distinction only emerged from testing live.
- **No Guardians step on the spouse's will**, because the children are `FamilyMember`
  rows on the primary's account. A guardian appointment only bites when both parents
  are gone, so it belongs in both wills of a mirror pair.

### W-0023 reproduces on the mirror path

After both wills completed: `bequests` = **0** for both. Neither charitable legacy
reaches the `bequests` table. The gift form also has no priority field, so the
persona's priority ordering (charity 1, children 2) has nowhere to live even once the
sync exists.

### Evidence bearing on CSJ's two open questions — offered, not answered

1. **"A spouse who will not engage."** The mirror is created `status: 'draft'`,
   `will_id: NULL`, and only the spouse can complete it from her own login. The pair
   can sit indefinitely as one complete will plus one orphan draft, with **neither
   party shown that state**. Under mirror-only this becomes the default failure mode,
   not an edge case.
2. **"Existing one-sided wills."** There is a concrete instance on the system:
   `will_documents.id 5` was created `will_type = "simple"` for a married user, then
   converted **in place** to `will_type = "mirror"` when the builder was re-entered and
   the type changed — same row, no data loss. So migration is mechanically possible.
   Whether it is desirable is CSJ's call.
