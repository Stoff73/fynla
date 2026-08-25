# W-0032 — build-lead (fix-batch-E) → quality-lead

## Done

`scheme_status` has a column, a single vocabulary, and a purpose.

- **Migration:** `database/migrations/2026_08_21_180000_add_scheme_status_to_db_pensions_table.php`
  — `string(20)`, nullable, no default, after `scheme_type`, applied with
  `php artisan migrate --path=…` alone.
- **Vocabulary:** `active` | `deferred` | `in_payment`, declared once at
  `DBPension::SCHEME_STATUSES`, read by `PensionNormaliser`,
  `PensionStore::validateDbCanonical()` and `StoreDBPensionRequest`.
- **One mapping:** `PensionNormaliser::normaliseSchemeStatus()`, reached from
  `fromFormDb()`, `fromFynPension()`, `fromUploadDb()` and the new public
  `normaliseDbFields()` (for `update_record`, which bypasses the `from*` methods).
- **`DBPension::isInPayment()` (`:116-131`)** prefers a stated status in both
  directions, keeping age-vs-Normal-Retirement-Age for rows that predate the column.
- **Applied everywhere:** `$fillable`, `UpdateRecordAllowlist`, `dbPensionFields.js`
  (options + `formatSchemeStatus()` + payload), both Defined Benefit forms,
  `PensionDetail.vue:195`, and a Scheme status row on `/m`'s pension detail importing
  the same mapper rather than a copy.

Tests: `DbPensionSchemeStatusTest.php` (new) 10 passed / 24 assertions;
`DbPensionFields.spec.js` 9 passed. Pint clean.

## Not done, and why

- **`fyn-memory/procedural/tool_schema/savings/create_pension.md`** — the Anthropic
  provider copy — still lacks `scheme_status`. The app runs xAI, whose copy has had the
  field for versions, so this is dormant pre-existing drift. Left rather than edited
  blind. Recommend folding into whatever sweep reconciles the two provider catalogues.
- **The xAI catalogue was deliberately not re-recorded.** It already declares
  `scheme_status` with the title-case enum; changing it to snake_case would mean
  re-recording a byte-identity golden master for a mapping that costs three lines.

## What you need that isn't obvious from the artefacts

- **No backfill, on purpose.** Every pre-existing row has an unknown status. Null is
  meaningful — `isInPayment()` reads it as "fall back to age", which is exactly the
  W-0036 behaviour batch C landed. `db_pensions.id 4` is untouched and still NULL, so
  it still works as batch C's acceptance fixture.
- **`investment_accounts.scheme_status` is a different column** with a different enum
  (`active`, `vesting`, `exercisable`, `exercised`, `expired`, `forfeited`,
  `cancelled`), for employee share schemes. A grep for `scheme_status` hits it. It has
  nothing to do with pensions and was not touched.
- **The stored value and the displayed label differ by design.** The forms send
  `in_payment`; the user reads "In Payment". `StoreDBPensionRequest` validates the
  stored vocabulary, so a title-case value posted directly to the HTTP endpoint is a
  422 — that is intended, and Fyn's title case is mapped before it ever reaches
  validation.
- **`DBPensionForm`'s required-status guard now applies to edits too**, except for
  records saved before the column existed, which have nothing to restore. Blocking
  those would trap the user on a pension they already have.

## Assumptions I made

- **Assumption:** snake_case storage is right, on the strength of every other enum in
  the app. If CSJ wants the stored value to match Fyn's schema literally, the change is
  one constant plus a golden-master re-record.
- **Assumption:** adding `scheme_status` to `UpdateRecordAllowlist` is wanted. It is the
  only way `/m` and native users can correct the field, and it mirrors what W-0017 did
  for the other four Defined Benefit fields — but it does widen what Fyn may write.
- **Assumption:** "Not recorded" is the right thing to show for an unset status. The
  old display asserted "Active" for every pension ever saved, which was never true.

## Surfaces covered / not covered

- **web** — covered: both forms capture it, the detail view reads it back honestly.
- **/m** — covered: displayed on the pension detail; capture and correction via Fyn,
  through the same store and the same allowlist.
- **ios** — capture and correction via Fyn, same as `/m`. Native's pension detail view
  was not inspected for a place to display the status; if parity there matters, it is a
  separate item.
