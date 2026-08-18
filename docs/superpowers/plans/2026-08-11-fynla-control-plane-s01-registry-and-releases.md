# Fynla Control Plane S01 Registry and Releases Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create `Fynla/fynla-control` as the sole canonical, schema-validated source for policies, SOPs, assistant assets and deterministic immutable releases.

**Architecture:** Human-readable YAML/Markdown assets are validated and compiled by a small Python package. Protected global fields use deny-preserving merge rules. A release bundle contains assistant-native outputs, provenance and hashes derived from one Git commit; runtime assignment and activation are implemented in S02.

**Tech Stack:** Python 3.12, Pydantic 2, PyYAML, JSON Schema, pytest, Ruff, GitHub Actions, SHA-256.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Requirements: `REG-01..12`, `SES-01..04`, `SEC-01..03` from design §§6–8 and §15.
- Repository: new private `Fynla/fynla-control`.
- The repository contains secret references only, never secret values.
- Global denials and protected fields can be narrowed but never weakened.
- Generated output must be byte-for-byte reproducible for the same source commit and compiler version.

---

## File Structure

```text
fynla-control/
├── pyproject.toml
├── src/fynla_control/
│   ├── models.py
│   ├── schema_loader.py
│   ├── merge.py
│   ├── compiler.py
│   ├── validation.py
│   ├── release.py
│   └── cli.py
├── schemas/
│   ├── asset.schema.json
│   ├── layer.schema.json
│   ├── role.schema.json
│   └── release.schema.json
├── core/
├── assistants/{codex,claude-code}/
├── agents/ prompts/ skills/ hooks/ workflows/
├── environments/{macos,windows,linux}/
├── disciplines/{backend,frontend,systems,design}/
├── repositories/ developers/ roles/ policies/
├── tests/{unit,conformance,security,golden}/
├── release-notes/
├── docs/implementation-plans/
└── docs/implementation-evidence/s01/
```

## PR Register

| PR | Outcome | Depends on | State |
|---|---|---|---|
| S01-PR01 | Repository, schemas and CI contract | None | Not started |
| S01-PR02 | Protected layered compiler with provenance | S01-PR01 | Not started |
| S01-PR03 | Deterministic release validation and packaging | S01-PR02 | Not started |
| S01-PR04 | Existing asset migration and golden baseline | S01-PR03 | Not started |

## S01-PR01 — Scaffold the canonical registry contract

**Branch:** `codex/icp-s01-pr01-registry-contract`

**Traceability:** `REG-01`, `REG-02`, `REG-03`, `SEC-01`.

**Acceptance:** A clean clone installs with Python 3.12, validates representative asset metadata, rejects secret-bearing or unowned assets, and records PR evidence in a schema-checked document.

### Task S01-PR01-T01 — Create the package, directories and CI

**Files:** `pyproject.toml`, `.github/workflows/ci.yml`, `src/fynla_control/__init__.py`, all canonical empty directories with `.gitkeep`, `tests/unit/test_package.py`.

- [ ] Add `test_package_exposes_version()` asserting `fynla_control.__version__ == "0.1.0"`.
- [ ] Run `python -m pytest tests/unit/test_package.py -q`; expect import failure.
- [ ] Add the package with Python constraint `>=3.12,<3.13`, runtime dependencies `pydantic>=2.11,<3` and `PyYAML>=6.0,<7`, and dev dependencies `pytest>=8,<9`, `ruff>=0.12,<1`.
- [ ] Add CI steps `python -m pip install -e '.[dev]'`, `ruff check .`, `python -m pytest -q` on Ubuntu and Windows with Python 3.12.
- [ ] Run `python -m pytest tests/unit/test_package.py -q`; expect `1 passed`.
- [ ] Commit `[ICP S01/PR01/T01] Scaffold the canonical registry`.

### Task S01-PR01-T02 — Define asset and evidence schemas

**Files:** `schemas/asset.schema.json`, `schemas/evidence.schema.json`, `src/fynla_control/models.py`, `src/fynla_control/schema_loader.py`, `tests/unit/test_asset_schema.py`, `tests/security/test_asset_secret_rejection.py`, `docs/implementation-evidence/s01/s01-pr01.md`.

