# W-0006 — build-lead → quality-lead

## Done

- `app/Http/Requests/UpdatePersonalInfoRequest.php:57-62` — validates the real
  `health_status` / `smoking_status` enums; the dead `good_health` / `smoker`
  boolean rules are gone.
- `:32-43` — an unanswered "Select..." (which arrives as null, not '', because of
  `ConvertEmptyStringsToNull` in `app/Http/Kernel.php:104`) drops the key rather
  than validating or nulling it.
- `app/Http/Resources/UserResource.php:32-38` — exposes `health_status`,
  `smoking_status`, `education_level`. One source; no second fetch added to the
  component.
- `app/Services/UserProfile/UserProfileService.php:86-89` and
  `resources/js/store/modules/userProfile.js:188-191` — two further publishers of
  the same dead column names, corrected.
- `tests/Feature/Api/UserProfileControllerTest.php` — 4 new specs, 15 passed.

## Not done, and why

- **No live browser verification** on either account (David 16, Sarah 17). Scoped
  out; Rule 14's loop is not closed by me.
- **`education_level` accepts three values the column cannot hold — REPORTED, NOT
  FIXED.** The rule allows `doctorate`, `foundation`, `hnd`
  (`UpdatePersonalInfoRequest.php:64`) but the column is
  `enum('secondary','a_level','undergraduate','postgraduate','professional','other')`.
  Any of the three passes validation and then dies in MySQL. The form's own select
  (`HealthInformation.vue`) never offers them, so it is unreachable through the UI
  and I left it — but it is a latent 500 for Fyn or any API client. Either widen
  the column or narrow the rule; that is a decision, not a fix.
- **Two dead reads left in place, deliberately.**
  `app/Services/Protection/ComprehensiveProtectionPlanService.php:193` and `:197`
  test `isset($user->smoker)` and `isset($user->good_health)`. Both are always
  false — no such property, no accessor — so both branches fall through to
  `$profile->smoker_status` / `$profile->health_status` on the protection profile,
  which is a real and working source. They are dead code, **not** a live fault, so
  I did not touch them: making them read the `users` columns instead would change
  which source drives protection advice text, and that is a Protection-module
  decision. `RetirementActionDefinitionService.php:1656-1665` reads the protection
  profile only and is fine.

## What you need that isn't obvious from the artefacts

- **The columns are not symmetrically nullable.** `health_status` is
  `NULL DEFAULT 'yes'`; `smoking_status` is **NOT NULL** `DEFAULT 'never'`;
  `education_level` is `NULL`. That is why `smoking_status` has no `nullable` in
  the rules — a null there was a 500, not a 422, and I hit it while writing the test.
- A factory-made `User` has `health_status = 'yes'` and `smoking_status = 'never'`
  from the column defaults, but the in-memory model attribute is null until you
  `refresh()`. Any test comparing "before and after" must refresh first.
- `HealthInformation.vue` re-reads via `store.dispatch('auth/fetchUser')` after
  saving, so it renders from `UserResource`, not from the update response.
- `/m` reads `GET /api/user/profile` (`UserProfileService::getCompleteProfile`),
  which is a DIFFERENT payload from `GET /api/auth/user`. Both were publishing the
  dead names; both are fixed. If you add anything else to this family, add it to both.

## Assumptions I made

- I assumed an unanswered select means "leave the stored value alone", never
  "clear it". There is no clear-this-field affordance in the UI, so nothing is lost,
  but it does mean an API client cannot null `health_status` through this endpoint.
- I assumed the five- and four-value enums in `HealthInformation.vue:66-71` and
  `:87-91` are the canonical user-facing set, since they match the columns exactly.

## Surfaces covered / not covered

- **web** — fixed, feature-tested, NOT browser-verified.
- **`/m`** — its data source is fixed, but `/m` renders no health, smoking or
  education field anywhere (zero grep hits across `resources/mobile/`). Its
  Personal Information screen has About you / Household / Dependants / Domicile /
  Financial summary only. **This is a real parity gap and I am flagging it rather
  than building it**: adding a Health & Lifestyle section to `/m` is new feature
  work, not this bug's root cause. Whoever builds it will read the corrected source.
- **iOS** — `ios-native/Fynla/Features/Profile/PersonalInformationModels.swift`
  references these fields; I did not open or change the native surface and have
  no evidence about how it renders them. **I COULD NOT TEST THIS.**
