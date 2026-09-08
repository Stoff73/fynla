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

---

# Appended by the iOS / release-follow-up session — 2026-09-08, 07:42–13:55 BST

The other session in this checkout today. Started from the 2026-09-07 handover
(`handover/September/07/handover-2026-09-07-session-1.md`); everything below is
**merged to dev** unless marked otherwise. `origin/dev` = `1168ec8a6`. The main
checkout was left on `dev` at `e05c8ef41`, deliberately **not pulled** so nothing
moved under the Fyn session; pull it when that session is done
(`git pull origin dev` is a fast-forward of five merges).

## Merged today (in order)

| PR | What | Verification |
|---|---|---|
| #777 | Board: W-0532/33/34 closed with outcome sections, `tasks.md` ticked | docs |
| #778 | `ios/` Capacitor target, `capacitor.config.ts`, `deploy/mobile/build-ios.sh` removed. Icon artwork was byte-identical to `ios-native`'s; the 31 tracked files stay in history | docs + verifier |
| #779 | Capacitor runtime stripped from the web SPA, `/m` scaffold, CSP `connect-src` and CORS; 17 `@capacitor*`/`@capgo` npm packages gone; `utils/platform.js` (no importers) deleted | Vitest 1,291; Pest 28 (CSP + mobile scaffold); Playwright web + `/m` sign-in |
| #781 | **Native registration fix** + both schemes on fynla.org + test-target signing (see below) | proven on CSJ's iPhone 11 against fynla.org: code screen reached, account created (`c.jones@csjones.co`, user 658) |
| #776 | Three production browser-pass findings: Free users no longer fire `calculate-iht` on property edits (`netWorth/syncRelatedModules` gates on `auth/hasFullCapability('estate')`) or the will-builder probe on the Estate dashboard (only in `mode === 'full'`); `/m` "Today's insight" amounts formatted as currency (`DailyInsightService`); Exit Demo fallback referrer via `withBase('/')` | Playwright local, web + `/m`; Pest 39; Vitest 24. **NOT verified:** the `/fynla/` base on csjones itself |
| #780 | **Legacy plans collapsed to Premium in the data**: `2026_09_08_100000_collapse_legacy_plans_to_premium` rewrites `users.plan`, `subscriptions.plan`, `payments.upgrade_from_plan`, `payments.plan_slug`, `invoices.plan_name` (→ "Premium"), `discount_codes.applicable_plans`, and `deletion_reason` `trial_expired` → `subscription_cancelled_grace_ended`; narrows the three enums. Runtime legacy mapping, `TierResolver::isGrandfatheredLegacyPaid`, `CheckFeatureAccess` (`feature:` middleware) removed; `SubscriptionFactory` makes premium only | `LegacyPlanCollapseMigrationTest` runs the real migration; 1,177 in the tier/billing/stores/payment/middleware/marketing/audit/architecture families; migration run on the local dev DB. Full suite deliberately NOT run (CSJ: targeted families are the standard) |

## Still open — needs CSJ

1. **#773 (native billing on the web)** — rebased onto dev in a scratch worktree, 2 commits, mergeable, docs conflicts resolved in favour of the fynla.org wording with #773's "One TestFlight app" section kept. **The build on CSJ's phone right now is #773 + #781**, pointing at fynla.org. Waiting on CSJ to see Settings → Plan and billing: Free account → "Upgrade on the web"; web-billed Premium → "Manage billing on the web". Then `gh pr merge 773 --merge --admin`. His earlier report ("two plans + Something went wrong") was the pre-#773 dev build's StoreKit path with no IAP products in ASC, not a server fault.
2. **W-0540 dead-component clusters** and the **Rule 15 lint scope** — carried from 5 and 7 September, still undecided.
3. **Homepage pension-check block** parked in #770's description — finish or drop.
4. **Prod housekeeping:** delete `~/release-backups/2026-09-07*/` on the server; set `COMPANIES_HOUSE_API_KEY` and `GETADDRESS_API_KEY` in prod `.env`. Plus, found today: the Apple verification bridge is unconfigured on prod (`php artisan route:list` there throws `invalid_configuration` from `SymfonyAppleBridgeClient`; only the Apple webhook/receipt routes depend on it, the entitlement endpoint does not).
5. **#780 on production is a release step**: `mysqldump` `users subscriptions payments invoices discount_codes` first (a backup, nothing is deleted), then `migrate`. Renewals keep the stored amount so no price changes. csjones also needs `git pull origin dev`, both bundles rebuilt/uploaded (#776 and #779 touched `resources/mobile/`), and the migration run.

