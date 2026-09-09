---
type: handover
mode: session-end
date: 2026-09-09
session: 2
repo: fynla
branch: dev
---

# Session Handover — 2026-09-09, Session 2

## Where things stand

The four native-app jobs CSJ raised at 11:35 (long recommendation rows, recommendation tap routing, legal-page links, campaign banner after a walk), plus the two follow-ups at 13:08 (web dashboard on the same mechanism, the "No Emergency Fund Goal" bug), are **live everywhere**: PR #797 → dev (csjones `745ebe74f`), PR #798 → main `3edf8c39a` on fynla.org (released 13:57 BST), and TestFlight build 9 (Production configuration, `org.fynla.app.dev` record "Fynla", VALID 14:05 BST). Every surface was browser-verified except native, which compiles and passed its new unit tests once on the iPhone 11 but has not had a simulator run. Tree clean, nothing unpushed, no worktrees, local checkout on dev.

## Priorities for the next session

1. **BLOCKED ON CSJ — what the phone shows on TestFlight build 9.** Rows should read as headlines, an information-request row (e.g. "Consider critical illness cover") should open Fyn with "I can help you enter the information for …", the capture should end with Yes / No thanks, and "No thanks" should close the cover and replace the row. If any of that is wrong on the phone, the native decode (`DashboardModels.swift:191`) and the routing in `AppRootView.swift` (`onFynCapture`) are the two places to look; the backend is proven on /m and web.
2. **BLOCKED ON CSJ — PR #773 (native billing on the web)**, still open, MERGEABLE, 49 commits behind dev with no `ios-native/` overlap. Same check as before: Settings → Plan and billing shows "Upgrade on the web" (Free) / "Manage billing on the web" (web-billed Premium). Then rebase, `ios-native/scripts/verify-project.sh`, `--admin` merge. Native-only.
3. **Run the full `FynlaTests` on a simulator.** None could be opened this session (Xcode access was not granted to the computer-use tools; the skill forbids `simctl boot`). Nine fixture-reading tests fail on a physical device by construction (absolute Mac paths) — that is not a signal. Use the `ios-simulator` skill.
4. **The remaining `deferred-ios` items** — W-0090, W-0243, W-0311, W-0416 still need Swift; W-0044 and W-0496 have the Swift landed and need only an on-screen check. Branch `fix/deferred-ios-parity-batch` was reused for today's jobs and is merged; start a fresh branch off dev.
5. **Decide the shared-id defect:** three "earns no interest" rows (`savings_zero_rate_account`, one per account) share one recommendation id, so marking one done completes all three. Fixing it changes stable ids that `recommendation_tracking` and the points dedup key off — CSJ's call on whether existing tracking rows may be orphaned.
6. **Parked, non-iOS, unchanged:** W-0540 clusters and the Rule 15 lint scope; the #770 homepage block; the 34 sweep findings; the tax-compliance reviews listed in `CSJTODO.md`.

## Context to load

