# Fynla Control Plane S06 Dashboard Access and People Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver one role-scoped founder and developer dashboard with server-enforced access management, developer self-service, onboarding, role changes, offboarding, releases, jobs, audit, costs and health.

**Architecture:** A Vue 3 SPA is compiled to static assets served by the existing PHP gateway. Google Workspace OIDC creates an HttpOnly gateway session linked to the S02 principal. Every `/api/*` query/action invokes server policy; the client receives explicit view models and capabilities, never raw tables or hidden-data payloads.

**Tech Stack:** Vue 3, TypeScript, Vite, Vue Router, Pinia, Vitest, Testing Library, PHP 8.2, `jumbojett/openid-connect-php` 1.0.x, PHPUnit, Google Workspace OIDC, Google Chrome acceptance.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Depends on S02 identity/policy; individual views also depend on their owning backend sections.
- Repository: `Fynla/FynlaMCP`.
- One dashboard only; founder, engineering, product/design and personal areas are role-scoped views.
- Hidden navigation is not authorisation. Every API route enforces capability and resource policy server-side.
- State-changing routes require CSRF protection, optimistic concurrency and an audit event.
- UI changes require automated tests plus acceptance in the user's installed Google Chrome; Chromium is prohibited.
- No raw tokens, provider responses, prompt bodies, secrets or restricted records enter browser state.

---

## File Structure

```text
gateway/ui/
├── package.json
├── src/{main,router,stores,api,components,views,types}/
└── tests/{unit,integration}/
gateway/src/
├── Auth/{OidcController,Session,SessionStore,Csrf}.php
├── Dashboard/{DashboardController,ViewModels}.php
├── Access/{AccessApiController,AccessChangeService}.php
├── People/{LifecycleController,OnboardingService,RoleChangeService,OffboardingService}.php
├── Registry/RegistryApiController.php
└── Operations/OperationsApiController.php
gateway/database/{007_dashboard_sessions.sql,008_people_lifecycle.sql,009_usage_health.sql}
gateway/tests/{Unit,Feature,Integration}/Dashboard/
tests/acceptance/dashboard/
docs/implementation-evidence/s06/
```

## PR Register

| PR | Outcome | Depends on | State |
|---|---|---|---|
| S06-PR01 | SPA shell and Google Workspace OIDC | S02-PR01, S02-PR02 | Not started |
| S06-PR02 | Role-scoped navigation and effective-access preview | S06-PR01 | Not started |
| S06-PR03 | Roles, assignments and temporary grants UI | S06-PR02 | Not started |
| S06-PR04 | Developer and machine self-service | S05-PR01, S06-PR02 | Not started |
| S06-PR05 | Onboarding workflow | S05-PR04, S06-PR03 | Not started |
| S06-PR06 | Role change and offboarding workflows | S04-PR06, S06-PR05 | Not started |
| S06-PR07 | Registry release and approval UI | S01-PR03, S02-PR03, S06-PR03 | Not started |
| S06-PR08 | Jobs, audit, cost and health views | S02-PR05, S03-PR05, S06-PR02 | Not started |

## S06-PR01 — Establish the dashboard shell and one authentication system

**Branch:** `codex/icp-s06-pr01-dashboard-auth`

**Traceability:** `UI-01..05`, `IAM-15`, `SEC-22`.

**Acceptance:** `mcp.fynla.org` serves one responsive SPA; only mapped active Google Workspace users obtain a short-lived server session; login/logout/CSRF/session expiry work without exposing tokens to JavaScript.

### Task S06-PR01-T01 — Scaffold the tested Vue shell

**Files:** `gateway/ui/package.json`, `vite.config.ts`, `src/main.ts`, `src/router/index.ts`, `src/views/HomeView.vue`, `src/api/client.ts`, `tests/unit/App.test.ts`.

- [ ] Add a test rendering loading, authenticated home and unauthenticated redirect states.
- [ ] Run `npm --prefix gateway/ui test -- --run`; expect package/config failure.
- [ ] Add Vue 3/TypeScript/Vite/Vitest with a same-origin `/api` client and no token persistence.
- [ ] Build assets into `gateway/public/dashboard/manifest.json` with hashed filenames.
- [ ] Run unit tests and `npm --prefix gateway/ui run build`; expect pass.
- [ ] Commit `[ICP S06/PR01/T01] Scaffold the unified dashboard shell`.

### Task S06-PR01-T02 — Authenticate with Google Workspace OIDC

