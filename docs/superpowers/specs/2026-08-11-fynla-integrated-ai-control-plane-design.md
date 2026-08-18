# Fynla Integrated AI Control Plane — Design Specification

**Date:** 11 August 2026

**Status:** Approved in conversation by Chris Slater-Jones on 11 August 2026

**Primary interfaces:** `mcp.fynla.org`, Slack, Codex and Claude Code

**Initial hosting:** Existing Fynla SiteGround subscription, with GitHub Actions execution workers

## 1. Decision

Fynla will build one integrated AI control plane for founders, developers and approved service identities. It will replace the previously separate founder platform and development control-plane concepts with one role-scoped platform, one policy engine, one audit trail and one canonical configuration registry.

The platform will centralise and govern:

- the Fynla Engineering Core SOP;
- separate Codex and Claude Code SOPs;
- agents, prompts, skills, hooks and reusable workflows;
- repository, operating-system, discipline and developer configurations;
- founder and company knowledge retrieval;
- Slack participation and coding-job orchestration;
- roles, permissions, repository assignments and temporary access;
- onboarding, role changes and offboarding;
- configuration releases, synchronisation, drift detection and rollback;
- job state, approvals, audit evidence, monitoring and cost controls.

The public control plane and dashboard will run at `mcp.fynla.org` on the existing SiteGround subscription. SiteGround will receive Slack and MCP requests, authenticate users, apply policy, maintain operational state and dispatch work. One dedicated SiteGround MySQL/MariaDB database will hold both control-plane state and the rebuildable, permission-filtered full-text knowledge index. GitHub Actions will execute long-running indexing, reasoning, Codex, Claude Code, build and test jobs. Git remains the canonical store for version-controlled platform content.

Routine, low-risk coding fixes may run automatically to an isolated branch and tested draft pull request. The platform will never automatically merge or deploy them. Medium- and high-risk work remains approval-gated, and prohibited production actions remain unavailable to coding agents.

This specification is the new controlling design where it conflicts with:

- `docs/superpowers/specs/2026-07-20-fynla-founder-agent-platform-design.md`;
- `.worktrees/FynlaMCP/docs/superpowers/specs/2026-07-21-fynlamcp-siteground-architecture-design.md`;
- `docs/superpowers/specs/2026-07-15-session-start-standing-authorisation-design.md`.

The earlier designs remain informative for requirements not changed here, particularly canonical knowledge sources, customer-data isolation, immutable proposals, auditability and standing authorisation after an approved implementation plan.

## 2. Outcomes

The platform succeeds when:

1. Founders and developers use one platform without maintaining separate founder and engineering surfaces.
2. Every Codex and Claude Code session begins from an approved, current and attributable configuration.
3. A macOS, Windows or Linux developer receives the correct environment, repository, discipline, assistant and personal configuration without weakening global policy.
4. Slack can answer, participate, interject when useful, create work and steer running jobs without exposing information across access boundaries.
5. A routine bug can move from a Slack report to a tested draft pull request without manual orchestration.
6. Medium- and high-risk work cannot cross its approval boundary.
7. Every asset, session, job, permission decision, approval and release is attributable and auditable.
8. Local, repository and worker drift is detected and safely corrected.
9. The platform remains usable when no founder computer is online.
10. The customer-facing Fynla application and CoALA runtime remain outside the platform's data and action boundary.

## 3. Non-goals

The first implementation will not:

- replace GitHub, Slack, Google Workspace or the shared Markdown vault as their natural systems of record;
- index or operate on production customer data or per-user CoALA memory;
- give AI workers permission to merge protected branches or deploy production;
- host long-running Node.js, Python or agent processes inside SiteGround web requests;
- provide remote wipe or device management for personal computers;
- guarantee that drift is physically impossible; it will prevent, detect, report and remediate drift;
- build a general-purpose enterprise identity provider or endpoint-management product;
- require founders or developers to understand the internal task-envelope format;
- make every provider-specific feature identical across Codex and Claude Code;
- add a second day-to-day dashboard for founders.

## 4. System boundaries

### 4.1 Separation from the Fynla application and CoALA

The control plane is company collaboration and development infrastructure. It is not Fyn's customer-facing AI or memory architecture.

It must never read, index, write or expose:

- production customer records;
- customer uploads or production exports;
- per-user procedural, semantic, episodic or working memory;
- advice cases, signed attestations or personal financial data;
- the production application's credentials, queues or private runtime state;
- Fyn internal tools merely because they exist in the application repository.

Approved source code and product documentation may be indexed as company knowledge. Runtime customer data remains excluded.

### 4.2 Shared SiteGround subscription

The control plane will initially share Fynla's existing SiteGround subscription. It must still have:

- its own subdomain and document root;
- its own application release directories;
- its own database and database credentials;
- its own environment secrets;
- separate cache exclusions;
- separate health and deployment checks;
- application-level rate and concurrency limits.

This is logical and credential isolation, not physical resource isolation. The control plane and customer application may share subscription-level CPU, memory, process and I/O allowances. Monitoring and migration triggers in this specification therefore form part of the hosting decision.

### 4.3 Canonical versus derived state

Canonical content remains in GitHub, Google Shared Drive, the approved Markdown vault and other explicitly approved systems. One dedicated SiteGround MySQL/MariaDB database stores identity, assignments, jobs, approvals, release pointers, audit metadata and a rebuildable full-text search index. Separate table groups and application services preserve the operational-versus-derived boundary even though they share the same SQL database. The database does not become an undocumented source of truth for prompts, SOPs, skills or company documents.

## 5. Architecture

