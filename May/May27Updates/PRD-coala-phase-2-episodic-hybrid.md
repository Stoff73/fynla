# PRD — CoALA Phase 2: Episodic Memory (SQL + `.md` Hybrid)

**Project:** Fyn brain rewire — Phase 2 (Tidy & Retain)
**Owner:** CSJ
**Status:** Draft — codebase audit completed during plan revisions v0.1 → v0.4
**Date:** 27 May 2026
**Spec & Plan:** `/Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md` (v0.4)
**Canonical contract:** `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/00-canonical.md`
**Codebase audit:** Performed during plan v0.1 → v0.4 revision cycle

---

## 1. Context & Why

### Problem

Episodic memory **exists** in Fynla today but has three structural problems that block CoALA-shaped recall and the regulatory roadmap:

1. **Forensic LONGTEXT bloat with no purge path.** `ai_messages.system_prompt` and `ai_messages.assembled_context` (added 2026-04-01 and 2026-05-18 respectively) store the verbatim full prompt and full per-turn context block per row. Plus `tool_calls` and `tool_results` JSON columns. MEMORY.md `project_ai_messages_forensic_columns_need_purge.md` flags this debt as deferred. Currently no retention or cold-archive job exists for these columns.
2. **No tamper-evidence binding between SQL audit chain and forensic body.** `ai_audit_events` hash-chains tool dispatches (`prev_hash`, `row_hash`, HMAC `signature`) but the LONGTEXT forensic body sits inside the SQL row uncontested. Cold-archival would orphan the forensic data from the chain.
3. **No structured fields for CoALA's typed episode shape.** Today's `ai_messages` row is per-message, not per-decision-cycle. There is no `reasoning_trace`, no `procedural_version`, no `semantic_snapshot_id`, no `action_type` (Phase 5 dependency).

The deferred forensic-column debt and the missing structured fields are the same problem from two angles: the verbatim per-turn body should live as a date-sharded `.md` blob on disk, referenced by SHA-256 from a now-leaner SQL row, with the audit chain hash extended to span both media.

### Business case

- **Regulatory.** FCA SYSC 9.1 requires the full suitability record retained 5 years minimum. The verbatim body must remain — but it does not need to live in a hot table indefinitely. Filesystem cold-archive is dramatically cheaper than MySQL hot storage.
- **GDPR right-to-erasure.** Today, `fyn:user:erase` (if it existed properly) would only delete SQL rows. The verbatim body in LONGTEXT goes with them. Once we relocate to `.md` blobs, erasure must span both media — but it also becomes a single `rm -rf user_path` filesystem operation rather than a multi-table delete.
- **Database health.** MySQL LONGTEXT bloat is real. A high-engagement client could produce thousands of rows a year, each carrying full verbatim prompt + assembled-context (potentially tens of KB per row). The hot `ai_messages` table grows unbounded today.
- **CoALA prerequisite.** Phases 5 (decision loop) and 6 (learning from experience) both need `procedural_version`, `semantic_snapshot_id`, and a structured `reasoning_trace` field on the episode. Phase 2 ships those columns.

### Strategic fit

Resolves the deferred forensic-column debt (MEMORY.md `project_ai_messages_forensic_columns_need_purge.md`) while extending the structured episode record for downstream phases. Touches every persisted Fyn conversation, but no end-user behaviour change.

---

## 2. Target Persona

**Infrastructure — indirectly benefits all personas.** No end-user UX change.

**Primary internal beneficiary:** Compliance — gets a structured, hash-chained, tamper-evident record per turn that spans DB + filesystem.

**Secondary internal beneficiary:** Operations — gets a retention and cold-archive path that resolves a known database-bloat risk.