Use this public model contract:

```python
class AssetMetadata(BaseModel):
    id: str
    type: Literal["policy", "sop", "agent", "prompt", "skill", "hook", "workflow", "role", "profile"]
    owner: str
    reviewer_role: str
    status: Literal["draft", "validated", "published", "deprecated", "revoked"]
    audience: list[str]
    classification: Literal["public", "internal", "restricted", "founder"]
    assistants: list[Literal["codex", "claude-code"]]
    operating_systems: list[Literal["macos", "windows", "linux"]]
    source_commit: str
```

- [ ] Add tests accepting a complete fixture and rejecting missing `owner`, unknown status and a content value matching `api_key`, `password` or a private-key header.
- [ ] Run `python -m pytest tests/unit/test_asset_schema.py tests/security/test_asset_secret_rejection.py -q`; expect failures because loaders do not exist.
- [ ] Implement `load_asset(path: Path) -> AssetMetadata` with strict unknown-field rejection and content scanning before model construction.
- [ ] Define the evidence schema fields `pr_id`, `requirements`, `commands`, `review`, `changed_files`, `rollback`, `pr_url`, `merge_sha`.
- [ ] Run the focused tests; expect all pass, then run `ruff check .`.
- [ ] Commit `[ICP S01/PR01/T02] Enforce asset and evidence metadata`.

### Task S01-PR01-T03 — Import the approved programme as canonical execution input

**Files:** `docs/implementation-plans/2026-08-11-fynla-integrated-ai-control-plane-programme.md`, the eight `2026-08-11-fynla-control-plane-sNN-*.md` section plans, `docs/implementation-plans/manifest.yaml`, `tests/conformance/test_plan_manifest.py`.

- [ ] Copy the approved programme and eight section plans byte-for-byte from Fynla source commit containing this plan set.
- [ ] Add manifest entries with document ID, source repository/commit/path, SHA-256, controlling design commit and supersession state.
- [ ] Add a failing conformance test requiring one master, sections S01–S08, 45 unique PR IDs, unique task IDs, resolvable plan links and matching file hashes.
- [ ] Run `python -m pytest tests/conformance/test_plan_manifest.py -q`; expect pass only after the complete approved set is present.
- [ ] Mark the Fynla copies as the immutable bootstrap source and `fynla-control` copies as canonical for subsequent checked execution updates.
- [ ] Commit `[ICP S01/PR01/T03] Canonicalise the approved implementation plans`.

### PR S01-PR01 review gate

- [ ] Run `python -m pytest -q` and `ruff check .` on Linux and Windows CI.
- [ ] Confirm the repository is private and branch protection requires CI and review.
- [ ] Confirm secret fixtures contain dummy patterns only.
- [ ] Validate `docs/implementation-evidence/s01/s01-pr01.md` against `schemas/evidence.schema.json`.
- [ ] Open the draft PR and obtain review before merge.

## S01-PR02 — Compile protected layered configuration

**Branch:** `codex/icp-s01-pr02-layered-compiler`

**Traceability:** `REG-04`, `REG-05`, `SES-01`, `SES-02`.

**Acceptance:** The compiler merges global, repository, assistant, OS, discipline, runtime, developer and session layers in that order; denies override allows; protected conflicts fail; every output field identifies its source.

### Task S01-PR02-T01 — Implement typed merge semantics

**Files:** `schemas/layer.schema.json`, `src/fynla_control/merge.py`, `tests/unit/test_merge.py`, `tests/security/test_protected_merge.py`.

```python
@dataclass(frozen=True)
class CompiledValue:
    value: object
    source_layer: str

class ProtectedConflict(ValueError):
    pass

```

Implement the exact public signature `merge_layers(layers: Sequence[Layer], schema: MergeSchema) -> CompiledConfiguration`.

- [ ] Test ordered scalar override for allowlisted preferences, list union with stable de-duplication, explicit list replacement, and provenance.
- [ ] Test that `allow: [deploy]` below global `deny: [deploy]` remains denied and that changing a protected scalar raises `ProtectedConflict`.
- [ ] Run `python -m pytest tests/unit/test_merge.py tests/security/test_protected_merge.py -q`; expect missing implementation failures.
- [ ] Implement the minimum pure merge functions; do not add file I/O or assistant rendering.
- [ ] Re-run focused tests; expect all pass.
- [ ] Commit `[ICP S01/PR02/T01] Implement deny-preserving layer merges`.

