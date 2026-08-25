# Fynla Control Plane S03 SiteGround SQL Knowledge Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the current PostgreSQL/embedding knowledge path with a rebuildable, permission-filtered SiteGround MySQL/MariaDB FULLTEXT index that returns authoritative links and visible freshness.

**Architecture:** GitHub Actions connectors fetch allowlisted canonical sources and send signed batches to narrow gateway APIs. The gateway applies source policy and writes derived chunks. Search uses structured permission filters before context assembly, MariaDB FULLTEXT relevance plus authority/freshness weights, and a degraded direct-source path when the index is unhealthy.

**Tech Stack:** MariaDB 11-compatible SQL, PHP PDO repositories, Python 3.12 workers using `httpx`, GitHub/Google/Slack APIs, PHPUnit, pytest.

## Global Constraints

- Programme: [`2026-08-11-fynla-integrated-ai-control-plane-programme.md`](2026-08-11-fynla-integrated-ai-control-plane-programme.md).
- Depends on S02-PR02 policy and S02-PR05 signed worker callbacks.
- Repository: `Fynla/FynlaMCP`.
- No PostgreSQL, pgvector, `DATABASE_URL`, embedding model or direct worker database credential remains in the pilot runtime.
- Only allowlisted repositories, Shared Drive folders and Slack channels are ingested.
- Slack DMs and non-allowlisted channels are excluded by default.
- Production customer and CoALA runtime data is excluded at source registration and ingestion.
- Derived rows are deletable/rebuildable; canonical content remains in source systems.

---

## File Structure

```text
gateway/database/006_knowledge_index.sql
gateway/src/Knowledge/{Source,SourcePolicy,IngestionService,KnowledgeRepository,SearchService,Citation}.php
gateway/src/Worker/KnowledgeBatchController.php
gateway/tests/{Unit,Feature,Integration}/Knowledge/
src/fynla_agent/gateway/knowledge_client.py
src/fynla_agent/ingestion/{models,chunking,github,drive,slack,runner}.py
src/fynla_agent/retrieval/evaluation.py
tests/{unit,integration,golden}/knowledge/
tests/golden/knowledge/cases.yaml
docs/implementation-evidence/s03/
```

## PR Register

| PR | Outcome | Depends on | State |
|---|---|---|---|
| S03-PR01 | Knowledge schema and signed ingestion API | S02-PR02, S02-PR05 | Not started |
| S03-PR02 | Git source sync, chunking and deletion | S03-PR01 | Not started |
| S03-PR03 | Shared Drive and Slack connectors | S03-PR02 | Not started |
| S03-PR04 | Permission-filtered FULLTEXT search and citations | S03-PR01 | Not started |
| S03-PR05 | Golden evaluation and degraded behaviour | S03-PR03, S03-PR04 | Not started |

## S03-PR01 — Establish derived knowledge storage and ingestion boundary

**Branch:** `codex/icp-s03-pr01-knowledge-schema-api`

**Traceability:** `KNW-01..06`, `SEC-10`, `ARC-02`.

**Acceptance:** The gateway registers allowlisted sources and atomically accepts idempotent, signed document batches with classification/audience tags, while workers have no SQL credentials.

### Task S03-PR01-T01 — Add the MariaDB knowledge schema

**Files:** `gateway/database/006_knowledge_index.sql`, `gateway/tests/Integration/Knowledge/KnowledgeMigrationTest.php`.

Create `knowledge_sources`, `knowledge_source_versions`, `knowledge_sync_runs`, `knowledge_documents`, `knowledge_chunks`, `knowledge_chunk_audiences`, `knowledge_chunk_resources`, `knowledge_tombstones`. Add `FULLTEXT(title, body)` to chunks, unique `(source_id, canonical_id, source_version)`, UTC timestamps and cascade only for derived child rows.

- [ ] Add `information_schema` assertions for tables, constraints, FULLTEXT index and utf8mb4 collation.
- [ ] Run the focused MariaDB test; expect missing-table failure.
- [ ] Add migration 006; do not add an embeddings or vectors column.
- [ ] Test a source cannot be deleted while canonical audit metadata references it; derived chunks may be rebuilt.
- [ ] Re-run the focused test; expect pass.
- [ ] Commit `[ICP S03/PR01/T01] Add permission-tagged FULLTEXT schema`.

