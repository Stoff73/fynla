---
id: W-0030
title: spouse_pension_percent stored as a decimal by the document importer and as percentage points everywhere else — imported Defined Benefit pensions understate the spouse's pension by 100x
mission: M-0002-persona-fidelity
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
status: gated
severity: high
surfaces: [web, m, ios]
source: found by fix-batch-C while fixing W-0017, 2026-08-21; deliberately not fixed there (different entry path, needs a recorded convention decision)
prior_art_checked: 2026-08-21
prior_art_outcome: extend
---

## Intent

One field, two incompatible unit conventions. A Defined Benefit pension imported from
an uploaded document stores `spouse_pension_percent` as a **decimal fraction**, while
every other producer and consumer treats it as **percentage points**. Every spouse
projection for an imported pension therefore computes `annual × 0.005` instead of
`annual × 0.50` — **understating the spouse's pension by a factor of 100**.

## Evidence

Decimal convention (the outlier):

- `app/Services/Documents/FieldMappers/DBPensionMapper.php:96-114` — converts 50% to
  0.50, commented "DB stores as decimal".
- `app/Services/AI/AIExtractionService.php:496` — documents the same 0.50 convention
  to the extraction model, so the model is actively taught the wrong unit.

Percentage-point convention (everything else):

- `app/Services/HouseholdPlanningService.php:791` — divides by 100.
- `app/Http/Requests/StoreDBPensionRequest.php:47` — `max:100`.
- `app/Services/Retirement/PensionStore.php:615` — `max:100`.
- `database/factories/DBPensionFactory.php:45` — 50.0 / 66.67 / 100.0.
- `app/Services/Retirement/PensionDerivedColumnCalculator.php:103-104` — divides by 100.

Five independent sites say percentage points; two say decimal. **Percentage points is
canonical on the weight of evidence** — but the convention should be a recorded
decision, not inferred, which is why fix-batch-C escalated rather than picking.

## Acceptance

1. One recorded convention for `spouse_pension_percent`, stated where a future
   contributor will find it (column comment, model cast, or a constant).
2. Every producer and consumer composes from it — importer, extraction prompt,
   requests, stores, factories, calculators. Per Rule 20, converging them is the fix;
   patching the mapper alone is not.
3. **Existing rows written under the decimal convention must be identified and
   migrated**, or the fix silently leaves live pensions wrong. Determine how many
   exist before deciding — do not assume zero.

   **How to find them** (from fix-batch-C, 2026-08-21 — approach flagged, neither
   query run, confirm before acting):
   - *Value heuristic, not proof:* percentage-point rows are 50 / 66.67 / 100;
     decimal-convention rows land in (0, 1). No genuine UK spouse pension is below
     1%, so `WHERE spouse_pension_percent > 0 AND spouse_pension_percent < 1`
     isolates most of them. **But `decimal(5,2)` truncates** — a decimal-convention
     66.67% stored as 0.6667 lands as **0.67**, indistinguishable by value from a
     legitimate 0.67%. Value alone cannot be the only test.
   - *Definitive discriminator — provenance:* only the document-extraction path
     (`DBPensionMapper`) ever wrote the decimal form. Cross-reference
     `document_extractions` / the audit trail for `db_pensions` rows for a
     conclusive list.

4. **The derived columns are already poisoned and must be recalculated.**
   `PensionDerivedColumnCalculator.php:103-104` writes `spouse_pension_projected_gbp`
   from the stored percentage, so every mis-scaled row also has a **stored projection
   wrong by 100×**. Correcting the source column alone leaves the wrong number
   rendering from the derived cache — the fix would look complete and the user would
   still see it. Recalculate derived columns for every migrated row.
5. The extraction prompt at `AIExtractionService.php:496` is corrected, otherwise the
   model keeps producing the wrong unit whatever the mapper does. **A mapper fix
   without a prompt fix is half a fix wherever an LLM is upstream** — the mapper would
   be normalising a unit the prompt keeps producing.

## Working notes

