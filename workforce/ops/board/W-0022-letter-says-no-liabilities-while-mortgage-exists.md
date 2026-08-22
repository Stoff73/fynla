---
id: W-0022
title: Letter to loved ones tells the surviving spouse "No outstanding liabilities recorded" while a £65,000 mortgage exists
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0003-batch-b-estate-wills.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-21T09:05:00Z
claimed: 2026-08-21T09:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: []
prior_art_outcome: extend
constitution_refs: [05-perimeter, 07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **premium sweep** (local `localhost:8000`),
account **David Jones (primary)**, user id 16, tier premium.

**Surface:** desktop web, `/valuable-info?section=letter`.

Severity: **high** — not because the mechanism is exotic, but because of what this
document is. The letter exists to tell a grieving spouse what they must manage. A
confident, wrong "no liabilities" is worse than an empty section.

### Expected

Persona: David holds the HSBC mortgage on The Willows — £65,000 outstanding, joint
50%, his share £32,500 (`tests/Persona/peak_earners.md:154-166`). The letter's
liabilities section should name it, or at minimum not deny it.

### Actual

```
letters_to_spouse.liabilities_info = "No outstanding liabilities recorded."
```

While, on the **same page**, the Letter Consistency Checks panel says:

```
Important
You have 1 outstanding liability recorded, but your letter does not include
liability details.
Add liability and mortgage information to your letter so your spouse knows what
payments to manage.
```

So one panel asserts there are no liabilities and another, six inches above it,
asserts there is one. The live data agrees with the checker:

```
mortgages where user_id=16 or joint_owner_id=16 : 1   (HSBC, £65,000)
estate liabilities rows for user 16             : 0
```

### Root cause — auto-populated once, never refreshed

Timestamps make it unambiguous:

```
letters_to_spouse.created_at = 2026-08-20 22:51:06
properties.id 9 created_at   = 2026-08-20 22:58:55   (with its HSBC mortgage)
```

The letter row was auto-created **8 minutes before** the mortgage existed, and
`liabilities_info` was frozen with the text that was true at that instant. Nothing
recomputes it when financial data changes afterwards.

This matches the documented behaviour in the vault
(`fynlaBrain/Current State/UserProfile.md`): "Letter to Spouse auto-population happens
**only once at first creation** — if you add financial data to the household
afterward, the Letter does not refresh automatically."

Two things make it worse than a stale-cache annoyance:

1. **The letter row is created as a side effect of merely visiting the page.** Mine
   was created at 22:51 during a routing check that ended on the `/teaser` redirect —
   before any property, mortgage, investment or pension existed. Any user who opens
   the letter early gets a snapshot of an empty financial life permanently baked in.
2. **The frozen text is an assertion, not an absence.** "No outstanding liabilities
   recorded" reads as a checked fact. An empty section would be safer.

### Evidence

**No screenshot of the contradiction as a single frame** — the two panels are far
apart vertically. Both strings are quoted verbatim above from the live DOM, and the
DB row and the counts are quoted from tinker.

Report: `reports/R-05-premium-sweep.md`.

### Repro

1. Premium account with no financial data yet.
2. Visit `/valuable-info?section=letter` once — a `letters_to_spouse` row is created.
3. Now add a property with a mortgage.
4. Return to the letter. `liabilities_info` still reads "No outstanding liabilities
   recorded", while the consistency panel on the same page reports 1 outstanding
   liability.

## Acceptance

- [ ] Auto-populated sections are recomputed from live data on read, or refreshed when
      the underlying records change — not frozen at row creation.
- [ ] Sections with no data render as empty/"not yet recorded", never as a positive
      denial ("No outstanding liabilities recorded") unless that has just been
      verified against live data.
- [ ] `liabilities_info` counts **mortgages** as well as estate `liabilities` rows —
      the consistency checker already does, and the two must agree (Rule 20: one
      answer to "what does this household owe").
- [ ] Consider not creating the letter row on mere page view.
- [ ] Apply the same audit to the letter's other auto-populated sections —
      `beneficiary_info`, `children_education_plans`, `financial_guidance`,
      `immediate_funds_access` were all populated at the same frozen instant.
- [ ] `/m` and iOS letter surfaces checked (Rule 19).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

(append-only)

- 2026-08-21 persona-tester: found during the premium sweep. Not fixed by me — routed
  to build-lead.
- Recording what is **right** about this feature, because most of it is: the Letter
  Consistency Checks are genuinely good and caught two real gaps plus an executor
  mismatch between the letter and the will. Key contacts saved and survived a hard
  reload. The executor card correctly prefers the will's value and labels it "From
  Will". This item is the frozen auto-population only.
- Naming trap for whoever picks this up: the UI field labelled **"Solicitor"** is
  stored in the column **`attorney_name`** / `attorney_contact`
  (`LetterToSpouse.vue:136-143`). There is no `solicitor_*` column. Given the app also
  has a separate Power of Attorney feature, that name will mislead — I lost time to it.
  Not raised as its own item, but worth renaming if the file is being touched anyway.

- 2026-08-21 build-lead: **FIXED**, browser-verified. Handing to quality-lead.

  **Root cause confirmed as reported** — content frozen at row creation. Fixed by
  making Fynla-owned sections live rather than one-shot.

  New nullable column `letters_to_spouse.auto_populated_fields` (migration
  `2026_08_21_090100_...`) records which sections Fynla still owns.
  `LetterToSpouseService::getOrCreateLetter()` now recomputes every owned section
  on read and persists what changed; `updateLetter()` removes a section from the
  list the moment the user edits it, so **their words are never overwritten**.

  **Legacy rows are adopted conservatively.** A NULL list means a row predating
  the column. Only sections holding nothing, or one of the old generator's
  no-data sentinels ("No outstanding liabilities recorded.", "Note: Review which
  accounts are joint accounts..."), are adopted. Anything else might be the
  user's own typing, and a letter to a grieving partner is not the place to
  guess. No destructive backfill was run.

  **The positive denial is gone.** `generateLiabilitiesInfo()` returns `null`
  where there is nothing to report, not a sentence asserting the absence. A blank
  section reads as nothing recorded yet; a sentence reads as a checked fact, and
  the reader of this letter cannot ask us which it was.

  **One answer to what the household owes (Rule 20).** The letter's section and
  the consistency checker each ran their own count. Both now go through
  `LetterToSpouseService::outstandingLiabilities()` /
  `outstandingLiabilityCount()`; `LetterEstateValidationService.php:233` calls
  it. The old sentinel string is kept in the checker for letters written before
  this change.

  **Two further defects found while fixing this, both in the same section, both
  fixed.** The generator read column names that do not exist:
  - `$mortgage->lender` — the column is `lender_name`. Every mortgage was listed
    as "• Mortgage - " with no lender at all.
  - `$liability->creditor` and `$liability->outstanding_balance` — the columns are
    `liability_name` and `current_balance`. Every non-mortgage debt was listed
    with no name and a balance of **£0.00**.
  These are exactly what this item is about — the liabilities section telling the
  surviving partner something untrue — so they were fixed here rather than
  deferred.

  Also fixed: `createWithDefaults()` did not set the relation on `$user`, so a
  second `getOrCreateLetter()` call on the same instance attempted a duplicate
  insert against a unique `user_id`. Pre-existing, but the refresh-on-read makes
  repeated reads far more likely.

  **Browser evidence (localhost:8000, Playwright, real login + MFA).** Sequence
  reproduced exactly as the item describes:
  1. Letter created with no financial data → `liabilities_info` is **NULL**
     (previously "No outstanding liabilities recorded."), 11 sections owned.
  2. Property + HSBC mortgage £65,000 added afterwards.
  3. `GET /api/user/letter-to-spouse` live in the browser:
     ```
     "Outstanding Liabilities:\n\n• Mortgage - HSBC\n  Outstanding: £65,000.00\n
      Monthly Payment: £1,200.00\n  Account Number: [Please add]"
     ```
  4. `GET /api/estate/letter-validation` in the same session: **zero** warnings
     matching /outstanding/. The two panels no longer contradict each other.
  Throwaway users deleted; **David (16) and Sarah (17) not modified.**

  **Acceptance item "consider not creating the letter row on mere page view" —
  considered, deliberately not changed.** With recompute-on-read the early row is
  no longer harmful: it holds nothing, and it fills in as data arrives. Removing
  the creation would change the `show()` contract the frontend depends on for a
  gain the refresh already delivers.

  **The other auto-populated sections were audited, not just liabilities** — all
  eleven are in `AUTO_POPULATED_FIELDS` and all refresh:
  `immediate_actions`, `employer_hr_contact`, `immediate_funds_access`,
  `bank_accounts_info`, `investment_accounts_info`, `insurance_policies_info`,
  `real_estate_info`, `liabilities_info`, `beneficiary_info`,
  `children_education_plans`, `financial_guidance`.

  **`/m` and iOS:** neither has a letter-to-spouse surface (`/m` routes are only
  `/estate` and `/estate/bequests`; `ios-native/` has no letter screen). The fix
  is entirely server-side, so any future surface gets it for free.

  **Naming trap confirmed and NOT renamed** — the UI field "Solicitor" is stored
  in `attorney_name`/`attorney_contact`. Renaming a column while fixing a
  content bug is out of scope; raise it as its own item if wanted.

  Tests: `tests/Unit/Services/UserProfile/LetterToSpouseRefreshTest.php` — 6
  cases covering no-denial-on-empty, refresh after a later mortgage, never
  overwriting user text, legacy adoption, legacy user-text left alone, and the
  checker agreeing with the letter.

- 2026-08-21 build-lead: Rule 22 handover for this batch is
  `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` — it carries the dispatch
  verbatim, the full `tax-compliance-reviewer` verdict on W-0020 (§3), the approved
  `compliance-lead` + `design-lead` refusal copy for W-0019 verbatim (§3a), decisions
  taken, dead ends ruled out, and environment state. **Rule 14's loop is NOT closed by
  me on this item** — see §8; the browser evidence recorded above is my own, gathered
  before the no-self-verification policy landed, and needs independent re-verification.
