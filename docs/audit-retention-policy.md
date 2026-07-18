# AI Audit Retention Policy

**Status:** Active
**Owner:** CSJ
**First effective:** Sprint 0 close (2026-04-26)
**Implementation:** `app/Jobs/AiAuditRetentionJob.php`
**Spec reference:** `April/April24Updates/spec/01-invariants.md` §INV-2.10.2

---

## 1. Purpose

This policy sets the retention windows applied to the AI tool-call audit chain (`ai_audit_events`) and the non-personal App Store notification audit (`apple_notification_logs`). It is the documented half of D4 (Audit integrity) level 3 in Rubric-A — the other three sub-criteria (HMAC signing, key outside application runtime, weekly integrity-verification job) are already live as code.

It does **not** cover the broader application audit log (`audit_logs`), user-data retention on subscription cancellation, or chat-message retention. Those are governed by separate policies referenced in §6.

## 2. Retention windows

| Window | Duration | Rows in scope |
|---|---|---|
| **Long retention (advice + writes)** | 7 years (2,555 days) | Any `ai_audit_events` row where `operation = 'write'` OR `tool_name = 'get_recommendations'` |
| **Short retention (general)** | 2 years (730 days) | All other rows: read-tool dispatches (`operation = 'read'`), classifier hits (`operation = 'classify'`), handoff chrome (`operation = 'handoff'`), stripped events (`status = 'stripped'`) |
| **Apple notification audit** | 7 years (2,555 days) | Non-personal `apple_notification_logs` idempotency and processing evidence |

**Why these durations.** Seven years matches FCA record-keeping guidance for advice and recommendation activity — every row that either persisted user data (`operation = 'write'`) or returned personalised recommendations (`tool_name = 'get_recommendations'`) is regulator-relevant and stays for the full window. Two years is sufficient for operational reconciliation on read-only and infrastructural rows (which carry no advice content) while keeping table size bounded.

The window starts at row `created_at` (set automatically by `AuditChainService::append`).

## 3. Enforcement

The existing scheduled audit-retention paths prune rows that have aged past
their window.

| Data | Retention path | Schedule |
|---|---|---|
| `ai_audit_events` | `App\Jobs\AiAuditRetentionJob` | Weekly, Sundays 04:00 UTC |
| `apple_notification_logs` | `audit:purge` (`App\Console\Commands\PurgeAuditLogs`) | Weekly, Sundays 03:00 UTC |

Both paths hard-delete expired rows and report their deleted counts. The Apple
notification path uses bounded delete batches and the configured
`retention.apple_notification_audit_days` cutoff.

The chain-integrity verifier runs immediately after retention each week:

```
04:00 UTC  AiAuditRetentionJob (prune)
04:30 UTC  ai:audit:verify-chain  (verify chain still valid post-prune)
```

If the verify command returns `chain_valid: false` after a prune, that is treated as a P1 incident — see §5.

## 4. Why deletion, not pseudonymisation

Spec INV-2.10.2 (line 423) originally described the retention job as performing pseudonymisation — swapping PII with hash-preserving tokens so the chain remained verifiable. The as-shipped implementation **deletes** instead. The reason is documented in the job's class docblock (`app/Jobs/AiAuditRetentionJob.php:27-43`):

The hash chain is computed over the original serialised payload of every row. Mutating any historical row — even with a hash-preserving token swap — produces a different `row_hash`, and `verifyChain()` correctly reports the chain as broken at that row. The chain's tamper-evidence guarantee is incompatible with in-place edits, by design.

Deletion preserves chain integrity for the retained tail: when the new earliest surviving row is walked, `verifyChain()` treats the absent predecessor as outside its remit and validates from there. The retained chain remains end-to-end signed and HMAC-verifiable.

If a future GDPR export needs to surface older rows with PII redacted, the canonical pattern is a separate read-only export view that re-serialises rows with PII fields swapped at read time. The source rows stay untouched and the chain stays verifiable. That export view is out of scope at the time of writing.

## 5. Operations

**Routine.** No manual operation is required. The weekly job runs unattended, logs counts of pruned rows, and the verifier runs 30 minutes later.

**Health check.** Anyone with shell access can spot-check the chain at any time:

```bash
php artisan ai:audit:verify-chain
# → {"chain_valid":true,"tip_hash":"…","row_count":N}
```

A `chain_valid: false` response is the only signal that retention has misbehaved (or that someone has tampered with the table). Investigate before re-running the job.

**Backfill.** When this policy first runs, any rows older than the relevant window are eligible for deletion. The first job run will likely prune the largest batch; subsequent weekly runs prune only the rows that crossed the window in the previous seven days.

**On-demand prune.** The job is idempotent and can be dispatched manually:

```bash
php artisan tinker --execute="(new \App\Jobs\AiAuditRetentionJob)->handle();"
```

There is no `--dry-run` flag at present — the row counts are visible after the fact in the application log.

## 6. Adjacent retention policies (for reference, not in scope here)

| Data | Retention | Owner |
|---|---|---|
| `audit_logs` (general application audit log) | 90 days standard, 7 years for GDPR-class events (`event_type = 'gdpr'`) | `app/Console/Commands/PurgeAuditLogs.php` (`audit:purge`, weekly Sunday 03:00 UTC) |
| User account data after subscription expiry | 30-day grace period, then full purge | `app/Console/Commands/PurgeExpiredUserData.php` (`data-retention:purge-expired`, daily 00:30 UTC) |
| `ai_messages` (chat history) | Bound to conversation lifecycle; deleted with conversation; cascades on user delete | No standalone retention job |
| `ai_advice_logs` (per-recommendation snapshot) | Bound to user lifecycle; cascades on user delete | No standalone retention job |
| `ai_abort_events` (SSE disconnect forensics) | Bound to user/conversation lifecycle; cascades | No standalone retention job |
| `ai_daily_usage` (token budget counters) | Indefinite; row count is bounded by `users × days` | No retention job (low-volume by design) |

If a chat-message or advice-log retention policy is later required for regulatory reasons, it would be authored as a separate document and a separate job, sharing the deletion-not-mutation discipline of this policy.

## 7. Apple notification audit retention

`apple_notification_logs` deliberately has no user foreign key, account token,
transaction identifier, decoded payload or raw JWS. It retains only the
notification UUID, environment, type/subtype, SHA-256 payload evidence,
processing status/error and timestamps. Verified account erasure therefore
does not delete in-window rows. The scheduled general `audit:purge` command
hard-deletes rows whose `created_at` is older than
`retention.apple_notification_audit_days` (2,555 days) in bounded batches.

The schema allowlist and both the retention and account-erasure behaviour are
pinned by focused Pest tests. Adding personal identifiers or raw signed data to
this table requires a new privacy review and policy change.

## 8. Change control

Changes to the retention windows or to the job's behaviour require:

1. Spec amendment to `April/April24Updates/spec/01-invariants.md` §INV-2.10.2.
2. Code change to the relevant retention path: `app/Jobs/AiAuditRetentionJob.php`
   for AI events, or `config/retention.php` and
   `app/Console/Commands/PurgeAuditLogs.php` for Apple notification audit.
3. Update to this document with a new effective date.
4. Pest coverage update (the job has a sibling test that pins the window math; reduction would loosen it).

Window extensions are safe to deploy directly. Window contractions or new deletion scopes need a `dev` deploy + soak window before reaching `main`, since they delete data the previous policy would have retained.
