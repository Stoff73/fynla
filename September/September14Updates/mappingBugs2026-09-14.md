# Mapping bugs — 2026-09-14

Raised by the `app-map` runs of 2026-09-14: the overview, sections 17 and 01 (commit `28194e804` on `dev`, MB-01 to MB-22), section 02a onboarding (commit `e4ddc4e3f`, MB-23 to MB-36, `docs/app-map/02-onboarding.md`) and section 02b campaigns (commit `6b365dddc`, MB-37 to MB-55, `docs/app-map/02b-campaigns.md`). Every entry is evidence from its run. Nothing here has been fixed; fixing is a separate task. Append, never renumber.

Status vocabulary: Broken, Dead, Duplicate, Dead end, Does not make sense (see `.claude/skills/app-map/SKILL.md` step 4).

## Decisions register (updated 2026-09-14 16:10)

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
| MB-18 | Broken | `/m` login has no two-factor step | none (add it) | fixed 2026-09-14, branch `mb-18-20-m-login-mfa-restore` |
| MB-19 | Dead end | `/m` login has no restore branch | none (add it) | fixed 2026-09-14, branch `mb-18-20-m-login-mfa-restore` |
| MB-20 | Broken | web restore modal never shows the MFA field | none (fix) | fixed 2026-09-14, branch `mb-18-20-m-login-mfa-restore` |
| MB-21 | Broken | wrong MFA or recovery code bounces the user with no message | 422 for wrong codes, or allowlist the endpoints | pending |
| MB-22 | Broken (layout) | privacy toggle under the open Fyn panel at 1440 px | none (fix) | — |
| MB-23 | Broken | paused onboarding user's next message hits the director with no step; web shows nothing | route by step not conversation source, or new conversation on pause | pending |
| MB-24 | Broken | `/m` "Something else" at the front door answers "This step cannot be skipped." | none (fix the bubble id routing) | fixed 2026-09-14, branch `mb-24-front-door-something-else` |
| MB-25 | Dead end | paused journey/focus onboarding has no way back in on any surface | should paused users be offered Continue, and where | pending |
| MB-26 | Dead end | web never offers onboarding to a returning user who registered but never started; `/m` does | none (align web with `/m`) | fixed 2026-09-14, branch `mb-26-web-onboarding-needs-start` |
| MB-27 | Broken | web expenditure verify screen shows £0 from a stale store while the database holds the figure | none (refetch on Fyn navigation) | fixed 2026-09-14 (with MB-47) |
| MB-28 | Does not make sense | web profile-review pause strands the user on Settings for the rest of the walk | none (fix the return route check) | fixed 2026-09-14, branch `mb-28-profile-review-return-leg` |
| MB-29 | Dead (latent Broken) | wizard step endpoint's property branch references an unimported class; five step branches have no caller | delete the dead branches, or keep for a future API | pending |
| MB-30 | Dead | seven onboarding and journey routes have no caller; `/planning/journeys` can never show a journey | delete, or wire the journeys feature | pending |
| MB-31 | Broken + Dead | wizard journey mode renders eight unregistered components as blank steps; reachable only from dead code | delete journey mode with MB-01, or restore the components | pending |
| MB-32 | Does not make sense | `users.life_stage` is written with three vocabularies and overrides every wizard route; `onboarding_fyn_path` gets a fourth value | one column per meaning, or one vocabulary | pending |
| MB-33 | Does not make sense | `POST /api/onboarding/step` writes user columns with no field validation | add a Form Request | — |
| MB-34 | Does not make sense | 46 hardcoded tax figures in wizard learning copy (Rule 2) | source from tax config, or accept as editorial copy | pending |
| MB-35 | Does not make sense | wizard copy and options: American "Dependents", marital status omits civil partnership | none (fix) | — |
| MB-36 | Does not make sense (adjacent: Savings) | `/m` savings screen says expenditure is missing while computing a target from it | none (Savings module fix) | — |
| MB-37 | Broken | web savings verify page shows no accounts | which page the savings verify should open | pending |
| MB-38 | Does not make sense + Duplicate | Pension Check hardcodes income bands in three places; no income context or cross-check | extend to Pension Check, or accept the drift | pending |
| MB-39 | Does not make sense | four non-campaign pages framed and routed as campaigns | retire them, or build the campaigns | pending |
| MB-40 | Does not make sense | Save Tax headline counts saving lines the page hides | show every line, or exclude them from the total | pending |
| MB-41 | Dead (route side) | public tax-allowances endpoint serves mock-ups only; mislabelled threshold | delete, or wire the live page to it | pending |
| MB-42 | Dead (route side) | tax-strategy calculate endpoint has no caller | delete, or restore the sliders | pending |
| MB-43 | Dead | charitable-giving state remnants | delete the remnants, or restore a giving section | pending |
| MB-44 | Dead end | declining the Save Tax consent gate has no way back; Actions tile can never show | allow Save Tax re-entry, or drop the tile and comment | pending |
| MB-45 | Dead end + Duplicate | Tax Strategy next-step routes missing on web; key drift on `/m` and iOS; two list sources | which list is canonical | pending |
| MB-46 | Does not make sense (Rule 2) | "£40,000 of unused tax allowances" hardcoded in the corpus prompt | none (compute from tax config) | — |
| MB-47 | Broken | web pages under the chat not refetched after a Fyn write (extends MB-27) | none (refetch) | fixed 2026-09-14, branch `mb-47-fyn-navigation-refresh` |
| MB-48 | Broken | spouse verify page shows nothing for a non-working spouse | none (return household figures without income) | fixed 2026-09-14, branch `mb-48-spouse-verify-household` |
| MB-49 | Does not make sense | natural "X, not Y" correction produced no write; 6.5-minute edit turn | accept as model behaviour, or add a deterministic correction parser | pending |
| MB-50 | Does not make sense | `/m` expenditure verify shows a derived total, never the entered figure | none (show the entered figure) | fixed 2026-09-14, branch `mb-50-m-expenditure-entered-figure` |
| MB-51 | Does not make sense (copy) | "details page" labels, "workplace pension we covered" for the self-employed, neutral DOB wording for Pension Check, "bank and savings" for savings-only | none (fix the strings and key lookup) | — |
| MB-52 | Duplicate drift | `/m` collapses multi-paragraph advice into one paragraph | none (render breaks on `/m`) | — |
| MB-53 | Does not make sense | Pension Check re-entry re-asks the pensions section | add data-presence skips, or accept the repeat | pending |
| MB-54 | Does not make sense | Save Tax workplace-pension capture drops the pot value and provider | add the pot loop to Save Tax, or extend the prompt | pending; cause corrected 2026-09-14 — the model refused and the deterministic backstop wrote the degraded row; the backstop now keeps the provider and reads a stated pot (branch `mb-56-58-pension-capture`) |
| MB-55 | Does not make sense | Save Tax sign-in link drops the campaign; Pension Check's keeps it | none (align with Pension Check, subject to MB-44) | — |
| MB-56 | Dead end | Pension Check pot loop: a £0 pot re-asks forever; "not sure, skip it" records the skip then stalls | none (treat a stated £0 as answered; advance on the recorded skip) | fixed 2026-09-14, branch `mb-56-58-pension-capture` |
| MB-57 | Broken (data) | a bare figure answered to the pot question was written to `users.annual_employment_income` (£62,000 became £500) | none (the pot turn must not offer, or must scope, `update_profile`) | fixed 2026-09-14, branch `mb-56-58-pension-capture` |
| MB-58 | Does not make sense | one workplace-pension sentence created two `dc_pensions` rows, the second with provider `Scottish` | none (one record per sentence; keep the provider whole) | fixed 2026-09-14, branch `mb-56-58-pension-capture`; cause corrected — the second row was the deterministic backstop, not a second model call |

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
Status: Fixed 2026-09-14, branch `mb-18-20-m-login-mfa-restore`. `resources/mobile/views/Login.vue` gains an authenticator step (the same six boxes as the emailed code, posting `POST /api/auth/mfa/verify`) with a recovery-code alternative (`POST /api/auth/mfa/recovery`). The server consumes the challenge token on the first attempt whatever the outcome (`MFAController::validateChallengeToken`), so a wrong code returns the user to sign-in with "Invalid verification code. Please sign in again to get a new code." rather than a dead retry (MB-21's 401-vs-422 question is unchanged). Vitest `resources/mobile/views/__tests__/Login.spec.js` (+5, red before). Live `/m` (rebuilt bundle), user 85: wrong code → back to sign-in with the message; correct authenticator code → dashboard as Map (`screenshots/mb-fixes/mb18-m-login-authenticator-step.png`); recovery code (regenerated through `MFAService::regenerateRecoveryCodes`) → dashboard (`mb18-m-login-recovery-code-step.png`).
Evidence: `resources/mobile/views/Login.vue:116-129` handles a login response only when it carries a token or `requires_verification`; the `requires_mfa` response (`AuthController.php:323-336`) falls to the final branch and shows the server message as an error. Driven this run with user 85 (MFA enabled): "MFA verification required." rendered under the password field, no code step, screenshot `docs/app-map/screenshots/auth/m-login-mfa-user.png`. iOS has the branch (`AuthModels.swift:42-73`, `MultiFactorView.swift`); web has `MFAVerifyModal.vue`.
What is wrong: the mobile web login was written for the emailed-code path only.
Suspected impact: every customer who enables two-factor on the website is locked out of `/m`, which is where phones are routed.
Decision needed: no — add the MFA and recovery-code step to the `/m` login (Rule 19).

### MB-19 — A deleted, restorable account has no way back on `/m`
Map: docs/app-map/01-auth-registration-sessions.md § 2.2
Status: Fixed 2026-09-14, branch `mb-18-20-m-login-mfa-restore`. The `/m` login gains a restore step on `account_deleted_restorable` ("Welcome back, {name} — this account was deleted on {date}"), posts `POST /api/auth/restore` with the restoration token, asks for the authenticator or recovery code when that call answers 422 `requires_mfa` (the MB-20 shape), and on success stores the token and opens the dashboard, carrying the campaign from `redirect_to` as `?from=`. Vitest `Login.spec.js` (in the +5). Live `/m`, user 85 deleted through `AccountDeletionService::deleteAccount()`: restore step shown (`mb19-m-login-restore-step.png`), Restore → code field (`mb19-m-login-restore-mfa-field.png`) → authenticator code → dashboard; `users.deleted_at` back to null.
Evidence: `AuthController.php:234-257` answers a correct password on a restorable account with `account_deleted_restorable` and a `restoration_token`; `resources/mobile/views/Login.vue:116-129` has no branch for it and shows "We could not sign you in. Please try again." (`:126`). Web mounts `RestoreAccountModal.vue`; iOS has `RestoreAccountFlow.swift`.
What is wrong: as MB-18, for the restoration branch.
Suspected impact: a phone user who deleted their account is told sign-in failed, with no hint that restoration exists.
Decision needed: no.

### MB-20 — The web restore modal never asks a two-factor user for their code
Map: docs/app-map/01-auth-registration-sessions.md § 2.7
Status: Fixed 2026-09-14, branch `mb-18-20-m-login-mfa-restore`. `RestoreAccountModal.vue` now treats a 422 `requires_mfa` from `restore` the way it treats the one from `restore/check`: shows the code field, keeps the token, no error. Vitest `resources/js/components/__tests__/Account/RestoreAccountModal.spec.js` (+2, red before). Live web, user 85 deleted again: modal → Restore → code field with no error (`mb20-web-restore-modal-mfa-field.png`) → authenticator code → `/dashboard?openPricing=1`; `users.deleted_at` back to null.
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

### MB-23 — A paused onboarding user's next message reaches the director with no step, and gets nothing
Map: docs/app-map/02-onboarding.md § 2.6, § 3
Status: Broken
Evidence: `app/Services/AI/ContextualConversation/ConversationModeResolver.php:23-25` returns true for any conversation whose `metadata.source` is `fyn_onboarding` before it reads the step; `app/Services/Onboarding/OnboardingChatDirector.php:191-198` then yields the content "Onboarding state lost. Please reload and try again." with no `done`. Live on web this run: user 86, conversation 187, rows 492-494 (user "Something else", assistant "No problem. What would you like help with?", user "What is an ISA?"), no assistant row after, nothing rendered — `docs/app-map/screenshots/02-onboarding/web-fyn-something-else-then-question-no-reply.png`. Feature probe: the resolver returned true for a step-null, not-completed user on a `fyn_onboarding` conversation. Contract test skipped at `tests/Feature/Onboarding/PausedUserMessageRoutesToAdviceTest.php`.
What is wrong: the pause paths (`handleSomethingElseAction` at `OnboardingChatDirector.php:779-805`, the front-door free-text exit at `:457-476`, `emitFreeChatTurn` at `:6536-6558`) null the step and promise the next message goes to advice Fyn. The dispatch resolver keys on the conversation's source first, so every later message in that conversation goes back to the director, which has no state and errors. The web store drops an error `content` that arrives without `done`, so the user sees no reply at all. The tests that cover the exits (`PathChoiceHasAWayOutTest`, `CampaignReentryExitTest`) assert the step is nulled and stop; none sends the following message.
Suspected impact: every user who taps "Something else" or types free text at the front door, on web and `/m` (the `/m` symptom is the same backend; I COULD NOT VERIFY the `/m` rendering because MB-24 blocks the tap there).
Decision needed: yes — route by the user's step (the canonical three-part predicate) rather than the conversation source, or start a new conversation on pause.

### MB-24 — On `/m`, "Something else" at the front door answers "This step cannot be skipped."
Map: docs/app-map/02-onboarding.md § 2.6
Status: Fixed 2026-09-14, branch `mb-24-front-door-something-else`. `/m` only: the synthesised spouse skip-link bubble is now flagged `action: true` and `chooseBubble` routes on that flag instead of `bubble.id === 'skip'`, so the front door's `skip` bubble sends its label as web does (`resources/mobile/mixins/onboardingChat.js`). Vitest: two new cases in `onboardingChat.spec.js` (first red before the fix); mobile suite 199 passed. Live, `/m`, user 88 (`map-onb-m2-2026-09-14@example.com`, at `path_choice`): Continue → front door → "Something else" → "No problem. What would you like help with?"; no "cannot be skipped"; `users.onboarding_fyn_step` nulled (`screenshots/mb-fixes/mb24-m-something-else-front-door.png`). Native builds its skip reply the same way (`FynModels.swift:137-141`) but routes on `isAction`, so it is not affected by code read; not driven (iOS deferred). What follows the pause is MB-23/MB-25 (`onboarding_fyn_context` was null after the tap, so `paused_at_step` is not kept on this path either).
Evidence: `resources/mobile/mixins/onboardingChat.js:575` routes any bubble with id `skip` to the action endpoint; the path_choice bubble for "Something else" has id `skip` (`fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`, `path_choice.bubbles`); `OnboardingChatDirector::handleSkipAction` (`:822-830`) allows only `base_spouse` and yields "This step cannot be skipped." Live this run: user 88 on `/m/app/dashboard` — `docs/app-map/screenshots/02-onboarding/m-fyn-something-else-then-question.png`; no user row was persisted for the tap (the action endpoint does not persist).
What is wrong: the spouse step's skip link and the front-door "Something else" bubble share the id `skip`; `/m` treats both as the skip action. Web sends the label as a message and works (`AiChatPanel.vue:1228-1243`).
Suspected impact: `/m` users cannot leave the front door with the bubble (typing free text still works — verified: a typed question was answered inline and the front door re-asked).
Decision needed: no — give the path_choice bubble a distinct id, or make the `/m` rule check `metadata.skip_link` rather than the id.

### MB-25 — Paused journey/focus onboarding has no way back in
Map: docs/app-map/02-onboarding.md § 2.6
Status: Dead end
Evidence: `AiChatController::startOnboarding` resumes a parked step only when `onboarding_fyn_path === 'campaign'` (`app/Http/Controllers/Api/AiChatController.php:648-658`); for other paths a bare `/start` with step null creates a fresh conversation at `path_choice` (`:809-811`). Web opens the chat in onboarding only when the step is non-null (`resources/js/components/Shared/AiChatPanel.vue:1049-1053`); the dashboard fires `/start` only from the registration query (`resources/js/views/Dashboard.vue:1377`). `/m` excludes paused users from auto-start (`resources/mobile/mixins/onboardingChat.js:61-65`). `handleSomethingElseAction` stores `paused_at_step` "so the next /start resumes" (`OnboardingChatDirector.php:772-777`).
What is wrong: the pause is designed as "without losing it", but only the campaign path can be resumed, and neither web nor `/m` ever calls `/start` again for a paused user. The parked step is written and never read.
Suspected impact: any non-campaign user who pauses is stuck on advice Fyn; their captured facts remain, but the guided walk is gone unless they know to visit `/dashboard?openFyn=journey`, which restarts at the front door.
Decision needed: yes — offer "Continue setting up" to paused users (where, and on which surfaces), or drop the parked-step promise.

### MB-26 — Web never offers onboarding to a returning user who registered but did not start; `/m` does
Map: docs/app-map/02-onboarding.md § 2.1, § 1 Surfaces
Status: Fixed 2026-09-14, branch `mb-26-web-onboarding-needs-start`. One decision, made once on the server (Rule 20): `UserResource` now serves `onboarding_fyn_needs_start` (incomplete, no step, not parked). `/m`'s `onboardingNeedsStart()` reads that flag instead of deriving its own answer; the web dashboard starts onboarding on it exactly as it does for `openFyn=journey`, and the chat panel's open path treats it like a mid-walk resume. Tests: `AuthenticatedUserTest` (+2, red before), `onboardingChatEvents.test.js` (updated to the flag), `AiChatPanel.onboardingStart.spec.js` (+2). Live web, user 89 (completed false, step null): signing in opened Fyn with the front door ("Follow a journey / Pick a focus / Something else") instead of the advice greeting; `onboarding_fyn_step` became `path_choice` (`screenshots/mb-fixes/mb26-web-not-started-user-gets-front-door.png`). `GET /api/auth/user` for the same user carried `onboarding_fyn_needs_start: true`. Note: a user parked by "Something else" at the front door via the message path has `onboarding_fyn_context` null (MB-24's live run), so the flag treats them as not started and they get the front door again; keeping `paused_at_step` on that path is MB-23/MB-25.
Evidence: web `AiChatPanel.vue:1046-1061` starts onboarding on open only when `onboarding_fyn_step` is set; user 86 (completed false, step null) logged in on web, opened Fyn and got the advice greeting "Hi, I'm Fyn — Ask me anything about your finances" — `docs/app-map/screenshots/02-onboarding/web-returning-not-started-user-gets-advice-chat.png` (screenshot retaken with user 89, same state: the web called `GET /api/ai-chat/resumption`, `GET /api/ai-chat/conversations` and `POST /api/ai-chat/conversations` (201), never `/onboarding/start`; user 89 still `onboarding_fyn_step = null` afterwards). `/m` auto-starts for the same state (`onboardingChat.js:61-65`, `resources/mobile/views/Dashboard.vue:986-988`) — verified with user 87, `m-dashboard-onboarding-autostart.png`.
What is wrong: the two surfaces disagree about a not-started user. On web the only trigger is the registration redirect query; a user who closed the tab after registering, or who registered on `/m` and later signs in on web, is never onboarded on web.
Suspected impact: web users who leave before the first Fyn turn.
Decision needed: no — mirror `/m`: start when completed is false and the step is null and not paused.

### MB-27 — The web expenditure verify screen shows £0 while the database holds the figure Fyn just saved
Map: docs/app-map/02-onboarding.md § 2.5
Status: Fixed 2026-09-14 with MB-47 (see that entry for the fix and the live evidence)
Evidence: after "About £2,500 a month", Fyn said "Recorded monthly spending of £2,500." and navigated to `/valuable-info?section=expenditure`, which showed "Monthly Expenditure: £0 / Annual Expenditure: £0 / Total Monthly Expenditure £0" — `docs/app-map/screenshots/02-onboarding/web-fyn-expenditure-verify-navigate.png`; the database at that moment had `users.monthly_expenditure = 2500.00` and `expenditure_profiles.total_monthly_expenditure = 2500.00` (user 86, checked 13:38). A full reload showed £2,500 and £30,000. The store's `navigation` handler only sets a pending route (`resources/js/store/modules/aiChat.js:579-599`); the expenditure form reads `props.initialData.monthly_expenditure` from the already-loaded profile (`resources/js/components/UserProfile/ExpenditureForm.vue:2248`). The income and protection screens refetched and showed the right figures.
What is wrong: the verify step asks the user to confirm a screen that shows stale data. On `/m` the same screen showed £1,800 correctly (`m-expenditure-verify-pills.png`).
Suspected impact: web journey and campaign users on the expenditure verify; a user may answer "No, change something" against a wrong £0.
Decision needed: no — refetch the profile when a Fyn navigation lands on a profile-backed screen.

### MB-28 — The web profile-review pause leaves the user on Settings for the rest of the walk
Map: docs/app-map/02-onboarding.md § 2.4
Status: Fixed 2026-09-14, branch `mb-28-profile-review-return-leg`. Two causes, one fix: the return leg compared the path against `/profile`, which the router redirects to `/settings/...`, so it never matched; and the pre-pause route was kept in `AppLayout` component data, which is destroyed by the pause's own route change because every routed view wraps its own layout, so a fresh instance handled the return with nothing stored. The route now lives in the `aiChat` store (`preProfileRoute`, `SET_PRE_PROFILE_ROUTE`, cleared on reset) and the return leg keys on it being set, not on the path. Vitest `resources/js/layouts/__tests__/AppLayout.pauseRouting.spec.js` (+3) models the remount with a fresh context per event; the first draft that kept the route in component data passed a single-context spec and failed live, which is why the spec is shaped that way. Live web, new user `mb28-web-2026-09-14@example.com` on the Protecting What Matters journey: dependants "No" pushed `/settings/personal`, "Looks correct" returned to `/dashboard` and the walk continued there (`screenshots/mb-fixes/mb28-web-pause-returns-to-dashboard.png`).
Evidence: entering `profile_review_family` pushes `/profile` (`resources/js/layouts/AppLayout.vue:330-334`); `/profile` redirects to `/settings/personal` (`resources/js/router/index.js:680-690`); on "Looks correct" the return push runs only if `this.$route.path === '/profile'` (`AppLayout.vue:341`), which is never true. Live this run the URL stayed `/settings/personal` through employment, income and expenditure until the verify navigation moved it — `web-fyn-profile-review-pause.png` and the following snapshots.
What is wrong: the return leg of the pause never fires; the user finishes several onboarding steps on the Settings page.
Suspected impact: navigational; every web onboarding that reaches the family review.
Decision needed: no — compare against the redirect target or the stored pre-pause route.

### MB-29 — The wizard step endpoint's property branch references a class that does not exist; five step branches have no caller
Map: docs/app-map/02-onboarding.md § 2.8
Status: Dead (latent Broken)
Evidence: `app/Services/Onboarding/OnboardingService.php:565` calls `app(PropertyNormaliser::class)` with no `use` import (imports at `:7-31`), so it resolves to `App\Services\Onboarding\PropertyNormaliser`; `class_exists` is false for that name and true for `App\Services\Stores\Normalisers\PropertyNormaliser` (checked in tinker this run). No client posts `step_name = assets` with `properties`: `AssetsStep.vue` saves through `propertyService`, `savingsService`, `investmentService`, `retirementService` (`resources/js/components/Onboarding/steps/AssetsStep.vue:1093-1129`); likewise `liabilities` (`LiabilitiesStep.vue` uses `estateService`), `protection_policies` (`protectionService`), `family_info` (`familyMembersService`), and `quick_assets` (quick mode is unreachable, MB-30). The `saveStepData` callers are `personal_info`, `income`, `expenditure`, `domicile_info`, `will_info`, `trust_info`, `goals` only.
What is wrong: five of the ten `processStepData` branches (`OnboardingService.php:152-195`) are dead, and the first request to reach the property branch would throw a container resolution error.
Suspected impact: none today; a maintenance trap.
Decision needed: yes — delete the dead branches (with MB-01), or keep the endpoint as a general API and fix the import.

### MB-30 — Seven onboarding and journey routes have no caller, and the Journeys page can never show a journey
Map: docs/app-map/02-onboarding.md § 2.8, § 2.9
Status: Dead
Evidence: no dispatch of `onboarding/setFocusArea`, `onboarding/restartOnboarding` or `onboarding/completeQuickOnboarding` outside the quick-mode branch (grep of `resources/js` excluding the store), and quick mode is unreachable because `FocusAreaSelection.vue` emits only `stage-selected` (`:481`), never `focus-selected` or `selected`, so `handleFocusAreaSelected` (`OnboardingWizard.vue:1326-1334`) never runs. No dispatch of `journeys/fetchSelections`, `journeys/saveSelections`, `journeys/fetchDashboardPrompts`, `journeys/dismissPrompt`, and no caller of `journeyService.getPreview` (the only consumer, `JourneyPreview.vue`, is in MB-01). Routes: `POST /api/onboarding/focus-area`, `POST /api/onboarding/restart`, `POST /api/onboarding/complete-quick`, `GET|POST /api/journeys/selections`, `GET /api/journeys/preview`, `GET /api/journeys/dashboard-prompts`, `POST /api/journeys/dismiss-prompt` (`routes/api.php:278,286,287,292-296`). `DashboardPromptService` is therefore dead. `/planning/journeys` renders `JourneyCard` only from `journey_selections`, which nothing writes — live this run: "No Journeys Selected" for a user who had just completed a journey (`web-planning-journeys.png`). Not in the MB-08 dossier.
What is wrong: a journeys feature (selection, preview, dashboard prompts) exists on the server with no client; the side-nav "Journeys" page is a permanent empty state.
Suspected impact: no user effect beyond a dead page; maintenance weight.
Decision needed: yes — delete the routes, services and the Journeys page, or build the client that was meant to call them.

### MB-31 — The wizard's journey mode renders eight unregistered components as blank steps, and is reachable only from dead code
Map: docs/app-map/02-onboarding.md § 2.8
Status: Broken + Dead
Evidence: `OnboardingWizard.vue:1273-1282,1289,1320` return the component names `SimplePersonalInfoStep`, `SimpleIncomeStep`, `SimpleExpenditureStep`, `SimpleSavingsAccountStep`, `SimplePropertyMortgageStep`, `BudgetingSteps`, `QuickAssetsStep`, `JourneyCompletionStep`; none exists in `resources/js` and none is registered in the component's `components` list (`:485-504`). Live this run at `/onboarding/journey/budgeting` with no life stage: header "Setting up: Budgeting", four step labels, "25% complete", no form — `web-wizard-journey-budgeting-no-life-stage.png`. The route is linked only from `ProfileCompletionCards.vue:95-143` and `AreasToCompleteCard.vue:75` (both MB-01 dead) and from `DashboardPromptService` (MB-30 dead).
What is wrong: journey mode cannot render its steps, and nothing live sends a user to it.
Suspected impact: none today unless the URL is typed.
Decision needed: yes — remove journey mode and its routes with MB-01/MB-30, or restore the components.

### MB-32 — `users.life_stage` carries three vocabularies and overrides every wizard route
Map: docs/app-map/02-onboarding.md § 2.8, § 2.10
Status: Does not make sense
Evidence: `LifeStageService::setStage` writes a stage id (`app/Services/LifeStage/LifeStageService.php:41-43`, values `university` to `retirement`); `JourneyStateService::startJourney` writes a journey id (`app/Services/Onboarding/JourneyStateService.php:73-76`, values `budgeting` to `goals`); `OnboardingService::setFocusArea` writes a focus area (`OnboardingService.php:86-88`). The wizard enters life-stage mode whenever the stored value is a known stage (`OnboardingWizard.vue:550-555`; `lifeStage.js:104-109` drops unknown values). Live this run: with `life_stage = mid_career`, `/onboarding/full`, `/onboarding/estate` and `/onboarding/journey/budgeting` all rendered the nine-step mid-career wizard (`web-wizard-full-mode.png`, `web-wizard-module-estate.png`, `web-wizard-journey-budgeting.png`); after clearing the column on the test account, `/onboarding/estate` rendered its two module steps (`web-wizard-module-estate-no-life-stage.png`). Also: the path_choice bubble id is written to `onboarding_fyn_path` (`fyn-onboarding.v1.md`, `capture_field: onboarding_fyn_path`), so "Something else" stores `skip` alongside `journey`, `focus`, `campaign` (user 86 row read at 13:33: `path='skip'`).
What is wrong: one column means three things depending on which code wrote it last, and a stored stage silently changes what every wizard URL shows.
Suspected impact: any user who picked a stage on the welcome screen and later follows a module link.
Decision needed: yes — one column per meaning (or one vocabulary), and the wizard should honour its route's mode.

### MB-33 — The wizard step endpoint writes user columns with no field validation
Map: docs/app-map/02-onboarding.md § 2.8
Status: Does not make sense (data integrity)
Evidence: `app/Http/Controllers/Api/OnboardingController.php:75-78` validates only `step_name` (string) and `data` (array); `OnboardingService::processPersonalInfo` (`:200-230`) and `processIncomeInfo` (`:388-406`) assign the array's values straight to `users.*` (date of birth, gender, marital status, incomes, retirement age). The live request this run (user 86, `POST /api/onboarding/step`, 200) wrote gender, city and postcode.
What is wrong: the only validation is in the Vue form; the API boundary accepts any shape.
Suspected impact: malformed values can reach columns that every calculation reads.
Decision needed: no — a Form Request per step, or reuse the profile endpoints' rules.

### MB-34 — Forty-six hardcoded tax and money figures in the wizard's learning copy
Map: docs/app-map/02-onboarding.md § 2.8
Status: Does not make sense (Rule 2)
Evidence: `grep -c '£[0-9]' resources/js/constants/lifeStageConfig.js` = 46; examples `:119` "£20,000 … annual ISA allowance", `:238` "£60,000 … pension allowance", `:244` "£12,570 Personal Allowance", `:406` "a married couple can pass up to £1 million … combined nil-rate bands" (shown live on the Family step, `web-wizard-module-estate.png`). The labels interpolate `TAX_YEAR` but the values are literals.
What is wrong: tax values in user-facing strings are hardcoded rather than read from tax configuration.
Suspected impact: wrong figures after the next tax year change.
Decision needed: yes — source the figures from `taxConfig.js`, or accept the copy as editorial and date-stamp it.

### MB-35 — Wizard copy and options: American spelling and a missing marital status
Map: docs/app-map/02-onboarding.md § 2.8
Status: Does not make sense
Evidence: `resources/js/components/Onboarding/steps/FamilyInfoStep.vue:3` titles the step "Family & Dependents" (seen live); the Personal Information step's Marital Status select offers Single, Married, Divorced, Widowed (snapshot this run) while the column is an enum including `civil_partnership` and the Fyn flow accepts it (`capture_personal_details.md`).
What is wrong: British spelling rule; a legal status the rest of the app supports cannot be chosen in the wizard.
Suspected impact: civil partners onboarding through the wizard.
Decision needed: no.

### MB-36 — The `/m` savings screen says expenditure is missing while computing a target from it (adjacent: Savings)
Map: docs/app-map/02-onboarding.md § 4 (observed during the `/m` verify step)
Status: Does not make sense
Evidence: `/m/app/savings` for user 87 (monthly expenditure £1,800 saved by Fyn) showed "Emergency fund — Target (6 months) £10,800 — 2.8 months from cash savings" and, below it, "Emergency Fund Cannot Be Assessed — We cannot assess your emergency fund without expenditure data" and "Provide Your Income Details" for a retired user — `docs/app-map/screenshots/02-onboarding/m-savings-verify-pills.png`.
What is wrong: two parts of one screen read expenditure from different sources. Belongs to the Savings module map (section 06); recorded here because it was seen in this run.
Suspected impact: contradictory guidance on the savings screen.
Decision needed: no — for the Savings map to trace.

### MB-37 — The web savings verify page shows none of the accounts just captured
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Broken (web)
Evidence: Save Tax walk this run, user 90: after the savings section the chat navigated to `/savings` (`OnboardingStateMachine::campaignVerifyConfig()` `:239`), the page's Cash Overview tab rendered "Account Overview" and a "Connect to Open Banking — Coming Soon" placeholder, and `main.innerText` contained neither "Nationwide", "Marcus", "15,000" nor "30,000" while `savings_accounts` rows 237 and 238 held both accounts (tinker). Screenshot `docs/app-map/screenshots/02b-campaigns/web-fyn-06-savings-verify-page.png`. The `/m` savings screen was not driven this run; `resources/mobile/views/Savings.vue` was not read.
What is wrong: the verify step asks "does it look right?" on a page that shows nothing the user entered. The completeness widget on the same page says "Your savings accounts" is complete, so the person has no way to check the balances or rates Fyn recorded.
Suspected impact: every Save Tax user who ticked bank or savings verifies blind on web; a mis-heard balance (the interest figure drives the ISA advice, § 2.6) goes unnoticed.
Decision needed: yes — should the savings verify open `/net-worth/cash` (the Bank Accounts page in the side nav) instead, or should `/savings` list the accounts?

### MB-38 — Pension Check hardcodes the income bands in three places and skips the income cross-check
Map: docs/app-map/02b-campaigns.md § 2.1, § 2.3, § 2.8
Status: Does not make sense (Rule 2) plus Duplicate
Evidence: `public/pages/pensioncheck.php:167-201` ("Up to £50,270", "£50,271 to £100,000", "£100,001 to £125,140", "Above £125,140" as literals) against `public/pages/savetax.php:3-16` which reads `FunnelIncomeBand::pageLabels()`; `public/pages/js/pensioncheck-plan.js:70-75` (`INC_LABEL`); `OnboardingStateMachine::buildPensioncheckFunnelRecapPrompt()` `:2555-2575` (literal `'earning up to £50,270'` and so on) against the savetax recap's `saveTaxIncomeRecapLabel()` `:1476-1497`. `AuthController::stampFunnelIncomeContext()` `:167-171` returns early unless the campaign is `savetax`; `OnboardingChatDirector::detectIncomeFunnelMismatch()` `:3344-3351` returns null for any other campaign. User 91's `pending_registrations.funnel_answers` this run carried no `income_context` (tinker).
What is wrong: the Save Tax path was rebuilt to source every band label from the tax configuration and to challenge an income that contradicts the funnel band; the Pension Check path copied the old literals and never got the challenge. A tax-year rollover moves the Save Tax labels and leaves the Pension Check ones stale.
Suspected impact: wrong band labels after the next threshold change on the Pension Check page, recap and social-proof copy; a typo'd income on the Pension Check walk builds the whole retirement picture unchallenged (the same failure the Save Tax challenge was added for on 2026-09-11).
Decision needed: yes — extend the band labels, the context stamp and the mismatch challenge to Pension Check, or accept the drift.

### MB-39 — Four "campaign" pages are framed into `/m` as campaigns but lead to plain onboarding
Map: docs/app-map/02b-campaigns.md § 2.1
Status: Does not make sense (and Dead end for the phone redirect)
Evidence: `app/Http/Middleware/RedirectPhoneToMobile.php:43-45` lists `biggerpension`, `paymortgage`, `managedebt`, `wealth` alongside `savetax` and `pensioncheck`; `resources/js/router/index.js:264-286` serves them from `CampaignPage.vue`, whose calls to action are `/register?from=fyn` (`CampaignPage.vue:39, 193`); `fyn` is not a key of `campaign_map` or `journey_map` (`config/onboarding.php:57-86`), so `AiChatController::startOnboarding()` `:750-753` falls through to `STATE_PATH_CHOICE`. No funnel page, estimate service or state exists for any of the four (`git ls-files` this run).
What is wrong: the phone redirect and the router treat these as campaign entry points, but nothing downstream knows them. A phone visitor on `/biggerpension` is framed into `/m` "as a campaign" and then gets the generic path choice.
Suspected impact: advert traffic to those paths gets the ordinary onboarding, not a campaign walk; the allowlist and the pages suggest a product that does not exist.
Decision needed: yes — retire the four pages and prefixes, or build them as campaigns.

### MB-40 — The Save Tax plan headline counts savings lines the page never shows
Map: docs/app-map/02b-campaigns.md § 2.2
Status: Does not make sense
Evidence: `SaveTaxEstimateService::estimate()` `:87-160` produces lines keyed `pension`/`tax_trap_60`, `isa`, `psa`, `dividend`, `cgt`, `spouse_pa`, `spouse_psa`, `spouse_starting_rate`, `marriage_allowance` and sums them into `savings_total`; `public/pages/js/savetax-plan-v4.js:41-45` attaches a "Could save £X/yr" callout only for `pension_aa`, `psa`, `dividend`, `cgt`, `marriage_allowance`, `spouse_pa`, `personal_allowance`. This run (band `50271_100000`, spouse with no income, savings + pension + ISA + investments): headline "up to £12,527"; visible callouts £200 + £5,028 + £179 + £720 = £6,127; the `isa` (£4,000), `spouse_psa` (£400) and `spouse_starting_rate` (£2,000) lines were in the total and absent from the page (`window.SAVETAX_ESTIMATE` read this run; screenshot `web-plan-01-full.png`).
What is wrong: the person cannot reconcile the headline with the cards. The ISA line is excluded on purpose (`:39-40` comment) but still counted.
Suspected impact: the largest single line (ISA, 10% of income at the marginal rate) is invisible; the headline reads as inflated.
Decision needed: yes — show every counted line, or exclude the hidden lines from the headline.

### MB-41 — The public tax-allowances endpoint serves only mock-ups and mislabels a threshold
Map: docs/app-map/02b-campaigns.md § 2.2
Status: Dead (route side, for the live pages)
Evidence: `GET /api/public/tax-allowances` (`routes/api.php:244-245`, `TaxAllowancesController.php`) is fetched by `public/pages/js/savetax-plan.js:116` (loaded only by the v3 mock-up, `savetax-plan-v3.php:367`), `savetax-plan-v2.js` (v2 mock-up) and `resources/js/views/Public/SaveTaxCampaignPage.vue` (shadowed, MB-09); the live `savetax-plan.php:203-205` injects `SaveTaxEstimateService` output and loads `savetax-plan-v4.js`, which never calls the endpoint. `TaxAllowancesController.php:90-94` publishes `thresholds.hicbc_threshold` from `income_tax.personal_allowance_taper_threshold` (the £100,000 Personal Allowance taper, not the High Income Child Benefit Charge threshold) and no client reads `thresholds` (grep of `public/pages/js`, `resources/js`, `resources/mobile`).
What is wrong: a public, rate-limited endpoint kept alive by design mock-ups, carrying a wrongly named figure nobody reads.
Suspected impact: none for users; maintenance and a misleading name.
Decision needed: yes — delete with MB-08 and MB-10, or make the live plan page use it.

### MB-42 — The Tax Strategy recalculation endpoint has no caller on any client
Map: docs/app-map/02b-campaigns.md § 2.9
Status: Dead (route side)
Evidence: `POST /api/tax-strategy/calculate` (`routes/api.php:383`, `TaxStrategyController::calculate()` `:113-119`, `TaxStrategyCalculateRequest`, `TaxStrategyOverridesDTO`); `resources/js/store/modules/taxStrategy.js:42-54` defines `recalculate` but no component dispatches `taxStrategy/recalculate` (grep of `resources/js`); `resources/mobile/views/TaxStrategy.vue` and `ios-native/Fynla/Features/TaxStrategy/TaxStrategyClient.swift` call only `GET /api/tax-strategy` and mark-done. `TaxStrategyDashboard.vue:14-22` renders no slider or toggle. The BS-26 scenario docblock (`tests/Browser/scenarios/BS-26-savetax-single-employed.php`) still says "sliders trigger live recalc". Not in the MB-08 dossier.
What is wrong: the in-memory override path (pension percentage, salary sacrifice, ISA deposit, Marriage Allowance claim, asset shift) is built, tested (`CalculateEndpointTest`, 5 tests passed this run) and unreachable.
Suspected impact: none for users; tests pass against code nobody can reach.
Decision needed: yes — delete the endpoint, request, DTO and store action, or restore the what-if sliders that were designed for it.

### MB-43 — The charitable-giving campaign state exists only as remnants
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Dead (state); the tool stays reachable
Evidence: `OnboardingStateMachine::STATE_CAMPAIGN_CHARITABLE_GIVING` `:127` has no entry in `inCodeStates()` (`:446-722`) and no block in `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (grep this run); no section in `campaignSections()` `:207-238` enters it; `campaignVerifyConfig()` `:245` comments "null = inline confirm — used for charitable giving" for a state that is never entered. Remnants: `OnboardingChatDirector.php:5795` (ack), `ActivityFeedService.php:49`, `GateRoutes.php:155`, `SECTION_STRATEGY_TYPES['giving']` (`OnboardingChatDirector.php:1223`) which `campaignSectionAdvice()` never maps. The tool `capture_charitable_giving` remains in `AdviceFyn::WRITE_TOOLS` (`:185`) and is therefore offered through the advice-to-capture handoff (`OnboardingPromptBuilder.php:177-179`), and its handler and test (`CaptureCharitableGivingTest`) are live.
What is wrong: a section was removed from the walk (the code comments call this #586) but its state constant, ack, gate route, feed label and advice map were left behind.
Suspected impact: none at run time; the gift-aid strategy can only ever be voiced by the synthesis if a donation figure arrives some other way.
Decision needed: yes — delete the remnants, or put a giving section back in the walk.

### MB-44 — Declining the Save Tax consent gate ends the campaign for good, and the promised way back never shows
Map: docs/app-map/02b-campaigns.md § 2.5, § 2.9
Status: Dead end
Evidence: `OnboardingStateMachine::nextFromCampaignIntro()` `:1932-1938` routes "No thanks" to `STATE_DONE`; the comment says "They can revisit the campaign via the Tax Strategy tile on /actions". That tile (`resources/js/views/Actions/ActionsDashboard.vue:4-12`) renders only when `auth.user.onboarding_fyn_selection === 'savetax'` (`:155-158`), and both `emitDoneTurn` (02a § 2.7) and `emitTerminalNavigationTurn()` (`OnboardingChatDirector.php:5760-5764`) null that column. `config/onboarding.php:84` sets `reentry => false` for savetax, so `startOnboarding()` answers 409 to a completed user (`:680-685`). Verified this run: user 90 completed the walk; `/actions` showed no "Your tax strategy" tile (`main.innerText` had no such heading) and `onboarding_fyn_selection` was null (tinker).
What is wrong: the tile condition can only be true mid-walk, when the user is not on `/actions`; after "No thanks" or completion there is no route back into the Save Tax walk.
Suspected impact: a Save Tax registrant who declines the gate gets a completed, empty dashboard and can never run the campaign; the Pension Check re-entry exists but the Save Tax one does not.
Decision needed: yes — allow Save Tax re-entry (a `reentry` entry state), or drop the tile and the comment.

### MB-45 — Tax Strategy next-step buttons point at routes that do not exist, and the three clients disagree on the list
Map: docs/app-map/02b-campaigns.md § 2.9
Status: Dead end (web routes); Duplicate (list source and key map)
Evidence: `StrategyRecommendationList.vue:101-120` maps `pa_taper_rescue`, `additional_rate_avoidance`, `pension_aa_carry_forward`, `salary_sacrifice_ni`, `non_earner_spouse_pension`, `junior_pension`, `tapered_annual_allowance` to `/pension` and `bed_and_isa`, `dividend_allowance_harvest`, `cross_spouse_dividends` to `/investments`; `resources/js/router/index.js` defines neither (`/pension/:type/:id` at `:818` and `/investment` → `/net-worth/investments` at `:890` only). Navigating to `/pension` and `/investments` this run ended on `/dashboard`. `resources/mobile/views/TaxStrategy.vue:149-157` and `TaxStrategyView.swift:473-478` key the dividend step as `dividend_allowance` while the strategy emits `dividend_allowance_harvest` (`DividendAllowanceHarvestStrategy.php:55`), so `/m` and iOS never show a next step for it. The web list renders the calculator's `recommendations` (`taxStrategy.js:87`, `StrategyRecommendationList.vue:139`) while `/m` (`TaxStrategy.vue:174`) and iOS (`TaxStrategyModels.swift:19`) render `composed_plan.items`. This run the two lists differed in order and in the presence of `gia_to_spouse` (API payload read by curl).
What is wrong: two next-step maps and two list sources for one page, one of them pointing at dead routes.
Suspected impact: "Open a pension" and "Open investments" on web drop the user on the dashboard; the dividend action has no next step on phone; the three clients can show different actions for the same user.
Decision needed: yes — which list is canonical (the composed plan, which Fyn voices, or the raw calculator list), then one next-step map.

### MB-46 — The non-working-spouse prompt hardcodes "around £40,000 of unused tax allowances"
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Does not make sense (Rule 2)
Evidence: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`, block `campaign_spouse_non_working_assets`, `prompt_text` "…they have around £40,000 of unused tax allowances we can put to work…"; rendered verbatim on web this run (screenshot `web-fyn-10-spouse-verify-page.png` shows the preceding turn; chat transcript row for step `campaign_spouse_non_working_assets`).
What is wrong: a pound figure in Fyn's mouth that no service computed. The spouse advice that follows (`buildSpouseAdvice()` `:1367-1405`) does compute the allowances from `TaxConfigService`.
Suspected impact: the figure drifts from the configured allowances at the next tax year.
Decision needed: no — build the figure from the tax configuration like the spouse advice does.

### MB-47 — Web pages under the chat do not refetch after a Fyn write, so the verify screens show stale figures
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Fixed 2026-09-14, branch `mb-47-fyn-navigation-refresh`. Root cause: `AiChatPanel.handleNavigation()` pushed the route and nothing else — profile-backed pages render from `auth/currentUser` and `userProfile/profile`, both loaded at sign-in (the record-card View button refreshed them, the navigation path did not), and a route resolving to the page already on screen never remounted (the old same-route check compared the raw string, so the `/investment` → `/net-worth/investments` alias defeated it). Fix: `handleNavigation` now refreshes `auth/fetchUser` and `userProfile/fetchProfile` before every push and, when the resolved route is unchanged, closes the dock and fires `fyn-screen-refresh`; `fynScreenRefreshMixin` (new) subscribes the investments, pensions, savings and dashboard pages, replacing the dashboard's own listener. Vitest: `AiChatPanel.navigation.spec.js` (4), web suite 310 passed. Live, web, this branch: user 91 re-entry — verify edit on `/net-worth/retirement` (Vanguard SIPP £180,000 → £185,000) updated the page under the chat with no reload (`screenshots/mb-fixes/mb47-web-retirement-after-edit.png`); user 92 (fresh Pension Check, `mb47-pc-web-2026-09-14@example.com`) — three consecutive verifies on `/net-worth/retirement` each showed the figure just written (state pension £11,200, target £30,000 at 66; `mb47-web-retirement-state-pension-same-route.png`) and the expenditure verify on `/valuable-info?section=expenditure` showed £2,750 / £33,000 where the mapping run saw £0 (`mb27-web-expenditure-verify-shows-figure.png`). `/m` untouched: it already refetches on `store.screenRefreshTick`.
Evidence: this run, user 90. (a) After the verify edit landed (`investment_accounts.id 123` `current_value` 25000 → 27000 at 15:17:22, audit rows 82-83, read-back "current value now £27,000"), `/net-worth/investments` under the chat still read £25,000 (`main.innerText` had "25,000" and not "27,000"). (b) The expenditure verify opened `/valuable-info?section=expenditure` showing "Monthly Expenditure £0, Annual £0" while `users.monthly_expenditure` and `expenditure_profiles.total_monthly_expenditure` were 3200 (tinker; screenshot `web-fyn-11-expenditure-verify-page.png`) — the MB-27 case reproduced. `/m` refetches on `store.screenRefreshTick` (`resources/mobile/views/Income.vue:90-92`); the web store has no equivalent for these pages.
What is wrong: the verify loop's whole point is to show what was just written; on web the page shows the pre-write store.
Suspected impact: a user confirms a page that does not show their figure, or "corrects" a value that was already right.
Decision needed: no — refetch the page data on Fyn navigation and after a landed edit (extends MB-27).

### MB-48 — The spouse verify page shows nothing for a non-working spouse
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Fixed 2026-09-14, branch `mb-48-spouse-verify-household`. `UserProfileService::spouseIncomeSources()` now reads the household row before deciding: null only when there is neither income nor captured figures; a zero-income spouse gets `total 0`, `sources []` and the `household` block. Both renderers already handled an empty list. Pest: `SpouseIncomeSummaryTest` (4, new case red before the fix) and `CaptureSpouseNonWorkingAssetsTest` (3). Live, user 90: web `/valuable-info?section=income` shows "Your spouse's income / What you told Fyn about your spouse / ISA balance £5,000" (`screenshots/mb-fixes/mb48-web-spouse-verify-non-working.png`); `/m` `/m/app/income?section=spouse` shows "Your spouse's total annual income £0 / No spouse income recorded yet / ISA balance £5,000" (`mb48-m-spouse-verify-non-working.png`).
Evidence: `UserProfileService::spouseIncomeSources()` `:488-501` returns null when `tax_strategy_household_inputs.spouse_annual_income` is not above zero, before it reads `spouseHouseholdCaptured()`; for the `single_earner_couple` path the only captured figures are `spouse_existing_*` (`CoordinatingAgent.php:5648-5688`). This run the spouse section navigated to `/valuable-info?section=income` and `main.innerText` contained no "spouse" text while `tax_strategy_household_inputs` row 1 held `spouse_existing_isa_balance = 5000` (tinker; screenshot `web-fyn-10-spouse-verify-page.png`). `resources/mobile/views/Income.vue:74-86` reads the same payload, so `/m` has the same gap (not driven this run).
What is wrong: the verify step for the non-working-spouse section sends the person to a page that cannot show what they said.
Suspected impact: every married Save Tax user whose spouse has no income verifies blind; the 2026-09-11 fix for dual earners did not cover this path.
Decision needed: no — return the household figures whether or not the spouse has income.

### MB-49 — A natural correction on the verify-edit turn produced no write and a failure message
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Does not make sense
Evidence: this run, `campaign_verify_edit` on investments: "The Vanguard account is actually worth £27,000, not £25,000" → no tool dispatched (audit row 81 is only the episode; `ai_messages` 599 `verify_edit_failed: true`) → "I wasn't able to apply that change. Tell me the exact value you want to replace and I will try again." The rephrase "Change the current value from £25,000 to £27,000" dispatched `update_record` (audit 82-83) and landed. Both turns ran with the xAI provider (`AI_PROVIDER=xai`, `grok-4.3`); the second took 6 minutes 36 seconds end to end (`ai_messages` 600 at 15:14:03, 601 at 15:20:39).
What is wrong: the honesty gate (`handleCampaignVerifyEdit()` `:4290-4322`) is right to refuse a false success, but the ordinary phrasing of a correction did not reach the tool.
Suspected impact: users who correct a figure the way people speak are told to try again; the edit turn is slow enough to read as broken.
Decision needed: yes — accept as model behaviour, or add a deterministic parser for "X, not Y" corrections before the model turn.

### MB-50 — The `/m` expenditure verify screen shows a derived total, not the figure the user gave
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Fixed 2026-09-14, branch `mb-50-m-expenditure-entered-figure`. `/m` only: the screen read `active_monthly_total` alone; the server presentation already carried `manual_monthly_total` and `commitments_monthly_total` (web shows all three). The hero is now labelled "Total monthly expenditure" and the summary card lists "Monthly spending you entered" and "Financial commitments (auto-calculated)". Vitest `resources/mobile/views/__tests__/Expenditure.spec.js` (+2, first red before). Live `/m` (rebuilt bundle), user 91: £2,400 entered, £667 commitments, £3,067 total (`screenshots/mb-fixes/mb50-m-expenditure-entered-figure.png`).
Evidence: this run, user 91 said "Around £2,400 a month"; `/m/app/expenditure?section=expenditure` showed "Monthly expenditure £3,067, £36,800 a year, Only a monthly summary has been entered" and `main.innerText` did not contain "2,400" (screenshot `m-fyn-06-expenditure-verify-screen.png`). £3,067 is £2,400 plus the £666.67 monthly SIPP contribution the walk had recorded as a financial commitment.
What is wrong: the person is asked whether £3,067 "looks right" without ever seeing their £2,400 on the screen.
Suspected impact: a user who said £2,400 is likely to tap "No, change something" and start an edit turn for a figure that is already correct.
Decision needed: no — show the entered summary figure on the verify screen alongside the commitments.

### MB-51 — Campaign prompt copy that does not fit the path it fires on
Map: docs/app-map/02b-campaigns.md § 2.5, § 2.7
Status: Does not make sense (copy)
Evidence: (a) `OnboardingStateMachine::sectionLabel()` has no label for `state_pension` or `retirement_goals`, so the verify announce reads "I've saved your details. Next I'll take you to your details page" (seen twice on `/m` this run). (b) The `campaign_pension_contribs` prompt "Beyond the workplace pension we covered…" fired for user 91 (self-employed) after `campaign_occupational_scheme` was skipped by `skipIfOccupationalScheme()`. (c) `buildCampaignDobPrompt()` `:1794-1801` frames the date-of-birth question around pensions only when `funnel_answers.assets` contains `pension`; Pension Check stores `pensions`, so every Pension Check user gets the neutral wording (seen this run). (d) `buildCampaignIntroPrompt()` `:1879-1893` says "bank and savings accounts" for a user who ticked only savings.
What is wrong: the copy assumes the Save Tax funnel's keys and the employed path.
Suspected impact: cosmetic, but on the walk that is the product's first impression.
Decision needed: no — fix the four strings and the key lookup.

### MB-52 — `/m` renders multi-paragraph advice turns as one run-on paragraph
Map: docs/app-map/02b-campaigns.md § 2.6
Status: Duplicate drift (Rule 20)
Evidence: `OnboardingChatDirector::buildRetirementSectionAdvice()` `:1355-1364` joins items and the actions-list line with `"\n\n"`; on web the savings advice this run rendered as two paragraphs (snapshot: separate `paragraph` nodes 1745 and 1746); on `/m` the retirement-goals advice rendered as one paragraph "…Lump Sum entitlement.You may want to consider: Consider Adjusting Retirement Age…I've added this to your actions list…" (snapshot `m-after-goals-continue.yml`, node f23e754). The `/m` renderer is `resources/mobile/utils/fynText.js` (not read this run).
What is wrong: two renderers for one Fyn text contract, and one of them loses paragraph breaks.
Suspected impact: two-item advice on phone reads as one sentence with no space after the full stop.
Decision needed: no — render paragraph breaks on `/m` (or emit one bubble per item from the one director).

### MB-53 — The Pension Check re-entry walk re-asks pension questions already answered
Map: docs/app-map/02b-campaigns.md § 2.8
Status: Does not make sense
Evidence: `campaignSections('pensioncheck')` `:224-231` has data-presence skips for income, state pension, retirement goals and expenditure only; the pensions section always enters at `campaign_dob` and `applySkipRules` reaches `campaign_pension_contribs`, which has no skip. This run, user 91 re-entered with `campaign2_existing_recap` listing the Vanguard SIPP (£180,000 pot, £8,000 a year already on file), tapped "Yes, that's right", and was asked "do you make any personal pension or Self-Invested Personal Pension contributions?" again (`onboarding_fyn_step = campaign_pension_contribs`, tinker).
What is wrong: the recap says "here's what I already have" and the next turn asks for it again.
Suspected impact: re-entrants repeat the pensions section; a second answer can create a duplicate SIPP unless the model chooses `update_record`.
Decision needed: yes — add data-presence skips to the pensions states, or accept the repeat as a deliberate refresh.

### MB-54 — The Save Tax workplace-pension capture drops the pot value and the provider
Map: docs/app-map/02b-campaigns.md § 2.5
Status: Does not make sense
Evidence: this run, user 90 said "I pay 5% of my salary into my Aviva workplace pension, the pot is about £40,000, my employer pays 3%, and it is salary sacrifice"; `dc_pensions` row 71 was written with `scheme_name = "Workplace Pension"`, `provider = "Workplace Pension"`, `current_fund_value = 0.00`, `employee_contribution_percent = 5`, `employer_contribution_percent = 3` (tinker; audit rows 88-89 `create_pension`). The pensioncheck walk has a pot-value loop (`campaign2_pension_pots`, `:519-545`); the savetax walk goes `campaign_occupational_scheme` → `campaign_pension_contribs` (`nextFromCampaignOccupationalScheme()` `:2098-2103`) with no pot state.
What is wrong: the state's prompt asks for percentages and sacrifice only; a stated pot value and provider name have nowhere to go, and the retirement page then shows a £0 pension.
Suspected impact: the salary-sacrifice and carry-forward strategies compute against a £0 pot; the retirement projection ignores the real pot.
Decision needed: yes — add the pot-value loop to the Save Tax pensions section, or extend the occupational prompt.
Correction 2026-09-14 (checked against the code and the persisted turn): `create_pension` never ran on this turn. The model's reply to message 609 was the injection refusal ("I can only help with financial planning questions", `ai_messages` 610, no tool call); row 71 was written by the deterministic occupational backstop (`AssetCaptureEntityExtractor::extractOccupationalPensionAnswer()`, log 15:23:56 "Gap-fill firing … llm_emitted 0"), which parsed the percentages but read no pot and no provider (it only took a provider after the word "with"). The audit rows cited above belong to the next turn's blocked-attempt retry. The backstop now keeps a multi-word provider whole and reads a stated pot ("the pot is about £40,000") — branch `mb-56-58-pension-capture` — so the degraded row no longer happens; the decision above (a Save Tax pot loop for the case where the user gives no pot) still stands.

### MB-55 — The two plan pages build different sign-in links for an existing account
Map: docs/app-map/02b-campaigns.md § 2.3
Status: Does not make sense
Evidence: `public/pages/js/savetax-plan-v4.js:183` links `/login?from=savetax`; `resources/js/views/Login.vue:246-248` honours only `?redirect=` and ignores `from`, so the person lands on the dashboard with no campaign. `public/pages/js/pensioncheck-plan.js:463-469` links `/login?redirect=/dashboard?openFyn=journey&from=pensioncheck`, which does open Fyn with the campaign (verified as the rendered `href` this run).
What is wrong: the same "already registered" case behaves differently on the two campaigns; the Save Tax one loses the campaign, and Save Tax has no re-entry anyway (MB-44).
Suspected impact: an existing user who came through a Save Tax advert signs in to an ordinary dashboard.
Decision needed: no — build the Save Tax link the way the Pension Check one is built (subject to MB-44).

### MB-56 — The Pension Check pot loop cannot be left with a £0 pot, and the skip reply stalls
Map: docs/app-map/02b-campaigns.md § 2.8
Status: Fixed 2026-09-14, branch `mb-56-58-pension-capture`. Root cause, two legs: (1) `PensionStore::hasDcPensionsMissingPotValue()` treats `current_fund_value <= 0` as missing and the column is NOT NULL DEFAULT 0, so a stated £0 could never be "answered"; (2) "Not sure, skip it" was caught by the director's zero-output guard (`OnboardingChatDirector.php` retry guard) before `nextFromPensionPots()` ran — the guard's own start-anchored regex knew no/none/nothing/done, not "not sure" or "skip", and the I5 tests in `PensioncheckRouteFixesTest` called `nextFromPensionPots` directly, so they proved a branch the live path never reached. Fix: one vocabulary `OnboardingStateMachine::DONT_KNOW_TOKENS` + `isDontKnowAnswer()` used by the state machine, the director's substantive-answer check and its zero-output guard; `statesZeroPot()` lets a stated zero leave the loop (the row still reads 0, so a re-entry recap can re-ask — MB-53; a confirmed-zero flag is the upgrade path). Tests: `PensioncheckRouteFixesTest` (+3), `CaptureWriteFailureTest` (+2 director-level). Live, web: user 94 (`mb56-pc-b-2026-09-14@example.com`) answered the pot question "Not sure, skip it" and went straight to the contributions question, `onboarding_fyn_step = campaign_pension_contribs`, no retry copy (`screenshots/mb-fixes/mb56-web-pot-loop-skip-advances.png`); user 95 (`mb56-pc-c-…`) answered "£0, it's empty", the model wrote 0 to the row and the walk advanced the same way (`mb56-web-pot-loop-zero-advances.png`).
Evidence: user 92, conversation 201, driven on web while verifying MB-47. At `campaign2_pension_pots` the loop asked for the duplicate pension's value (MB-58). "£0, that one is a duplicate of the Bramble Ltd pension" → "Recorded — Scottish Workplace Pension value updated to £0." and the same question again (`ai_messages` 727-729). "Not sure, skip it" → "Recorded — pension capture skipped." immediately followed by "Sorry, I didn't catch that. Could you try again?" and no state change (`ai_messages` 733-735; `users.onboarding_fyn_step` stayed `campaign2_pension_pots`). Only a full sentence with a non-zero value ("The Scottish Workplace Pension pot is worth £500") left the loop (`ai_messages` 736-738). `PensionStore::hasDcPensionsMissingPotValue()` `:171-177` treats `current_fund_value <= 0` as missing, so a stated £0 is never "answered"; `nextFromPensionPots()` `OnboardingStateMachine.php:2240-2261` only advances on a skip token when the delegated handler reaches it, and here the skip was recorded but the turn ended in the stall reply.
What is wrong: a person whose pot is genuinely empty, or who does not know the value, is asked the same question until they invent a number.
Suspected impact: every Pension Check user with a zero-value or unknown pot on web; `/m` shares the state machine so the loop is the same there (not driven).
Decision needed: no — treat a stated £0 as an answer (a nullable "not yet valued" marker, or a `pot_value_confirmed` flag), and advance when the skip is recorded rather than falling through to the stall.

### MB-57 — A bare figure answered to the pot question overwrote the user's annual income
Map: docs/app-map/02b-campaigns.md § 2.8
Status: Fixed 2026-09-14, branch `mb-56-58-pension-capture`. Root cause: `toolsForFocus()` appends `update_profile` to every walk focus for the retraction rule, and nothing scoped it on delegated capture turns — the section/field scope existed only for verify-edit turns; `PensioncheckCaptureFocusTest` asserted the tool was offered. Fix: `OnboardingPromptBuilder::WALK_PROFILE_SCOPE` (personal: any field; income_occupation: employment_status, occupation, employer, industry only) travels with the unified focus into the agent and is enforced at dispatch by `HasAiChat::onboardingProfileScopeError()`, the sibling of the verify-edit scope check. Advice, inline capture and verify-edit are untouched. Tests: `RetractionTest` (+2). Live, web: user 93 (`mb58-pc-a-2026-09-14@example.com`) answered the pot question with a bare "£500"; the model chose `update_record` on the pension this time (row 75 → £500) and `annual_employment_income` stayed £58,000, so the dispatch guard was proven by the test rather than exercised live.
Evidence: user 92, conversation 201. At `campaign2_pension_pots`, the reply "£500" produced "Recorded — annual income updated to £500." (`ai_messages` 730-731) and `users.annual_employment_income` went from 62000 (captured at `base_work`, `ai_messages` 706-708 area) to 500.00; the pension row was untouched (`dc_pensions` 74 still 0.00 until the later sentence). The pot loop is a delegated state whose tool list is `toolsForFocus()` plus `update_profile` and `update_record` (`OnboardingPromptBuilder.php:103-108`), so the model was free to write the figure to the profile.
What is wrong: a pension-pot question silently rewrote the income the whole plan is built on, and the read-back named a field the user was never asked about.
Suspected impact: any campaign user who answers a pot, contribution or balance question with a bare number; every downstream figure (tax, retirement projection, the synthesis) is wrong from that point. High.
Decision needed: no — the pot turn must not expose `update_profile` (or must scope it away from income), and a bare number at `campaign2_pension_pots` should route to the pension in question.

### MB-58 — One workplace-pension sentence created two pension records
Map: docs/app-map/02b-campaigns.md § 2.8
Status: Fixed 2026-09-14, branch `mb-56-58-pension-capture`. Cause corrected: the model called `create_pension` once (message 722, row 73); row 74 was the deterministic occupational backstop (log 17:16:16 "Gap-fill firing … llm_emitted 0"). Two gaps let it through: the director counted the model's writes for the gap-fill only from `fill_form` events, never from a landed direct write; and the extractor's provider regex took one word ("Scottish"), so the persisted-key dedupe (`pension|dc|scottish` vs `scottishwidows`) could not match. `GapFillDedupTest` used identical providers on both sides and the extractor test only "Aviva". Fix: the delegated loop counts landed writes per tool and the gap-fill stays quiet when the model landed at least as many as the extractor found; the extractor keeps the whole provider and reads a stated pot. Tests: `AssetCaptureEntityExtractorTest` (+3), `CaptureWriteFailureTest` (+1). Live, web: users 93, 94 and 95 each gave the workplace pension "with Scottish Widows" / "with Royal London" / "with Standard Life" and each ended with exactly one `dc_pensions` row carrying the whole provider name; no gap-fill line was logged for any of them (the corrected provider key matched the model's row through the existing persisted-row dedupe).
Evidence: user 92, conversation 201, `campaign_occupational_scheme`: "I pay 5%, my employer pays 4%, not salary sacrifice. The pot is £48,000 with Scottish Widows" → "Saved 2 records": `dc_pensions` 73 (`Bramble Ltd workplace pension`, provider `Scottish Widows`, 48000) and 74 (`Scottish Workplace Pension`, provider `Scottish`, 0.00), created one second apart (17:16:15 and 17:16:16). Row 74 then drove the pot loop (MB-56, MB-57).
What is wrong: the model called `create_pension` twice for one scheme, the second time with the provider split into a scheme name and a truncated provider.
Suspected impact: a duplicate pension in the retirement projection (double counting once both have values) and an extra pot-loop turn.
Decision needed: no — one `create_pension` per scheme on the occupational turn (dedupe on employer + provider, or reject a second create in the same turn); adjacent to MB-54.
