---
id: W-0545
title: The password rules hint is hidden exactly when the rules are failed
mission: new-user-run-2026-09-07
branch: fix/board-w0550-w0543-w0544-w0542-w0545-w0546-w0547
owner: build-lead
reviewers: [design-lead]
status: done
severity: medium
surfaces: [web]
created: 2026-09-09
source: new-user run, csjones 2026-09-07
prior_art_checked: 2026-09-09
prior_art_found: [W-0542]
prior_art_outcome: extends — compounds W-0542; fix them together
constitution_refs: [07-quality-bar]
---

## Intent

`Register.vue` gates the password rules hint on `v-if="!errors.password"`. On the
first failure the full rules ("at least 8 characters with one uppercase letter,
one lowercase letter, one number, and one special character") are replaced by a
single partial error.

Verified in-page: with an error showing, the hint is absent — the two are never
on screen together.

Compounds W-0542: the user loses the complete rules at the exact moment they
need them, and is shown only one of the three things wrong.

## Acceptance

- [ ] The rules hint stays visible alongside validation errors.
- [ ] Checked on `/m` registration (Rule 19).

## Outcome — done, 2026-09-10

Confirmed live: `v-if="!errors.password"` on the rules hint. The hint is now always on
screen, above the messages. Browser-verified locally with a failing password: hint and
errors visible together. `/m` has no registration view.
