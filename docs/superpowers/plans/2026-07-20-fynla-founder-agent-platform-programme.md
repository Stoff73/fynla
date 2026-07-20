# Fynla Founder-Agent Platform Programme Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver the approved Fynla founder-agent platform as three independently testable releases: a read-only shared knowledge agent, approval-gated canonical actions, and the Git-backed dashboard plus production rollout.

**Architecture:** The platform lives in a new private `fynla-founder-platform` code repository and uses the separate private `fynla-agents` and `fynla-vault` content repositories. Slack remains the founder interface, while a FastMCP/Slack/Python service, LiteLLM and PostgreSQL/pgvector provide model-neutral retrieval and bounded tools on a dedicated VPS. Each phase is fail-closed and must pass its own acceptance gate before the next phase begins.

**Tech Stack:** Python 3.12, FastMCP 2, Slack Bolt for Python, LiteLLM Proxy, SQLAlchemy 2, Alembic, psycopg 3, PostgreSQL 16, pgvector, pytest, Docker Compose v2, Caddy 2, PHP 8.3, Laravel 12, Inertia 2, Vue 3, Vite, Pest and Vitest.

## Global Constraints

- The approved source of truth is `docs/superpowers/specs/2026-07-20-fynla-founder-agent-platform-design.md` at commit `bc1eecd3fef8dc773510d788ef03b72416d4fe26`.
- The customer application remains on SiteGround and is not modified by platform implementation.
- Fyn's procedural, semantic, episodic and working-memory modules are customer-facing CoALA systems and must never be read, indexed or written by this platform.
- Production customer records, advice cases, episodes, signed attestations, credentials and production exports are excluded.
- Slack is the canonical founder interface; other MCP clients are optional.
- Google Shared Drive, GitHub, `fynla-vault` and selected Slack channels remain canonical; PostgreSQL search content is derived and rebuildable.
- Every factual answer includes canonical links and source freshness.
- Every canonical write requires approval of an immutable payload; no write is inferred from conversation alone.
- Merge, deployment, deletion and permission-changing tools do not exist.
- Tool scope, source access, security controls and prohibited-action changes require two-founder configuration approval.
- `mcp.fynla.org` and `agents.fynla.org` run on a separate Fynla-owned VPS; internal ports and PostgreSQL are never public.
- Founder bearer tokens are distinct, hashed, scoped, revocable and rotated within 90 days; service identities are separate.
- Full LLM request and response bodies are not retained by default.
- All model and embedding calls use LiteLLM aliases; no application code hard-codes a commercial model name.
- Every code task follows TDD: failing test, observed failure, minimal implementation, focused green, affected-suite green, then commit.
- Every completion claim uses fresh verification output.
- Browser acceptance uses the user's installed Google Chrome only; bundled/headless Chromium is prohibited.
- Existing untracked `July/July20Updates/` is unrelated and must never be staged by these plans.
- The verified existing remote is personal (`Stoff73/fynla`). New platform, agent-config and vault repositories must not be created under a personal account; execution pauses until a Fynla-owned GitHub organisation is available.

---

## Programme repository map

### Existing planning repository

- `docs/superpowers/specs/2026-07-20-fynla-founder-agent-platform-design.md` — approved architecture and controls.
- `docs/superpowers/plans/2026-07-20-fynla-founder-agent-platform-programme.md` — programme order and gates.
- `docs/superpowers/plans/2026-07-20-fynla-founder-agent-platform-phase-1.md` — read-only shared knowledge agent.
- `docs/superpowers/plans/2026-07-20-fynla-founder-agent-platform-phase-2.md` — approval-gated canonical actions.
- `docs/superpowers/plans/2026-07-20-fynla-founder-agent-platform-phase-3.md` — dashboard, operations and founder rollout.

### New `fynla-founder-platform` repository

- `src/fynla_agent/` — Python application, Slack worker, FastMCP tools, retrieval, connectors, actions and audit.
- `migrations/` — Alembic schema migrations for PostgreSQL/pgvector.
- `tests/` — unit, integration, contract, security and golden acceptance tests.
- `dashboard/` — separate Laravel/Inertia/Vue founder configuration dashboard.
- `deploy/` — Compose, Caddy, backup, restore and operational definitions.
- `docs/runbooks/` — deployment, connector, recovery and founder operating guides.

### New content repositories

- `fynla-agents` — prompts, schemas, model aliases, source allowlists, tool policies and golden cases; contains no secrets.
- `fynla-vault` — team-safe shared Obsidian Markdown and approved attachments; protected default branch; agent writes only by pull request.

