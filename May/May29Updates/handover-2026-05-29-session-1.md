---
type: handover
mode: end-of-day
date: 2026-05-29
session: 1
branch: coala
previous_session: 2026-05-28 session 2 (context-clear)
---

# Handover — 2026-05-29, Session 1

## Where we left off

Continuing the **test-stabilisation block** on `coala` (the CoALA pivot prerequisite — get `dev`'s suite green before CoALA code lands). Yesterday's session-2 handover left 2 full-suite failures: `MobileScaffoldTest` (fix ready, not applied) and `InvestmentAccountHttpIntegrationTest` "records FORM audit context" (flaky isolation bug, not root-caused). **MobileScaffold is now fixed and committed.** The audit-context flake is **partially diagnosed but NOT fixed** — that's the open thread for next session.

## What shipped today

- `088c86c` — `test(mobile): align MobileScaffoldTest iframe assertion with /m/landing` (the only commit this session)

## What's in flight (NOT done)

**The test-stabilisation block is NOT complete.** One flaky full-suite failure remains:

`tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` → "creates an investment account via POST and records FORM audit context" (lines 20–47). Fails at **line 45** (`expect($auditRow)->not->toBeNull()`) — the CREATED audit row for the account is absent.

### Diagnostic state (what's been ruled in/out — do NOT re-derive)

- **It is genuinely FLAKY, ~30–50% per full run.** Confirmed: full-suite run #3 PASSED, run #4 FAILED; Unit+Feature loop run 1 passed, run 2 FAILED. Test order is deterministic (phpunit.xml: Unit→Feature→Arch→Browser→Eval; no random ordering), so the flake is **true non-determinism**, not order.
- **Subsets ALWAYS pass** — do not waste time re-running these:
  - `./vendor/bin/pest tests/Feature/` → 1668 passed (target green).
  - `./vendor/bin/pest tests/Unit/ tests/Feature/Stores/InvestmentAccountHttpIntegrationTest.php` → 2472 passed (target green, even though `InvestmentAccountStoreEventsTest`'s `Event::fake` ran in Unit).
  - Only the **full Unit+Feature combination** flakes.
- **RULED OUT via instrumentation** (temporary probe added to `app/Traits/Auditable.php` `bootAuditable()` `created` closure, then reverted — code below to re-add): in a captured FAILING run, the failing test's account (provider `Hargreaves Lansdown`, name `General Investment Account`, id=507):
  - The `created` event **DID fire** → listener is NOT detached. (Event::fake / boot-stranding theory is dead.)
  - `config('audit.in_tests')` = **true** at fire time → config-leak theory is dead.
  - `auth()->user()` was **null** (not a preview user) → preview-leak theory is dead.
  - Dispatcher = real `Illuminate\Events\Dispatcher` (not `EventFake`).
  - ⇒ `shouldAudit()` SHOULD have returned true and the row SHOULD have been written. Yet the test found null.

### Leading hypothesis for next session (test this FIRST)

**Wrong-account / model_id mismatch.** The test does `InvestmentAccount::where('provider', 'Hargreaves Lansdown')->first()` (no `orderBy`) then queries the audit by `$account->id`. The **InvestmentAccount factory's default provider is `Hargreaves Lansdown`** (one suite run produced 531 `Hargreaves Lansdown` CREATED events across many tests). If a prior test **leaks a committed Hargreaves row** (created outside the RefreshDatabase transaction — e.g. a non-RefreshDatabase test, an explicit `DB::commit`, or a second connection), then within the failing test `->first()` can return that **older leaked row** (different id), whose CREATED audit doesn't exist → null. The intermittency would come from *when/whether* that leak happens.

Enhanced probe v2 was built to confirm this (logs `hl_count` + `hl_first_id` at write time) but the suite did not reproduce in the 3 runs before session end. **Re-run the enhanced probe loop until it fails, then read `hl_count`:** if `hl_count > 1` at write time, the leak is confirmed → find the leaking test (grep `tests/` for non-`RefreshDatabase` files, `DB::commit`, or alternate connections that create InvestmentAccounts). Secondary check: `auditCreated()` (Auditable.php:127) early-returns if `filterAuditableChanges($this->getAttributes())` is empty — unlikely but cheap to log.

### Probe code to re-add (temporary — REVERT before any commit)

In `app/Traits/Auditable.php`, replace the `created` closure body:
```php
static::created(function ($model) {
    $probe = app()->runningUnitTests() && $model instanceof \App\Models\Investment\InvestmentAccount && config('audit.in_tests');
    if ($probe) {
        $decision = $model->shouldAudit();
        @file_put_contents('/tmp/audit-probe.txt', sprintf("CREATED id=%s name=%s shouldAudit=%s auth_id=%s preview=%s\n",
            $model->id, $model->account_name, var_export($decision, true),
            var_export(auth()->id(), true), var_export(optional(auth()->user())->is_preview_user, true)), FILE_APPEND);
        if ($decision) {
            $model->auditCreated();
            $cnt = \App\Models\AuditLog::where('model_type', \App\Models\Investment\InvestmentAccount::class)->where('model_id', $model->id)->where('action', \App\Models\AuditLog::ACTION_CREATED)->count();
            $hlCount = \App\Models\Investment\InvestmentAccount::where('provider', 'Hargreaves Lansdown')->count();
            $hlFirstId = optional(\App\Models\Investment\InvestmentAccount::where('provider', 'Hargreaves Lansdown')->first())->id;
            @file_put_contents('/tmp/audit-probe.txt', sprintf("WROTE model_id=%s created_rows_now=%s hl_count=%s hl_first_id=%s\n", $model->id, $cnt, $hlCount, var_export($hlFirstId, true)), FILE_APPEND);
        }
        return;
    }
    if ($model->shouldAudit()) { $model->auditCreated(); }
});
```

Reproduction loop (single process — NEVER run two `pest` concurrently, they collide on `laravel_testing`):
```bash
for i in 1 2 3 4 5 6; do
  : > /tmp/audit-probe.txt
  ./vendor/bin/pest --testsuite=Unit,Feature 2>/dev/null > /tmp/coala-loop-$i.txt
  grep -aq "FAIL  Tests.Feature.Stores.InvestmentAccountHttpIntegration" /tmp/coala-loop-$i.txt && { cp /tmp/audit-probe.txt /tmp/probe-FAILED.txt; echo "REPRODUCED run $i"; break; }
  echo "run $i passed"
done
cat /tmp/probe-FAILED.txt
```
(`--testsuite=Unit,Feature` matches full-suite ordering for the relevant suites and skips Eval/Browser, which run after the target and can't affect it.)

## Deploy status

Nothing to deploy — the only change is a test-file assertion. `coala` is not deployed (csjones still pre-Pass-4-PR6 per the standing deploy gate). `coala` is NOT yet merged to `dev`.

## Tech debt found this session

None new (only a 4-line test-assertion change). Pre-existing CSJTODO follow-ups still open (MortgageControllerTest seeder gap was fixed in session 2's WIP; isa_type drift fixed in session 2; LISA bucketing + preview-spouse tier-cap still deferred — see CSJTODO).

Note: vault-sync flagged PHP Services count drift (CLAUDE.md says 340, actual 342) — pre-existing from earlier SP1 store work, not this session. Left as-is; fold into the next real metrics refresh.

## Known issues / blockers

- **Flaky audit test above** — blocks declaring the test-stabilisation block "green". Suite is currently `1 failed (flaky), 26 skipped, ~4272 passed` on a bad run; `0 failed, 4273 passed` on a good run.
- No other red. 26 skipped is normal (Browser BS-NN scenarios + the live EvalHttpDriverTest).

## Rules reinforced this session

None new to memory. (Re-applied existing: single-process pest only; deterministic-order ≠ deterministic-result when static/leaked state is involved; systematic-debugging — instrument, don't guess.)

## Next session should

1. **Re-add the probe** (code above) to `app/Traits/Auditable.php` and run the reproduction loop until it fails. Read `/tmp/probe-FAILED.txt`.
2. **Check `hl_count` at write time.** If `>1` → confirmed leaked duplicate Hargreaves account → grep `tests/` for the leaking test (non-`RefreshDatabase`, `DB::commit`, alternate DB connection creating InvestmentAccounts). Fix the leak (or make the test query unambiguous — e.g. scope by the acting user's id, which the test already has via `$this->user`).
3. **REVERT the probe** before committing anything. Then run the full suite 2–3× to confirm `0 failed` deterministically.
4. Once green: test-stabilisation block is done → start CoALA (Phase 5 cost-telemetry PR first, then Phase 1) per `May/May28Updates/SP1-vs-CoALA-prioritisation-review-2026-05-28.md` + `fynla-coala-implementation-plan.md` v0.4 + `May/May27Updates/PRD-coala-phase-{1..6}-*.md`.

## Context hints

- Active branch type: mixed (test-stabilisation on `coala`, off `dev`)
- `coala` = `dev` + defer-note (`d142ff0`) + WIP test-stab (`b774538`) + session-2 handover (`a1e2d51`) + MobileScaffold fix (`088c86c`). NOT merged to `dev`.
- Behind origin/coala by: 0 (pushed `088c86c`)
- Uncommitted: none, working tree clean (untracked `docs/mobile/designer-brief.pdf` is CSJ's file — leave it)
- Last commit: `088c86c test(mobile): align MobileScaffoldTest iframe assertion with /m/landing`
- Ephemeral: `/tmp/coala-fullsuite-*.txt`, `/tmp/coala-loop*.txt`, `/tmp/audit-probe*.txt` — diagnostic captures from this session; gone on reboot, findings preserved above.
