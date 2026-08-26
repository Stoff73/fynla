---
id: W-0498
title: migrate:savings-to-cash is registered and would double-count every household's cash if anyone ran it
mission: persona-run-peak_earners-2026-08-20
owner: build-lead
reviewers: [quality-lead]
status: queued
severity: high
surfaces: [web, m, ios]
source: found while establishing what cash_accounts is for, W-0323, 2026-08-25
prior_art_checked: 2026-08-25
prior_art_found: [W-0323, W-0263]
prior_art_outcome: none
constitution_refs: [07-quality-bar]
---

## Intent

`php artisan migrate:savings-to-cash` is **live and registered** — it appears in
`php artisan list`. It reads every row of `savings_accounts` and creates a
`CashAccount` for each:

    // MigrateSavingsToCash.php:113
    $accounts = DB::table('savings_accounts')->get();
    foreach ($accounts as $account) { $this->migrateSavingsAccount($account); }

It **never deletes, flags or otherwise marks the source rows**, and there is no
idempotency guard — no `updateOrCreate`, no "already migrated" check. Running it
twice creates two copies.

`HouseholdPlanningService` sums both tables into one net-worth total:

    $savings   = app(SavingsStore::class)->forUser($user);        // savings_accounts
    $savingsValue = $savings->sum(...);
    $cashAccounts = CashAccount::forUserOrJoint($userId)->get();  // cash_accounts
    $cashValue    = $cashAccounts->sum(...);

    $total = $propertyValue + $savingsValue + $investmentValue + $pensionValue
        + $businessValue + $cashValue + $chattelValue;

**So running that command doubles every household's cash in household planning.
Running it twice triples it.** Today it is harmless only because nobody has run
it: `cash_accounts` holds zero rows.

This is a loaded gun in `artisan list` with a name that reads like routine
maintenance.

## Why it is still there — three answers to one question

The command believes `cash_accounts` replaces `savings_accounts`:

> Migrate legacy savings_accounts to new cash_accounts table

The model believes the opposite:

> `CashAccount` tracks current/transactional accounts for cash flow analysis.
> It is **NOT** part of the savings recommendation engine. Savings accounts are
> managed via the `SavingsAccount` model.

`HouseholdPlanningService` agrees with the model — it adds them as separate asset
classes, which is only correct if they are separate things.

The table is also demonstrably alive: it is read by that service and by
`UserModuleTrackingService`, has an observer in `EventServiceProvider`, a
`RetentionPurgeService` entry, relations on `User` and `Household`, and gained a
`joint_owner_id` column as recently as 2026-02-19.

**So `cash_accounts` is simultaneously live, planned-as-a-replacement, and
distinct-by-design, depending on which file you read.** That contradiction is the
root; the double-count is what it costs.

## Acceptance

1. Decide what `cash_accounts` is for. The model docblock and
   `HouseholdPlanningService` already agree on one answer — current accounts,
   distinct from savings — so the cheapest resolution is to make the command agree
   with them rather than the reverse. **That is a call for CSJ, not an agent.**
2. Given that decision, either delete `migrate:savings-to-cash` or make it
   impossible to double-count: idempotency guard, and either remove or mark the
   source rows.
3. If the command is kept in any form, `HouseholdPlanningService` needs a stated
   answer to what happens when the same money exists in both tables.
4. A test that fails if a savings account and a cash account describing the same
   money are both counted.

## Not in scope here

The column-width half of this discovery is W-0323 and is fixed —
`cash_accounts.interest_rate` is `decimal(8,4)` with a corrected comment. The
units were determined from this very command, which is how the contradiction came
to light.
