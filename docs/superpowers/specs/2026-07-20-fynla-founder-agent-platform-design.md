# Fynla Founder-Agent Platform — Design Specification

**Date:** 20 July 2026

**Status:** Approved design

**Founders:** Azlan Raj (CMO), Brett Isenberg (CFO), Chris Slater-Jones (CTO)

**Primary interface:** Slack

**Public service endpoints:** `mcp.fynla.org` and `agents.fynla.org`

## 1. Decision

Fynla will build a lightweight, self-hosted founder-agent platform on a dedicated Fynla-owned VPS. Slack is the shared daily interface. Google Workspace, GitHub and the shared Obsidian vault remain the canonical stores for company knowledge and work. The platform retrieves across those stores, answers with direct source links, and proposes correctly routed actions for founder approval.

The platform consists of:

- a Slack agent using Slack Bolt and Socket Mode;
- a FastMCP server using authenticated Streamable HTTP;
- LiteLLM as the model-provider gateway;
- a source ingestion and indexing service;
- PostgreSQL with full-text search and pgvector;
- a private `fynla-agents` GitHub repository for versioned prompts and configuration;
- a small Laravel, Inertia and Vue 3 dashboard for editing, publishing, history and rollback;
- direct, least-privilege API integrations with Google Workspace, GitHub and Slack.

Onyx is not part of the selected architecture. Zapier is not required for core workflows. Notion, Guru, Confluence and NotebookLM are not canonical systems in this design.

## 2. Critical separation from Fyn and CoALA

This founder platform is company collaboration infrastructure. It is not Fyn's in-app AI memory architecture.

Fyn's procedural, semantic, episodic and working-memory modules remain part of the customer-facing CoALA-based architecture described in `docs/superpowers/specs/2026-07-10-fyn-evidence-first-advice-design.md` and the CoALA phase specifications. Those modules contextualise each user's financial data and support evidence-first answers inside the Fynla application.

The founder platform must not:

- read or index production customer records;
- read or index per-user Fyn memory, episodes, advice cases or signed attestations;
- write to Fyn's procedural, semantic, episodic or working-memory stores;
- expose the Fynla application's internal tools as founder-platform tools;
- share database credentials, queues, deployment processes or runtime state with `fynla.org`;
- describe its company-knowledge index as Fyn or CoALA memory.

Global product documentation about the CoALA architecture may be indexed from the approved GitHub repositories and vault because it is company knowledge. Runtime customer data and runtime memory are always excluded.

## 3. Existing constraints

The live application remains on SiteGround shared hosting. `CLAUDE.md` and `deploy/DEPLOYMENT_FYNLA_ORG.md` confirm that production is a Laravel 10, Vue 3 and MySQL application deployed to shared hosting with constrained server-side build and process support.

Therefore:

- `fynla.org` remains unchanged on SiteGround;
- SiteGround DNS supplies A records for the new subdomains;
- the founder-agent services run on a separate VPS that supports persistent processes, WebSockets, PostgreSQL and background workers;
- the founder platform has its own database, secrets, backups and release lifecycle;
- a founder-platform failure must not affect the customer application.

The existing local vault gateway design in `docs/superpowers/specs/2026-03-25-vault-gateway-design.md` remains useful for local coding sessions. The new platform extends the underlying goal to all founders by making a private GitHub vault repository the shared copy rather than depending on Chris's local machine.

## 4. Goals

1. Give all three founders one low-learning-curve experience through Slack.
2. Search the whole approved company corpus with citations and freshness information.
3. Detect issues, features, decisions and schedules in selected Slack channels in near real time.
4. Route durable outcomes to the correct canonical system after approval.
5. Allow each founder to create and assign work without learning agent syntax.
6. Keep prompts, routing, tools and model aliases version-controlled and auditable.
7. Permit model-provider changes without changing the Slack workflow or tool contracts.
8. Keep the derived search index rebuildable from canonical sources.
9. Prevent silent, duplicate or unauthorised writes.
10. Preserve the current application and CoALA boundaries.

## 5. Non-goals

