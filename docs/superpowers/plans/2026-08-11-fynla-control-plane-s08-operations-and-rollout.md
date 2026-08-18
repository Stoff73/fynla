# Fynla Control Plane S08 Operations and Rollout Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Operate the integrated control plane safely on the existing SiteGround subscription, prove backup/recovery/security/failure behaviour, and enable capabilities through reversible evidence-gated rollout phases.

**Architecture:** Immutable PHP/UI artifacts deploy to versioned SiteGround release directories with a current symlink and forward-only SQL migrations. Cron and GitHub Actions provide dispatch/recovery/backup/monitoring. Operational feature switches and budgets are stored separately from canonical policy but bounded by it. A central evidence verifier calculates PR/section/programme completion from child records.

**Tech Stack:** SiteGround PHP/CLI/cron, MariaDB, GitHub Actions, Bash/PowerShell where platform-specific, PHP backup tools with OpenSSL envelope encryption, Google Drive API restricted backup folder, PHPUnit, pytest, Vitest, installed Google Chrome.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Repository: principally `Fynla/FynlaMCP`; final traceability tooling lives in `Fynla/fynla-control`.
- Shared SiteGround subscription, but dedicated subdomain/document root/releases/database/credentials/secrets/cache rules/health checks/rate limits are mandatory.
- Deployments never run model/coding jobs inside web requests.
- Database backups are encrypted before leaving SiteGround; the decryption key is never stored on SiteGround.
- Rollout switches default off and a later phase cannot weaken an earlier safety gate.
- Browser acceptance uses only the user's installed Google Chrome.
- Programme/section/PR completion is derived from validated child evidence; no manual parent checkbox can override a failed child.

---

## File Structure

```text
FynlaMCP/
├── deploy/siteground/{build,deploy,rollback,health-check}.sh
├── gateway/bin/{migrate,backup,restore-drill,retention,health-snapshot}.php
├── gateway/src/Operations/{FeatureSwitchService,BudgetService,HealthService,BackupService}.php
├── gateway/database/{012_operations.sql,013_audit_retention.sql}
├── .github/workflows/{deploy-siteground,external-monitor,backup-audit,failure-drills}.yml
├── docs/runbooks/{deploy,rollback,backup-restore,incident,hosting-migration}.md
├── tests/{acceptance,security,integration}/operations/
└── docs/implementation-evidence/s08/

fynla-control/
├── tools/verify_programme_evidence.py
├── schemas/programme-evidence.schema.json
└── tests/conformance/test_programme_traceability.py
```

## PR Register

| PR | Outcome | Depends on | State |
|---|---|---|---|
| S08-PR01 | Dedicated SiteGround deployment and migration safety | S02-PR01, S06-PR01 | Not started |
| S08-PR02 | Security hardening, retention, backups and restore | S08-PR01 | Not started |
| S08-PR03 | Monitoring, budgets and independent kill switches | S03-PR05, S04-PR06, S07-PR05, S08-PR01 | Not started |
| S08-PR04 | End-to-end acceptance and failure drills | S05-PR05, S06-PR08, S07-PR06, S08-PR03 | Not started |
| S08-PR05 | Staged rollout and verified programme closure | S08-PR04 | Not started |

## S08-PR01 — Make SiteGround deployment isolated and reversible

**Branch:** `codex/icp-s08-pr01-siteground-deployment`

**Traceability:** `ARC-04..10`, `OPS-12..17`, `SEC-31`.

**Acceptance:** One artifact deploys to a dedicated `mcp.fynla.org` release directory, validates dedicated database/environment boundaries, migrates safely, switches atomically, and rolls back application code without exposing secrets or disturbing the customer site.

### Task S08-PR01-T01 — Build a reproducible secret-free deployment artifact

**Files:** `deploy/siteground/build.sh`, `tests/security/operations/test_deployment_artifact.py`, `.github/workflows/deploy-siteground.yml`.

