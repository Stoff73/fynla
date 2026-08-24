# Fynla Control Plane S05 Developer Sync and Session Start Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give every developer and worker the correct global, repository, assistant, OS, discipline, runtime and personal configuration, and enforce an automatic `session-start` preflight before Codex or Claude Code work begins.

**Architecture:** A signed cross-platform `fynla-sync` binary authenticates to the control plane, downloads a verified release/effective manifest, installs managed files atomically and records hashes. Assistant-native launchers run preflight before startup-loaded configuration is read; native `SessionStart` hooks provide a deterministic backstop and attestation context.

**Tech Stack:** Go 1.24 cross-compiled client, Python registry compiler from S01, PHP preflight API from S02, Codex `AGENTS.md`/`.codex/config.toml`/hooks/plugins, Claude `CLAUDE.md`/settings/MCP/skills/agents/hooks/plugin, GitHub Actions, Go test, pytest.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Depends on S01 compiler/releases and S02 release assignments/attestations.
- Registry/client work is in `Fynla/fynla-control`; product bootstrap is a separate PR in `Fynla/Fynla`.
- Automatic preflight is mandatory; manual `/session-start` and `$session-start` are recovery/diagnostic paths.
- Never reset, rebase, discard, stash, overwrite or force-update developer Git work automatically.
- Managed installation is atomic and retains the last known-good release.
- A hook that repairs startup-loaded files returns a restart-required result; it never claims the current process loaded new policy.
- Secret tokens use OS credential storage and are never written to repository or generated configuration files.

---

## File Structure

```text
fynla-control/
├── cmd/fynla-sync/main.go
├── internal/{api,auth,manifest,install,gitstate,preflight,launch,attestation}/
├── compiler/renderers/{codex.py,claude.py}
├── assistants/codex/{hooks,plugin,skills/session-start}/
├── assistants/claude-code/{hooks,plugin,skills/session-start}/
├── environments/{macos,windows,linux}/
├── disciplines/ developers/ repositories/
└── tests/{unit,conformance,security,golden}/

Fynla/
├── AGENTS.md
├── CLAUDE.md
├── .codex/config.toml
├── .claude/settings.json
├── scripts/fynla-control/{bootstrap.sh,bootstrap.ps1,verify-managed-files.sh}
└── .github/workflows/fynla-control-drift.yml
```

## PR Register

| PR | Repository | Outcome | Depends on | State |
|---|---|---|---|---|
| S05-PR01 | `fynla-control` | Cross-platform sync client and atomic installer | S01-PR03, S02-PR03 | Not started |
| S05-PR02 | `fynla-control` | Native Codex compilation and enforcement | S05-PR01 | Not started |
| S05-PR03 | `fynla-control` | Native Claude Code compilation and enforcement | S05-PR01 | Not started |
| S05-PR04 | `fynla-control` | OS, discipline, runtime and developer profiles | S05-PR02, S05-PR03 | Not started |
| S05-PR05 | `Fynla` | Repository bootstrap and drift CI | S05-PR04 | Not started |

## S05-PR01 — Build the cross-platform atomic sync client

**Branch:** `codex/icp-s05-pr01-sync-client`

**Traceability:** `SES-13..22`, `SEC-20`.

**Acceptance:** Signed binaries for macOS, Windows and Linux authenticate, fetch an assigned manifest, verify hashes, install in one atomic swap, preserve the prior release on failure and report drift without exposing tokens.

### Task S05-PR01-T01 — Define API, manifest and exit-code contracts

**Files:** `cmd/fynla-sync/main.go`, `internal/api/client.go`, `internal/manifest/manifest.go`, `internal/preflight/result.go`, `internal/api/client_test.go`.

```go
const (
    ExitReady           = 0
    ExitRestartRequired = 10
    ExitWarning         = 20
    ExitBlocked         = 30
    ExitOfflineDenied   = 40
)

type EffectiveManifest struct {
    ReleaseID string            `json:"release_id"`
    Files     map[string]string `json:"files"`
    ExpiresAt time.Time         `json:"expires_at"`
}
```

- [ ] Test authenticated release assignment, ETag/304, bad TLS, bad JSON, hash mismatch, expired assignment and redacted HTTP errors.
- [ ] Run `go test ./internal/api ./internal/manifest`; expect compile failures.
- [ ] Implement finite timeouts, platform user-agent, response-size limits and strict JSON decoding.
- [ ] Store refresh/enrolment secrets behind `CredentialStore` implementations for macOS Keychain, Windows Credential Manager and Linux Secret Service; test with an in-memory fake.
- [ ] Re-run focused tests; expect pass.
- [ ] Commit `[ICP S05/PR01/T01] Define secure sync client contracts`.

