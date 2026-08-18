# Fynla Control Plane S04 Worker and Coding Execution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Execute approved task envelopes with native Codex or Claude Code in isolated GitHub Actions jobs and deliver only a validated patch and tested draft pull request.

**Architecture:** A provider-neutral Python runner claims a leased job from the gateway, verifies its pinned release and attestation, prepares a clean checkout, then invokes one native adapter. The model job has repository read permission and no push credential; it emits a patch/result artifact. A separate deterministic publisher job validates the patch, runs required tests, pushes one generated branch and opens a draft PR.

**Tech Stack:** Python 3.12, `httpx`, Pydantic, pytest, GitHub Actions, `openai/codex-action@v1`, `anthropics/claude-code-action@v1`, Git, GitHub CLI.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Depends on S01 releases, S02 envelopes/leases/callbacks, and S05 adapter outputs for full activation.
- Repository: `Fynla/FynlaMCP`; fixture work uses dedicated non-production repositories.
- Provider/model keys are scoped to the agent action and never job-level environment variables.
- The generation job has `contents: read`, `persist-credentials: false` and no PR write permission.
- The publisher has no provider key and cannot target a protected branch.
- Never interpolate Slack text into shell, workflow YAML, branch names, paths or CLI flags.
- Each adapter consumes the same envelope/event/result contract but may use native prompts and settings.

---

## File Structure

```text
src/fynla_agent/
├── gateway/{client,models}.py
├── worker/{runner,handlers,bootstrap,safe_points}.py
├── adapters/{base,codex,claude}.py
├── git/{workspace,patch_validation,publisher}.py
└── results/{schema,normalise}.py
.github/workflows/
├── fynlamcp-worker.yml
├── fynlamcp-codex.yml
├── fynlamcp-claude.yml
└── fynlamcp-publish-draft-pr.yml
tests/{unit,integration,acceptance}/worker/
tests/fixtures/task-envelope.json
tests/fixtures/worker-result.schema.json
docs/implementation-evidence/s04/
```

## PR Register

| PR | Outcome | Depends on | State |
|---|---|---|---|
| S04-PR01 | Gateway-only worker client and current job contract | S02-PR05 | Not started |
| S04-PR02 | Pinned release, envelope and worker bootstrap | S01-PR03, S02-PR03, S04-PR01 | Not started |
| S04-PR03 | Native Codex execution adapter | S04-PR02 | Not started |
| S04-PR04 | Native Claude Code execution adapter | S04-PR02 | Not started |
| S04-PR05 | Patch validation, tests and draft PR publisher | S04-PR03, S04-PR04 | Not started |
| S04-PR06 | Cancellation, lease renewal and recovery | S04-PR05 | Not started |

## S04-PR01 — Make the worker gateway-only

**Branch:** `codex/icp-s04-pr01-gateway-worker`

**Traceability:** `JOB-21..24`, `ARC-03`, `SEC-14`.

**Acceptance:** The worker claims and completes leased attempts only through authenticated gateway APIs; current runtime and workflows contain no direct database client or credentials.

### Task S04-PR01-T01 — Upgrade gateway client models and lease methods

**Files:** `src/fynla_agent/gateway/models.py`, `client.py`, `tests/unit/worker/test_gateway_client.py`.

```python
class ClaimedAttempt(BaseModel):
    job_id: str
    attempt_id: str
    lease_token: SecretStr
    lease_expires_at: datetime
    envelope_url: AnyHttpUrl
    release_url: AnyHttpUrl

class GatewayClient(ABC):
    @abstractmethod
    def claim(self, job_id: str) -> ClaimedAttempt:
        raise NotImplementedError

    @abstractmethod
    def renew(self, attempt: ClaimedAttempt) -> ClaimedAttempt:
        raise NotImplementedError

    @abstractmethod
    def event(self, attempt: ClaimedAttempt, event: WorkerEvent) -> None:
        raise NotImplementedError

    @abstractmethod
    def complete(self, attempt: ClaimedAttempt, result: WorkerResult) -> None:
        raise NotImplementedError
```

- [ ] Test HMAC raw-byte signing, lease token placement, 409 lost lease, duplicate completion and redacted exceptions.
- [ ] Run `python -m pytest tests/unit/worker/test_gateway_client.py -q`; expect failure against `ClaimedJob`.
- [ ] Implement typed requests with finite connect/read timeouts and idempotency headers.
- [ ] Re-run the focused test; expect pass.
- [ ] Commit `[ICP S04/PR01/T01] Align workers with leased gateway jobs`.

### Task S04-PR01-T02 — Remove active direct-database runtime

**Files:** `pyproject.toml`, `.github/workflows/fynlamcp-worker.yml`, `.github/workflows/fynlamcp-recovery.yml`, `tests/architecture/test_no_worker_database.py`; remove active Alembic/PostgreSQL bootstrap paths after S03 migration.

