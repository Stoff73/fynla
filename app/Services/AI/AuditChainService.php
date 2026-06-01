<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiAuditEvent;
use Illuminate\Support\Facades\DB;

/**
 * S0.12 — Append-only hash-chained audit log for AI tool calls.
 *
 * `append()` serialises the supplied event payload, prefixes the previous
 * row's `row_hash`, hashes the lot with SHA-256 to form the new `row_hash`,
 * then HMAC-signs that with `config('app.ai_audit_hmac_key')`. Wrapped in a
 * `DB::transaction` with `lockForUpdate` so concurrent writers serialise on
 * the latest row and the chain stays single-threaded.
 *
 * `verifyChain()` walks the table in id order and re-hashes each row from
 * its predecessor, returning the breakpoint id on the first mismatch. The
 * artisan command `ai:audit:verify-chain` exposes this for ops + tests.
 *
 * INV-2.10.2 in `April/April24Updates/spec/01-invariants.md` defines the
 * exact serialisation contract — fields covered by `row_hash` are
 * user_id, conversation_id, tool_name, operation, status, input_summary,
 * result_summary, entity_type, entity_id (the chain & signature columns
 * are intentionally NOT covered, otherwise the hash would be self-referential).
 *
 * Hashed JSON is canonicalised via deep-ksort before encoding. MySQL's
 * binary JSON column type reorders object keys on storage (sorts by key
 * length ascending, ties by insertion order). Without canonicalisation
 * the write-time PHP array order differs from the read-back order and
 * `verifyChain()` cannot reproduce the stored `row_hash` for any row whose
 * `input_summary` keys aren't already in MySQL-sort order. Canonicalising
 * to alphabetical key order makes the hash input independent of either
 * MySQL's internal sort or PHP's array iteration order.
 */
final class AuditChainService
{
    private const ZERO_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

    /**
     * Phase 2 — sentinel tool name for the per-turn episode attestation row
     * (hash_scheme = 2). A v2 row binds the v1 payload serialisation PLUS the
     * episodic blob SHA, the served semantic snapshot id, and a digest of the
     * fetch provenance. Everything needed to re-derive the hash lives on the
     * row's result_summary, so verification is self-contained and never
     * depends on a mutable ai_messages row. INV-2.10.2 (v1) is unchanged.
     */
    private const EPISODE_TOOL = '__episode__';

    /**
     * M19 — fail loud rather than HMAC-signing with a constant fallback.
     * If neither AI_AUDIT_HMAC_KEY nor APP_KEY is set, the audit chain is
     * forgeable by anyone who can read the codebase. Throw on the first
     * audit-write so a broken deploy is caught immediately instead of
     * silently corrupting the chain.
     */
    private static function hmacKey(): string
    {
        $key = (string) config('app.ai_audit_hmac_key');
        if ($key === '') {
            throw new \RuntimeException(
                'app.ai_audit_hmac_key is not configured. Set AI_AUDIT_HMAC_KEY '
                .'(preferred) or APP_KEY in the environment before invoking the '
                .'audit chain.'
            );
        }

        return $key;
    }

    /**
     * Fields included in the SHA-256 row hash. Order matters — both
     * `append()` and `verifyChain()` must produce the same JSON.
     */
    private const HASHED_FIELDS = [
        'user_id',
        'conversation_id',
        'tool_name',
        'operation',
        'status',
        'input_summary',
        'result_summary',
        'entity_type',
        'entity_id',
    ];

    public function append(array $event): AiAuditEvent
    {
        return DB::transaction(function () use ($event) {
            $prev = AiAuditEvent::query()->lockForUpdate()->latest('id')->first();
            $prevHash = $prev?->row_hash ?? self::ZERO_HASH;

            $signedAt = now();

            $payload = [
                'user_id' => $event['user_id'] ?? null,
                'conversation_id' => $event['conversation_id'] ?? null,
                'tool_name' => $event['tool_name'] ?? '',
                'operation' => $event['operation'] ?? 'read',
                'status' => $event['status'] ?? 'dispatched',
                'input_summary' => $event['input_summary'] ?? null,
                'result_summary' => $event['result_summary'] ?? null,
                'entity_type' => $event['entity_type'] ?? null,
                'entity_id' => $event['entity_id'] ?? null,
            ];

            $rowHash = self::computeRowHash($prevHash, $payload, $signedAt->toIso8601String());
            $signature = hash_hmac('sha256', $rowHash, self::hmacKey());

            return AiAuditEvent::create([
                ...$payload,
                'prev_hash' => $prevHash,
                'row_hash' => $rowHash,
                'signed_at' => $signedAt,
                'signature' => $signature,
                'created_at' => $signedAt,
            ]);
        });
    }