### Task S05-PR01-T02 — Install releases atomically and retain rollback

**Files:** `internal/install/installer.go`, `installer_test.go`, `internal/manifest/verify.go`, `tests/security/test_sync_paths.py`.

- [ ] Test traversal (`../`), absolute paths, symlink escape, duplicate destination, bad mode, interrupted download, disk-full simulation and hash mismatch.
- [ ] Run focused Go/security tests; expect failure.
- [ ] Download to a same-volume staging directory, verify every SHA-256, fsync files/manifest, rename current to previous, rename staging to current, and restore previous on failure.
- [ ] Install only under declared managed roots; preserve unmanaged files and write `installed-manifest.json` with release/hash/provenance.
- [ ] Re-run tests including injected failure at each atomic step; expect prior release remains usable.
- [ ] Commit `[ICP S05/PR01/T02] Install effective releases atomically`.

### Task S05-PR01-T03 — Cross-compile and verify release binaries

**Files:** `.github/workflows/fynla-sync-release.yml`, `internal/version/version.go`, `tests/conformance/test_binary_matrix.py`.

- [ ] Add a failing matrix test for `darwin/arm64`, `darwin/amd64`, `windows/amd64`, `windows/arm64`, `linux/amd64`, `linux/arm64` version and self-check output.
- [ ] Cross-compile with `CGO_ENABLED=0`, inject source commit/version, generate SHA-256 checksums and sign the checksum manifest with the approved GitHub release identity.
- [ ] Verify binary checksum before onboarding installer execution.
- [ ] Run `go test ./...` and matrix smoke tests; expect pass.
- [ ] Commit `[ICP S05/PR01/T03] Release verified cross-platform sync binaries`.

### PR S05-PR01 review gate

- [ ] Run Go race tests and all atomic-failure injections.
- [ ] Scan binaries/config output for enrolment token and fixture secrets.
- [ ] Verify signatures/checksums on each target platform fixture.
- [ ] Record rollback, offline and corrupt-download evidence.

## S05-PR02 — Compile and enforce native Codex configuration

**Branch:** `codex/icp-s05-pr02-codex-session-start`

**Traceability:** `SES-23..30`, `REG-19`.

**Acceptance:** The compiler emits portable Codex-native outputs and the managed launcher plus `SessionStart` hook blocks work without a valid release/attestation; `$session-start` runs the same diagnostic path.

### Task S05-PR02-T01 — Render Codex-native files deterministically

**Files:** `compiler/renderers/codex.py`, `assistants/codex/templates/AGENTS.md.j2`, `config.toml.j2`, `hooks.json.j2`, `tests/golden/codex/`, `tests/conformance/test_codex_renderer.py`.

- [ ] Add goldens for macOS, Windows and Linux including `AGENTS.md`, project `.codex/config.toml`, user profile config, hooks, plugin manifest and session-start skill.
- [ ] Run the renderer test; expect missing-renderer failure.
- [ ] Render paths through OS path types; use `commandWindows` for Windows hook commands and never emit `/Users/` or `C:\\Users\\` literals.
- [ ] Include release ID, managed header and hash in each generated repository file.
- [ ] Re-run twice and compare bytes; expect deterministic equality.
- [ ] Commit `[ICP S05/PR02/T01] Compile portable Codex configuration`.

### Task S05-PR02-T02 — Add Codex prelaunch and SessionStart enforcement

**Files:** `internal/launch/codex.go`, `assistants/codex/hooks/session-start.sh`, `session-start.ps1`, `assistants/codex/skills/session-start/SKILL.md`, `tests/conformance/test_codex_session_start.py`.

- [ ] Test `fynla-sync launch codex` completes sync before starting Codex and passes no secret arguments to the child process.
- [ ] Test Codex `SessionStart` invokes `fynla-sync preflight --assistant codex --hook`, injects release/policy summary on ready, and exits blocking on invalid attestation.
- [ ] Test hook-corrected startup files produce exit `10` and a single restart instruction.
- [ ] Implement `$session-start` skill as `fynla-sync preflight --assistant codex --manual`; it may diagnose/remediate but cannot bypass blocking policy.
- [ ] Test untrusted project config is detected and onboarding receives a specific trust action.
- [ ] Commit `[ICP S05/PR02/T02] Enforce Codex session preflight`.