**Files:** `gateway/composer.json`, `gateway/database/007_dashboard_sessions.sql`, `gateway/src/Auth/OidcController.php`, `SessionStore.php`, `Csrf.php`, `gateway/tests/Feature/Dashboard/OidcAuthenticationTest.php`.

- [ ] Add tests for state, nonce, PKCE, issuer, audience, hosted-domain, verified-email, mapped subject, disabled person, session rotation, logout and expired session.
- [ ] Run focused PHPUnit; expect missing routes/services.
- [ ] Add the reviewed OIDC dependency pinned in `composer.lock`; implement `/auth/login`, `/auth/callback`, `/auth/logout`, `/api/session`.
- [ ] Store only hashed session IDs server-side; set `Secure`, `HttpOnly`, `SameSite=Lax`, narrow path and absolute expiry; rotate after login and role change.
- [ ] Add double-submit or server-session CSRF token requirement to non-GET `/api` routes.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR01/T02] Add mapped Google Workspace sessions`.

### PR S06-PR01 review gate

- [ ] Run PHP and UI tests, `composer audit` and `npm audit --audit-level=high`.
- [ ] Confirm no ID/access/refresh token appears in HTML, JS state, logs or local/session storage.
- [ ] Use installed Google Chrome to verify login, responsive shell, expiry and logout.
- [ ] Record screenshots at 390×844 and 1280×900 plus cookie/security-header evidence.

## S06-PR02 — Scope navigation and data by effective access

**Branch:** `codex/icp-s06-pr02-role-scoped-shell`

**Traceability:** `UI-06..11`, `IAM-16..18`.

**Acceptance:** The server returns permitted modules/counts only; the SPA renders role-appropriate navigation and an effective-access preview; direct URL/API access still denies unauthorised users.

### Task S06-PR02-T01 — Build a server-authorised navigation view model

**Files:** `gateway/src/Dashboard/DashboardController.php`, `NavigationViewModel.php`, `gateway/tests/Feature/Dashboard/NavigationPolicyTest.php`.

```php
final readonly class NavigationItem {
    public function __construct(
        public string $id,
        public string $label,
        public string $route,
        public array $allowedActions,
    ) {}
}
```

- [ ] Add role/resource matrix tests for Home, Knowledge, Assets, SOPs, Repositories, Developers, Jobs, Approvals, Slack, Releases, People, Audit, Costs, Health and Integrations.
- [ ] Run focused tests; expect failure.
- [ ] Implement `GET /api/navigation` from effective capabilities; do not query counts for denied modules.
- [ ] Add direct-route denial tests returning 403 stable error objects.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR02/T01] Authorise dashboard modules server-side`.

### Task S06-PR02-T02 — Render role-scoped shell and access preview

**Files:** `gateway/ui/src/components/AppNavigation.vue`, `views/AccessPreviewView.vue`, `stores/session.ts`, `tests/integration/navigation.test.ts`.

- [ ] Test founder, lead, developer, product/design and service-disabled fixtures render exact links/actions.
- [ ] Run UI tests; expect failure.
- [ ] Render navigation only from `/api/navigation` and show an access preview grouped by role, repository, content and temporary grant with denial reasons.
- [ ] On any 401 clear in-memory session and redirect; on 403 retain shell and show a non-leaking denial.
- [ ] Run tests/build and Chrome keyboard/responsive acceptance.
- [ ] Commit `[ICP S06/PR02/T02] Render one role-scoped dashboard`.

### PR S06-PR02 review gate

- [ ] Attempt every hidden route through direct URL and API for each role fixture.
- [ ] Confirm denied module names/counts do not appear in payloads.
- [ ] Run axe-compatible accessibility checks and Chrome keyboard navigation.
- [ ] Record role screenshots and API denial evidence.

## S06-PR03 — Manage roles, assignments and temporary grants safely

**Branch:** `codex/icp-s06-pr03-access-manager`

**Traceability:** `IAM-19..31`, `UI-12`.

**Acceptance:** Authorised administrators create/edit custom roles, assign repositories/content, grant expiring access, preview diffs, apply or roll back changes; final-founder removal and self-elevation are impossible.

### Task S06-PR03-T01 — Add transactional access-change commands

**Files:** `gateway/src/Access/AccessChange.php`, `AccessChangeService.php`, `AccessApiController.php`, `gateway/tests/Integration/Access/AccessChangeServiceTest.php`.