- [ ] Add an architecture test rejecting runtime imports `sqlalchemy`, `psycopg`, `alembic`, environment names `DATABASE_URL`, and workflow database services.
- [ ] Run the test; expect failure and record all active violations.
- [ ] Route recovery and worker reads through gateway endpoints, then remove unused runtime dependencies and secrets.
- [ ] Preserve historical migrations only under `archive/postgresql-pilot/` with a non-runtime marker if needed for audit.
- [ ] Run architecture and existing worker tests; expect pass.
- [ ] Commit `[ICP S04/PR01/T02] Remove worker database access`.

### PR S04-PR01 review gate

- [ ] Search active code/workflows for database driver and `DATABASE_URL`; expected zero.
- [ ] Run a fake gateway end-to-end claim/event/complete sequence.
- [ ] Confirm lease secrets and callback signing secrets are redacted from logs.
- [ ] Record dependency removals and gateway contract fixtures.

## S04-PR02 — Bootstrap a verified bounded attempt

**Branch:** `codex/icp-s04-pr02-worker-bootstrap`

**Traceability:** `JOB-25..30`, `REG-18`, `SES-10`, `SEC-15`.

**Acceptance:** Before an agent starts, the worker verifies the envelope hash, release manifest/files, repository allowlist, base SHA, attestation, resource limits and adapter compatibility.

### Task S04-PR02-T01 — Verify envelope and release inputs

**Files:** `src/fynla_agent/worker/bootstrap.py`, `tests/unit/worker/test_bootstrap.py`, `tests/fixtures/task-envelope.json`.

```python
class WorkerBootstrap(BaseModel):
    job_id: str
    attempt_id: str
    envelope_sha256: str
    release_id: str
    release_manifest_sha256: str
    repository: str
    base_sha: str
    assistant: Literal["codex", "claude-code"]
    attestation_id: str
```

- [ ] Test tampered envelope, tampered release file, wrong repository, missing base SHA, expired attestation and incompatible adapter version.
- [ ] Run focused tests; expect failure.
- [ ] Implement canonical JSON hashing, release file verification and fail-closed compatibility checks.
- [ ] Emit `accepted` only after all checks pass; emit no model call on failure.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S04/PR02/T01] Verify every worker bootstrap input`.

### Task S04-PR02-T02 — Materialise safe prompt and structured result contracts

**Files:** `src/fynla_agent/results/schema.py`, `worker/prompt_builder.py`, `tests/security/worker/test_prompt_boundary.py`, `tests/fixtures/worker-result.schema.json`.

- [ ] Test Slack markup, YAML delimiters, shell substitutions, branch-like text, prompt injection and overlong body remain quoted task data.
- [ ] Run focused tests; expect failure.
- [ ] Build a prompt from trusted release instructions plus a labelled JSON envelope; never concatenate requester text into policy instructions.
- [ ] Define result fields `outcome`, `summary`, `changed_paths`, `test_commands`, `test_results`, `concerns`, `suggested_pr_title`, `usage` with strict additional-property rejection.
- [ ] Re-run focused tests; expect pass.
- [ ] Commit `[ICP S04/PR02/T02] Separate task data from worker policy`.

### PR S04-PR02 review gate

- [ ] Run all tamper and prompt-injection fixtures against both adapter selections.
- [ ] Confirm the worker uses exact repository/base SHA and does not accept request URLs.
- [ ] Confirm cost, turn and time limits are available before invocation.
- [ ] Record rejected-bootstrap cases with zero provider calls.

## S04-PR03 — Execute Codex through its native GitHub Action

**Branch:** `codex/icp-s04-pr03-codex-adapter`

**Traceability:** `JOB-31`, `SES-11`, `SEC-16`.

**Acceptance:** The Codex job uses the official GitHub Action, workspace-write sandbox, ephemeral structured output and a release-pinned Codex home; provider credentials are isolated from repository-controlled setup/test steps.

### Task S04-PR03-T01 — Implement the provider-neutral adapter contract

**Files:** `src/fynla_agent/adapters/base.py`, `codex.py`, `tests/unit/worker/test_codex_adapter.py`.

```python
class CodingAdapter(ABC):
    name: str

    @abstractmethod
    def prepare(self, context: AttemptContext) -> AdapterInvocation:
        raise NotImplementedError

    @abstractmethod
    def parse(self, output_path: Path) -> WorkerResult:
        raise NotImplementedError

class AdapterInvocation(BaseModel):
    prompt_file: Path
    output_schema_file: Path
    output_file: Path
    managed_home: Path
    timeout_seconds: int
```

- [ ] Test only envelope-permitted paths/actions/tests enter the invocation and the model alias resolves from the pinned release.
- [ ] Test output schema, malformed JSON, provider refusal, time/cost overrun and no-change outcome.
- [ ] Run focused tests; expect failure.
- [ ] Implement a pure Codex adapter that prepares files and parses output but does not invoke a shell.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S04/PR03/T01] Add the native Codex adapter contract`.

