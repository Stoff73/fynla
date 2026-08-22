---
id: W-0046
title: Wills completed before W-0023 have gifts in the document but no Bequest rows — the Estate module and the Inheritance Tax calculation cannot see them
mission: M-0002-persona-fidelity
owner: build-lead
status: handoff
severity: high
surfaces: [web, m, ios]
source: CSJ direction 2026-08-21 ("these need to work properly"); gap identified by fix-batch-B while fixing W-0023
prior_art_checked: 2026-08-21
prior_art_outcome: extend
branch: branches/fixes/F-0003-batch-b-estate-wills.md
handoff_to: quality-lead
claimed: 2026-08-21T14:10:00Z
prior_art_found: [W-0023, W-0020, W-0024]
---

## Intent

W-0023 made will-builder gifts create `Bequest` rows — but `syncBequests()` runs **on
completion only**. Every will completed before that fix therefore has its gifts
recorded in the will document and **zero `Bequest` rows**.

Consequence: the Estate module does not show those bequests, and
`getCharitableBequestTotal()` returns 0 for them — so a charitable legacy that should
move the estate to the 36% reduced Inheritance Tax rate is invisible to the
calculation. The user recorded the gift; the app acts as though they did not.

**This is a defect, not a decision.** CSJ 2026-08-21: "these need to work properly".

## Acceptance

1. Existing completed wills are backfilled so their gifts become `Bequest` rows —
   idempotent, safe to re-run, and never touching hand-made rows (the
   `will_document_id` column W-0023 added is what distinguishes them).
2. **Report what the backfill changed**: how many wills, how many rows, and the
   before/after charitable totals for any estate whose Inheritance Tax rate moves.
   Same standard as W-0030 — a data migration that does not say what it touched cannot
   be verified.
3. Any estate whose Inheritance Tax position changes as a result is identified, since
   that is a materially different answer from the one the user was previously shown.
4. Re-completing a will must not duplicate rows — verify the backfill and
   `syncBequests()` cannot both write the same gift.
5. David (16) and Sarah (17) are the local test case: both hold completed wills with
   zero bequests today.

## Working notes

Applies to production as well as local, so sequence it with W-0019's production
question — both are asking "what does existing user data look like, and what do we owe
those users".

