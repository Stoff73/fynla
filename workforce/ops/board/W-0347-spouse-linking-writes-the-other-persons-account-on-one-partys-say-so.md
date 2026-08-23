---
id: W-0347
title: CRITICAL — spouse linking writes the other person's account, and forges their consent, on one party's say-so
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
status: blocked
severity: critical
surfaces: [web, m, ios]
created: 2026-08-22T23:55:00Z
claimed: null
blocked_by: [csj-decision]
gate: csj
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0278, W-0344]
prior_art_outcome: none
constitution_refs: [05-perimeter, 03-hard-nos]
---

## Intent

Found by a `security-reviewer` pass commissioned for W-0344, which was expected to
confirm a narrow question and instead found the mechanism underneath it.

**Unclaimed and gated to CSJ deliberately.** It is outside every scope issued in cycle 4,
it spans onboarding, the family-members API and Fyn's capture tool, and it should not be
absorbed into a fix batch.

### The defect

`app/Services/Onboarding/SpouseLinkingService.php:226-254` — `linkExistingSpouse()`
writes the **named account's** `users` row, not only the caller's:

- `$lockedSpouse->spouse_id = $currentUser->id` — the target now names the caller as
  their spouse
- `$lockedSpouse->marital_status = $maritalStatus` — overwritten to `married`
- `$lockedSpouse->annual_employment_income = $data['annual_income']` — the caller sets
  the target's income
- five address fields copied in where the target's are blank

`createSpousePermissions()` (`:476-486`) then writes `status => 'accepted'`,
`responded_at => now()` on **both** `spouse_permissions` rows. **No request. No
acceptance. Nothing from the target.**

**The only precondition is that the target's `spouse_id` is NULL** — every unlinked
account.

**Reachable at `POST /api/user/family-members`** (`routes/api.php:311` → `:331-333`,
`auth:sanctum` only). `StoreFamilyMemberRequest::authorize()` returns `true`; the target
`email` is validated as `required_if:relationship,spouse` plus `email` and nothing more.
**No proof of control of that address at any layer.** Also reachable through
`CoordinatingAgent.php:1866` (`capture_spouse_details`) and `OnboardingService.php:346`.

### The same request returns the target's entire user row

`app/Http/Controllers/Api/FamilyMembersController.php:216-222` returns
`'spouse_user' => $spouseUser`, a fully hydrated Eloquent `User`. `$hidden` strips
password, MFA secret and national insurance number. **Everything else ships**: email,
date of birth, address, phone, occupation, employer, every `annual_*_income` column,
monthly and annual expenditure plus all 21 category columns, health status, smoking
status, domicile.

### Why it invalidates every gate in the application

| Gate | Survives a raw one-sided `spouse_id`? | Survives this endpoint? |
|---|---|---|
| raw `spouse_id` / `->spouse` | no | no |
| `liveSpouseId()` / `liveSpouse()` | no | no |
| `hasReciprocalSpouseLink()` | **yes** | **no — the server wrote the target's side** |
| `hasAcceptedSpousePermission()` | **yes** | **no — the permission rows are forged `accepted`** |

**Until this lands, the reciprocity and permission gates are decorative**, including the
one added in W-0344. They remain the right rules; the write path underneath them is what
is broken.

## Acceptance

1. **No account's row is ever written by another account.** Linking becomes a request the
   named party accepts or declines; `spouse_permissions` returns to meaning what its
   status column says.
2. Nothing about the target is disclosed before they accept — see **W-0348**.
3. Existing links established under the old flow are reviewed: they carry `accepted`
   rows nobody accepted.
4. Compliance sign-off — this is consent, not a bug fix.

## Working notes

(append-only)

- 2026-08-23 — **`blocked` on a CSJ decision, deliberately unclaimed.** team-lead verified
  the four load-bearing lines against the code directly and is escalating tonight.
  **Do not attempt the fix.** `linkExistingSpouse` needs an accept/decline flow — invite,
  token, expiry, notification — touching onboarding, Fyn's `capture_spouse_details` tool
  and the email pipeline. That is specified work, not a patch, and **half-fixing it would
  leave a system that looks gated and is not.**
