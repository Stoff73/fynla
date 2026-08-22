---
id: W-0032
title: scheme_status is collected by both pension forms and silently discarded on every save — no such column exists
mission: M-0002-persona-fidelity
owner: build-lead
claimed_by: fix-batch-E
status: handoff
handoff_to: quality-lead
branch: branches/fixes/F-0004-batch-e-retirement-income.md
severity: medium
surfaces: [web, m, ios]
source: found by fix-batch-C while fixing W-0017, 2026-08-21; only unblocked there, not resolved
prior_art_checked: 2026-08-21
prior_art_outcome: extend
---

## Intent

Both Defined Benefit pension forms collect `scheme_status`. There is no `scheme_status`
column on `db_pensions`, so the value is discarded on every save. The user answers a
question that goes nowhere.

fix-batch-C stopped it **blocking edits** (it was required on a form whose value could
never persist) but deliberately did not resolve it: whether the field should exist at
all is a Retirement-module decision, not a fix-batch one.

## Acceptance

Decide one of two, and apply it everywhere including Fyn's catalogue:

- **Give it a column** — if scheme status (active / deferred / in payment) genuinely
  drives projections or advice, it needs to persist and be used.
- **Take it off the forms** — if nothing consumes it, asking for it is friction that
  produces a silent discard.

Not both, and not left as-is: a collected-and-discarded field is the same disease as
W-0006 and W-0026 (policy end date accepted and dropped) — the app telling the user it
recorded something it did not.

---

### fix-batch-E — 2026-08-21, handed to quality-lead

Branch document: `workforce/branches/fixes/F-0004-batch-e-retirement-income.md`.

Outcome applied: **give it a column**, per CSJ's ruling and batch C's recommendation
(F-0001 §"Answer for W-0032"). Not re-raised as a decision.

**Migration.** `database/migrations/2026_08_21_180000_add_scheme_status_to_db_pensions_table.php`,
applied with `php artisan migrate --path=…` alone. `db_pensions.scheme_status` —
`string(20)`, nullable, no default, after `scheme_type`, with a column comment
recording the vocabulary and what null means. **Deliberately not backfilled:** every
existing row has an unknown status, and guessing one would invent the fact the column
exists to record. `db_pensions.id 4` is untouched and still NULL, so it continues to
exercise the age fallback as batch C's acceptance fixture requires.

**Vocabulary.** Stored `active` | `deferred` | `in_payment`, declared once at
`DBPension::SCHEME_STATUSES` and read by `PensionNormaliser`,
`PensionStore::validateDbCanonical()` and `StoreDBPensionRequest` rather than retyped.
Snake_case because every other enum in the app is — `scheme_type` is `final_salary`,
`inflation_protection` is `cpi`, `investment_accounts.scheme_status` (a *different*
column, employee share schemes) is lowercase. The title-case forms the two Defined
Benefit forms display, and that Fyn's tool schema declares, are labels; mapping them
costs three lines in one place, a per-table convention costs forever.

**One mapping, one place.** `PensionNormaliser::normaliseSchemeStatus()`, reached from
all four inbound paths: `fromFormDb()`, `fromFynPension()`, `fromUploadDb()`, and the
new public `normaliseDbFields()` — added because `update_record` hands `PensionStore` a
bare field list and passes through none of the `from*` methods. Without it,
`update_record` would have been the one writer with its own idea of "In Payment"
(Rule 20). An unrecognised value maps to null, never to a guess; null is meaningful, it
is what `isInPayment()` reads as "fall back to age".

**The loop batch C left open is closed.** `app/Models/DBPension.php:116-131` — a stated
status wins in **both** directions: "In Payment" is income regardless of age, "Active"
or "Deferred" is not income even past the scheme age. The age-vs-Normal-Retirement-Age
heuristic remains for rows that predate the column. Both directions are pinned by test,
because both are wrong under the heuristic in cases common in Fynla's audience —
drawing early at 57 against a scheme age of 60, deferring at 62 past a scheme age of 60.

**Applied everywhere, including Fyn's catalogue.**
- `DBPension::$fillable`.
- `UpdateRecordAllowlist` `db_pension` — without it, `/m` and native users could state
  a status once through Fyn and never correct it, the exact gap W-0017 closed for the
  other four Defined Benefit fields.
- `dbPensionFields.js` — `DB_SCHEME_STATUS_OPTIONS`, `formatSchemeStatus()`, and
  `scheme_status` in `buildDbPensionPayload()`. Both forms read the shared options and
  send the value; `DBPensionForm` restores it on edit so a re-save cannot silently
  clear it, and its "cannot persist" guard is gone.
- `PensionDetail.vue:195` — was `pension.scheme_status || 'Active'`, so it displayed
  "Active" for every Defined Benefit pension ever saved. Now the shared mapper, which
  reads unset as "Not recorded".
- `resources/mobile/views/modules/RetirementPensionDetail.vue` — a Scheme status row
  importing the **same** `formatSchemeStatus`, not a copy (`/m` already imports
  `ownership.js` that way; the module is pure JavaScript with no Vue or store
  dependency).

**Fyn's xAI tool schema was NOT re-recorded.** `create_pension.xai.md` already declares
`scheme_status` with the title-case enum. Editing it means re-recording a byte-identity
golden master, for a mapping that costs three lines in the normaliser. The corpus is
untouched.

**Noticed, deliberately not fixed.** `fyn-memory/procedural/tool_schema/savings/create_pension.md`
— the Anthropic provider copy — lacks `scheme_status` entirely. The app runs xAI, so
the live catalogue is correct and this is dormant, pre-existing drift. Left rather than
edited blind. Recommend folding into whatever sweep reconciles the two providers.

**Tests.** `tests/Feature/Retirement/DbPensionSchemeStatusTest.php` (new) — 10 passed,
24 assertions: persistence through the HTTP endpoint, edit-preserves-omitted, invalid
rejected, Fyn's title case mapped, unrecognised dropped, both heuristic directions, the
age fallback for legacy rows, the income actually moving in
`UserProfileService::getCompleteProfile()`, `update_record` correcting it, and the value
reaching `GET /api/retirement` for `/m` and native. `DbPensionFields.spec.js` 9 passed.
Pint clean.

**Correction, same day — the vocabulary moved off the model.** It was first declared as
`DBPension::SCHEME_STATUSES`. `--testsuite=Architecture` rejected that, correctly:
`tests/Architecture/StoreBoundary/PensionStoreBoundaryTest.php` is a LOCKED allowlist
and `App\Models\DBPension` may only be referenced from the canonical pension write/read
set, which a form request is not part of and should not be. It was reported to me as an
unused import; it was not — the reference was live at `StoreDBPensionRequest.php:46`,
and finishing the usage would have left the suite just as red. Adding the request to the
allowlist would have weakened a boundary whose own docblock says a red suite means the
entry is load-bearing, "not that the rule should be weakened". The list now lives at
`app/Constants/PensionEnums.php`, the same shape as `ProfileEnums` (which a form request
already consumes), and `DBPension`, `PensionNormaliser`, `PensionStore` and
`StoreDBPensionRequest` all read it without any of them touching a pension model.
`PensionNormaliser` lost its model dependency entirely as a side effect.
`--testsuite=Architecture` now 148 passed; the 3 remaining failures are other batches'
(`app/Http/Requests/Concerns/ValidatesSharedOwnership.php`, untracked, ×2; and
`SavingsController.php:369` querying `SavingsAccount` directly). Pension families
re-run green after the move.