### Task S03-PR01-T02 — Implement signed atomic batch ingestion

**Files:** `gateway/src/Knowledge/SourcePolicy.php`, `IngestionService.php`, `gateway/src/Worker/KnowledgeBatchController.php`, `gateway/tests/Feature/Knowledge/KnowledgeBatchApiTest.php`.

```php
final readonly class IndexedDocumentInput {
    public function __construct(
        public string $canonicalId,
        public string $canonicalUrl,
        public string $sourceVersion,
        public string $classification,
        public array $audiences,
        public array $resources,
        public array $chunks,
        public bool $deleted,
    ) {}
}
```

- [ ] Test signature/timestamp/replay handling, unknown source, disallowed classification, disallowed URL host, duplicate batch and partial-invalid batch.
- [ ] Run the focused test; expect route-not-found failure.
- [ ] Add `POST /callbacks/github/knowledge-batches`; authenticate before parsing records and enforce the registered source policy.
- [ ] Write a full batch in one transaction and return the prior receipt for duplicate idempotency keys.
- [ ] Ensure request and chunk bodies are excluded from operational logs.
- [ ] Re-run the focused test; expect pass.
- [ ] Commit `[ICP S03/PR01/T02] Accept policy-checked knowledge batches`.

### PR S03-PR01 review gate

- [ ] Prove the Python worker environment has no database host, database user or `DATABASE_URL` secret.
- [ ] Test rollback leaves no partial batch when the final document is invalid.
- [ ] Test customer-data and CoALA source identifiers are rejected before persistence.
- [ ] Record migration and 1,000-document batch timing evidence.

## S03-PR02 — Synchronise Git sources through the gateway

**Branch:** `codex/icp-s03-pr02-git-sync`

**Traceability:** `KNW-07..11`, `SEC-11`.

**Acceptance:** GitHub workers incrementally sync approved source, documentation, issues and pull-request metadata using stable chunks and reconcile edits/deletions without direct SQL.

### Task S03-PR02-T01 — Add deterministic chunking and the gateway client

**Files:** `src/fynla_agent/ingestion/models.py`, `chunking.py`, `src/fynla_agent/gateway/knowledge_client.py`, `tests/unit/knowledge/test_chunking.py`, `test_knowledge_client.py`.

```python
@dataclass(frozen=True)
class Chunk:
    chunk_id: str
    ordinal: int
    title: str
    body: str
    sha256: str

```

Implement the exact public signature `chunk_markdown(canonical_id: str, text: str, max_chars: int = 6000) -> list[Chunk]`.

- [ ] Test heading-aware splits, stable IDs, UTF-8, an overlong section, empty content and a one-character edit affecting only its chunk hash.
- [ ] Test gateway client HMAC uses exact bytes and retries 429/5xx without changing idempotency key.
- [ ] Run focused pytest files; expect failures.
- [ ] Implement pure chunking and `KnowledgeGatewayClient.submit_batch()`; do not import SQLAlchemy or psycopg.
- [ ] Re-run focused tests; expect pass.
- [ ] Commit `[ICP S03/PR02/T01] Build deterministic gateway-only ingestion`.

### Task S03-PR02-T02 — Replace direct-database Git ingestion

**Files:** `src/fynla_agent/ingestion/github.py`, `runner.py`, `.github/workflows/fynlamcp-sync.yml`, `tests/integration/knowledge/test_git_sync.py`, delete or retire `src/fynla_agent/ingestion/service.py` direct SQL path.

- [ ] Create fixture repository events for add, edit, rename, delete, force-push-visible version change and unchanged rerun.
- [ ] Run `python -m pytest tests/integration/knowledge/test_git_sync.py -q`; expect failure against the current SQLAlchemy implementation.
- [ ] Implement cursor-based GitHub reads and submit batches to the gateway; use source commit/object IDs as versions.
- [ ] Emit tombstones for canonical IDs absent after a completed authoritative scan.
- [ ] Remove PostgreSQL service, Alembic and `DATABASE_URL` use from `fynlamcp-sync.yml`; add only gateway URL, worker ID and signing secret.
- [ ] Re-run focused tests; expect pass.
- [ ] Commit `[ICP S03/PR02/T02] Synchronise Git sources through the control plane`.