### Task S04-PR03-T02 — Add the locked-down Codex workflow

**Files:** `.github/workflows/fynlamcp-codex.yml`, `tests/architecture/test_codex_workflow.py`, `docs/action-locks.json`.

- [ ] Resolve the current `openai/codex-action@v1` commit using `gh api repos/openai/codex-action/git/ref/tags/v1 --jq '.object.sha'`; record the 40-character SHA in `docs/action-locks.json` and `uses:`.
- [ ] Add an architecture test requiring `permissions: contents: read`, `persist-credentials: false`, exact action SHA, `safety-strategy: drop-sudo`, `sandbox: workspace-write`, `codex-args` containing `--ephemeral` and `--output-schema`, and no job-level provider key.
- [ ] Run the architecture test before adding the workflow; expect failure.
- [ ] Add setup steps before the Codex action, then make Codex the final executable step in the model job and upload only patch/result artifacts from a separate no-key collection job.
- [ ] Run a fixture task and validate output against `worker-result.schema.json`.
- [ ] Commit `[ICP S04/PR03/T02] Run Codex with isolated credentials and output`.

### PR S04-PR03 review gate

- [ ] Review the implementation against official Codex GitHub Action and non-interactive security guidance.
- [ ] Prove repository setup/test processes cannot read the OpenAI key.
- [ ] Attempt disallowed network, out-of-workspace write, merge and push; each must fail.
- [ ] Save redacted workflow log, action SHA and patch artifact evidence.

## S04-PR04 — Execute Claude Code through its native GitHub Action

**Branch:** `codex/icp-s04-pr04-claude-adapter`

**Traceability:** `JOB-32`, `SES-12`, `SEC-17`.

**Acceptance:** The Claude job uses the official action in deterministic non-interactive mode with explicit settings/tools, structured output and a release-pinned Claude configuration; it satisfies the same result contract as Codex.

### Task S04-PR04-T01 — Implement and test the Claude adapter

**Files:** `src/fynla_agent/adapters/claude.py`, `tests/unit/worker/test_claude_adapter.py`, `tests/conformance/worker/test_adapter_parity.py`.

- [ ] Reuse `CodingAdapter`; test Claude-native settings, agents, plugin and MCP paths come only from the release.
- [ ] Add shared conformance cases for allowed edit, disallowed path, required test, no-change, refusal and malformed result.
- [ ] Run focused tests; expect failure.
- [ ] Implement preparation for `--bare`, `--permission-mode dontAsk`, explicit `--settings`, `--mcp-config`, `--plugin-dir`, `--output-format json` and bounded `--max-turns`.
- [ ] Implement result normalisation without changing common event vocabulary.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S04/PR04/T01] Add the native Claude Code adapter`.

### Task S04-PR04-T02 — Add the locked-down Claude workflow

**Files:** `.github/workflows/fynlamcp-claude.yml`, `tests/architecture/test_claude_workflow.py`, `docs/action-locks.json`.

- [ ] Resolve `anthropics/claude-code-action@v1` to a 40-character commit with GitHub API and add it to `docs/action-locks.json`.
- [ ] Add a failing architecture test requiring read-only GitHub permissions, no persisted checkout credential, exact action SHA, explicit `claude_args`, no `bypassPermissions`, and no job-level provider key.
- [ ] Add the generation workflow and patch/result artifact boundary matching the Codex path.
- [ ] Run the shared conformance fixture through the action; expect a schema-valid result and local patch only.
- [ ] Commit `[ICP S04/PR04/T02] Run Claude Code with isolated credentials and output`.

### PR S04-PR04 review gate

- [ ] Review against official Claude Code programmatic, permissions and GitHub Actions guidance.
- [ ] Attempt unapproved Bash, network, merge, push and outside-path writes; each must fail.
- [ ] Compare Codex/Claude result/event fields; expected same external contract.
- [ ] Save redacted logs, action SHA and patch artifact evidence.

## S04-PR05 — Validate patches and publish tested draft PRs

**Branch:** `codex/icp-s04-pr05-draft-pr-publisher`

**Traceability:** `JOB-33..40`, `SEC-18`.

**Acceptance:** A deterministic no-provider-key job validates the agent patch, runs envelope-required tests, pushes only a generated branch and creates/updates one draft PR; protected branches, workflow files and prohibited paths cannot be changed.

### Task S04-PR05-T01 — Validate and test an untrusted patch

**Files:** `src/fynla_agent/git/patch_validation.py`, `workspace.py`, `tests/security/worker/test_patch_validation.py`, `tests/acceptance/worker/test_patch_test_gate.py`.

- [ ] Test binary patch, symlink escape, submodule, `.github/workflows`, secrets, outside permitted paths, excessive files/bytes and base SHA mismatch.
- [ ] Run focused tests; expect failure.
- [ ] Apply with `git apply --check` then `git apply --index` in a fresh checkout at exact base SHA.
- [ ] Run only required test commands from the signed envelope via an argument-array allowlist, never `shell=True`.
- [ ] Reject dirty setup state and any changed path not declared by `git diff --name-only --cached`.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S04/PR05/T01] Validate and test generated patches`.