```text
Slack, dashboard, Codex, Claude Code and approved MCP clients
                              │
                              ▼
                    mcp.fynla.org on SiteGround
        ┌──────────────────────────────────────────────┐
        │ PHP control plane and role-scoped dashboard │
        │ /mcp                                        │
        │ /slack/events                               │
        │ /callbacks/github                           │
        │ /health                                     │
        │ identity, policy, jobs, approvals and audit │
        └──────────────────────┬───────────────────────┘
                               │
                               ▼
               SiteGround MySQL/MariaDB database
                control data + full-text index
                               │
                        signed job dispatch
                               ▼
                     GitHub Actions workers
        ┌──────────────────────────────────────────────┐
        │ registry validation and release compilation │
        │ indexing and retrieval                      │
        │ provider-neutral reasoning                  │
        │ Codex and Claude Code execution             │
        │ isolated branches, tests and draft PRs      │
        │ synchronisation and recovery                │
        └──────────────────────┬───────────────────────┘
                               │
                               ▼
                  Fynla canonical Git and
                  approved knowledge sources
                               │
                               └── signed callbacks ──► SiteGround
                                                          │
                                                          ▼
                                                   Slack/dashboard
```

### 5.1 Component responsibilities

| Component | Responsibility | Must not do |
|---|---|---|
| SiteGround control plane | Authentication, policy, dashboard, MCP, Slack ingress, job state, approvals, audit and dispatch | Run long coding agents, builds or persistent WebSocket workers |
| SiteGround MySQL/MariaDB | Operational identities, assignments, jobs, approvals, release pointers, audit metadata and a permission-tagged derived full-text index | Become the canonical store for SOPs, prompts, skills or company documents |
| `Fynla/fynla-control` | Canonical versioned policies, SOPs, assets, schemas, tests and release inputs | Store secrets or runtime customer data |
| GitHub Actions workers | Bounded asynchronous indexing, reasoning, Codex/Claude work, tests and draft PRs | Merge, deploy, broaden permissions or use unapproved repositories |
| Developer synchronisation client | Compile and install the user's permitted local configuration | Overwrite uncommitted work or store shared secrets |
| Slack app | Conversation, notification, approval and job steering | Decide permissions independently of the control plane |

### 5.2 Public endpoints

The initial public routes are:

- `POST /mcp` — authenticated, stateless MCP JSON-RPC;
- `POST /slack/events` — signed Slack Events API delivery;
- `POST /callbacks/github` — signed worker progress and results;
- `GET /health` — secret-free liveness plus authenticated dependency detail;
- dashboard routes beneath `https://mcp.fynla.org/`.

The prior `agents.fynla.org` concept becomes a redirect to the role-scoped dashboard or is retired after transition. It is not maintained as a separate product surface.

Long operations return a job identifier rather than holding an HTTP request open. The MCP implementation will target the 28 July 2026 protocol revision and negotiate compatible client versions. Existing older gateway assumptions must be upgraded before release.

### 5.3 Provider routing

Model selection is configured through capability aliases rather than commercial model names embedded in workflows. Initial logical aliases include:

- `answer-fast`;
- `answer-primary`;
- `coding-codex`;
- `coding-claude`.

The SiteGround gateway and GitHub workers resolve aliases from the pinned configuration release. Changing an answer or coding deployment is a validated configuration change. Embedding models and an `embedding-v1` alias are not required for the pilot because initial retrieval uses SiteGround SQL full-text search. If a later approved design adds vector retrieval, changing its embedding deployment must build and evaluate a parallel index before activation because vector dimensions and spaces may differ. Provider keys remain in SiteGround environment configuration or GitHub encrypted secrets and never enter the registry, task envelope, Slack or logs.

### 5.4 Core operational records

The control plane uses explicit records with narrow purposes:

- person and linked external identities;
- role template, permission definition, resource assignment and temporary grant;
- developer, machine and repository profile;
- registry asset, release and active consumer assignment;
- session attestation;
- task envelope, job, attempt and state transition;
- immutable proposal, approval and execution result;
- source, source version, sync run and derived index entry;
- append-only audit event.

No record combines identity, content, job and audit responsibilities into an opaque session blob.

## 6. Canonical registry

The private `Fynla/fynla-control` repository is the sole canonical store for version-controlled control-plane behaviour. Existing useful `fynla-agents` material will migrate into it; the two repositories will not remain competing sources of truth.

The initial layout is:

```text
fynla-control/
├── core/
│   ├── engineering-sop/
│   ├── security-policy/
│   ├── coding-standards/
│   └── approval-policy/
├── assistants/
│   ├── codex/
│   └── claude-code/
├── agents/
├── prompts/
├── skills/
├── hooks/
├── workflows/
├── slack-agent/
├── repositories/
├── disciplines/
│   ├── backend/
│   ├── frontend/
│   ├── systems/
│   └── design/
├── environments/
│   ├── macos/
│   ├── windows/
│   └── linux/
├── developers/
├── roles/
├── policies/
├── schemas/
├── tests/
│   ├── golden/
│   ├── conformance/
│   └── security/
└── release-notes/
```

### 6.1 Asset metadata

Every governed asset has machine-readable metadata containing:

- stable identifier and type;
- owner and reviewing role;
- status: draft, validated, published, deprecated or revoked;
- audience and content classification;
- repository and discipline scope;
- compatible assistant and minimum version;
- supported operating systems;
- dependencies and conflicting assets;
- change note and source commit;
- review date and optional expiry date.

The registry contains references to secrets, never secret values. Personal paths are represented by variables and resolved during compilation.

### 6.2 Ownership and review

Every asset has one accountable owner. The dashboard reports unowned, expired and overdue-for-review assets. Deprecation includes a replacement or explicit retirement reason. Revoked security assets invalidate affected session attestations.

## 7. Layered developer configuration

An effective configuration is compiled for a specific person, machine, repository, assistant and task from:

1. mandatory Fynla global policy;
2. repository configuration;
3. Codex or Claude Code adapter;
4. operating-system configuration;
5. discipline configuration;
6. runtime and toolchain configuration;
7. developer-specific preferences and approved tools;
8. temporary session or task constraints.

For example:

```text
Fynla global
+ Fynla application repository
+ Codex SOP
+ macOS environment
+ founder/full-stack discipline
+ Chris-specific preferences
+ current task constraints
```

