<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\PensionInputHistory;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('writes pension_input_history rows for each entry', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [
            ['tax_year' => '2024/25', 'pension_input_amount' => 25000],
            ['tax_year' => '2023/24', 'pension_input_amount' => 18000],
            ['tax_year' => '2022/23', 'pension_input_amount' => 0],
        ],
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['field_group'] ?? null)->toBe('campaign_pension_history');
    expect($result['details'])->toHaveKey('2024/25')
        ->and($result['details']['2024/25'])->toBe(25000.0);
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(3);
});

it('updates existing rows when called twice for same tax year', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 5000]],
    ], $user);

    app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 9000]],
    ], $user);

    $rows = PensionInputHistory::where('user_id', $user->id)->get();
    expect($rows)->toHaveCount(1);
    expect((float) $rows->first()->pension_input_amount)->toBe(9000.0);
});

it('rejects empty history array', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [],
    ], $user);

    expect($result['error'] ?? null)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('validation_failed');
    expect(PensionInputHistory::count())->toBe(0);
});

it('skips negative amounts', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [
            ['tax_year' => '2024/25', 'pension_input_amount' => -100],
            ['tax_year' => '2023/24', 'pension_input_amount' => 4000],
        ],
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['details'])->toHaveKey('2023/24')
        ->and($result['details'])->not->toHaveKey('2024/25');
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(1);
});

it('blocks preview users', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 5000]],
    ], $user);

    expect($result['blocked'] ?? false)->toBeTrue();
    expect(PensionInputHistory::count())->toBe(0);
});

// ─── Carry-forward total-vs-per-year disambiguation guard ───────────────────
//
// A lone figure for the three-year pension window ("Around £90,000") is fatally
// ambiguous: £90k spread across three years is unused allowance to top up; £90k
// PER YEAR is an annual-allowance charge. Whatever split the model invents to
// satisfy the per-year tool schema is fabricated. So when the SOURCE message
// carries a single figure with no per-year structure, the handler must write
// nothing and ask the clarifying question.

$seedPensionConv = function (User $user, string $text): int {
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    AiMessage::create([
        'conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => $text,
    ]);

    return $conversation->id;
};

it('asks to clarify and writes nothing for a single ambiguous figure', function () use ($seedPensionConv) {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversationId = $seedPensionConv($user, 'Around £90000');

    // The model, forced by the tool schema, parks the lone figure under one year.
    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 90000]],
    ], $user, $conversationId);

    expect($result['onboarding_capture_error'] ?? false)->toBeTrue();
    expect($result['error_type'] ?? null)->toBe('pension_history_ambiguous');
    expect(strtolower((string) ($result['message'] ?? '')))
        ->toContain('per year')
        ->toContain('total');
    // Nothing written — the figure is not yet disambiguated.
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(0);
});

it('captures normally when the figure is stated per year', function () use ($seedPensionConv) {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversationId = $seedPensionConv($user, 'about 5,000 each year');

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [
            ['tax_year' => '2024/25', 'pension_input_amount' => 5000],
            ['tax_year' => '2023/24', 'pension_input_amount' => 5000],
            ['tax_year' => '2022/23', 'pension_input_amount' => 5000],
        ],
    ], $user, $conversationId);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(3);
});

it('captures normally when the user labels the figure as a window total', function () use ($seedPensionConv) {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversationId = $seedPensionConv($user, '£90,000 in total across the three years');

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 90000]],
    ], $user, $conversationId);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['onboarding_capture_error'] ?? false)->toBeFalse();
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(1);
});

it('captures normally when multiple distinct figures are given', function () use ($seedPensionConv) {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversationId = $seedPensionConv($user, '30k, 25k and 20k');

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [
            ['tax_year' => '2024/25', 'pension_input_amount' => 30000],
            ['tax_year' => '2023/24', 'pension_input_amount' => 25000],
            ['tax_year' => '2022/23', 'pension_input_amount' => 20000],
        ],
    ], $user, $conversationId);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(3);
});

it('does not trip the guard on a zero / none answer', function () use ($seedPensionConv) {
    $user = User::factory()->create(['is_preview_user' => false]);
    $conversationId = $seedPensionConv($user, 'zero in all of them');

    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 0]],
    ], $user, $conversationId);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect($result['onboarding_capture_error'] ?? false)->toBeFalse();
});

it('captures (no guard) when no conversation context is available', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // Null conversation id — the legacy direct-write path. The guard cannot see
    // a source message, so capture proceeds exactly as before.
    $result = app(CoordinatingAgent::class)->executeTool('capture_pension_history', [
        'history' => [['tax_year' => '2024/25', 'pension_input_amount' => 90000]],
    ], $user, null);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect(PensionInputHistory::where('user_id', $user->id)->count())->toBe(1);
});
