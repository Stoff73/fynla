# Handover — Companies House filing dates & reminder ladder

**Date:** 2026-08-21 · **Branch:** `dev` · **Status:** feature complete, all tests green, NOT yet verified against the live API

## The ask (verbatim)

> In the business module, when a user enters a business they own, can we link into or look at companies house
> to retrieve the due dates for both accounts and confirmation statements, so we can remind the user when these
> are 30, 20, 15, 10, 5,4,3,2,1 days out and due, over due fines payable? as an example i own csjones limited,
> https://find-and-update.company-information.service.gov.uk/company/12248522, as you can see the url has the
> company number, which we can ask the user for

Classified **bounded** (business module + alert pattern already existed). CSJ approved the design in chat.

## THE ONE THING TO DO FIRST

`.env` has the API key under the **wrong variable name**. CSJ pasted it as:

```
companies_house=<key>
```

It must be:

```
COMPANIES_HOUSE_API_KEY=<key>
```

Then run the live smoke test (nothing has ever hit the real API yet — every test uses `Http::fake`):

```bash
php artisan tinker --execute="dd(app(App\Services\Business\CompaniesHouseService::class)->fetchFilingProfile('12248522'));"
```

Expect: company_name "CS JONES LIMITED", plus real `accounts_due_on` and `confirmation_statement_due_on`.
Cross-check both dates against https://find-and-update.company-information.service.gov.uk/company/12248522

## Decisions already taken — do not re-litigate

- **REST Public Data API**, not Streaming (a 24/7 firehose daemon over the whole register) and not the Document API.
  Key must be **Live**, not Test/Sandbox (sandbox has synthetic companies). REST key, not the JavaScript/browser
  variant, because we call it server-side.
- **No penalty figures displayed.** CSJ's approved default: flag overdue, don't compute a fine. Late *accounts*
  carry an automatic penalty; a late *confirmation statement* carries **no fine** (strike-off / prosecution risk
  instead). The notification copy says exactly this and is asserted in tests.
- **No new notification-preference toggle.** Entering a company number IS the opt-in; clearing it stops the alerts.
  Adding a pref column would have touched 5 more files (2 controllers, 1 request, web + /m settings UI).
- **Company number is NOT in `$fillable`** — filing dates are read from the register via `sync()`, never accepted
  from a request, so a crafted payload cannot fake a deadline.
- **No tightening of `company_number` validation.** It still accepts any string up to 50 chars (sole traders /
  foreign registrations use it freely). `CompaniesHouseService::normaliseNumber()` validates at the outbound
  boundary and returns null for anything unparseable, so the lookup silently no-ops.

## Dead ends already walked — don't repeat

- **A single `saved()` observer hook does not work.** Eloquent calls `syncChanges()` only from `performUpdate()`,
  so on an INSERT `wasChanged('company_number')` is always false and the observer silently never fires for a new
  business. Proven: the update test passed while the create test failed. Fix was splitting into `created()` +
  `updated()`. Do not "simplify" it back to `saved()`.
- **Test contention, not code.** Several stale `pest` runs plus another session's run were sharing
  `laravel_testing_a/b/c`, producing `SQLSTATE[40001] 1213 Deadlock ... drop table` with 0 assertions.
  A dedicated database `laravel_testing_ch` was created for this work. Use it:
  `DB_DATABASE=laravel_testing_ch ./vendor/bin/pest <paths>`

## What is DONE (all committed to disk, nothing committed to git)

### Backend
| File | Change |
|---|---|
| `database/migrations/2026_08_21_120000_add_companies_house_filing_dates_to_business_interests_table.php` | 3 columns: `accounts_due_on`, `confirmation_statement_due_on`, `companies_house_synced_at`. **Already migrated locally**, DB reseeded after. |
| `config/services.php` | `companies_house.key` block (reads `COMPANIES_HOUSE_API_KEY`) |
| `app/Services/Business/CompaniesHouseService.php` | NEW. `fetchFilingProfile()`, `sync()`, `normaliseNumber()`, `isConfigured()`, `STALE_AFTER_DAYS = 7`. Every failure path returns null. |
| `app/Models/BusinessInterest.php` | 3 casts + `nextFiling()` accessor (soonest of the two filings, negative `days_until` when overdue) |
| `app/Observers/BusinessInterestObserver.php` | NEW. `created()` + `updated()`. **The single home** for "number changed → refresh dates", covering all 4 write paths. |
| `app/Providers/EventServiceProvider.php` | Registered in the existing `BusinessInterest::class` `$observers` entry (there was already one — merged, not duplicated) |
| `app/Services/Business/BusinessInterestService.php` | `getCompanyDeadlines()` now uses the real dates when synced, falls back to the old estimate otherwise. Every deadline carries `estimated: true/false`. |
| `app/Http/Resources/BusinessInterestResource.php` | Exposes the 3 fields + `next_filing` |
| `app/Services/NetWorth/NetWorthService.php` | Business items carry `next_filing` — feeds web AND /m from one place |
| `app/Http/Controllers/Api/BusinessInterestController.php` | Simplified — no explicit sync, the observer owns it |
| `app/Notifications/CompanyFilingDueNotification.php` | NEW. Distinct copy per filing type and per approaching/due/overdue. |
| `app/Console/Commands/SendBusinessFilingAlerts.php` | NEW. Ladder `[30,20,15,10,5,4,3,2,1,0]` + overdue `[-1,-7,-14,-30]`. Exact-day match, no state table. Refreshes stale dates (>7d). Notifies both joint owners, skips preview users and deactivated spouses. |
| `app/Console/Kernel.php` | `business:send-filing-alerts` daily at 10:45 |

