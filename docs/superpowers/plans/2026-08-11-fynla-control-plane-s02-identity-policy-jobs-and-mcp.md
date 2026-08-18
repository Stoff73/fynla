# Fynla Control Plane S02 Identity Policy Jobs and MCP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the existing FynlaMCP PHP gateway into a deny-by-default control-plane core with linked identities, effective permissions, immutable envelopes, durable leased jobs, release attestations and a bounded MCP 2026-07-28 interface.

**Architecture:** PHP application services own policy and state transitions. MySQL/MariaDB repositories persist narrow records and append-only audit events. All entry points resolve one authenticated principal and call the same authorisation, proposal and job services before returning or dispatching work.

**Tech Stack:** PHP 8.2+, Symfony HttpFoundation/HttpClient, PDO MySQL, PHPUnit 11, MariaDB service in GitHub Actions, MCP JSON-RPC 2026-07-28.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Depends on S01-PR03 release schema; S01-PR04 is required before production activation.
- Repository: `Fynla/FynlaMCP`.
- Extend `gateway/database/001_control_plane.sql` only through new immutable numbered migrations.
- Do not retain SQLite as evidence for MySQL locking, FULLTEXT, collation or constraint behaviour.
- No endpoint may authorise from request-supplied role, repository or content scope.
- Every denial and mutation writes an audit event without secret or full prompt bodies.

---

## File Structure

```text
gateway/
├── database/
│   ├── 002_identity_access.sql
│   ├── 003_releases_sessions.sql
│   ├── 004_task_envelopes.sql
│   └── 005_job_leases_callbacks.sql
├── src/
│   ├── Identity/
│   ├── Access/
│   ├── Registry/
│   ├── Sessions/
│   ├── Tasks/
│   ├── Risk/
│   ├── Jobs/
│   ├── Worker/
│   ├── Mcp/
│   └── Audit/
├── tests/{Unit,Feature,Integration,Architecture}/
└── docs/implementation-evidence/s02/
```

## PR Register

| PR | Outcome | Depends on | State |
|---|---|---|---|
| S02-PR01 | Linked identities, roles and assignments | S01-PR01 | Not started |
| S02-PR02 | Effective-access policy engine | S02-PR01 | Not started |
| S02-PR03 | Release assignments and session attestations | S01-PR03, S02-PR02 | Not started |
| S02-PR04 | Task envelopes, risk and immutable approvals | S02-PR02, S02-PR03 | Not started |
| S02-PR05 | Leased job state, callbacks and cancellation | S02-PR04 | Not started |
| S02-PR06 | MCP 2026-07-28 and bounded tool catalogue | S02-PR05 | Not started |

## S02-PR01 — Persist linked identities and access assignments

**Branch:** `codex/icp-s02-pr01-identity-access`

**Traceability:** `IAM-01..05`, `SEC-04`.

**Acceptance:** People and service identities link to Google, Slack and GitHub IDs; roles, capabilities, resource assignments and expiring grants can be represented without hard-coded role ceilings.

### Task S02-PR01-T01 — Add the access schema on MariaDB

**Files:** `gateway/database/002_identity_access.sql`, `gateway/tests/Integration/Database/IdentityAccessMigrationTest.php`, `.github/workflows/ci.yml`.

Create separate tables: `people`, `external_identities`, `role_templates`, `permission_definitions`, `role_permissions`, `person_roles`, `resource_assignments`, `temporary_grants`, `service_identities`. Use `CHAR(36)` IDs, UTC `DATETIME(6)`, foreign keys, unique provider subject, explicit `revoked_at`, and indexes on active expiry/resource lookups.

- [ ] Add a migration test asserting every table, unique key, foreign key and index via `information_schema` against MariaDB.
- [ ] Run `./gateway/vendor/bin/phpunit gateway/tests/Integration/Database/IdentityAccessMigrationTest.php`; expect missing-table failure.
- [ ] Add the migration without editing `001_control_plane.sql`.
- [ ] Add a MariaDB 11 CI service and run migration tests with `DB_CONNECTION=mysql`; keep SQLite unit tests only where SQL semantics are irrelevant.
- [ ] Run the focused MariaDB test; expect pass.
- [ ] Commit `[ICP S02/PR01/T01] Add identity and access schema`.

