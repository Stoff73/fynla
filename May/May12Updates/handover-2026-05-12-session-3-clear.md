---
type: handover
mode: context-clear
date: 2026-05-12
session: 3
branch: audit-criticals
trigger: context-handover skill (tripwire at ~291k tokens)
---

# Context Clear Handover — 2026-05-12, Session 3

## Immediate state

Mid-execution of Tier 1 audit-critical fixes on a new feature branch `audit-criticals` off `dev`. PCLS LSA cap (#1) and SDLT FTB (#2) shipped in commit `8283753`. Tier 1 #3 (income_tax aliases) and #5 (band-ceiling formula) are in commit `49bc64c` as a WIP — tests passed (2,074/2,074), so the next session should amend/squash this WIP into a proper fix commit before merging. Tier 1 #4 (salary sacrifice) deferred — audit's proposed fix is wrong, needs CSJ ruling. Tier 1 #6 (Starting Rate for Savings) and Tiers 2/3 not yet started.

## The thread

- Session bootstrapped via `session-start`; auto-resumed from session-2 handover which had instructed: (1) move WIP audit reports off local main, (2) amend Rule #16 framing per CSJ's 2026-05-12 grandfathering, (3) address remaining ~13 critical issues sequentially.
- Step 0: archived local-main WIP commits (`87b79f4`, `919dbfe`) on `audit-archive-may12` branch, reset main to origin/main, created `audit-criticals` off `origin/dev`, cherry-picked audit reports onto it as `21d8df6`.
- Step 1: amended Rule #16 framing in `REVIEW.md`, `review-frontend.md`, `review-conventions.md` per CSJ's reinterpretation — existing icons grandfathered, only NEW icons banned on dashboards / detail / chat. Preserved C-2's `stroke="#5854E6"` finding as a separate Rule #12 critical. Flagged emoji (`goalIcons.js`) and Unicode-as-icons (`▲▼` in AdminDashboard) as OPEN — pending CSJ decision because Rule #16 lists them as a strict subclass that applies on allowed surfaces too. Committed as `927805e`.
- Step 2 Tier 1 #1 (PCLS LSA cap): added `lump_sum_allowance: 268275`, `lump_sum_and_death_benefit_allowance: 1073100`, `pcls_rate: 0.25` to pension config in `TaxConfigurationSeeder`. Added `TaxConfigService::calculatePCLS(float, float)` helper. Patched 4 call sites (RetirementIncomeService:256, RetirementIncomeService:1937, DecumulationPlanner:232, RetirementActionDefinitionService:1982). Wrote 7 Pest cases covering boundary/cap/LSA-used scenarios. Reseed verified the keys live.
- Step 2 Tier 1 #2 (SDLT FTB): investigation revealed the audit's "singular/plural FTB key" finding was just one of THREE compounding bugs in `GoalAssignmentService::calculateSDLT` — also (a) rates divided by 100 when seeder stores them as decimals (off by 100x) and (b) wrong band semantics. Replaced the buggy local calc by injecting `PropertyTaxService` and delegating to its canonical `calculateSDLT()`. Wrote 5 Pest cases. Committed both #1 and #2 as `8283753`. Pushed branch.
- Step 2 Tier 1 #3 (DecumulationPlanner `higher_rate_threshold` config key): added top-level `higher_rate_threshold: 50270` and `additional_rate_threshold: 125140` aliases to `income_tax` config in the seeder. The existing `?? 50270` fallbacks in 13+ consumers (DecumulationPlanner, RetirementActionDefinitionService×3, SavingsActionDefinitionService, PSACalculator, TaxOptimisationService, TaxActionDefinitionService×2, AdvicePromptBuilder) now hit real values. Wrote `IncomeTaxConfigAliasTest.php` pinning the alias-to-bands invariant.
- Step 2 Tier 1 #5 (band-ceiling formula): fixed `personalAllowance + bands[1]['max']` in TaxBandTracker, UKTaxCalculator, PropertyTaxService — was wrong because `bands[1].max` stores the absolute £125,140 ATR, not a band width. New fallback chain: top-level alias → `bands[1].upper_limit` → legacy formula. The bug was latent (masked by PA-taper coincidence) and only manifests for unusual tax-year configs.
- Step 2 Tier 1 #4 (salary sacrifice £2,000) DEFERRED. Audit's proposed fix is to add `salary_sacrifice_limit: 2000` to config. Investigation showed the £2,000 in `RetirementStrategyService:1186` is misleading — `nic_exemption_cap: 2000` already exists in the seeder under `pension.salary_sacrifice` with `effective_date: 2029-04-06`. The current rule has NO £2,000 cap on salary sacrifice (bounded only by £60k Annual Allowance). Adding the audit-proposed key would codify the wrong rule. The correct fix is probably to remove the £2,000 special-case from `calculateNetCostOfContribution` entirely, but that needs CSJ judgement on the intent.
- Full unit suite (2,074 tests) ran in the background while I prepared this handover — exit 0, all green.

## Files touched this session

```
A  May/May12Updates/REVIEW.md                 (cherry-picked from archive, then amended)
A  May/May12Updates/review-*.md (×7)          (cherry-picked from archive; frontend + conventions amended)
M  database/seeders/TaxConfigurationSeeder.php
   + pension: lump_sum_allowance, lump_sum_and_death_benefit_allowance, pcls_rate
   + income_tax: higher_rate_threshold, additional_rate_threshold (top-level aliases)
   + 2021/22 override: additional_rate_threshold = 150000
M  app/Services/TaxConfigService.php          (calculatePCLS helper)
M  app/Services/Retirement/RetirementIncomeService.php  (lines 256, 1937)
M  app/Services/Retirement/DecumulationPlanner.php      (line 232)
M  app/Services/Retirement/RetirementActionDefinitionService.php  (line 1982)
M  app/Services/Goals/GoalAssignmentService.php         (delegates to PropertyTaxService)
M  app/Services/TaxBandTracker.php                       (band-ceiling fallback chain)
M  app/Services/UKTaxCalculator.php                      (band-ceiling fallback chain)
M  app/Services/Property/PropertyTaxService.php          (band-ceiling fallback chain)
M  tests/Unit/Services/Retirement/DecumulationPlannerTest.php  (mock calculatePCLS)
A  tests/Unit/Services/Tax/TaxConfigServicePCLSTest.php       (7 cases)
A  tests/Unit/Services/Tax/IncomeTaxConfigAliasTest.php       (3 cases)
A  tests/Unit/Services/Goals/GoalAssignmentServiceSDLTTest.php (5 cases)
```

## WIP commit

- SHA: `49bc64c`
- Subject: `wip: context-handover snapshot`
- Pushed: **yes** (origin/audit-criticals at `49bc64c`)
- **Next session should**: amend/squash this WIP into a proper commit titled `fix(audit): add income_tax threshold aliases + fix band-ceiling formula (audit Tier 1 #3, #5)`. Tests already verified GREEN (2,074 passed, exit 0) — the WIP designation is procedural, not because the work is incomplete. After amending, force-push the branch (it's a feature branch off dev; safe to force-push since only this work lives on it).

## Open decisions

1. **Tier 1 #4 — salary sacrifice £2,000 in `RetirementStrategyService:1186`.** Audit proposed adding `salary_sacrifice_limit: 2000` to pension config. Analysis showed this is wrong — the £2,000 NIC exemption cap is the post-2029 reform, not a current rule. Need CSJ ruling: (a) remove the £2,000 special-case from `calculateNetCostOfContribution` entirely (current correct rule), (b) leave as-is and add a config key with comment marking it as "future-2029 cap", (c) keep both paths and toggle by effective date. **Default direction-of-travel: (a) remove the special-case.** Auto-resume should NOT touch this without explicit CSJ confirmation.

2. **Rule #16 emoji + Unicode-as-icons.** Whether `goalIcons.js` (🔥 🎯 📈 ⭐ 🏆) and `AdminDashboard.vue:199` (`▲`/`▼`) are also grandfathered. Rule #16 lists both as a strict subclass banned even on allowed surfaces. **Default direction-of-travel: defer until Tier 1-3 lands; surface to CSJ as a separate review item.** Auto-resume should NOT act on this.

3. **Tier 1 sequencing.** Handover writes "13 critical issues in 3 tiers". Tier 1 has 6 items (3 done, 1 deferred, 2 not started). Continue with Tier 1 #6 (Starting Rate for Savings) before moving to Tier 2, OR skip directly to Tier 2 (TransientToken family — more security-critical, less domain work). **Default direction-of-travel: skip Tier 1 #6 and move to Tier 2.** Reasoning: Starting Rate for Savings is a significant new implementation (HMRC taper rule for non-earner savers) — more like Phase B sprint work. Tier 2 TransientToken family is 3 small instanceof guards with high security impact. Better tradeoff.

## Pick up from here (auto-continue contract)

Execute in this order:

### Step A: Squash the WIP commit (3 min)
```bash
git log --oneline -5            # Confirm 49bc64c is HEAD with subject "wip: context-handover snapshot"
git reset --soft HEAD~1         # Unstage the WIP commit, keep changes
git status                      # Confirm 5 files staged
git commit -m "$(cat <<'EOF'
fix(audit): add income_tax threshold aliases + fix band-ceiling formula

Addresses Tier 1 audit findings #3 (DecumulationPlanner non-existent config
key) and #5 (band-ceiling formula bug, currently latent) from
May/May12Updates/REVIEW.md.

income_tax threshold aliases (#3):
- Add `higher_rate_threshold: 50270` and `additional_rate_threshold: 125140`
  as top-level keys on `income_tax` config in TaxConfigurationSeeder.
- Add 2021/22 historical override (additional_rate_threshold = 150000).
- ~13 consumers (DecumulationPlanner, RetirementActionDefinitionService,
  SavingsActionDefinitionService, PSACalculator, TaxOptimisationService,
  TaxActionDefinitionService, AdvicePromptBuilder) previously fell back to
  `?? 50270` / `?? 125140` literals because the keys didn't exist. Now they
  hit real config values.

Band-ceiling formula fix (#5):
- TaxBandTracker, UKTaxCalculator, PropertyTaxService computed
  `personalAllowance + bands[1]['max']`. `bands[1]['max']` stores the
  absolute £125,140 ATR, not a band width — so the formula returned
  £137,710 instead of £125,140 whenever PA was non-zero.
- Bug was latently masked: under current tax years anyone earning above
  £125,140 has PA fully tapered to £0, so 0 + 125140 = correct. Breaks
  immediately for any tax-year config without full PA taper.
- All three sites now read the top-level alias first, fall back to
  bands[1].upper_limit (absolute), then legacy formula as last resort.

Pinning test: `tests/Unit/Services/Tax/IncomeTaxConfigAliasTest.php`
asserts higher_rate_threshold == bands[0].upper_limit == 50270 and
additional_rate_threshold == bands[1].upper_limit == 125140 and
PA + bands[0].max == bands[0].upper_limit.

Full unit suite green (2,074 tests passed).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
git push --force-with-lease origin audit-criticals
```

### Step B: Surface deferred decisions to CSJ
Print a single block summarising:
- Tier 1 #4 salary sacrifice — needs CSJ ruling per "Open decisions" #1 above
- Rule #16 emoji/Unicode — needs CSJ ruling per "Open decisions" #2 above
- Tier 1 sequencing — proposing skip #6, move to Tier 2 per "Open decisions" #3 above

DO NOT block waiting for replies. Proceed with Step C while CSJ is reading.

### Step C: Start Tier 2 (TransientToken family — 3 sites)

Per `May/May12Updates/REVIEW.md` §4 critical rows #3, #4, and the audit's `reference_transient_token_family_bugs.md` memory file.

1. `app/Http/Controllers/Api/V1/Auth/TokenRefreshController.php:20-26` — add `instanceof PersonalAccessToken` guard around `currentAccessToken()->delete()`. Under SPA cookie auth (`TransientToken`), this is a no-op and the old token isn't revoked. Return 400 with "use bearer-token refresh flow" if cookie-auth.
2. `app/Services/Eval/EvalBypassGate.php:45-50` — guard before `$token->abilities`. Eval-only gate; failure mode = security boundary bypass.
3. `app/Http/Middleware/EnsureMFAVerified.php:31` — guard before `?->can('mfa_verified')`. Under SPA cookie, the `?->` short-circuits but for the wrong reason — fail closed.

Fix pattern (copy from PR #277 / #278):
```php
use Laravel\Sanctum\PersonalAccessToken;

$token = $user->currentAccessToken();
if (! ($token instanceof PersonalAccessToken)) {
    return $this->errorResponse(/* fail-closed response */);
}
$token->delete();
```

Each site needs a Pest unit case mocking a `TransientToken` and asserting the fail-closed path.

### Step D: Tier 3 (data layer)
After Tier 2 lands, address:
- `users` table float→decimal migration for ~18 expenditure columns (audit finding S-01, see `review-database.md` §1)
- `Holding` model `'float'` → `'decimal:2'` / `'decimal:6'` casts (audit S-02)
- `audit_logs` composite index (event_type, created_at) (audit I-01)
- Remove `truncate()` from `2026_01_18_000003_migrate_existing_goals_data` `down()` (audit MIG-01)

These are bigger surgeries — schedule each as its own commit + Pest regression test.

### Step E: After Tier 1-3 done
Pause and ask CSJ before kicking off Phase B from `REVIEW.md` §6. Don't auto-continue into Phase B.

## What the next Claude needs to know

- **DO NOT push to `main` or `dev` from this branch.** Open a PR from `audit-criticals → dev` only after CSJ reviews. Branch protection enforces this anyway but be explicit.
- **The audit-archive-may12 branch holds the original local-main WIP commits** (`87b79f4` audit, `919dbfe` session-2 handover). Don't delete — that's the safety net. NOT pushed to origin.
- **Reseed already happened this session** for `TaxConfigurationSeeder`. New keys are live in local DB. The session-start phase-1c reseed runs again next session — that's idempotent.
- **DecumulationPlannerTest.php has a Mockery test (12 cases)** that now requires a `calculatePCLS` stub. I added it. If any other test in the codebase mocks `TaxConfigService` and calls a service that hits PCLS, it'll fail the same way. Watch for this if Step C's TransientToken work touches any service that resolves TaxConfig in test fixtures.
- **Audit `review-tax-compliance.md` flags the seeder's 2022/23 historical additional-rate-threshold as wrong** (£125,140 inherited; should be £150,000). I did NOT fix that — out of scope for the audit's stated #3 finding. Open question whether to fix in Step D or later.
- **The `RetirementStrategyService.php:1186` salary-sacrifice £2,000** is the file/line CSJ will be asked about in Step B Open Decision #1. Read it before answering CSJ — context starts at line 1175 (private function `calculateNetCostOfContribution`).
- **Memory files worth re-reading before TransientToken work:** `reference_transient_token_family_bugs.md` (lists 6 prior sites + fix pattern + fail-closed response selection).
- **Per `feedback_dev_server_is_separate.md` + CLAUDE.md:** don't `pkill -f vite` mid-session (sibling project on same Vite). The session-start phase-1e check is non-destructive — fine to leave dev server alone.

## Branch / deploy state

- Branch: `audit-criticals`
- Behind origin: 0
- Ahead of origin: 0 (pushed `49bc64c` at handover time)
- Deploy status: NOT deployed. This is feature-branch work — no csjones smoke, no production deploy.
- **Action needed before next push:** none. Branch is in sync.
- **Action needed before merging to dev:** squash the WIP per Step A, open PR `audit-criticals → dev`, CSJ approves.
- **PR not yet opened** — `gh pr create` is Step F (deferred until Tier 1-3 complete and CSJ has reviewed).