- 2026-08-21 build-lead: **BUILT and unit-verified. NOT YET RUN against real data —
  I need your go-ahead, see "Waiting on you" below.** Handing to quality-lead for the
  evidence pack. **Rule 14's loop is not closed by me.**

  **Prior art — outcome `extend`, six sources checked.** `registry/capabilities.md`
  (no bequest/backfill capability recorded) · code (11 existing `Backfill*` /
  `Migrate*` commands) · **custom artisan commands** — `fyn:episodic:purge` and
  `fyn:user:erase` set the dry-run-by-default + `--force` convention, and
  `fyn:episodic:backfill-blobs` is CLAUDE.md's own "one-shot idempotent backfill"
  precedent · open PRs and branches (only #249, parked, unrelated) · vault · skills
  and agents. Nothing already does this job; the command pattern to follow exists.

  **`php artisan estate:backfill-bequests`**
  (`app/Console/Commands/BackfillWillBequests.php`). Dry-run by default;
  `--force` to write; `--user=` to scope to one account.

  **It is not a second implementation.** It calls
  `WillDocumentService::syncBequestsForDocument()` (`:472`), a thin public wrapper
  over the SAME `syncBequests()` a completion calls. That is what makes acceptance 4
  true by construction rather than by inspection: the backfill and a later
  re-completion literally cannot write the same gift twice, because they are the same
  code and it clears before it writes. Pinned by a test that backfills, re-completes,
  and asserts one row.

  **Two defects found while building this, both fixed, both mine:**

  1. **The Inheritance Tax calculation would NOT have picked the backfill up.**
     `IHTCalculationService` caches into `iht_calculations`, keyed on hashes of
     assets and liabilities (`generateHashes()`). Bequests were not in that key —
     fine while the rate depended only on `IHTProfile.charitable_giving_percent`,
     **but W-0020 made the rate read the bequests.** So a user could record a
     charitable legacy, qualify for the reduced rate, and keep being served the
     previous 40% figure until their assets happened to change — the exact journey
     W-0020 exists to fix, silently defeated by cache. Fixed with
     `charitableBequestFingerprint()` (`IHTCalculationService.php:1535`), folded into
     the key in both `generateHashes()` and `saveCalculation()`.
     **Verified the pin is real**: with the fingerprint removed the new test fails
     `Failed asserting that 0.4 is identical to 0.36`; with it, it passes.
  2. **`syncBequests()` only cleared rows from its own document.** A user who
     completed a second will document had the first document's gifts left standing
     beside the new ones — two live sets for one will, both counted by the charitable
     total. Now it clears every will-document-sourced row for the will
     (`whereNotNull('will_document_id')`), so the current document is authoritative.
     Hand-made rows (NULL) are still never touched. Pinned by a superseded-document
     test.

  **Acceptance 2 and 3 — the report.** Local dry-run, verbatim:

  ```
  DRY RUN — nothing will be written. Re-run with --force to apply.

  Would backfill 6 will(s) → 6 bequest row(s).

  | User | Name             | Doc | Rows | Charitable total        | IHT rate  |        |
  | 28   | Harold Bennett   | 13  | 1    | £0.00 → £0.00           | 40% → 40% |        |
  | 27   | Patricia Bennett | 12  | 1    | £0.00 → £0.00           | 40% → 40% |        |
  | 24   | Sarah Mitchell   | 11  | 1    | £10,000.00 → £20,000.00 | 40% → 40% | REVIEW |
  | 23   | David Mitchell   | 10  | 1    | £10,000.00 → £10,000.00 | 40% → 40% |        |
  | 17   | Sarah Jones      | 6   | 1    | £0.00 → £10,000.00      | 40% → 40% |        |
  | 16   | David Jones      | 5   | 1    | £0.00 → £10,000.00      | 40% → 40% |        |

  1 user(s) now hold more charitable bequests than before. Check these are separate
  legacies and not one legacy recorded twice — a mirror will generated before W-0024
  carried the partner's charity verbatim:
    user 24 (Sarah Mitchell): British Heart Foundation → British Heart Foundation, Cancer Research UK

  No estate changed its Inheritance Tax rate.
  ```

  The dry-run produces REAL before/after figures rather than estimates: the whole run
  executes inside a transaction that is rolled back unless `--force`, so the numbers
  come from the actual code path against actual rows. Caches are cleared again after a
  rollback so a dry-run leaves nothing behind.

  **Acceptance 3 — no local estate changes its Inheritance Tax rate.** All six sit far
  below the 10% threshold (a £10,000 legacy against a seven-figure estate). The
  command flags a rate move loudly if one occurs; none does here.

  **A finding the report surfaced, which is why the report was worth building.**
  Sarah Mitchell (24) would go £10,000 → £20,000 because her hand-made bequest names
  **British Heart Foundation** while her will *document* names **Cancer Research UK** —
  her husband's charity, copied verbatim by the W-0024 mirror defect before it was
  fixed. The two are different legacies by name, so the adoption matcher correctly
  does NOT merge them, and the backfill would faithfully record what her document
  says. **I did not merge them and will not** — merging two differently-named
  charities would be inventing. The command flags it `REVIEW` instead. This is
  pre-existing wrong data that W-0024 created and the backfill makes visible; it is an
  argument for running it, not against.

  **Preview personas are in scope.** Four of the six rows (23, 24, 27, 28) are seeded
  preview users. Their wills are as wrong as anyone's and should display correctly;
  `php artisan db:seed` restores them if wanted.

  **Waiting on you — I have NOT run `--force`.** The dry-run is complete and the write
  path is proven by tests, but committing touches David and Sarah while the tester is
  re-running against them, and creates Sarah Mitchell's flagged row. Given the
  route-everything-through-you rule, that is your call, not mine. One command,
  ~30 seconds, and I can scope it: `--user=16` / `--user=17` only, or all six.

  **Production is explicitly out of scope** per your instruction and was not touched.

  Tests: `tests/Feature/Estate/BackfillWillBequestsCommandTest.php` — 10 cases
  (dry-run writes nothing; reports before/after; creates rows; idempotent; never
  touches hand-made rows; already-synced no-op; no-gift skip; `--user` scoping;
  superseded document; backfill-then-re-completion holds one row).
  Plus `tests/Unit/Services/Estate/WillAnalysisCharitableBequestTest.php` gains the
  cache-staleness pin.

- 2026-08-21 build-lead: **`--force` RUN on all six, on team-lead's explicit
  authorisation and scope decision.** Result, verbatim:

  ```
  Backfilled 6 will(s) → 6 bequest row(s).
  | 28 | Harold Bennett   | 13 | 1 | £0.00 → £0.00           | 40% → 40% |        |
  | 27 | Patricia Bennett | 12 | 1 | £0.00 → £0.00           | 40% → 40% |        |
  | 24 | Sarah Mitchell   | 11 | 1 | £10,000.00 → £20,000.00 | 40% → 40% | REVIEW |
  | 23 | David Mitchell   | 10 | 1 | £10,000.00 → £10,000.00 | 40% → 40% |        |
  | 17 | Sarah Jones      |  6 | 1 | £0.00 → £10,000.00      | 40% → 40% |        |
  | 16 | David Jones      |  5 | 1 | £0.00 → £10,000.00      | 40% → 40% |        |
  No estate changed its Inheritance Tax rate.
  ```

  **Acceptance 5 satisfied, and better than expected.** David (16) now holds
  Cancer Research UK £10,000; Sarah (17) holds **British Heart Foundation**
  £10,000 — which is the persona-correct value, not her husband's charity. Her
  will document had already been corrected during the persona run, so the pair
  now matches `tests/Persona/peak_earners.md` exactly.

  **Idempotency proven against real data, not only in tests.** A second
  `--force` produced the same six rows: total bequests 18 before and after,
  6 doc-sourced, **12 hand-made untouched**. The `REVIEW` flag correctly did NOT
  re-fire on the second run — it reports a *change*, and by then Sarah
  Mitchell's second charity was the standing state, not a new one.

  Sarah Mitchell's flagged row was left standing, per team-lead: it is a
  preserved artefact of the W-0024 mirror defect (her document names her
  husband's charity), and making it visible is worth more than a tidy table.

  Local database now correct. **Production untouched and out of scope.**