### Task S02-PR01-T02 — Add strict identity repositories

**Files:** `gateway/src/Identity/Principal.php`, `IdentityRepository.php`, `PdoIdentityRepository.php`, `gateway/src/Access/AssignmentRepository.php`, `PdoAssignmentRepository.php`, `gateway/tests/Integration/Identity/PdoIdentityRepositoryTest.php`.

```php
final readonly class Principal {
    public function __construct(
        public string $personId,
        public string $kind,
        public array $externalIdentities,
        public bool $active,
    ) {}
}

interface IdentityRepository {
    public function findActiveByExternalSubject(string $provider, string $subject): ?Principal;
}
```

- [ ] Test provider-subject lookup, disabled-person rejection, revoked identity rejection and service-identity expiry.
- [ ] Run the focused PHPUnit file; expect class-not-found failures.
- [ ] Implement prepared-query repositories with no role or capability inference.
- [ ] Re-run tests; expect pass on MariaDB.
- [ ] Commit `[ICP S02/PR01/T02] Resolve active linked identities`.

### PR S02-PR01 review gate

- [ ] Apply migrations from an empty database and from a copy containing migration 001 data.
- [ ] Prove duplicate provider subjects and orphan assignments fail.
- [ ] Run PHP unit, integration and architecture suites plus `composer audit`.
- [ ] Record schema dump, migration timing and rollback procedure in the evidence record.

## S02-PR02 — Calculate effective access before data access

**Branch:** `codex/icp-s02-pr02-policy-engine`

**Traceability:** `IAM-06..12`, `SEC-05`, `KNW-01`.

**Acceptance:** One policy service combines active roles, repository/content assignments, deny rules and temporary grants; denial occurs before repository reads, search or tool construction.

### Task S02-PR02-T01 — Implement the policy decision model

**Files:** `gateway/src/Access/Capability.php`, `Resource.php`, `PolicyContext.php`, `PolicyDecision.php`, `PolicyEngine.php`, `gateway/tests/Unit/Access/PolicyEngineTest.php`.

```php
final readonly class PolicyDecision {
    public function __construct(
        public bool $allowed,
        public string $reasonCode,
        public array $matchedAssignmentIds,
    ) {}
}

interface PolicyEngine {
    public function decide(Principal $principal, string $capability, Resource $resource, DateTimeImmutable $at): PolicyDecision;
}
```

- [ ] Add a table-driven matrix for founder admin, lead, developer, product/design and service identity.
- [ ] Include explicit deny, absent assignment, expired grant, revoked role, cross-repository and self-elevation cases.
- [ ] Run `./gateway/vendor/bin/phpunit gateway/tests/Unit/Access/PolicyEngineTest.php`; expect failure.
- [ ] Implement deny-overrides-allow with stable reason codes and no role-name special cases.
- [ ] Re-run the focused test; expect pass.
- [ ] Commit `[ICP S02/PR02/T01] Evaluate deny-first effective access`.

### Task S02-PR02-T02 — Enforce policy before stores and audit decisions

**Files:** `gateway/src/Access/AuthorisedOperation.php`, `gateway/src/Audit/AuditWriter.php`, `gateway/tests/Architecture/PolicyBeforeRepositoryTest.php`, `gateway/tests/Feature/DeniedRequestAuditTest.php`.

- [ ] Add an architecture test requiring controllers and MCP handlers to call `AuthorisedOperation` rather than PDO repositories directly.
- [ ] Add a spy-store test proving a denied request performs zero protected repository calls.
- [ ] Run both tests; expect failures against existing direct handler/store use.
- [ ] Introduce the authorisation boundary and append audit fields `principal_id`, `capability`, `resource`, `decision`, `reason_code`, `correlation_id`.
- [ ] Re-run tests; expect pass and no full request body in audit rows.
- [ ] Commit `[ICP S02/PR02/T02] Enforce policy before protected reads`.

