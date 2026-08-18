<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Will;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * SPEC-crud-handler-contract §6 acceptance 2 — the C2 matrix, per entity.
 *
 * The pension case was fixed on its own on 2026-08-17 and is pinned by
 * CreatePensionRecaptureTest. This file proves the SAME three-way behaviour now
 * reaches the other handlers through RecaptureGuard, because before it they had
 * no existence check at all and silently created a second row.
 *
 *   fill      blank field on the record — unambiguous, apply it
 *   conflict  a different value — write nothing, ask
 *   identical everything matches — write nothing, ask (never assume a duplicate)
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function callTool(string $tool, array $input, User $user): array
{
    return app(CoordinatingAgent::class)->executeTool($tool, $input, $user);
}

it('asks rather than duplicating when a goal is captured twice identically', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $input = [
        'name' => 'House deposit',
        'goal_type' => 'home_deposit',
        'target_amount' => 40000,
        'target_date' => now()->addYears(4)->toDateString(),
        'priority' => 'high',
    ];

    callTool('create_goal', $input, $user);
    $second = callTool('create_goal', $input, $user);

    expect($second['error_type'] ?? null)->toBe('confirm_duplicate_required');
    expect(Goal::where('user_id', $user->id)->count())->toBe(1);
});

it('never overwrites a goal target — it asks whether this is an edit', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $input = [
        'name' => 'House deposit',
        'goal_type' => 'home_deposit',
        'target_amount' => 40000,
        'target_date' => now()->addYears(4)->toDateString(),
        'priority' => 'high',
    ];

    callTool('create_goal', $input, $user);
    $second = callTool('create_goal', ['target_amount' => 60000] + $input, $user);

    expect($second['error_type'] ?? null)->toBe('confirm_edit_required');
    expect($second['conflicts'])->toHaveKey('target_amount');
    expect((float) Goal::where('user_id', $user->id)->first()->target_amount)->toBe(40000.0);
    expect(Goal::where('user_id', $user->id)->count())->toBe(1);
});

it('fills a blank field on an estate asset rather than creating a second one', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);

    callTool('create_asset', [
        'asset_name' => 'Antique Watch',
        'asset_type' => 'other',
        'current_value' => 5000,
    ], $user);

    $second = callTool('create_asset', [
        'asset_name' => 'Antique Watch',
        'asset_type' => 'other',
        'current_value' => 5000,
        'exemption_reason' => 'Left to charity',
    ], $user);

    expect($second['updated'] ?? false)->toBeTrue();
    expect(Asset::where('user_id', $user->id)->count())->toBe(1);
    expect(Asset::where('user_id', $user->id)->first()->exemption_reason)->toBe('Left to charity');
});

it('stops a second will capture from silently overwriting the first', function (): void {
    // This handler was an updateOrCreate until 2026-08-17: a second capture
    // replaced the executor with no question asked and no way back.
    $user = User::factory()->create(['is_preview_user' => false]);

    callTool('create_will', ['executor_name' => 'Margaret Hale'], $user);
    $second = callTool('create_will', ['executor_name' => 'John Thornton'], $user);

    expect($second['error_type'] ?? null)->toBe('confirm_edit_required');
    expect(Will::where('user_id', $user->id)->count())->toBe(1);
    expect(Will::where('user_id', $user->id)->first()->executor_name)->toBe('Margaret Hale');
});

it('matches a family member on the whole name, not just the first part', function (): void {
    $user = User::factory()->create(['is_preview_user' => false]);
    $input = [
        'first_name' => 'Lily',
        'surname' => 'Carter',
        'relationship' => 'child',
        'date_of_birth' => '2018-05-12',
    ];

    callTool('create_family_member', $input, $user);
    $same = callTool('create_family_member', $input, $user);
    expect($same['error_type'] ?? null)->toBe('confirm_duplicate_required');

    $sibling = callTool('create_family_member', ['surname' => 'Okafor'] + $input, $user);
    expect($sibling['success'] ?? false)->toBeTrue();
    expect(FamilyMember::where('user_id', $user->id)->count())->toBe(2);
});