- [ ] Add artifact tests rejecting `.env`, `.git`, tests/fixtures, logs, auth files, provider keys, source maps and developer paths while requiring PHP vendor and dashboard hashed assets.
- [ ] Run focused pytest; expect failure against the current artifact contents.
- [ ] Build from clean commit, run Composer production install and UI build, create sorted archive and SHA-256 manifest.
- [ ] Pin third-party GitHub Actions to reviewed 40-character SHAs and set minimal workflow permissions.
- [ ] Re-run artifact tests twice; expect equal manifest hashes.
- [ ] Commit `[ICP S08/PR01/T01] Build reproducible SiteGround artifacts`.

### Task S08-PR01-T02 — Validate dedicated environment and migrate before activation

**Files:** `deploy/siteground/deploy.sh`, `gateway/bin/preflight.php`, `gateway/bin/migrate.php`, `tests/acceptance/operations/test_siteground_deploy.py`.

- [ ] Test wrong document root, shared customer DB name/user, missing secret, old PHP, unwritable release dir, migration checksum change, failed health and concurrent deploy lock.
- [ ] Run acceptance against a disposable SiteGround-like fixture; expect failure.
- [ ] Upload to the literal directory `releases/${GITHUB_SHA}`, verify manifest, run preflight, acquire deploy lock, back up schema metadata, apply immutable migrations, warm config and call authenticated dependency health.
- [ ] Switch `current` symlink only after checks pass; retain prior release pointer and never log environment values.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S08/PR01/T02] Activate SiteGround releases atomically`.

### Task S08-PR01-T03 — Roll back code and document migration recovery

**Files:** `deploy/siteground/rollback.sh`, `docs/runbooks/deploy.md`, `rollback.md`, `tests/acceptance/operations/test_siteground_rollback.py`.

- [ ] Test application rollback to prior artifact, failed new-release health, incompatible forward schema and restore-from-backup decision.
- [ ] Run focused tests; expect failure.
- [ ] Implement symlink rollback plus health verification; SQL migrations remain forward-only and each migration documents compatible rollback or restore requirement.
- [ ] Ensure rollback never targets the Fynla customer application document root/release directories.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S08/PR01/T03] Roll back control-plane releases safely`.

### PR S08-PR01 review gate

- [ ] Deploy and roll back staging twice from clean artifacts.
- [ ] Confirm separate DB/database user/document root/environment and cache exclusion.
- [ ] Verify customer-site URL/content/hash is unchanged across deployment.
- [ ] Record artifact hash, migration ledger, health and rollback duration.

## S08-PR02 — Harden data handling and prove encrypted recovery

**Branch:** `codex/icp-s08-pr02-security-backup`

**Traceability:** `SEC-32..43`, `OPS-18..23`.

**Acceptance:** Secrets are independently revocable, audit is append-only, retention jobs remove only eligible sensitive context, encrypted backups run at least every six hours and monthly restore drills meet RPO/RTO.

### Task S08-PR02-T01 — Harden secrets, headers, logs and audit retention

**Files:** `gateway/database/013_audit_retention.sql`, `gateway/src/Operations/RetentionService.php`, `gateway/src/Http/SecurityHeaders.php`, `gateway/tests/Security/Operations/SecurityHardeningTest.php`.

- [ ] Test CSP, HSTS, frame/type/referrer policies, cookie flags, rate limits, credential rotation version and audit insert-only database grants.
- [ ] Test 30-day operational log/job-context expiry by classification and seven-year action-audit preservation.
- [ ] Run focused tests; expect failure.
- [ ] Add redaction at structured-log creation, append-only audit DB role/grants and retention tombstones/receipts.
- [ ] Make temporary content tracing an audited administrator grant expiring within 24 hours.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S08/PR02/T01] Enforce security and retention boundaries`.

### Task S08-PR02-T02 — Create encrypted off-site backup and retention jobs

**Files:** `gateway/bin/backup.php`, `gateway/src/Operations/BackupService.php`, `tests/integration/operations/BackupServiceTest.php`, `docs/runbooks/backup-restore.md`.

- [ ] Test consistent dump failure, encryption failure, upload failure, checksum mismatch, duplicate run and retention selection.
- [ ] Run focused tests; expect failure.
- [ ] Run local `mysqldump --single-transaction --routines --triggers --hex-blob` from a process argument array, stream to compression, encrypt to an offline-held X.509 public certificate using OpenSSL CMS/AES-256, then upload to one restricted Google Shared Drive backup folder.
- [ ] Store backup ID, source database fingerprint, schema migration, ciphertext SHA-256, size and receipt; never retain plaintext after verified encryption.
- [ ] Schedule every six hours; retain six-hourly copies 7 days, daily copies 30 days and monthly copies 12 months.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S08/PR02/T02] Back up the SQL control plane off-site`.