- [ ] Test role template create/revise, assignment add/remove, temporary grant expiry, bulk assignment, stale version, rollback, self-elevation and final-founder removal.
- [ ] Run focused tests; expect failure.
- [ ] Implement `POST /api/access/preview`, `/apply`, `/rollback` with canonical before/after diff and optimistic `version`.
- [ ] Require elevated proposal/approval for founder access, new admin, high-risk approval, security relaxation and service-identity expansion.
- [ ] Invalidate affected sessions/attestations immediately after committed revocation.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR03/T01] Apply auditable access changes`.

### Task S06-PR03-T02 — Build the Access Manager UI

**Files:** `gateway/ui/src/views/access/{People,Roles,Assignments,Requests,History}.vue`, `components/AccessDiff.vue`, `tests/integration/access-manager.test.ts`.

- [ ] Test list/filter, custom role builder, repository/content assignment, expiry/reason, bulk selection, preview, approval-needed, apply conflict and rollback.
- [ ] Run UI tests; expect failure.
- [ ] Build forms from server permission definitions; do not hard-code role capabilities in the client.
- [ ] Require explicit confirmation of the server-generated diff; refresh on version conflict.
- [ ] Run tests/build and Chrome acceptance for desktop/mobile, keyboard and destructive confirmation.
- [ ] Commit `[ICP S06/PR03/T02] Deliver the role and access manager`.

### PR S06-PR03 review gate

- [ ] Exercise the full permission matrix and concurrent-admin conflicts.
- [ ] Prove no administrator can grant themselves a capability they cannot assign.
- [ ] Expire a temporary grant and verify access/session revocation without manual cleanup.
- [ ] Record before/after, approval and rollback evidence.

## S06-PR04 — Provide safe developer and machine self-service

**Branch:** `codex/icp-s06-pr04-developer-self-service`

**Traceability:** `IAM-32..37`, `SES-56`, `UI-13`.

**Acceptance:** Developers register/revoke machines, edit schema-allowlisted preferences, choose permitted assistants/tools, preview compiled configuration and see sync/drift without assigning their own access.

### Task S06-PR04-T01 — Add machine/profile APIs with protected-field checks

**Files:** `gateway/src/Dashboard/DeveloperProfileController.php`, `gateway/src/Sessions/MachineService.php`, `gateway/tests/Feature/Dashboard/DeveloperSelfServiceTest.php`.

- [ ] Test own-versus-other profile, machine registration/revocation, assistant choice, allowlisted preference, protected key, repository assignment and enrolment token one-time use.
- [ ] Run focused tests; expect failure.
- [ ] Implement `/api/me/profile`, `/api/me/machines`, `/api/me/configuration-preview`, `/api/me/sync-status` through the registry schema and policy engine.
- [ ] Return enrolment token only once and store only its hash/expiry.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR04/T01] Bound developer self-service APIs`.

### Task S06-PR04-T02 — Build developer and machine views

**Files:** `gateway/ui/src/views/developer/{Profile,Machines,Configuration,SyncStatus}.vue`, `tests/integration/developer-profile.test.ts`.

- [ ] Test OS/assistant form, permitted settings, config provenance preview, copy-once enrolment, revoke machine and drift states.
- [ ] Run UI tests; expect failure.
- [ ] Render protected fields read-only with source layer; never render token after the creation response is dismissed.
- [ ] Show current/outdated/modified/incompatible/blocked/offline with last sync/preflight and exact remediation.
- [ ] Run tests/build and Chrome acceptance.
- [ ] Commit `[ICP S06/PR04/T02] Add developer configuration self-service`.

### PR S06-PR04 review gate

- [ ] Attempt self-assignment and protected preference changes through UI and direct API; expected denial.
- [ ] Register/revoke one macOS and one Windows fixture machine.
- [ ] Confirm token copy-once behaviour and log redaction.
- [ ] Record compiled preview provenance and drift status evidence.

## S06-PR05 — Execute onboarding as a blocking workflow

**Branch:** `codex/icp-s06-pr05-onboarding`

**Traceability:** `PPL-01..12`, `UI-14`, `SEC-23`.

**Acceptance:** An authorised administrator onboards a person through identity mapping, role/repository/content assignment, environment/assistant selection, enrolment, first validation and acknowledgement; unresolved mandatory steps prevent completion.

### Task S06-PR05-T01 — Persist and execute onboarding steps idempotently

**Files:** `gateway/database/008_people_lifecycle.sql`, `gateway/src/People/OnboardingService.php`, `LifecycleController.php`, `gateway/tests/Integration/People/OnboardingServiceTest.php`.