### PR S05-PR02 review gate

- [ ] Test new, resumed, compacted, expired, revoked, drifted and offline sessions.
- [ ] Confirm startup-loaded drift always causes restart rather than false-ready.
- [ ] Confirm project `.codex` layers are active only for trusted repositories.
- [ ] Record Codex config/hook conformance evidence for three OS outputs.

## S05-PR03 — Compile and enforce native Claude Code configuration

**Branch:** `codex/icp-s05-pr03-claude-session-start`

**Traceability:** `SES-31..38`, `REG-20`.

**Acceptance:** The compiler emits portable Claude-native project/local files, skills, agents, MCP and plugin configuration; the launcher and native hook enforce the same preflight outcomes as Codex; `/session-start` remains diagnostic.

### Task S05-PR03-T01 — Render Claude Code files deterministically

**Files:** `compiler/renderers/claude.py`, `assistants/claude-code/templates/`, `tests/golden/claude-code/`, `tests/conformance/test_claude_renderer.py`.

- [ ] Add goldens for `CLAUDE.md`, shared `.claude/settings.json`, generated `.claude/settings.local.json`, `.mcp.json`, skills, agents, hooks and plugin metadata across three OSes.
- [ ] Run focused tests; expect failure.
- [ ] Keep shared repository settings separate from personal/local output and parameterise executable/home paths.
- [ ] Reject a lower layer that disables or replaces the mandatory session hook.
- [ ] Re-run deterministic golden tests; expect pass.
- [ ] Commit `[ICP S05/PR03/T01] Compile portable Claude Code configuration`.

### Task S05-PR03-T02 — Add Claude prelaunch and SessionStart enforcement

**Files:** `internal/launch/claude.go`, `assistants/claude-code/hooks/session-start.sh`, `session-start.ps1`, `assistants/claude-code/skills/session-start/SKILL.md`, `tests/conformance/test_claude_session_start.py`.

- [ ] Mirror the Codex scenario matrix using assistant value `claude-code`.
- [ ] Test `fynla-sync launch claude` preflights before process start and native `SessionStart` verifies the active attestation.
- [ ] Test `/session-start` calls the same binary/manual path and cannot mint an attestation for unassigned repository access.
- [ ] Remove hard-coded Chris paths from migrated Claude settings and hooks in the generated result.
- [ ] Run conformance tests; expect pass.
- [ ] Commit `[ICP S05/PR03/T02] Enforce Claude Code session preflight`.

### PR S05-PR03 review gate

- [ ] Run the same session outcome matrix as Codex.
- [ ] Compare common policy outcomes; expected identical allow/deny/attestation decisions.
- [ ] Confirm assistant-native differences exist only in renderer output and invocation.
- [ ] Record Claude hook/plugin/config evidence for three OS outputs.

## S05-PR04 — Compose OS, discipline, runtime and developer profiles

**Branch:** `codex/icp-s05-pr04-developer-profiles`

**Traceability:** `SES-39..47`, `IAM-14`.

**Acceptance:** A person can register multiple machines and compile distinct macOS/Windows/Linux plus backend/frontend/systems/design configurations without personal paths or preferences weakening policy.

### Task S05-PR04-T01 — Define portable machine and developer profile schemas

**Files:** `schemas/machine-profile.schema.json`, `schemas/developer-profile.schema.json`, `environments/*/profile.yaml`, `disciplines/*/profile.yaml`, `tests/conformance/test_profiles.py`.

- [ ] Test required OS/architecture, toolchain constraints, shell, path variables, assistant choices, discipline capabilities and allowlisted preferences.
- [ ] Test developer layer cannot add repository assignment, protected tool, secret, global allow or security override.
- [ ] Run focused tests; expect failure until schemas/profiles exist.
- [ ] Add base profiles for macOS, native Windows/PowerShell, Linux, backend, frontend, systems and design.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S05/PR04/T01] Define safe developer and machine profiles`.

### Task S05-PR04-T02 — Implement complete repository/environment preflight

**Files:** `internal/gitstate/check.go`, `internal/preflight/runner.go`, `internal/attestation/store.go`, `internal/preflight/runner_test.go`.

- [ ] Test clean-current, clean-behind, dirty, untracked, ahead, diverged, detached, wrong remote, missing runtime, incompatible assistant, MCP failure and control-plane outage.
- [ ] Run focused Go tests; expect failure.
- [ ] Permit `git fetch --prune`; permit `git merge --ff-only @{upstream}` only for a clean permitted branch that is strictly behind.
- [ ] Never run reset, clean, stash, checkout-discard or rebase; return warning/block with exact human action.
- [ ] Issue/cache eight-hour attestations, revalidate on identity/repository/assistant change, and allow offline work only with unexpired same-scope attestation.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S05/PR04/T02] Preflight repositories without risking work`.

