# CoALA Phase 2 — Episodic Memory: SQL + `.md` Hybrid (Design Spec)

**Status:** Approved design (brainstorm, 2026-06-01)
**Owner:** Chris
**Master plan:** `fynla-coala-implementation-plan.md` v0.5 → "Phase 2 — Episodic memory: SQL+.md hybrid, extend, retain, index"
**Base branch:** `feat/coala-phase1-semantic-memory` (CoALA work; PRs target `coala`). Confirm base at execution time.
**Sequence context:** Phase 2 first, then "finish Phase 4" (Phase 4's `procedural_version` stamping + pointer-provenance landing both depend on the columns this phase adds).

---

## Goal

Make every Fyn turn reconstructable from a `(SQL row, .md blob)` pair, relocate the verbatim forensic columns out of `ai_messages` into date-sharded `.md` blobs referenced by path + SHA-256, extend the tamper-evident hash chain to span DB **and** filesystem, and land live fetch-provenance (the v0.5 requirement; closes today's "built-but-unfed" gap on the pointer registry). Full Phase 2: substrate + backfill + read-only compliance UI (advisor per-client + admin global) + retention/erasure jobs.

## Locked decisions (from brainstorm)

1. **Episode = extended `ai_messages` row** (not a new `ai_episodes` table). The row already is the per-turn record; every write/read path points at it; backfill is in-place. Matches the plan's "extend, don't rebuild" ethos.
2. **Live fetch-provenance lands via a request-scoped `FetchProvenanceCollector`**, flushed onto the assistant row at persist time — NOT by threading an assistant `AiMessage` through the assembler (which runs before the row exists).
3. **Full Phase 2** — all nine plan items, including backfill, retention jobs, and both UI surfaces.
4. **Both UI surfaces** — per-client log in `AdvisorClientDetail.vue` + a global admin compliance view alongside `AiCostDashboard.vue`. Read-only.

## Non-goals

- Dense / embedding-based similar-case recall over `reasoning_trace` — deferred to Phase 6 (sparse-only until ~500 users, per the master plan's locked decision).
- A new `ai_episodes` table — explicitly rejected above.
- Re-chaining historical `ai_audit_events` during backfill — rewriting history breaks tamper-evidence; backfilled rows get blob columns for forensic retrieval but their chain entries predate the cross-medium extension.
- Wiki-style editing of any of this — read-only UI only.
- Dropping the legacy `system_prompt` / `assembled_context` LONGTEXT columns in this phase — they stop being written but are dropped in a later migration after a backup cycle confirms blobs are safe.

---

## Architecture

```
Fyn turn (HasAiChat / FynLoop)
  ├─ FynContextAssembler  ── pre-fetch pointers ──► FetchDispatcher ──► FetchProvenanceCollector (request-scoped)
  ├─ executeTool          ── tool-mode pointers ──► FetchDispatcher ──► FetchProvenanceCollector
  ├─ SemanticRetriever    ── returns facts ───────► semantic_snapshot_id (SHA over (fact_id,version))
  └─ persist assistant row:
        EpisodeBlobWriter.write(.md)  →  fsync  →  atomic rename  →  SHA-256
        ai_messages row  (blob_md_path, blob_md_sha256, fetch_provenance ← collector flush,
                           semantic_snapshot_id, procedural_version)
        AuditChainService.append(row_hash = SHA(prev ‖ sql_cols ‖ blob_md_sha256))

Read paths:
  EpisodeRetriever.findEpisodes(clientId, limit, since)  → SQL only (list)
  EpisodeProjection.detail(messageId)                    → SQL row + lazy Storage::get(blob)  (hot|cold)

Retention:
  fyn:episodic:cold-archive  (>12mo  hot → cold)
  fyn:episodic:purge         (>6yr   SQL row + cold blob delete; FCA SYSC 9.1)
  fyn:episodic:reconcile     (nightly orphan-blob flag)
  fyn:user:erase {id}        (GDPR — SQL rows + hot+cold blobs)

UI (read-only, AppLayout, design-system, no icons):
  AdvisorClientDetail.vue  → per-client "Session log" panel + blob drill-down + chain-verify status
  Admin global view        → all-users episode list, filter/search, blob drill-down, chain-verify
  AiAuditController         → GET episodes (paginated/filterable), GET episodes/{id}, POST episodes/{id}/verify-chain
```

## Components

### 1. Data model — columns on `ai_messages` (all nullable)

| Column | Type | Purpose |
|---|---|---|
| `procedural_version` | JSON | array of `procedure_id@version` contributing to the turn (Phase 4 populates; nullable until then) |
| `semantic_snapshot_id` | CHAR(64) nullable | SHA-256 over the sorted list of `(fact_id, version)` the retriever returned this turn |
| `fetch_provenance` | JSON nullable | array of `{pointer_id, handler, source_label, source_version, digest}` (the `FetchResult::provenance()` shape) |
| `blob_md_path` | VARCHAR(255) nullable | `episodic/YYYY/MM/DD/{conversation_id}/{message_id}.md` (relative to the local disk) |
| `blob_md_sha256` | CHAR(64) nullable | tamper-evidence over the `.md` body |

Existing `system_prompt` + `assembled_context` LONGTEXT columns: **kept, stop writing after cutover.** Drop in a later migration.

Migration: single `add_episode_columns_to_ai_messages` migration; all nullable so it is safe on existing rows and backward-compatible with the audit chain.

### 2. `FetchProvenanceCollector` (request-scoped)

`app/Services/AI/Memory/Episodic/FetchProvenanceCollector.php` — singleton-per-request (bound `scoped` in the container; reset at turn start). API:
- `record(array $provenanceEntry): void` — append one `FetchResult::provenance()` tuple.
- `all(): array` — the accumulated tuples.
- `reset(): void` — clear at the start of a turn.

Wiring:
- `FetchDispatcher::run()` calls `$collector->record(...)` on every successful fetch (in addition to / replacing the optional `?AiMessage` direct-record path, which becomes legacy). Both trigger paths (`FynContextAssembler` pre-fetch and `CoordinatingAgent::executeTool` tool-mode) flow through `FetchDispatcher`, so both populate the collector.
- `HasAiChat` (assistant-row persistence): on writing the assistant `ai_messages` row, set `fetch_provenance = $collector->all()` and `reset()`.
- `semantic_snapshot_id`: `SemanticRetriever` already has a `snapshotId()` hook (built Phase 1, unconsumed) — the assembler/loop captures it into the same request scope and stamps it on the row.

This is the fix for the documented Phase-4 gap: provenance now lands on every turn that fetched, without changing the message lifecycle.

### 3. `EpisodeBlobWriter` — atomic write protocol

`app/Services/AI/Memory/Episodic/EpisodeBlobWriter.php`. `write(AiMessage $message, EpisodeBlobData $data): EpisodeBlobRef` where the ref carries `{path, sha256}`. Steps (the plan's hard invariant — no exception):

1. Compose `.md` body: YAML frontmatter (`episode_id` = message uuid/id, `session_id`, `client_id`, `timestamp` UTC, `persona`, `module`, `procedural_version`, `semantic_snapshot_id`, `model_used`) + sections `## system_prompt`, `## assembled_context`, `## reasoning_trace` (omit if no planner ran), `## tool_calls`, `## tool_results`.
2. Write `…/{message_id}.md.tmp`.
3. `fsync`.
4. Atomic `rename()` → drop `.tmp`.
5. SHA-256 of the final file → returned in the ref → caller writes `blob_md_path` + `blob_md_sha256` on the row.
6. Caller appends the audit-chain entry (step incorporates the SHA — see §4).
7. Crash between 4 and 6 → orphan `.md`; `fyn:episodic:reconcile` flags it; never reused (path carries `message_id`).

Path sharding: `storage/app/episodic/{YYYY}/{MM}/{DD}/{conversation_id}/{message_id}.md`, UTC date of the turn timestamp. **Never flat** — retention/archive/erase scripts depend on this path being a contract.

The blob resolver (`EpisodeBlobLocator`) checks `episodic/` then `episodic-cold/`.

### 4. Cross-medium hash chain

Extend `AuditChainService` so `row_hash = SHA256(prev_hash ‖ canonical(sql_columns) ‖ blob_md_sha256)`.

- **Backward-compatible:** pre-cutover and backfilled rows have `blob_md_sha256 = null`, hashed as the empty string, so existing chain entries continue to verify unchanged. Only entries written *after* the extension incorporate a non-null blob SHA.
- `AiAuditVerifyChainCommand` updated: for each entry with a non-null `blob_md_path`, fetch the `.md` (hot or cold), recompute SHA-256, and fail verification on missing / modified / mismatched blobs. Cold-archived blobs must remain reachable by the verifier.
- This is the highest-risk change. It ships with a dedicated test suite proving: (a) the existing chain stays green across the extension, (b) a tampered blob breaks verification at that row, (c) a missing blob is distinguishable (operational vs tamper) in the failure report, (d) cold-archived blobs still verify.

### 5. Backfill — `fyn:episodic:backfill-blobs`

One-shot, idempotent. For each existing `ai_messages` row with `system_prompt`/`assembled_context` but no `blob_md_path`: write the `.md` blob via `EpisodeBlobWriter`, populate `blob_md_path` + `blob_md_sha256`. Does **not** re-chain historical audit entries (documented). Batched, resumable (skips rows that already have `blob_md_path`). After backfill, the live write path stops writing the LONGTEXT columns.

**Accessibility guarantee (CSJ requirement):** backfilled episodes are first-class retrievable through the episodic memory system — because they carry `blob_md_path` + `blob_md_sha256`, they appear in `EpisodeRetriever::findEpisodes`, render in `EpisodeProjection::detail` (lazy blob load, hot|cold), and show in both UI surfaces exactly like live episodes. The only difference from post-cutover episodes is that their `ai_audit_events` entry is not chain-linked to the blob SHA (history is not rewritten). Integrity of a backfilled blob is still verifiable **standalone**: recompute SHA-256 of the `.md` and compare to the stored `blob_md_sha256`. The `verify-chain` command reports backfilled entries as "blob present, standalone-verified, pre-extension (not chain-linked)" rather than failing them — a distinct, honest status, not an error. A test asserts a backfilled episode is retrievable and standalone-verifiable end-to-end.

### 6. Retrieval + projection

- `EpisodeRetriever::findEpisodes(int $clientId, int $limit, ?Carbon $since): Collection` — SQL-only list path; typed; alongside `MemoryRetrieverService` (not replacing it).
- `EpisodeProjection` — read model: `list()` (SQL columns only) and `detail(messageId)` (SQL row + lazy blob load via `EpisodeBlobLocator`, exposing section anchors `#system_prompt`, `#assembled_context`, `#reasoning_trace`, `#tool_calls`, `#tool_results`).

### 7. Retention + erasure (scheduled in `app/Console/Kernel.php`)

- `fyn:episodic:cold-archive` — `.md` >12 months hot → cold; SQL rows untouched; idempotent, batched. Weekly schedule.
- `fyn:episodic:purge` — >6 years (FCA SYSC 9.1): delete SQL row + cold blob together. Dry-run default; `--force` to execute. Chain verification past a purge point intentionally fails for those entries (regulatory window closed — correct).
- `fyn:episodic:reconcile` — nightly orphan-blob flag (blob with no matching SQL row); never reused.
- `fyn:user:erase {user_id}` — GDPR: cascade-delete the user's SQL rows AND walk hot + cold blob dirs removing their `.md`. Extend the existing erase path if one exists; partial erasure is a regulatory failure. Single command.

### 8. UI — both surfaces (read-only)

Shared constraints: `<AppLayout>` (Rule #14); palette tokens + `designSystem.js` only (Rule #11); **no decorative icons / emoji / Unicode-as-icon** (Rule #16 — detail views are banned surfaces; chain-verify status is a text badge); preview users see nothing (real audit data only); no scores (Rule #13).

- **A. Advisor per-client** — `AdvisorClientDetail.vue` gains a "Session log" panel: the client's episodes (date, module, persona, model, tool count) via `find_episodes`; drill-down renders blob sections + per-episode chain-verify status. Advisor authorised only for their own clients.
- **B. Admin global compliance** — new admin view alongside `AiCostDashboard.vue`: all-users episode list, server-side paginated, filter/search (user, date range, module, persona); same blob drill-down + chain-verify. Admin-gated.
- **Backend** — read-only endpoints on `AiAuditController` (extend; it exists): `GET episodes` (paginated/filterable), `GET episodes/{id}` (lazy blob), `POST episodes/{id}/verify-chain` (on-demand single-entry check). Authorisation: admin for global; advisor-scoped for per-client.

## Error handling / resilience

- Blob write failure (disk full, permission) must **not** break the chat turn: the turn still streams; the persistence step logs + reports and writes the SQL row without `blob_md_path` (flagged for reconcile). The verbatim columns remain the fallback until the LONGTEXT drop migration, so no forensic data is lost pre-drop.
- Provenance collector errors degrade to "no provenance recorded for the turn" — never break the turn (same posture as the Phase-1 `<knowledge>` / pointer paths).
- Chain-verify on a missing blob reports operational-vs-tamper distinctly (records `(path, expected_sha, actual_state)`).
- All retention/erase commands log exactly what they touched (no silent truncation).

## Testing

- **Unit:** `FetchProvenanceCollector` (record/all/reset, request-scope isolation), `EpisodeBlobWriter` (atomic protocol — tmp→fsync→rename→sha; orphan-on-crash simulation), `EpisodeBlobLocator` (hot/cold resolution), `EpisodeRetriever`/`EpisodeProjection`.
- **Hash chain (dedicated suite):** existing chain stays green across the extension; tampered blob breaks verification; missing-blob operational-vs-tamper distinction; cold-archived blob still verifies; null-SHA pre-cutover rows verify.
- **Feature/console:** backfill idempotency + resumability; cold-archive move + resolver still finds blob; purge dry-run vs `--force`; reconcile orphan flag; `fyn:user:erase` spans both media (assert hot + cold `.md` gone + SQL rows gone).
- **Integration:** a full turn writes the row + blob + chain entry + provenance; `verify-chain` green end-to-end; provenance lands for both a pre-fetch turn and a tool-mode turn.
- **Browser (CLAUDE.md law):** advisor per-client panel — navigate, list, drill into a blob, see chain-verify status; admin global view — filter, paginate, drill in, verify. Click/fill/observe, not snapshot-only.
- Test DB convention: `DB_DATABASE=laravel_testing` (never `php artisan --env=testing`).

## Invariants this phase must preserve

- Atomic write protocol — no exception (tmp→fsync→rename→commit; orphan on crash, never reused).
- Hash chain spans DB + filesystem; `ai:audit:verify-chain` fetches + re-hashes blobs; existing chain stays green.
- Date-sharded path is a contract (retention/erase depend on it); never flat.
- GDPR erasure spans both media; partial erasure is a regulatory failure.
- Verbatim episodic capture is mandatory every turn (the recall summary is additive, never a replacement — FCA SYSC 9.1).
- `FynSystemPrompt::text()` byte-invariant; `TaxConfigService` canonical (this phase records provenance of fetches, never freezes figures).
- No frontend persona signals; consent gating unchanged; preview users excluded from audit data.
- SiteGround: deploy must not touch `storage/app/episodic/`; access control over episodes in Laravel middleware, not `.htaccess`; confirm `storage/` is in the daily backup before this ships to prod.

## Decomposition note

Large enough that the implementation plan will likely sequence as: (S1) columns + collector + snapshot wiring; (S2) blob writer + atomic protocol; (S3) hash-chain extension + verify (dedicated suite); (S4) backfill; (S5) retrieval + projection; (S6) retention + GDPR erase; (S7) advisor UI; (S8) admin UI. Each independently testable; the `writing-plans` skill will turn this into the task breakdown.