---

## Release sequence

### Release 1: Read-only shared knowledge agent

Execute `2026-07-20-fynla-founder-agent-platform-phase-1.md`.

Release outcome:

- dedicated local/staging composition starts reproducibly;
- source ingestion and deletion reconciliation work for allowlisted fixtures and sandbox sources;
- hybrid retrieval returns canonical links and freshness;
- LiteLLM can switch/fail over between two approved provider aliases;
- authenticated FastMCP read tools work;
- Slack answers in the selected channels without performing any canonical write;
- the 30-question golden set and security gates pass.

Gate: no Phase 2 work begins until the read-only release passes every Phase 1 acceptance check and all three founders approve answer quality/noise in Slack.

### Release 2: Approval-gated canonical actions

Execute `2026-07-20-fynla-founder-agent-platform-phase-2.md`.

Release outcome:

- Slack presents immutable action previews;
- ✅ or unambiguous thread approval executes once;
- GitHub issue/project, Drive decision, vault pull-request and Calendar tools are bounded and independently enabled;
- requester, approver, payload hash, configuration SHA and result link are auditable;
- replay, timeout and duplicate tests pass;
- prohibited actions remain structurally unavailable.

Gate: no action connector is enabled for founders until its contract, permission-boundary and idempotency suites pass against sandbox resources.

### Release 3: Dashboard and controlled rollout

Execute `2026-07-20-fynla-founder-agent-platform-phase-3.md`.

Release outcome:

- Google Workspace founder login works at `agents.fynla.org`;
- drafts, validation, publish, high-risk second approval, history and rollback operate through Git;
- configuration releases activate atomically and notify `#fynla-agents`;
- backup, restore, monitoring and bad-release recovery drills pass;
- the three-founder production rollout completes with documented operating procedures.

Gate: production enablement requires Chris's infrastructure/security approval plus explicit founder acceptance of the final Slack and dashboard workflows.

---

## Programme control tasks

### Task P1: Establish company-owned external prerequisites

**Files:**
- Create during execution: `docs/runbooks/external-prerequisites.md` in `fynla-founder-platform`
- Create during execution: `docs/runbooks/access-register.yaml` in `fynla-founder-platform`
- Create during execution: `tests/architecture/test_external_prerequisites.py` in `fynla-founder-platform`

**Interfaces:**
- Consumes: Fynla-owned Google Workspace, Slack workspace, DNS zone and VPS billing account.
- Produces: verified organisation/repository ownership, named service-account owners and a credential-free access register consumed by all three phases.

- [ ] **Step 1: Write the prerequisite verification script test**

```python
from pathlib import Path
import yaml


def test_access_register_has_company_owners():
    data = yaml.safe_load(Path("docs/runbooks/access-register.yaml").read_text())
    assert data["github"]["ownership"] == "fynla-company"
    assert data["vps"]["ownership"] == "fynla-company"
    assert data["dns"]["zone"] == "fynla.org"
    assert set(data["founders"]) == {
        "Azlan Raj",
        "Brett Isenberg",
        "Chris Slater-Jones",
    }
    assert "token" not in Path("docs/runbooks/access-register.yaml").read_text().lower()
```

- [ ] **Step 2: Run the test and observe the missing-register failure**

Run: `pytest tests/architecture/test_external_prerequisites.py -q`

Expected: FAIL because `docs/runbooks/access-register.yaml` does not exist.

- [ ] **Step 3: Create the credential-free access register**

```yaml
version: 1
github:
  ownership: fynla-company
  repositories:
    - fynla-founder-platform
    - fynla-agents
    - fynla-vault
vps:
  ownership: fynla-company
dns:
  zone: fynla.org
  records:
    - mcp.fynla.org
    - agents.fynla.org
founders:
  - Azlan Raj
  - Brett Isenberg
  - Chris Slater-Jones
credential_storage: vps-secret-store
```

- [ ] **Step 4: Run the prerequisite test**

Run: `pytest tests/architecture/test_external_prerequisites.py -q`

Expected: PASS.

- [ ] **Step 5: Stop if ownership is personal**

Run: `git remote -v`

Expected: the new repository remote resolves to the company-owned GitHub organisation recorded by the founders. If it resolves to `Stoff73` or another personal account, do not push or provision credentials.

- [ ] **Step 6: Commit**

