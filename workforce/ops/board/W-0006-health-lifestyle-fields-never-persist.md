---
id: W-0006
title: Health & Lifestyle form silently drops health_status and smoking_status — never persisted
mission: persona-run-peak_earners-2026-08-20
branch: branches/fixes/F-0001-batch-c-retirement-profile-gates.md
owner: build-lead
status: handoff
surfaces: [web, m, ios]
created: 2026-08-20T21:55:00Z
claimed: 2026-08-21T09:10:00Z
blocked_by: []
gate: null
handoff_to: quality-lead
prior_art_checked: 2026-08-21T09:10:00Z
prior_art_found: ["app/Http/Resources/UserResource.php — the one source every client reads for the user record", "app/Http/Requests/UpdatePersonalInfoRequest.php — the one validator on PUT /api/user/profile/personal", "app/Services/UserProfile/UserProfileService.php:86-89 — second publisher of the same dead column names"]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **Pass A** (desktop web module UI forms, local
`localhost:8000`), account **David Jones (primary)**, user id 16.

**Surface:** desktop web, `/settings/health` (`Settings` → Health tab).

### Expected

Persona file `tests/Persona/peak_earners.md:26-28`:

| Field | Value |
|---|---|
| Health Status | Yes |
| Smoking Status | Never |
| Education Level | Postgraduate |

After submitting the Health & Lifestyle form with Health = "Yes", Smoking = "Never
smoked", Education = "Postgraduate Degree", the page should display those three
values, and `users.health_status`, `users.smoking_status`, `users.education_level`
should hold `yes`, `never`, `postgraduate`.

### Actual

Form submits with no error, the edit panel closes as if saved. After a **hard page
reload**, all three still read **"Not specified"**.

Database after submit:

```
users.health_status    => NULL      <-- expected 'yes'
users.smoking_status   => NULL      <-- expected 'never'
users.education_level  => 'postgraduate'   (persisted, but not displayed)
```

So this is two faults in one form:

**Fault 1 — data loss (health_status, smoking_status).**
`resources/js/components/UserProfile/HealthInformation.vue:239` posts
`formData = { health_status, smoking_status, education_level }` to
`userProfileService.updatePersonalInfo`.

`app/Http/Requests/UpdatePersonalInfoRequest.php:57-58` whitelists:

```php
'good_health' => ['sometimes', 'nullable', 'boolean'],
'smoker'      => ['sometimes', 'nullable', 'boolean'],
```

There are **no `good_health` / `smoker` columns on `users`** — the columns are
`health_status`, `smoking_status`, `education_level` (verified via
`Schema::getColumnListing('users')`). Neither `health_status` nor `smoking_status`
appears anywhere in the rule set, so `$request->validated()` returns them stripped.
`app/Http/Controllers/Api/UserProfileController.php:94-97` passes exactly
`$request->validated()` into the service, so both values are discarded silently —
no validation error, no 422, no user-visible failure.

The rules are also the wrong *type*: `boolean` cannot accept the five-value health
enum (`yes`, `yes_previous`, `no_previous`, `no_existing`, `no_both`) or the
four-value smoking enum (`never`, `quit_recent`, `quit_long_ago`, `yes`) that the
select elements emit (`HealthInformation.vue:66-71`, `:87-91`).

**Fault 2 — display (education_level).**
`HealthInformation.vue:164-170` builds `displayData` from
`store.getters['auth/currentUser']`, which is `GET /api/auth/user` →
`app/Http/Resources/UserResource.php`. That resource exposes **none** of
`health_status`, `smoking_status`, `education_level` (grep returns zero hits across
all 140 lines; there is no `merge()` fallback). So even the value that *did* persist
renders through `formatEducationLevel(undefined)` → `'Not specified'`
(`HealthInformation.vue:209-218`).

Fixing Fault 1 alone will not make the page display correctly — Fault 2 must be
fixed too or the user will still see "Not specified" after a correct save.

### Repro

1. Register/login as any real (non-preview) user on `localhost:8000`.
2. Go to `/settings/health`, click **Edit**.
3. Health = "Yes", Smoking = "Never smoked", Education = "Postgraduate Degree".
4. Click **Save Changes** — panel closes, no error.
5. Hard-reload `/settings/health` — all three read "Not specified".
6. `php artisan tinker` → `User::where('email', ...)->first()->health_status` is `NULL`.

### Blast radius beyond this page

`health_status` / `smoking_status` feed protection premium estimation and life
expectancy (per the page's own "Why this matters" copy and
`Current State/Protection.md`). With both permanently NULL for every user who enters
them through this form, those downstream figures run on missing inputs.

### Evidence

Screenshot: `tests/Persona/20-08-2026_run/pass-a-web/01-web-health-not-specified-W-0006.jpg` — all three fields reading "Not specified" after a hard reload (Sarah's account; identical on David's).
Report: `reports/R-02-pass-a-verification.md` RED-6.

## Acceptance

- [ ] `UpdatePersonalInfoRequest` validates `health_status` and `smoking_status`
      against the real enums the form emits, and the dead `good_health` / `smoker`
      rules are removed (no such columns exist).
- [ ] Submitting the form persists all three columns; verified by DB row, not by UI.
- [ ] `UserResource` exposes `health_status`, `smoking_status`, `education_level`
      so `HealthInformation.vue` can render them — **one** source, per Rule 20; do
      not add a second fetch inside the component.
- [ ] After a hard reload the page shows "Yes, good health" / "Never smoked" /
      "Postgraduate Degree".