### Task S01-PR02-T02 — Compile one effective configuration with provenance

**Files:** `src/fynla_control/compiler.py`, `tests/conformance/test_layer_order.py`, `tests/golden/effective-config.json`.

The public compiler signature is `compile_effective_configuration(*, person: str, machine: str, repository: str, assistant: str, operating_system: str, discipline: str, runtime: str, session_constraints: dict[str, object], registry_root: Path) -> CompiledConfiguration`.

- [ ] Add a golden fixture proving all eight layers contribute and the final JSON includes `value` and `source_layer` for every leaf.
- [ ] Run `python -m pytest tests/conformance/test_layer_order.py -q`; expect failure because the compiler is absent.
- [ ] Implement deterministic discovery, schema validation, layer ordering and merge invocation.
- [ ] Re-run the focused test twice and compare output SHA-256 values; expect equality.
- [ ] Commit `[ICP S01/PR02/T02] Compile attributable effective configuration`.

### PR S01-PR02 review gate

- [ ] Run `python -m pytest tests/unit tests/conformance tests/security -q` and `ruff check .`.
- [ ] Review every protected key in `layer.schema.json` against design §7.1.
- [ ] Fuzz nested allow/deny and path values with at least 100 generated cases.
- [ ] Record golden hash, review decision and PR URL in `docs/implementation-evidence/s01/s01-pr02.md`.

## S01-PR03 — Validate and package immutable releases

**Branch:** `codex/icp-s01-pr03-release-packaging`

**Traceability:** `REG-06`, `REG-07`, `REG-08`, `SEC-02`.

**Acceptance:** One command validates the registry and writes a deterministic release directory whose manifest binds source commit, compiler/schema versions, compatibility, approvals, files and rollback reference.

### Task S01-PR03-T01 — Add complete release validation

**Files:** `src/fynla_control/validation.py`, `tests/unit/test_validation.py`, `tests/security/test_release_validation.py`.

- [ ] Test missing owners, broken dependencies, conflicting assets, expired reviews, secret patterns, absolute personal paths and unsupported assistant/OS pairs.
- [ ] Run `python -m pytest tests/unit/test_validation.py tests/security/test_release_validation.py -q`; expect missing validator failures.
- [ ] Implement `validate_registry(root: Path) -> ValidationReport` returning sorted errors with asset ID and source path.
- [ ] Make any error produce CLI exit code `1`; warnings do not publish silently and are written to the report.
- [ ] Re-run focused tests; expect all pass.
- [ ] Commit `[ICP S01/PR03/T01] Validate registry publication inputs`.

### Task S01-PR03-T02 — Produce a deterministic release bundle

**Files:** `schemas/release.schema.json`, `src/fynla_control/release.py`, `src/fynla_control/cli.py`, `tests/golden/test_release_bundle.py`.

```python
class ReleaseManifest(BaseModel):
    release_id: str
    source_commit: str
    compiler_version: str
    schema_version: str
    compatibility: dict[str, str]
    approvals: list[str]
    rollback_release_id: str | None
    files: dict[str, str]
    manifest_sha256: str
```

- [ ] Add a golden test that runs `fynla-control release build --source-commit 0123456789abcdef0123456789abcdef01234567 --out dist/release` twice and compares all bytes and hashes.
- [ ] Run `python -m pytest tests/golden/test_release_bundle.py -q`; expect failure because the command is absent.
- [ ] Implement sorted traversal, LF line endings, fixed JSON separators, SHA-256 file map and self-hash excluding only `manifest_sha256`.
- [ ] Reject a dirty checkout and a `source_commit` that differs from `git rev-parse HEAD`.
- [ ] Re-run the focused test; expect all pass.
- [ ] Commit `[ICP S01/PR03/T02] Build reproducible release bundles`.

### PR S01-PR03 review gate