```bash
git add docs/runbooks/external-prerequisites.md docs/runbooks/access-register.yaml tests/architecture/test_external_prerequisites.py
git commit -m "docs: record founder platform ownership"
```

### Task P2: Run phase gates in order

**Files:**
- Create during execution: `docs/runbooks/release-gates.md` in `fynla-founder-platform`
- Create during execution: `docs/evidence/phase-1.md`, `docs/evidence/phase-2.md`, `docs/evidence/phase-3.md`
- Create during execution: `tests/architecture/test_release_evidence.py`

**Interfaces:**
- Consumes: the verification outputs named in each phase plan.
- Produces: one evidence record per release and a founder approval checkpoint before the following phase.

- [ ] **Step 1: Create the release-gate contract test**

```python
from pathlib import Path


def test_each_phase_has_an_evidence_record():
    for phase in (1, 2, 3):
        text = Path(f"docs/evidence/phase-{phase}.md").read_text()
        assert "## Verification output" in text
        assert "## Founder decision" in text
        assert "approved" in text.lower()
```

- [ ] **Step 2: Run the test before a release gate**

Run: `pytest tests/architecture/test_release_evidence.py -q`

Expected: FAIL until the current phase has real verification output and a founder decision.

- [ ] **Step 3: Record evidence without paraphrasing failures**

Use this exact section shape in each evidence document. The H1 is respectively `Phase 1 Release Evidence`, `Phase 2 Release Evidence`, and `Phase 3 Release Evidence`:

```markdown
# Phase 1 Release Evidence

## Commit
Full commit SHA and configuration release SHA.

## Verification output
Verbatim commands, exit codes, pass/fail counts and links to Chrome acceptance evidence.

## Known limitations
Only limitations explicitly accepted by all founders.

## Founder decision
Names, timestamp and approved/rejected decision.
```

- [ ] **Step 4: Run the evidence test**

Run: `pytest tests/architecture/test_release_evidence.py -q`

Expected: PASS for all completed phases and FAIL for an unapproved later phase.

- [ ] **Step 5: Commit the three phase decisions after their respective gates**

```bash
git add docs/evidence/phase-1.md docs/runbooks/release-gates.md tests/architecture/test_release_evidence.py
git commit -m "docs: record phase 1 release gate"
git add docs/evidence/phase-2.md
git commit -m "docs: record phase 2 release gate"
git add docs/evidence/phase-3.md
git commit -m "docs: record phase 3 release gate"
```

## Design-to-plan coverage map

| Approved design area | Implemented and verified by |
|---|---|
| CoALA/customer/SiteGround separation | Programme constraints; Phase 1 Tasks 4 and 9; Phase 3 Task 6 |
| Founder Slack experience and channel policy | Phase 1 Tasks 3 and 8; Phase 2 Task 2 |
| Founder responsibility routing | Phase 1 Task 3; Phase 2 proposal payload/preview tests |
| Canonical GitHub/Drive/vault/Calendar routes | Phase 2 Tasks 3–6 |
| Shared vault and Google Shared Drive boundaries | Phase 1 Task 4; Phase 2 Tasks 4–5 |
| VPS, FastMCP and Slack Socket Mode | Phase 1 Tasks 7–9 |
| LiteLLM provider and embedding independence | Phase 1 Tasks 5–6 and 9 |
| PostgreSQL/pgvector hybrid retrieval/freshness | Phase 1 Tasks 2 and 5 |
| Git-backed prompts/configuration | Phase 1 Task 3; Phase 3 Tasks 2–4 |
| Immutable approvals/idempotent actions | Phase 2 Tasks 1–2 and 7 |
| Least privilege, redaction and prompt-injection resistance | Phase 1 Tasks 1, 3, 4, 6–9; Phase 2 Task 7 |
| Failure recovery and no silent success | Phase 1 Tasks 5–9; Phase 2 Tasks 1 and 7; Phase 3 Tasks 4–5 |
| Dashboard history, diff, publish and rollback | Phase 3 Tasks 1–4 |
| Backups, monitoring, RPO/RTO and restore | Phase 3 Task 5 |
| Automated, provider-switch and Chrome acceptance | Phase 1 Task 9; Phase 2 Task 7; Phase 3 Task 6 |

## Programme completion

The programme is complete only when all three phase evidence documents are approved, the production restoration drill meets RPO six hours/RTO four hours, the provider-switch and embedding-reindex gates pass, and no founder-platform service or credential is present on SiteGround or inside the Fynla customer application.