    /**
     * Phase 2 — append a per-turn `__episode__` attestation (hash_scheme = 2).
     *
     * Mirrors `append()`'s transaction/lock/prev-hash pattern, but the row
     * hash binds the v1 payload serialisation PLUS three extra fields:
     * `blob_md_sha256`, `semantic_snapshot_id`, and a stable
     * `provenance_digest` over the fetch-provenance digests. Those three (plus
     * the blob path) are persisted to `result_summary` so verification can
     * re-derive the hash from the row alone.
     */
    public function appendEpisode(array $event): AiAuditEvent
    {
        return DB::transaction(function () use ($event) {
            $prev = AiAuditEvent::query()->lockForUpdate()->latest('id')->first();
            $prevHash = $prev?->row_hash ?? self::ZERO_HASH;

            $signedAt = now();

            $blobSha = (string) ($event['blob_md_sha256'] ?? '');
            $snapshotId = $event['semantic_snapshot_id'] ?? null;
            $provDigest = hash('sha256', (string) json_encode(
                array_column($event['fetch_provenance'] ?? [], 'digest')
            ));

            $resultSummary = [
                'blob_md_sha256' => $blobSha,
                'blob_md_path' => $event['blob_md_path'] ?? null,
                'semantic_snapshot_id' => $snapshotId,
                'provenance_digest' => $provDigest,
            ];

            $payload = [
                'user_id' => $event['user_id'] ?? null,
                'conversation_id' => $event['conversation_id'] ?? null,
                'tool_name' => self::EPISODE_TOOL,
                'operation' => 'persist',
                'status' => 'persisted',
                'input_summary' => null,
                'result_summary' => $resultSummary,
                'entity_type' => 'ai_message',
                'entity_id' => $event['entity_id'] ?? null,
            ];

            $rowHash = self::computeEpisodeRowHash(
                $prevHash,
                $payload,
                $signedAt->toIso8601String(),
                $blobSha,
                $snapshotId,
                $provDigest
            );
            $signature = hash_hmac('sha256', $rowHash, self::hmacKey());

            return AiAuditEvent::create([
                ...$payload,
                'prev_hash' => $prevHash,
                'row_hash' => $rowHash,
                'signed_at' => $signedAt,
                'signature' => $signature,
                'hash_scheme' => 2,
                'created_at' => $signedAt,
            ]);
        });
    }

    /**
     * Walk every row in id order and re-derive each row hash from its
     * predecessor. Returns `chain_valid: false` with `broken_at` on the
     * first mismatch.
     *
     * @return array{chain_valid: bool, tip_hash?: string, row_count: int, broken_at?: int}
     */
    public function verifyChain(): array
    {
        $prevHash = self::ZERO_HASH;
        $count = 0;

        foreach (AiAuditEvent::query()->orderBy('id')->cursor() as $row) {
            $payload = self::extractHashedPayload($row);

            if ((int) $row->hash_scheme === 2) {
                $rs = $row->result_summary ?? [];
                $expected = self::computeEpisodeRowHash(
                    $prevHash,
                    $payload,
                    $row->signed_at->toIso8601String(),
                    (string) ($rs['blob_md_sha256'] ?? ''),
                    $rs['semantic_snapshot_id'] ?? null,
                    (string) ($rs['provenance_digest'] ?? '')
                );
            } else {
                $expected = self::computeRowHash(
                    $prevHash,
                    $payload,
                    $row->signed_at->toIso8601String()
                );
            }

            if (! hash_equals($expected, (string) $row->row_hash)) {
                return [
                    'chain_valid' => false,
                    'broken_at' => (int) $row->id,
                    'row_count' => $count,
                ];
            }

            $prevHash = $row->row_hash;
            $count++;
        }

        return [
            'chain_valid' => true,
            'tip_hash' => $prevHash,
            'row_count' => $count,
        ];
    }

    /**
     * Re-derive the HMAC signature for a row. Useful for verifying the
     * signing key alone, separate from the chain integrity check.
     */
    public function verifySignature(AiAuditEvent $row): bool
    {
        $expected = hash_hmac('sha256', (string) $row->row_hash, self::hmacKey());

        return hash_equals($expected, (string) $row->signature);
    }

    private static function computeRowHash(string $prevHash, array $payload, string $signedAtIso): string
    {
        $serialised = self::serialiseForHash($payload);

        return hash('sha256', $prevHash.$serialised.$signedAtIso);
    }

    /**
     * Phase 2 — v2 (`hash_scheme = 2`) row hash for `__episode__` rows.
     *
     * Reuses the EXACT v1 payload serialisation (`serialiseForHash`) so the
     * v1 portion is byte-identical, then appends the three episode-binding
     * fields to the hash input before SHA-256. Verification re-derives these
     * from the row's own `result_summary`, so the attestation is
     * self-contained. v1 rows never reach this method.
     */
    private static function computeEpisodeRowHash(
        string $prevHash,
        array $payload,
        string $signedAtIso,
        string $blobSha,
        ?string $snapshotId,
        string $provDigest
    ): string {
        $serialised = self::serialiseForHash($payload);
        $input = $prevHash.$serialised.$signedAtIso.'|'.$blobSha.'|'.((string) $snapshotId).'|'.$provDigest;

        return hash('sha256', $input);
    }

    /**
     * Canonicalise and JSON-encode the hashed payload. Shared by the v1
     * (`computeRowHash`) and v2 (`computeEpisodeRowHash`) hashes so both
     * produce byte-identical serialisation for the v1 field set.
     */
    private static function serialiseForHash(array $payload): string
    {
        $canonical = self::canonicaliseForHash($payload);

        return json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Recursively sort associative-array keys alphabetically so the hash
     * input is independent of write-time PHP array order or MySQL's binary
     * JSON key reordering. Numeric (list) arrays preserve their order —
     * only string-keyed objects are sorted.
     */
    private static function canonicaliseForHash(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);
        $result = [];
        foreach ($value as $k => $v) {
            $result[$k] = self::canonicaliseForHash($v);
        }

        if (! $isList) {
            ksort($result);
        }

        return $result;
    }

    /**
     * Extract the hashed-fields payload from a stored row in the same
     * shape `append()` uses, so re-hashing is deterministic across PHP
     * processes and DB drivers.
     *
     * @return array<string, mixed>
     */
    private static function extractHashedPayload(AiAuditEvent $row): array
    {
        $payload = [];
        foreach (self::HASHED_FIELDS as $field) {
            $value = $row->getAttribute($field);
            // Cast model attributes back to the same primitive types
            // `append()` saw, so the JSON is byte-identical.
            if ($field === 'user_id' || $field === 'conversation_id' || $field === 'entity_id') {
                $payload[$field] = $value === null ? null : (int) $value;
            } else {
                $payload[$field] = $value;
            }
        }

        return $payload;
    }
}
