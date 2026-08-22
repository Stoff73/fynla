---
id: W-0052
title: REGRESSION — creating any investment account returns 500, "Column 'advisor_fee_percent' cannot be null"; introduced by the W-0008 fix
mission: persona-run-peak_earners-2026-08-20
branch: workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md
owner: build-lead
status: handoff
severity: critical
surfaces: [web, m, ios]
created: 2026-08-21T15:35:00Z
claimed: null
blocked_by: []
gate: null
handoff_to: null
prior_art_checked: 2026-08-21
prior_art_found: [W-0008]
prior_art_outcome: regression-of
constitution_refs: [07-quality-bar]
---

## Intent

Found by: persona run `peak_earners`, **independent confirmation of Batch A**, local
`localhost:8000`. Throwaway account `users.id 20` (Priya Raman), free tier, with a
linked spouse.

**Surface:** `/net-worth/investments` → Add Account → Add Account (create path).

**This is a regression of the W-0008 fix**, not a pre-existing defect. It was found
while verifying W-0014, on the very next action after the joint-owner check passed.

### Expected

Creating an investment account succeeds. Where no adviser fee is entered, the column
takes its database default of `0.0000`.

### Actual

```
POST /api/investment/accounts  →  500

SQLSTATE[23000]: Integrity constraint violation: 1048
Column 'advisor_fee_percent' cannot be null
```

**No investment account can be created at all.** The modal stays open; the only
user-visible signal is that nothing happens.

### Root cause — a `nullable` rule added for a `NOT NULL` column

| Layer | State |
|---|---|
| Column | `advisor_fee_percent decimal(5,4)` **`NOT NULL`**, default `'0.0000'` |
| Validation | `StoreInvestmentAccountRequest.php:59` — **`['nullable', 'numeric', 'min:0', 'max:10']`**, added by the W-0008 fix (confirmed in `git diff`) |
| Frontend | `AccountForm.vue:971` — `submitData.advisor_fee_percent = null;` explicitly, when the additional-information panel is collapsed |
| Model | `app/Models/Investment/InvestmentAccount.php:62` fillable, `:216` cast `decimal:4` |

Before the W-0008 change, `advisor_fee_percent` was **not in the rules**, so
`validated()` stripped it, the INSERT omitted the column, and the database default
`0.0000` applied. Adding a `nullable` rule is exactly what lets the frontend's explicit
`null` through into a `NOT NULL` column.

The `git diff` comment on the new rule reads "the column and every display of it
already existed, only the way to enter it did not" — correct about the column
existing, but the column's **nullability** was not checked against the rule.

### Impact

**Blocking.** Investments are one of the seven modules and four of this persona's
records are investment accounts. A faithful Pass A cannot enter a single one. It also
blocks the joint-investment checks that Batch A's whole W-0014/W-0015 consolidation
exists to prove — the joint share cannot be verified on a new record if no record can
be created.

Any user on any environment where this has landed cannot add an investment account.

### Repro

1. Any account, free or premium. `/net-worth/investments`.
2. **Add Account** (create path, not edit). Account Type "General Investment Account",
   provider "AJ Bell", Current Value 95000. Leave the additional-information panel
   collapsed so no adviser fee is entered.
3. Click **Add Account**.
4. `POST /api/investment/accounts` returns **500** with the integrity-constraint message
   above. No account is created and no error is shown to the user.

### Evidence

- Live response body captured in-browser:
  `{"success":false,"message":"SQLSTATE[23000]: Integrity constraint violation: 1048 Column 'advisor_fee_percent' cannot be null (Connection: mysql, SQL: insert into \`investment_accou…"}`
- `app/Http/Requests/StoreInvestmentAccountRequest.php:59` (and its `git diff`)
- `resources/js/components/Investment/AccountForm.vue:247, 971, 1208`
- `app/Models/Investment/InvestmentAccount.php:62, 216, 314`
- Column definition: `advisor_fee_percent decimal(5,4) NOT NULL DEFAULT '0.0000'`
- `tests/Persona/20-08-2026_run/pass-a-web/32-web-investment-create-joint-owner-select-present.png`

## Acceptance

- [ ] Creating an investment account with no adviser fee succeeds and stores
      `advisor_fee_percent = 0.0000`.
- [ ] Decide ONE way and apply it consistently: either coalesce `null → 0` on the way in
      (`prepareForValidation` or the normaliser), or make the column nullable, or stop
      the frontend sending an explicit `null`. Do **not** do all three.
- [ ] The **update** path is checked for the same mismatch —
      `UpdateInvestmentAccountRequest` was modified by the same fix.
- [ ] The other fields nulled alongside it at `AccountForm.vue:965-972` —
      `country`, `platform`, `planned_lump_sum_amount`, `planned_lump_sum_date`,
      `platform_fee_percent`, `platform_fee_amount` — are each checked against their
      column nullability. `platform_fee_type` and `platform_fee_frequency` are also
      `NOT NULL`, so this may not be the only one.
- [ ] A 500 on this path surfaces an error to the user instead of a silently open modal.
- [ ] A test pins account creation with the additional-information panel collapsed —
      the exact shape that ships the nulls.
