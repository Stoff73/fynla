# W-0100 — build-lead → quality-lead

Branch document: `workforce/branches/fixes/F-0008-batch-g-lpa.md` (full detail).
Perimeter half (acceptance 5, not mine): `workforce/ops/reports/2026-08-21-W-0100-lpa-perimeter-review.md`.

## Done

- **The overclaim is fixed in every home it had.** The green "Compliant" badge
  (`LpaComplianceService.php:49` + `LpaComplianceChecklist.vue:88,97`), the generated
  document's *"This instrument is now a valid Lasting Power of Attorney under the Mental
  Capacity Act 2005"* (`lpaDocumentRenderer.js:248`), and — the one nobody was looking
  for — **the renderer drawing the donor's, every attorney's and the certificate
  provider's signature in a script font** whenever `completed_at` was set
  (`:191, :204, :218, :231`).
- **One home for the wording:** `app/Services/Estate/LpaCheckPolicy.php` (new). The
  service composes its payload from it; the component hardcodes nothing.
  `overall_status` was **deleted, not renamed**, so every consumer had to be found.
- **Acceptance 1–4 answered**, written up in W-0100's working notes.
- **Ten board items raised**, W-0101–W-0110.
- Targeted tests green: 42 passed / 181 assertions (Pest, `laravel_testing_c`); 9 passed
  (Vitest, run in isolation). Pint `passed`. ESLint exit 0.

## Not done, and why

- **No browser verification.** The dispatch reserved Rule 14's loop for a persona-tester.
  Nothing in this batch has been seen rendered.
- **No commit, no PR, no deploy.** Dispatch.
- **No evidence pack.** Yours, and I do not write my own (`08-process.md` §2.4).
- **No new statutory checks.** W-0102–W-0107 are raised rather than built: adding
  validation that states what the law requires needs compliance-lead's read of the
  wording, and the dispatch scoped the fix to the overclaim.
- **W-0101 not fixed** — the will renderer has the identical defect (it draws the
  testator's **and both witnesses'** signatures) and is live on production, but
  `fix-batch-B` holds those files today and a parallel edit would collide.
- **No production query.** Not mine to run.

## What you need that isn't obvious from the artefacts

1. **The green badge was reachable, but only down a narrow path, and you should test
   that path rather than the obvious one.** The checklist renders only for
   `status === 'draft'` (`LpaDetailView.vue:48`), and the registration check warns on
   any unregistered instrument — so an ordinary draft always said "Review Needed". To
   reproduce the old badge: create and complete a Lasting Power of Attorney, mark it
   registered (`POST /api/estate/lpa/{id}/register`), then re-open it in the wizard and
   press **Save Draft**. `LpaService::updateLpa()` never clears `is_registered_with_opg`.
   Add a replacement attorney, one person to notify, and a certificate provider known
   two years or more. **That is the state to verify now reads "No issues found in these
   checks" as plain text.**
2. **The regression test excludes "valid" from its verdict scan on purpose.**
   `LpaControllerTest.php` scans the response body for `compliant`, `compliance`,
   `approved`, `sufficient` — but not `valid`, because the disclosure says the checks
   "cannot tell you whether your Lasting Power of Attorney is valid". The negation is
   the point. `valid` is asserted separately against `outcome_label` only. If you
   tighten that scan, it will go red for the right words.
3. **`resources/js/utils/__tests__/lpaDocumentRenderer.spec.js` is the gate on the
   signature fix**, not the copy. Do not accept a future change to that renderer that
   makes the test pass by deleting it.
4. **There is nothing to verify on `/m` or iOS.** Not "not yet checked" — those surfaces
   have no Lasting Power of Attorney screen at all (W-0110). Rule 19 is satisfied by the
   absence being established and raised, not by parity being delivered.

## Assumptions I made

(Stated as assumptions, not facts.)

- **That fixing the assertions and raising the checks was the right split.** The dispatch
  said audit findings become board items and the overclaim gets fixed; I drew the line
  at "does this assert something Fynla cannot know". W-0102 is the closest call and I
  have offered to take it.
- **That `LpaCheckPolicy` as a new class is what compliance meant** by "composing from
  one home per Rule 20 (`WillTypePolicy` precedent)" — the pattern, not the class. If
  they meant the referral sentence should live in a shared constant today, that is a
  small follow-up and `WillTypePolicy`'s approved-verbatim text must not be edited to
  achieve it.
- **That the document's first-person body clauses may stay.** They read as the
  instrument; the top-of-document qualification is what carries the correction. A
  reviewer could reasonably want more.
- **That the per-check pass/fail icons and their spring/raspberry/violet colours stay.**
  They are pre-existing Rule 15 violations, grandfathered and forward-only, and they
  describe a check rather than the instrument. I did not touch them.
- **That "up to 8 weeks" for registration is stale.** I did **not** verify it and assert
  no replacement figure — that is exactly why W-0109 exists.

## Surfaces covered / not covered

- **Web — covered.** `/estate/power-of-attorney`, `/estate/lpa/create/:type`. Code
  complete, unit-verified, **not browser-verified**.
- **`/m` — not covered, and nothing to cover.** No Lasting Power of Attorney route,
  view or API call exists in `resources/mobile/`. Established by absence-grep, raised as
  W-0110.
- **iOS native — not covered, and nothing to cover.** Same, plus `WebHandoffDestination`
  has no case for it.
- **Fyn — reaches all three surfaces and writes here.** `create_power_of_attorney` /
  `update_power_of_attorney` are unchanged by this batch. That asymmetry — write
  everywhere, read on web only — is W-0110.
