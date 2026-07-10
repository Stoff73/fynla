# Fynla Online Readiness Programme Design

**Date:** 10 July 2026

**Status:** Approved design; implementation plan follows after document review

**Owner:** CSJ

**Release path:** feature branch -> `dev` -> csjones verification -> `main` -> fynla.org

## 1. Goal

Bring the current Fynla application online as a production release that is demonstrably correct, observable, reversible, and verified on both the desktop web application and the `/m` mobile-web pathway.

"Online and working" means all of the following:

- Every critical and high finding in the July 2026 full-app, blind-spot, and Fyn audits is either fixed and verified or explicitly shown to be inapplicable by evidence.
- The release is protected by repeatable PHP, frontend, lint, build, and browser-test gates.
- Agent-led browser verification interacts with real user journeys on csjones before production promotion.
- The exact `dev` tip tested on csjones is the code promoted to `main`.
- Production has monitoring, queue and scheduler visibility, a rehearsed rollback, and post-release verification.
- A failure in authentication, authorization, payment state, data retention, financial calculations, Fyn behaviour, or web-to-`/m` parity prevents release.

## 2. Current state and evidence

The design is based on repository and vault inspection on 10 July 2026.

### 2.1 Branch and release state

- Production `main`: `2e8357b`.
- Staging `dev`: `e16ea5f`.
- The branches have diverged: 28 `main`-only commits and 163 `dev`-only commits from their merge base.
- The current pull-request-shaped `main...dev` difference contains 367 files, approximately 19,348 insertions and 8,754 deletions.
- The July full-app and blind-spot audit/spec/plan documents are present on `main` and in the vault but absent from `dev`; an implementation agent branching correctly from `dev` cannot currently read its own source contracts.
- PRs #613, #614, and #615 are merged to `dev`, deployed to csjones, and live-browser-verified.
- Production remains untouched by the June/July feature train after the last `dev -> main` release.
- The next production release includes at least the `users.active_campaign` migration plus web and `/m` bundle changes.

### 2.2 Existing quality assets

- Pest covers unit, feature, integration, architecture, browser-stub, and Fyn evaluation suites.
- The repository currently contains roughly 332 unit-test files, 370 feature-test files, 33 architecture-test files, and 3 integration-test files.
- Vitest exists with 36 frontend test files and more than 500 test cases.
- Playwright is installed and `tests/E2E/` contains 55 tests across the major modules and mobile scaffold.
- `tests/Browser/scenarios/` contains agent-driven BS-NN acceptance scripts for Fyn, onboarding, server-sent events, persistence, security, and campaign journeys.
- Environment-specific build scripts already build both the desktop and `/m` bundles for csjones and fynla.org.
- Agent hooks already encode useful design, environment, deployment, and `/m` parity checks.

### 2.3 Quality gaps that this programme must close

- There is no general GitHub Actions workflow for linting, PHP tests, frontend tests, builds, or Playwright.
- Playwright uses `testDir: './tests/e2e'` while the tracked directory is `tests/E2E`, which is not portable to case-sensitive CI runners.
- The Playwright authentication helper assumes an obsolete registration form and skips the real verification-code flow.
- The browser tests rely heavily on fixed sleeps, optional `isVisible()` branches, and assertions such as `toBeDefined()` that do not prove behaviour.
- Playwright starts only `php artisan serve`; it does not establish a deterministic frontend build or Vite process for CI.
- The Pest Browser suite intentionally skips its scenarios and therefore cannot be treated as an automated browser gate.
- Laravel Pint is available, but JavaScript/Vue linting is not configured.
- Existing agent hooks are session-specific and use absolute local paths; they are not reusable CI commands.
- The July blind-spot audit identified unresolved critical/high risks in observability, queues, GDPR erasure, joint ownership, tax-year rollover, silent failures, concurrency, scale, framework support, and financial test coverage.
- The July Fyn audit identified a live repetition incident plus gating, state, surface-parity, cache, and prompt/tool-coherence defects.
- The complete July Updates corpus is 34 artifacts on `origin/main` only; `origin/dev` contains none of those source documents even though the SaveTax, WP-1–6/WP-5c, pensioncheck, audit-fix, and life-event implementation branches are already merged into `dev`. The verified disposition is recorded in `docs/superpowers/specs/2026-07-10-july-updates-inventory.md`.