- Replacing Slack, Google Workspace, GitHub or Obsidian with a new all-in-one workspace.
- Building a general-purpose enterprise search product.
- Giving an agent permission to merge code, deploy software, change permissions or delete records.
- Indexing personal drives, personal Slack conversations or repositories that are not explicitly allowlisted.
- Providing customer-facing financial guidance.
- Making the dashboard another day-to-day chat interface.
- Hosting local inference models in the first release.
- Making Claude Desktop, ChatGPT, Codex or another vendor client mandatory.

## 6. Founder experience

### 6.1 Normal workflow

1. A founder posts normally in Slack about a question, bug, feature, decision or schedule.
2. The agent classifies the post and searches the current approved corpus.
3. It replies in the thread with a concise answer, direct source links, source freshness and any likely duplicate.
4. If a durable action is appropriate, it shows the destination, assignee, title and payload before doing anything.
5. The requesting founder reacts ✅ to the proposal message or replies `approve` in the same thread.
6. The platform executes the immutable proposal once, records the approver and result, then posts the permanent link back to Slack.

No slash-command syntax is required for ordinary use. Mentions remain available when a founder wants an explicit response.

### 6.2 Slack channel policy

Initial channel roles are:

| Channel | Agent behaviour |
|---|---|
| `#fynla-testing` | Classify every new founder message; interject for questions, bugs and test observations. |
| `#fynla-product` | Classify every new founder message; interject for features, product decisions, dependencies and schedules. |
| `#fynla-agents` | Publish configuration releases, indexing status, warnings, action failures and recovery notices. |
| Other invited channels | Respond only to an explicit mention or direct question addressed to the agent. |

The allowlist and interjection confidence threshold live in `fynla-agents`. The initial actionable threshold is `0.80`. Below that threshold the agent stays silent unless explicitly mentioned. Classification may occur automatically, but a classification never authorises a write.

The agent replies in threads to avoid channel noise. It does not index private messages or uninvited channels.

### 6.3 Suggested assignment

The agent proposes an assignee from founder responsibilities:

| Work domain | Default suggested owner |
|---|---|
| Marketing, brand, acquisition, campaigns, communications | Azlan Raj |
| Finance, commercial planning, budgets, funding, financial controls | Brett Isenberg |
| Product engineering, architecture, security, infrastructure, releases | Chris Slater-Jones |

The founder can correct the destination or assignee in plain language before approval. Cross-functional items use one primary owner and mention the other relevant founders. The agent never changes GitHub or Drive access based on this routing table.

## 7. Canonical information and routing

Slack is the conversation layer, not the permanent home for decisions or work.

| Information or event | Canonical destination | Agent action after approval |
|---|---|---|
| Bug, failure or test observation | GitHub Issue | Create a deduplicated issue with evidence, source thread, labels and assignee. |
| Feature or product work | GitHub Issue and Fynla Product Project | Create or link the issue and set its initial project fields. |
| Technical decision or durable engineering learning | Shared Obsidian vault in `fynla-vault` | Open a pull request containing the proposed Markdown change. |
| Business, marketing, finance or governance decision | Relevant Fynla Google Shared Drive folder | Create a decision record or append to the designated decision log. |
| Project milestone or delivery date | Fynla Product Project | Update the project date/status; create a linked Calendar event only when a timed reminder or meeting is useful. |
| Meeting or timed commitment | Google Calendar | Create a linked event with source and project references. |
| Ordinary question or transient discussion | Slack thread | Answer with citations; no durable write. |
| Agent prompt, routing, model or tool configuration | `fynla-agents` repository | Create, validate and publish a versioned configuration release. |

GitHub Project remains the truth for delivery status. Calendar is a reminder surface, not a competing project plan.

## 8. Shared source layout

### 8.1 GitHub

The GitHub organisation contains, at minimum:

- `fynla`: application source, issues and pull requests;
- `fynla-vault`: shared Obsidian Markdown and approved attachments;
- `fynla-agents`: prompts, schemas, routing, source allowlists, model aliases and tests.