### Fyn / tool catalogue (so /m and native can capture the number — Rule 19/20)
| File | Change |
|---|---|
| `fyn-memory/procedural/tool_schema/estate/create_business_interest.xai.md` | `company_number` property + in `required` (xAI strict). version 1 → 2 |
| `fyn-memory/procedural/tool_schema/estate/create_business_interest.md` | `company_number` property. version 1 → 2 |
| `fyn-memory/procedural/tool_schema/data/update_record.xai.md` | `company_number` added to the flat `fields` list — it has `additionalProperties: false`, so without this xAI literally cannot send it. version 1 → 2 |
| `app/Constants/UpdateRecordAllowlist.php` | `company_number` added to `business_interest` |
| `app/Agents/CoordinatingAgent.php` | `handleCreateBusinessInterest` validates + persists `company_number` |
| `tests/fixtures/ToolSchema/*.json`, `tests/fixtures/XaiToolSchema/*.json` | Golden masters re-captured |

### Frontend
| File | Change |
|---|---|
| `resources/js/components/NetWorth/BusinessInterestCard.vue` | `filingDue` computed + a due/overdue line, shown within 30 days. violet = approaching, raspberry = overdue (Rule 8). |
| `resources/js/components/NetWorth/BusinessInterestDetailInline.vue` | "Estimated" note on non-register deadlines |
| `resources/js/components/NetWorth/BusinessInterestForm.vue` | Company-number hint now explains what it buys |
| `resources/mobile/views/modules/NetWorthCategory.vue` | Filing-due field on the business list, reading the same server-computed `next_filing` |

### Tests — 37 + 27 green
- `tests/Unit/Services/Business/CompaniesHouseServiceTest.php` (NEW, 27) — normalisation incl. path-traversal rejection, field mapping, deprecated-field fallback, 404 / outage / no-key paths, sync, `nextFiling()`
- `tests/Feature/Business/BusinessFilingAlertsTest.php` (NEW, 37) — every ladder rung, every off-rung day, overdue rungs, joint owners, deactivated spouse, preview users, notification copy, deadline merge, all 4 observer paths

Last consolidated run was interrupted mid-way by the `.env` write; everything up to that point was green.

## NOT STARTED / OUTSTANDING, in priority order

1. **Rename the `.env` var and run the live smoke test** (see top). Nothing has touched the real API.
2. **Re-run the consolidated pass** — it was cut off mid-run:
   ```bash
   DB_DATABASE=laravel_testing_ch ./vendor/bin/pest \
     tests/Unit/Services/Business tests/Feature/Business \
     tests/Feature/AI/ToolSchemaGoldenMasterTest.php tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php \
     tests/Feature/AI/DirectWrite tests/Architecture/PreviewModeToolCatalogueTest.php \
     tests/Unit/Services/Onboarding/CaptureFocusToolCoverageTest.php tests/Unit/Services/NetWorthServiceTest.php
   ```
3. **Browser-verify per Rule 14** — not done at all. Add company number 12248522 to a business on web, confirm the
   Tax Deadlines tab shows the real dates with no "Estimated" note, confirm the card line appears. Then /m.
4. **`/m` rebuild** — `resources/mobile/` changed, so `/m` needs its bundle rebuilt before it can be verified on
   csjones. NOT done; do not build or deploy without asking CSJ.
5. **Decide on a `--dry-run` for the sweep** before it goes anywhere real.

## ⚠️ WARNING — golden masters absorbed someone else's drift

Re-capturing the fixtures also swept in **pre-existing uncommitted changes to the pension corpus** that were
already in the working tree and had never been re-recorded:

- `fyn-memory/procedural/tool_schema/savings/create_pension.md` (modified before this session started)
- `fyn-memory/procedural/tool_schema/savings/create_pension.xai.md` (same)
- adds `career_average`, `public_sector`, `spouse_pension_percent`

This belongs to the in-flight spouse-pension work (cf. the `2026_08_21_120000_correct_spouse_pension_percent_convention`
migration, which also ran during this session). **If the Companies House work is committed separately from the pension
work, those fixture hunks must travel with the pension change, not with this one.**

## Adjacent issue found, deliberately NOT fixed

`BusinessInterestService::getCompanyDeadlines()` fabricated the confirmation-statement deadline for any business
with no company number — `now() + 14 days`, hardcoded `days_until: 14`, every single time, with a note telling the
user to go check Companies House themselves. It is now marked `estimated: true` with honest copy, but the fabricated
date itself is untouched (out of scope, and it is the pre-existing behaviour for un-synced businesses).
There is no way to compute it without the incorporation or last-statement date, neither of which is stored.
**Recommend: drop that entry entirely rather than show a made-up date. CSJ's call.**