## 3. Scope

### 3.1 Launch blockers

The following are mandatory before production promotion:

1. A reproducible quality and CI foundation.
2. The Fyn repetition incident and its root-cause family.
3. Error monitoring, scheduler heartbeat, and failed-job visibility.
4. A real SiteGround-compatible asynchronous queue.
5. Silent-success and silent-zero failure paths in high-risk surfaces.
6. Complete and truthful GDPR deletion/retention behaviour.
7. Joint-ownership authorization, consent, and unlink cleanup.
8. Tax-year rollover safety and exact tests for high-risk financial calculations.
9. High-risk concurrency, cache-coherence, and request-path scale fixes.
10. Required Fyn web/`/m` parity and onboarding state hygiene.
11. Supported Laravel, Sanctum, and PHP runtime posture through a dedicated upgrade project.
12. A full automated and agent-led release-candidate gauntlet.
13. Staging soak, deployment rehearsal, rollback proof, and written go/no-go.

### 3.2 Findings that do not automatically block

Medium and low findings remain in the issue ledger unless one of these conditions applies:

- The finding is a dependency of a launch blocker.
- The finding breaks a required acceptance journey.
- The finding creates a security, financial-correctness, data-loss, or production-operability risk during remediation.
- The finding is cheaper and safer to fix inside the same bounded change than to preserve deliberately.

### 3.3 Included continuation release trains, not initial launch blockers

- Remaining pensioncheck polish and desktop achievements/milestones/history parity from the delivered July plans.
- OpenAI provider expansion from the July Fyn plan, after canonical xAI/provider truth is reconciled.
- The investment campaign specified in `July/July6Updates/investment-campaign-spec.md` and its implementation plan.
- The estate campaign specified in `July/July6Updates/estate-campaign-spec.md` and its implementation plan.

These remain in the master programme so they cannot be lost, but each starts only after the initial production release and its seven-day check are green. Each is a separate `feature -> dev -> main` release train with the same automated and agent-led browser gates. The investment campaign lands fully before the estate campaign because both modify the shared campaign machinery.

### 3.4 Explicitly outside the complete programme

- New product features or redesigns unrelated to remediation.
- Capacitor iOS packaging and TestFlight release. The `/m` mobile-web pathway remains fully in scope.
- Automated production deployment. SiteGround deployment remains an operator-controlled release step.
- Refactoring whose only benefit is aesthetic cleanup.

## 4. Programme architecture

The work is a gated release train. A later gate cannot begin until the required evidence from the previous gate is green.

### Gate 0: Freeze and baseline

- Pause non-essential feature development.
- Reconcile the `main`-only and `dev`-only histories without losing main-side documentation.
- Bring all 34 July Updates artifacts into the `dev` line before remediation begins, preserving their `main` provenance and byte content except for separately recorded corrections to stale file paths.
- Produce one release manifest containing commits, changed files, migrations, configuration changes, corpus changes, build changes, scheduled commands, queue changes, and deployment paths.
- Establish baseline results for PHP syntax, Pint, Pest, Vitest, dependency audit, target builds, Playwright, and the current csjones smoke set.
- Create a single issue ledger mapping every July audit finding to owner, severity, workstream, test, environment evidence, and launch disposition, plus a source register mapping every July artifact and executable work package to delivered proof or a master-programme task.

**Exit:** the release scope and baseline are reproducible from committed commands; every critical/high finding has an accountable work item.

### Gate 1: Quality spine

