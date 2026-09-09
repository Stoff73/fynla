---
id: W-0546
title: The cookie-decline warning still claims registration will be unavailable — it is not, on csjones or production
mission: new-user-run-2026-09-07
branch: null
owner: null
reviewers: [compliance-lead, design-lead]
status: queued
severity: medium
surfaces: [web, m]
created: 2026-09-09
source: new-user run; verified on csjones 2026-09-07 and production 2026-09-09
prior_art_checked: 2026-09-09
prior_art_found: [W-0050, W-0049, W-0155]
prior_art_outcome: extends — W-0050 covered both the cookie wall and the untrue copy; the wall is genuinely fixed, the copy clause is still live
constitution_refs: [05-perimeter]
---

## Intent

Step 2 of the decline flow warns:

> "Without cookies, some features including registration will be unavailable.
> Google Analytics has been disabled."

**Tested with `fyn_cookie_consent=declined` on both environments:** the
registration form renders fully enabled and `POST /api/auth/register` is
accepted and processed normally — **201 Created** on production. A complete
account was created, verified by email, and used, entirely in the declined
state. Registration is not unavailable.

W-0050 (`done`) covered both the cookie wall and copy that was "factually
untrue", and listed as acceptance: *"the copy is removed or rewritten to
describe what is actually being consented to."* The wall is genuinely gone —
verified. The copy is still making a false claim.

Overstating the cost of refusing consent is the shape the ICO treats as a
consent dark pattern, which for a regulated fintech is worth `compliance-lead`
ruling on rather than treating as a wording tidy-up.

**Note on process:** unticked acceptance boxes on W-0050 are *not* evidence it
was closed prematurely — 47 of 73 `done` items have none ticked, so that is
normal practice here. This item rests on observed behaviour, not on the boxes.

## Acceptance

- [ ] The decline copy states only what declining actually costs.
- [ ] `compliance-lead` rules on the wording (they own it per W-0050).
- [ ] Same copy on the SPA banner and the vanilla server-rendered banner — both
      exist and currently mirror each other; keep them from one source.
- [ ] Checked on `/m`.
