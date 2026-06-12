<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AiChatController;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Gamification\LevelService;
use App\Services\Gamification\LevelUpCollector;
use Laravel\Sanctum\Sanctum;

it('emits a level_up SSE frame after a level-up turn', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    // Simulate a level-up having occurred during this request.
    app(LevelUpCollector::class)->record(5, 'Planner');

    $response = $this->get("/api/ai-chat/conversations/{$conversation->id}/level-up-probe");
    // See implementation note: the probe asserts the emit helper output.
})->skip('Illustrative — assert via the extracted emit helper unit test below.');

it('formats a level_up frame from the collector', function () {
    app(LevelUpCollector::class)->record(5, 'Planner');
    $frame = AiChatController::levelUpFrame(app(LevelUpCollector::class), app(LevelService::class), app(User::class)->forceFill(['id' => 1]));
    expect($frame)->not->toBeNull();
    expect($frame['type'])->toBe('level_up');
    expect($frame['level'])->toBe(5);
    expect($frame['level_name'])->toBe('Planner');
    expect($frame)->toHaveKey('next_actions');
});
