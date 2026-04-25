<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AuditChainService;

/**
 * S0.12 — HMAC signature is HMAC-SHA256 of row_hash keyed on
 * config('app.ai_audit_hmac_key'). Tampering with row_hash without re-signing
 * is detectable; tampering with the key invalidates every signature.
 */
describe('AuditChainService HMAC signature (S0.12)', function () {
    it('signs each row with HMAC-SHA256 of row_hash keyed on the configured secret', function () {
        config(['app.ai_audit_hmac_key' => 'test-secret-key-for-signing-1234']);

        $service = app(AuditChainService::class);
        $user = User::factory()->create();

        $row = $service->append([
            'user_id' => $user->id,
            'tool_name' => 'list_records',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);

        $expected = hash_hmac('sha256', $row->row_hash, 'test-secret-key-for-signing-1234');

        expect($row->signature)->toBe($expected)
            ->and($service->verifySignature($row))->toBeTrue();
    });

    it('reports the signature as invalid when the secret rotates without re-signing', function () {
        config(['app.ai_audit_hmac_key' => 'original-secret']);
        $service = app(AuditChainService::class);
        $user = User::factory()->create();

        $row = $service->append([
            'user_id' => $user->id,
            'tool_name' => 'list_records',
            'operation' => 'read',
            'status' => 'dispatched',
        ]);

        // Rotate the secret. Existing rows were signed with the old key, so
        // the new key sees them as invalid — operators can detect that the
        // chain pre-dates the key change.
        config(['app.ai_audit_hmac_key' => 'rotated-secret']);

        expect($service->verifySignature($row->fresh()))->toBeFalse();
    });
});