- Add repository-owned lint and quality commands.
- Add GitHub Actions gates for pull requests to `dev` and `main`.
- Repair the Playwright runner and isolated test environment.
- Establish automated web and `/m` smoke tests.
- Establish agent acceptance manifests and evidence format.

**Exit:** a deliberately introduced PHP, Vue, policy, unit-test, build, desktop-browser, or `/m` regression fails the appropriate gate.

### Gate 2: Production blockers

- Land small risk-grouped remediation PRs through the feature -> csjones -> `dev` flow.
- Require the Gate 1 checks on every PR.
- Require independent agent browser verification for each user-visible change.
- Keep decision-gated product changes isolated from mechanical safety fixes.

**Exit:** every critical/high finding is green or closed as inapplicable with evidence; no known launch blocker remains.

### Gate 3: Whole-product gauntlet

- Run exact financial fixtures and the complete automated browser matrix.
- Run independent agent acceptance across desktop and `/m`.
- Exercise external-service behaviour on csjones using sandbox or controlled failure doubles.
- Close all failures through the diagnose -> fix -> re-run loop.

**Exit:** the whole release candidate passes with zero unresolved severity-one or severity-two launch defects.

### Gate 4: Staging release candidate

- Deploy the exact `dev` tip and matching web/`/m` bundles to csjones.
- Run the full gauntlet against the deployed environment.
- Rehearse migrations, queue worker, scheduler, config caching, file reconciliation, backup, and rollback.
- Complete a minimum seven-day staging soak after the final launch-blocking change, resetting the soak if a severity-one defect is found.
- Produce a written go/no-go for CSJ.

**Exit:** CSJ records "go" and the release commit, build manifests, database plan, rollback point, and test evidence are fixed and immutable.

### Gate 5: Production cutover

- Open and review `dev -> main` only after the exact `dev` tip is green on csjones.
- Build with the fynla.org build script.
- Reconcile production files, upload web and `/m` bundles, run migrations, rebuild Composer autoloading, and clear caches ending with `config:cache`.
- Never use `route:cache`, `artisan optimize`, `migrate:fresh`, `migrate:refresh`, or `db:wipe`.
- Run read-safe production browser smoke tests on desktop and `/m` using a dedicated production QA account and CSJ-provided multifactor code.
- Monitor application errors, queue throughput, scheduler heartbeat, webhooks, and server health.

**Exit:** production smoke is green, no rollback trigger fires, and CSJ accepts the deployment.

### Gate 6: Post-release proof

- Run health checks at 15 minutes, 24 hours, and seven days.
- Confirm authentication, queue, scheduler, mail, payments, Fyn, tax configuration, desktop, and `/m` health.
- Review monitoring for silent-error and performance regressions.
- Convert non-blocking findings into the ranked post-launch backlog.
- Resume feature development only after the seven-day review.

### Gate 7: July continuation releases

- Close the delivered-plan pensioncheck and gamification parity/polish list.
- Reconcile xAI model truth and wire the July-specified OpenAI provider behind configuration.
- Execute the investment campaign spec/plan through its own complete release train.
- Only after investment is live and stable, execute the estate campaign spec/plan through a separate release train.
- Return every continuation through automated tests, independent browser acceptance, staging proof, deployment rehearsal, CSJ go/no-go, and post-release checks.

**Exit:** every executable July plan is either delivered with current evidence or carries a CSJ-approved explicit disposition; the continuation features are online without weakening the stable core release.

## 5. Quality and lint design

### 5.1 One canonical quality entry point

The implementation will expose a repository-owned command such as `composer quality` or `./scripts/quality/run.sh`. CI and agents invoke the same underlying commands; the workflow YAML does not contain a second, divergent test recipe.

The command supports explicit lanes:

- `lint`: deterministic syntax, format, JavaScript/Vue, and policy checks.
- `php`: Pest unit, feature, integration, architecture, and evaluation suites.
- `frontend`: Vitest.
- `build`: csjones and fynla.org web plus `/m` builds.
- `browser:smoke`: fast deterministic desktop and `/m` Playwright tests.
- `browser:full`: the complete automated browser matrix.
- `all`: all blocking local checks in release order.

