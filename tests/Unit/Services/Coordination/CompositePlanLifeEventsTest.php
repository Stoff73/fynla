<?php

declare(strict_types=1);

use App\Models\LifeEvent;
use App\Models\User;
use App\Services\Coordination\CompositePlanService;

it('surfaces near-term life-event capital in the composite financials', function (): void {
    $user = User::factory()->create();

    LifeEvent::create([
        'user_id' => $user->id,
        'event_name' => 'Inheritance',
        'event_type' => 'inheritance',
        'impact_type' => 'income',
        'amount' => 10000,
        'expected_date' => now()->addMonths(6),
        'certainty' => 'confirmed',
        'ownership_type' => 'individual',
        'status' => 'expected',
    ]);

    $fin = app(CompositePlanService::class)->financials($user->fresh());

    // A near-term income event is positive near-term capital the plan can factor.
    expect($fin)->toHaveKey('near_term_capital')
        ->and($fin['near_term_capital'])->toBe(10000.0);
});

it('reports zero near-term capital when there are no life events', function (): void {
    $fin = app(CompositePlanService::class)->financials(User::factory()->create());

    expect($fin['near_term_capital'])->toBe(0.0);
});
