---
id: W-0261
title: An "(Optional)" holding field is NOT NULL, and leaving it blank prints the raw INSERT statement to the end user
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0023-cycle4-validation-and-silent-data-loss.md
owner: build-lead
status: handoff
severity: critical
surfaces: [web, m, ios]
created: 2026-08-22T20:40:00Z
claimed: 2026-08-22T20:45:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-22
prior_art_found: [W-0052, W-0008, W-0026]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, cycle 4 batch 3 (`R-21`), local `localhost:8000`,
David (16). **The third instance of the W-0052 pattern in this run.**

### The defect — three faults in one submit

Fill Add Holding, leave **"Dividend Yield % (Optional)"** blank exactly as its label
invites, submit. The page renders to the end user:

```
SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'dividend_yield'
cannot be null (Connection: mysql, SQL: insert into `holdings` (`asset_type`,
`sub_type`, `security_name`, `ticker`, `isin`, `allocation_percent`, …
```

1. **The "(Optional)" field is mandatory.** `holdings.dividend_yield` is
   `decimal(5,4) NOT NULL DEFAULT '0.0000'`; `StoreHoldingRequest:44` and
   `UpdateHoldingRequest:44` validate it `nullable`; `HoldingForm.vue:374`
   initialises it to an explicit `null`. **`ocf_percent` is the identical column
   with the identical latent 500** — it escaped only because the tester filled it.
2. **The raw statement and its column list are rendered to the user** — schema
   disclosure, and a fault in its own right rather than a consequence of (1).
3. **An earlier submit failed silently** — the conditional "Fund Type" select is
   enforced server-side but had no error binding, no error paragraph and no client
   check, so a rejected save looked like nothing happening.

## Acceptance

- [x] Creating a holding with Dividend Yield blank succeeds and stores `0.0000`.
- [x] `ocf_percent` covered by the same fix, not left latent.
- [x] A constraint violation never surfaces SQL to a user **on any surface,
      regardless of `app.debug`**.
- [x] The Fund Type rejection surfaces a message at the field.
- [x] Tests that OMIT the field rather than supplying it (`tests/CLAUDE.md` §4).
- [x] A schema-drift guard so a fourth occurrence fails a test, not a user's save.
- [x] The full `nullable`-on-NOT-NULL sweep across `app/Http/Requests/`, reported
      whether or not fixed.
- [x] **Re-verified live in the browser** — all three faults, as David (16).

## Working notes

- 2026-08-22 build-lead: **FIXED, three faults, three fixes. Not browser-verified.**

  **Prior art — outcome `extend`.** W-0052 already generalised this exact bug for
  `investment_accounts` (`InvestmentAccountNormaliser::NOT_NULL_WITH_DEFAULT` +
  a schema-drift test). I did not build a parallel mechanism: the same shape now
  exists for `holdings` as `HoldingValuation::NOT_NULL_WITH_DEFAULT`, in the class
  that is already the one write boundary for holdings.

  **Fault 1** — the drop loop in `HoldingValuation::reconcile()`, running before the
  float casts, because `(float) null` is `0.0` and would write a zero on an update
  where the caller meant "leave it alone". Placed there rather than in the two form
  requests because `reconcile()` is the boundary the web form, the pension holdings
  controller, the account-seeding paths **and Fyn's `create_holding`** all already
  cross — the validators would have covered two of five.

  **Per-column judgement, as instructed.** `current_value`, `security_name` and
  `asset_type` are also NOT NULL on `holdings` and are deliberately EXCLUDED: no
  database default, so there is nothing to fall back to and dropping the key would
  fabricate a figure. Zero is right for the two rates because it is the column's own
  default and "not stated" and "zero" are the same fact for a yield or a charge.

  **Fault 2** — `app/Exceptions/Handler.php` now intercepts `QueryException` and
  `PDOException` ahead of the generic branch, logs the real message with path,
  method and user id, and returns a civil sentence. The old `! config('app.debug')`
  guard was never enough: debug is true on every developer machine and on any server
  where it has been left on, **and the disclosure is a property of the exception,
  not of the environment**. One handler, so web, `/m` and native are covered at once.

  **Fault 3** — the server was never wrong; `sub_type` carries
  `required_if:asset_type,fund` and returns a clear message. Three client-side
  causes hid it: no error binding on that select (the only field in the form
  lacking one), no client check, and the parent passing only `data.message` (the
  generic "The given data was invalid.") into a banner at the **top of a modal the
  user has scrolled to the bottom of**. Fixed by matching the select to its
  siblings and adding a `fieldErrors` prop so any 422's per-field messages land on
  the fields that caused them — general, not specific to this field.

  ### Tests — 10 cases, all verified RED first

  `tests/Feature/Investment/HoldingNotNullColumnsTest.php` (6) and
  `tests/Feature/Security/DatabaseExceptionDisclosureTest.php` (4).

  Disabling the drop loop turns **3 of 6** red — the three that enter the bug
  branch; the other three (keys absent, a supplied figure, the drift guard)
  correctly stay green. Removing the handler branch turns **3 of 4** red.

  The disclosure test drives `ExceptionHandler::render()` rather than a route: a
  route declared inside a test is appended behind the SPA catch-all in
  `routes/web.php` and never matches (verified — such a request returns the SPA's
  HTML with a 200). `render()` is the production entry point for the code under
  test. `app.debug` is forced ON, because a run with debug off passes on the OLD
  code and proves nothing.

  ### The sweep — the deliverable

  **192 rows** across 40 model-backed form requests, built mechanically from
  `information_schema` against `rules()` called on real instances. Full list and
  ranking in `F-0023` §6.1. Headline: **23 columns where a client sends an explicit
  null today**, of which 2 are fixed here and 6 were already covered by W-0052 —
  leaving 15 unfixed across business interests, chattels, mortgages, properties,
  savings, goals, protection profiles and wills. **Nine more have NO database
  default and cannot be fixed by dropping the null at all** — they need a nullable
  column or a required rule, which is a per-column product decision.

  ### Browser-verified GREEN — all three faults, as David (16)

  | Check | Result |
  |---|---|
  | Dividend Yield **and** Ongoing Charge Figure both blank | **Saved.** Row 68, both `0.0000`, £1,250 = 100 units x £12.50. **No SQL on the page.** |
  | Asset Type "Fund", Fund Type blank | **"Fund type is required when the asset type is Fund"** at the field, red border. `W-0261-fundtype-error-now-visible.png` |
  | A real `QueryException` via `POST /api/investment/holdings` | Civil message, **zero SQL**; full statement + path + method + user_id in `laravel.log`. |

  Fault 2 was proven with a **genuine** database exception, not a simulated one:
  `dividend_yield` is `decimal(5,4)` and stops at 9.9999, so a yield of 50 passed
  the old `max:100` rule and overflowed the column. That is a defect in its own
  right and is raised as **W-0263** (18 rules across 11 requests, worst of them the
  mortgage interest rates, where any rate of 10%+ 500s today). The two holdings
  rules are capped here; the other sixteen need a migration decision.

  **`HoldingForm` has TWO parents** — `InvestmentHoldings.vue` and
  `InvestmentProjections.vue`, and the account-detail drill-down the tester used is
  the second. Fixing one would have left the field-error behaviour reaching half
  the entry points. Both are wired.

  **DB hygiene:** the verification holding was removed (not persona data).

  **The web bundle needs rebuilding** for the client changes to reach csjones —
  build artefacts are the coordinator's and were not built here. `/m` has no
  holding-entry surface, so there is no `/m` counterpart to build.
