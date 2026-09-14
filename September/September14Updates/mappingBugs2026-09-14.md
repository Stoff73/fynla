# Mapping bugs — 2026-09-14

Raised by the `app-map` overview run (`docs/app-map/00-overview.md`, commit `28194e804` on `dev`). Every entry is evidence from this run. Nothing here has been fixed; fixing is a separate task.

Status vocabulary: Broken, Dead, Duplicate, Dead end, Does not make sense (see `.claude/skills/app-map/SKILL.md` step 4).

## Decisions register (updated 2026-09-14 11:55)

| Id | Status | Area | Decision needed | Decided |
|---|---|---|---|---|
| MB-01 | Dead | 153 unreachable web files (extends W-0540, queued since 2026-09-04) | sweep or allowlist | pending |
| MB-02 | Dead | estate store action, no route | none (delete with MB-01) | — |
| MB-03 | Dead | savings service methods, no route | none (delete with MB-01) | — |
| MB-04 | Dead | `getSpouse()`, no route | none | — |
| MB-05 | Dead + Dead end | three Investment components, no routes | none (delete with MB-01) | — |
| MB-06 | Dead | seven lifecycle mailables with no campaign | planned or abandoned | pending |
| MB-07 | Dead | `RiskRecalculationObserver` + job never wired | delete, or was the queued path intended | pending |
| MB-08 | Dead (route side) | 25 orphan route rows; dossier at `docs/app-map/reports/mb-08-orphan-routes.md` | household routes: wire up or retire the sold capability; persona list: keep as probe or delete | pending (22 delete, 1 keep) |
| MB-09 | Dead | 56 Vue public page copies blocked by the router guard; PHP pages are canonical | confirm and delete the Vue copies | pending |
| MB-10 | Does not make sense | six mock-up routes live unguarded | keep, guard, or delete | pending |
| MB-11 | Does not make sense | 4 unreferenced and 12 one-off artisan commands | prune or record | pending |
| MB-12 | Dead | three test-only services | none | — |
| MB-13 | Dead | three unused middleware aliases | none | — |
| MB-14 | Broken | five alert types never delivered (preference keys not columns) | which switch governs each alert; whether database channel needs a device | pending |
| MB-15 | Dead end | in-app notifications written, never read | build an inbox, or move to email/push | pending |
| MB-16 | Dead | `Registered` listener never fires | none | — |
| MB-17 | Duplicate drift | `/m` lacks two lifecycle switches | none (add them) | — |
| MB-18 | Broken | `/m` login has no two-factor step | none (add it) | — |
| MB-19 | Dead end | `/m` login has no restore branch | none (add it) | — |
| MB-20 | Broken | web restore modal never shows the MFA field | none (fix) | — |
| MB-21 | Broken | wrong MFA or recovery code bounces the user with no message | 422 for wrong codes, or allowlist the endpoints | pending |
| MB-22 | Broken (layout) | privacy toggle under the open Fyn panel at 1440 px | none (fix) | — |

### MB-01 — 153 web SPA files are unreachable from the app entry points
Map: docs/app-map/00-overview.md § 3 and Appendix A
Status: Dead
Evidence: import-and-tag reachability from `resources/js/app.js`, `resources/js/router/index.js` and `resources/js/App.vue`, with HTML and JS comments stripped before matching. Full list in Appendix A of the overview. Largest groups: `components/Investment` (40), `components/Dashboard` (21), `components/Estate` (15), `components/UserProfile` (11), `components/Goals` (10), `components/Retirement` (9).
What is wrong: 153 of 855 non-test files under `resources/js` are never imported or used as a component tag by any file that the app can reach. They ship in the source tree, are maintained, and can be edited in the belief they are live. Eleven of them appear only inside commented-out template blocks (for example `resources/js/components/NetWorth/InvestmentList.vue:190-218`).
Suspected impact: wasted maintenance, misleading code reads, and audit findings against components no user sees. No user-facing effect.
Decision needed: yes — delete, or move to an archive folder, or reinstate any that were meant to be live. Each section map will confirm its own subset before deletion.