### PR S02-PR02 review gate

- [ ] Run the full permission matrix on MariaDB.
- [ ] Mutation-test allow/deny branches or manually invert each branch and prove tests fail.
- [ ] Confirm founder identity does not automatically broaden worker resource scope.
- [ ] Security reviewer signs off pre-retrieval and pre-tool filtering.

## S02-PR03 — Assign releases and attest sessions

**Branch:** `codex/icp-s02-pr03-releases-attestations`

**Traceability:** `REG-13..17`, `SES-05..09`, `SEC-06`.

**Acceptance:** Validated releases are imported by digest, assigned atomically per consumer, rolled back safely, and used to issue short-lived revocable session attestations.

### Task S02-PR03-T01 — Persist releases and consumer assignments

**Files:** `gateway/database/003_releases_sessions.sql`, `gateway/src/Registry/Release.php`, `ReleaseRepository.php`, `PdoReleaseRepository.php`, `gateway/tests/Integration/Registry/ReleaseActivationTest.php`.

- [ ] Test import idempotency by manifest digest, rejection of a mismatched file hash and one active assignment per consumer.
- [ ] Test atomic activation keeps the previous assignment when validation fails.
- [ ] Run the focused test; expect missing schema failure.
- [ ] Add `registry_releases`, `release_files`, `consumer_release_assignments`, `release_activations` with immutable manifest fields.
- [ ] Implement import, assign and rollback in transactions using compare-and-swap on prior release ID.
- [ ] Re-run the focused test; expect pass.
- [ ] Commit `[ICP S02/PR03/T01] Persist and assign validated releases`.

### Task S02-PR03-T02 — Issue and revoke attestations

**Files:** `gateway/src/Sessions/SessionAttestation.php`, `SessionAttestationService.php`, `gateway/tests/Unit/Sessions/SessionAttestationServiceTest.php`, `gateway/tests/Feature/SessionAttestationApiTest.php`.

```php
final readonly class SessionAttestation {
    public function __construct(
        public string $id,
        public string $principalId,
        public string $repository,
        public string $assistant,
        public string $machineId,
        public string $releaseId,
        public DateTimeImmutable $expiresAt,
    ) {}
}
```

- [ ] Test eight-hour default expiry, repository/assistant/machine binding, security invalidation and access-revocation invalidation.
- [ ] Run focused tests; expect failure.
- [ ] Implement issue/validate/revoke APIs returning opaque bearer material only once and storing its hash.
- [ ] Ensure expired or mismatched attestations return stable denial codes.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S02/PR03/T02] Bind sessions to effective releases`.

### PR S02-PR03 review gate

- [ ] Verify release assignment, rollback and attestation revocation under concurrent requests.
- [ ] Confirm active jobs retain their pinned release after a new activation.
- [ ] Confirm raw attestation tokens never appear in logs or database.
- [ ] Record rollback and revocation timings.

## S02-PR04 — Normalise tasks and freeze approval payloads

**Branch:** `codex/icp-s02-pr04-task-risk-approval`

**Traceability:** `JOB-01..09`, `SEC-07`.

**Acceptance:** Natural-language requests become validated assistant-neutral envelopes; deterministic safety rules classify green/amber/red; approvals bind to an immutable payload hash and expiry.

### Task S02-PR04-T01 — Define and persist task envelopes

**Files:** `gateway/database/004_task_envelopes.sql`, `gateway/src/Tasks/TaskEnvelope.php`, `TaskEnvelopeFactory.php`, `gateway/tests/Unit/Tasks/TaskEnvelopeFactoryTest.php`.

```php
final readonly class TaskEnvelope {
    public function __construct(
        public string $id,
        public string $requesterId,
        public string $source,
        public string $repository,
        public array $permittedPaths,
        public string $task,
        public array $acceptanceCriteria,
        public array $allowedActions,
        public array $prohibitedActions,
        public array $requiredTests,
        public string $releaseId,
        public string $risk,
        public string $idempotencyKey,
    ) {}
}
```

- [ ] Test derivation from principal, repository assignment and release; request-supplied permissions must be ignored.
- [ ] Test missing repository, acceptance, known test path or material scope produces clarification, not defaults.
- [ ] Run focused tests; expect failure.
- [ ] Add immutable `task_envelopes` and child tables/JSON fields with canonical JSON hash.
- [ ] Implement the factory and persistence; prohibit secret-bearing and customer-data scope.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S02/PR04/T01] Create immutable task envelopes`.

