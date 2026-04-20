<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Covers PRD FR-M11 — state-machine walkthrough from path_choice to done.
 *
 * The walkthrough drives the director via the real HTTP endpoints for every
 * bubble/free_text transition. For grouped_extract and asset_capture states
 * (which delegate to the LLM via CoordinatingAgent::chatWithPromptOverride),
 * the test directly writes the user columns the handler would have written
 * and advances the state. OnboardingChatDirectorFixesTest.php covers those
 * handlers at the unit level.
 *
 * Note: Sanctum::actingAs($user->fresh()) is called before every HTTP hop
 * because Laravel's auth guard caches the resolved user between test
 * requests — without a fresh rebind, the controller sees the previous
 * request's user instance and misses DB updates made in the test itself.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M11
 */
beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    $this->user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'first_name' => 'Test',
    ]);
});

function sendOnboardingMessage(
    \Tests\TestCase $testCase,
    User $user,
    int $conversationId,
    string $message
): void {
    Sanctum::actingAs($user->fresh());
    $testCase->postJson(
        "/api/ai-chat/conversations/{$conversationId}/messages",
        ['message' => $message]
    )->streamedContent();
}

function jumpTo(int $userId, string $nextState, array $userFields = []): void
{
    DB::table('users')->where('id', $userId)->update(array_merge(
        $userFields,
        ['onboarding_fyn_step' => $nextState]
    ));
}

describe('state-machine walkthrough — path_choice → done', function () {
    it('drives through every bubble transition and terminates at done', function () {
        // Step 1 — start onboarding
        Sanctum::actingAs($this->user);
        $response = $this->postJson('/api/ai-chat/onboarding/start');
        $response->assertOk();
        $response->streamedContent();

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);

        $conversation = AiConversation::forUser($this->user->id)
            ->where('title', 'Onboarding')
            ->first();
        expect($conversation)->not->toBeNull();

        // Step 2 — path_choice bubble: "Follow a journey" → journey_selection
        sendOnboardingMessage($this, $this->user, $conversation->id, 'Follow a journey');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_JOURNEY_SELECTION)
            ->and($this->user->onboarding_fyn_path)->toBe('journey');

        // Step 3 — journey_selection bubble: "Protecting and growing" → base_personal
        sendOnboardingMessage($this, $this->user, $conversation->id, 'Protecting and growing');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL)
            ->and($this->user->onboarding_fyn_selection)->toBe('family');

        // Step 4 — simulate grouped_extract (base_personal). Single route
        // takes us past base_spouse straight to base_dependants.
        jumpTo($this->user->id, OnboardingStateMachine::STATE_BASE_DEPENDANTS, [
            'date_of_birth' => '1985-01-15',
            'marital_status' => 'single',
        ]);

        // Step 5 — base_dependants bubble: "No" → base_employment
        sendOnboardingMessage($this, $this->user, $conversation->id, 'No');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT)
            ->and($this->user->onboarding_fyn_context['has_dependants'] ?? null)->toBeFalse();

        // Step 6 — base_employment bubble: "Employed" → base_work
        sendOnboardingMessage($this, $this->user, $conversation->id, 'Employed');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_WORK)
            ->and($this->user->employment_status)->toBe('employed');

        // Step 7 — simulate grouped_extract work capture
        jumpTo($this->user->id, OnboardingStateMachine::STATE_BASE_EXPENDITURE, [
            'employer' => 'Dentsu',
            'occupation' => 'Chief Marketing Officer',
            'annual_employment_income' => 50000,
        ]);

        // Step 8 — base_expenditure free_text: "4000" → asset_capture
        sendOnboardingMessage($this, $this->user, $conversation->id, '4000');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE)
            ->and((float) $this->user->monthly_expenditure)->toBe(4000.0);

        // Step 8b — ExpenditureProfile sync (covers bug §4 from 88018a5)
        $profile = ExpenditureProfile::where('user_id', $this->user->id)->first();
        expect($profile)->not->toBeNull()
            ->and((float) $profile->total_monthly_expenditure)->toBe(4000.0);

        // Step 9 — simulate asset_capture delegation advancing to add_more
        jumpTo($this->user->id, OnboardingStateMachine::STATE_ADD_MORE, [
            'onboarding_fyn_context' => json_encode(['visited_focuses' => ['family']]),
        ]);

        // Step 10 — add_more bubble: "I'm done" → STATE_DONE (terminal)
        sendOnboardingMessage($this, $this->user, $conversation->id, "I'm done");

        $this->user->refresh();
        expect($this->user->onboarding_completed)->toBeTrue()
            ->and($this->user->onboarding_fyn_step)->toBeNull();
    });

    it('routes employment=retired to base_retirement_date', function () {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/ai-chat/onboarding/start')->streamedContent();

        $conversation = AiConversation::forUser($this->user->id)
            ->where('title', 'Onboarding')
            ->first();

        jumpTo($this->user->id, OnboardingStateMachine::STATE_BASE_EMPLOYMENT, [
            'onboarding_fyn_path' => 'journey',
            'onboarding_fyn_selection' => 'family',
            'date_of_birth' => '1955-01-15',
            'marital_status' => 'single',
        ]);

        sendOnboardingMessage($this, $this->user, $conversation->id, 'Retired');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_RETIREMENT_DATE)
            ->and($this->user->employment_status)->toBe('retired');
    });

    it('loops add_more back into asset_capture when a new focus is picked', function () {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/ai-chat/onboarding/start')->streamedContent();

        $conversation = AiConversation::forUser($this->user->id)
            ->where('title', 'Onboarding')
            ->first();

        jumpTo($this->user->id, OnboardingStateMachine::STATE_ADD_MORE, [
            'onboarding_fyn_path' => 'journey',
            'onboarding_fyn_selection' => 'family',
            'onboarding_fyn_context' => json_encode(['visited_focuses' => ['family']]),
        ]);

        sendOnboardingMessage($this, $this->user, $conversation->id, 'Savings');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE)
            ->and($this->user->onboarding_fyn_selection)->toBe('savings')
            ->and($this->user->onboarding_fyn_context['visited_focuses'] ?? [])
            ->toContain('savings');
    });
});
