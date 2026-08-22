---
id: W-0023
title: Will builder specific gifts never become Bequest rows — invisible to the Estate module and to the charitable IHT rate
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0003-batch-b-estate-wills.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-21T08:45:00Z
claimed: 2026-08-21T09:40:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21
prior_art_found: []
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **premium sweep** (local `localhost:8000`),
account **David Jones (primary)**, user id 16, tier premium.

**Surface:** desktop web, `/estate/will-builder` → step 5 "Gifts".

Severity: **high**. The gift is displayed back to the user inside a generated legal
document, so it looks recorded. Everything downstream sees nothing.

### Expected

Persona file `tests/Persona/peak_earners.md:540-544` — David's will bequests:

| Beneficiary | Type | Amount/% | Priority |
|---|---|---|---|
| William Jones | Percentage | 50% | 2 |
| Charlotte Jones | Percentage | 50% | 2 |
| **Cancer Research UK** | **Specific Amount** | **£10,000** | **1** |

Entering the £10,000 charitable legacy in the will builder should create a `Bequest`
row so the Estate module, `WillAnalysisService` and the `/m` bequests screen all see
it.

### Actual

The will completes successfully and the generated document renders the gift
correctly:

```
SPECIFIC GIFTS AND LEGACIES
4. I GIVE AND BEQUEATH the following:
   (a) The sum of £10,000 to Cancer Research UK.
```

`POST /api/estate/will-builder/5/complete` → 200,
`"message":"Will document completed and saved."`

The `Will` row is created correctly:

```
wills.id = 11
has_will                    = true
spouse_primary_beneficiary  = true
spouse_bequest_percentage   = 100.00
executor_name               = 'Sarah Jones, Barclays Wealth'
will_last_updated           = 2026-08-21
```

But:

```
bequests for user 16 : 0        <-- expected at least the £10,000 charitable legacy
bequests in db total : 14       (all seeded preview users)
```

The gift lives only as JSON on the will-builder document:

```
will_documents.id = 5, will_id = 11, status = 'complete'
specific_gifts = [{"type":"cash","amount":10000,"conditions":null,
                   "description":null,"beneficiary_name":"Cancer Research UK"}]
```

`will_documents.will_id = 11` proves the association exists — the sync simply never
happens.

### What breaks downstream

- **The reduced 36% IHT rate cannot be triggered from the will builder.**
  `WillAnalysisService::getCharitableBequestTotal()`
  (`app/Services/Estate/WillAnalysisService.php:88-112`) iterates `$will->bequests`.
  With none, the total is £0. Verified live after completing the will:

  ```
  charitable_giving_percent = 0
  charitable_deduction      = 0
  iht_rate                  = 0.4
  iht_rate_message = "Standard IHT rate of 40% applies. Leave 10%+ of your
                      baseline estate (£409,280) to charity to qualify for the
                      reduced 36% rate."
  ```

  The user has just recorded a charitable legacy and the estate page still tells them
  they have not.
- **Estate bequest CRUD** (`GET/POST/PUT/DELETE /api/estate/bequests/{id}`) shows
  nothing.
- **`/m` bequests screen** (`/m/app/estate/bequests`, `MobileEstateBequests`) has
  nothing to render.

**See also W-0020** — even a bequest created through the proper API is not counted
toward the charitable total, because of a separate enum mismatch. Both must land for a
charitable legacy to actually reduce the rate.