**Tertiary:** Engineers — get the typed `Episode` projection they need for Phase 5 / 6 work.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| `ai_messages.system_prompt` + `assembled_context` LONGTEXT bloat relocated to `.md` blobs | 0% (all in MySQL) | 100% post-cutover for new rows; backfilled rows complete within retention window | `SELECT COUNT(*) WHERE blob_md_path IS NULL` over recent rows |
| Audit-chain verification pass rate (DB + filesystem SHA) | n/a (chain spans SQL only today) | 100% on `ai:audit:verify-chain` post-cutover | Daily verification job; alert on any failure |
| Orphan `.md` blob count (post `fyn:episodic:reconcile` nightly run) | n/a | 0 maintained | Nightly reconcile job output |
| Cold-archive automation: blobs > 12 months moved to `episodic-cold/` | n/a (no archive job exists) | 100% of >12-month blobs auto-archived | `fyn:episodic:cold-archive` artisan command run results |
| GDPR right-to-erasure spans both media | Partial (SQL cascade only) | 100% — `fyn:user:erase` removes SQL + .md blobs in one operation | Test case: erase user, verify zero `.md` files remain under their conv_ids |
| Per-row hot table size (excluding verbatim columns) | TBD: measure pre-cutover | Reduce by ≥ 70% post-cutover | `information_schema.tables` row-size estimate before/after |

---

## 4. User Stories & Scenarios

### User stories

- As **Compliance**, I want every Fyn turn's verbatim prompt + assembled context + tool calls + tool results to be tamper-evidently recorded so that I can demonstrate to the FCA which inputs produced which advice.
- As **Operations**, I want hot-table storage to stay bounded so that MySQL doesn't degrade as the user base grows.
- As **GDPR caseworker**, I want a single command to fully erase a user's AI conversation history including verbatim forensic data so that we can honour erasure requests within the regulated window.
- As **a Phase 5 engineer**, I want `procedural_version`, `semantic_snapshot_id`, and `action_type` fields on the episode so that I can attribute cost and behaviour back to specific procedures and knowledge versions.

### Key scenarios

**Scenario 1 — Normal turn write:**

1. Fyn completes a turn via `FynLoop` / `CoordinatingAgent::chatWithPromptOverride`.
2. `EpisodeBlobWriter::write()` runs the atomic protocol:
   a. Compose `.md` body (frontmatter + verbatim sections).
   b. Write to `storage/app/episodic/2026/05/27/{conv_id}/{msg_id}.md.tmp`.
   c. `fsync`.
   d. Atomic `rename()` drops the `.tmp` suffix.
3. Compute SHA-256 of the `.md` body.
4. INSERT `ai_messages` row carrying `blob_md_path`, `blob_md_sha256`, `procedural_version`, `semantic_snapshot_id`, plus structured `tool_calls` / `tool_results` JSON (verbatim copy of body) and structured fields (role, persona, model_used, tokens).
5. APPEND `ai_audit_events` row with `row_hash = SHA256(prev_hash || sql_columns || blob_md_sha256)`.

**Scenario 2 — Verification:**

1. `php artisan ai:audit:verify-chain --since=2026-04-01` runs nightly.
2. For each row: fetch `.md` from `blob_md_path`, re-hash, compare with `blob_md_sha256`. Recompute `row_hash` and compare.
3. Mismatch on any row → fail loud, alert ops. Distinguish "missing file" (operational) from "modified file" (tamper).

**Scenario 3 — Cold archive:**

1. Nightly `php artisan fyn:episodic:cold-archive` walks `storage/app/episodic/`.
2. For directories with `mtime > 12 months ago`: move to `storage/app/episodic-cold/{YYYY}/{MM}/{DD}/{conv_id}/`.
3. SQL rows unchanged. `blob_md_path` resolver checks hot, then cold, then errors.
4. Subsequent verification still passes — verifier reads from wherever the blob lives.

**Scenario 4 — GDPR erasure:**

1. User invokes account erasure (existing legal path).
2. `php artisan fyn:user:erase {user_id}` runs:
   a. Find all `ai_conversations` for the user, list their `conv_ids`.
   b. For each `conv_id`: walk `storage/app/episodic/**/{conv_id}/` and `storage/app/episodic-cold/**/{conv_id}/`, delete the `.md` files.
   c. Delete the SQL rows (cascade handles `ai_messages`, `ai_audit_events`, `ai_advice_logs`, `ai_abort_events`).
3. Audit chain past the erasure point is intentionally broken for that user's entries — this is correct behaviour, the regulatory window has closed.

**Unhappy path — crash between rename and SQL insert:**

