# W-0017 — build-lead → quality-lead

## Done

All four gaps, on all three surfaces, plus two blocking defects found on the way.

- **One shared definition** — `resources/js/components/Retirement/dbPensionFields.js`
  holds the scheme-type options, the inflation-protection options and the
  `db_pensions` payload mapper. Both Defined Benefit forms compose from it.
- **`DCPensionForm.vue`** (the ADD path): Scheme Type select, Normal Retirement
  Age, Spouse Pension %, Inflation Protection select, conditional fixed
  revaluation rate. Submits through the shared mapper.
- **`DBPensionForm.vue`** (the EDIT + onboarding path): the same field set, the
  full three-value scheme type, and a corrected edit-population mapping.
- **Fyn catalogue (`/m` + iOS)**: `create_pension` gains
  `spouse_pension_percent`, `inflation_protection`, `lump_sum_entitlement` and
  the `public_sector` scheme type, both provider variants, version 3 → 4; golden
  masters re-recorded. `UpdateRecordAllowlist::db_pension` gains
  `scheme_type`, `spouse_pension_percent`, `inflation_protection`,
  `revaluation_method`, `lump_sum_entitlement`.
- **`PensionStore`**: `inflation_protection` tightened from `string|max:64` to the
  column's enum.
- Tests: 14 (`PensionStoreHttpIntegrationTest`), 10 (`CreatePensionTest`),
  7 (`DbPensionFields.spec.js`), 14 (both tool-schema golden masters).

## Not done, and why

- **No live browser verification and no live Fyn turn.** Same reason as W-0010 —
  scoped out. Nobody has typed Sarah's NHS scheme into the form, and nobody has
  asked Fyn on `/m` to record a career-average pension with a 50% spouse benefit.
  Rule 14's loop is NOT closed by me.
- **Sarah's existing row (`db_pensions.id = 4`) is untouched.** I did not patch it;
  the brief forbade it. It still reads `scheme_type='final_salary'`,
  `normal_retirement_age NULL`, `spouse_pension_percent NULL`,
  `inflation_protection='none'`. It should now be correctable through the edit
  form — that is the acceptance test.
- **`spouse_pension_percent` unit contradiction — REPORTED, NOT FIXED.** This is
  the most important thing in this note.
  `app/Services/Documents/FieldMappers/DBPensionMapper.php:96-114`
  (`parseSpousePercent`) converts 50% to **0.50** with the comment
  "DB stores as decimal (0.50 for 50%)". Everything else treats the column as
  percentage points: `HouseholdPlanningService.php:791` divides by 100,
  `StoreDBPensionRequest.php:47` and `PensionStore.php:615` both allow `max:100`,
  `DBPensionFactory.php:45` uses `[50.0, 66.67, 100.0]`, and
  `PensionDerivedColumnCalculator.php:103-104` divides by 100. So a Defined
  Benefit pension imported from an uploaded document stores 0.5 and every spouse
  projection then computes `annual × 0.005` — understating the spouse's pension
  by 100×. I did not change it: it is the Documents module, it is a different
  entry path from the one this item names, and picking the convention should be
  recorded as a decision rather than done quietly. **It deserves its own board
  item at high severity.** All the evidence says percentage points is canonical.
- I did NOT remove the dead `scheme_status` field (collected by both forms, no
  `db_pensions` column, discarded on every save). I only stopped it blocking
  edits. Either give it a column or take it off the forms — a decision, not a fix.
- I did NOT touch `AIExtractionService.php:496`, which documents the same 0.50
  convention to the extraction model.

## What you need that isn't obvious from the artefacts

- **There are two Defined Benefit forms, not one.** `DCPensionForm.vue` is the ADD
  path (via `UnifiedPensionForm` when `initialPensionType` is null);
  `DBPensionForm.vue` is the EDIT path and the onboarding path
  (`Onboarding/steps/AssetsStep.vue:447`). Verify BOTH. A fix in one does not
  reach the other, which is exactly how these four gaps survived.
- The `pension_type` wire value for a Defined Benefit scheme in `DCPensionForm`
  is still `final_salary` even when the user picks Career Average — the real
  scheme type is `formData.db_scheme_type`. Do not "fix" the dropdown value; the
  computed `isFinalSalary` and the aiFormFill sequences key on it.
- Re-recording the golden masters is
  `CAPTURE_TOOL_SCHEMA_GOLDEN=1 CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php`.
  The diff should touch `create_pension` and nothing else; check that before trusting it.
- The xAI schema is `strict: true` with every property in `required`, so the three
  new params are now mandatory on every `create_pension` call including Defined
  Contribution ones — the model must send null. That matches the existing design
  ("Every field is required by the schema, so send null…") but it is a real change
  in what the model must emit, and it is the thing most likely to show up in evals.
- The vault Known Issue claiming projections ignore `inflation_protection` /
  `revaluation_method` is **false**: `PensionProjector.php:113-119` applies both.
  Worth correcting in `Current State/Retirement.md`.

## Assumptions I made

- I assumed percentage points (50, not 0.5) is the canonical convention for
  `spouse_pension_percent`, and wrote the tool-schema description to say so
  explicitly. If CSJ rules the other way, the schema description and the two
  forms both change.
- I assumed the numeric revaluation rate should only be collected for the "Fixed
  rate" option, since that is the only branch `PensionProjector` reads it in.
  Users who previously typed a number with `inflation_protection` at its default
  'none' were never having it applied, so nothing is lost — but it is a UI
  behaviour change I decided, not one that was specified.
- I assumed relaxing the `scheme_status` requirement on edit only (rather than
  removing the field or adding a column) is the right minimal call.
- I assumed adding `public_sector` to the Fyn scheme-type enum is safe because
  `PensionNormaliser::ALLOWED_DB_SCHEME_TYPES` already contained it.

## Surfaces covered / not covered

- **web** — both forms fixed, unit-tested, NOT browser-verified.
- **`/m`** — fixed at the only entry mechanism it has (the Fyn tool catalogue),
  feature-tested at the handler level, NOT verified with a live Fyn turn on csjones.
- **iOS** — same catalogue, same handlers, so covered by the same change; not
  separately verified, and I have no evidence about the native surface beyond
  "it uses the same endpoint".