## The registration bug (for the record)

`AppRootView` rendered `RegistrationView` from two `switch` arms, `.signedOut` and
`.authenticating`. The submit flips the session between them; SwiftUI gave the view
a new identity, the old one's `onDisappear` cancelled the in-flight request and cleared
both password fields, and the cancellation is deliberately silent. Login survived only
because its `onDisappear` does not cancel. Fix: one arm for both states. Regression:
`registration-slow-success` UI-test scenario (suspends after the state flip) +
`testRegistrationSurvivesTheSessionStateChangeWhileTheRequestIsInFlight`. **The shared
UI-test typing helper cannot clear the email keyboard off the password field on an
iPhone 11** (recording in the local result bundle), so the test is simulator/CI only.
Also fixed: `FynlaTests`/`FynlaUITests` now set `CODE_SIGN_ENTITLEMENTS = ""` — `Base.xcconfig`
gave every target the app's push/associated-domains entitlements, fatal when signing
the test bundles for a real device.

## Decisions taken today (CSJ)

- **Both native schemes read fynla.org** ("a better way to test the iOS"). Staging keeps `org.fynla.app.dev`, `staging` tag, development push. Every account a tester registers is a real production account. The "TestFlight reads csjones" trap in CLAUDE.md / ios-native/CLAUDE.md / TESTFLIGHT.md / memory is rewritten.
- **One iOS folder**: `ios-native/` only; Capacitor gone from repo, bundles and backend config.
- **Two states in the data, not just at runtime**, including financial history ("yes rewrite history too").
- **No full Pest suite for a schema change**; grep the tree for the retired values, run families, let CI's suite on the PR do the rest. Memory `feedback_no_full_suite_per_small_change` amended.
- **Merge order** #781 before #773 (done; #773 rebased).

## Dead ends and things that bite

- **Two sessions, one checkout**: my branch switches dragged the Fyn session's unstaged Savings edits along and a temp merge left the tree mid-merge for them. Memory written: `feedback_concurrent_session_in_main_checkout` — check `git status` for foreign files before any checkout; use a scratchpad worktree.
- **`git add` with an already-staged deletion in the list aborts the whole add**, and the commit goes out with only the deletions. Happened twice (#779, #780), both amended. Add only paths that exist.
- **Simulator**: booting via Xcode wedged twice (frozen display, `xcodebuild` saw no destinations). CSJ connected the iPhone 11 instead. Build/install/launch by UDID works: `xcodebuild build -destination "id=00008030-000531C43E60402E" -derivedDataPath <scratch>` then `xcrun devicectl device install app --device 1BBFB594-D615-5252-927F-0CEDDB7FEDAF <app>` and `... process launch --terminate-existing ... org.fynla.app.dev`. I cannot tap the phone; CSJ does the taps.
- **csjones SSH key (`~/.ssh/fynlaDev`) is passphrase-protected** and no agent held it, so csjones codes/tinker were unreachable this session; registration codes went to CSJ's inbox instead.
- **Prod log level is `error`** — no info lines to trace requests; `native_device_sessions` is the only trace of native logins.
- `preview.js:106` has a pre-existing ESLint `no-unused-vars`; left alone.

## Follow-ups to log (not done)

- Retire the `subscription_plans` catalogue (4 legacy rows, `SubscriptionPlanSeeder`, read by 6 files / 7 tests) and `PaymentController`'s legacy `PLAN_ORDER` branch — dead once #780 is live.
- UI-test typing helper on small screens (see above).
- Universal links: fynla.org's site association must list `org.fynla.app.dev` as well as `org.fynla.app` now both builds carry `applinks:fynla.org`.
- `September/` is untracked in the main checkout; the Fyn session owns it.

## Memory written/updated this session

`project_ios_programme_status` (rewritten), `project_release_2026_09_07_rolled_back` (superseded-by-#780 note), `feedback_no_full_suite_per_small_change` (2026-09-08 repeat), `feedback_concurrent_session_in_main_checkout` (new), `MEMORY.md` index lines.