### 5.2 PHP checks

- Syntax-check tracked PHP files in `app/`, `config/`, `database/`, `routes/`, and PHP public pages.
- Run Laravel Pint in check-only mode in CI; agents may run fix mode only on files in their change scope.
- Run architecture tests before slower feature suites so structural failures are reported quickly.
- Preserve the MySQL test path; do not silently substitute SQLite for money, JSON, locking, or schema behaviour.
- Add Larastan/PHPStan only as a separate baseline-backed task after the current quality spine is stable; it must not produce an unowned wall of legacy warnings.

### 5.3 JavaScript and Vue checks

- Add ESLint with Vue support and a committed flat configuration.
- Lint `resources/js/`, `resources/mobile/`, `tests/frontend/`, `tests/E2E/`, and JavaScript configuration files.
- CI runs check-only mode; formatting is not silently rewritten in CI.
- Rules prioritize correctness: undefined variables, duplicate keys, invalid Vue patterns, unreachable code, accidental promises, and test mistakes.
- Style-only rules are introduced conservatively to avoid mass unrelated churn.

### 5.4 Fynla policy checks

Move reusable logic out of agent-only hooks into repository scripts, then have both hooks and CI call those scripts.

Blocking policies include:

- No new banned colour tokens or hardcoded component-style hex values.
- No new emoji or Unicode-as-icon additions.
- Layout wrappers on routed views.
- No new user-facing financial-quality scores.
- No raw deployment builds or banned Artisan commands in deployment automation.
- No committed environment credentials or production secrets.
- Canonical enum checks at API/form boundaries.
- Prompt/tool corpus and golden-master parity for Fyn changes.
- `/m` impact declaration when desktop user-facing files change without a mobile file change. The declaration must identify shared-backend parity, an existing mobile counterpart, or a CSJ-approved no-counterpart exception.

Policy scans apply to the changed lines or changed files for forward-only rules so grandfathered violations do not create permanent false failures.

## 6. Continuous integration design

### 6.1 Pull-request checks

Pull requests to `dev` and `main` run:

1. Dependency install with locked Composer and npm manifests.
2. Secret and conflict-marker checks.
3. PHP syntax and Pint check.
4. ESLint and Fynla policy lint.
5. Pest architecture and focused invariant suites.
6. Full Pest suites in parallel jobs grouped by suite, not by arbitrary file splitting.
7. Vitest.
8. csjones and fynla.org build validation, including both manifest files and base-path assertions.
9. Playwright smoke on desktop and `/m`.

The branch cannot merge if a blocking job is red. Retries are allowed only for diagnosed infrastructure flakiness; functional failures are not hidden by repeated retries.

### 6.2 Full and scheduled checks

- The full Playwright matrix runs on release-candidate PRs, manually on demand, and nightly while the programme is active.
- Dependency audits run on every release candidate and on a schedule.
- Long Fyn evaluation or provider-cassette suites run outside the fast feedback lane but remain required before affected PRs merge.
- Reports, traces, screenshots, videos, and machine-readable results are retained as workflow artifacts.

### 6.3 No automatic production mutation

CI validates builds and creates evidence. It does not upload to SiteGround, run production migrations, change server environment files, charge payment methods, merge protected branches, or promote `dev` to `main`.

## 7. Automated browser-test design

### 7.1 Test environment

- CI uses an ephemeral MySQL 8 service and a newly created end-to-end database for each run.
- Local agent runs use a newly named database reserved for that run; they never point Playwright at the normal `laravel` development database.
- Preparation runs normal migrations into an empty database and then `php artisan db:seed`.
- No browser-test workflow uses `migrate:fresh`, `migrate:refresh`, or `db:wipe`.
- External mail, payment, push, and large-language-model calls use deterministic fakes in CI.
- csjones acceptance uses its real staging configuration, Revolut sandbox, and explicitly namespaced test users.

