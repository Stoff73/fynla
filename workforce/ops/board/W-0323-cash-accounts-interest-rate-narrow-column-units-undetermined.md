---
id: W-0323
title: cash_accounts.interest_rate is decimal(5,4) with no writer and no rows — latent, and its units cannot be determined from the code
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: queued
severity: low
surfaces: [web, m, ios]
created: 2026-08-22T22:50:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-22
prior_art_found: [W-0263]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

The one row from W-0263's sweep that was **deliberately not fixed**, because
fixing it would have required guessing.

`cash_accounts.interest_rate` is `decimal(5,4)` — it stops at 9.9999. Every other
narrow rate column in the sweep was widened, because live rows proved they hold
percentages. This one cannot be settled the same way:

- the table holds **zero rows**;
- **no form request writes it** — the sweep matches against it are name
  collisions with `mortgages`, `savings_accounts` and `investment_accounts`,
  each of which was resolved to its real table;
- nothing in `app/` creates or updates a `CashAccount` with a rate.

**So `decimal(5,4)` may be perfectly correct.** This schema uses that exact type
for genuine fractions, and does so deliberately: `savings_market_rates.rate`
stores `0.0450` meaning 4.5%, `life_insurance_policies.decreasing_rate` and
`dc_pensions.employer_ni_rebate_pct` are validated `max:1`, and
`PolicyFormModal.vue:920` divides by 100 on save.

**Widening it to close a sweep row would be guessing at the units of a column
nobody writes** — the precise failure mode W-0263 exists to prevent, and the
reason its own headline needed checking twice (a stale column comment on
`mortgages.fixed_interest_rate` claimed "annual rate as decimal" and was wrong).

## Acceptance

1. Establish whether `cash_accounts` is live, dead, or planned.
2. If it is to be written by a form, decide its units and size the column to
   match **before** the first writer lands — a percentage needs at least
   `decimal(7,4)`; a fraction is correct as it stands.
3. If it is dead, say so, and consider removing the table rather than leaving a
   trap for the next sweep.

## Working notes

- 2026-08-22 build-lead: also worth a look at whether `cash_accounts` overlaps
  `savings_accounts`, which is live, populated, and now `decimal(8,4)`.
