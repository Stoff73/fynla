---
id: W-0489
title: migrate:savings-to-cash is registered and would double-count every household's cash if anyone ran it
mission: persona-run-peak_earners-2026-08-20
owner: build-lead
branch: fix/w-0489-delete-the-savings-to-cash-migration-command
reviewers: [quality-lead]
status: in_review
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

## Resolution — 2026-08-28

**Acceptance 1 — CSJ decided: `cash_accounts` holds current accounts, distinct from
savings.** Two of the three voices already said so — the `CashAccount` docblock and
`HouseholdPlanningService`, which sums the two tables as separate asset classes and is only
correct if they ARE separate things. The command was the odd voice out.

**Acceptance 2 — the command is deleted**, not guarded.
`app/Console/Commands/MigrateSavingsToCash.php` is gone, so there is nothing left to make
idempotent and no source rows left to mark. Nothing in the application copies a row between
the two tables.

The one live reference to it was the docblock on
`2026_08_25_120000_widen_cash_accounts_interest_rate_to_match_savings`, which quotes the
command as the evidence for what units `cash_accounts.interest_rate` holds. That reasoning
is still sound and is kept, now marked as a citation of something deleted rather than a
pointer to live code. The remaining mentions are in `docs/archive/`, which is archive.

**Acceptance 3 — the stated answer, at both sites.** The decision is recorded on the
`CashAccount` docblock (what the table is for, and that the command was the disagreement)
and at the summing site in `HouseholdPlanningService`, which now says why adding both
totals is correct rather than a double count — and that it was one
`php artisan migrate:savings-to-cash` away from being wrong for every household.

**Acceptance 4 — the guard, and an honest note about what it can be.** Once the two tables
are distinct by design, *"the same money in both tables"* is not a state the application can
reach: there is no marker a test could use to recognise a duplicate, because duplicates are
not supposed to be producible. A test asserting "these two rows are the same money" would
have to invent the very concept the decision removes.

So `tests/Unit/Console/Commands/NoCommandCopiesSavingsIntoCashTest` guards the thing that
would actually have caught this:

1. **No registered command copies between them.** `migrate:savings-to-cash` is absent from
   `Artisan::all()`, and so is any command whose name mentions both `savings` and `cash`.
   The gun stays out of `artisan list`.
2. **The totals stay additive.** A household with £40,000 of savings and £6,000 of cash
   totals **£46,000** — not the £86,000 one run of the deleted command produced.

## Not fixed here

The `docs/archive/appMapping/` files still document the command. They are archive, and
rewriting history there would make the archive less useful rather than more.