### Task S02-PR04-T02 — Classify risk and bind approvals

**Files:** `gateway/src/Risk/RiskClassifier.php`, `gateway/src/Tasks/ApprovalService.php`, `gateway/tests/Unit/Risk/RiskClassifierTest.php`, `gateway/tests/Integration/Tasks/ApprovalServiceTest.php`.

- [ ] Add high-risk fixtures for finance, auth, privacy, billing, migration, dependencies, infrastructure, multi-repository, credentials, merge, deploy and destructive data.
- [ ] Assert zero high-risk fixtures are green; unknown or ambiguous work is amber.
- [ ] Run focused tests; expect failure.
- [ ] Implement deterministic hard rules before any model-assisted suggestion; red rules cannot be downgraded by a model score.
- [ ] Store approval against `envelope_sha256`, approver capability, decision, reason and expiry; any envelope revision invalidates it.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S02/PR04/T02] Gate mutation with immutable risk approval`.

### PR S02-PR04 review gate

- [ ] Run the approved high-risk corpus and publish confusion-matrix evidence.
- [ ] Prove modified acceptance, path, assistant or allowed action invalidates approval.
- [ ] Confirm green still prohibits merge, deploy, secrets and production data.
- [ ] Obtain security and founder review of classification policy.

## S02-PR05 — Add durable leases, callbacks and safe cancellation

**Branch:** `codex/icp-s02-pr05-job-lifecycle`

**Traceability:** `JOB-10..20`, `SEC-08`.

**Acceptance:** Jobs transition through one validated state machine, workers claim short leases, callbacks are signed and replay-safe, cancellation occurs at safe boundaries, and retry preserves prior attempts.

### Task S02-PR05-T01 — Upgrade job schema and state machine

**Files:** `gateway/database/005_job_leases_callbacks.sql`, `gateway/src/Jobs/JobState.php`, `JobStateMachine.php`, `PdoJobStore.php`, `gateway/tests/Unit/Jobs/JobStateMachineTest.php`, `gateway/tests/Integration/Jobs/JobLeaseTest.php`.

Allowed external states: `queued`, `claimed`, `running`, `investigating`, `implementing`, `validating`, `needs_approval`, `draft_pr_ready`, `failed`, `cancel_requested`, `cancelled`, `timed_out`.

- [ ] Test every allowed and prohibited transition as a data provider.
- [ ] Test two concurrent claims yield one lease holder, expired lease recovery creates a linked attempt, and old evidence remains immutable.
- [ ] Run focused tests; expect failure against the current state vocabulary.
- [ ] Add lease owner/token/expiry, attempt linkage and transition rows; implement compare-and-swap transitions.
- [ ] Re-run tests on MariaDB; expect pass.
- [ ] Commit `[ICP S02/PR05/T01] Enforce leased job transitions`.

### Task S02-PR05-T02 — Verify callbacks and cancellation

**Files:** `gateway/src/Worker/CallbackAuthenticator.php`, `CallbackController.php`, `gateway/src/Jobs/CancellationService.php`, `gateway/tests/Feature/WorkerCallbackTest.php`, `gateway/tests/Integration/Jobs/CancellationTest.php`.

- [ ] Test HMAC, timestamp skew, nonce replay, payload job/attempt mismatch and duplicate terminal callback.
- [ ] Test cancel request, worker acknowledgement, timeout and access-revoked cancellation.
- [ ] Run focused tests; expect failures.
- [ ] Implement `POST /callbacks/github` against the raw body with a five-minute replay window and idempotent prior-result response.
- [ ] Implement cancellation as a state request; never kill mid-write without adapter safe-point acknowledgement.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S02/PR05/T02] Make callbacks and cancellation replay-safe`.