### Task S05-PR04-T03 — Add Chris macOS and neutral Windows/Linux fixtures

**Files:** `developers/chris.yaml`, `tests/fixtures/developers/windows-developer.yaml`, `linux-developer.yaml`, `tests/golden/effective/`.

- [ ] Represent Chris's approved preferences and founder/full-stack discipline without absolute local paths or credentials.
- [ ] Compile Chris macOS Codex/Claude outputs and neutral Windows/Linux backend/frontend outputs.
- [ ] Test Windows outputs use Windows separators/PowerShell hooks and non-Windows outputs contain no drive letters.
- [ ] Review generated permissions to confirm personal preference changes do not broaden repository/action scope.
- [ ] Commit `[ICP S05/PR04/T03] Prove layered profiles across operating systems`.

### PR S05-PR04 review gate

- [ ] Run compiler, Go client and assistant conformance suites.
- [ ] Pilot both assistants on Chris's real macOS machine.
- [ ] Run Windows GitHub-hosted and Linux runner smoke tests.
- [ ] Record all effective-field provenance and no-broaden policy result.

## S05-PR05 — Install stable product-repository bootstrap and drift CI

**Branch:** `codex/icp-s05-pr05-fynla-bootstrap`

**Traceability:** `SES-48..55`, `REG-21`, `SEC-21`.

**Acceptance:** `Fynla/Fynla` contains only small managed bootstrap/enforcement files; CI detects unexplained direct edits; sync never touches ordinary release content or developer work; current repository instructions point to live preflight context.

### Task S05-PR05-T01 — Add generated bootstrap files in a dedicated product PR

**Files:** `AGENTS.md`, `CLAUDE.md`, `.codex/config.toml`, `.claude/settings.json`, `scripts/fynla-control/bootstrap.sh`, `bootstrap.ps1`, `managed-manifest.json`.

- [ ] Generate the repository files from the reviewed active registry release; do not hand-copy current SOPs/assets.
- [ ] Test each file has managed header, release ID, source asset ID and expected hash.
- [ ] Replace existing absolute Claude hook paths with variables/portable launchers while preserving unrelated user content outside managed blocks.
- [ ] Run current Fynla unit/static tests affected by instruction/config changes.
- [ ] Commit `[ICP S05/PR05/T01] Install Fynla control-plane bootstrap`.

### Task S05-PR05-T02 — Reject repository drift in CI

**Files:** `scripts/fynla-control/verify-managed-files.sh`, `.github/workflows/fynla-control-drift.yml`, `tests/Feature/ControlPlaneManagedFilesTest.php` or repository-equivalent test.

- [ ] Add a failing test that modifies one managed byte and expects verifier exit `1` with source asset/release remediation.
- [ ] Add verifier comparing declared hashes and release ID; never rewrite files in CI.
- [ ] Add workflow with read-only contents permission and no control-plane secret.
- [ ] Test a normal content release does not require a product PR when bootstrap hashes are unchanged.
- [ ] Re-run drift and existing CI; expect pass.
- [ ] Commit `[ICP S05/PR05/T02] Detect Fynla bootstrap drift`.

### PR S05-PR05 review gate

- [ ] Confirm no unrelated `Fynla` application file changed.
- [ ] Start Codex and Claude through managed launchers in the repository and capture preflight attestation evidence.
- [ ] Create dirty/unpushed work and prove preflight does not change it.
- [ ] Directly edit a managed file and prove CI fails with precise remediation.

## Section S05 Completion Gate

- [ ] All five PRs are merged in their named repositories with linked evidence.
- [ ] macOS, Windows and Linux compile/install/preflight suites pass.
- [ ] Codex and Claude new/resumed/offline/revoked/drifted session matrices pass.
- [ ] Chris's real macOS pilot succeeds for both assistants.
- [ ] No hard-coded developer paths or tokens appear in releases or product bootstrap.
- [ ] Dirty, ahead and diverged Git states are never modified automatically.
