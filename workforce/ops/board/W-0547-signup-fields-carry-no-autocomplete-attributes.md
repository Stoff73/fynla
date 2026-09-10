---
id: W-0547
title: No signup or verification-code field carries an autocomplete attribute — password managers cannot fill or save, and the code boxes have no accessible name
mission: new-user-run-2026-09-07
branch: null
owner: null
reviewers: [design-lead]
status: queued
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