### Evidence

Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/11-web-david-will-builder-review-GREEN.jpg`
— the review step showing the will generated correctly, gift included.

Report: `reports/R-05-premium-sweep.md`.

### Repro

1. Premium account with a spouse and children.
2. `/estate/will-builder` → work through to step 5 "Gifts" → Add a Gift →
   beneficiary "Cancer Research UK", type Cash Amount, £10,000.
3. Continue to Review — the document correctly shows "The sum of £10,000 to Cancer
   Research UK". Click **Complete & Finalise**.
4. `SELECT * FROM bequests WHERE will_id = <the new will>` → **empty**.
5. `will_documents.specific_gifts` holds the gift as JSON.
6. `/estate` still shows the 40% rate and prompts for a charitable gift.

## Acceptance

- [ ] Completing a will syncs `will_documents.specific_gifts` into `bequests` rows
      with the correct `bequest_type`, `specific_amount` / `percentage_of_estate`,
      `beneficiary_name`, `priority_order` and `conditions`.
- [ ] Residuary beneficiaries are considered too — decide deliberately whether they
      become `Bequest` rows or stay document-only, and document the decision.
- [ ] Re-editing and re-completing a will updates rather than duplicates bequests.
- [ ] After entering the persona's £10,000 charitable legacy,
      `getCharitableBequestTotal()` returns 10000 (needs **W-0020** as well).
- [ ] The `/m` bequests screen shows gifts entered via the will builder (Rule 19).
- [ ] One source of truth for "what is bequeathed" — the Estate module and the will
      builder must not hold divergent answers (Rule 20).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

(append-only)

- 2026-08-21 persona-tester: found during the premium sweep, after team-lead
  provisioned premium. Root cause located; not fixed by me — routed to build-lead.
- The will builder itself is otherwise **good**: personal details correctly prefilled
  from the profile, minor-child detection correct (listed Charlotte 16, excluded
  William 18), a useful "children under 18 but no guardian" warning, and sound legal
  prose. This item is the persistence gap only.

- 2026-08-21 build-lead: **FIXED**, browser-verified. Handing to quality-lead.

  **Fix.** `WillDocumentService::syncBequests()`
  (`app/Services/Estate/WillDocumentService.php:480`), called from
  `markComplete()`. Cash gift → `bequest_type: specific_amount` + `specific_amount`;
  item gift → `specific_asset` + `specific_asset_description`; plus
  `beneficiary_name`, `conditions` and a sequential `priority_order`.
  Cache invalidated for the user afterwards, so the estate analysis recomputes.

  **Idempotency needed a marker.** New nullable column
  `bequests.will_document_id` (migration
  `2026_08_21_090000_add_will_document_id_to_bequests_table.php`). Rows the sync
  wrote are `forceDelete`d and rewritten on each completion — force, not soft,
  because "replaced" has to mean replaced and these rows hold no history the
  document does not already hold. Rows created by hand through the Estate bequest
  API carry NULL and are never touched.

  **Double-count vector found by tax-compliance-reviewer and closed.** Adopting
  hand-made rows was not in my first cut: a user who recorded "£10,000 to Cancer
  Research UK" through the bequest API and then entered the same gift in the will
  builder would have ended up with TWO live rows, and `getCharitableBequestTotal`
  sums both — a doubled total can push someone onto the reduced Inheritance Tax
  rate they have not earned, which is the unsafe direction. `syncBequests` now
  matches an existing NULL-document row on (beneficiary, type) via
  `isSameParty()` and updates it instead of adding. This mattered because
  `PreviewUserSeeder` creates exactly such hand rows for every persona.

  **Residuary beneficiaries stay document-only — decided, not overlooked.** The
  `bequests` table cannot express "a share of what is left after the gifts": a
  residuary row could only be stored as `percentage`, and
  `Will::getNonSpouseAllocationPercentage()` sums exactly those rows — so a
  mirror will leaving 100% to a partner would report a 100% NON-partner
  allocation. Recording it there would corrupt an existing answer to buy a
  duplicate of one the document already holds. Reasoning is in the method
  docblock and pinned by a test.

  **Browser evidence (localhost:8000, Playwright, real login + MFA).** Full
  wizard driven on a throwaway married pair; entered "Cancer Research UK", Cash
  Amount, £10,000; clicked Complete & Finalise. Database immediately after:
  ```
  will_documents.status = complete
  wills.executor_name   = 'Beth Hall'
  BEQUEST id=17 name=Cancer Research UK type=specific_amount amount=10000.00
          will_document_id=8 charitable=yes
  getCharitableBequestTotal() = 10000        (was 0 — needed W-0020 too)
  ```
  `GET /api/estate/bequests` — the exact endpoint `/m`'s `EstateBequests.vue:87`
  calls — returned the row, so the `/m` bequests screen has content (Rule 19).
  The throwaway users were deleted afterwards; **David (16) and Sarah (17) were
  not modified.**

  **Known, deliberate gap for the persona re-test.** Wills ALREADY completed under
  the old code do not backfill — David and Sarah still show `bequests = 0`,
  because the sync runs on completion. Re-completing their wills will populate
  them. I did not re-complete them: persona-passA is mid-run and the instruction
  was not to disturb that data. Whether a one-off backfill command is wanted for
  existing completed wills is a CSJ call, adjacent to W-0019 acceptance 6.

  **Charity detection is name-based and that is a real weakness** — see W-0020,
  finding F9. `beneficiary_type` and `charity_registration_number` exist on the
  table but NO write path populates them, and `SaveWillDocumentRequest.php:49-54`
  has no charity field on a gift, so the £10,000 above qualifies only because
  "Cancer Research UK" contains "cancer". Reported, not fixed — it needs a form
  field and a product decision.

  Tests: `tests/Unit/Services/Estate/WillDocumentServiceTest.php` — 4 new cases
  under "completing a will records its gifts as bequests (W-0023)", covering the
  charitable cash legacy, re-completion not duplicating, hand-made rows
  surviving, and residuary staying document-only.

- 2026-08-21 build-lead: Rule 22 handover for this batch is
  `workforce/branches/fixes/F-0003-batch-b-estate-wills.md` — it carries the dispatch
  verbatim, the full `tax-compliance-reviewer` verdict on W-0020 (§3), the approved
  `compliance-lead` + `design-lead` refusal copy for W-0019 verbatim (§3a), decisions
  taken, dead ends ruled out, and environment state. **Rule 14's loop is NOT closed by
  me on this item** — see §8; the browser evidence recorded above is my own, gathered
  before the no-self-verification policy landed, and needs independent re-verification.
