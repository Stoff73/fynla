<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\AuditChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults existing-style appends to hash_scheme 1', function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    $user = User::factory()->create();
    $event = app(AuditChainService::class)->append([
        'user_id' => $user->id, 'tool_name' => 'list_goals', 'operation' => 'read', 'status' => 'dispatched',
    ]);
    $event->refresh();
    expect((int) $event->hash_scheme)->toBe(1);
});