### 7.1 Merge rules

Configuration is not merged through unrestricted last-writer-wins behaviour.

- Global denials and security constraints are locked; a lower layer cannot loosen them.
- Permission sets use deny-overrides-allow semantics.
- Repository constraints may tighten global policy.
- Assistant, OS, discipline and individual layers may alter only schema-allowlisted settings.
- Lists are merged and deduplicated unless the schema declares replacement.
- Scalar precedence is permitted only for non-protected preferences.
- Every compiled field retains provenance identifying the source layer.
- Conflicting protected values fail compilation rather than selecting one silently.
- Session constraints may narrow tools, time, cost or scope but cannot broaden them.

### 7.2 Generated outputs

The compiler emits assistant-native, operating-system-correct outputs plus a manifest of file hashes. It must never emit a macOS path into a Windows configuration or vice versa. Shared and personal outputs are separated so repository-controlled settings remain reviewable and local preferences remain local.

## 8. Engineering SOPs and assistant adapters

The platform maintains three separately readable SOPs:

1. **Fynla Engineering Core SOP** — normative lifecycle, safety, testing and delivery rules.
2. **Fynla Codex SOP** — native Codex implementation of the core lifecycle.
3. **Fynla Claude Code SOP** — native Claude Code implementation of the same lifecycle.

The two assistant SOPs may optimise prompts, context, profiles, hooks, skills, agents and execution style independently. They cannot weaken the core policy.

### 8.1 Shared engineering lifecycle

```text
Intake → identity and permission check → repository scope
→ risk classification → context loading → plan
→ isolated branch/worktree → implementation
→ tests and validation → self-review → draft PR
→ audit and notification
```

### 8.2 Codex adapter

The Codex adapter compiles the shared configuration into supported Codex surfaces, including:

- `AGENTS.md` for durable repository instructions and verification expectations;
- `.codex/config.toml` for trusted-repository configuration;
- Codex skills for repeatable workflows;
- plugins for approved skills, hooks and MCP configuration;
- lifecycle hooks, including `SessionStart`;
- global and profile-specific settings where a value is personal rather than repository-owned.

### 8.3 Claude Code adapter

The Claude Code adapter compiles the shared configuration into supported Claude Code surfaces, including:

- `CLAUDE.md` for persistent repository guidance;
- `.claude/settings.json` for shared project configuration;
- `.claude/settings.local.json` for generated local-only settings;
- `.mcp.json` for approved MCP connections;
- `.claude/skills/`, `.claude/agents/` and lifecycle hooks;
- a versioned Fynla Claude plugin for reusable team components.

### 8.4 Conformance rather than identical behaviour

Both adapters must satisfy the same policy and outcome contract. They are not required to produce identical reasoning or code. Conformance tests verify permissions, task boundaries, required evidence and delivery state.

## 9. Assistant-neutral task envelope

Slack, the dashboard, an MCP client or a developer submits an ordinary natural-language request. The control plane normalises it into an assistant-neutral task envelope before dispatch.

The requester does not manually complete the envelope. The platform derives fields from identity, access, repository, developer profile and policy, asking for clarification only when a material value cannot be determined safely.

The envelope contains:

- task and acceptance criteria;
- requester, source and callback destination;
- repository and permitted paths;
- selected assistant or allowed routing choices;
- effective configuration release;
- risk classification and reason;
- allowed and prohibited actions;
- required tests and evidence;
- time, turn and cost limits;
- approval state and expiry;
- idempotency key.

Example:

```text
Requester: Chris
Source: Slack thread
Repository: Fynla/Fynla
Task: Fix retirement calculator total after contribution change
Acceptance: Total recalculates correctly and regression test passes
Risk: Amber — financial calculation
Assistant: Codex
Configuration: Global + repository + Codex + macOS + Chris
Allowed: Investigate, edit isolated branch, run tests, open draft PR after approval
Prohibited: Merge, deploy, production data, secrets
Return: Original Slack thread and dashboard job page
```

### 9.1 Common job events

Codex and Claude workers emit the same external event vocabulary:

- `accepted`;
- `queued`;
- `running`;
- `investigating`;
- `implementing`;
- `validating`;
- `needs_approval`;
- `draft_pr_ready`;
- `failed`;
- `cancel_requested`;
- `cancelled`;
- `timed_out`.

This keeps Slack, dashboard, audit and recovery behaviour independent of the selected assistant.

## 10. Risk and autonomy model

### 10.1 Green — automatic to draft PR

Green work may automatically create an isolated branch, implement, test, self-review and open a draft pull request when all of these are true:

- the request is concrete and unambiguous;
- the requester is permitted to use the repository;
- acceptance criteria can be tested;
- the change is localised and reversible through Git;
- no protected domain or prohibited action is involved;
- the required test path is known;
- the risk classifier meets the configured confidence threshold.

Examples include obvious text defects, narrow presentation bugs, deterministic lint or formatting repairs, documentation fixes and bounded regressions with existing tests.

### 10.2 Amber — approval before mutation

Amber work may be investigated read-only but requires an authorised lead or founder before a write-capable worker begins. It includes:

- financial calculations or user-facing financial outcomes;
- authentication, authorisation or privacy behaviour;
- schema migrations or material data-model changes;
- dependency upgrades;
- multi-repository changes;
- infrastructure or external integrations;
- ambiguous scope or missing acceptance criteria;
- broad refactoring or architecture changes.

### 10.3 Red — tightly controlled or unavailable

Red work includes production deployment, merging, destructive data operations, credential use, access-policy elevation, payment or billing state, security-policy changes and other irreversible actions.

- Coding agents do not receive merge or production-deploy capability.
- Global security, source-access, prohibited-action and high-risk tool-scope changes require two-founder approval.
- Destructive or credential-bearing work requires a separately designed and explicitly authorised workflow; it is not inferred from an ordinary Slack request.

