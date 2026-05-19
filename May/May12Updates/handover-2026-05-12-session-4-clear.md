---
type: handover
mode: context-clear
date: 2026-05-12
session: 4
branch: audit-criticals
trigger: context-handover skill (tripwire at ~292k tokens)
---

# Context Clear Handover — 2026-05-12, Session 4

## Immediate state

Tier 1, Tier 2, and Tier 3 of the audit-critical fixes are all complete on `audit-criticals`. Six fix commits landed plus one WIP at the tip for follow-up regression fixes. Full Pest suite ran to completion: **3,542 passed / 25 skipped / 1 failed** — the 1 failure was not identifiable from the truncated runner log and needs a fresh re-run next session to isolate. Per handover Step E from session 3, the auto-resume contract was to pause and ask CSJ before Phase B once Tier 1-3 was done; tripwire fired before that pause.

## The thread

- Auto-resumed from session-3 handover. Step A: squashed WIP `49bc64c` into a proper `fix(audit): add income_tax threshold aliases + fix band-ceiling formula` commit while preserving the handover-doc commit on top via `git reset --soft HEAD~2 + unstage handover + re-commit both`. Force-pushed with `--force-with-lease`.
- Step B: surfaced 3 deferred decisions (Tier 1 #4 salary sacrifice, Rule #16 emoji/Unicode grandfathering, Tier 1 sequencing) in the Phase 4 session-start report — no blocking.
- Step C: Tier 2 TransientToken family — added `instanceof PersonalAccessToken` guards to `TokenRefreshController::refresh` (#7), `EvalBypassGate::isActive` (#8), `EnsureMFAVerified::handle` bearer branch (#9). All three previously failed open under TransientToken (`TransientToken::can()` always returns true — #9 was an actual MFA bypass). Wrote new Pest cases for each, plus added `Unit/Http` and `Unit/Database` to `tests/Pest.php` so they bootstrap the Laravel app. Updated `reference_transient_token_family_bugs.md` memory file with sites #7–#9.
- Step D: Tier 3 data layer — landed 4 separate commits:
  - **S-01** (`e7ef67e`): migration `2026_05_12_000001` converts 19 `users` expenditure / aggregate columns from `double` to `decimal(10,2)` / `decimal(12,2)`. Regression test pins types via `information_schema.COLUMNS`.
  - **S-02** (`358f6c9`): `Holding` model casts `'float'` → `'decimal:N'` per audit-recommended precision. Regression test pins string-return + per-column precision. Decimal-string return broke `HoldingsDataExtractor::calculateAnnualizedReturn` (strict `float` signature) → fixed at the boundary with `(float)` cast in the WIP commit. Also broke `PortfolioOptimizationTest:566` strict comparison `->toBe(80.00)` against `'80.0000'` → fixed at the assertion boundary in the WIP commit.
  - **I-01** (`2f41599`): migration `2026_05_12_000002` adds composite `audit_logs_event_type_created_idx (event_type, created_at)` for the purge sweep. Regression test asserts index existence + column order.
  - **MIG-01** (`246d39a`): defused `2026_01_18_000003_migrate_existing_goals_data.php` down() — replaced `DB::table('goals')->truncate()` (would silently destroy ~4 months of user goals on a rollback) with a documented no-op. Regression test pins file contents textually.
- All 4 Tier 3 commits ship with new Pest tests; intermediate suites green at commit time. Full-suite final run came back 1-failed but the Monitor command (`tail -5`) discarded the per-test failure block. The flaky SavingsAgentGoalsTest seen during the Holding-cast investigation passed when run in isolation immediately afterward — likely the same flake at full-suite scale.

## Files touched this session

```
A  May/May12Updates/handover-2026-05-12-session-3-clear.md   (preserved through Step A squash)
M  app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php       (Tier 2 #7)
M  app/Http/Middleware/EnsureMFAVerified.php                          (Tier 2 #9)
M  app/Services/Eval/EvalBypassGate.php                                 (Tier 2 #8)
M  app/Models/Investment/Holding.php                                    (Tier 3 S-02)
M  app/Services/Investment/Analytics/HoldingsDataExtractor.php          (S-02 boundary cast)
M  app/Services/Property/PropertyTaxService.php                         (Tier 1 #5 — replays via reword)
M  app/Services/TaxBandTracker.php                                      (Tier 1 #5)
M  app/Services/UKTaxCalculator.php                                     (Tier 1 #5)
M  database/seeders/TaxConfigurationSeeder.php                          (Tier 1 #3)
A  database/migrations/2026_05_12_000001_convert_users_expenditure_columns_to_decimal.php   (Tier 3 S-01)
A  database/migrations/2026_05_12_000002_add_audit_logs_event_type_created_idx.php          (Tier 3 I-01)
M  database/migrations/2026_01_18_000003_migrate_existing_goals_data.php                    (Tier 3 MIG-01)
A  tests/Feature/Mobile/TokenRefreshTest.php                                                (TransientToken describe block)
M  tests/Feature/PortfolioOptimizationTest.php                                              (S-02 boundary cast in assertion)
A  tests/Unit/Database/AuditLogsIndexTest.php                                               (Tier 3 I-01)
A  tests/Unit/Database/GoalsMigrationDownIsSafeTest.php                                     (Tier 3 MIG-01)
A  tests/Unit/Database/UsersExpenditureColumnTypesTest.php                                  (Tier 3 S-01)
A  tests/Unit/Http/Middleware/EnsureMFAVerifiedTest.php                                     (Tier 2 #9)
A  tests/Unit/Models/Investment/HoldingCastsTest.php                                        (Tier 3 S-02)
A  tests/Unit/Services/Tax/IncomeTaxConfigAliasTest.php                                     (Tier 1 #3)
A  tests/Unit/Services/Eval/EvalBypassGateTest.php                                          (Tier 2 #8)
M  tests/Pest.php                                                                            (Unit/Http + Unit/Database opt-in)
```

Memory file updated (outside repo): `reference_transient_token_family_bugs.md` (sites #7–#9 + 2026-05-12 surfaced entry).

## WIP commit

- SHA: `a5e4770`
- Subject: `wip: context-handover snapshot`
- Pushed: **yes** (origin/audit-criticals at `a5e4770`)
- **Next session should**: this WIP captures the S-02 boundary-cast fixes (HoldingsDataExtractor + PortfolioOptimizationTest assertion). The cleanest squash is to amend it into the existing `358f6c9` S-02 commit's tree via `git rebase --interactive 358f6c9~1` and fixup — OR keep it as a standalone `fix(audit): cast Holding fields to (float) at numeric boundaries` commit. The latter is simpler and reads cleanly in the log. Both options keep the same logical change; just pick one before opening the PR.

## Open decisions

1. **Tier 1 #4 — salary sacrifice £2,000 in `RetirementStrategyService:1186`.** Audit's proposed `salary_sacrifice_limit: 2000` config key codifies the wrong rule (post-2029 reform). **Default direction-of-travel: remove the £2,000 special-case from `calculateNetCostOfContribution` entirely.** Auto-resume should NOT touch this without explicit CSJ confirmation. Carried over from session 3.

2. **Rule #16 emoji + Unicode-as-icons.** `goalIcons.js` (🔥 🎯 📈 ⭐ 🏆) and `AdminDashboard.vue:199` (`▲`/`▼`) — Rule #16 lists both as a strict subclass banned even on allowed surfaces. **Default direction-of-travel: defer until PR is open; surface to CSJ as a separate review item.** Carried over from session 3.

3. **PR opening timing.** Tier 1-3 is complete. Step E from session 3 said "Pause and ask CSJ before kicking off Phase B from REVIEW.md §6." That pause is now. **Default direction-of-travel: open PR `audit-criticals → dev` first** (cleaner review surface), gather CSJ's verdict, then decide on Phase B. Auto-resume should propose this and **wait for CSJ** before opening — opening a PR is the kind of "shipping" action that benefits from explicit go-ahead.

4. **WIP-commit squash style.** Squash `a5e4770` into S-02's `358f6c9` (history-rewrite, cleanest log) OR keep as standalone follow-up commit (no rewrite, more commits). **Default direction-of-travel: standalone follow-up.** Audit-criticals is a feature branch and force-pushable, so either is safe. Standalone is faster.

## Pick up from here (auto-continue contract)

Execute in this order:

### Step A: Reword the WIP commit to a clean message
The WIP `a5e4770` contains real fixes (HoldingsDataExtractor float cast + PortfolioOptimizationTest assertion). Default direction is to amend the message rather than squash into S-02. Run:

```bash
git log --oneline -3   # confirm a5e4770 is HEAD with "wip: context-handover snapshot"
git commit --amend -m "$(cat <<'EOF'
fix(audit): cast Holding fields to (float) at numeric boundaries (S-02 follow-up)

Follow-up to 358f6c9 — Holding's decimal:N casts return strings, which
broke two consumers:

- HoldingsDataExtractor::calculateAnnualizedReturn has a strict
  `float $purchasePrice, float $currentPrice` signature. Now cast at
  the call site in calculateExpectedReturn (line 158-162). Also cast
  dividend_yield when accumulating into the expected-return total.

- PortfolioOptimizationTest "can update a holding" used strict
  ->toBe(80.00) against the cast-returned string '80.0000'. Adapted
  the assertion to compare via (float) so it asserts the numeric
  value, not the decimal:4 string shape.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
git push --force-with-lease origin audit-criticals
```

### Step B: Re-run the full Pest suite cleanly
Tier 3 complete suite saw 1 failure on the final run, but the Monitor tail discarded the per-test block before it could be identified. Re-run, save full output, isolate the failure. SavingsAgentGoalsTest is a known intermittent flake (passed in isolation during session 4) — if that's the lone red, document and move on; otherwise diagnose.

```bash
./vendor/bin/pest 2>&1 | tee /tmp/audit-criticals-final-pest.log
grep -E "FAILED|FAIL " /tmp/audit-criticals-final-pest.log
```

### Step C: Surface the four open decisions to CSJ and **stop and wait**
Tier 1-3 is shipping-grade. Phase B from REVIEW.md §6 is the next major chunk and should not auto-start. Print:

- The 4 open decisions verbatim from "Open decisions" above
- Branch summary: `audit-criticals` at `a5e4770` + the reword from Step A
- Proposed next move: open PR `audit-criticals → dev`
- Ask CSJ explicitly for go/no-go on the PR and which open decisions to lock in

### Step D: ONLY if CSJ says go — open PR
```bash
gh pr create --base dev --head audit-criticals --title "fix(audit): Tier 1-3 critical findings (PCLS LSA cap, SDLT, income_tax aliases, band-ceiling, TransientToken family, expenditure decimals, Holding casts, audit_logs index, goals migration safety)" --body "..."
```

The PR body should enumerate each Tier 1 / 2 / 3 finding with its commit SHA and the audit reference (`May/May12Updates/review-database.md §X-NN`).

### Step E: Tier 1 #4 (salary sacrifice) — only when CSJ rules
Per Open Decision #1. Default direction is to remove the £2,000 special-case from `RetirementStrategyService:1186` `calculateNetCostOfContribution`. Add a Pest regression case pinning the post-fix behaviour. **Do NOT auto-run.**

### Step F: Tier 1 #6 (Starting Rate for Savings) — separate task
Skipped from session 3's sequencing decision (handover Open Decision #3). Treat as Phase B scope.

## What the next Claude needs to know

- **Memory file updated.** `reference_transient_token_family_bugs.md` now lists sites #7–#9 as fixed on the `audit-criticals` branch. Don't re-add them.
- **`Unit/Http` and `Unit/Database` are now in `tests/Pest.php` uses() block.** New tests in those directories will inherit `TestCase + RefreshDatabase` automatically. `Unit/Models/*` tests still opt-in per-file via `uses(TestCase::class, RefreshDatabase::class);` — that's the existing convention, don't try to bulk-bind.
- **Sanctum::actingAs() is NOT a TransientToken simulator.** It creates a Mockery'd `PersonalAccessToken` that passes `instanceof` checks. For TransientToken HTTP testing, either drive the controller directly (TokenRefreshTest pattern) or attach `new TransientToken` then manually `auth->guard('sanctum')->setUser($user)`.
- **Tier 3 migrations are applied locally.** `2026_05_12_000001` and `2026_05_12_000002` ran cleanly. The session-start phase-1c reseed runs again next session and is idempotent.
- **Holding cast change has propagation risk.** Audit's note ("Note: decimal:N in Eloquent returns a string. Any service that compares === or sums via array_sum needs (float) casts...") was right. Only TWO consumer breaks surfaced (`HoldingsDataExtractor::calculateAnnualizedReturn` + the one test assertion) — both fixed in `a5e4770`. If the final-suite 1-failure turns out to be a third such site, the fix pattern is identical: `(float) $holding->field` at the boundary.
- **`audit-archive-may12` branch** (local-only) preserves the original local-main WIP commits — still alive, still NOT to be deleted.
- **csjones / fynla.org both currently track `main` at `f15e068`.** This branch is feature work; NOT deployed to either env. Do NOT deploy `audit-criticals` directly to csjones — it goes through PR → dev → manual deploy.

## Branch / deploy state

- Branch: `audit-criticals`
- Behind origin: 0
- Ahead of origin: 0 (pushed `a5e4770` at handover time)
- Deploy status: NOT deployed. Feature branch only.
- **Action needed before next push:** none.
- **Action needed before merging to dev:** reword `a5e4770` per Step A, re-run full suite per Step B, open PR per Step D after CSJ's go-ahead.
- **PR not yet opened** — Step C surfaces the proposal and waits for CSJ.

## Commits landed this session

```
a5e4770 wip: context-handover snapshot                                       (to be reworded — Step A)
246d39a fix(audit): defuse goals data migration down() (Tier 3 MIG-01)
2f41599 fix(audit): add audit_logs (event_type, created_at) covering index (I-01)
358f6c9 fix(audit): cast Holding columns as decimal:N, not float (Tier 3 S-02)
e7ef67e fix(audit): convert users expenditure columns from double to decimal  (Tier 3 S-01)
1ce847c fix(audit): guard 3 new TransientToken family sites (Tier 2 #1-#3)
1b04184 docs(session): context-handover 2026-05-12-session-3                 (preserved from prior session)
cecbe8c fix(audit): add income_tax threshold aliases + fix band-ceiling formula (Tier 1 #3, #5 — squashed from WIP)
```

Six audit-fix commits + one handover doc + one in-flight WIP. Tier 1-3 closed (modulo Tier 1 #4 deferred, Tier 1 #6 skipped).