- [ ] Add tables `lifecycle_cases`, `lifecycle_steps`, `lifecycle_external_actions`, `lifecycle_completion_records` with immutable step receipts.
- [ ] Test all ten design steps, rerun after failure, external action unavailable, duplicate identity, expired engagement, missing review date and unresolved mandatory step.
- [ ] Run focused tests; expect failure.
- [ ] Implement state `draft → approved → running → action_required → complete|failed`; each step has idempotency key and evidence.
- [ ] Generate a short-lived single-use enrolment token only after access transaction commits.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR05/T01] Execute idempotent onboarding cases`.

### Task S06-PR05-T02 — Build the onboarding wizard and evidence view

**Files:** `gateway/ui/src/views/people/OnboardingWizard.vue`, `LifecycleCaseView.vue`, `tests/integration/onboarding.test.ts`.

- [ ] Test identity, role/discipline/manager, resources, OS, assistants, GitHub/Slack, review/engagement, preview/approval and progress steps.
- [ ] Run UI tests; expect failure.
- [ ] Persist drafts server-side, show effective-access diff, and prevent completion while any mandatory step is non-terminal.
- [ ] Make external manual actions visible checklist items with owner/evidence, never false success.
- [ ] Run Chrome end-to-end onboarding against fixtures.
- [ ] Commit `[ICP S06/PR05/T02] Deliver platform onboarding workflow`.

### PR S06-PR05 review gate

- [ ] Onboard a developer fixture for macOS/Codex and Windows/Claude.
- [ ] Interrupt after every step and prove resume does not duplicate external action.
- [ ] Confirm the first preflight, repository access and policy acknowledgement are recorded.
- [ ] Obtain people owner and security review.

## S06-PR06 — Handle role changes and offboarding completely

**Branch:** `codex/icp-s06-pr06-role-offboarding`

**Traceability:** `PPL-13..27`, `IAM-38`, `SEC-24`.

**Acceptance:** Immediate/scheduled role changes preview effective access; offboarding blocks new sessions, revokes access/tokens, handles jobs/ownership/external actions and produces a signed completion record without claiming device wipe.

### Task S06-PR06-T01 — Implement scheduled role-change transactions

**Files:** `gateway/src/People/RoleChangeService.php`, `gateway/tests/Integration/People/RoleChangeServiceTest.php`.

- [ ] Test immediate/future effective time, discipline/repository changes, concurrent change, cancellation, approval escalation and access diff.
- [ ] Run focused tests; expect failure.
- [ ] Apply due changes through one transaction and invalidate sessions/attestations affected by lost access.
- [ ] Trigger a new effective configuration assignment without editing canonical profile content in SQL.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR06/T01] Apply scheduled role changes safely`.

### Task S06-PR06-T02 — Implement offboarding and ownership transfer

**Files:** `gateway/src/People/OffboardingService.php`, `gateway/tests/Integration/People/OffboardingServiceTest.php`, `gateway/ui/src/views/people/OffboardingWizard.vue`, `tests/integration/offboarding.test.ts`.

- [ ] Test immediate block, token/grant/session revocation, running-job cancel/transfer, PR/branch/asset/approval inventory, ownership transfer, secret-rotation flag and device return/wipe confirmation.
- [ ] Run backend/UI tests; expect failure.
- [ ] Execute internal revocations first; external GitHub/Slack actions use idempotent receipts and unavailable APIs remain blocking checklist items.
- [ ] Preserve profile/audit history and sign the completion record digest; never claim local clone deletion.
- [ ] Run Chrome end-to-end offboarding and direct login/session-reuse attempts.
- [ ] Commit `[ICP S06/PR06/T02] Complete auditable offboarding`.

### PR S06-PR06 review gate

- [ ] Offboard a fixture with running Codex job, open PR, owned skill and temporary grant.
- [ ] Confirm no new session/job/Slack delegation after the first internal-revocation step.
- [ ] Confirm final-founder protection and mandatory ownership transfer.
- [ ] Record signed completion and outstanding manual device/secret actions.

## S06-PR07 — Review, approve, activate and roll back registry releases

**Branch:** `codex/icp-s06-pr07-release-ui`

**Traceability:** `REG-22..29`, `UI-15`, `SEC-25`.

**Acceptance:** Authorised users inspect validation, generated diffs, compatibility and access impact; required approvers activate one release assignment atomically or roll back; two-founder changes cannot self-approve.

### Task S06-PR07-T01 — Add registry/release API view models and commands