### Task S08-PR02-T03 — Automate a clean monthly restore drill

**Files:** `gateway/bin/restore-drill.php`, `.github/workflows/backup-audit.yml`, `tests/acceptance/operations/test_restore_drill.py`.

- [ ] Test decrypt checksum, empty target requirement, migration/schema validation, row-count/control totals, derived-index rebuild and application health.
- [ ] Run focused acceptance; expect failure.
- [ ] Restore the selected backup only into a disposable isolated database; never accept the production DB name as target.
- [ ] Verify operational records, rebuild knowledge chunks from fixture canonical sources, measure RPO/RTO, destroy credentials and retain signed drill evidence.
- [ ] Require RPO `<= 6h` and RTO `<= 4h`.
- [ ] Commit `[ICP S08/PR02/T03] Prove clean backup restoration`.

### PR S08-PR02 review gate

- [ ] Inspect SiteGround for zero plaintext dumps after successful/failed runs.
- [ ] Confirm the private decryption key is absent from SiteGround and the backup Drive service identity has folder-only scope.
- [ ] Complete a real staging restore and index rebuild within targets.
- [ ] Security reviewer signs off secrets, retention and audit grants.

## S08-PR03 — Monitor shared hosting, cost and independent feature controls

**Branch:** `codex/icp-s08-pr03-monitoring-controls`

**Traceability:** `OPS-24..38`, `SEC-44`.

**Acceptance:** Founders see resource/customer impact, queue, Slack/MCP, worker, model, drift and freshness telemetry; hard budgets and independent switches stop new risky work without disabling audit/results; migration triggers generate incidents.

### Task S08-PR03-T01 — Persist health snapshots and migration triggers

**Files:** `gateway/database/012_operations.sql`, `gateway/src/Operations/HealthService.php`, `gateway/bin/health-snapshot.php`, `gateway/tests/Integration/Operations/HealthServiceTest.php`.

- [ ] Test SiteGround resource samples, customer/control response/error, Slack ack, MCP latency, queue age, Actions usage, AI usage/cost, drift, freshness and release state.
- [ ] Test thresholds: resource >70%, control errors >1%, queue >10 minutes, Slack near 3 seconds, customer p95 degradation >10%.
- [ ] Run focused tests; expect failure.
- [ ] Store time-bucketed aggregates without raw sensitive bodies and emit one incident per threshold window.
- [ ] Mark cause as correlated/inferred unless direct attribution exists.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S08/PR03/T01] Detect shared-hosting pressure`.

### Task S08-PR03-T02 — Add external probes and alert delivery

**Files:** `.github/workflows/external-monitor.yml`, `gateway/tests/Feature/Operations/HealthEndpointTest.php`, `docs/runbooks/incident.md`.

- [ ] Test public `/health` exposes liveness/version only and authenticated detail includes dependencies with no secret values.
- [ ] Add five-minute GitHub-hosted HTTPS probes for control plane and customer site; record latency/status and post alert/recovery through signed gateway Slack notification when available.
- [ ] Configure founder GitHub workflow-failure email notifications as the independent fallback when Slack/control-plane delivery fails.
- [ ] Run outage, slow response, bad certificate and dependency degradation fixtures.
- [ ] Commit `[ICP S08/PR03/T02] Monitor the control plane externally`.

### Task S08-PR03-T03 — Enforce budgets and kill switches before dispatch

**Files:** `gateway/src/Operations/FeatureSwitchService.php`, `BudgetService.php`, `gateway/tests/Integration/Operations/FeatureControlTest.php`.

- [ ] Test independent switches for proactive Slack, green jobs, Codex, Claude, knowledge, new dispatch, repository and integration.
- [ ] Test daily/monthly/user/repository/assistant/workflow budgets, warning threshold, hard stop and in-flight safe-point behaviour.
- [ ] Run focused tests; expect failure.
- [ ] Check active switch/budget immediately before dispatch/provider call and audit actor/reason/expiry for changes.
- [ ] Keep dashboard/audit/existing-result reads available when automation is disabled.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S08/PR03/T03] Stop automation independently and on budget`.