### PR S02-PR05 review gate

- [ ] Run concurrency, duplicate dispatch, duplicate callback and abandoned-worker tests 20 times.
- [ ] Confirm no retry erases or overwrites an attempt.
- [ ] Confirm cancellation cannot transition a terminal job.
- [ ] Record state-transition coverage and recovery evidence.

## S02-PR06 — Upgrade the bounded MCP surface

**Branch:** `codex/icp-s02-pr06-mcp-2026-07-28`

**Traceability:** `MCP-01..12`, `ARC-01`, `SEC-09`.

**Acceptance:** `/mcp` negotiates MCP 2026-07-28, exposes only approved typed tools, applies the common identity/policy/job services and returns job IDs for long operations.

### Task S02-PR06-T01 — Implement protocol negotiation and errors

**Files:** `gateway/src/Mcp/Protocol.php`, `JsonRpcController.php`, `gateway/tests/Feature/McpProtocolTest.php`.

- [ ] Test `initialize`, version negotiation, request IDs, notifications, invalid JSON, invalid params, unsupported version and authenticated stateless calls.
- [ ] Run the focused test; expect failures against the existing gateway assumptions.
- [ ] Implement protocol version `2026-07-28` with an explicit compatibility allowlist; do not silently accept unknown revisions.
- [ ] Return JSON-RPC errors without exception details or secrets.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S02/PR06/T01] Negotiate MCP 2026-07-28`.

### Task S02-PR06-T02 — Publish bounded tools through common policy

**Files:** `gateway/src/Mcp/ToolCatalog.php`, `ControlPlaneToolHandler.php`, `gateway/tests/Feature/McpToolContractTest.php`, `gateway/tests/Architecture/NoRawCapabilityToolsTest.php`.

The exact pilot names are `get_active_release`, `get_effective_configuration`, `get_sync_status`, `get_asset`, `list_available_assets`, `search_company_knowledge`, `fetch_canonical_source`, `get_source_freshness`, `begin_session_preflight`, `submit_coding_task`, `get_job_status`, `cancel_job`, `get_pull_request_result`, `create_action_proposal`, `get_proposal_status`.

- [ ] Snapshot tool names and JSON Schemas; fail on undeclared additions.
- [ ] Test each tool denies an unauthorised principal before calling its service.
- [ ] Add an architecture test rejecting tools or parameters that provide raw SQL, shell, arbitrary HTTP, merge, deploy, permission change or deletion.
- [ ] Route mutating tools through the envelope, risk, approval and job services; long calls return `{job_id, status}`.
- [ ] Run focused and architecture tests; expect pass.
- [ ] Commit `[ICP S02/PR06/T02] Expose policy-bounded MCP tools`.

### PR S02-PR06 review gate

- [ ] Run full PHP tests, schema snapshots, MariaDB integration and `composer audit`.
- [ ] Exercise every MCP tool as founder, developer, unassigned user and expired service identity.
- [ ] Confirm tool discovery itself does not reveal inaccessible resource names.
- [ ] Capture request/response contract fixtures without credentials.

## Section S02 Completion Gate

- [ ] All six PRs are merged with valid evidence.
- [ ] Permission filtering occurs before protected reads and tool exposure in tests and architecture rules.
- [ ] Release rollback, attestation invalidation, lease recovery and callback replay drills pass.
- [ ] The full high-risk corpus has zero amber/red auto-executable outcomes.
- [ ] `/mcp`, `/callbacks/github` and `/health` expose no secret detail.
- [ ] S03, S04, S06 and S07 can consume the services without direct database access.
