---
id: W-0011
title: Free-tier users cannot save monthly expenditure at all — Simple View always sends the detailed payload and trips the Premium gate
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
status: gated
surfaces: [web, m, ios]
created: 2026-08-20T23:20:00Z
claimed: 2026-08-21T09:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
certification: CANNOT CERTIFY 2026-08-23 quality-lead — see ops/handoffs/quality-lead/cycle4-certification-2026-08-23.md
prior_art_checked: 2026-08-21T09:10:00Z
prior_art_found: ["app/Services/Tiers/TeaserGate.php::allows — the one capability predicate (CSJ decision 2026-08-19)", "database/seeders/TierConfigurationSeeder.php:36-37,79 — expenditure=full on Free, expenditure_detailed=none", "app/Agents/CoordinatingAgent.php handleUpdateProfile section=expenditure — already writes a simple total for any tier", "app/Http/Controllers/Api/AuthController.php:465-475 tier_flags.capabilities — capability matrix already published to clients"]
prior_art_outcome: extend
constitution_refs: [06-commercials, 07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **David Jones (primary)**, user id 16, `tier=free`.

**Surface:** desktop web, `/valuable-info?section=expenditure`.

Severity: **high**. Expenditure is not a nice-to-have field — emergency fund runway,
protection need, cashflow surplus and retirement income need are all derived from it.
On the free tier it can never be recorded by any route.

### Expected

Persona file `tests/Persona/peak_earners.md:25` — Monthly Expenditure £2,500.

A free-tier user offered a "Simple View" with a single "Monthly Expenditure" field
should be able to save that figure. The Premium restriction is on the **detailed
15-category breakdown**, not on knowing what you spend.

### Actual

Both routes fail, and the second one fails invisibly:

**Detailed View** — the form renders all 22 category inputs to a free user and accepts
values. On save the API returns 403 and the page shows "Detailed expenditure is part
of Premium." Nothing is written. (Gating after the fact rather than before is poor,
but at least it is visible.)

**Simple View** — offered and fully usable on free tier. Entering 2500 and clicking
Save Changes closes the form with no error message. Nothing is written:

```
users.monthly_expenditure    = NULL
users.annual_expenditure     = NULL
users.expenditure_entry_mode = 'category'   (never flipped to 'simple')
expenditure_profiles         : no row
```

Captured request (XHR hook in the live page):

```
PUT /api/user/profile/expenditure   →  403
request: {"use_simple_entry":true,"expenditure_entry_mode":"simple",
          "use_separate_expenditure":false,"monthly_expenditure":2500,
          "annual_expenditure":30000,"rent":0,"utilities":0,
          "food_groceries":0,"transport_fuel":0,"healthcare_medical":0,
          "insurance":0,"mobile_phones":0,"internet_tv":0,"subscriptions":0, ...}
response: {"error":"capability_denied","capability":"expenditure_detailed",
           "required_tier":"premium",
           "message":"Detailed expenditure is part of Premium."}
```

### Root cause — two halves that only fail together

**Frontend** `resources/js/components/UserProfile/ExpenditureForm.vue:2233-2246`.
`handleSave()` builds `saveData` and then appends every detailed category
unconditionally:

```js
allFields.forEach(field => {
  saveData[field.key] = formData.value[field.key] || 0;
});
```

There is no `if (!useSimpleEntry.value)` around it, so a Simple View save carries all
22 detailed keys as zeros.

**Backend** `app/Http/Controllers/Api/UserProfileController.php:147-150`:

```php
if (! $this->canUseDetailedExpenditure($user)
    && array_intersect(array_keys($request->all()), self::DETAILED_EXPENDITURE_FIELDS) !== []) {
    return $this->detailedExpenditureDenial();
}
```

The gate fires on the **presence of a key**, not on whether any detailed value is
non-zero and not on the requested mode. A simple-mode request carrying zeroed
category keys is indistinguishable from a detailed-mode request.

Either half alone would be harmless. Together they lock every free-tier user out of
recording expenditure by any route, while still showing them a Simple View that looks
like it works.

The Simple View failure is also **silent** — 403 with a message the UI does not
surface on that path.

### Repro

1. Free-tier account (any new registration).
2. `/valuable-info?section=expenditure` → Edit → **Simple View**.
3. Enter 2500 in Monthly Expenditure → Save Changes.
4. Form closes, no error. `users.monthly_expenditure` is still NULL.
5. Network shows `PUT /api/user/profile/expenditure` → 403 `capability_denied`.

### Impact on this persona run

David's £2,500 monthly expenditure **could not be entered**, and neither could the
15-category breakdown (`peak_earners.md:486-504`). Everything downstream of
expenditure is therefore unverifiable in Pass A on a free account: emergency fund
runway, protection gap analysis, cashflow surplus, goal affordability, and the
retirement income need.

### Evidence

**No screenshot** — entry-phase finding. The captured 403 request/response pair above is the evidence. Downstream effect IS visible on `/m`, where Savings and Investment render LOCKED with "Monthly expenditure is required".
Report: `reports/R-01-pass-a-entry.md`.

## Acceptance

- [ ] Simple View sends only the simple fields — no detailed category keys — so a
      free-tier user can save `monthly_expenditure`.
- [ ] Backend gate distinguishes "detailed entry attempted" from "simple entry with
      incidental zero keys"; consider keying it on `expenditure_entry_mode` rather
      than key presence.
