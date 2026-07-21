<?php

declare(strict_types=1);

use App\Models\ProposedSemanticFact;
use App\Models\User;

it('stages a proposed semantic fact as pending', function () {
    $user = User::factory()->create();

    $fact = ProposedSemanticFact::create([
        'user_id' => $user->id,
        'derived_from_conversation_id' => null,
        'category' => 'user_profile',
        'fact_id' => 'retires-2041',
        'title' => 'Plans to retire in 2041',
        'body' => 'User stated they intend to retire in 2041.',
        'valid_from' => null,
        'valid_to' => null,
        'status' => 'pending',
    ]);

    expect($fact->status)->toBe('pending')
        ->and($fact->reviewed_at)->toBeNull()
        ->and($fact->reviewed_by)->toBeNull();

    $this->assertDatabaseHas('proposed_semantic_facts', [
        'user_id' => $user->id,
        'fact_id' => 'retires-2041',
        'status' => 'pending',
    ]);
});