### 10.4 Standing authorisation

Approval of an implementation plan or immutable job authorises ordinary in-scope local edits, tests and proportionate corrections. It does not authorise scope expansion, secrets, production changes, irreversible loss, merge, push or deployment unless the approved workflow explicitly includes them.

The green automation workflow defined by this specification explicitly authorises its bounded worker to push only its generated feature branch and open or update its draft pull request. It does not authorise a protected-branch push, merge or deployment.

### 10.5 Non-coding founder actions

The integrated platform retains the founder workflow for durable company actions. After approval of an immutable payload, initial bounded actions may:

- create or update a GitHub issue and approved project fields;
- open a pull request against the shared Markdown vault;
- create a decision record in an allowlisted Google Shared Drive folder;
- append to a designated decision log;
- create or update a linked Google Calendar event.

These connector actions remain idempotent, attributable and separately permissioned. They cannot delete records, change memberships or permissions, overwrite arbitrary documents, merge code or deploy software. Each additional connector mutation is a reviewed tool-scope change.

## 11. Slack agent

Slack is a conversation and orchestration surface, not the only system of record.

### 11.1 Answer mode

The agent responds when:

- mentioned directly;
- asked a relevant question in an allowlisted channel;
- sent a direct message by a mapped, authorised user;
- asked for repository, job, pull-request, incident or configuration status.

Answers are filtered by the requester's effective access before retrieval. Direct messages are not indexed into the shared corpus by default.

### 11.2 Participation mode

In selected channels, the agent may interject without a mention when it can materially help with:

- a technical or company question supported by approved evidence;
- a reported defect or failing workflow;
- a discussion contradicting a current SOP or recorded decision;
- an unresolved decision that should be made durable;
- a relevant known incident, issue or pull request;
- a concrete work item that can be routed safely.

It remains silent during social conversation, low-confidence speculation, conversations outside its scope or discussions where it has nothing material to add. Interjections are confidence-gated, rate-limited and posted in threads.

### 11.3 Action mode

A Slack message can become a task envelope and job. Green fixes may auto-start. Amber and red work presents an immutable proposal or clarification request.

Slack shows meaningful state changes rather than every internal step:

```text
Accepted → Investigating → Implementing → Testing
→ Draft PR ready / Needs approval / Failed / Cancelled
```

The final response identifies the assistant, configuration release, branch, tests, pull request and unresolved concerns.

### 11.4 Interrupting and steering

Each job has one controlling Slack thread. An authorised participant may request:

- stop or cancel;
- a narrower scope;
- added acceptance criteria;
- additional tests;
- a switch between Codex and Claude;
- an explanation of current status.

The platform records the instruction and requests cancellation at the next safe point. Material scope changes create a revised envelope and replacement attempt; they do not silently mutate an active job.

### 11.5 Slack ingress

The gateway verifies Slack signatures and timestamps against the raw request, rejects replays, deduplicates event identifiers, applies workspace/user/channel policy, persists the event and acknowledges within three seconds. Long work always continues asynchronously.

## 12. Unified dashboard

There is one dashboard and one authentication system. Navigation and data are role-scoped rather than implemented as separate founder and developer applications.

The dashboard contains:

- Home and current activity;
- Knowledge and canonical sources;
- Agents, prompts, skills, hooks and workflows;
- Core, Codex and Claude SOPs;
- repository configurations;
- developer and machine profiles;
- jobs, attempts and draft pull requests;
- proposals and approvals;
- Slack activity;
- releases, synchronisation and drift;
- people, roles and access;
- onboarding and offboarding;
- audit, usage, costs and system health;
- integrations and source freshness.

Founder-only, engineering, product and design areas appear within the same application according to effective access. Hidden navigation is not the security control; server-side authorisation is enforced on every query and action.

## 13. Roles, permissions and access UI

The platform combines role-based access with repository assignment, content classification, action type and temporary grants.

Default templates are:

| Role | Default access |
|---|---|
| Founder administrator | All approved repositories, founder knowledge, policy, costs, publishing and approvals |
| Engineering lead | Approved repositories, team configuration, jobs and amber approvals |
| Developer | Assigned repositories, relevant engineering knowledge, own profile and permitted jobs |
| Product/design | Assigned product areas, design assets, relevant repositories and permitted workflows |
| Worker/service identity | Only the resources and actions required for one bounded purpose |

These are editable templates, not hard-coded ceilings. The system exposes stable granular capabilities from which authorised administrators may build custom roles.

Developers can launch jobs only for explicitly assigned repositories. Founder administrators can launch against all organisation repositories approved for the platform. Engineering leads can launch across the repositories included in their lead scope, which may span the full approved engineering portfolio.

### 13.1 Access Manager

The UI provides:

- people and service accounts;
- role templates and custom roles;
- repository and content assignments;
- temporary grants with reason and expiry;
- pending access requests;
- before-and-after policy diffs;
- an effective-access preview;
- bulk assignment;
- history and rollback.

Routine repository assignments may take effect immediately when performed by an authorised administrator. Founder-only access, new administrators, global policy publication, high-risk approval capability, security relaxation and expanded service identities require the configured elevated approval.

Developers may use the same UI to register their machines and edit schema-allowlisted personal preferences, assistant choices and local tool settings. They can preview the resulting configuration but cannot assign their own repositories, grant themselves capabilities or alter protected global and repository policy.

### 13.2 Sources of truth

- Permission definitions, role templates and protected policy live in `fynla-control` and publish through releases.
- User-to-role, repository and temporary assignments live in the dedicated SiteGround MySQL/MariaDB database for immediate operational enforcement.
- The dashboard manages both and creates registry revisions automatically when a version-controlled definition changes.

### 13.3 Safety controls

- The platform prevents removal of the final founder administrator.
- Administrators cannot silently elevate their own privileges.
- Access revocation invalidates sessions and worker credentials promptly.
- A worker receives a short-lived identity scoped to one requester, repository, task and expiry.
- Founder status does not cause a worker to inherit unrestricted founder knowledge.
- Permission filtering happens before search, retrieval and model context assembly.