Different entry path from W-0017 (document import vs form), which is why it was out of
scope there. Reaches iOS and /m identically — both enter Defined Benefit pensions via
Fyn, and document import feeds the same column.

## Working notes (append-only)

- 2026-08-21 build-lead: FIXED, all four acceptance criteria.

  **Note on the paths in the Evidence section above** — four are slightly off, so
  the real ones, for anyone following them: `HouseholdPlanningService` is at
  `app/Services/Coordination/`, `StoreDBPensionRequest` at
  `app/Http/Requests/Retirement/`, `PensionStore` at `app/Services/Stores/`,
  `PensionDerivedColumnCalculator` at `app/Services/Stores/Recalc/`, and
  `AIExtractionService` at `app/Services/Documents/`. The evidence itself is exact.

  **(1) Convention recorded, in three places a contributor actually looks.**
  - The column itself: migration `2026_08_21_120000` sets a MySQL column comment,
    "Percentage points, not a fraction: 50 means 50%. Consumers divide by 100."
    Pinned by a test so it cannot be silently dropped.
  - `app/Models/DBPension.php:16-26` — a UNIT CONVENTION block in the class docblock.
  - Vault `Current State/Retirement.md:112`.

  **(2) Converged, not patched.** The two mappers each carried their own copy of
  the conversion and they disagreed — `DCPensionMapper` returned points,
  `DBPensionMapper` returned a fraction. Both now call one helper,
  `AbstractFieldMapper::parsePercentagePoints()`
  (`app/Services/Documents/FieldMappers/AbstractFieldMapper.php:168-207`), so they
  cannot drift again. `DBPensionMapper.php:96-108` and `DCPensionMapper.php:101-108`
  are now three lines each.

  `parsePercentage()` was deliberately NOT changed: savings and mortgage
  `interest_rate` genuinely do store fractions, and
  `tests/Feature/Stores/SavingsThreeIngestParityTest.php:202` pins that. Merging the
  two helpers would have traded this bug for a different one.

  **(3) Existing rows — surveyed, not assumed.** Local database: 4 `db_pensions`
  rows, 3 with `spouse_pension_percent` set, all 50.00, **zero** in the
  decimal-convention range. So no local row needed correcting — but the migration
  still ships, because csjones and production were not surveyed and are not mine
  to query. It corrects any row in (0,1), **recalculates
  `spouse_pension_projected_gbp` with it** (without that the derived cache keeps
  serving the hundredth-scale figure and the fix only looks complete), and logs
  every correction so a deploy on a populated environment leaves a record of
  exactly which pensions changed.

  Identification is by value: strictly between 0 and 1. No real scheme pays a
  spouse under 1%, while 0.5 meaning "a half" is exactly what the old convention
  produced. The one case value cannot separate is a fraction that truncated into a
  plausible points value — `decimal(5,2)` stores 0.6667 as 0.67 — but 0.67 as
  points is not a real spouse pension either, so it is corrected on the same
  reasoning. Recorded in the migration docblock rather than left implicit.

  **(4) Extraction prompt corrected.** `app/Services/Documents/AIExtractionService.php:496`
  now reads "Spouse pension in percentage points (50% = 50, not 0.50)". Without this
  the model keeps emitting 0.50 whatever the mapper does — the team lead's addition,
  and the right one.

  **Tests**
  - `tests/Unit/Services/Documents/FieldMappers/SpousePensionPercentConventionTest.php`
    — 12 passed. Ten input shapes including the legacy fraction, clamping, and a
    direct assertion that the two mappers now agree.
  - `tests/Feature/Database/SpousePensionPercentBackfillTest.php` — 5 passed.
    Rescale + derived recalculation, correct rows left alone, idempotency, null
    annual pension, and the column comment.

  **NOT done:** csjones and production were not surveyed for affected rows — no
  access, and out of scope. The migration handles them on deploy, and the log lines
  are the evidence. Someone with access should still check the count beforehand so
  the deploy is not a surprise.