### MB-02 — Estate store action calls an endpoint that does not exist
Map: docs/app-map/00-overview.md § 3
Status: Dead (and would be a Dead end if reached)
Evidence: `resources/js/store/modules/estate.js:295` dispatches `estateService.analyzeEstate`; `resources/js/services/estateService.js:22-23` posts to `/estate/analyze`; `php artisan route:list` has no `api/estate/analyze` route (the estate group at `routes/api.php:907-1009` has no `analyze`). No component dispatches `estate/analyzeEstate` (grep of `resources/js` and `resources/mobile`).
What is wrong: a store action and service method exist for an API that was never registered, and nothing calls them.
Suspected impact: none today. If someone wires a button to it, it 404s.
Decision needed: no — candidate for deletion with MB-01.

### MB-03 — Savings service has three methods and one store action for endpoints that do not exist
Map: docs/app-map/00-overview.md § 3
Status: Dead
Evidence: `resources/js/services/savingsService.js:114-137` (`getGoals` → `/savings/goals`, `getExpenditureProfile` and `updateExpenditureProfile` → `/savings/expenditure-profile`); `resources/js/store/modules/savings.js:356-361` dispatches `updateExpenditureProfile`. Neither path is in the route table (savings group `routes/api.php:525-548`). No caller of any of the three outside the service and store.
What is wrong: as MB-02, in the savings module. Expenditure is actually written through `PUT /api/user/profile/expenditure` (`routes/api.php:316`).
Suspected impact: none today.
Decision needed: no — candidate for deletion with MB-01.

### MB-04 — `getSpouse()` in the user profile service targets a route that does not exist
Map: docs/app-map/00-overview.md § 3
Status: Dead
Evidence: `resources/js/services/userProfileService.js:195-197` gets `/user/spouse`; the only `spouse` routes under `api/user` are `/spouse/financial-commitments` (`routes/api.php:319`) and the letter-to-spouse set. No caller of `getSpouse()`.
What is wrong: dead method on a live service.
Suspected impact: none today.
Decision needed: no.

### MB-05 — Three dead Investment components call API paths that were never registered
Map: docs/app-map/00-overview.md § 3
Status: Dead (component) plus Dead end (route)
Evidence: `resources/js/components/Investment/GoalProjection.vue` calls `/investment/goal-progress/*`; `PerformanceAttribution.vue` and `BenchmarkComparison.vue` call `/investment/performance-attribution/*`. Neither prefix exists in the route table (investment group `routes/api.php:604-895`). All three components are in the MB-01 list.
What is wrong: frontend built against an API that was never shipped, then orphaned.
Suspected impact: none today.
Decision needed: no — delete with MB-01, or raise a feature ticket if the analysis was wanted.

### MB-06 — Seven lifecycle emails exist as code and templates but can never be sent
Map: docs/app-map/00-overview.md § 2.8
Status: Dead
Evidence: `config/lifecycle.php:12-15` registers only `ChurnedSubscriberCampaign` and `LapsedSubscriberCampaign`; `app/Services/Lifecycle/Campaigns/` contains only those two classes; `app/Services/Lifecycle/LifecycleEngine.php:139` sends whatever a campaign's `mailable()` returns. The mailables `DontMissOutMail`, `GetStartedMail`, `GreatJobMail`, `InsightsMail`, `WeHaventSeenYouMail`, `WelcomeMail`, `WellDoneMail` (all `app/Mail/Lifecycle/`) have no constructor call anywhere in `app/` and their Blade templates in `resources/views/emails/lifecycle/` are therefore never rendered.
What is wrong: an onboarding and re-engagement email series was written to the template layer and never given a campaign that sends it.
Suspected impact: users never receive the welcome, get-started, well-done or we-haven't-seen-you emails the templates promise.
Decision needed: yes — is the series planned (write the campaigns) or abandoned (delete the seven mailables and templates)?

