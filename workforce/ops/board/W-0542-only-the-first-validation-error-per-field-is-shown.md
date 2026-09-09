---
id: W-0542
title: Registration shows only the first validation error per field while the server sends all of them — a user can rate-limit themselves out of signing up
mission: new-user-run-2026-09-07
branch: null
owner: null
reviewers: [design-lead, build-lead]
status: queued
severity: high
surfaces: [web]
created: 2026-09-09
source: new-user run, csjones 2026-09-07; code confirmed on origin/main
prior_art_checked: 2026-09-09
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

`POST /api/auth/register` returned **three** password errors:

```json
{"errors":{"password":[
  "The password field must be at least 8 characters.",
  "The password field confirmation does not match.",
  "Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character."
]}}
```

The UI rendered **one**. Verified in-page: length shown, mismatch not shown,
complexity not shown.

`Register.vue` renders `{{ errors.password[0] }}` — only the first array
element. Same single-element pattern on `first_name`, `last_name` and `email`.

There is also **no client-side validation**: a 4-character password with a
mismatched confirmation still round-trips to the server. `/register` sits behind
`throttle:auth-5` (`routes/api.php:157`), so a user learns the rules one at a
time, one request each, and can plausibly exhaust the throttle before ever
creating an account.

## Acceptance

- [ ] All validation messages for a field render, not just the first.
- [ ] Password match and complexity are checked client-side before submitting.
- [ ] Applied to every field on the form, not only `password`.
- [ ] Checked on `/m` registration (Rule 19).
- [ ] A test pins that a multi-error response renders every message.