1. Process dies between step 2.d (rename) and step 4 (INSERT).
2. Orphan `.md` blob sits on disk with no matching SQL row.
3. Nightly `php artisan fyn:episodic:reconcile` walks `episodic/` directories, joins against `ai_messages.blob_md_path`. Flags orphans.
4. Orphans are NEVER reused — they're either deleted by ops or moved to a quarantine path with audit log entry. The `{msg_id}` in the path is unique per message; a future write picks a fresh `msg_id`.

**Scenario 5 — Cutover for existing `ai_messages` rows:**

1. Phase 2 ships migration adding new columns: `procedural_version`, `semantic_snapshot_id`, `blob_md_path`, `blob_md_sha256`. Old `system_prompt` and `assembled_context` LONGTEXT columns remain populated.
2. After deploy, `EpisodeBlobWriter` writes new rows in the hybrid shape.
3. `php artisan fyn:episodic:backfill-blobs --since={older-date}` extracts old `system_prompt` + `assembled_context` from existing rows into `.md` blobs, populates the new columns.
4. After backfill completes and verification passes, a follow-up migration drops the old LONGTEXT columns. This drop is gated on a full backup cycle confirming blobs survive.

---

## 5. Functional Requirements

### Must-have

- **FR-M1:** Migration adding columns to `ai_messages` (or new sibling `ai_episodes` — TBD by migration impact assessment): `procedural_version VARCHAR(64) NULL`, `semantic_snapshot_id CHAR(64) NULL`, `blob_md_path VARCHAR(255) NULL`, `blob_md_sha256 CHAR(64) NULL`. _Touches: new migration._
- **FR-M2:** `EpisodeBlobWriter` service implementing the atomic write protocol (write `.tmp` → fsync → atomic rename → compute SHA → SQL insert → audit chain append). Single transactional unit semantically; failure between rename and INSERT yields an orphan never reused. _Touches: new service `app/Services/AI/Memory/Episodic/EpisodeBlobWriter.php`._
- **FR-M3:** Extend `ai_audit_events.row_hash` computation to include `blob_md_sha256` in the hash input: `row_hash = SHA256(prev_hash || canonical_sql_columns || blob_md_sha256)`. _Touches: `app/Services/AI/AuditChainService.php`._
- **FR-M4:** Extend `ai:audit:verify-chain` artisan to fetch each referenced `.md` from `blob_md_path`, re-hash, compare with `blob_md_sha256`, and incorporate it into `row_hash` recomputation. Distinguish missing-file vs modified-file vs tamper. Cold-archived blobs must remain reachable. _Touches: `app/Console/Commands/AiAuditVerifyChainCommand.php`._
- **FR-M5:** `php artisan fyn:episodic:backfill-blobs --since={date}` artisan command. Extracts existing `ai_messages.system_prompt + .assembled_context` into `.md` blobs at the canonical date-sharded path. Idempotent. Populates `blob_md_path` and `blob_md_sha256`. Does NOT delete the old LONGTEXT columns. _Touches: new command._
- **FR-M6:** `php artisan fyn:episodic:cold-archive` artisan command. Moves `.md` blobs whose directory `mtime > 12 months` from `storage/app/episodic/` to `storage/app/episodic-cold/`. Runs nightly via scheduler. _Touches: new command + scheduler entry._
- **FR-M7:** `php artisan fyn:episodic:reconcile` artisan command. Walks `storage/app/episodic/` and `storage/app/episodic-cold/`, joins against `ai_messages.blob_md_path`. Reports orphans. Runs nightly. Does NOT auto-delete — flagging is enough; ops resolve. _Touches: new command + scheduler entry._
- **FR-M8:** `php artisan fyn:user:erase {user_id}` artisan command (new or extending an existing erasure path if one exists). Cascades SQL row deletion AND removes `.md` blobs across hot and cold archives for every conversation belonging to the user. Single command, atomic-ish (best-effort: SQL transaction first, then filesystem; failure leaves a documented inconsistency surfaced by reconcile). _Touches: new command._
- **FR-M9:** Hard-delete sweep after 6 years (FCA SYSC 9.1 + 1-year buffer). `php artisan fyn:episodic:hard-delete --before={date}` removes both SQL rows and cold-archived `.md` blobs. Chain verification past the deletion point intentionally fails for those entries — this is correct. _Touches: new command + clear documentation._
- **FR-M10:** Filesystem layout invariant: blobs are date-sharded `storage/app/episodic/{YYYY}/{MM}/{DD}/{conversation_id}/{message_id}.md`. UTC of the `timestamp` field determines the date directory. Never flat. _Touches: enforced in `EpisodeBlobWriter`._
- **FR-M11:** Structured `Episode` projection over the SQL + `.md` pair for compliance UI consumption. Reads SQL row only on list view; lazy-loads `.md` body on detail view via `Storage::disk('local')->get($blob_md_path)`. _Touches: new VO `app/Services/AI/Memory/Episodic/Episode.php` + repository class._
- **FR-M12:** Compliance UI: list and detail view for episodes within a conversation. Read-only. Wraps in `AppLayout`. Renders the `.md` blob body with section anchors (`#system_prompt`, `#assembled_context`, `#reasoning_trace`, `#tool_calls`, `#tool_results`). _Touches: new admin views `EpisodeList.vue` and `EpisodeDetail.vue`._