- [ ] Re-verified live in the browser by the persona run, both create and edit.

## Working notes

**Method note worth keeping.** This was caught in the first minute of independently
re-testing a completed batch, on the action immediately after the check I was actually
sent to do. Batch A verified its own work and reported the joint-share behaviour
correctly — the joint-owner select really does work — but the create path it depends on
was broken by a sibling fix in the same window. That is the argument for independent
re-verification in one line.

Grep for other `nullable` validation rules whose column is `NOT NULL` without a
frontend guarantee — this is a silent class and W-0026 was the same shape in reverse
(validated but not fillable).

- 2026-08-21 build-lead: **FIXED. My regression, and the reachable surface is 28 columns, not one.**

  ### Root cause, confirmed exactly as reported

  `advisor_fee_percent` is `decimal(5,4) NOT NULL DEFAULT '0.0000'`. Before W-0008
  the field was in no rules array, so `validated()` stripped it, the INSERT omitted
  the column and the DB default applied. My W-0008 rule
  (`['nullable','numeric','min:0','max:10']`) is precisely what let
  `AccountForm.vue:971`'s explicit `null` survive validation and reach the column.

  ### The fix — one rule, at the write boundary

  `InvestmentAccountNormaliser::NOT_NULL_WITH_DEFAULT` + a drop loop that runs
  **before** the float casts (`(float) null` is `0.0`, which would silently write a
  zero where the caller meant "leave it alone"). Dropping the key restores exactly
  the pre-W-0008 behaviour: default applies on create, stored value untouched on
  update. It sits in the normaliser, so the form, Fyn and upload paths are all
  covered at once.

  ### Both of your points, checked rather than assumed

  **1. All six fields in `AccountForm.vue:965-972`, against actual nullability:**

  | Field | Nullable | Default | Verdict |
  |---|---|---|---|
  | `country` | **NO** | United Kingdom | Would break — already had its own null-drop in both requests (PR #269) |
  | `advisor_fee_percent` | **NO** | 0.0000 | **The reported break** |
  | `platform` | YES | NULL | fine |
  | `planned_lump_sum_amount` | YES | NULL | fine |
  | `planned_lump_sum_date` | YES | NULL | fine |
  | `platform_fee_amount` | YES | NULL | fine |
  | `platform_fee_percent` | YES | 0.0000 | fine (nullable despite the default) |

  `platform_fee_type` and `platform_fee_frequency` are indeed NOT NULL, but the
  block does **not** null them and the form always sends a value — so they were
  never breaking. They are covered anyway.

  **2. The update path** took the same W-0008 rule and is covered by the same one
  rule plus its own test.

  ### The finding that matters more than the fix

  `country` having its own null-drop, in two requests, for one column, is the whole
  story: **a per-column special case existed, nobody generalised it, and the next
  column to need it took down every create.** So I did not fix the field — I
  measured the class.

  Intersecting `investment_accounts` NOT NULL-with-default columns against the
  Store/Update request rules gives **28 columns a client can send as null**, every
  one of which would have failed identically: `accelerated_vesting_allowed`,
  `clawback_risk`, `company_status`, `contribution_frequency`,
  `csop_disqualifying_event`, `employer_is_listed`, `ers_registered`,
  `grant_currency`, `has_anti_dilution`, `has_performance_conditions`,
  `holding_structure`, `investment_currency`, `loss_relief_eligible`,
  `negligible_value_claim`, `paye_via_payroll`, `scheme_status`, the five
  `units_*` columns, and the rest. Most are on the Employee Share Scheme path,
  which the persona has not exercised yet — so this was 27 more waiting.

  All 28 are now covered.

  ### The guard against a third occurrence

  `it('keeps the NOT NULL list in step with the actual schema')` recomputes the
  intersection from `information_schema` and the live request rules, and fails if
  the constant drifts. **Adding a `nullable` rule for a NOT NULL column — the exact
  W-0008 mistake — is what moves a column into that set**, so the same error now
  fails a test instead of a user's save.

  ### Tests — 42 passing

  - `tests/Feature/Investment/InvestmentAccountNotNullColumnsTest.php` (NEW, 4):
    create with the panel collapsed (the exact 500ing payload), update with the
    panel collapsed, an adviser fee that IS supplied still stores (W-0008 must
    survive its own regression fix), and the schema-drift guard.
  - `InvestmentAccountHttpIntegrationTest` (14) and
    `InvestmentAccountNormaliserTest` + `Unit/Support` (28) all green — the
    ownership, units and adviser-fee work is intact.

  ### Why my own verification missed it

  Recorded in full in `F-0002` §10. Short version: I verified the field I added,
  on the path where it is visible. The failure needs the panel **collapsed**, which
  is the default state and the one where the field is invisible — so the state I
  could see was the only state I thought to check. A collapsed panel looked like
  "the feature is off", not "a different payload".

  **NOT browser-verified, per instruction** — the tester holds the create path and
  will confirm, including `ownership_percentage = 50` on a new joint row, which
  this bug was blocking.