## 14. People Lifecycle module

Onboarding, role changes and offboarding are native platform workflows rather than external SOPs.

### 14.1 Onboarding

An authorised administrator selects:

- identity and contact details;
- role, discipline and manager;
- assigned repositories and content classes;
- macOS, Windows or Linux profile;
- Codex, Claude Code or both;
- GitHub teams and permitted Slack channels;
- access-review date;
- permanent or time-limited engagement.

The platform then:

1. creates the platform identity;
2. maps Google Workspace, Slack and GitHub identities;
3. applies roles and repository assignments;
4. generates the layered developer configuration;
5. issues a short-lived single-use enrolment token;
6. provides the correct machine bootstrap process;
7. installs the MCP connection, approved assets and automatic session preflight;
8. runs the first environment validation;
9. verifies repository and assistant access;
10. records policy acknowledgements and outstanding actions.

The dashboard blocks completion while mandatory steps remain unresolved.

### 14.2 Role and team changes

The same module supports moves between teams, disciplines, repositories or employment types. It displays an effective-access diff before application and can schedule the change for a future time.

### 14.3 Offboarding

An immediate or scheduled offboarding will:

- block new platform sessions;
- revoke attestations, tokens and temporary grants;
- remove repository and restricted-content assignments;
- cancel or transfer running jobs;
- disable MCP and worker credentials;
- remove GitHub team access where the integration permits;
- stop the Slack agent acting for the identity;
- identify open branches, pull requests, owned assets and pending approvals;
- transfer ownership;
- flag shared secrets requiring rotation;
- archive the developer profile and preserve audit history;
- produce a signed completion record.

The platform cannot erase local clones or files without an endpoint-management system. Device return or wipe is therefore a mandatory tracked confirmation when applicable. External actions unavailable through an approved API remain visible checklist items and prevent false completion.

## 15. Release, synchronisation and drift prevention

Changes follow:

```text
Draft → Validate → Review → Approve → Publish → Synchronise → Monitor
```

A release contains:

- immutable Git commit SHA;
- generated Codex and Claude outputs;
- asset and file hashes;
- schema and compiler versions;
- compatibility requirements;
- approval record and change note;
- rollback reference.

The control plane changes one atomic active-release pointer only after all validation succeeds. Active jobs pin their release and cannot change instructions halfway through execution.

### 15.1 Validation

Publication checks:

- YAML, JSON, TOML and frontmatter schemas;
- required metadata and ownership;
- references and dependencies;
- protected-policy conflicts;
- secret and customer-data scanning;
- Codex and Claude compatibility;
- macOS, Windows and Linux compilation;
- golden workflows and assistant conformance;
- role and permission impact;
- generated manifest reproducibility.

A failed release leaves the prior release active. Security, source-access, tool-scope and prohibited-action changes require two-founder approval.

### 15.2 Synchronisation paths

| Consumer | Synchronisation |
|---|---|
| SiteGround control plane | Reads the active release and cached validated manifest |
| GitHub Actions worker | Downloads the job's pinned release before execution |
| Product repository | Receives an automated synchronisation PR for generated shared files |
| Developer machine | Uses the authenticated Fynla sync client for personal/local layers |
| Slack agent | Uses the active control-plane release |

Generated repository files include a managed header and release identifier. CI rejects unexplained direct edits. Changes originate from `fynla-control` and propagate through reviewable PRs rather than being manually copied between repositories.

Repository-managed files are deliberately small, stable bootstrap and enforcement files. The current SOP, prompts, skills and effective profile are supplied by the pre-launch/session-start path rather than copied wholesale into every product repository. A product-repository synchronisation PR is required only when the bootstrap, native project settings or CI enforcement itself changes. Ordinary content releases can therefore become active without creating a PR in every repository.

Each consumer has an explicit release assignment. The platform distinguishes a release that is validated and available from one that is active for a given repository, machine or service. It never reports a lagging consumer as current merely because the global release exists.

### 15.3 Drift status

Every managed machine and repository reports:

- expected and installed release;
- last successful sync;
- managed-file hashes;
- assistant and toolchain versions;
- operating system;
- modified, missing or incompatible assets;
- most recent session preflight.

The dashboard classifies state as current, outdated, modified, incompatible, blocked or offline. A failed sync retains the last known-good installation. A security-critical revocation may block an older release.

## 16. Mandatory session-start preflight

Every new or resumed Codex or Claude Code development session, whether initially read-only or write-capable, must complete the `session-start` preflight before agentic work begins.

It is implemented as:

- a managed pre-launch sync invoked before the assistant process loads project instructions;
- a reusable `session-start` skill;
- a native `SessionStart` lifecycle hook for both assistants;
- an automatic worker-bootstrap step for Slack and GitHub Actions jobs.

The pre-launch path is primary because some instructions, hooks, skills and MCP settings are loaded before a native session-start hook runs. The hook is a deterministic backstop and attestation check. If the hook corrects drift that affects startup-loaded configuration, it blocks the current session and requires one restart rather than pretending the new configuration is already active.

The manual native invocation is `/session-start` in Claude Code and `$session-start` or the equivalent skill picker in Codex. Manual invocation is a recovery and diagnostic path; enforcement does not depend on a developer remembering it. Onboarding verifies that required hooks are installed and trusted. A minimal stable bootstrap remains available even when a project-local hook changes.

### 16.1 Preflight checks

The preflight:

1. authenticates the developer and refreshes permissions;
2. identifies repository, branch, assistant, OS, discipline and profile;
3. fetches or validates the active release;
4. compiles the effective configuration;
5. compares SOP, prompt, agent, skill, hook and settings hashes;
6. atomically corrects safe managed drift;
7. fetches Git remote state and checks upstream divergence;
8. validates runtimes, build tools, assistant versions and MCP connectivity;
9. reports differences requiring human action;
10. creates a short-lived session attestation;
11. injects a concise release and policy summary into assistant context.