An organisation-level `Fynla Product` Project tracks product work and milestones. A GitHub App is installed only on the approved repositories and project. Fine-grained app permissions replace broad personal access tokens.

### 8.2 Obsidian

Chris's local `fynlaBrain` vault is migrated into the private `fynla-vault` repository after a secret, credential, customer-data and personal-file review. The repository becomes the shared canonical vault. Each founder may clone it and use Obsidian, another Markdown editor or the dashboard; Obsidian itself is optional.

Local Obsidian directories such as workspace state, caches and personal-only notes are excluded. Attachments are included only when they are company records and safe for all founders. Agent changes always arrive as pull requests and never commit directly to the protected default branch.

### 8.3 Google Workspace

Company documents live in a Fynla Google Shared Drive rather than a founder's My Drive. The initial top-level folders are:

1. Company & Governance
2. Finance
3. Marketing & Sales
4. Product & Research
5. Operations

The connector service account is a member only of this Shared Drive. It has no domain-wide delegation and cannot search personal drives. New agent-authored documents state which founder requested and approved them.

### 8.4 Slack

Only allowlisted channels and their threads are indexed. Slack message identifiers, channel, thread, author, timestamps, edits and deletion events are preserved as source metadata. A deleted source message is removed from the derived index during reconciliation.

## 9. Architecture

```text
Founders in Slack
       │
       ▼
Slack Bolt worker ───────────────┐
       │                         │
       ▼                         ▼
Founder-agent API          FastMCP /mcp
       │                         │
       ├──────────┬──────────────┤
       ▼          ▼              ▼
 Retrieval    Action engine   LiteLLM proxy
       │          │              │
       └──────┬───┴──────────────┘
              ▼
 PostgreSQL + pgvector + audit + durable jobs
              ▲
              │
  Ingestion workers and source webhooks
              │
     ┌────────┼───────────┬───────────┐
     ▼        ▼           ▼           ▼
 Google    GitHub     Obsidian repo   Slack
 Drive     + Project                  channels

agents.fynla.org → Laravel/Inertia/Vue dashboard → fynla-agents repo
```

### 9.1 VPS and network

The company provisions one dedicated Linux VPS under a Fynla-owned account. The initial target is 4 vCPU, 8 GB RAM and at least 80 GB SSD. A 4 GB instance may be used only if corpus benchmarking, indexing and restore tests remain within the acceptance limits. Model inference and embedding generation use external approved providers in the first release.

SiteGround DNS points:

- `mcp.fynla.org` to the FastMCP and service API entry point;
- `agents.fynla.org` to the configuration dashboard.

Caddy terminates TLS and proxies only the required HTTPS routes. PostgreSQL and internal service ports bind to the private container network or loopback and are never internet-accessible. SSH uses keys only. The firewall exposes HTTPS and a restricted administrative SSH path.

Public webhook routes are limited to the approved GitHub/configuration callbacks. Each request must pass provider signature verification before its delivery ID enters the durable queue; duplicate delivery IDs are acknowledged without reprocessing. Slack uses Socket Mode and Google Drive uses its changes feed, so neither requires a public inbound webhook in the first release.

The deployment uses a reproducible container composition with these logical services:

- Caddy;
- founder-agent API / FastMCP ASGI application;
- Slack worker;
- ingestion and durable-job worker;
- LiteLLM proxy;
- PostgreSQL with pgvector;
- Laravel/Inertia/Vue dashboard.

PostgreSQL-backed durable jobs and advisory locks avoid adding Redis to the first release.

### 9.2 FastMCP

FastMCP exposes the portable tool contract at `https://mcp.fynla.org/mcp`. The server uses Streamable HTTP, not the legacy SSE transport. Caddy must disable proxy buffering for streamed responses and preserve the MCP session headers. Browser origins are explicit; wildcard production CORS is forbidden.

The first release exposes:

- source search and fetch tools;
- project, issue, document and decision lookup tools;
- source freshness and connector status tools;
- action-proposal tools that create a pending approval in Slack.

It does not expose raw connector mutation methods. Optional clients such as Claude Desktop, Claude Code, Codex or another MCP-capable product may search and submit a proposal, but the proposal is approved and executed through the common Slack workflow.

