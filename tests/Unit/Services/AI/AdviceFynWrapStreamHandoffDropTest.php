<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * Regression for P0.9 — `wrapStream` must drop ALL `type === 'handoff'` events
 * that aren't routed to the DELEGATE_TO_CAPTURE handler. INV-2.4.1 forbids any
 * handoff event from reaching the frontend; the previous code only intercepted
 * DELEGATE_TO_CAPTURE and let any other handoff_type (e.g. `capture_complete`)
 * fall through to `yield $event`.
 */
it('wrapStream drops a non-delegate handoff event without yielding it', function (): void {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'is_preview_user' => false,
    ]);
    $conv = AiConversation::create([
        'user_id' => $user->id,
        'status' => 'active',
        'model_used' => 'test',
    ]);

    $upstream = function (): Generator {
        yield ['type' => 'token', 'text' => 'hello'];
        yield ['type' => 'handoff', 'handoff_type' => 'capture_complete', 'payload' => ['foo' => 'bar']];
        yield ['type' => 'token', 'text' => ' world'];
        yield ['type' => 'done'];
    };

    $adviceFyn = app(AdviceFyn::class);
    $reflection = new ReflectionMethod($adviceFyn, 'wrapStream');
    $reflection->setAccessible(true);

    /** @var Generator<array<string, mixed>> $wrapped */
    $wrapped = $reflection->invoke($adviceFyn, $upstream(), $user, $conv, 'msg', null);
    $events = iterator_to_array($wrapped, false);
    $types = array_map(fn (array $e) => $e['type'] ?? null, $events);

    expect($types)->not->toContain('handoff');
    expect($types)->toContain('token');
    expect($types)->toContain('done');
});
