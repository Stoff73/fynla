---
id: W-0444
title: Every not-found path on all five pension holdings endpoints returned 500, because ModelNotFoundException was thrown without being imported
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0032-cycle4-pension-holdings-entry-and-display.md
owner: build-lead
status: handoff
severity: medium
surfaces: [web, m, ios]
created: 2026-08-23T03:35:00Z
claimed: 2026-08-23T03:35:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-23
prior_art_found: [W-0126, reference_formatter_strips_new_use_import]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

Found by `fix-cycle4-retirement` while wiring the client control for W-0441 — these
endpoints were about to become reachable from the interface for the first time.

`DCPensionHoldingsController::pensionForUserOr404():43` throws
`ModelNotFoundException`. **The class is not imported** — the file's `use` block held
eleven imports and none of them it — so PHP resolved it against the controller's own
namespace as `App\Http\Controllers\Api\Retirement\ModelNotFoundException`.
`class_exists()` on that returns false.

So a request for a pension that does not exist, or that belongs to someone else,
raised a fatal `Error` and returned **500** on all five endpoints —
`index`, `store`, `update`, `destroy` and `bulkUpdate` — where
`app/Http/CLAUDE.md` promises *"404 — Not found or access denied"*.

### Why nothing caught it

`DCPensionHoldingValuationTest` gives every case a pension the acting user owns, so
the branch was never entered. **Nothing in that file says "and no unowned pension is
ever requested here"** — the Fixture variant in `tests/CLAUDE.md` §4, and the hardest
of the five to see, because a fixture's absence of a row is invisible. The
countermeasure named there is to ask what the fixture does not contain. What it did
not contain was a second user.

### Likely origin

The shape matches the `reference_formatter_strips_new_use_import` trap: an import
added in one edit and its first reference in the next is removed by the formatter
before the second edit lands. The file is valid PHP, `php -l` passes, and the class
resolves as a same-namespace name that does not exist.

## Acceptance

1. [x] All five endpoints return 404, not 500, for a pension the caller does not own.
2. [x] A pension the caller does own is still served.
3. [x] Cover that enters the branch, with a fixture that contains a second user.

## Working notes

- 2026-08-23 build-lead (`fix-cycle4-retirement`): **Fixed.** Import added; verified
  present after Pint, per the formatter trap above.

  `tests/Feature/Retirement/DCPensionHoldingsOwnershipTest.php` — 6 cases. Five enter
  the branch on each endpoint; the sixth asserts the owner is **still served**, which
  is what stops "return 404 unconditionally" satisfying the other five.

  Each refusal also asserts the row is unchanged, because a fatal error raised *after*
  a write would equally have failed to be a 404. The untouched value is £160,018
  rather than a round figure, so it cannot coincide with anything a partial write
  would have produced.

  **Mutation-tested:** removing the import again reddens exactly the five not-found
  cases and leaves the owner case green.

  **Rule 19:** backend only, one shared endpoint — reaches web, `/m` and native
  together. No bundle step.
