---
type: handover
mode: context-clear
date: 2026-09-08
session: 2
branch: fix/f0-savings-engine-dispatch
trigger: context-handover skill (CSJ asked for a logical stop to /clear)
---

# Context Clear Handover — 2026-09-08, Session 2

## Immediate state

Mid-way through Task 8 (verification) of `September/September8Updates/fyn-wiring-batch-a-plan.md`: Tasks 1–7 are implemented and committed on `fix/f0-savings-engine-dispatch`, the web dashboard shows the revived savings recommendations, and the very next thing being checked was why the Savings module's own **Strategy tab** (`/savings`, tab "Strategy") says "No recommendations" for the young family persona while the dashboard shows four and the engine returns nineteen.

## The thread

- Session 1 (same session, before this handover) produced the Fyn wiring artifact (https://claude.ai/code/artifact/7375932e-a8e0-4920-9142-5a2db33b2d88, findings F0–F19) and the Batch A plan. CSJ decisions recorded in the plan header: all eight previously unevaluated savings rows are implemented (none disabled), goals live in one mechanism (seeded rows evaluated by the service; Fyn proposes an emergency-fund goal when the user is short), State Pension from tax config with no literal, emergency-fund months 3-6-9 with retired at 3, rate basis reconciled in `RateComparator`.
- CSJ was explicit that ISAs are tax-free: never suggest moving ISA money into taxable interest. The regular-saver rule therefore points a Cash ISA only at a regular saver ISA and excludes Junior ISAs; the parental-settlement rule counts non-ISA child accounts only (Junior ISA interest is tax-free and outside HMRC's £100 rule).
- Root cause of F0 confirmed: the savings dispatcher switched on `trigger_config.condition` but every arm was named after the row `key` (both born in commit 446b04d0a, 2026-03-14). Zero of 36 agent rules had ever fired. Second fault: the Fyn path passed the savings analysis without `user_id`.
- Dry-running the revived engine against the preview personas exposed a second wave of content defects (mortgage rate ×100, duplicate cards, Premium Bonds as zero-rate, the excess-cash cascade firing every rung, Junior ISAs identified by three vocabularies, seeded Junior ISAs with no child link). All fixed in commit 5b1cfa55f; `SavingsAccount::isJuniorIsa()` is now the one predicate.
- **Another session is using the main checkout** (`/Users/CSJ/Desktop/fynla` is on `tmp/build-773-plus-781` with an in-progress merge, `.git/MERGE_HEAD` present, iOS files unmerged). Do NOT touch it. All Batch A work lives in the worktree `/Users/CSJ/Desktop/fynla-f0` (branch `fix/f0-savings-engine-dispatch`, vendor copied, `.env` copied, `public/build` + `public/m-build` copied from the main checkout, no `public/hot`).
- Rejected: disabling rows that lacked evaluators (CSJ: "they must be implemented"); reverting the ISA exclusion on the regular-saver rule (would move tax-free money to taxable).

## Files touched this session (all on the branch, 9 commits ahead of `e05c8ef41`)

- Engine: `app/Services/Savings/SavingsActionDefinitionService.php` (dispatcher on condition; 11 new evaluators; goal family deleted; resolver extended; thresholds from rows), `app/Services/Savings/RateComparator.php`, `app/Services/Savings/EmergencyFundCalculator.php`, `app/Agents/SavingsAgent.php` (analysis carries `user_id`; private goal builder deleted; delegates to the service), `app/Services/Coordination/PlanSources/SavingsStrategySource.php`, `app/Services/Plans/SavingsPlanService.php`, `app/Services/Retirement/RetirementActionDefinitionService.php` (State Pension literal removed at two sites).
- Model / consumers: `app/Models/SavingsAccount.php` (`isJuniorIsa()`), `app/Http/Controllers/Api/SavingsController.php`, `app/Http/Resources/SavingsAccountResource.php`.
- Seeds / data: `database/seeders/SavingsActionDefinitionSeeder.php` (10 new rows, goal rows moved to `source => agent`, templates fixed), `database/seeders/PreviewUserSeeder.php` (links Junior ISAs and education goals to the named child), `database/factories/SavingsAccountFactory.php` (percentage rate), `resources/js/data/personas/young_family.json` (Sophia → Sophie).
- Tests: `tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php` (guard, all six engines), `tests/Unit/Services/Savings/SavingsActionDefinitionServiceTest.php`, `tests/Unit/Services/Savings/RateComparatorTest.php`, `tests/Unit/Services/Savings/EmergencyFundCalculatorTest.php`, `tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php`, `tests/Unit/Agents/SavingsAgentTest.php`, `tests/Unit/Agents/SavingsAgentGoalsTest.php`.
- Docs: `September/September8Updates/fyn-wiring-batch-a-plan.md`.

## WIP commit

- SHA: see `git log -1` on the branch (subject `wip: context-handover snapshot`, adds the plan document).
- Pushed: yes, `origin/fix/f0-savings-engine-dispatch` (if the push line above the handover reported a failure, push first thing).

## Open decisions

- None blocking. CSJ answered every plan question. One product-level note for CSJ, not a blocker: `regular_saver_opportunity` fires on David's Cash ISA at £833/month, suggesting a regular saver ISA; most regular saver ISAs cap monthly deposits well below that. Default direction: leave the rule, let Fyn caveat it.
- F20 (new, reported not fixed): `savings_market_rates` is seeded only for `2025/26` while the active tax year is `2026/27`, so `RateComparator::getMarketBenchmarks` falls back to a flat 4.00 % in every environment. The seeder hardcodes the year (`SavingsMarketRatesSeeder.php:26`). Default direction: seed the active year from `TaxConfigService::getTaxYear()`; the rate values are CSJ's call.

## Pick up from here (auto-continue contract)

1. `cd /Users/CSJ/Desktop/fynla-f0` (the worktree). Confirm `git status` clean and `git log --oneline -3` shows the handover + wip commits. Do not switch the main checkout's branch.
2. **Open defect, next check:** the Savings module Strategy tab shows "No recommendations" for the young family persona. `GET /api/savings/recommendations` → `SavingsController::recommendations` (`:257`) → `SavingsPlanService::getRecommendations` (`:89`) → `evaluateAgentActions`. The dashboard (`RecommendationsAggregatorService`) and the engine dry run (`SavingsAgent::generateRecommendations`) both return the savings items, so the divergence is inside that endpoint. Reproduce with tinker in the worktree: `app(\App\Services\Plans\SavingsPlanService::class)->getRecommendations(<young_family uid>)` (persona uid via `User::where('preview_persona_id','young_family')->first()`; the ids changed on reseed). If it returns items, the fault is the SPA (cached empty Vuex state or a shape mismatch in `SavingsDashboard.vue` / `savingsService.js:31`); if it throws, fix the service. Then re-verify in the browser.
3. Finish Task 8 of the plan: `/m` verification per the `verify-m` skill (worktree server: `cd /Users/CSJ/Desktop/fynla-f0 && php artisan serve --host=127.0.0.1 --port=8001`; the built `/m` bundle is in `public/m-build`), and the Fyn check: ask "How is my emergency fund looking?" as a persona and confirm `<financial_context>` carries the savings item with a `Triggered by:` line (admin AI audit or `ai_messages`).
4. Full suite: the background run from the worktree finished after the handover was written: **222 failed, 30 skipped, 8,240 passed** (2,046 s). Only its last 25 lines survived (the command piped through `tail`). The one visible failure (`tests/Feature/Validation/ValidatedRangeReachesTheColumnTest.php`, savings 12.5 % rate) passes in isolation (10/10), which points at contention: the other session runs tests against the same testing database, and two reseeds ran mid-suite. Treat the 222 as unclassified, not as green. Rerun once in the worktree with the full output kept (`./vendor/bin/pest --compact > /tmp/f0-suite.txt 2>&1`), list the failing files, rerun those files alone, and for any that still fail run the same files on `dev` to separate pre-existing from branch-caused before opening the PR. Load `test-failure-forensics` first.
5. Rebase before the PR: `origin/dev` is 7 commits ahead of the branch base (`git rev-list --count HEAD..origin/dev` = 7). Rebase onto `dev`, rerun the savings families (`tests/Unit/Services/Savings tests/Unit/Agents/SavingsAgentTest.php tests/Unit/Agents/SavingsAgentGoalsTest.php tests/Feature/Database/ActionDefinitionDispatchCoverageTest.php tests/Unit/Services/Coordination/ComposedSavingsPlanTest.php tests/Unit/Services/Plans/GoalIntegrationTest.php`, 95 green at handover), squash the wip commit into the plan-doc commit, open the PR to `dev` with the evidence, then remove the worktree (`git worktree remove /Users/CSJ/Desktop/fynla-f0`) once it lands. Never recommend deploying; CSJ decides.
6. Update the artifact's F0, F15, F16, F17 entries to "fixed on branch" and add F20 (republish via the Artifact tool with the URL above; the source HTML is only in the old session's scratchpad, so `action: "read"` the artifact first).

## What the next Claude needs to know

- **Two sessions, one checkout.** The main checkout belongs to the iOS session right now. My unstaged edits were pulled out of it and reapplied in the worktree; the main tree was restored to what that session expects. The worktree shares the same local MySQL database (`laravel`), and the tests use the testing DB; both reseeds below were run from the worktree.
- **Local DB state:** `SavingsActionDefinitionSeeder` and `PreviewUserSeeder` have been re-run with the new rows and links. Persona user ids changed (young_family primary is now 45 at the time of writing; look it up, never hardcode).
- **Rate basis contract:** `savings_accounts.interest_rate` and `mortgages.interest_rate` are percentages; `savings_market_rates.rate` is a decimal; `RateComparator::compareToMarketRates` is the only place they meet and now returns both `account_rate` (decimal) and `account_rate_percent`. Evaluators read the comparison, never the column ×100.
- **Junior ISA contract:** seeded and form-created rows carry `account_type = 'junior_isa'`; older rows carry `isa_type` `'junior'` or `'junior_isa'`. `SavingsAccount::isJuniorIsa()` is the one predicate; the agent, controller, resource and service all use it.
- **Spouse rules** still read the calculator's own 75 % "approaching" literal (`PSACalculator.php:58`) while the seeded `psa_approaching` row says 80: two thresholds for one concept, reported not fixed.
- **Tinker gotcha:** `php artisan tinker <file>` stays in the interactive shell and never exits; use `--execute` with a `require` or a heredoc, or wrap in `timeout`.
- **Chrome:** Playwright MCP was held by the other session; the claude-in-chrome bridge was used instead (persona selected via the landing page "See our demo" modal, never a direct URL). The worktree server on :8001 was stopped at handover; the main checkout's `./dev.sh` on :8000/:5173 belongs to the other session.
- The engines report from the explorer (26 defects, `recommendation-engines-report.md`) and the onboarding report only existed in the old session's scratchpad; everything verified from them is in the artifact.

## Branch / deploy state

- Branch: `fix/f0-savings-engine-dispatch` (worktree `/Users/CSJ/Desktop/fynla-f0`)
- Behind origin/dev: 7 commits (rebase needed before PR)
- Ahead of origin/dev: 10 commits including this handover
- Deploy status: Not deployed. Not yet PR'd.