**Files:** `gateway/src/Registry/RegistryApiController.php`, `ReleaseApprovalService.php`, `gateway/tests/Feature/Dashboard/ReleaseApiTest.php`.

- [ ] Test draft/validated/published/revoked views, file diff, compiler/schema compatibility, consumer assignments, failed validation, rollback and two-founder policy.
- [ ] Run focused tests; expect failure.
- [ ] Add `/api/registry/assets`, `/api/releases`, `/api/releases/{id}/diff`, `/approve`, `/activate`, `/rollback` using S01/S02 records.
- [ ] Prevent requester self-approval where separation of duties applies.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR07/T01] Expose controlled release operations`.

### Task S06-PR07-T02 — Build assets, release and approval views

**Files:** `gateway/ui/src/views/registry/{Assets,ReleaseDetail,ReleaseDiff,Assignments}.vue`, `tests/integration/releases.test.ts`.

- [ ] Test owner/status/review filters, generated Codex/Claude diff, OS compatibility, policy impact, approval and rollback confirmation.
- [ ] Run UI tests; expect failure.
- [ ] Display source commit, manifest digest, file hashes, approvals, compatibility, rollback reference and active consumers.
- [ ] Require the server-generated before/after diff to be acknowledged before activation.
- [ ] Run Chrome desktop/mobile acceptance.
- [ ] Commit `[ICP S06/PR07/T02] Deliver governed release management`.

### PR S06-PR07 review gate

- [ ] Attempt validation failure, insufficient approval, same-person dual approval and stale activation.
- [ ] Activate a fixture release, pin a running job, then roll consumers back; job must retain original release.
- [ ] Confirm unowned/expired/revoked assets are visible and cannot publish.
- [ ] Record activation/rollback audit chain.

## S06-PR08 — Expose jobs, audit, usage, cost and system health

**Branch:** `codex/icp-s06-pr08-operations-views`

**Traceability:** `UI-16..23`, `OPS-03..08`, `SEC-26`.

**Acceptance:** Users see only permitted jobs/results; administrators can reconstruct events and monitor budgets, queue, source freshness, drift and shared-hosting pressure without viewing raw sensitive bodies.

### Task S06-PR08-T01 — Add permission-filtered operational APIs

**Files:** `gateway/database/009_usage_health.sql`, `gateway/src/Operations/OperationsApiController.php`, `gateway/tests/Feature/Dashboard/OperationsApiTest.php`.

- [ ] Test own/assigned/all job scopes, attempt/events, PR result, audit filters, cost aggregation, health/freshness and restricted-body redaction.
- [ ] Run focused tests; expect failure.
- [ ] Add `/api/jobs`, `/api/audit`, `/api/usage`, `/api/costs`, `/api/health`, `/api/freshness`, `/api/drift` with paginated stable cursors.
- [ ] Aggregate provider alias, tokens/cost/latency/outcome; omit full prompts/responses by default.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S06/PR08/T01] Expose safe operational telemetry`.

### Task S06-PR08-T02 — Build operational views and live state updates

**Files:** `gateway/ui/src/views/{Jobs,JobDetail,Audit,Costs,Health,Drift}.vue`, `tests/integration/operations.test.ts`.

- [ ] Test job timeline, attempts, cancel/approval actions, audit pagination, cost dimensions, queue/health thresholds, freshness and drift remediation.
- [ ] Run UI tests; expect failure.
- [ ] Poll bounded JSON endpoints with ETags; do not require WebSockets on SiteGround.
- [ ] Show current/degraded/offline labels and data timestamp on every operational panel.
- [ ] Run Chrome acceptance with founder/developer fixtures and narrow viewport.
- [ ] Commit `[ICP S06/PR08/T02] Deliver jobs audit cost and health views`.

### PR S06-PR08 review gate

- [ ] Attempt cross-user/job/audit access through direct APIs.
- [ ] Confirm raw sensitive bodies and secrets never appear in browser payloads.
- [ ] Load-test paginated views and polling within SiteGround limits.
- [ ] Record Chrome screenshots, accessibility results and API policy evidence.

## Section S06 Completion Gate

- [ ] All eight PRs are merged with valid evidence.
- [ ] Founders and developers use one authenticated role-scoped dashboard.
- [ ] Access preview/apply/expiry/revocation and final-founder/self-elevation controls pass.
- [ ] Onboarding, role change and offboarding fixture drills complete with evidence.
- [ ] Release activation/rollback and operational views are reconstructable from audit.
- [ ] All browser acceptance used installed Google Chrome and passes desktop/mobile/accessibility checks.