- [ ] Checked on `/m` and iOS: whatever surface renders health/smoking/education
      reads the same corrected source (Rule 19 / Rule 20).
- [ ] Re-verified live in the browser by the persona run, both accounts.

## Working notes

(append-only)

- 2026-08-20 persona-tester: raised from Pass A. Root cause diagnosed to
  file:line above; not fixed by me — routed to build-lead.

- 2026-08-21 build-lead: FIXED. Both faults, plus a third instance of the same
  dead-column disease.

  **Fault 1 — data loss.** `app/Http/Requests/UpdatePersonalInfoRequest.php:57-62`
  now validates `health_status` against the real five-value enum and
  `smoking_status` against the real four-value enum; the dead `good_health` /
  `smoker` boolean rules are gone. Confirmed against the live schema:
  `health_status enum('yes','yes_previous','no_previous','no_existing','no_both')
  NULL DEFAULT 'yes'`, `smoking_status enum('never','quit_recent','quit_long_ago',
  'yes') NOT NULL DEFAULT 'never'`.

  `smoking_status` is deliberately NOT `nullable` in the rules because the column
  is NOT NULL — a null there was a 500, not a 422.

  **An unanswered select had to be handled explicitly.** The selects submit `''`
  for "Select...", which the global `ConvertEmptyStringsToNull` middleware
  (`app/Http/Kernel.php:104`) turns into null before the request is seen. Left
  alone that 422s the whole form (taking the answered fields with it) or 500s on
  the NOT NULL column. `prepareForValidation()` now drops those keys entirely
  (`UpdatePersonalInfoRequest.php:32-43`) — unanswered means "leave it alone",
  never "clear it".

  **Fault 2 — display.** `app/Http/Resources/UserResource.php:32-38` now exposes
  `health_status`, `smoking_status` and `education_level`. One source, per Rule 20:
  no second fetch was added to `HealthInformation.vue`, which already reads
  `store.getters['auth/currentUser']` ← `GET /api/auth/user` ← this resource
  (`AuthController.php:492`).

  **Third instance, same disease, found while fixing.**
  `app/Services/UserProfile/UserProfileService.php:86-89` — `getCompleteProfile()`
  published `'good_health' => $user->good_health` and `'smoker' => $user->smoker`,
  both permanently null, and omitted the real columns. That payload is
  `GET /api/user/profile`, which is what **`/m` reads**
  (`resources/mobile/views/PersonalInformation.vue:162`). Corrected to
  `health_status` / `smoking_status`. `resources/js/store/modules/userProfile.js:188-191`
  carried the same two dead keys into the Vuex `personalInfo` object; also corrected.

  **`/m` and iOS (Rule 19) — checked.** `/m` renders no health, smoking or
  education field anywhere (`grep` over `resources/mobile/` returns zero hits); its
  Personal Information screen shows About you / Household / Dependants / Domicile /
  Financial summary only. So there was nothing rendering the wrong value — but its
  data source was one of the two publishers of the dead column names, and that is
  now fixed, so a `/m` Health section would read the corrected source from day one.
  Building that section is a separate piece of work, not this bug's root cause;
  flagged in the handoff note.

  **Tests:** `tests/Feature/Api/UserProfileControllerTest.php` — persists all three,
  rejects out-of-enum values, treats the unanswered select as no answer, and
  exposes all three on `GET /api/auth/user`. 15 passed.

  **NOT done by me — see the handoff note:** the `education_level` rule accepts
  three values the column enum cannot hold, and two dead reads survive in
  `ComprehensiveProtectionPlanService`.

- 2026-08-21 build-lead: batch branch document (also the Rule 22 context handover)
  written to `workforce/branches/fixes/F-0001-batch-c-retirement-profile-gates.md`.
  It carries the dispatch verbatim plus both amendments, per-item file:line
  evidence, test output, decisions taken with reasoning, dead ends ruled out,
  environment state (no throwaway user was created — nothing to tear down), and
  the full W-0018 argument. Every Pest run re-verified under
  `DB_DATABASE=laravel_testing_c` after the shared-database deadlocks.

## Addendum — 2026-08-21, persona-tester (onboarding sweep)

**A second write path for these same three fields exists, and it works.**

Registering a throwaway account and completing onboarding step 1 ("About You",
which carries a **Health & Lifestyle Information** block) persisted all three columns
correctly on the first attempt:

```
health_status  = "yes"
smoking_status = "never"
education_level = "postgraduate"
```

Verified on `users.id 20` after `POST /api/onboarding/step` returned 200. The three
dropdowns on that step offer values that match the column enums exactly
(`yes|yes_previous|no_previous|no_existing|no_both`,
`never|quit_recent|quit_long_ago|yes`,
`secondary|a_level|undergraduate|postgraduate|professional|other`).

**Why this matters for the fix (Rule 20).** The same three fields are written by two
different mechanisms — the onboarding step endpoint, which is correct, and
`/settings/health` via `UpdatePersonalInfoRequest`, which is not. The fix should
therefore **consolidate onto the working path's validation and mapping**, not add a
second correct-but-separate implementation in the settings controller. Whatever the
onboarding step does with these three fields is the behaviour to converge on.

It also means the display half of this item is narrower than it looked: a user who
completes onboarding **has** the data in the database already, so `HealthInformation.vue`
showing "Not specified" is a read/expose failure for those users, not a missing value.
`UserResource` still needs to expose the three fields either way.

Evidence: `tests/Persona/20-08-2026_run/pass-a-web/24-web-onboarding-step1-filled.png`;
run report R-11.