### 7.2 Runner repair

- Correct the case-sensitive Playwright directory.
- Start Laravel and Vite, or serve a verified built bundle, from one deterministic orchestration script.
- Replace the obsolete auth helper with canonical registration, multifactor, login, preview-persona, and logout helpers.
- Replace fixed timeouts with locator, URL, response, or state waits.
- Remove optional branches that turn missing required functionality into passes.
- Replace weak assertions with exact visible text, state, API response, database outcome, or persisted reload assertions.
- Fail on uncaught browser exceptions, console errors outside an explicit allow-list, unexpected 4xx/5xx responses, and failed resource loads.
- Record traces on first failure and retain screenshot/video evidence.

### 7.3 Automated matrix

The matrix is data-driven rather than 84 copy-pasted files.

For each of the six preview personas and seven financial modules:

- Desktop route loads under `AppLayout`.
- `/m` counterpart loads under `MobileLayout`, or a documented designed no-counterpart is asserted.
- Seeded headline figures match approved fixtures.
- Lists show the expected records.
- One supported create/read/update/delete round trip persists across reload.
- Joint ownership is shown using the canonical single-record split.
- The action updates dashboard and module summaries on both surfaces.
- No console, network, or server error is laundered into £0 or an empty success state.

Additional automated journeys cover:

- Registration, verification, login, multifactor, password reset, session recovery, preview switching, and logout.
- Goal contribution -> dashboard update.
- Property plus mortgage -> net-worth update.
- Pension contribution -> Annual Allowance and Money Purchase Annual Allowance behaviour.
- Life event -> tax-optimised allocation.
- Estate gifts -> seven-year taper and Inheritance Tax result.
- SaveTax and pension campaign registration/onboarding/landing flows.
- Fyn read-only advice, delegated capture, write failure, token limit, consent, disconnection/resume, and repetition guard.
- Admin and advisor authorization boundaries.
- Revolut sandbox checkout, webhook retry, cancellation failure, and local/remote state agreement.
- Public homepage content, key marketing routes, and desktop-to-`/m` phone redirection.

### 7.4 Existing BS-NN scenarios

The BS-NN files remain the acceptance contract for complex Fyn flows. Each scenario receives one of three statuses:

- Automated: a Playwright test proves the contract in CI.
- Agent acceptance: an interactive agent run is required on csjones because the flow depends on streaming or a live provider.
- Both: automated deterministic coverage plus live semantic acceptance.

Skipped Pest stubs never count as a pass. Release evidence names the actual automated or agent execution that satisfied each scenario.

## 8. Agent-led browser verification

### 8.1 Separation of responsibilities

- Implementation agent: diagnoses and changes one bounded PR, writes regression tests, and runs local gates.
- Review agent: verifies spec compliance, test quality, security implications, financial correctness, and unintended scope changes.
- Browser agent: independently executes the acceptance manifest on the deployed csjones branch and reports evidence.
- Release operator: prepares deployment notes/checklists and performs only CSJ-authorized merge/deploy actions.

The same agent may coordinate the programme, but an implementation cannot be accepted solely on its own narrative. A separate review pass and browser evidence are required.

### 8.2 Acceptance manifest

Every user-visible PR declares:

- Starting user/persona and required seed state.
- Desktop route and `/m` route.
- Exact clicks, fields, submissions, and expected visible results.
- Expected API, database, server-sent event, cache, or audit outcome.
- Negative checks: no false success, no £0 laundering, no duplicate records, no web/`/m` divergence, no console/server errors.
- Cleanup steps and test-record identifiers.

The browser agent must use accessible locators and normal interactions. DOM injection, direct JavaScript clicks, and snapshot-only verification cannot satisfy the gate.

### 8.3 Evidence

Each run produces:

- Commit SHA and environment URL.
- Browser/project, viewport, user/persona, and run identifier.
- Per-step pass/fail result.
- Screenshots at assertion boundaries.
- Playwright trace or equivalent interaction record.
- Relevant request/response, database, audit, and streaming evidence with secrets and personal data removed.
- A concise failure diagnosis linked to the remediation loop.

Evidence belongs under a release-specific artifact location or CI artifact, not mixed into product source unless the existing BS-NN contract requires committed screenshots.

## 9. Remediation sequence

The implementation plan will preserve the detailed July remediation documents and arrange them into the following dependency-safe waves.

### Wave 1: Quality foundation

- Canonical quality runner.
- CI workflows.
- ESLint and policy lint.
- Playwright environment, auth fixtures, desktop smoke, and `/m` smoke.

### Wave 2: Immediate incident and visibility

- Fyn gating fix, loop guards, repetition collapse, overlay activation, and live web/`/m` repro.
- Sentry/backend error reporting with personal-data scrubbing.
- Scheduler heartbeat and failed-job watchdog.
- Database queue, worker cron, retries, and job failure handlers.

### Wave 3: Silent failure and live broken paths

- Dashboard, alerts, estate/Inheritance Tax, and mobile unavailable states.
- Revolut cancel/webhook truthfulness.
- Registration and multifactor mail failure truthfulness.
- Broken GDPR `salary` field and truthful copy.
- Fyn tool-catalogue degradation visibility.

### Wave 4: Security and data lifecycle

- GDPR table-completeness guard, hard-delete policy, joint-record reassignment, FCA retention guard, export cleanup, and AI-store coordination.
- Accepted-spouse validation on joint writes.
- Unlink/divorce revocation across all joint models.
- Authorization parity and mass-assignment hardening.

### Wave 5: Financial correctness and rollover

- Date-driven tax-year activation and successor warning.
- 2027/28 configuration only from current authoritative HMRC sources and CSJ confirmation.
- Tax-boundary predicate fixes.
- Exact-value tests for Inheritance Tax, Residence Nil Rate Band taper, pension allowances, Gift Aid, tax bands, ownership shares, and named untested money services.
- Removal of tests that cannot fail.

### Wave 6: Concurrency, cache, and scale

- Cross-surface cache invalidation.
- Module-create idempotency.
- Atomic/locked contribution and gamification updates.
- Monte Carlo queue ordering and deduplication.
- Fyn inflight lock robustness.
- Off-request aggregates and long-running calculations.
- Bounded transcript, audit purge, and indexed admin queries.

### Wave 7: Framework support project

- Laravel 10 -> supported Laravel release.
- Sanctum and PHP runtime compatibility.
- Dependency upgrades required by the framework.
- Full regression and browser passes as a dedicated release candidate, without unrelated feature work.

### Wave 8: Whole-product closure

- Complete automated matrix.
- Complete agent acceptance matrix.
- Exact `main...dev` release diff review.
- Seven-day staging soak, go/no-go, production cutover, and post-release proof.

## 10. Failure classification and loop

### Severity one

- Authentication or session failure.
- Unauthorized data visibility or mutation.
- Data loss or duplicate financial records.
- Payment state diverges from Revolut or a real charge is at risk.
- A financial calculation differs from its approved exact fixture beyond documented presentation rounding.
- GDPR erasure/retention violates the approved policy.
- Production outage, widespread server errors, or unrecoverable migration failure.

### Severity two

- A primary desktop or `/m` journey is broken.
- Desktop and `/m` show conflicting state or figures.
- A server/browser error is hidden as success, £0, or an empty result.
- Fyn repeats, fabricates success, exposes a write tool on advice, or instructs an unavailable tool.
- Required monitoring, queue, scheduler, or rollback evidence is missing.

Severity one and two defects block the relevant PR and reset the affected release gate. Severity three cosmetic defects may be deferred only when they do not violate the design system, accessibility, or a numbered repository rule.

