---
id: W-0323
title: cash_accounts.interest_rate is decimal(5,4) with no writer and no rows — latent, and its units cannot be determined from the code
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0025-cycle4-validation-vs-schema-range.md
owner: build-lead
status: review
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

## Done 2026-08-25 — the units were determinable after all

### Acceptance 1 — live, dead, or planned? All three, and that is the defect

| Reading | Evidence |
|---|---|
| **Live** | `HouseholdPlanningService:505` sums it into household net worth; `UserModuleTrackingService:133` reads counts and balances; observer in `EventServiceProvider:121`; `RetentionPurgeService:331`; relations on `User` and `Household`; gained `joint_owner_id` on 2026-02-19 |
| **Planned replacement** | `migrate:savings-to-cash` — *"Migrate legacy savings_accounts to new cash_accounts table"* |
| **Distinct by design** | `CashAccount` docblock — *"tracks current/transactional accounts… **NOT** part of the savings recommendation engine. Savings accounts are managed via the `SavingsAccount` model."* |

So it is **not dead**, and acceptance 3's "consider removing the table" is off the
table: deleting it would break two live services.

The model docblock and `HouseholdPlanningService` agree with each other — separate
concepts, summed as separate asset classes. The console command contradicts both.
That contradiction is raised separately as **W-0489**, because it is worth more
than this item: the command is registered, has no idempotency guard, never removes
its source rows, and would therefore **double-count every household's cash**.

### Acceptance 2 — the units, determined rather than guessed

The item searched form requests and found no writer. The writer is a console
command, and it settles the question by copying:

    // MigrateSavingsToCash.php:159
    'interest_rate' => $account->interest_rate,   // from savings_accounts

`savings_accounts.interest_rate` holds **percentages**, on three independent
grounds:

- live values 0.0000 to 5.1000, mean 1.886 across 25 rows — 5.1 meaning 5.1%;
- its validation message says *"cannot exceed 20%"*;
- W-0263 widened it to `decimal(8,4)` for that reason.

20% does not fit in `decimal(5,4)`, which stops at 9.9999. So the column was too
narrow **under the only writer it has**, and `decimal(8,4)` — matching
`savings_accounts` — is the evidenced size, not a chosen one.

Widening is also the safe direction under either reading of the table: `decimal(8,4)`
stores fractions perfectly well, so a future writer storing 0.051 breaks nothing.
Narrowing would have been the risky call, and is not what was needed.

**The comment was wrong too.** It read *"Annual interest rate as decimal"* — the
identical stale boilerplate that sat on `mortgages.fixed_interest_rate` and
`variable_interest_rate` until W-0263 found it wrong there. This item's own text
warned about that exact trap. Now reads *"Annual interest rate as a percentage
(4.5 = 4.5%)"*.

### Change

`database/migrations/2026_08_25_120000_widen_cash_accounts_interest_rate_to_match_savings.php`,
following W-0263's pattern exactly — raw `MODIFY COLUMN` because doctrine/dbal is
absent and `->change()` drops unstated attributes; full definitions on both sides so
nullability survives; `down()` restores the original.

### Verified

Migration applied. `information_schema` after:

| Table | Type | Nullable | Comment |
|---|---|---|---|
| `cash_accounts` | `decimal(8,4)` | YES | Annual interest rate as a percentage (4.5 = 4.5%) |
| `savings_accounts` | `decimal(8,4)` | NO | — |

`php -l` and `pint` clean.

### Noted in passing

`database/schema/mysql-schema.sql:3389` still declares
`savings_accounts.interest_rate` as `decimal(5,4)`, while the live column is
`decimal(8,4)`. The dump predates W-0263 and has not been regenerated. Harmless
for a fresh install — the dump loads first and the widening migration runs on top —
but the dump is not a reliable source for a question like this one, which is worth
knowing for the next sweep that consults it.

### Gaps

- **No functional verification is possible.** The table has no writer reachable from
  the application, no rows, and no UI. There is nothing to exercise; the change is
  verified at the schema level only, which is the whole of it.
- **The double-count in W-0489 is not fixed here**, only raised.
