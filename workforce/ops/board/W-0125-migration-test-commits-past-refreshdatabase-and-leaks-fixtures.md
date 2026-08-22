---
id: W-0125
title: A migration test commits past RefreshDatabase on DDL and leaks its fixtures into the rest of the run
mission: M-0002-persona-fidelity
owner: build-lead
status: handoff
claimed: 2026-08-21T18:50:00Z
handoff_to: quality-lead
branch: workforce/branches/fixes/F-0010-batch-j-consolidation-red.md
severity: high
surfaces: [web, m, ios]
source: found by fix-batch-J in the consolidation suite run, 2026-08-21 — the cause of both TrialSchemaRemovalTest and AppleTransactionSubmissionApiTest failing
prior_art_checked: 2026-08-21
prior_art_found: [PR #710 (f54cd7903), tests/Unit/Services/Auth/NativeSessionServiceTest.php cleanupCommittedNativeFixture + end-of-file guard, tests/Feature/Native/StoreKit/AppAccountTokenApiTest.php, tests/Feature/Native/Billing/AppleTransactionSubmissionApiTest.php, tests/Feature/Webhooks/Apple/AppleNotificationWebhookTest.php]
prior_art_outcome: extend
constitution_refs: [07-quality-bar]
---

## Intent

Two unrelated end-of-file guards failed in the consolidation run —
`tests/Feature/Database/TrialSchemaRemovalTest.php:91` and
`tests/Feature/Native/Billing/AppleTransactionSubmissionApiTest.php:546` — both
naming the identical four users: `casimir62@example.org`, `jerad54@example.org`,
`rodolfo.gottlieb@example.com`, `treutel.lavon@example.com`.

**The guards were right and the leak was real.** The source is
`tests/Feature/Database/SpousePensionPercentBackfillTest.php`, added today for W-0030
and untracked at the time of the run.

Each of its four data tests creates a user and a defined benefit pension, then calls
`runSpousePercentMigration()`, which executes the real migration's `up()`. That
migration alters the `spouse_pension_percent` column comment, and **MySQL commits
implicitly on DDL** — the second of the two mechanisms PR #710 documented. The moment
`up()` runs, `RefreshDatabase`'s wrapping transaction is gone; teardown's rollback is a
silent no-op (Laravel's `rollBack()` returns without doing anything at transaction
level 0); and everything written before that point is committed for the remainder of
the run.

**Exactly four users leaked, not thousands, and that shape is the proof:** the next
test opens its own fresh transaction, so a test that commits leaks only its own rows.
The fifth test in the file creates no user, which is why the count is four rather than
five.

## What actually leaked — more than the guards could see

Reconstructed from the MySQL binary log for the run window, which contains only
committed transactions and therefore contains only the leak:

| Table | Inserted | Deleted | Leaked |
|---|---|---|---|
| `users` | 13 | 9 | **4** |
| `db_pensions` | 4 | 0 | **4** |
| `point_awards` | 4 | 0 | **4** |
| `user_gamification` | 4 | 0 | **4** |
| `personal_access_tokens` | 9 | 9 | 0 |
| `native_device_sessions` | 4 | 4 | 0 |
| `native_refresh_tokens` | 5 | 5 | 0 |
| `tier_configurations` | 14 | 14 | 0 |
| `tax_configurations` | 9 | 9 | 0 |
| `subscriptions` | 3 | 3 | 0 |
| `user_sessions` | 3 | 3 | 0 |

Every balanced row is the PR #710 family committing deliberately and cleaning up
correctly. **The only unbalanced tables are this test's.** Note the leak was never
only about users: creating a pension fires the gamification observer, so a
`point_awards` row and a `user_gamification` row arrive with it, and **no guard in the
suite watches either table** — they would have survived a users-only fix unnoticed.

## Acceptance

1. `tests/Feature/Database/SpousePensionPercentBackfillTest.php` leaves nothing behind:
   no user, pension, point award, gamification row or tax configuration.
2. Cleanup happens even when an assertion fails, so a red test does not also poison
   the run.
3. What the tests prove is unchanged — the migration is still exercised against real
   rows, which is the entire point of W-0030 criterion 3.
4. The file carries its own end-of-file guard, reporting **identifiers**, so the next
   leak from this file names itself instead of surfacing as somebody else's failure.

## Working notes

**Fixed 2026-08-21 by fix-batch-J.**

Extended the PR #710 pattern rather than inventing a second one:

- `spousePercentFixture()` — creates the user and pension the four data tests share.
- `cleanupCommittedSpousePercentFixture(int $userId)` — removes children first
  (`point_awards`, `user_gamification`), then `DBPension::withTrashed()->forceDelete()`
  because it soft-deletes, then the user's tokens and `forceDelete()` because `User`
  soft-deletes too, then the `Pest.php` 2019/20 safety-net tax configuration that was
  committed inside the same doomed transaction.
- Each data test wraps its assertions in `try`/`finally` so cleanup runs regardless.
- An end-of-file guard, `it leaves nothing behind once the migration has committed past
  the transaction`, asserting on identifiers across `users`, `db_pensions`,
  `point_awards` and `user_gamification`. `tax_configurations` is deliberately excluded
  and the reason is written into the test: the safety-net row is created fresh for the
  guard itself, inside its own live transaction, so it is legitimately present there.
- The file's docblock now states plainly that these tests commit whether they like it
  or not, and why.

**No assertion was weakened, skipped or removed.** All five original tests assert
exactly what they asserted before; the migration is still executed for real.

**Evidence:** `tests/Feature/Database` — 11 passed (35 assertions), and afterwards
`users`, `db_pensions`, `point_awards`, `user_gamification` and `tax_configurations`
are all empty. Before the fix, the same directory left four users and their pensions
behind and failed `TrialSchemaRemovalTest`.

### How it was found, for whoever hunts the next one

Bisecting was slow and nearly sent the hunt the wrong way. Two things cracked it:

1. **The MySQL binary log is a perfect detector for this class of bug.** A rolled-back
   transaction never reaches the binlog, so during a `RefreshDatabase` run the binlog
   contains *only* what leaked. `mysqlbinlog --base64-output=DECODE-ROWS --verbose
   --start-datetime=… --stop-datetime=…` over the run window produced the exact rows,
   the tables, and the insert/delete balance above in a couple of minutes — no
   re-running of anything. `log_bin` is `ON` on this machine.
2. **Auto-increment ids date the leak.** InnoDB does not roll back auto-increment, so
   an id is a monotonic counter of how many rows the process has attempted. The four
   leaked users were `id` 3501–3504 in the full run but `id` **1–4** when the leak was
   reproduced in a smaller run — which placed the offender at the very start of that
   run's argument list, `tests/Feature/Database`, the same directory as the guard that
   caught it.

A cheaper standing detector, if this recurs: a global `afterEach` in `tests/Pest.php`
reporting any test that ends with `DB::transactionLevel() === 0`. Not added here —
`tests/Pest.php` is shared config and editing it mid-run while parallel batches are
live is a collision, not a fix.