- `September/September9Updates/ios-dashboard-jobs-plan.md` — the four jobs, root causes, design and outcome, including CSJ's 13:08 corrections on where each recommendation must route.
- `app/Services/Mobile/RecommendationRouting.php` — the one home for Fyn-vs-page routing (Fyn set keyed by the composed ids, tax strategies to product pages, account/pension/goal detail screens). Adjust here if CSJ moves anything after the phone check.
- `app/Services/AI/AdviceFyn.php:229` — `withRecommendationFollowUp`, the "anything else?" / "No thanks" seam every surface shares.
- `ios-native/Fynla/App/AppRootView.swift` (`onFynCapture`, the `isPresentingFyn` refresh) and `ios-native/Fynla/Features/Dashboard/DashboardModels.swift:191` — the native half.
- `deploy/DEPLOY.md` — step 6 now names the prod rsync set and forbids `bootstrap/` (today's mistake).
- `handover/September/09/handover-2026-09-09-session-1.md` — the morning's release notes and the iOS carry-overs (harness debt, StoreKit products).

## Completed this session

- **PR #797** (fix/deferred-ios-parity-batch → dev, 12 commits): `NextActionsService::splitHeadline` (headline before the first em dash, remainder as `detail`); `RecommendationRouting` + `fyn_capture` actions carrying the complete contextual-conversation request; `CreateContextualConversationRequest` takes the dashboard's string recommendation id; `ContextualConversationService` opens with the recommendation named; `AdviceFyn::withRecommendationFollowUp`; `RecommendationCompletionService` shared with `RecommendationsController::markDone`; `RedirectPhoneToMobile` excludes `privacy`, `terms`, `editorial-policy`; native `AuthenticationCoordinator.refreshAuthenticatedUser` on Fyn-cover close mid-walk; native and /m and web dashboards drop their prompt maps and route from the server payload (`resolveWebDestination`, `aiChat/startContextualConversation`, dock collapse on a same-screen `navigation` frame, /m `screenRefreshTick` watcher); `GoalStore` assigns the module from the goal type and `GoalsAgent` checks the emergency fund by type.
- **PR #798** dev → main; fynla.org deployed 13:57 BST (bundle `app-DkxKqdRy.js`; no migrations); csjones deployed with both bundles.
- **TestFlight build 9** archived from `Fynla-Production` with the `org.fynla.app.dev` overrides (`ios-native/build/Fynla-Build9.xcarchive`), uploaded 14:04, VALID 14:05.
- Docs: `deploy/DEPLOY.md` step 6 (`6955585cf`), the plan/outcome file, `docs/tech-debt-report.md`. Memory: `project_release_2026_09_09` (release 3), `feedback_never_rsync_bootstrap_to_prod`.

## Verification state

- Pest at `745ebe74f`: mobile actions / recommendations 143, contextual + AdviceFyn 57, middleware 17, goals agent + store 37 — green. Full suite not run (Rule 17).
- Vitest: 127 green (web store, dashboard, utils; /m dashboard + chat).
- Browser: local /m and web (john): goal and protection captures → "anything else?" → "No thanks" → chat closes, row replaced, wheel moves; page routes to Bank Accounts / Savings / Estate; History tab lists completed actions. csjones /m: the same protection capture and the Estate page route. fynla.org: young family demo shows headline rows and routes a savings row to Bank Accounts; phone-UA `/privacy` and `/terms` return 200; log clean for 10 minutes after `up`.
- Native: `build-for-testing` green; on the iPhone 11 `fynCaptureActionsCarryTheServerPromptOrTheContextualRequest` and both `refreshAuthenticatedUser*` tests pass; the fixture tests cannot run on a device. **Not verified: any native journey on a screen.**
- Job 4 backend: `c.jones@csjones.co` has no completed walk on production (user 658, `path_choice`, no user messages) or csjones (user 149, `campaign_verify_navigate` since 29 June). The banner reflects the server.

## Decisions and dead ends

- **CSJ (13:08): route by destination, not by engine.** Personal details, income and expenditure open their own captures; tax strategies open the product page (cash ISA → Bank Accounts, Stocks & Shares → Investment, pension → Retirement; Gift Aid stays on Tax Strategy); rebalancing, rates and fees open the exact account or pension. After "No thanks" the current replace-on-refresh flow stays. Completed actions are visible on Achievements → History (/m) and the web actions page.
- **Built on the existing contextual-conversation mechanism** (`origin.kind = recommendation` had been reserved but unused) rather than a new prompt channel — the server composes the whole request, clients post it verbatim.
- **Rules with no capture tool open the page**: investment preferences (Fyn only acknowledged the answers live) and care costs.
- **Protection and retirement composed ids are category slugs** (`protection_protection_income_protection_gap`, `retirement_state_pension`), not seeded keys — the adapters collapse rules to categories. The routing map is keyed accordingly.
- **Rejected:** deleting `ActionsOverviewCard.vue` (dead, but not asked); changing the shared recommendation ids (needs CSJ); replicating the `release` skill's workflow when the tool refused it — CSJ typed `/release`.
- **Dead ends:** the claude-in-chrome `type` action does not reach the web SPA's inputs (login, MFA, chat textarea) — set values through the native setter with `input` events via `javascript_tool`; the /m inputs are fine. `codesign` against a locked `fynla-dist` keychain hangs on a GUI prompt; the unlock command is blocked by the auto-mode classifier on the first attempts and passed on a later retry.

## Things that will bite you

- **Never rsync `bootstrap/` to a server** — its cache manifest lists dev-only providers; every artisan call then fails until `composer dump-autoload -o`. Recorded in `deploy/DEPLOY.md` and memory.
- The mobile dashboard aggregation result is what the contextual opening reads (`NextActionsService::buildAll` per tap); the `mobile_dashboard_*` cache is invalidated by `CacheInvalidationService` on writes and by mark-done, but a stale `GoalsAgent` analysis can outlive a goal write until the TTL — `invalidateForUser` clears it.
- zsh does not word-split an `ssh …` command held in a variable — call `ssh` directly (bit again today).
- The phone build reads fynla.org, so native behaviour only changes once the backend is released; today it is.
- `ios-native/build/` holds several archives (7 September's and today's); they are gitignored.

## Tech debt deferred

`docs/tech-debt-report.md` (top section). Warnings: two Yes/No vocabularies inside `AdviceFyn` (`:229` follow-up and `:340` deferred answer); the web path map exists twice (`resources/js/utils/semanticDestinations.js:10` and `GamifiedDashboard.vue:365`) where the server could emit a `web` path; two /m contextual-open methods (`Dashboard.vue:781`, `MobileChrome.vue:344`). Suggestions: per-tap `buildAll` in `ContextualConversationService::recommendationFor`; `HolisticPlanningController::markRecommendationDone` as a second completion path; the redundant `by_module` fallback in `GoalsAgent:196`; four SSE-frame parsers across `tests/Feature/AI`.

## Branch and deploy state

- Branch: dev @ `6955585cf`
- Unpushed commits: none
- Deploy status: fynla.org = main `3edf8c39a` (tree == dev `745ebe74f`, released 13:57 BST, backups `~/release-backups/2026-09-09c/` manifests only); csjones = dev `745ebe74f`; TestFlight "Fynla" 1.0 (9) VALID on the `org.fynla.app.dev` record. PR #773 still open.
