---
id: W-0518
title: Fyn captures salary sacrifice without asking whether the recorded employment income is before or after the pay given up
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: queued
claimed_by: null
severity: medium
surfaces: [web, m, ios]
created: 2026-08-29T08:05:00Z
claimed: null
blocked_by: []
gate: tax-compliance-reviewer
prior_art_checked: 2026-08-29
prior_art_found: [W-0204, W-0189]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
source: found while closing W-0204 — the web form asks the question, the capture tool does not
---

## Intent

W-0204 added `users.employment_income_basis` and asks for it on the web profile, under the
Employment Income field, whenever the user has a sacrificing pension. **Fyn's
`capture_salary_sacrifice` tool (`CoordinatingAgent.php:5258`) writes
`dc_pensions.salary_sacrifice` without asking the follow-up.**

So a user who declares salary sacrifice in conversation is left on the stated assumption
(`assumed_gross`) until they happen to open the profile page — and Fyn is the primary
capture path on `/m` and native, where **there is no Income Definitions panel to visit**.
Those are exactly the surfaces where the assumption is least likely to be corrected.

The assumption is defensible and is published as one, so this is not a wrong figure being
presented as certain. It is a question the app knows to ask and does not ask on two of its
three surfaces.

**Rule 20 applies:** the follow-up belongs in the tool that captures the sacrifice, once,
so every surface asks it — not copied into each client.

## Acceptance

1. `capture_salary_sacrifice` asks whether the recorded employment income is before or
   after the pay given up, and writes `users.employment_income_basis`.
2. Asked only when the answer would change a figure — the user is declaring sacrifice —
   and never re-asked once answered.
3. One mechanism, in the tool, reaching web, `/m` and native (Rule 20). Not a per-client
   question.
4. A test driving the capture path and asserting the column is written, not only that the
   tool returns a receipt.
5. The wording matches the web form's, so the two surfaces do not ask the same question two
   ways.

## Working notes

- 2026-08-29 — Found closing W-0204. Not folded into it because W-0204's scope is the
  arithmetic and the web question, and the Fyn tool catalogue is its own surface with its
  own golden masters (see the `fyn-architecture` skill).