The mandatory loop is:

1. Reproduce with file/line, request, database, browser, or log evidence.
2. Identify the root cause.
3. Add a failing regression test.
4. Implement the smallest root-cause fix.
5. Run focused tests.
6. Run the full affected lane.
7. Deploy to csjones when user-visible.
8. Browser-test desktop and `/m`.
9. Repeat until green.

## 11. Staging and production data safety

- Preview users remain isolated with `is_preview_user = true` filtering.
- Automated browser tests never use the normal local development database.
- csjones test users use a release/run prefix and are inventoried for cleanup.
- Cleanup is scoped to those identifiers and followed by the required seed command when database data is removed.
- Production smoke is non-destructive by default and uses a dedicated QA user. Controlled Fyn advice may persist QA conversation evidence; financial CRUD and payment mutation remain disabled unless CSJ explicitly authorizes a bounded production check.
- Production payment verification never creates an uncontrolled real charge.
- Credentials and multifactor codes never enter committed artifacts, logs, screenshots, or chat summaries.
- Production database snapshots used in rehearsal are sanitised and access-controlled.

## 12. Deployment and rollback design

### 12.1 Staging

- Deploy feature branches to csjones before merge when required by the release skill.
- Build locally with `./deploy/csjones-fynla/build.sh`.
- Preserve old chunks during active test sessions.
- Pull source in the real csjones `dev` checkout, migrate, clear caches, rebuild Composer autoloading, and finish with `config:cache`.
- Verify homepage content, desktop SPA, `/m` iframe/build, APIs, queue worker, and scheduler.

### 12.2 Production

- Build locally with `./deploy/fynla-org/build.sh` only after `dev -> main` merge authorization.
- Perform a full file reconciliation because production is not a git checkout and may contain drift.
- Upload `public/build/`, `public/m-build/`, changed PHP/source files, corpus files, and deployment templates listed by generated deploy notes.
- Run `composer dump-autoload -o`, `php artisan migrate --force`, required idempotent corpus/index validators, cache clears, and `config:cache`.
- Keep routes uncached.

### 12.3 Rollback triggers

Rollback is initiated for any of these during the release window:

- Login, multifactor, registration, or session recovery failure.
- Unauthorized cross-user access.
- Data loss, duplicate financial records, or migration corruption.
- Material financial calculation mismatch.
- Payment/webhook divergence.
- Desktop or `/m` boot failure.
- Sustained 5xx rate, queue failure, or scheduler failure.
- Fyn persistence or write-safety contract violation.

The rollback rehearsal must prove code/assets restoration, migration decision path, config/cache restoration, queue handling, and user communication. Database migrations that cannot be safely rolled back require a forward-compatible code rollback strategy documented before release.

## 13. Decision gates retained for CSJ

These are concrete execution decisions, not undefined scope. The affected PR cannot start until the decision is recorded.

1. Fyn advice signposting: recommended choice is plain-text route guidance while `navigate_to_page` stays capture-side.
2. Fyn a1/a2 overlays: recommended choice is activate both with regenerated golden masters.
3. Holistic-plan tier gate: recommended choice is enforce the same gate as the REST endpoint.
4. Retirement summary canon: choose the one engine used by web dashboard, `/m`, and Fyn; recommended choice from the Fyn remediation spec is the 4% safe-withdrawal-rate summary, with Monte Carlo and scheme quotes explicitly labelled as different bases.
5. Mobile dashboard cache: recommended choice is complete invalidation on every write and retain the 24-hour value only as a fallback cache lifetime, with the documentation corrected.
6. Self-service "Delete my Data": recommended choice is clear the named profile fields and state that truthfully; full account erasure remains the separate retention-controlled pathway.
7. Retention hard delete: recommended choice is hard-delete the user after the retention period, preserving only the minimal re-registration tombstone.
8. 2027/28 tax figures and salary-sacrifice effective date: values must be verified against current authoritative sources and explicitly accepted by CSJ before seeding or copy changes.
9. Fyn compliance backstops: recommended choices are deterministic adviser-signpost insertion when required, report-only product-name detection until its eval false-positive rate is accepted, a readable violations queue, and sanitisation of banned acronym/icon output before persistence.
10. OpenAI provider rollout: wire `gpt-5-nano` as the July plan specifies, but leave it dormant until current official API parameters, pricing, credentials, provider-parity evals, and the launch component-routing decision are all verified.
11. Pensioncheck polish: approve final public/Fyn copy, campaign-affinity persistence, carry-forward wording, and original social images before that continuation releases.
12. Campaign URLs and copy: confirm `investmentcheck` then `inheritancecheck` names and final copy before each campaign's public-surface slice.