Each founder receives a separate high-entropy bearer token. Tokens are hashed at rest, scoped, revocable, last-used audited and rotated at least every 90 days. Slack, dashboard and background workers use separate service identities. If the platform expands beyond the three founders or becomes externally accessible, OAuth replaces founder bearer tokens before adding users.

### 9.3 Slack transport

The private Slack app uses Bolt and Socket Mode. This keeps Slack event delivery on an authenticated outbound WebSocket and avoids exposing another public request URL. The worker acknowledges events promptly, stores the Slack event ID and processes the event through the durable PostgreSQL job queue.

The bot receives only the granular scopes required for allowlisted channel history, mentions, messages, reactions and posting replies. It is invited only to approved channels.

### 9.4 LiteLLM and model neutrality

All language-model and embedding calls pass through LiteLLM model aliases. Application code refers to capabilities such as:

- `founder-answer-primary`;
- `founder-answer-fast`;
- `founder-embedding-v1`.

Aliases map to an ordered list of approved provider deployments. Timeouts, retries, fallbacks, spend limits and request metadata are configured centrally. Provider API keys never appear in prompts, Git or Slack.

Changing the answer provider requires a validated configuration release, not application code. Changing the embedding provider requires a versioned re-index because dimensions and vector spaces can differ. The index stores the embedding alias, provider deployment, model, dimensions and generation timestamp. A migration builds a parallel index, runs retrieval acceptance tests, then atomically changes the active embedding version.

The platform sends only the retrieved fragments needed for a response, not the complete corpus. Per-source outbound policies can be `allowed`, `metadata-only` or `local-only`. Only providers approved for Fynla company data may be configured.

### 9.5 PostgreSQL, pgvector and retrieval

PostgreSQL stores:

- source and connector definitions;
- documents, versions and canonical links;
- extracted chunks and full-text indexes;
- embeddings and embedding-version metadata;
- sync runs, webhooks and source freshness;
- Slack event processing state;
- immutable action proposals and payload hashes;
- approvals, executions and append-only audit events;
- configuration releases and active-release pointers;
- hashed founder and service credentials.

The index is derived and rebuildable. Canonical files remain in Drive, GitHub, the vault repository or Slack.

Retrieval combines PostgreSQL full-text search with pgvector similarity. Results are merged using reciprocal-rank fusion, filtered by source allowlist and classification, and reranked for source authority and freshness. Every factual answer includes direct canonical links. The model may not invent a citation or cite a search chunk without its canonical source record.

Incremental sync targets are:

- Slack: event-driven, with hourly reconciliation;
- GitHub and vault: webhook-driven, with 15-minute reconciliation;
- Google Drive: changes feed every 15 minutes;
- configuration repository: webhook-driven on validated publication.

Each connector persists its own delta cursor and source version/checksum. A reconciliation never marks a run successful until additions, edits and deletions are committed together, so a partial sync cannot appear current.

A relevant source is stale when its most recent successful reconciliation is more than two hours old or its latest sync is in an error state. Search may still return it with a visible stale warning. A context-dependent write is blocked until the relevant source is current.

## 10. Agent configuration repository

The private `fynla-agents` repository is the single source of truth for agent behaviour. Its initial structure is:

```text
agents/
prompts/
routing/
sources/
models/
tools/
schemas/
tests/golden/
```

It contains no secrets.

### 10.1 Draft, validate and publish

1. A founder edits through the dashboard or a normal Git branch.
2. Every draft has an author and required one-line change note.
3. Validation checks YAML/JSON schemas, referenced prompt files, model aliases, source routes, tool permissions and golden prompt/tool cases.
4. A successful publish identifies one immutable commit SHA.
5. The server checks out that commit into a new release directory and runs validation again.
6. The active-release pointer changes atomically only after all checks pass.
7. `#fynla-agents` receives the author, change note, commit link and affected behaviour.

A failed release never partially changes live behaviour. Rollback reactivates the previous validated commit and records a new audit event; no one edits live files directly.

