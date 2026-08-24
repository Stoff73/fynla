---
id: W-0394
title: Every bequest is stored as a gift to a person — both charitable legacies included, because beneficiary_type reaches no request class
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0029-cycle4-wills-and-estate-figures.md
owner: build-lead
reviewers: [quality-lead, tax-compliance-reviewer]
status: gated
severity: medium
surfaces: [web, m]
created: 2026-08-23T00:55:00Z
claimed: 2026-08-23T00:55:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-23
prior_art_found: [W-0020, W-0046, W-0132]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, cycle 4, alongside D-14.

### The consequence first, because it is a real loss to a real user

**A charitable legacy that the name list does not recognise is worth nothing to
the charitable total that decides the reduced Inheritance Tax rate.**

Every bequest was stored as `beneficiary_type = individual`. `isCharitable()`
rescued the ones whose names happen to contain a word from its indicator list —
`cancer`, `heart`, `foundation` — which is why this household's two legacies were
classified correctly despite being stored wrongly. **A charity outside that list
had no such rescue:** *Guide Dogs for the Blind Association*, an air ambulance, a
hospice trust registered under a family surname. It is an individual in the
database and an individual to `getCharitableBequestTotal()`.

So a user records a £50,000 gift to charity, the application shows it in their
will, and **the 36% reduced rate it should qualify them for silently does not
follow.** Nothing on any screen says why. On the peak_earners household the
difference between 40% and 36% is worth **£34,351**, a figure the estate screen
itself quotes.

**The validation gap below is the cause. The paragraph above is the defect.**

### Actual

```
bequests.id 51  user 16  Cancer Research UK        beneficiary_type = individual
bequests.id 50  user 17  British Heart Foundation  beneficiary_type = individual
```

`bequests.beneficiary_type` is `enum('individual','charity','trust',
'organization') NOT NULL DEFAULT 'individual'`.

### Root cause — two write paths, neither of which wrote it

1. `StoreBequestRequest` and `UpdateBequestRequest` listed neither
   `beneficiary_type` nor `charity_registration_number`, so `validated()`
   dropped both and the column took its default. This is axis 2 of
   `app/Http/CLAUDE.md`'s rule-vs-schema list — fillable, offered, silently
   stripped.
2. `WillDocumentService::syncBequests()` never set it either, so gifts entered in
   the will builder took the default too. Both of this household's rows came from
   that path (`will_document_id` 5 and 6).

`Bequest::isCharitable()`'s own docblock already recorded the consequence — "no
write path in the application populates beneficiary_type" — as a known
limitation. It was accurate.

### Why nothing looked broken, and why that is the danger

`isCharitable()` re-derives the answer from the beneficiary NAME on every read,
and its indicator list happens to contain `cancer` and `heart`. **So both of this
household's charities were classified correctly despite being stored wrongly.**

A charity the list does not name has no second chance: *Guide Dogs for the Blind
Association*, an air ambulance, a local hospice trust registered under a family
surname. It is an individual in the database and an individual to
`getCharitableBequestTotal()` — which is what decides whether the estate reaches
the reduced Inheritance Tax rate. **The user records the legacy and the tax
relief silently does not follow it.**

### What else keys off `beneficiary_type`

Checked across `app/` and `resources/`. Exactly one reader:
`Bequest::isCharitable()` (`app/Models/Estate/Bequest.php:90`). Nothing else — no
resource, no service, no frontend. So the blast radius of correcting the stored
value is the charitable total and the Inheritance Tax rate, and neither moved on
this household because the name fallback was already producing the right answer.

## Fix

One home for the classification, two callers:

- `Bequest::nameLooksCharitable()` — the indicator list, extracted from inside
  `isCharitable()` where it had no other caller. `trust` remains deliberately
  absent (a gift into a family trust is a chargeable transfer, not an exempt one).
- `Bequest::inferBeneficiaryType()` — fills a caller's silence with the same
  judgement `isCharitable()` would reach, so the stored row and the derived
  answer cannot disagree.
- `WillController::classifyBeneficiary()` on create and update. An explicit type
  from the caller always wins; the name must be in the payload for anything to
  happen, so editing an amount never reclassifies a beneficiary behind the user's
  back.
- `WillDocumentService::syncBequests()` classifies through the same helper.
- Both request classes now accept `beneficiary_type` and
  `charity_registration_number`.

### Data repaired

`estate:backfill-bequests --user=16 --force` and `--user=17 --force`, through the
**existing** command rather than a hand edit. Both rows now
`beneficiary_type = charity`. Charitable total £10,000 → £10,000, rate 40% → 40%
on both — **no user-visible figure moved**, which is the correct outcome.

## Acceptance

- [x] Both write paths store the classification.
- [x] An explicitly stated type is believed over the name.
- [x] A charity the name list cannot recognise can be recorded as one.
- [x] Renaming a beneficiary reclassifies; editing an amount does not.
- [x] Every consumer of `beneficiary_type` enumerated — there is one.
- [x] Persona rows repaired through the sanctioned command, before/after figures
      recorded.
- [x] Mutation-tested in both directions (rules removed; controller call removed)
      — each turns a disjoint subset red.
- [ ] `tax-compliance-reviewer` to confirm the indicator list is a defensible
      fallback for the reduced-rate test, given it now also decides what is
      STORED rather than only what is derived.


### Browser verification — 2026-08-23, localhost:8000, Playwright

**Tab established as nobody** on arrival (both token stores empty) — checked
rather than assumed, and it was the state team-lead warned about. Logged in
through the real form on each account and confirmed identity with
`GET /api/auth/user` before reading anything: **id 16 David Jones**, then
**id 17 Sarah Jones**. `estate_analysis_16` / `_17` cleared by hand before each
read (W-0381).

Read verbatim off `/estate/will-builder`:

| | David (16) | Sarah (17) |
|---|---|---|
| Spouse line | `100% of your own estate to your spouse (£989,500)` | `100% of your own estate to your spouse (£739,280)` |
| Executors | Sarah Jones · Barclays Wealth | **David Jones** · Barclays Wealth |
| Specific Gifts | `£10,000 to Cancer Research UK` | `£10,000 to British Heart Foundation` |
| Residuary | Sarah Jones — 100% | David Jones — 100% |

The two estate figures **differ**, each is its owner's, and **neither £1,728,780
nor £1,716,780 appears anywhere on either page**. Nobody is their own executor.
Every gift names its recipient.

Screenshots:
`tests/Persona/20-08-2026_run/pass-a-web/150-web-david-will-own-estate-989500-executor-sarah-gift-named-W-0391.png`
`tests/Persona/20-08-2026_run/pass-a-web/151-web-sarah-will-own-estate-739280-executor-david-gift-named-W-0391-W-0393-W-0395.png`

## Working notes

- 2026-08-23 build-lead: fixed. `tests/Feature/Estate/BequestBeneficiaryTypeTest.php`.
  Not self-certified — handed to quality-lead.
- **Left standing deliberately:** `BequestForm.vue` still offers no way to say
  "this is a charity", so a user whose charity the name list does not know cannot
  state it through the web form — only through the API. Adding a field to that
  form is a design change outside this batch's scope. Flagging, not building.