## 14. Required programme artifacts

The implementation produces and maintains:

- Master implementation plan.
- July Updates branch/file inventory and machine-readable plan-disposition register.
- Audit finding ledger.
- Test coverage and persona/module/surface matrix.
- Acceptance manifests.
- CI workflows and artifact-retention policy.
- Release manifest for `main...dev`.
- Migration and database rehearsal report.
- Staging deployment checklist and evidence pack.
- Rollback runbook.
- Security and dependency re-check.
- CSJ go/no-go record.
- Production deployment checklist.
- 15-minute, 24-hour, and seven-day post-release reports.

## 15. Acceptance criteria for the programme

The programme is complete only when:

1. All critical/high audit findings are closed with code/test/environment evidence or evidenced as inapplicable.
2. Pull requests cannot merge with failing lint, PHP tests, frontend tests, build checks, or browser smoke.
3. The full Pest and Vitest suites pass on the final release candidate.
4. Both target build scripts pass and produce valid desktop and `/m` manifests with correct base paths.
5. Automated Playwright passes the approved desktop and `/m` matrix.
6. Agent acceptance passes on the exact csjones release commit.
7. Every required BS-NN contract has named automated or live-agent evidence; skipped stubs are not counted.
8. The Fyn repetition reproduction yields one clean response on web and `/m` with sane persistence.
9. Financial fixtures pin exact approved outputs for the named high-risk calculations.
10. Queue, scheduler, monitoring, mail, payment sandbox, and webhook failure paths are observable and truthful.
11. The framework/runtime versions are within supported security windows.
12. The seven-day staging soak completes with no unresolved severity-one or severity-two launch defect.
13. Deployment and rollback rehearsals pass.
14. CSJ records the production go decision.
15. Production smoke passes on desktop and `/m`, followed by green 15-minute, 24-hour, and seven-day checks.
16. Every July artifact is present on the `dev` line and registered as delivered, launch remediation, continuation, evidence-only, or superseded; every executable plan/work package points to a master-programme task.
17. The continuation lane closes the pension/gamification parity list, provider expansion, investment campaign, and estate campaign through separate green release trains.

## 16. Source documents

- `AGENTS.md`
- `deploy/DEPLOY.md`
- `.agents/skills/release/SKILL.md`
- `docs/superpowers/specs/2026-07-10-july-updates-inventory.md`
- `origin/main:July/July1Updates/` (Gate 0 reconciles the complete corpus into `dev`)
- `origin/main:July/July3Updates/`
- `origin/main:July/July4Updates/`
- `origin/main:July/July5Updates/`
- `origin/main:July/July6Updates/`
- `origin/main:July/July7Updates/`
- `tests/Browser/README.md`
- `playwright.config.js`
- `/Users/CSJ/Desktop/fynlaBrain/Current State/DeploymentBuild.md`
- `/Users/CSJ/Desktop/fynlaBrain/Current State/Testing.md`
- `/Users/CSJ/Desktop/fynlaBrain/May/May12Updates/PRD-test-gauntlet-v1.md`
- `/Users/CSJ/Desktop/fynlaBrain/Plans/test-gauntlet-plan-v1.md`