Prompt wording, channel thresholds and ordinary routing changes may be published by any founder after validation. Changes to write-tool scopes, source access, security controls or the prohibited-action list require approval from a second founder, including Chris for infrastructure/security changes.

### 10.2 Dashboard

`agents.fynla.org` is a separate Laravel, Inertia and Vue 3 application, not a route inside the customer application. It provides:

- prompt and agent library with Markdown preview;
- forms for channel behaviour, model aliases, routes and allowed sources;
- draft versus published state;
- validation results;
- Git history and rendered diffs;
- publish and rollback controls;
- action and connector status views.

Founders sign in with Google Workspace and an explicit founder allowlist. The dashboard commits through the GitHub App and never writes directly to the running agent service.

## 11. Approval and action model

### 11.1 Autonomy boundaries

| Behaviour | Policy |
|---|---|
| Search, fetch, summarise and cite | Automatic |
| Classify a Slack message | Automatic |
| Detect a likely duplicate | Automatic |
| Propose a destination, assignee and payload | Automatic |
| Post connector, indexing or release notices | Automatic |
| Fail over to an approved model deployment | Automatic |
| Create or change a canonical record | Founder approval required |
| Change source access, tool scope or security policy | Two-founder configuration approval required |
| Merge code or vault pull requests | Prohibited |
| Deploy code or change production state | Prohibited |
| Change permissions or connector membership | Prohibited |
| Delete files, issues, events or records | Prohibited |
| Read or write production customer or CoALA runtime data | Prohibited |

### 11.2 Immutable proposal

Each proposed write contains:

- proposal ID;
- source Slack workspace, channel, thread and message;
- requesting founder;
- destination and action type;
- structured payload preview;
- suggested assignee;
- source citations;
- configuration release SHA;
- model and retrieval metadata;
- payload hash and idempotency key;
- expiry time, initially 24 hours.

Approval applies to that exact payload hash. Editing the title, body, destination, assignee or dates creates a new proposal and invalidates the prior approval.

A ✅ reaction applies only to the bot proposal message that received it. A thread reply of `approve` is accepted only when the thread has exactly one unexpired proposal. Approval must come from a recognised founder. The resulting canonical record identifies both the requester and approver when they differ.

### 11.3 Initial write tools

The first write-enabled release supports only:

- create a GitHub issue;
- set issue labels and one assignee;
- add an issue to the Fynla Product Project and set initial status/date fields;
- create a Google Drive decision record in an allowlisted folder;
- append an entry to a designated Drive decision log;
- open a pull request against `fynla-vault`;
- create or update a linked Google Calendar event.

It cannot modify application code, push to protected branches, merge pull requests, close issues, overwrite arbitrary Drive documents or cancel events. Each additional mutation is a separate tool-scope change requiring tests and two-founder approval.

### 11.4 Idempotency and duplicate prevention

The idempotency key combines the source Slack message, action type, destination and payload hash. An approved proposal can execute successfully only once. Timeouts and API retries first check the destination and prior execution record. The agent searches for likely existing issues or decisions before presenting a new proposal and offers to link the existing item when appropriate.

## 12. Security and data governance

1. All traffic uses TLS; database and internal services are not public.
2. Secrets live in the VPS secret store or root-owned environment files, never Git, prompts or Slack.
3. Founder, Slack, GitHub and Google identities are distinct and independently revocable.
4. GitHub uses a least-privilege GitHub App; Google uses a service account limited to the Fynla Shared Drive; Slack uses granular bot scopes.
5. Source allowlists are deny-by-default. Adding a repository, Drive folder or Slack channel is a security configuration change.
6. Ingestion runs a secret and customer-data check. Suspected credentials, production exports and customer records are quarantined from retrieval and reported in `#fynla-agents` without reproducing the sensitive value.
7. Documents carry `internal`, `confidential` or `restricted` classification plus an outbound-LLM policy.
8. Full LLM request and response bodies are not retained by default. Operational logs keep request ID, founder, provider alias, model deployment, token/cost metadata, latency, citations and outcome.
9. Temporary content tracing requires an explicit administrator action, expires within 24 hours and is access-audited.
10. Canonical deletions and Slack edits propagate to the derived index during reconciliation.
11. Audit events are append-only to the application role; corrections are new events, never destructive edits.