The default attestation lifetime is eight hours. It is revalidated on expiry, identity or repository change, session resume after expiry, and security-critical invalidation. A compaction event may reuse a valid attestation after checking its release rather than repeating expensive setup.

### 16.2 Repository safety

The preflight may fetch remote state and fast-forward only a clean, permitted branch. It never resets, discards, rebases or overwrites uncommitted or unpushed developer work automatically. Dirty state, unexpected branches, conflicts or divergence produce an explicit warning or block according to repository policy.

### 16.3 Outcomes

- **Pass:** current and ready.
- **Auto-remediated:** safe managed drift corrected and ready.
- **Warning:** non-critical difference recorded; policy decides whether work continues.
- **Blocked:** revoked access, invalid release, critical drift, incompatible environment or failed identity verification.

If the control plane is temporarily unavailable, an unexpired prior attestation may permit ordinary local work using its pinned known-good release. It cannot authorise a new remote worker, privileged connector action or changed repository scope until revalidation succeeds.

## 17. Founder and company knowledge

The integrated platform retains the founder-platform knowledge capability. Approved sources include:

| Source | Canonical role |
|---|---|
| `Fynla/Fynla` and approved repositories | Source, tests, issues, pull requests and delivery state |
| `Fynla/fynlaBrain` or successor vault repo | Shared Markdown decisions and technical knowledge |
| `Fynla/fynla-control` | Control-plane behaviour, SOPs and assets |
| Google Shared Drive | Governance, finance, marketing, product and operations |
| Allowlisted Slack channels | Conversation and source-thread context |

The index records canonical link, source version, timestamp, classification, audience, outbound-model policy and deletion state. Permission filtering occurs before retrieval. Every factual answer includes canonical links and visible freshness. The model may not fabricate citations.

The pilot `KnowledgeIndex` implementation uses SiteGround MySQL/MariaDB full-text search, structured metadata filters, source authority and freshness weighting. Indexed documents are stored as permission-tagged derived chunks in the dedicated control-plane database and can be rebuilt from canonical sources. Vector or embedding search is explicitly outside the pilot; it may be proposed later only if the full-text acceptance results demonstrate a material need.

Slack direct messages and non-allowlisted channels are excluded from shared indexing. Adding a source, repository, Drive folder or channel is a security-policy change.

## 18. MCP contract

The MCP server exposes bounded tools grouped by purpose. Initial conceptual tools are:

### Registry and configuration

- `get_active_release`;
- `get_effective_configuration`;
- `get_sync_status`;
- `get_asset`;
- `list_available_assets`.

### Knowledge

- `search_company_knowledge`;
- `fetch_canonical_source`;
- `get_source_freshness`.

### Sessions and work

- `begin_session_preflight`;
- `submit_coding_task`;
- `get_job_status`;
- `cancel_job`;
- `get_pull_request_result`.

### Proposals

- `create_action_proposal`;
- `get_proposal_status`.

Mutating tools call the common policy and job engine. MCP clients do not receive raw connector credentials or arbitrary shell, SQL, HTTP, merge, deploy, permission-change or deletion tools.

## 19. Job execution and recovery

Jobs use a durable state machine:

```text
Queued → Claimed → Running → Validating → Completed
                   ↘ Needs approval
                   ↘ Failed / Cancelled / Timed out
```

Each dispatch, claim, attempt, callback and external action carries an idempotency key. Workers claim with an expiring lease. A recovery workflow detects pending or abandoned jobs, while retry creates a linked attempt rather than erasing prior evidence.

Worker payloads contain signed identifiers rather than interpolated Slack text. The worker retrieves the full envelope through an authenticated API and checks out only allowlisted repositories. Callbacks are signed, timestamped and replay-safe.

### 19.1 Failure behaviour

| Failure | Required behaviour |
|---|---|
| SiteGround unavailable | Slack and clients retry; existing unexpired local attestations have limited offline behaviour; no new privileged job starts |
| GitHub Actions unavailable or quota exhausted | Job stays queued with visible reason; no false success or duplicate dispatch |
| Selected model unavailable | Use an approved fallback only when envelope policy permits and disclose the provider/assistant actually used |
| All models unavailable | Fail transparently and preserve retryable state |
| Full-text knowledge index degraded | Use permission-filtered canonical metadata and direct-source lookup, label the degraded state and block unsupported claims |
| Slack unavailable | Retain result in dashboard and retry thread delivery |
| Callback duplicated | Idempotency returns the prior result without repeating the action |
| Worker abandoned | Lease expires and recovery decides whether a safe retry is possible |
| Release validation fails | Keep prior active release |
| Synchronisation fails | Keep last known-good configuration and report drift |
| Access revoked during job | Cancel at the next safe boundary and revoke subsequent actions |

## 20. Security and data governance

1. Google Workspace SSO is the dashboard identity source; Slack and GitHub identities are explicitly mapped.
2. Roles and source allowlists are deny-by-default.
3. Permissions are applied before retrieval, prompt construction and tool exposure.
4. Slack, GitHub, MCP and worker callback signatures or credentials are independently revocable.
5. Secrets live only in SiteGround environment configuration, GitHub encrypted secrets or an approved secret store.
6. Untrusted Slack and source text is data, never system policy, shell input, branch naming or workflow syntax.
7. Repository, Drive, Slack and model-provider scopes use least privilege.
8. Full model request and response bodies are not retained by default.
9. Temporary content tracing requires an audited administrator action and expires within 24 hours.
10. General operational logs retain identifiers, provider alias, cost, latency, citations and outcome but redact secrets and sensitive bodies.
11. Audit events are append-only to the application role; corrections create new events.
12. Canonical edits and deletions reconcile into derived indexes.
13. Every service identity has an owner, purpose, scope, expiry or review date.
14. Security-critical revocation can invalidate sessions, releases and workers immediately.