### PR S08-PR03 review gate

- [ ] Trigger every migration threshold and verify one actionable incident.
- [ ] Disable each feature during queued/running work and verify documented boundary.
- [ ] Exhaust a fixture budget and prove non-critical dispatch stops before provider use.
- [ ] Record customer/control baseline and alert delivery evidence.

## S08-PR04 — Run complete acceptance and failure drills

**Branch:** `codex/icp-s08-pr04-e2e-failure-drills`

**Traceability:** `OPS-39..48`, all section acceptance requirements.

**Acceptance:** Automated and human acceptance proves authorisation, release/preflight, Slack, both coding adapters, dashboard, lifecycle, recovery, rollback and failure behaviour end-to-end in safe environments.

### Task S08-PR04-T01 — Build one deterministic end-to-end fixture environment

**Files:** `tests/acceptance/environment/`, `.github/workflows/failure-drills.yml`, `docs/runbooks/acceptance.md`.

- [ ] Provision fixture identities/roles/repos/content, test Slack channels, source documents, releases, Codex/Claude tasks and lifecycle cases with deterministic IDs.
- [ ] Add setup verifier that rejects production repository, customer DB, production Slack channel or production credential identifiers.
- [ ] Seed through public/admin test APIs, never direct production SQL.
- [ ] Run fixture setup twice; expect idempotent identical state.
- [ ] Commit `[ICP S08/PR04/T01] Create isolated programme acceptance fixtures`.

### Task S08-PR04-T02 — Automate cross-component acceptance

**Files:** `tests/acceptance/test_programme_paths.py`, `tests/acceptance/dashboard/chrome-checklist.md`.

- [ ] Test founder/developer/product access and forbidden cross-boundary retrieval.
- [ ] Test release activation, macOS/Windows/Linux compile, session drift correction and attestation.
- [ ] Test Slack answer, explicit Codex/Claude dispatch, steering/cancel and green-to-draft-PR.
- [ ] Test access change, onboarding, role change and offboarding with revocation.
- [ ] Use installed Google Chrome through the connector for dashboard desktop/mobile/keyboard/visual evidence; do not invoke Playwright Chromium.
- [ ] Record every job/release/access/citation/PR/audit identifier.
- [ ] Commit `[ICP S08/PR04/T02] Verify integrated founder and developer paths`.

### Task S08-PR04-T03 — Exercise every approved failure mode

**Files:** `tests/acceptance/test_failure_matrix.py`, `docs/runbooks/failure-matrix.md`.

- [ ] Simulate SiteGround unavailable, GitHub Actions unavailable/quota, selected/all model unavailable, degraded FULLTEXT, Slack unavailable, duplicate callback, abandoned worker, failed release, failed sync and mid-job access revocation.
- [ ] Assert the exact design §19.1 behaviour and no false success/duplicate action.
- [ ] Drill active-release rollback and database restore within targets.
- [ ] Measure Slack ack p95, knowledge recall@5, customer-site p95 impact and job queue age.
- [ ] Commit `[ICP S08/PR04/T03] Prove transparent failure and recovery`.

### PR S08-PR04 review gate

- [ ] All section completion gates pass from clean fixtures.
- [ ] Zero unauthorised disclosures/access, zero protected false-green outcomes and zero duplicate external actions.
- [ ] Chrome evidence is complete for all role/lifecycle/operations paths.
- [ ] Founder, security and engineering reviewers sign the acceptance record.

