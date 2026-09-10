---
id: W-0547
title: No signup or verification-code field carries an autocomplete attribute — password managers cannot fill or save, and the code boxes have no accessible name
mission: new-user-run-2026-09-07
branch: fix/board-w0550-w0543-w0544-w0542-w0545-w0546-w0547
owner: build-lead
reviewers: [design-lead]
status: done
severity: medium
surfaces: [web, m]
created: 2026-09-09
source: new-user run, csjones 2026-09-07
prior_art_checked: 2026-09-09
prior_art_found: []
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

All five registration inputs report `autocomplete: null` **and** `name: null`.
WCAG 2.1 AA §1.3.5 (Identify Input Purpose) expects `given-name`,
`family-name`, `email`, `new-password`, `new-password`. With no `name` either,
the heuristic fallback is gone too, so a password manager can neither reliably
fill the form nor offer to save the credential it just created.

The six verification-code boxes have the same problem plus one more: no
`aria-label` on any of them, so a screen reader announces six unlabelled text
fields; and no `autocomplete="one-time-code"`, which is what enables automatic
code autofill on iOS, Android and macOS. That matters most on `/m` and native,
where the code arrives on the same device.

Form labels themselves are correctly associated via `label[for]` -> `id` — that
part was checked and is fine.

## Acceptance

- [ ] Registration fields carry correct `autocomplete` values.
- [ ] Code boxes carry `autocomplete="one-time-code"` and an accessible name.
- [ ] Login and password-reset forms checked for the same gap.
- [ ] Checked on `/m` (Rule 19).

## Outcome — done, 2026-09-10

Confirmed live: no `name` or `autocomplete` on the five registration inputs; the code
boxes had `inputmode` only.

- `Register.vue`: given-name, family-name, email, new-password ×2, each with a `name`.
- `Login.vue`: email, current-password. `/m` `Login.vue` already had both (parity holds).
- `VerificationCodeModal.vue` and both code groups in `ForgotPasswordModal.vue`: each box
  has an accessible name ("… code digit N of 6"), a `name`, and the first box carries
  `autocomplete="one-time-code"` (the others `off`) so iOS, Android and macOS offer the
  code. The reset flow's email and new-password / confirm inputs are attributed too.
- Browser-verified locally on /register and the verification modal (attributes read off
  the DOM).