it('treats "Aviva Pension" and "Aviva Personal Pension" as the same pension', function (): void {
    // CSJ §7.1 — identity is the normalised name, because exact matching is what
    // produced the original duplicate. Generic product nouns carry no identity.
    $user = User::factory()->create(['is_preview_user' => false]);

    callTool('create_pension', [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ], $user);

    $second = callTool('create_pension', [
        'pension_category' => 'dc',
        'scheme_name' => 'Aviva Personal Pension',
        'scheme_type' => 'workplace',
        'current_fund_value' => 45000,
    ], $user);

    expect($second['error_type'] ?? null)->toBe('confirm_duplicate_required');
    expect(DCPension::where('user_id', $user->id)->count())->toBe(1);
});

it('asks a blocked question once, not again on the next turn', function (): void {
    // Live 2026-08-17: Fyn asked "same House Deposit goal, or a separate one?";
    // the user's next message started a DIFFERENT capture; the model re-emitted
    // the identical create_goal, and the same sentence was put to the user a
    // second time while their actual message went unanswered. The write stays
    // blocked — repeating the sentence does not.
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Recapture repeat',
        'model_used' => 'test-model',
    ]);

    $agent = app(CoordinatingAgent::class);
    $input = [
        'name' => 'House Deposit',
        'goal_type' => 'home_deposit',
        'target_amount' => 25000,
        'target_date' => now()->addYears(4)->toDateString(),
        'priority' => 'high',
    ];

    $agent->executeTool('create_goal', $input, $user, $conversation->id);

    // Turn one: a different target on the same goal — Fyn asks.
    $firstAsk = $agent->executeTool('create_goal', ['target_amount' => 20000] + $input, $user, $conversation->id);
    expect($firstAsk['error_type'])->toBe('confirm_edit_required');
    expect($firstAsk['message'])->toContain('House Deposit');

    // That turn is now history, carrying the question it asked.
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => $firstAsk['message'],
        'persona' => 'data_capture',
        'tool_results' => [['raw' => $firstAsk, 'is_error' => true, 'sequence' => 0]],
    ]);

    // The user moves on to something else entirely.
    $conversation->messages()->create([
        'role' => 'user',
        'content' => 'I have a Chase easy access savings account with 5000 in it',
        'persona' => 'advice',
    ]);

    // Turn two: the model re-issues the same call. Still blocked, still silent.
    $repeat = $agent->executeTool('create_goal', ['target_amount' => 20000] + $input, $user, $conversation->id);

    expect($repeat['error_type'])->toBe('confirm_edit_required');
    expect($repeat['repeated_ask'] ?? false)->toBeTrue();
    expect($repeat['message'])->toBe('', 'The question stands from the turn that asked it.');
    expect(Goal::where('user_id', $user->id)->count())->toBe(1);
    expect((float) Goal::where('user_id', $user->id)->first()->target_amount)->toBe(25000.0);
});

it('still asks when the user is answering, not moving on', function (): void {
    // The mirror of the test above. "300 a month, high priority" answers Fyn's
    // own follow-up, so the blocked question has never been put to the user —
    // silencing it there left them with a bare "the information could not be
    // saved" (live 2026-08-18).
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversation = AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Recapture answer',
        'model_used' => 'test-model',
    ]);

    $agent = app(CoordinatingAgent::class);
    $input = [
        'name' => 'House Deposit',
        'goal_type' => 'home_deposit',
        'target_amount' => 25000,
        'target_date' => now()->addYears(4)->toDateString(),
        'priority' => 'high',
    ];

    $agent->executeTool('create_goal', $input, $user, $conversation->id);

    $firstAsk = $agent->executeTool('create_goal', ['target_amount' => 20000] + $input, $user, $conversation->id);
    $conversation->messages()->create([
        'role' => 'assistant',
        'content' => 'What monthly amount do you plan to contribute?',
        'persona' => 'data_capture',
        'tool_results' => [['raw' => $firstAsk, 'is_error' => true, 'sequence' => 0]],
    ]);
    $conversation->messages()->create([
        'role' => 'user',
        'content' => '300 a month, high priority',
        'persona' => 'advice',
    ]);

    $answered = $agent->executeTool('create_goal', ['target_amount' => 20000] + $input, $user, $conversation->id);

    expect($answered['error_type'])->toBe('confirm_edit_required');
    expect($answered['repeated_ask'] ?? false)->toBeFalse();
    expect($answered['message'])->toContain('House Deposit');
});