Action audit records are retained for at least seven years unless Fynla policy requires longer. Structured operational logs are retained for 30 days by default. Job-context retention is restricted by content classification and defaults to 30 days unless a durable canonical record is required.

## 21. Monitoring, backups and operational controls

### 21.1 Monitoring

The dashboard reports:

- SiteGround CPU, memory, process, I/O and database pressure;
- Fynla customer-site response time and error rate;
- Slack acknowledgement and useful-response latency;
- MCP request latency and errors;
- queue depth and oldest waiting job;
- GitHub Actions startup, runtime and minute use;
- AI requests, tokens, failures and estimated cost;
- jobs by assistant, repository, risk and outcome;
- drift and failed preflights;
- source and connector freshness;
- active release and rollback state.

External HTTPS monitoring alerts by founder email and Slack where available.

### 21.2 Feature controls

Founders can independently disable:

- proactive Slack interjections;
- all automatic green jobs;
- Codex jobs;
- Claude jobs;
- knowledge retrieval;
- new worker dispatch;
- individual repositories or integrations.

Disabling automation does not disable audit, dashboard access or existing-result retrieval.

### 21.3 Backups and recovery

- The dedicated SiteGround SQL database receives encrypted off-site backups at least every six hours and a nightly retained snapshot.
- Retention is 30 daily and 12 monthly backups.
- Git repositories provide additional content history but do not replace database backups.
- The derived search index may be rebuilt.
- Restore drills run monthly into a clean environment.
- Initial targets are RPO six hours and RTO four hours.

## 22. Shared-hosting migration triggers

The initial shared SiteGround decision is revisited if any of these persist:

- customer-site latency materially worsens during control-plane activity;
- subscription resource usage regularly exceeds approximately 70% of allowance;
- SiteGround issues resource or fair-use warnings;
- Slack acknowledgement approaches its three-second limit;
- control-plane error rate exceeds 1%;
- routine jobs repeatedly wait more than ten minutes;
- rate limiting required to protect the customer site makes the platform impractical.

The preferred first migration is to move only the constrained component: either place the control plane on a separate SiteGround subscription or move the worker/orchestrator to a managed platform. `mcp.fynla.org`, Slack, MCP contracts and the user-facing dashboard remain stable.

## 23. Pilot cost envelope

The pilot reuses the existing SiteGround subscription, so its incremental SiteGround cost is expected to be zero. Indicative monthly incremental costs are:

| Item | Pilot estimate |
|---|---:|
| Existing SiteGround subscription | $0 incremental |
| SiteGround MySQL/MariaDB database | $0 incremental |
| GitHub Actions overage | $0–10 initially |
| Off-site monitoring/backup overhead | $0–10 |
| AI model usage cap | $100–250 |
| **Expected incremental total** | **$100–270** |

The fixed non-AI infrastructure target is approximately $0–20 per month while existing SiteGround and GitHub allowances remain sufficient. Vendor taxes and currency conversion are excluded. The dashboard exposes daily, monthly, user, repository, assistant and workflow cost. Hard budget controls may stop non-critical automation before a monthly cap is exceeded.

## 24. Verification strategy

All behavioural implementation follows test-driven development.

### 24.1 Automated tests

- role, repository, content and temporary-grant permission matrices;
- proof that permission filtering happens before retrieval and model context;
- registry schemas, ownership, dependency and protected-policy conflicts;
- deterministic Codex and Claude compilation;
- macOS, Windows and Linux path and runtime handling;
- assistant conformance using shared task envelopes;
- mandatory session preflight in clean, drifted, dirty, offline, expired and revoked states;
- Slack signature, replay, acknowledgement, deduplication and thread routing;
- risk classification, especially finance, auth, privacy, billing, migrations and infrastructure;
- job state, lease, cancellation, retry and callback idempotency;
- unsafe shell, path, branch, prompt-injection and secret payloads;
- release validation, atomic activation and rollback;
- source freshness, deletion reconciliation and provider fallback;
- SiteGround SQL full-text relevance, permission filters, indexing limits and rebuild behaviour;
- People Lifecycle onboarding, role change, scheduled revocation and offboarding;
- deployment artifact and secret-exclusion checks.

### 24.2 Acceptance environments

- dedicated fixture repositories for coding jobs;
- GitHub-hosted Linux and Windows runners;
- a real macOS developer pilot;
- approved Slack test channels;
- real GitHub sandbox issues and draft pull requests;
- Google Chrome for all dashboard visual and browser acceptance.

### 24.3 Acceptance gates

The pilot must demonstrate:

- zero unauthorised information disclosure or repository access;
- zero amber/red cases classified as auto-executable in the approved high-risk evaluation set;
- every write-capable session has a valid attestation;
- every job records requester, permissions, envelope and configuration release;
- automatic work stops at a tested draft pull request;
- replay and recovery tests produce no duplicate external action;
- Slack acknowledgement p95 remains below 2.5 seconds;
- at least 90% of the founder knowledge golden set returns an authoritative source in the first five results;
- at least 20 consecutive shadow-mode green decisions agree with the human reviewer before automation is enabled;
- no sustained, attributable customer-site p95 degradation greater than 10% during the monitored pilot;
- backup restoration and active-release rollback complete within the recovery targets;
- configuration drift is identified during session start and represented accurately in the dashboard.

## 25. Phased rollout

### Phase 1 — Foundation

- establish `fynla-control`;
- implement identity, policy, operational job state and audit;
- deploy the SiteGround control plane and dashboard skeleton;
- establish signed GitHub dispatch and callback.

### Phase 2 — Registry and developer preflight

- implement release validation and compilation;
- implement the Core, Codex and Claude SOP adapters;
- implement automatic `session-start` and attestations;
- pilot Chris's macOS profile and validate Windows/Linux outputs.

### Phase 3 — Slack assisted mode