## 13. Failure and recovery behaviour

| Failure | Required behaviour |
|---|---|
| Primary model unavailable | LiteLLM tries the approved fallback and records the deployment used. |
| All approved models unavailable | Reply that answering is temporarily unavailable; do not fabricate or write. |
| Relevant source stale or unavailable | Show last successful sync and block context-dependent writes. |
| Slack event redelivered | Event ID and idempotency controls prevent duplicate processing. |
| Connector write times out | Check destination and execution log before retrying. Never claim success without a canonical link. |
| Configuration validation fails | Keep the previous active commit; publish failure details to `#fynla-agents`. |
| New release becomes unhealthy | Automatically reactivate the last healthy validated release. |
| Founder token revoked | Reject access immediately without affecting other founders. |
| VPS unavailable | Slack, Drive and GitHub continue normally; an external monitor alerts founders independently of the VPS. |
| Database lost | Restore encrypted backup, rebuild the derived index and reconcile canonical systems before enabling writes. |

## 14. Backups, monitoring and operations

- PostgreSQL receives encrypted off-site backups at least every six hours and a nightly retained snapshot.
- Retention is 30 daily backups and 12 monthly backups.
- The `fynla-agents` and `fynla-vault` histories are additionally preserved by GitHub.
- The derived document index may be rebuilt; proposals, approvals, executions, credentials and audit events must be restored.
- A restore drill into a clean environment runs monthly.
- Recovery targets are RPO six hours and RTO four hours.
- Health endpoints cover database, queue lag, Slack connection, source freshness, active configuration release and LiteLLM readiness.
- An external HTTPS monitor alerts by founder email and, when possible, Slack.
- Structured operational logs are retained for 30 days; action audit records are retained for at least seven years unless company policy sets a longer period.
- `#fynla-agents` receives actionable warnings, not routine successful sync noise.

## 15. Acceptance gates

### 15.1 Knowledge quality

- A founder-approved golden set contains at least 30 questions spread across Drive, GitHub, Slack and the vault.
- At least 90% of questions return the authoritative source in the first five retrieval results.
- Every factual answer contains working canonical links and visible source dates.
- Tests cover duplicate names, contradictory documents, superseded decisions, edited Slack messages and deleted sources.
- Prompt-injection text inside indexed documents cannot change tool policy or authorise a write.
- Stale and unavailable sources are labelled and enforce the write block.

### 15.2 Action safety

- Every write tool is proven unable to execute before valid founder approval.
- Replayed Slack events, repeated reactions and connector timeouts create no duplicates.
- Requester, approver, payload hash, active configuration SHA and result link are reconstructable for every execution.
- Unauthorised users, expired proposals, revoked tokens and altered payloads are rejected.
- GitHub, Drive, Calendar and vault permissions are tested against resources outside their allowlists.
- No write tool can merge, deploy, delete, change permissions or reach production customer data.

### 15.3 Provider independence

- The default answer alias is switched between two approved model providers without changing Slack or tool code.
- Provider failure invokes the configured fallback and is visible in audit metadata.
- A second embedding deployment can build a parallel version, pass the golden retrieval set and be activated without losing the prior index.
- No prompt, Slack message or tool schema contains a hard-coded commercial model name.

### 15.4 Operations and recovery

- The full stack is recreated from version-controlled deployment definitions on a clean VPS.
- A database backup restores within the four-hour RTO.
- The corpus is rebuilt and reconciled from canonical sources.
- A bad configuration release is rejected without service drift.
- A healthy prior release can be reactivated and produces the same golden-case results.
- Sustained indexing plus normal founder use stays below 70% memory and 70% disk; otherwise the VPS is resized before rollout.

## 16. Delivery sequence

