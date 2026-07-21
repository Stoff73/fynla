<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * CoALA Phase 5 PR 2 — the ground gate's rejection path inside the chat loop.
 *
 * When a write surface is rejected in advice mode, HasAiChat::rejectGroundSurface
 * must (1) write an ai_audit_events chain row with status:stripped so the
 * blocked attempt is forensically recorded, and (2) return a safe, non-executing
 * observation to the LLM loop.
 */
function invokeRejectGroundSurface(string $tool, User $user, ?int $conversationId): array
{
    $agent = app(CoordinatingAgent::class);
    $reflection = new ReflectionMethod($agent, 'rejectGroundSurface');
    $reflection->setAccessible(true);

    return $reflection->invoke($agent, $tool, $user, $conversationId);
}

describe('HasAiChat::rejectGroundSurface (ground gate rejection path)', function () {
    it('returns a blocked observation that did not execute the write', function () {
        $user = User::factory()->create();

        $result = invokeRejectGroundSurface('create_savings_account', $user, null);

        expect($result['success'])->toBeFalse()
            ->and($result['blocked'])->toBeTrue()
            ->and($result['message'])->toBeString()
            // must NOT signal a created record — that would bump partialWriteCount
            ->and($result)->not->toHaveKey('created');
    });

    it('writes a status:stripped audit-chain row for the blocked write', function () {
        $user = User::factory()->create();

        invokeRejectGroundSurface('delete_record', $user, null);

        expect(
            AiAuditEvent::where('user_id', $user->id)
                ->where('tool_name', 'delete_record')
                ->where('status', 'stripped')
                ->exists()
        )->toBeTrue();
    });
});
