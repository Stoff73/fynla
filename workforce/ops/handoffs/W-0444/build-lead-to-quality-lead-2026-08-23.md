# W-0444 — build-lead (`fix-cycle4-retirement`) → quality-lead

## Done

`ModelNotFoundException` was thrown in `DCPensionHoldingsController` without being
imported, so it resolved to a class in the controller's own namespace that does not
exist and every not-found path on all five endpoints returned **500** instead of 404.
Import added and verified present after Pint.

`tests/Feature/Retirement/DCPensionHoldingsOwnershipTest.php` — 6 cases. Five enter
the branch on `index`, `store`, `update`, `destroy` and `bulkUpdate`; the sixth
asserts the owner is still served, which is what stops "return 404 unconditionally"
satisfying the other five. Each refusal also asserts the row is unchanged, because a
fatal error raised after a write would equally have failed to be a 404.

Mutation-tested: removing the import again reddens exactly those five and leaves the
owner case green.

## Not done, and why

Nothing outstanding. The fix is one import; the cover is the substantive part.

## What you need that isn't obvious from the artefacts

- **The existing `DCPensionHoldingValuationTest` could never have caught this** — every
  case gives the acting user a pension they own, so the branch was never entered, and
  nothing in the file says so. That is the Fixture variant in `tests/CLAUDE.md` §4.
  If you sweep for more of these, the question to ask a fixture is what it does *not*
  contain; here it was a second user.
- **Likely origin is the formatter trap** recorded in
  `reference_formatter_strips_new_use_import`: an import added in one edit and its
  reference in the next is stripped before the second edit lands. Worth a wider grep
  for the same shape — a class-name reference with no matching `use`.
- These endpoints were unreachable from the interface until W-0441 wired them up,
  which is why a 500 on the error path had never been seen by a user.

## Assumptions I made

- I assume 404 is the intended status for "not yours", per `app/Http/CLAUDE.md`
  ("404 — Not found or access denied") and the surrounding controllers. I did not
  change the status, only made the intended one reachable.

## Surfaces covered / not covered

- **web, `/m`, iOS** — one shared endpoint, backend only. No bundle step. Exercised
  live through the new pension Holdings tab on web.
