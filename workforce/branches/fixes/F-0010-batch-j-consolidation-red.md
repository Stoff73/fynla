# F-0010 — Batch J: the consolidation suite is red

**Owner:** build-lead (agent `fix-batch-J`) · **Branch:** `dev` (no feature branch) ·
**Board items:** W-0121 (fixed), W-0122 (raised, then fixed), W-0125 (fixed), W-0126
(raised, then fixed), W-0127 (raised, queued — a decision for CSJ), W-0040 (extended
with new evidence, then resolved by team-lead)

**ID block allocated to this agent: W-0121 – W-0130.** **W-0121**, **W-0122**,
**W-0125**, **W-0126** and **W-0127** were used. W-0123 and W-0124 were drafted and then **withdrawn** — see §4,
dead ends.

**Status: CLOSED 2026-08-21.** All four consolidation failures GREEN; W-0122 and
W-0126 taken and closed afterwards; W-0127 raised and left queued by instruction.
`fix-batch-J` stood down on the team-lead's order when CSJ changed the working model —
nothing was mid-edit, nothing was abandoned part-done. This document is the seed a
replacement agent would be started from (CLAUDE.md Rule 22).

---

## 1. The dispatch, verbatim

> You are `fix-batch-J`. The full consolidation suite has just run over the whole
> uncommitted tree — six agents' work from today, nothing committed yet. Result:
> **7,156 passed, 4 failed, 30 skipped, 125,507 assertions, 1,887s**. Those four
> failures are the gate between today's work and a commit. Your job is to make them
> green **without weakening a single assertion**.
>
> **Read this first:** `feedback_evals_surface_engineering_issues` is a standing rule
> in this project — a failing test is a real bug, you fix the code, never the
> assertion. The one exception is a test that pins behaviour a *deliberate, agreed*
> change has superseded; in that case you rewrite the test to pin the **new** contract
> explicitly and say so in your notes. Never delete a case, never loosen a matcher to
> get green.
>
> Full log: `/private/tmp/claude-501/-Users-CSJ-Desktop-fynla/9e595a25-6362-48cc-b29a-bf60116e441d/scratchpad/consolidation-suite.log`
> (failure blocks around lines 4110–4250).
>
> ## Failures 1 and 3 — the same defect, seen twice: four users leak out of a committed transaction
>
> - `tests/Feature/Database/TrialSchemaRemovalTest.php:91`
> - `tests/Feature/Native/Billing/AppleTransactionSubmissionApiTest.php:546`
>
> Both are **end-of-file guard tests**, and both fail with the **identical four
> users**: `casimir62@example.org`, `jerad54@example.org`,
> `rodolfo.gottlieb@example.com`, `treutel.lavon@example.com`.
>
> I have already confirmed these are a genuine intra-run leak, not stale residue: they
> are `users.id` **3501–3504** in `laravel_testing_a`, all with
> `created_at = 2026-08-21 17:24:28`, i.e. written during the run and still present
> after it. **The guards are working as designed — they are pointing at a real leak,
> so do not touch the guards.**
>
> **The mechanism, already diagnosed and merged once as PR #710 (`f54cd7903`) — read
> it before anything else, and read the end-of-file guard pattern in
> `tests/Unit/Services/Auth/NativeSessionServiceTest.php`:** a test whose transaction
> ends stops being covered by `RefreshDatabase`. Its rollback becomes a silent no-op
> and every row it wrote persists for the rest of the run. Two ways that happens — a
> test calls `DB::commit()` so forked workers on another connection can see the
> fixture, or a test runs a real migration and **MySQL implicitly commits on DDL**.
>
> **Only four files in the suite do either.** Your suspect list is exactly:
> - `tests/Unit/Services/Auth/NativeSessionServiceTest.php`
> - `tests/Feature/Native/StoreKit/AppAccountTokenApiTest.php`
> - `tests/Feature/Native/Billing/AppleTransactionSubmissionApiTest.php`
> - `tests/Feature/Webhooks/Apple/AppleNotificationWebhookTest.php`
>
> Method: run each in isolation and inspect `users` afterwards. That identifies the
> offender in four runs rather than by reading.
>
> **What PR #710 learned, so you do not re-learn it:** every one of those files
> already had a cleanup routine and every one was incomplete, each differently —
> `User` soft-deletes, so `->delete()` leaves the row; a cleanup helper never touched
> two of the tables it exists to clean; Sanctum tokens hang off a polymorphic column
> with **no foreign key**, so they do not follow the user; deleting invoices does not
> rewind `invoice_sequences`. **Assert on identifiers, not counts** — that change is
> what turned a red job into a nameable email address. Whatever you fix, extend the
> same guard style so the next leak names itself.
>
> ## Failure 2 — `InvestmentModuleTest > Holdings Management → it can update a holding` (`tests/Feature/InvestmentModuleTest.php:188`)
>
> The PUT sends `current_price: 450` and `current_value: 45000`. The response comes
> back with `current_value: "8980.07"`.
>
> **I have already root-caused this and it is a real defect, not a stale test.**
> `app/Support/HoldingValuation.php` is new, written today by `fix-batch-A` for
> W-0039. Its rule — *units are the fact, value is derived* — is correct and agreed,
> and the persona work depends on it. But `reconcile()` resolves `quantity` by falling
> back to the **existing stored holding** before deciding which branch to take. So on
> an update where the caller supplies an explicit `current_value` and no quantity, the
> *inherited* quantity (here the factory's `19.955704`) wins and silently overwrites
> the figure the user actually typed: 19.955704 × 450 = 8980.07.
>
> **That is a user-typed figure being validated, accepted, 200'd and silently
> discarded — the same class of defect as W-0026 (`policy_end_date`) which another
> batch fixed today.** In a financial application it is worse than the bug it
> replaced.
>
> The distinction the code is missing is **supplied-in-this-payload** versus
> **inherited-from-the-existing-record**. My reading of the correct rule, which you
> should verify rather than assume:
> - payload has quantity (with a price) → value is derived, units win. This is
>   W-0039's intent and must keep working.
> - payload has an explicit `current_value` and **no** quantity → the typed value
>   stands and the quantity is back-calculated. That is what the class already calls
>   the legacy fallback; it just never fires because of the inheritance.
> - payload has both an explicit quantity and an explicit `current_value` that
>   disagree → decide deliberately and document it. Silently picking one is what got
>   us here.
>
> **Read `workforce/branches/fixes/F-0002-batch-a-ownership-net-worth.md` before
> changing `HoldingValuation`** — it is `fix-batch-A`'s branch document, batch A is
> retired, and it carries the reasoning for W-0039 and its four consolidation claims.
> **Do not undo the units-are-authoritative direction.** Whatever you do lands in that
> one class — Rule 20 — never as a special case in a controller.
>
> ## Failure 4 — `CaptureAccuracyGateTest` (`tests/Feature/Onboarding/CaptureAccuracyGateTest.php:1017`)
>
> Posting a savings account with `ownership_type` of `joint` or `tenants_in_common`
> and a `joint_owner_id`, but **no `ownership_percentage`**, expects **422** with a
> validation error on `ownership_percentage` and now returns **201**.
>
> The surrounding test is a deliberate accuracy gate: it also proves a foreign trust
> is rejected, a non-spouse joint owner is rejected, and shares of 0 and 100 are
> rejected. Its intent is that **a joint share must be stated explicitly and never
> silently defaulted.**
>
> Today `fix-batch-A` consolidated joint ownership onto `SharedOwnership` (the one
> write rule) and `ownership.js` (the one display rule), fixing W-0014 and W-0015, and
> another batch fixed W-0013 (a joint savings account could not be created at all).
> One of those relaxed this validation.
>
> **You must determine which of two things is true and say which, with evidence:**
> - **(i)** the relaxation is a regression — an explicit share is still required, and
>   a silent default reintroduces exactly the ambiguity W-0015 was raised to kill. Fix
>   the validation.
> - **(ii)** the new behaviour is deliberate and correct — a missing share now
>   defaults through `SharedOwnership` in a single documented place. Then the test must
>   be rewritten to pin **that** contract, explicitly and visibly, and your notes must
>   record that the accuracy gate was deliberately narrowed and why.
>
> **Do not guess, and do not simply delete the failing loop.** Rule 6 governs: a joint
> asset is a SINGLE record with `joint_owner_id` and `ownership_percentage` (the
> primary owner's share), the spouse's share being `100 − ownership_percentage`. Note
> also the CSJ ruling already recorded on W-0040: a deliberate 100/0 split is
> nonsensical — that is individual ownership — which is why 0 and 100 are rejected.
>
> ---
>
> ## Rules that bind you
>
> - **Rule 2** no hardcoded tax values; **Rule 6** joint ownership as above; **Rule 9**
>   no acronyms in user-facing text; **Rule 12** no scores; **Rule 15** no decorative
>   icons; **Rule 19** web AND `/m`; **Rule 20** one behaviour, one home, all surfaces
>   and all paths.
> - British spelling in user-facing text, American in code identifiers.
>   `declare(strict_types=1);` everywhere.
> - **Never edit `.env` or DB rows to work around a bug.** Reading `.env` for a
>   credential is fine.
> - The formatter deletes an unreferenced `use` at the moment it runs — **add an
>   import and its first usage in the SAME edit**, or a dozen unrelated tests 500.
>
> ## Environment
>
> - Use **`laravel_testing_a`**: `DB_DATABASE=laravel_testing_a ./vendor/bin/pest <paths>`.
>   It is free — I have finished with it. `_b`, `_c`, `_d`, `_e` are taken by four
>   other agents right now, so do not use them.
> - **`pgrep -f "vendor/bin/pest"` before every run.** Four other agents are running
>   tests. Contention between agents produces failures with **0 assertions** and
>   `SQLSTATE[40001] 1213 Deadlock` — that signature is contention, not a code
>   failure. Vitest timeouts at 5000ms under parallel load are the same thing:
>   **re-run the file in isolation before believing it, and never raise the timeout.**
> - Migrations: `php artisan migrate --path=database/migrations/<file>.php` only.
>   **Never bare `migrate`, never `migrate:fresh`/`migrate:refresh`** — they drop every
>   table.
> - Laravel `:8000`, Vite `:5173` are up. **Do not rebuild the `/m` bundle** — that is
>   mine, ask me.
> - **Do not create, delete or provision users** in the development database. Users 16,
>   17, 20, 30 and 31 are live reproduction data other agents depend on.
>
> ## What you do NOT do
>
> - **Do not commit, do not open a PR, do not deploy.** You are the last gate before I
>   do that.
> - **Do not browser-verify your own work** — a persona-tester closes Rule 14's loop
>   independently.
> - **No colour or palette changes** — CSJ parked that workstream today.
> - **Do not weaken, skip, or delete an assertion to reach green.**
>
> ## Deliverables
>
> 1. All four green, re-verified by a **targeted** re-run of the affected families
>    (not the whole suite — I will run that at the consolidation point).
> 2. `./vendor/bin/pint` clean on everything you touched.
> 3. **New board items for anything you find that is a defect in its own right** —
>    particularly the `HoldingValuation` silent discard and, if it turns out to be (i),
>    the ownership validation regression. Use `workforce/ops/FORMATS.md` shape and take
>    IDs from the block **W-0121–W-0130** so you cannot collide with other agents. Set
>    them straight to `status: handoff`, `handoff_to: quality-lead` if you fix them in
>    the same pass.
> 4. Branch document **`workforce/branches/fixes/F-0010-batch-j-consolidation-red.md`**
>    per FORMATS.md — a complete seed for a replacement: the dispatch verbatim, DONE
>    with evidence, IN FLIGHT and its exact state, NOT STARTED, decisions taken with
>    reasoning, dead ends ruled out, environment state.
> 5. A concise report to me: which test leaked the four users and why its cleanup was
>    incomplete, what you decided on failure 2 and failure 4 and on what evidence, and
>    anything needing a CSJ decision stated with a recommendation.
>
> **Report the moment you are blocked — do not sit idle.** I unblock tooling,
> environments, databases and test data. Rule 22: at ~900k context, stop taking new
> work, write the handover into your branch document, and return it to me.

### Amendments received since

None.

---

## 2. Decisions taken, with the reasoning

| Decision | Why |
|---|---|
| **Failure 2: supplied beats inherited, inside `HoldingValuation` only.** A payload that states a value and no units keeps that value; a payload that states units derives the value. | The lead's reading was verified against the class, both controller call sites and the Vue form, and is correct. `resolve()` conflates "the caller said" with "the row remembered"; a `supplied()` companion is the whole fix. Nothing moves into a controller (Rule 20). |
| **When one payload supplies BOTH units and a disagreeing value, units still win.** | This was already the class's behaviour and is already pinned (`it lets units override a value the caller also sent`). It is also what makes the UI safe: `HoldingForm.calculatedHoldingValue` computes the value from units, so the form can never send a disagreeing pair — only an API caller can, and such a caller is choosing. Recorded explicitly in the docblock rather than left to fall out of branch order, and now pinned on the update path too. |
| **Failure 4 is (ii): deliberate, and the gate is narrowed on purpose.** | See §3 for the evidence. No production code changed for this failure. |
| **The 100 → 50 coercion was NOT changed by this batch.** | Rejecting a stated 100 for savings alone would have made savings the one asset type diverging from property, investments and chattels — a ninth copy of the rule, the disease W-0015 cured. The asymmetry (0 refused, 100 rewritten) was instead recorded and pinned under **W-0040**. The team-lead then resolved W-0040 against CSJ's existing ruling and **another agent has since fixed it at the shared boundary, for every asset type**, which is where it belonged. A stated 100 is now refused. See §9. |
| **W-0122 (Fyn's second valuation mechanism) is raised, not fixed.** | It changes what `create_holding` writes, at the final gate, on a large uncommitted tree, with no tester pass available to me. One line plus a test, deliberately deferred rather than slipped in. |
| **The `laravel_testing_j` database was created for this agent's own verification.** | `_a` was needed for a long bisect; `_b`–`_e` belong to other agents. Verification runs and the bisect would otherwise have contended. It is an empty scratch database that `RefreshDatabase` migrates itself. |

---

## 3. Failure 4 — the evidence for (ii)

**The relaxation is deliberate, is the fix for a live persona defect, and lives in one
place.**

1. `git diff app/Http/Requests/Savings/StoreSavingsAccountRequest.php` shows the
   change is uncommitted and today's. The removed line was
   `'An explicit ownership share is required for a shared account.'`; the replacement
   resolves the share in `prepareForValidation()` via
   `SharedOwnership::primaryOwnerPercentage()`, with the comment: *"This used to
   reject the request outright when the modal — which has no share input — sent
   nothing, making joint savings accounts impossible to create from the UI
   (W-0013)."*
2. W-0013 is a **live persona-run defect**: `POST /api/savings/accounts` returned 422
   for every joint savings account. Requiring the share made the feature unreachable
   on every surface, because no savings form has a share input to state it with.
3. The default is not scattered or silent-per-surface. It is one constant,
   `SharedOwnership::DEFAULT_PERCENTAGE = 50.0`, in the class that replaced **nine**
   divergent implementations (F-0002 §2), and it is displayed back through the one
   display rule (`ownership.js`) as "Joint (50%)".
4. A share the caller **does** state is not touched: `primaryOwnerPercentage('joint', 70)`
   returns 70. So the ambiguity W-0015 was raised to kill — the same share computed
   differently by different mechanisms — is not reintroduced by a default that only
   fires when nothing was stated.
5. `CaptureAccuracyGateTest` is **committed** (`git status` shows it unmodified before
   my change), so a committed assertion was superseded by today's uncommitted fix
   rather than the reverse.

**The accuracy gate was deliberately narrowed, and this is the record of it.** It
previously required every shared savings account to state its share explicitly. It no
longer does: an unstated share is resolved rather than refused. The narrowing is
justified by the point above — **a committed assertion was superseded by an
uncommitted fix**, and the fix answers a live persona defect the assertion caused. The
gate lost that one requirement and gained a stricter one in its place.

**What the gate now pins instead** — deliberately stronger than the bare 422 it
replaced, because it asserts the stored row rather than a status code:

- `it resolves the share of a shared savings account through the one shared rule` — a
  dataset asserting the resolved `ownership_percentage`, the
  `joint_owner_id` and the `ownership_type` on the stored record for: joint with no
  share stated (50), tenants in common with no share stated (50), a stated 70 (70),
  and a stated 100 (50).
- The 0-share rejection stays exactly where it was, in
  `it rejects foreign ownership links through the direct savings API`, alongside the
  foreign-trust and non-spouse rejections, with `sole()` still guarding that every
  rejected request wrote nothing.

**A second, latent failure was found and fixed while doing this.** The original
`foreach ([0, 100])` loop never ran — the test aborted at the missing-share loop
above it. An explicit **100** resolves to 50 and returns **201**, so that loop would
have gone red the moment the first one was fixed. Measured directly:

| `ownership_type` | submitted | resolved | outcome |
|---|---|---|---|
| joint | *absent* | 50 | 201 |
| tenants_in_common | *absent* | 50 | 201 |
| joint | 70 | 70 | 201 |
| tenants_in_common | 0 | 0 | **422** |
| tenants_in_common | 100 | **50** | 201 |

**That last row no longer holds** — a stated 100 is now refused (422) after another
agent implemented the W-0040 resolution mid-batch. The measurement above is what
justified raising it; §9 records what changed and how the test followed.

**Why the dataset test needed its own user:** the free tier caps a household at **two**
savings accounts (`TierConfigurationSeeder.php:52`). The original test only ever
created one account, so the cap never bit; four creations in one test returns **403**
on the third. A dataset gives each case a fresh user, which is also why each case can
use `sole()`.

---

## 4. Dead ends and withdrawn work — do not re-walk

- **The lead's four-file suspect list for the leak is exhausted and clean.** Each was
  run in isolation against a `users`-empty `laravel_testing_a` and each left the table
  empty:
  `tests/Unit/Services/Auth/NativeSessionServiceTest.php` (16 passed),
  `tests/Feature/Native/StoreKit/AppAccountTokenApiTest.php` (13 passed),
  `tests/Feature/Native/Billing/AppleTransactionSubmissionApiTest.php` (16 passed),
  `tests/Feature/Webhooks/Apple/AppleNotificationWebhookTest.php` (18 passed).
  Their PR #710 cleanups work. Log:
  `scratchpad/leakhunt.log`.
- **The dispatch's "only four files do either" list is STALE — do not inherit it.**
  The corrected list of every test file that ends or manipulates its own transaction,
  as of 2026-08-21:

  | File | Mechanism | Leaks? |
  |---|---|---|
  | `tests/Unit/Services/Auth/NativeSessionServiceTest.php` | `DB::commit()` + `DB::purge()` | No — cleans up by id |
  | `tests/Feature/Native/StoreKit/AppAccountTokenApiTest.php` | `DB::commit()` + `DB::purge()` | No — cleans up by id |
  | `tests/Feature/Native/Billing/AppleTransactionSubmissionApiTest.php` | `DB::commit()` + `DB::purge()` | No — cleans up by id |
  | `tests/Feature/Webhooks/Apple/AppleNotificationWebhookTest.php` | `DB::commit()` | No — cleans up by id |
  | **`tests/Feature/AI/DirectWriteTransactionRollbackTest.php`** | `beginTransaction()` + `rollBack()` | No — nested savepoint only |
  | **`tests/Unit/Services/Tiers/TierCollapseLockTest.php`** | second named connection + `DB::purge($name)` | No — writes no rows |
  | **`tests/Feature/Database/SpousePensionPercentBackfillTest.php`** | **implicit commit on the migration's DDL** | **YES — this was the leaker** |

  The last three were all absent from the dispatch's list, and the offender is one of
  them. **The mechanism that bites is not the one that looks dangerous.** Every file
  that visibly calls `DB::commit()` was clean; the one that leaked never mentions a
  transaction at all — it just runs a migration.

- `tests/Feature/AI/DirectWriteTransactionRollbackTest.php` is **not** the
  leaker either — it only ever `beginTransaction()` + `rollBack()`, which under
  `RefreshDatabase` is a nested savepoint, and it creates one user per test, not four.
- **`tests/Unit/Services/Tiers/TierCollapseLockTest.php`** opens a second named
  connection and `DB::purge`s it, but writes no users. Not the leaker.
- **The whole `Unit` suite is clean.** 3,763 passed, 105,407 assertions, 983s, `users`
  empty afterwards. The leaker is in `Feature`.
- **W-0123 and W-0124 were drafted and withdrawn.** They would have raised (a) that no
  surface lets a household state a joint share and (b) that a stated 100 is coerced to
  50. **`W-0040` already owns both**, is assigned to product-lead, and already asks CSJ
  the governing question. The prior-art outcome is *route*, so the new evidence was
  appended to W-0040's working notes instead of opening a duplicate. Two references to
  a "W-0124" in the test file were corrected to W-0040 before it could stick.
- **`git log -S "Missing explicit share"`** dates the superseded assertion to
  `98025c0de` *fix: close SaveTax campaign readiness gaps*. It was added deliberately;
  it was superseded deliberately. Neither was an accident.

---

## 5. What is DONE, with evidence

| Failure | Outcome | Evidence |
|---|---|---|
| **2 — holding update discards a typed value** | **GREEN.** `app/Support/HoldingValuation.php` gained `supplied()`; the typed-value branch now precedes the units branch. W-0121 raised and set to `handoff` / `quality-lead`. | `tests/Feature/InvestmentModuleTest.php` 22 passed (86 assertions). `tests/Unit/Support/HoldingValuationTest.php` 12 passed (20 assertions) — nine pre-existing cases unchanged, three added. Investment families: 345 passed (1,068 assertions). |
| **4 — savings ownership share** | **GREEN**, and a second latent failure in the same test fixed. No production code changed. Evidence and reasoning in §3. | `tests/Feature/Onboarding/CaptureAccuracyGateTest.php` 104 passed (240 assertions), including the 4 new dataset cases. |
| **1 and 3 — four leaked users** | **GREEN.** The leaker is `tests/Feature/Database/SpousePensionPercentBackfillTest.php`, added today for W-0030. Its `runSpousePercentMigration()` executes the real migration's `up()`, which alters a column comment — **MySQL commits implicitly on DDL**, so `RefreshDatabase`'s transaction ends mid-test and teardown's rollback becomes a no-op. Four data tests, four users. W-0125 raised and set to `handoff` / `quality-lead`. | `tests/Feature/Database` 11 passed (35 assertions), no residue in any of the five affected tables. Combined re-run of the whole chunk plus **both** guard families — `tests/Feature/Database …/Mobile`, `tests/Feature/Native`, `tests/Feature/Webhooks`, `tests/Unit/Services/Auth/NativeSessionServiceTest.php` — **787 passed (3,132 assertions), 3 skipped**, both end-of-file guards green, and `users`, `db_pensions`, `point_awards`, `user_gamification`, `tax_configurations`, `personal_access_tokens` and `subscriptions` all empty afterwards. |

`./vendor/bin/pint` — passed on `app/Support/HoldingValuation.php`,
`tests/Unit/Support/HoldingValuationTest.php`,
`tests/Feature/Onboarding/CaptureAccuracyGateTest.php`. Imports re-checked after the
formatter ran; none were stripped.

---

## 6. The leak hunt — how it was found, so the next one is cheaper

**Resolved.** The offender is `tests/Feature/Database/SpousePensionPercentBackfillTest.php`
(untracked, added today for W-0030). Full detail in **W-0125**; the method is recorded
here because it generalises.

**The shape of the leak was the first real constraint, and it held all the way
through: exactly four users, all in one second, and only four.** A broken transaction
persisting past the offending test would have leaked every later test's users too, and
the guards would have listed hundreds. Laravel's `rollBack()` is a silent no-op at
transaction level 0 and the next test opens its own fresh transaction, so a test that
commits leaks only its own rows. That reasoning said "look for ONE test that creates
four users", which is exactly what it turned out to be — four data tests in one file,
one user each, and a fifth test creating none, which is why it was four and not five.

Bisecting was the slow path and nearly sent the hunt the wrong way — the lead's
suspect list, the whole `Unit` suite and Feature `AI → Dashboard` all came back clean,
which looked like the leak was unreproducible. Two things cracked it:

1. **The MySQL binary log is a near-perfect detector for this class of bug.** A
   rolled-back transaction never reaches the binlog, so during a `RefreshDatabase` run
   the binlog contains *only* what leaked. `log_bin` is `ON` on this machine.

   ```bash
   mysqlbinlog --base64-output=DECODE-ROWS --verbose \
     --start-datetime="2026-08-21 17:15:00" --stop-datetime="2026-08-21 17:48:00" \
     /usr/local/var/mysql/binlog.000131
   ```

   Tallying INSERTs against DELETEs per table named the leak in minutes without
   re-running anything, and showed it was **never only about users**: `db_pensions`,
   `point_awards` and `user_gamification` leaked alongside them, and **no guard in the
   suite watches those last two**. Every other table balanced exactly — the PR #710
   family committing deliberately and cleaning up correctly.

2. **Auto-increment ids date a leak.** InnoDB does not roll back auto-increment, so an
   id counts how many rows the process has attempted. The four users were `id`
   3501–3504 in the full run but `id` **1–4** when reproduced in a smaller one — which
   placed the offender at the very start of that run's arguments, `tests/Feature/Database`,
   the same directory as the guard that caught it. That single observation replaced a
   further hour of bisecting.

**Dead end, for the record:** the ordering reasoning that said "the leaker must run
before `Feature/Database`" was sound but useless — the leaker was *inside*
`Feature/Database`, two files above the guard that reported it.

**A cheaper standing detector, if this recurs:** a global `afterEach` in
`tests/Pest.php` reporting any test that ends with `DB::transactionLevel() === 0`.
Deliberately not added — `tests/Pest.php` is shared config, and `tests/CLAUDE.md` names
editing it while parallel batches are live as a collision rather than a fix.

**Nothing is in flight.** No file is mid-edit. Every file listed in §5 is complete,
formatted and passing.

## 7. NOT STARTED / left for others, in priority order

1. **W-0122** — route `CoordinatingAgent::handleCreateHolding` through
   `HoldingValuation::reconcile()`. Queued. The team-lead has since raised it to
   `severity: high` and made it a **prerequisite for signing off W-0121**, on the
   grounds that a second copy inside Fyn is a Rule 20 matter, not a quality-bar one.
2. **W-0040** — resolved by the team-lead against CSJ's existing ruling while this
   batch was running, and another agent is implementing it now (see §9). No action
   here.
3. **Rule 14's loop on W-0121 and W-0125** is not closed by this agent by policy — a
   fix agent does not browser-verify its own work. The holding edit path wants a tester
   pass: type a value with no units and confirm the figure that comes back is the
   figure typed.

## 8. Environment state a replacement inherits

- **Branch `dev`, nothing committed by this agent.** No PR, no deploy, no csjones, no
  prod. The tree still holds six agents' uncommitted work plus this batch's three
  files.
- **Databases.** `laravel_testing_a` — free, `users` left empty as a bisect baseline.
  `laravel_testing_j` — **created by this agent** for its own verification runs; it is
  a scratch database, safe to drop when the batch closes. `_b` to `_e` belong to other
  agents; do not touch them.
- **The development database was not touched at all** — no user created, deleted or
  provisioned, no row patched, no `.env` read or written.
- Laravel `:8000` and Vite `:5173` were left running and untouched. The `/m` bundle was
  not rebuilt.
- `pgrep -f "vendor/bin/pest"` showed 3–5 concurrent pest processes throughout; every
  result quoted in this document came from a run on a database no other agent was
  using.

---

## 9. A live collision the next agent must know about

**While this batch was finishing, another agent began rewriting
`app/Support/SharedOwnership.php` to implement the W-0040 resolution** — the same
supplied-versus-inherited distinction recommended in §3, applied at the ownership
boundary. It is a good change and it is the right one. It also **changed the contract
this batch had just pinned, mid-run**:

- `primaryOwnerPercentage('tenants_in_common', 100)` returned `50.0` when failure 4 was
  diagnosed. It now returns `100.0`, and a stated 100 is refused by the validation
  layer via the new `isValidSharedSplit()`.
- `applyTo()` gained an `$existing` parameter; `StoreSavingsAccountRequest` no longer
  resolves the share in `prepareForValidation()` and now uses a
  `ValidatesSharedOwnership` trait.

**The gate test was updated to follow, and the update made it smaller, not larger.**
Under the new contract a stated 0 *and* a stated 100 are both refused — which is
exactly what the original `foreach ([0, 100])` loop asserted. That loop is therefore
**restored verbatim**, and the dataset test now pins only the three cases that remain
genuinely new: joint with no share stated → 50, tenants in common with no share stated
→ 50, and a stated 70 → 70. Re-verified against the changed code: 4 passed
(26 assertions); whole file 103 passed (239 assertions).

**What a replacement should take from this:** results from this batch were green
against the tree as it stood at roughly 19:00. `SharedOwnership` and the savings
requests were being edited by another agent after that point, so **re-run
`tests/Feature/Onboarding/CaptureAccuracyGateTest.php` and the savings families before
treating failure 4 as settled**. The other three failures touch code nobody else is in.

---

## 10. W-0122, taken after the red was cleared

The team-lead assigned W-0122 to this agent once the leak was closed, on the amended
acceptance from the Archivist's flag: **`create_holding` must READ `HoldingValuation`,
not compute the same answer.** Done — detail and evidence in the board item. Two things
from it are worth carrying here.

**The unconditional zero had to go before the routing could go in.** The handler seeded
its payload with `'current_value' => 0.0` whenever no allocation was given.
`reconcile()` reads a stated `0.0` as a stated valuation, so routing the payload through
it unchanged would have back-calculated a unit count of **zero** — asserting "this
holding has no units", a fabricated fact where the truth is an absent one. The
allocation now sets `current_value` only when an allocation exists, and the NOT NULL
column filler runs *after* reconciliation, where it is a storage constraint rather than
a valuation rule. Anyone routing another site through the shared class (W-0126) hits
the same trap.

**The Fyn handler was not the only second copy.** Enumerating every `Holding::create`
site found **five more** that do not read the shared class — including
`DCPensionHoldingsController.php:96-98`, which writes out `cost_basis = quantity ×
purchase_price` line for line. Raised as **W-0126** rather than absorbed: five write
paths across three modules, one of which (document import, which can receive units and
a value that disagree) needs a product decision rather than a routing change.

## 11. Not mine: the architecture suite is red from in-flight W-0040 work

While verifying W-0122, `tests/Architecture` returned **4 failures that did not exist in
the consolidation run**. None are from this batch:

- `app/Http/Requests/Concerns/ValidatesSharedOwnership.php` (**untracked, new**) fails
  two `ApplicationArchitectureTest` expectations — everything under
  `App\Http\Requests` must extend `FormRequest` and be suffixed `Request`. A trait in a
  `Concerns` sub-namespace is neither.
- `StoreBoundaryTest` fails on `App\Models\SavingsAccount` being used in
  `App\Http\Controllers\Api\SavingsController`. `git diff` shows both the import and
  its use (`$existing = SavingsAccount::where(...)`) were **added** in the working tree,
  presumably to feed the new `applyTo($data, $type, $existing)` inheritance.

Both belong to the agent implementing the W-0040 resolution. Flagged to the team-lead,
deliberately not touched — reaching into another agent's in-flight change to make an
arch test green would collide with them and hide the signal. **The consolidation cannot
be committed until these are resolved by their owner.**

---

## 12. W-0126 — the remaining holding write paths

Assigned after W-0122, with the import site explicitly split out. Detail and evidence in
the board items; three things belong here.

**The count was wrong and grew while fixing it: seven sites, not five.**
`DCPensionHoldingsController` held **three** copies, not the one W-0126 named. The two
extra ones are the interesting ones:

- `update()` wrote out by hand the exact construct W-0121 was raised about —
  `$validated['quantity'] ?? $holding->quantity`, an inherited unit count standing in
  for one the caller never mentioned.
- `bulkUpdate()` reconciled **nothing**. A bulk re-valuation to £60,000 wrote the new
  value and left 100 units and a £500 price beside it, so the row contradicted itself on
  save. That was live, not theoretical.

**Four of the seven sites changed no behaviour at all, and saying so matters.**
`InvestmentController:411`/`:564`, `RetirementController:409` and the cash-remainder
writes accept only `security_name`, `asset_type`, `allocation_percent`, `cost_basis` and
`ocf_percent` — no units, no prices — so the reconciliation is inert today. It is still
worth doing: the day someone adds a price field to one of those forms, the units rule
applies without anyone remembering it should. **A reader that does nothing yet is not
the same as a copy that agrees for now.** Anyone reviewing this should not read those
four as behaviour changes and go looking for what moved.

**These endpoints had no feature cover at all.**
`tests/Feature/Retirement/DCPensionHoldingValuationTest.php` is new — five cases, every
one asserting the stored row, because all three endpoints return a response that looks
identical whether or not the reconciliation happened.

## 13. Two failures in my regression run that are NOT mine

`tests/Feature/Stores` returned two failures, both **422s on ownership validation**,
both from `fix-batch-F`'s in-flight W-0040 change. Nothing in this batch touches
ownership and every holding test passes.

- **`InvestmentAccountHttpIntegrationTest:95`** is the one that needs a decision, not a
  patch. It posts a joint investment account with `ownership_percentage: 100.00` and
  asserts **201** with the 100 → 50 coercion — **that is W-0014's acceptance, and it is
  a committed test.** Under the new rule a stated 100 is refused, so it now 422s. This
  is the W-0014-versus-W-0040 contradiction that W-0040 was raised to settle, finally
  biting: the resolution chose refuse, and the cost is that the investment form must
  stop sending an uncleared 100, or W-0014's test must be rewritten to the new contract.
  **A frontend change or a test rewrite — someone has to choose, and it is not the fix
  agent's call.**
- **`PropertyHttpIntegrationTest:129`** — a joint property create now 422s where it
  previously stored a 50/50.

Flagged to the team-lead, deliberately untouched. Reaching into another agent's
in-flight change to green a test collides with them and hides the signal.

---

## 14. W-0127 — the import path, NOT built, and the reasoning that matters

Raised, `queued`, owner `product-lead`. **Deliberately not built** — the team-lead
split it out because it is a decision rather than a routing change, and then the stand
down came. The board item carries the full three options; **the reasoning is the part
worth keeping and it is repeated here so it survives even if the item is re-triaged.**

`app/Services/Documents/HoldingsImportService.php:96` passes `quantity`,
`current_price`, `current_value` and `cost_basis` straight from a parsed file into
`Holding::create()` with no reconciliation, so an imported row can store units and a
value that contradict each other.

**Recommendation: reconcile silently within a tolerance, store both and ask on a
material disagreement.**

Why not simply route it through `HoldingValuation` like the other seven sites — which
is the obvious answer and the wrong one:

- **A broker's export has a claim to authority a Fynla form does not.** The other seven
  sites reconcile figures a user typed into our own form, where the server owning the
  derivation is plainly right. Here the valuation may be the provider's official
  mid-price at a stated valuation date, while the price column is indicative or stale.
  Units-win would replace an accurate figure with a derived one.
- **A large disagreement almost always means the file was misparsed** — a column
  offset, a pence/pounds mix-up. Silently "fixing" it destroys the one piece of
  evidence that would have shown the import was wrong. The disagreement is
  *information*, not noise.
- **Small disagreements are rounding**, not error: units and price are often correct to
  more decimal places than either column displays. Those should reconcile silently,
  because nobody needs to see them.
- Refusing the row outright is the wrong default: it throws away data the user does
  have, and gives them no way forward when the file is their broker's and not editable.

**And the reason this is not a tidy-up at all:** routing it unchanged would silently
overwrite a figure the user supplied. That is **W-0121 in a new place**, at the one
boundary where the source has independent authority. Whatever is decided, the tolerance
and the resolution rule belong in `App\Support\HoldingValuation` with the rest of it,
never as an import-only branch (Rule 20).

## 15. Open, and not this agent's: the W-0014 / W-0040 test conflict

**`fix-batch-F` is also standing down, so this is recorded here rather than left to be
rediscovered.**

`tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php:95` posts a joint
investment account with `ownership_percentage: 100.00` and asserts **201** with the
100 → 50 coercion. It is a **committed** test and that assertion is **W-0014's
acceptance**. Under `fix-batch-F`'s W-0040 change a stated 100 is refused, so it now
returns **422**. `tests/Feature/Stores/PropertyHttpIntegrationTest.php:129` fails the
same way — a joint property create 422s where it previously stored 50/50.

This is the W-0014-versus-W-0040 contradiction that W-0040 was raised to settle,
finally biting. The resolution chose *refuse*; the cost is that **either the investment
form must stop sending an uncleared 100, or W-0014's test must be rewritten to the new
contract.** A frontend change or a test rewrite — someone has to choose, and it is not
a fix agent's call.

Worth noting the shape, because it recurred twice in one day: **resolving an ambiguity
correctly breaks the tests that pinned the ambiguity.** In this batch's own failure 4
the new contract happened to restore the original assertion, so the fix made the test
*smaller*. Here it does not, because W-0014 explicitly wanted the coercion. The second
case is the one that needs a human.

**Neither failure was touched.** Reaching into another agent's in-flight change to
green a test collides with them and hides the signal.

## 16. Closing state

- **Nothing committed, no PR, no deploy, no branch created.** Everything is in the
  working tree on `dev`.
- **Files this agent changed:** `app/Support/HoldingValuation.php`,
  `app/Agents/CoordinatingAgent.php`,
  `app/Http/Controllers/Api/Retirement/DCPensionHoldingsController.php`,
  `app/Http/Controllers/Api/RetirementController.php`,
  `app/Http/Controllers/Api/InvestmentController.php` (holdings blocks only),
  `tests/Unit/Support/HoldingValuationTest.php`,
  `tests/Feature/Onboarding/CaptureAccuracyGateTest.php`,
  `tests/Feature/Database/SpousePensionPercentBackfillTest.php` (new),
  `tests/Feature/AI/DirectWrite/CreateHoldingTest.php`,
  `tests/Feature/Retirement/DCPensionHoldingValuationTest.php` (new).
- **Pint clean on every one**, imports re-checked after each formatter run.
- **No file left part-edited.** The stand down arrived between items, not inside one.
- **Databases:** `laravel_testing_a` free and clean; `laravel_testing_j` retained per
  instruction, safe to drop. **The development database was never touched** — no user
  created, deleted or provisioned, no row patched, no `.env` read or written.
- **Not browser-verified by this agent, by policy.** Two surfaces want a tester pass:
  the investment holding edit (type a value with no units, confirm the figure returned
  is the figure typed) and the defined contribution pension holdings editor (the same,
  plus a bulk re-valuation).