- allowlisted channels and mapped direct messages;
- sourced answers and explicit job requests;
- manual approval before every write-capable coding dispatch;
- dashboard job and audit views.

### Phase 4 — Shadow autonomy

- classify potential green fixes without auto-starting;
- compare classification and proposed envelope with human decisions;
- tune thresholds and expand the regression corpus.

### Phase 5 — Low-risk automation

- enable green-category automatic branch, tests and draft PR;
- retain kill switches, quotas and conservative repository allowlists;
- keep merge and deployment unavailable.

### Phase 6 — Proactive Slack participation and team rollout

- enable interjection in selected channels;
- add developer, lead, product and design profiles;
- activate the full Access Manager and People Lifecycle workflows;
- onboard additional developers only after offboarding and recovery drills pass.

Each phase has an independent feature switch and rollback. A later phase does not weaken an earlier safety gate.

### 25.1 Implementation decomposition

This document is a programme-level architecture and is intentionally broader than one safe implementation batch. Delivery is divided into bounded implementation units, each receiving its own detailed implementation plan and, where new product behaviour remains undecided, a focused subordinate design:

1. canonical registry, schemas, compiler and release validator;
2. SiteGround identity, policy, MCP and durable job control plane;
3. signed GitHub dispatch, callback, lease and recovery workers;
4. Codex/Claude adapters, local synchronisation and session attestations;
5. unified dashboard, Access Manager and People Lifecycle;
6. Slack answer, participation, steering and proposal workflows;
7. company-knowledge ingestion and permission-filtered retrieval;
8. green automation, monitoring, backups and operational hardening.

The first implementation plan covers only unit 1 plus the minimum interfaces needed to prove its outputs. Later units may depend on its published schemas but may not redefine its protected-policy semantics ad hoc.

## 26. Alternatives considered

### 26.1 Separate founder and developer platforms

Rejected because it duplicates identity, policy, configuration, retrieval and audit surfaces and invites behavioural drift. One role-scoped application provides different experiences without creating two systems.

### 26.2 Dedicated PaaS or VPS immediately

Deferred. It provides persistent workers and stronger resource isolation but increases fixed cost before usage justifies it. The SiteGround gateway plus GitHub Actions design satisfies the pilot requirements with explicit migration triggers.

### 26.3 Separate SiteGround subscription immediately

Deferred at Chris's direction. It would improve resource isolation, but the existing subscription will be monitored first.

### 26.4 Long-running agents on SiteGround

Rejected because shared-hosting request limits and the lack of the required persistent Node.js, Python and WebSocket runtime make it unsuitable. SiteGround remains the public control plane; GitHub Actions performs long work.

### 26.5 Manual configuration copying

Rejected because it directly creates the drift this platform is intended to prevent. Managed releases, generated outputs, session preflight and drift reporting replace manual copying.

### 26.6 One generic SOP for both assistants

Rejected because Codex and Claude Code expose different native configuration and extension surfaces. A common normative core plus native adapters preserves policy while allowing each assistant to perform well.

### 26.7 Relying on developers to remember `/session-start`

Rejected because an optional instruction cannot enforce freshness. The skill remains available, but native hooks and worker bootstrap provide deterministic enforcement.

### 26.8 External PostgreSQL/pgvector during the pilot

Deferred at Chris's direction. The initial control plane and derived knowledge index use the dedicated SiteGround MySQL/MariaDB database. A separate vector database adds cost and operational complexity before the full-text quality gates demonstrate that it is needed. It may be reconsidered through a later approved design without changing canonical sources or the `KnowledgeIndex` contract.

## 27. Authoritative references

- [Model Context Protocol specification](https://modelcontextprotocol.io/specification/2026-07-28)
- [Slack Events API](https://api.slack.com/apis/connections/events-api)
- [SiteGround Node.js availability](https://www.siteground.com/kb/node-js-available/)
- [SiteGround WebSocket limitations](https://www.siteground.com/kb/can-host-websocket-server/)
- [SiteGround shared-server timeout](https://www.siteground.com/kb/what_is_the_apache_timeout_on_the_shared_servers/)
- [SiteGround fair-use policy](https://www.siteground.com/kb/fair-use-siteground-hosting/)
- [GitHub Actions billing](https://docs.github.com/en/billing/concepts/product-billing/github-actions)
- [Codex `AGENTS.md`](https://learn.chatgpt.com/docs/agent-configuration/agents-md)
- [Codex configuration](https://learn.chatgpt.com/docs/config-file/config-basic)
- [Codex hooks](https://learn.chatgpt.com/docs/hooks)
- [Claude Code settings](https://code.claude.com/docs/en/settings)
- [Claude Code hooks](https://code.claude.com/docs/en/hooks-guide)
- [Claude Code extension surfaces](https://code.claude.com/docs/en/features-overview)

## 28. Definition of done

The integrated control plane is complete for initial team rollout when:

1. founders and developers authenticate to one role-scoped dashboard;
2. Access Manager can create, preview, apply, expire and revoke roles and assignments;
3. onboarding and offboarding execute through the People Lifecycle module with complete evidence;
4. `fynla-control` publishes immutable, validated and reversible releases;
5. Codex and Claude Code receive native outputs from the same mandatory policy core;
6. every write-capable session completes automatic preflight and records an attestation;
7. macOS, Windows and Linux configurations pass their conformance suites;
8. Slack can answer, participate, dispatch, report and interrupt within permission boundaries;
9. green jobs can create tested draft pull requests automatically without merge or deployment capability;
10. amber and red gates reject or pause unauthorised actions;
11. company knowledge answers are permission-filtered, cited and freshness-aware;
12. jobs, approvals, access decisions, releases and external results are reconstructable from audit evidence;
13. drift, resource usage, customer-site impact and cost are visible in the dashboard;
14. backup restore, failed-job recovery, provider fallback and release rollback pass their drills;
15. the customer application and all production customer/CoALA runtime data remain isolated and untouched.