### Should-have

- **FR-S1:** Drop the old `ai_messages.system_prompt` and `ai_messages.assembled_context` LONGTEXT columns in a follow-up migration after backfill completes and one full SiteGround backup cycle confirms blobs are safe. _Touches: migration._
- **FR-S2:** Backup-coverage confirmation. Document that SiteGround daily backups include `storage/` by default. Add a deploy-time check that warns if not. _Touches: deploy documentation; possibly `./deploy/csjones-fynla/build.sh` and `./deploy/fynla-org/build.sh`._

### Nice-to-have

- **FR-N1:** Dense similarity retrieval over `reasoning_trace + observation` for similar-case recall. Deferred to Phase 6 (depends on Phase 1 embeddings infrastructure being shipped). _Touches: future._
- **FR-N2:** Episode export API for advisor case-management integrations. Out of scope for the refactor itself but worth flagging as a downstream possibility. _Touches: future._

---

## 6. User Flow & UX/Design

### Atomic write protocol (engineering flow)

```
FynLoop completes a turn
  └─ EpisodeBlobWriter::write(episodeData)
       ├─ Compose .md body (frontmatter + sections)
       ├─ Write to {path}.md.tmp
       ├─ fsync
       ├─ Atomic rename .tmp → .md
       ├─ Compute SHA-256 of .md
       ├─ DB: INSERT ai_messages (... blob_md_path, blob_md_sha256, procedural_version, semantic_snapshot_id ...)
       └─ AuditChainService::append (row_hash incorporates blob_md_sha256)

If crash between rename and INSERT:
  └─ Orphan .md on disk
       └─ fyn:episodic:reconcile (nightly) flags it
            └─ Ops decides: delete or quarantine
            └─ Never reused — msg_id in path is unique
```

### Compliance UI