## S08-PR05 — Roll out in evidence-gated phases and close traceability

**Branch:** `codex/icp-s08-pr05-staged-rollout`

**Traceability:** `OPS-49..60`, programme definition of done.

**Acceptance:** Each rollout phase has an owner, switch, entry/exit evidence and rollback; additional developers are not onboarded until offboarding/recovery drills pass; a verifier proves every design requirement maps to merged PR/task evidence.

### Task S08-PR05-T01 — Verify the complete requirement/PR/task evidence graph

**Files:** `fynla-control/tools/verify_programme_evidence.py`, `schemas/programme-evidence.schema.json`, `tests/conformance/test_programme_traceability.py`.

- [ ] Add tests rejecting missing requirement mapping, orphan task, unchecked child with checked parent, missing command/review/PR/merge evidence, circular dependency and superseded-without-amendment PR.
- [ ] Run focused tests; expect failure.
- [ ] Parse the canonical programme/section manifests and evidence JSON exports; calculate PR, section and checkpoint state from children.
- [ ] Emit human Markdown plus machine JSON with zero inferred completions.
- [ ] Run verifier; every implemented item must have design → section → PR → task → evidence links.
- [ ] Commit `[ICP S08/PR05/T01] Enforce end-to-end programme traceability`.

### Task S08-PR05-T02 — Execute phases 1–5 with rollback after each

**Files:** `docs/runbooks/rollout.md`, `docs/implementation-evidence/s08/rollout-phases.json`.

- [ ] Phase 1 Foundation: activate registry, identity/policy/jobs/audit, dashboard skeleton, signed dispatch/callback; run rollback.
- [ ] Phase 2 Developer pilot: activate Core/Codex/Claude adapters and session-start for Chris macOS; compile Windows/Linux; run rollback.
- [ ] Phase 3 Slack assisted: activate allowlisted answers/explicit jobs with approval before mutation; run rollback.
- [ ] Phase 4 Shadow autonomy: collect 20 consecutive agreements with zero false-green protected cases; run rollback.
- [ ] Phase 5 Green automation: enable one bounded repository/category, prove tested draft-only output and kill switch; run rollback.
- [ ] Record owner, timestamps, metrics, deviations and reviewer sign-off for each phase.
- [ ] Commit `[ICP S08/PR05/T02] Complete the controlled pilot rollout`.

### Task S08-PR05-T03 — Execute phase 6 team rollout and closure review

**Files:** `docs/implementation-evidence/s08/team-rollout.json`, `release-notes/initial-team-rollout.md`.

- [ ] Re-run offboarding, access revocation, worker recovery, backup restore and release rollback drills immediately before team enablement.
- [ ] Enable proactive Slack only in selected channels and onboard one additional developer through the complete platform workflow.
- [ ] Validate their actual OS/discipline/assistant profile and first session attestation; do not reuse Chris's profile.
- [ ] Review SiteGround/customer-site/cost metrics over the agreed monitoring window and apply migration triggers if breached.
- [ ] Run `verify_programme_evidence.py`; expected zero missing/orphan/invalid records.
- [ ] Obtain two-founder, engineering and security closure approvals.
- [ ] Commit `[ICP S08/PR05/T03] Approve initial team rollout`.

### PR S08-PR05 review gate

- [ ] Every prior PR is merged or explicitly superseded by an approved programme amendment.
- [ ] All five programme checkpoints are calculated `Verified` from child evidence.
- [ ] Runbooks have named owners/review dates and all switches have tested rollback.
- [ ] Publish the final evidence report and initial-team release note.

## Section S08 Completion Gate

- [ ] All five PRs are merged with valid evidence.
- [ ] SiteGround deployment, SQL migration, code rollback and DB restore drills pass.
- [ ] Monitoring, budgets, alerts and each kill switch work independently.
- [ ] End-to-end and failure matrices meet all approved acceptance thresholds.
- [ ] Rollout phases 1–6 have founder/security/engineering evidence.
- [ ] The traceability verifier reports zero gaps from specification through checked task evidence.
