# PR6 personalised achievements acceptance evidence — 11 August 2026

## Deterministic persona contract

The focused native and `/m` journeys use the same visible canonical contract:

- earned badge: `Added savings details`, reason `You started building your savings picture.`, earned 01/08/2026;
- reached milestone: `Your emergency fund covers a month of your spending.`, reached 02/08/2026;
- in-progress item: `Net worth £10,000`, `£4,000 of £10,000`, 40%;
- inapplicable item: `On track for retirement`, with no progress UI; and
- contextual action: `Review your net worth`, opening the native Net Worth screen or `/m/app/net-worth`.

The browser persona is created through the E2E-only `activeUser` support using
`with_achievement_personalisation: true`. It persists a real savings account,
`PointAward`, `UserMilestone`, and `UserGamification`; the production
achievements services and real v2 endpoint shape the response. The Playwright
test does not mock the endpoint. Native UI-test support supplies the equivalent
server-shaped response to the established unlocked test mode.

## Focused evidence and closure matrix

| Command | Surface | Persona | Route | Result | Issues found | Regression test/fix | Rerun |
|---|---|---|---|---|---|---|---|
| `xcodebuild test -project ios-native/Fynla.xcodeproj -scheme Fynla-Staging -destination 'platform=iOS Simulator,id=94F2B841-2099-4291-88AB-EDAA797ADF75' -only-testing:FynlaUITests/FynlaUITests/testPR6PersonalisedAchievementsJourney CODE_SIGNING_ALLOWED=NO CODE_SIGNING_REQUIRED=NO` | Native iOS, visible iPhone 16 Pro iOS 18.6 simulator | Established unlocked UI-test persona | Dashboard level control → Achievements → Milestones → Net Worth | Initial RED, exit 65 | The new stable badge identifier was absent and the fixture still exposed the old `Reached Builder` content. | Added the parity fixture, stable row/emblem/progress/action identifiers, state/date/progress assertions, and destination assertion. | A first implementation rerun found progress exposed as `40.000000%`; formatting was corrected to a concise percentage. Final rerun passed 1/1 in 33.030s, exit 0, `** TEST SUCCEEDED **`. Result bundle: `/Users/CSJ/Library/Developer/Xcode/DerivedData/Fynla-ejjdpperaozvonemktoydtucoxsh/Logs/Test/Test-Fynla-Staging-2026.08.11_11-55-20-+0100.xcresult`. |
| `env E2E_DB_NAME=laravel_e2e bash scripts/e2e/prepare.sh` | Laravel E2E support | Guarded `laravel_e2e` database | E2E support routes | Passed, exit 0 | None in the guarded preparation step. | Not applicable. | Browser RED/GREEN runs used the prepared database. |
| `env E2E_DB_NAME=laravel_e2e PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin PLAYWRIGHT_CHROME_CHANNEL=chrome DEBUG=pw:browser npx playwright test tests/E2E/mobile/achievements-personalisation.spec.js --project=mobile-chrome` | `/m` at 390×844 in installed Google Chrome | Unique premium user; token stored as `m_scaffold_token`; canonical achievement records seeded server-side | `/m/app/achievements` → `/m/app/net-worth` | Initial assertion RED, exit 1 | The real v2 response rendered the savings badge as locked because the scoped E2E persona support did not yet create canonical achievement records. A prior config-only RED also reported no tests matched the project. | Added the narrowly scoped support flag and production-shaped records, then included `tests/E2E/mobile/**/*.spec.js` in `mobile-chrome`. The journey asserts the successful real v2 response, visible dates/copy, hidden raw provenance, accessible 40% progress, no progress for the inapplicable row, safe action route, and no captured runtime/API errors. | Final run launched `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome` and passed 1/1 in 42.7s, exit 0. |
| `env PATH=/Users/CSJ/.nvm/versions/node/v20.19.5/bin:/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin npx vitest run resources/mobile/views/__tests__/Achievements.spec.js resources/mobile/navigation/__tests__/semanticDestinations.spec.js` | `/m` component and navigation contracts | Canonical component fixtures | Achievements milestone rendering and semantic destinations | New parity assertion RED: 1 failed, 13 passed in the achievements file | Reached milestones reused badge wording, rendering `Earned on 09/08/2026` instead of `Reached on 09/08/2026`. | Added the failing reached-date assertion and a milestone-specific user-facing provenance label; earned badges retain `Earned on`. | Final focused run passed 2 files and 28/28 tests in 7.74s, exit 0. |
| `php -l app/Http/Controllers/TestSupport/E2EController.php` | Laravel E2E support | Not applicable | Not applicable | Passed, exit 0 | None. | Not applicable. | Final rerun reported no syntax errors. |
| `git diff --check` | Whole Task 4 diff | Not applicable | Not applicable | Passed, exit 0 | None. | Not applicable. | Repeated after cleanup and documentation. |
| **Controller-owned: interactive visible iPhone loop** | Native iOS manual acceptance | Deterministic achievements persona | Dashboard level control → Achievements/Milestones → action → back; Dynamic Type or rotation where practical | **Pending controller** | Not yet observed as an interactive manual loop. The focused visible XCUITest above is automated evidence only. | Record each observed issue, add a failing regression, fix, and rerun. | Pending. |
| **Controller-owned: Chrome connector sign-in/user journey** | Installed Google Chrome connector at mobile viewport | Same deterministic persona | Sign in → `/m/app/achievements` → action → `/m/app/net-worth` | **Pending controller** | Not yet observed through the connector. The installed-Chrome Playwright result above is focused automated evidence only. | Record each observed issue, add a failing regression, fix, and rerun. | Pending. |
| **Controller-owned: full PR6 regression gates** | Laravel, `/m`, native iOS, production build | PR6 regression fixtures | Plan-specified suites/builds | **Pending controller** | Not run by this task implementer to avoid colliding with controller-owned gates. | Run the exact Task 4 plan commands and record any fix loop. | Pending. |
| **Controller-owned: whole-branch review** | Laravel, `/m`, and native iOS diff | Same-user/state/action contract | Entire PR6 branch | **Pending controller** | Not yet completed by the controller/reviewer. | Confirm server ownership, equivalent actions, no client-authored financial truth, no unrelated changes, and explicit PR7 audit deferral. | Pending. |

## Observed scope boundary

The automated native journey visibly ran on the required simulator, and the
browser journey used the installed Google Chrome executable. Neither result is
presented as the controller-owned interactive simulator loop or Chrome
connector evidence. Full regression gates and final whole-branch review also
remain pending for the controller.
