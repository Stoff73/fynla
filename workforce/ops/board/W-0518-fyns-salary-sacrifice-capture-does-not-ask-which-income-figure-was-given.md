---
id: W-0518
title: Fyn captures salary sacrifice without asking whether the recorded employment income is before or after the pay given up
mission: persona-run-peak_earners-2026-08-20
branch: null
owner: null
reviewers: [tax-compliance-reviewer]
status: done
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

## 2026-09-01 — CLOSED

**Root cause was the schema, not the handler.** Evidence gathered at each boundary
before anything was changed:

| Boundary | Evidence | Verdict |
|---|---|---|
| Tool schema | `capture_salary_sacrifice.xai.md` — three properties, `strict: true`, `additionalProperties: false` | **fails here** — no slot, so the model *cannot* return the answer |
| Dispatch | `CoordinatingAgent.php:1166` passes `$input` through | passes |
| Handler | `:5436-5443` built a two-key payload and wrote to the **pension** | fails, downstream of the schema |
| Column | `users.employment_income_basis` `enum('gross','post_sacrifice')` nullable, default NULL | correct — NULL is "never asked", published as `assumed_gross` |

Fixing only the handler would have left the model with nothing to send. Both halves
moved together.

**Acceptance 1** — the schema carries `employment_income_basis` and the handler writes
`users.employment_income_basis` (`CoordinatingAgent.php:5443-5470`).

**Acceptance 2** — two gates, both mirroring the web form: written only when the user is
declaring sacrifice (`IncomeOccupation.vue:242` gates the web question on the same
condition), and never when an answer is already on file. Re-asking a settled question and
preferring the newer answer would let a conversational misreading replace something the
user typed into a form.

**Acceptance 3** — one mechanism, in the tool. Web, `/m` and native all reach the same
handler; nothing was copied into a client.

**Acceptance 4** — `tests/Feature/AI/DirectWrite/CaptureSalarySacrificeTest.php`, driving
`executeTool()` and asserting the **column**, not the receipt. Written before the fix and
red at 3 of 7; now **7 passed**. Five cases: the write, the not-sacrificing gate, the
never-re-ask gate, an invalid value refused, and the schema itself carrying the property —
the last because a handler-only fix would pass every other assertion.

**Acceptance 5** — the description carries the web form's own words: *"Is that figure
before or after the pay you give up?"* and *"It decides whether their Annual Allowance is
reduced."*

**The dependency this turned on, found before editing:** the tool appears in **four**
golden masters. Both schema variants were changed (`.xai.md` under `strict: true` needs
`anyOf: [enum, null]`, the pattern `capture_spouse_household_data.xai.md:25` already
uses; the anthropic variant takes a plain enum), and the masters were regenerated through
the sanctioned `CAPTURE_TOOL_SCHEMA_GOLDEN=1` command — **additions only, 8 lines each,
no existing tool altered**. Both schema versions bumped 2 → 3.

Tests: 7 passed on the item's file, 10 passed + 1 skipped on the golden masters,
**171 passed** across `tests/Feature/AI/DirectWrite/` and the capture-integrity test.

**Not done:** the `tax-compliance-reviewer` gate on this item's front matter has not been
run — no agent was dispatched, per the session instruction. No browser or live-Fyn drive:
the capture path is exercised through `executeTool()`, not through a real conversation.