### PR S03-PR02 review gate

- [ ] Run the sync twice and prove the second run creates no duplicate rows.
- [ ] Delete and rename fixtures; prove search cannot return tombstoned content.
- [ ] Scan runtime dependencies and workflows for `psycopg`, `pgvector`, `DATABASE_URL` and `to_tsvector`; expected zero active references.
- [ ] Confirm only repository allowlist entries from active policy are fetched.

## S03-PR03 — Add Shared Drive and Slack source connectors

**Branch:** `codex/icp-s03-pr03-drive-slack-connectors`

**Traceability:** `KNW-12..17`, `SLK-01`, `SEC-12`.

**Acceptance:** Incremental connectors ingest only allowlisted Shared Drive folders and Slack channels with canonical URLs, classifications, cursors and deletion reconciliation; DMs never enter shared search.

### Task S03-PR03-T01 — Synchronise approved Shared Drive files

**Files:** `src/fynla_agent/ingestion/drive.py`, `tests/unit/knowledge/test_drive_connector.py`, `.github/workflows/fynlamcp-sync.yml`.

- [ ] Test allowlisted folder ancestry, shortcut resolution, Google Docs export, binary skip, modified-time cursor, trashed file and permission loss.
- [ ] Run the focused test; expect missing connector failure.
- [ ] Implement `DriveConnector.collect(source, cursor) -> SyncBatch` with read-only Drive scope and canonical Google Drive web links derived from returned file IDs.
- [ ] Treat permission loss as a tombstone plus warning; do not retain stale content as searchable.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S03/PR03/T01] Ingest allowlisted Shared Drive knowledge`.

### Task S03-PR03-T02 — Synchronise approved Slack channel threads

**Files:** `src/fynla_agent/ingestion/slack.py`, `tests/unit/knowledge/test_slack_connector.py`, `tests/security/knowledge/test_slack_scope.py`.

- [ ] Test channel allowlist, thread canonical links, edits, deletions, bot messages, private-channel permission loss and cursor pagination.
- [ ] Add explicit tests rejecting `im`, `mpim`, DM event types and any channel absent from the active release.
- [ ] Run focused tests; expect failure.
- [ ] Implement `SlackConnector.collect()` with minimal history scope and source-thread grouping.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S03/PR03/T02] Ingest allowlisted Slack threads`.

### PR S03-PR03 review gate

- [ ] Revoke a Drive folder and Slack channel; prove their content disappears after reconciliation.
- [ ] Inspect API scopes and confirm no write permissions.
- [ ] Query the database for DM/MPIM source types; expected zero rows.
- [ ] Record connector cursor, freshness and deletion evidence.

## S03-PR04 — Search with permissions, authority, freshness and citations

**Branch:** `codex/icp-s03-pr04-fulltext-retrieval`

**Traceability:** `KNW-18..25`, `IAM-13`, `MCP-06..08`.

**Acceptance:** Search candidates are limited to the principal's permitted classifications/resources before results reach the model; ranking combines FULLTEXT score, source authority and freshness; every hit has a canonical citation.

### Task S03-PR04-T01 — Implement the pre-filtered repository query

**Files:** `gateway/src/Knowledge/KnowledgeRepository.php`, `PdoKnowledgeRepository.php`, `gateway/tests/Integration/Knowledge/PermissionFilteredSearchTest.php`.

```php
interface KnowledgeRepository {
    /** @return list<SearchCandidate> */
    public function search(
        string $query,
        array $allowedClassifications,
        array $allowedResources,
        int $limit,
    ): array;
}
```

- [ ] Seed identical keywords into public, internal, restricted and founder chunks across two repositories.
- [ ] Assert each role receives only allowed rows and a zero-access user causes zero content reads.
- [ ] Run the focused MariaDB test; expect failure.
- [ ] Implement prepared boolean-mode FULLTEXT query plus permission joins/conditions; cap query length and result count.
- [ ] Re-run focused tests; expect pass.
- [ ] Commit `[ICP S03/PR04/T01] Filter knowledge candidates before context`.

### Task S03-PR04-T02 — Rank and render trustworthy citations