- [ ] Run `python -m pytest -q` and `ruff check .` in a clean clone.
- [ ] Verify two clean machines produce the same manifest hash.
- [ ] Tamper with one generated byte and prove `fynla-control release verify` exits `1`.
- [ ] Record release hash and tamper-test evidence in `docs/implementation-evidence/s01/s01-pr03.md`.

## S01-PR04 — Migrate existing assets and establish goldens

**Branch:** `codex/icp-s01-pr04-asset-migration`

**Traceability:** `REG-09`, `REG-10`, `REG-11`, `REG-12`, `SES-03`, `SES-04`.

**Acceptance:** Existing useful Fynla assets are inventoried, de-duplicated and represented under one owner; separate Core, Codex and Claude SOPs compile; hard-coded personal paths are removed; the old `fynla-agents` repository is marked non-canonical.

### Task S01-PR04-T01 — Inventory and classify current assets

**Files:** `migration/source-inventory.yaml`, `migration/decisions.md`, `tests/conformance/test_inventory.py`.

- [ ] Inventory every file under `Fynla/.claude/agents`, `.claude/hooks`, `.claude/skills`, `plugins/fynla-dev-skills`, `plugins/fynla-windows` and `fynla-agents` with source SHA-256.
- [ ] Classify each as `migrate`, `replace`, `deprecate` or `exclude`, with owner and reason; no unclassified row is permitted.
- [ ] Add a test comparing inventory paths with a fresh source scan; expect failure until the inventory is complete.
- [ ] Run `python -m pytest tests/conformance/test_inventory.py -q`; expect pass after every source asset is classified.
- [ ] Commit `[ICP S01/PR04/T01] Inventory existing assistant assets`.

### Task S01-PR04-T02 — Add Core, Codex and Claude SOP sources

**Files:** `core/engineering-sop/SKILL.md`, `assistants/codex/SOP.md`, `assistants/claude-code/SOP.md`, `tests/conformance/test_sop_policy.py`.

- [ ] Encode the shared lifecycle `intake → permission → risk → context → plan → isolation → implementation → validation → self-review → draft PR → audit` in the Core SOP.
- [ ] Add Codex-native and Claude-native instructions without copying mutable global policy into lower layers.
- [ ] Test that both native SOPs reference every normative Core rule and contain no override of deny, approval, merge or deploy policy.
- [ ] Run `python -m pytest tests/conformance/test_sop_policy.py -q`; expect all pass.
- [ ] Commit `[ICP S01/PR04/T02] Establish shared and native engineering SOPs`.

### Task S01-PR04-T03 — Migrate approved assets and remove personal paths

**Files:** approved paths under `agents/`, `prompts/`, `skills/`, `hooks/`, `workflows/`, `environments/`, plus `tests/security/test_portability.py` and `release-notes/0.1.0.md`.

- [ ] Migrate only `migration/source-inventory.yaml` entries marked `migrate` or `replace`, preserving their source reference in metadata.
- [ ] Add portability tests rejecting `/Users/`, `C:\\Users\\`, home-directory literals and unresolved machine-specific executables.
- [ ] Replace current Chris-specific paths with declared variables resolved by the compiler.
- [ ] Build and verify release `0.1.0`; record manifest hash and inventory totals.
- [ ] Add a deprecation notice to `fynla-agents` directing changes to `Fynla/fynla-control` in a separate linked repository PR.
- [ ] Commit `[ICP S01/PR04/T03] Migrate approved assets into the registry`.

### PR S01-PR04 review gate

- [ ] Run `python -m pytest -q`, `ruff check .`, release build and release verify.
- [ ] Confirm every migrated asset has owner, reviewer, classification, compatibility and source provenance.
- [ ] Compare compiled Codex and Claude policy outcomes against the shared conformance suite.
- [ ] Confirm no secret or absolute personal path appears in the release.
- [ ] Obtain founder and engineering review before making release `0.1.0` available.

## Section S01 Completion Gate

- [ ] All four PRs are merged and their evidence files validate.
- [ ] A clean Linux and Windows checkout reproduces release `0.1.0` byte-for-byte.
- [ ] The migrated inventory has zero unclassified files.
- [ ] `fynla-control` is documented as the sole canonical source.
- [ ] S02 and S05 consumers can validate the release manifest without importing compiler internals.
