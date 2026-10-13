<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\ToolResults;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\ValueObjects\CaptureContext;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\FynStreamHarness;

/*
 * Fix plan T4 (N4; P2, Q6, Q7). Every capture_* handler reports a write as
 * `onboarding_capture`, but the stored `capture_write_landed` counted only
 * created/updated/deleted, so every retirement, State Pension or spouse
 * write was recorded as not landed and the next message was pulled back into
 * capture (Brett #3964, #4008; csjones #22766 on the T2 walk).
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

function runCaptureTool(User $user, string $tool, array $input): array
{
    return app(CoordinatingAgent::class)->executeTool($tool, $input, $user);
}

it('counts a capture handler that wrote as a landed write', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-05-01']);

    $result = runCaptureTool($user, 'capture_retirement_goals', ['target_retirement_age' => 60, 'target_retirement_income' => 70373]);

    expect($user->retirementProfile()->first()?->target_retirement_age)->toBe(60)
        ->and(ToolResults::wroteData($result))->toBeTrue();
});

it('does not count a capture handler that wrote nothing', function (): void {
    $user = User::factory()->create(['is_preview_user' => false, 'date_of_birth' => '1980-05-01']);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test', 'title' => 'Chat']);

    // Income without an age: parked, nothing saved, the age is asked for.
    $incomeOnly = app(CoordinatingAgent::class)->executeTool('capture_retirement_goals', ['target_retirement_income' => 70373], $user, $conversation->id);
    // No forecast, no qualifying years: nothing recorded.
    $noStatePension = runCaptureTool($user, 'capture_state_pension', []);

    expect($user->retirementProfile()->first())->toBeNull()
        ->and(ToolResults::wroteData($incomeOnly))->toBeFalse()
        ->and(ToolResults::wroteData($noStatePension))->toBeFalse()
        ->and(ToolResults::wroteData(['error' => true, 'message' => 'Validation failed']))->toBeFalse();
});

it('still counts records created, updated and deleted', function (string $flag): void {
    expect(ToolResults::wroteData(['success' => true, $flag => true, 'entity_type' => 'savings_account', 'entity_id' => 1]))->toBeTrue();
})->with(['created', 'updated', 'deleted']);

it('stores an inline capture turn that wrote through a capture handler as landed', function (): void {
    // The csjones T2 walk, #22766: Advice Fyn handed a question to capture and
    // capture_retirement_goals wrote age 60 and £70,373; the row said not landed.
    $user = User::factory()->create(['is_preview_user' => false, 'onboarding_completed' => true, 'date_of_birth' => '1980-05-01']);
    $conversation = AiConversation::create(['user_id' => $user->id, 'status' => 'active', 'model_used' => 'test', 'title' => 'Chat'])->fresh();
    FynStreamHarness::fake()
        ->toolTurn('capture_retirement_goals', ['target_retirement_age' => 60, 'target_retirement_income' => 70373], 'toolu_rg')
        ->textTurn('Recorded your retirement goal: age 60 and £70,373 a year.')
        ->bind();

    iterator_to_array(app(OnboardingChatDirector::class)->handleInlineCapture(
        $user,
        $conversation,
        'I want to retire at 60 on £70,373 a year',
        CaptureContext::fromArray(['reason' => 'retirement goal stated', 'entity_types' => ['retirement_goals'], 'fields_needed' => []]),
    ), false);

    $captureRow = $conversation->messages()->where('role', 'assistant')->where('persona', 'data_capture')->latest('id')->first();

    expect($user->retirementProfile()->first()?->target_retirement_age)->toBe(60)
        ->and($captureRow)->not->toBeNull()
        ->and($captureRow->metadata['capture_write_landed'] ?? null)->toBeTrue();
});