**Files:** `gateway/src/Knowledge/SearchService.php`, `Citation.php`, `gateway/tests/Unit/Knowledge/SearchServiceTest.php`, `gateway/tests/Feature/KnowledgeMcpToolsTest.php`.

```php
final readonly class SearchHit {
    public function __construct(
        public string $title,
        public string $excerpt,
        public string $canonicalUrl,
        public string $sourceVersion,
        public DateTimeImmutable $freshAt,
        public float $score,
    ) {}
}
```

- [ ] Test score order `0.70 fulltext + 0.20 authority + 0.10 freshness`, stable tie-breaking, stale labels and missing canonical URL rejection.
- [ ] Test MCP search/fetch/freshness tools cannot fabricate or accept model-supplied citations.
- [ ] Run focused tests; expect failures.
- [ ] Implement ranking over authorised candidates and derive excerpts/citations only from stored source metadata.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S03/PR04/T02] Return ranked freshness-aware citations`.

### PR S03-PR04 review gate

- [ ] Use query logs or repository spies to prove denied chunks never enter the result object/model context.
- [ ] Run SQL injection, wildcard abuse, oversized query and Unicode tests.
- [ ] Confirm all factual answer context entries contain canonical URL, version and freshness.
- [ ] Record explain plan and p95 search latency for 100,000 fixture chunks.

## S03-PR05 — Prove retrieval quality and degraded operation

**Branch:** `codex/icp-s03-pr05-knowledge-evaluation`

**Traceability:** `KNW-26..30`, `OPS-01`, `SEC-13`.

**Acceptance:** A versioned golden set measures authority and leakage; healthy retrieval reaches 90% authoritative-source recall@5; degraded mode is visibly labelled and blocks unsupported claims.

### Task S03-PR05-T01 — Build the permission-aware golden evaluator

**Files:** `tests/golden/knowledge/cases.yaml`, `src/fynla_agent/retrieval/evaluation.py`, `tests/golden/knowledge/test_retrieval_quality.py`.

Each case contains `id`, `query`, `principal_fixture`, `required_source_ids`, `forbidden_source_ids`, `minimum_freshness`, `expected_top_five`.

- [ ] Add at least 50 cases spanning engineering, founder, product/design, stale content, duplicate language and denied near-matches.
- [ ] Run the golden test; record baseline failure and current recall@5.
- [ ] Implement evaluator metrics `authoritative_recall_at_5`, `forbidden_hit_count`, `freshness_pass_rate` and per-case diagnostics.
- [ ] Tune only documented authority/freshness weights; do not weaken filters to improve score.
- [ ] Require recall@5 `>= 0.90` and forbidden hits `== 0`.
- [ ] Commit `[ICP S03/PR05/T01] Gate FULLTEXT retrieval quality`.

### Task S03-PR05-T02 — Implement degraded direct-source behaviour

**Files:** `gateway/src/Knowledge/KnowledgeHealth.php`, `DegradedSearchService.php`, `gateway/tests/Feature/Knowledge/DegradedKnowledgeTest.php`.

- [ ] Test missing FULLTEXT index, stale sync, database pressure flag and source API outage independently.
- [ ] Run focused tests; expect failure.
- [ ] Implement permission-filtered source metadata/direct-fetch fallback; label `degraded=true`, include reason and disallow unsourced answer generation.
- [ ] Ensure the dashboard/job audit receives health transition events.
- [ ] Re-run tests; expect pass.
- [ ] Commit `[ICP S03/PR05/T02] Fail knowledge retrieval transparently`.

### PR S03-PR05 review gate

- [ ] Run the golden suite from a clean rebuilt index.
- [ ] Inject denied high-scoring rows; expected forbidden hit count remains zero.
- [ ] Disable FULLTEXT and each connector; verify truthful degraded state.
- [ ] Publish quality metrics, fixture source versions and review decision.

## Section S03 Completion Gate

- [ ] All five PRs are merged with valid evidence.
- [ ] Active worker/workflow code has zero PostgreSQL, pgvector, embeddings or direct SQL dependencies.
- [ ] Index rebuild, edit, deletion and permission-revocation reconciliation pass.
- [ ] Golden recall@5 is at least 90% with zero forbidden hits.
- [ ] Every returned factual context item is cited and freshness-labelled.
- [ ] S07 can use search/fetch APIs without receiving broader access than its Slack requester.
