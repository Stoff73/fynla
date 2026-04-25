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
 */
final class AuditChainService
{
    private const ZERO_HASH = '0000000000000000000000000000000000000000000000000000000000000000';

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
            $signature = hash_hmac('sha256', $rowHash, (string) config('app.ai_audit_hmac_key'));

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
            $expected = self::computeRowHash(
                $prevHash,
                $payload,
                $row->signed_at->toIso8601String()
            );

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
        $expected = hash_hmac('sha256', (string) $row->row_hash, (string) config('app.ai_audit_hmac_key'));

        return hash_equals($expected, (string) $row->signature);
    }

    private static function computeRowHash(string $prevHash, array $payload, string $signedAtIso): string
    {
        $serialised = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return hash('sha256', $prevHash.$serialised.$signedAtIso);
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