Existing admin sidebar already has an AI conversation viewer (referenced in `app/Services/AI/HasAiChat.php:784-797` per the v0.4 plan's research). Phase 2 extends it:

- **List view:** existing — shows conversations per user with summary fields.
- **Detail view (new):** for a selected conversation, shows the list of `ai_messages` rows + their `Episode` projection. Lazy-loads the `.md` body on row expansion.
- **Audit-chain status:** banner at top of detail view shows chain verification status (green/red) for the conversation's range.

### UX/Design notes

- **Design system:** `fynlaDesignGuide.md` v1.3.0. Admin viewer uses standard `AppLayout` chrome, standard table patterns for the list, standard expand/collapse for episode detail. Audit-chain status uses `spring-*` for green / `raspberry-*` for red (CLAUDE.md Rule #9 — never amber/orange).
- **Reusable components:** existing audit-log viewer patterns, existing `ConversationViewer` admin component (if it exists — confirm in implementation).
- **New components:** `EpisodeDetail.vue`, `AuditChainBanner.vue`.
- **Accessibility:** Standard ARIA expand/collapse for lazy-loaded body. Keyboard navigation through episode list.
- **No icons** (Rule #16 — admin viewer is functional list + detail, no decorative icons. Audit-chain status uses colour + text label, not icon).

---

## 7. Out of Scope

- Dense similarity retrieval over `reasoning_trace`. Phase 6.
- New columns beyond those listed in FR-M1. `action_type` (typed CoALA action enum) ships in Phase 5; it's not Phase 2's concern.
- Migration of `ai_advice_logs`, `ai_abort_events`, `ai_request_idempotency` to hybrid storage. Those tables are structured forensic records, not verbatim bodies. They stay SQL-only.
- Real-time streaming of `.md` writes during a long turn. The write happens once at end-of-turn; no incremental streaming to disk.
- Cross-conversation `.md` blob deduplication. Each turn gets its own `.md` regardless of content overlap.
- Cross-user blob retention policies beyond the 12-month cold / 6-year hard-delete defaults. Per-user retention preferences not supported.
- Editing or annotating `.md` blobs after write. Append-only. Edits to the canonical record are a regulatory failure mode.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Crash between atomic rename and SQL insert leaves orphan `.md` | Low (small window) | Low (orphans don't break anything — reconciled nightly) | Reconcile job runs nightly; orphans flagged not auto-deleted; `msg_id` uniqueness prevents reuse |
| `.md` blob loss (operational error vs tamper) is indistinguishable to the verifier | Medium | High | Verifier records `(blob_md_path, expected_sha, actual_state)` per failure. Quarantine + alert. Tamper-evidence by design — if blob is lost, the chain shows it. Sometimes this IS the regulatory answer. |
| Backfill of historical rows pulls forensic LONGTEXT into memory at scale | Medium | Medium | `fyn:episodic:backfill-blobs` processes in batches with `--limit` / `--offset`, streams to disk, doesn't accumulate in PHP memory. Run during low-traffic windows. |
| SiteGround filesystem doesn't include `storage/` in backups (untested assumption) | Low | High | Verify before Phase 2 ships. Document the verification. If SiteGround omits it, configure a separate backup path. |
| File-count growth: thousands of `.md` per user per year | Low (date-sharded) | Low | Date sharding (`YYYY/MM/DD/{conv_id}/`) keeps directory entry counts bounded — max hundreds per day-conversation pair. Filesystem-friendly. |
| `EpisodeBlobWriter` is on the hot path; filesystem I/O slows turn completion | Low (write happens once per turn, not per token) | Medium | Measure pre/post turn-completion latency in Phase 5 telemetry. Acceptable if added latency < 50ms p95. |
| Hard-delete at 6 years breaks chain verification past that point | Certain (intentional) | None — by design | Document explicitly. Verification past hard-delete is expected to fail for those entries; this is correct because the regulatory window has closed. |

### Technical dependencies

- `ai_audit_events` hash-chain infrastructure (`AuditChainService.php`) — exists today, must extend hash input to include `blob_md_sha256`.
- `Storage::disk('local')` Laravel filesystem abstraction — already in use; no new dependency.
- SiteGround backup coverage of `storage/` — assumption to verify.
- POSIX `rename()` atomicity on the same filesystem — guaranteed by the standard; relies on `storage/app/episodic/` being on the same mount as the `.tmp` files.

### Sequencing dependencies

- **Blocks:** Phase 5 (decision loop) needs `procedural_version` and `semantic_snapshot_id` columns to attach to per-turn cost telemetry. Phase 6 (learning) needs the `reasoning_trace` field structured.
- **Blocked by:** Nothing strictly — can ship after Phase 1 in parallel with Phase 3. Recommended order is 1 → 2 → 3 → 4 → 5 → 6.

### Residual concerns from codebase audit

- **Decision: extend `ai_messages` vs new `ai_episodes` sibling table.** Plan v0.4 leaves this open — "TBD by migration impact assessment." Recommendation: extend `ai_messages` to avoid a second table that always joins 1:1. Confirm during migration design.
- **`ai_advice_logs` overlap.** Today this table records `query_type`, `classification`, `kyc_status`, `recommendations`, `tools_called`, `user_data_snapshot` per advice turn. Some of this overlaps with `tool_calls` on `ai_messages`. Audit whether `ai_advice_logs` collapses into the new `Episode` projection or stays separate. Recommendation: keep separate — different write paths, different consumers.
- **`persona_state` JSON column on `ai_conversations`** is orphan/legacy from the removed `FynPersonaOrchestrator`. Phase 3's job to deprecate, but Phase 2 should confirm no live consumer remains before extending the row.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 27 May 2026 | Initial draft from CoALA v0.4 plan Phase 2 | prd-writer skill |