### Task S04-PR05-T02 — Publish one idempotent draft pull request

**Files:** `src/fynla_agent/git/publisher.py`, `.github/workflows/fynlamcp-publish-draft-pr.yml`, `tests/integration/worker/test_draft_pr_publisher.py`.

```python
def branch_name(job_id: str, attempt_id: str) -> str:
    return f"fynla-agent/{job_id.lower()}/{attempt_id.lower()}"
```

- [ ] Test branch derivation uses IDs only, rejects protected/base targets, and returns an existing PR for duplicate publish key.
- [ ] Run focused tests against a fixture GitHub repository; expect failure.
- [ ] Give the publisher job only `contents: write` and `pull-requests: write`; do not provide model, SiteGround DB or production secrets.
- [ ] Commit the verified patch, push only the derived branch, and create `draft=true` PR with assistant, release, tests, job link and concerns.
- [ ] Re-run the same publish request; expect the same branch/PR with no duplicate commit.
- [ ] Commit `[ICP S04/PR05/T02] Publish idempotent tested draft PRs`.

### PR S04-PR05 review gate

- [ ] Run malicious patch corpus and all adapter conformance fixtures.
- [ ] Prove failing tests prevent push and PR creation.
- [ ] Prove the publisher token cannot merge, administer or deploy.
- [ ] Confirm PR is always draft and base branch comes from signed repository policy.

## S04-PR06 — Honour cancellation, leases and recovery

**Branch:** `codex/icp-s04-pr06-worker-recovery`

**Traceability:** `JOB-41..47`, `OPS-02`, `SEC-19`.

**Acceptance:** Workers renew leases, poll cancellation at safe points, stop before subsequent mutation when access is revoked, and recover abandoned attempts without duplicating external actions.

### Task S04-PR06-T01 — Add safe points and lease heartbeat

**Files:** `src/fynla_agent/worker/safe_points.py`, `runner.py`, `tests/unit/worker/test_safe_points.py`.

- [ ] Test checks before provider call, after provider result, before tests, before artifact upload and before publish dispatch.
- [ ] Test lease loss, `cancel_requested`, expired attestation and revoked access produce distinct terminal/retryable results.
- [ ] Run focused tests; expect failure.
- [ ] Implement renewal at one-third lease duration and a gateway revalidation at each safe point.
- [ ] Never publish an artifact or request PR creation after cancellation/revocation.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S04/PR06/T01] Stop workers at authenticated safe points`.

### Task S04-PR06-T02 — Recover abandoned attempts without duplicate action

**Files:** `.github/workflows/fynlamcp-recovery.yml`, `src/fynla_agent/worker/recovery.py`, `tests/acceptance/worker/test_recovery.py`.

- [ ] Simulate abandonment during investigation, patch upload, tests, branch push and PR creation.
- [ ] Run the acceptance test; expect failure.
- [ ] Query recoverable attempts only through the gateway and create a linked retry when the previous side-effect receipt is absent.
- [ ] Reconcile existing artifact/branch/PR receipts before any repeated action.
- [ ] Run 20 repeated recovery scenarios; expect one branch, one PR and complete attempt history.
- [ ] Commit `[ICP S04/PR06/T02] Recover workers idempotently`.

### PR S04-PR06 review gate

- [ ] Run cancellation/recovery matrix for both adapters.
- [ ] Revoke access during each safe point and prove no later write occurs.
- [ ] Confirm Slack/dashboard events preserve original and replacement attempt IDs.
- [ ] Record recovery timing and duplicate-action count (`0`).

## Section S04 Completion Gate

- [ ] All six PRs are merged with valid evidence.
- [ ] Codex and Claude satisfy the shared conformance suite.
- [ ] Provider credentials cannot be read by repository-controlled code or publisher jobs.
- [ ] Automatic output stops at a passing, draft-only PR and cannot merge/deploy.
- [ ] Cancellation, access revocation, lease expiry and abandoned-worker drills produce no duplicate action.
- [ ] Official execution references are recorded: [Codex GitHub Action](https://learn.chatgpt.com/docs/github-action), [Codex non-interactive mode](https://learn.chatgpt.com/docs/non-interactive-mode), [Claude Code programmatic mode](https://code.claude.com/docs/en/headless), [Claude Code GitHub Actions](https://code.claude.com/docs/en/github-actions).