- [ ] A free-tier user entering 2500 in Simple View persists
      `monthly_expenditure = 2500`, `annual_expenditure = 30000`,
      `expenditure_entry_mode = 'simple'` — verified by DB row.
- [ ] The Detailed View is not rendered as an editable form to a user who cannot save
      it; gate before entry, not after submit.
- [ ] Any 403 on this path surfaces to the user rather than closing the form silently.
- [ ] `/m` and iOS expenditure entry checked for the same payload shape (Rule 19).
- [ ] Re-verified live in the browser by the persona run.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Both halves diagnosed to file:line
  above; not fixed by me — routed to build-lead.
- Note for whoever picks this up: the commercial question (is simple expenditure
  meant to be free?) is CSJ's, not engineering's. If simple expenditure IS meant to be
  Premium too, then the defect is that the Simple View is offered to free users at
  all — but either way the current silent 403 is wrong.

- 2026-08-21 build-lead: FIXED, both halves. **The commercial question the item
  raised is already answered in the enforcing layer, so no CSJ decision was needed:**
  `database/seeders/TierConfigurationSeeder.php:36-37` gives Free
  `'expenditure' => 'full'` and `'expenditure_detailed' => 'none'`; line 79 gives
  Premium both as `full`. Simple expenditure IS free by design. Fyn's own handler
  already behaved that way — `CoordinatingAgent::handleUpdateProfile`
  (`section === 'expenditure'` with a monthly total) writes
  `expenditure_entry_mode = 'simple'` for any tier with no gate, while
  `handleSetExpenditure` (`:4952`) gates only the category write. The HTTP
  controller was the one mechanism that disagreed.

  **Frontend** `resources/js/components/UserProfile/ExpenditureForm.vue:2254-2262`
  — the unconditional `allFields.forEach` that appended all 22 category keys is now
  inside `if (!useSimpleEntry.value)`. Same guard applied to both spouse payloads
  (`:2280-2284`, `:2296-2300`), which had the identical bug.

  **Backend** `app/Http/Controllers/Api/UserProfileController.php` —
  `guardDetailedExpenditure()` (`:497-533`) replaces the key-presence test at both
  call sites (`updateExpenditure` `:157-159`, `updateSpouseExpenditure` `:387-389`).
  It now:
  - lets a user with the capability straight through;
  - for a simple-entry request (`use_simple_entry` true OR
    `expenditure_entry_mode === 'simple'`) **strips** the category keys and
    proceeds — the incidental zeros are dropped, never written, so a Free user's
    stored categories are not cleared by a form that never showed them;
  - denies a genuine detailed attempt exactly as before.

  `use_simple_entry` and `use_separate_expenditure` were removed from
  `DETAILED_EXPENDITURE_FIELDS` (`:26-37`). `use_simple_entry` being in that list
  was the sharpest edge of the bug: the flag that says "no categories here" was
  itself enough to trip the gate.

  **Gate before entry, not after submit.** `auth/hasCapability` was added as ONE
  getter mirroring `TeaserGate::allows` including the admin/preview bypass
  (`resources/js/store/modules/auth.js:36-42`), reading the capability matrix
  already published at `AuthController.php:470`. `ExpenditureForm` uses it to hide
  the Simple/Detailed toggle entirely (`:397-400`), to default to Simple View
  (`:1371-1377`), to override a stored `'category'` mode (`:2212-2217`), and to stop
  an AI form-fill forcing Detailed View (`:2383-2385`).

  **The silent-403 line in the item is not quite right.** The 403 message DOES
  surface: `userProfile.js:289-293` rethrows and
  `ExpenditureOverview.vue:123-125` renders `err.response.data.message` in the
  banner at the top of the card. What the user saw was a banner above the fold
  they had scrolled past. Either way the 403 no longer happens.

  **`/m` and iOS (Rule 19) — checked, no equivalent form.**
  `resources/mobile/views/Expenditure.vue` is read-only (`apiGet` only, 104 lines);
  entry is via Fyn, which already allowed the simple total for any tier. iOS is the
  same mechanism. No mobile payload change was needed.

  **Throwaway user:** none created. The tier behaviour is reproduced in tests with
  `User::factory()->create()` (free) vs
  `User::factory()->withActivePremiumSubscription()->create()`, so no live free-tier
  account was needed and none was made.

  **Tests:** `tests/Feature/Fyn/DetailedExpenditureGateTest.php` — free user saves
  a simple total carrying zeroed categories; a genuine detailed save still 403s;
  the incidental zeros are not written. 7 passed.
  `resources/js/components/__tests__/UserProfile/ExpenditureSimpleEntry.spec.js` —
  payload shape in both modes, toggle visibility, stored-mode override. 5 passed.

- 2026-08-21 build-lead: batch branch document (also the Rule 22 context handover)
  written to `workforce/branches/fixes/F-0001-batch-c-retirement-profile-gates.md`.
  It carries the dispatch verbatim plus both amendments, per-item file:line
  evidence, test output, decisions taken with reasoning, dead ends ruled out,
  environment state (no throwaway user was created — nothing to tear down), and
  the full W-0018 argument. Every Pest run re-verified under
  `DB_DATABASE=laravel_testing_c` after the shared-database deadlocks.