1. Create governance rules, source classifications, the three GitHub repositories and the `fynla-agents` schemas.
2. Provision the VPS, DNS, TLS, firewall, secrets, monitoring and backup/restore path.
3. Deploy PostgreSQL/pgvector and implement source ingestion, reconciliation and hybrid retrieval.
4. Implement authenticated FastMCP read and proposal tools.
5. Deploy LiteLLM aliases, limits, metadata and provider-fallback tests.
6. Connect Slack in read-only answer mode and tune the golden knowledge set and interjection threshold.
7. Add approval-gated write tools one destination at a time, starting with GitHub issues.
8. Build the Laravel/Inertia/Vue dashboard over the Git-backed configuration workflow.
9. Enable release notices, rollback and full founder rollout after all acceptance gates pass.

Write access remains disabled until the read-only knowledge gates pass. Each connector's mutations remain disabled until its own action-safety tests pass.

## 17. Alternatives considered

### Onyx

Onyx Standard is not suited to the intended small initial server: its current documented minimum is 10 GB RAM and preferred size is 16+ GB. Onyx Lite is smaller and could fit from a resource perspective, but it still introduces another product surface and duplicates retrieval, prompt and workflow controls that Fynla needs to own. The approved design therefore uses the narrower custom stack. See [Onyx resourcing](https://docs.onyx.app/deployment/getting_started/resourcing).

### Notion, Guru and Confluence

These can be useful authoring or knowledge products, but none is selected as Fynla's orchestration and action gateway. Adding one would create another canonical location and migration burden. Google Shared Drive, GitHub and the shared Markdown vault already match the founders' business, engineering and technical-note needs.

### NotebookLM

NotebookLM may be used by a founder for ad hoc research, but it is not a shared system of record, action engine, provider-neutral gateway or auditable configuration store.

### Direct ChatGPT or Claude clients

Individual AI accounts may remain optional MCP clients. They are not the team entry point because subscription levels, available connectors, model providers and histories differ between founders. Slack plus the Fynla gateway supplies the common experience and audit trail.

### Zapier

Core GitHub, Google and Slack actions use their direct APIs through bounded tools. Zapier may be added later for a low-risk SaaS that lacks a suitable API, but no core workflow or approval boundary depends on it.

## 18. Authoritative technical references

- [FastMCP HTTP deployment](https://gofastmcp.com/v2/deployment/http) — Streamable HTTP, authentication, health checks, stateless scaling and reverse-proxy requirements.
- [FastMCP bearer authentication](https://gofastmcp.com/clients/auth/bearer) — authenticated HTTP client transport.
- [LiteLLM getting started](https://docs.litellm.ai/) — provider gateway, routing, cost tracking and compatible errors.
- [LiteLLM provider fallbacks](https://docs.litellm.ai/docs/proxy/reliability) — ordered provider/model failover.
- [Slack Socket Mode](https://docs.slack.dev/apis/events-api/using-socket-mode/) — private WebSocket event delivery, granular permissions and Bolt support.
- [Slack Events API](https://api.slack.com/apis/connections/events-api) — event acknowledgement, retries and permission model.
- [pgvector](https://github.com/pgvector/pgvector) — PostgreSQL vector search and hybrid use with full-text search.
- [GitHub Issues REST API](https://docs.github.com/en/rest/issues/issues) — issue creation and update capabilities with fine-grained credentials.
- [Google Drive uploads](https://developers.google.com/workspace/drive/api/guides/manage-uploads) — file creation and content upload.
- [Google Calendar event creation](https://developers.google.com/workspace/calendar/api/guides/create-events) — linked calendar events.

## 19. Definition of done

The founder-agent platform is complete for initial rollout when:

1. all three founders can use the selected Slack channels without special agent syntax;
2. grounded answers meet the knowledge acceptance gates across every approved source;
3. GitHub issues, project fields, Drive decision records, vault pull requests and Calendar events execute only through immutable approved proposals;
4. every action is attributable, idempotent and linked back to its Slack thread;
5. prompts and behaviour publish from a validated Git commit with visible history and rollback;
6. the default model provider can be changed without changing the founder workflow;
7. backup restore, index rebuild and prior-release recovery pass their drills;
8. the customer application and all Fyn CoALA runtime memory remain isolated and untouched.