### MB-07 — `RiskRecalculationObserver` and `RecalculateRiskProfileJob` are never registered or dispatched
Map: docs/app-map/00-overview.md § 2.9
Status: Dead
Evidence: `app/Observers/RiskRecalculationObserver.php` is referenced only from `app/Services/CLAUDE.md`; it is not in `app/Providers/EventServiceProvider.php` (`$observers`, lines 111-140) nor `app/Providers/AppServiceProvider.php` (which registers `DocumentArticleObserver`, `InsightArticleObserver`, `RecommendationTrackingObserver`, `SurvivingSpouseExpenditureObserver`, `UserOnboardingStepObserver`). `app/Jobs/RecalculateRiskProfileJob.php` has exactly one dispatch site, that observer. Live risk recalculation runs through the per-model observers (`UserRiskObserver`, `SavingsAccountRiskObserver`, and so on).
What is wrong: a queued risk recalculation path exists beside the synchronous observer path and is never wired. The services `CLAUDE.md` still documents it as current.
Suspected impact: none at runtime; the documentation is wrong.
Decision needed: yes — delete the observer and job, or was the queued path meant to replace the synchronous observers?

### MB-08 — Backend routes with no caller in any client
Map: docs/app-map/00-overview.md § 3 and Appendix C; full route-by-route dossier at `docs/app-map/reports/mb-08-orphan-routes.md`
Status: Dead (route side)
Evidence: the overview compared every API path called from reachable web files, all `/m` files, the public pages and every Swift file with `php artisan route:list`. The dossier then read each controller method, traced in git when the route was added and when its last caller was removed, searched every client again with fixed strings, and listed the tests and the live code each deletion must not touch. Two corrections to the overview's list came out of that: `POST api/net-worth/refresh` is live (dispatched from seven store modules through a base-path template the overview's regex missed) and is removed from the list; `GET api/advisor/reports` is an orphan after all (the only hit was a menu link), and is added. `DashboardAggregator::aggregateAlerts()` is live through the `/m` dashboard and must survive the `api/dashboard` route deletion.
What is wrong: 25 route rows across 21 groups serve no client. Highlights: the whole `api/dashboard` group (its last web caller was removed on 2026-05-23, `7392ec0e8`; the web dashboard now reads the mobile payload); the holistic `analyze`, `plan`, `cash-flow-analysis` and six `recommendations` routes, superseded by `composite-plan` and `api/recommendations/*`; the legacy GDPR erasure trio that never had a client; the `dashboard-widget-order` setter for a feature removed on 2026-01-13; the mobile `modules/{module}` summary retired with the `/m` scaffold on 2026-09-09; `payment/trial-status`, already a 404 tombstone.
Suspected impact: none for users; real maintenance cost, and tests that pass against code nobody can reach. Five test files use an orphan route as a probe for middleware and will need retargeting, not deleting (listed in the dossier's cross-cutting notes). Five documents describe deleted routes as current.
Decision needed: yes, on two of the 25 —
- `api/household/net-worth`, `death-scenario`, `optimisations`: no client calls them, but the `joint_household_view` capability they implement is sold on the pricing page. Wire a screen up, or delete them and retire the capability from the tier matrix.
- `GET api/preview/personas`: public and unauthenticated, no client calls it (the landing page uses fixed persona ids and the preview store bundles the JSON locally), but `workforce/core/constitution/01-mission.md:73` names it canonical.
The other 22 are recommended for deletion, with the dossier's "live code the deletions must not touch" list as the guard rail.

### MB-09 — Public marketing pages exist twice; the PHP set is canonical and the 56 Vue copies can never render
Map: docs/app-map/00-overview.md § 2.1 and Appendix E
Status: Dead (the Vue copies)
Evidence: `routes/web.php:96-470` serves the marketing, stage, feature, compare and learn pages from `public/pages/*.php`, declared before the SPA catch-all at `:600`. The PHP set was created by Azlan Raj (GitHub handle `Phailanx`, `workforce/core/registry/people.md:29`, `handover/August/18/handover-2026-08-18-session-1.md:40`) from 2026-05-18 (`49210cbce`, `448694399`) and has been edited since by Azlan and CSJ. The Vue router still defines 56 routes for the same paths with their own page components (`resources/js/router/index.js`, list in Appendix E of the overview). The router guard at `resources/js/router/index.js:1662-1680` forces a full document load for every one of those paths on any in-app navigation, and on a direct load the server route wins, so the Vue components never render on either path. Verified this run: clicking "Help" in the app footer while signed in performed a full load and the server rendered `public/pages/help.php` (page title "Help & Documentation — Using Fynla | Fynla"). Exception: `/savetax` is not in the guard's list, but no in-app link to it exists (grep of `resources/js`), so its Vue copy is also unreached.
What is wrong: 56 Vue page components (plus their sub-components) are maintained, linted and tested as if live, and the guard's own comment calls them "STALE". The last edits to the Vue copies range from 2026-03-31 to 2026-08-31, while the PHP set is the one that has been served since May.
Suspected impact: no user-facing effect today; wasted maintenance and a risk that someone "fixes" a Vue copy nobody sees. The reachability sweep in MB-01 counted these as live because the router imports them, so MB-01 understates the dead set by these 56 files and their private sub-components.
Decision needed: yes — confirm the PHP pages under `public/pages/` are canonical (they are what is served) and delete the 56 Vue routes and components, leaving `/insights*`, `/news*`, `/sitemap`, `/privacy`, `/terms`, `/login`, `/register` and the campaign pages that have no PHP twin as genuine SPA public pages.

### MB-10 — Six design mockup routes are live with no environment guard
Map: docs/app-map/00-overview.md § 2.1
Status: Does not make sense
Evidence: `routes/web.php:660-702` (`/savetax/plan/v3`, `/savetax/plan/v4`, `/m-mockup/dashboard`, `/savetax/v2`, `/savetax/plan/v2`) and `routes/web.php:744-749` (`/mockup/dashboard`) render standalone HTML from `public/pages/*mockup*.php` and `savetax-*-v*.php`. None is wrapped in an environment check; the comment at `:704-706` says they stay viewable for design review.
What is wrong: mockups are reachable on production by anyone who knows the URL.
Suspected impact: stale designs indexed or shared as if real; no data exposure seen.
Decision needed: yes — keep, guard to local and staging, or delete.

### MB-11 — Artisan commands with no reference anywhere, and one-off migrations still registered
Map: docs/app-map/00-overview.md § 2.9 and Appendix B
Status: Does not make sense
Evidence: signature search across `app`, `deploy`, `scripts`, `docs`, `.github`, `database` and `CLAUDE.md`, excluding each command's own file. Zero references: `family:reconcile-spouse-links`, `estate:backfill-bequests`, `eval:show`, `eval:purge`. One-off migration or backfill commands still registered: `migrate:estate-to-networth`, `migrate:verify`, `data:encrypt`, `estate:backfill-mirror-parties`, `mortgages:backfill-derived-columns`, `pensions:backfill-derived`, `properties:backfill-derived-columns`, `properties:backfill-outstanding-mortgage`, `savings:backfill-derived`, `ai:usage:backfill`, `fyn:episodic:backfill-blobs`, `gamification:backfill`.
What is wrong: 31 of 67 commands are not scheduled; most are legitimate operator tools, but the four with no reference and the twelve one-off migrations have no documented reason to stay.
Suspected impact: an operator can run a data migration twice by accident.
Decision needed: yes — prune, or record in a runbook which are kept and why.

### MB-12 — Three services are referenced only from tests
Map: docs/app-map/00-overview.md § 3
Status: Dead
Evidence: reverse sweep of `app/Services` against `app`, `routes`, `config`, `database`, `bootstrap`, `resources/views`. `app/Services/Marketing/PensionEstimateService.php`, `app/Services/Pipeline/CaptionBuilder.php`, `app/Services/Stores/CurrencyDisplayService.php` have no production caller; each has one or two test files.
What is wrong: green tests protect code nothing runs.
Suspected impact: none.
Decision needed: no — delete with their tests, or wire them if they were meant to be used.

### MB-13 — Three middleware aliases are registered but no route uses them
Map: docs/app-map/00-overview.md § 2.2
Status: Dead
Evidence: `app/Http/Kernel.php` aliases `admin` (`IsAdmin`), `role` (`HasRole`), `mfa.verified` (`EnsureMFAVerified`); the effective route table (`php artisan route:list`) lists neither class on any route. Admin routes use `permission:admin.access` instead.
What is wrong: three middleware classes with no route.
Suspected impact: none.
Decision needed: no.

### MB-14 — Five daily alert types are computed and then never delivered
Map: docs/app-map/17-emails-notifications.md § 2.6
Status: Broken
Evidence: `app/Services/Mobile/PushNotificationService.php:37-47` returns false when the user has no `device_tokens` row, and otherwise `(bool) ($prefs->{$preferenceKey} ?? false)`. The `via()` methods of `SavingsMaturityAlertNotification`, `SavingsRateExpiryNotification`, `ISAAllowanceWarningNotification`, `EmergencyFundAlertNotification` and `ProtectionAlertNotification` (each at lines 17-26 or 19-28) pass the keys `savings_maturity_alerts`, `savings_rate_alerts`, `isa_allowance_warnings`, `protection_alerts`. `SHOW COLUMNS FROM notification_preferences` this run lists none of those columns. No test references `SendSavingsAlerts`, `SendProtectionAlerts` or those notification classes (grep of `tests/`).
What is wrong: `savings:send-alerts` and `protection:send-alerts` run every morning, query the data, and every notification resolves to no channel. Even with the keys fixed, the device-token check would still drop web-only users from a channel that does not need a device.
Suspected impact: nobody has ever received a savings maturity, rate expiry, ISA allowance, emergency fund or protection alert from these commands.
Decision needed: yes — which preference switch should govern each alert (the page offers `policy_renewals`, `market_updates`, `goal_milestones` and others), and should the database channel require a phone at all.

### MB-15 — In-app notifications are written to a table nothing reads
Map: docs/app-map/17-emails-notifications.md § 2.6
Status: Dead end
Evidence: `GiftExemptionNotification`, `TrustAnniversaryNotification`, `CompanyFilingDueNotification` and the anonymous class at `SendEstateAlerts.php:284-300` return `['database']` from `via()`. The route table has no endpoint over the `notifications` table (only `notifications/preferences`, `routes/api.php:406` and `routes/api_v1.php:172-175`), and no file under `resources/js`, `resources/mobile` or `ios-native/Fynla` reads `unreadNotifications`, `notifications()` or the table (grep this run). Locally the table holds 0 rows.
What is wrong: the estate and business alert commands succeed and their output is invisible.
Suspected impact: gift-exemption, trust-anniversary, annual IHT review and Companies House deadline reminders never reach a person.
Decision needed: yes — build an in-app inbox (web, `/m`, iOS), or switch these to email or push and drop the database channel.

### MB-16 — A registration email listener that can never fire
Map: docs/app-map/17-emails-notifications.md § 2.9
Status: Dead
Evidence: `app/Providers/EventServiceProvider.php:69-71` maps `Illuminate\Auth\Events\Registered` to `SendEmailVerificationNotification`. No code dispatches `Registered` (grep of `app/` and `routes/` this run) and `App\Models\User` does not implement `MustVerifyEmail` (`app/Models/User.php:7`, commented out). Registration verification actually runs through `PendingRegistration` and `VerificationCode` mail (`AuthController.php:137`).
What is wrong: framework default left in place beside the real flow.
Suspected impact: none.
Decision needed: no.

### MB-17 — `/m` offers nine notification switches; web and iOS offer eleven
Map: docs/app-map/17-emails-notifications.md § 2.7
Status: Duplicate drift (Rule 19)
Evidence: `resources/js/components/UserProfile/NotificationPreferences.vue:70-90` lists eleven keys including `lifecycle_churned_subscriber` and `lifecycle_lapsed_subscriber`; `resources/mobile/views/NotificationPreferences.vue` lists nine (grep this run); `ios-native/Fynla/Core/Push/PushModels.swift` lists eleven. Both preference endpoints accept all eleven (`UpdateNotificationPreferencesRequest.php:19-29`).
What is wrong: a phone user cannot opt out of the two lifecycle emails without the desktop site.
Suspected impact: a user who cancels on their phone cannot decline the cancellation-feedback email from the same device.
Decision needed: no — add the two switches to `/m`.

### MB-18 — A two-factor user cannot sign in on `/m`
Map: docs/app-map/01-auth-registration-sessions.md § 2.2
Status: Broken
Evidence: `resources/mobile/views/Login.vue:116-129` handles a login response only when it carries a token or `requires_verification`; the `requires_mfa` response (`AuthController.php:323-336`) falls to the final branch and shows the server message as an error. Driven this run with user 85 (MFA enabled): "MFA verification required." rendered under the password field, no code step, screenshot `docs/app-map/screenshots/auth/m-login-mfa-user.png`. iOS has the branch (`AuthModels.swift:42-73`, `MultiFactorView.swift`); web has `MFAVerifyModal.vue`.
What is wrong: the mobile web login was written for the emailed-code path only.
Suspected impact: every customer who enables two-factor on the website is locked out of `/m`, which is where phones are routed.
Decision needed: no — add the MFA and recovery-code step to the `/m` login (Rule 19).

### MB-19 — A deleted, restorable account has no way back on `/m`
Map: docs/app-map/01-auth-registration-sessions.md § 2.2
Status: Dead end (by code read; not driven because the test account was restored through the API first)
Evidence: `AuthController.php:234-257` answers a correct password on a restorable account with `account_deleted_restorable` and a `restoration_token`; `resources/mobile/views/Login.vue:116-129` has no branch for it and shows "We could not sign you in. Please try again." (`:126`). Web mounts `RestoreAccountModal.vue`; iOS has `RestoreAccountFlow.swift`.
What is wrong: as MB-18, for the restoration branch.
Suspected impact: a phone user who deleted their account is told sign-in failed, with no hint that restoration exists.
Decision needed: no.

### MB-20 — The web restore modal never asks a two-factor user for their code
Map: docs/app-map/01-auth-registration-sessions.md § 2.7
Status: Broken
Evidence: `resources/js/components/Account/RestoreAccountModal.vue:207-235`: on the login path the modal already holds a `restorationToken`, skips the `restoreCheck` branch that sets `mfaRequired` (`:213-224`), posts `restore` without a code, and the catch shows the server message. `RestoreAccountController.php:38-46` answers 422 `requires_mfa: true`. Driven this run: the modal showed "Authenticator or recovery code required." with no input (screenshot `web-login-restore-account-modal.png`, console 422 at 11:20:27). The same request with `mfa_code` made directly restored the account (HTTP 200).
What is wrong: the `requires_mfa` answer from `restore` is not handled, only the one from `restore/check`.
Suspected impact: any two-factor user who deletes their account cannot restore it from the sign-in page.
Decision needed: no — handle `requires_mfa` from `restore` the same way as from `restore/check`.

### MB-21 — A wrong authenticator or recovery code closes the sign-in modal with no message
Map: docs/app-map/01-auth-registration-sessions.md § 2.3
Status: Broken
Evidence: `MFAController.php:48-49,264` answer a wrong code with 401. `resources/js/services/api.js:99-125` treats every 401 as an expired token unless the URL is `/auth/login`, `/auth/register`, `/auth/verify-code`, `/auth/user` or `/preview/exit`; `/auth/mfa/verify`, `/auth/mfa/recovery` and `/auth/password-reset/*` are not on the list, so `handleAuthExpiry()` runs. Driven this run: code `000000` on the MFA modal → `POST api/auth/mfa/verify` 401 (network log) → modal gone, form cleared, page back at `/login` with no message. Earlier in the run one recovery-code attempt did the same.
What is wrong: the client cannot tell "wrong code" from "token expired" because the server uses 401 for both and the client allowlist is incomplete.
Suspected impact: a mistyped code looks like a broken sign-in; there is no retry prompt.
Decision needed: no — either return 422 for a wrong code or add the MFA and reset endpoints to the client allowlist.

### MB-22 — The marketing consent toggle is covered by the Fyn panel on the privacy page
Map: docs/app-map/01-auth-registration-sessions.md § 2.7
Status: Broken (layout)
Evidence: at 1440 × 1000 with the Fyn side panel open, Playwright could not click the toggle in `resources/js/views/Settings/PrivacySettings.vue` because the fixed `<aside>` (356 px wide, `z-40`) intercepted the pointer; collapsing the panel made it clickable and the consent row was written (`user_consents.marketing`, 12:18:21).
What is wrong: the settings content does not leave room for the open panel at this width, so the right-hand toggles sit underneath it.
Suspected impact: a user with the panel open cannot change the consent until they close the panel; other right-aligned controls on settings pages may share the issue (not checked).
Decision needed: no.
