---
id: W-0348
title: Two endpoints return a raw Eloquent User model for another person
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: blocked
severity: high
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: [csj-decision]
gate: csj
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0347]
prior_art_outcome: none
constitution_refs: [05-perimeter, 03-hard-nos]
---

## Intent

From the W-0344 security review. Separate from W-0347 because it is independently
fixable and independently harmful: even after linking requires consent, neither response
should carry these fields.

1. `app/Http/Controllers/Api/FamilyMembersController.php:216-222` —
   `'spouse_user' => $spouseUser`, the whole model, in the 201 from
   `POST /api/user/family-members`.
2. `app/Http/Controllers/Api/SpousePermissionController.php:81` — `'spouse' => $user->spouse`,
   the raw model, from `GET /api/spouse-permission/status`. Wider than `UserResource`
   and not tier-gated.

**A raw `User` is not a safe payload.** `$hidden` (`User.php:95-105`) strips only
password, remember token, MFA secret and recovery codes, failed-login fields, national
insurance number and the Apple token. Everything else serialises: email, date of birth,
gender, marital status, full address, phone, occupation, employer, industry, employment
status, every `annual_*_income` column, monthly/annual expenditure and all 21 category
columns, health status, smoking status, target retirement age, domicile fields.

**Neither caller needs more than `{id, first_name, surname}`.** Both were almost
certainly written as "return the linked user so the client can show a name".

## Acceptance

1. Both return a resource with the minimum the client actually renders, not a model.
2. A test asserts the response body does NOT contain income, expenditure, address or
   date-of-birth keys — assert on absence, because the next field added to `users`
   otherwise ships automatically.
3. Sweep for other `=> $someOtherUser` returns of a hydrated model.

## Working notes

(append-only)

- 2026-08-23 — **`blocked` on a CSJ decision, deliberately unclaimed.** team-lead verified
  the four load-bearing lines against the code directly and is escalating tonight.
  **Do not attempt the fix.** `linkExistingSpouse` needs an accept/decline flow — invite,
  token, expiry, notification — touching onboarding, Fyn's `capture_spouse_details` tool
  and the email pipeline. That is specified work, not a patch, and **half-fixing it would
  leave a system that looks gated and is not.**
