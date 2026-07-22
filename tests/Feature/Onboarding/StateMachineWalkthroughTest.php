<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\ExpenditureProfile;
use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

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
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => null,
        'first_name' => 'Test',
    ]);

    // S0.9 — runtime guard now requires ai_chat consent before any
    // /api/ai-chat/* endpoint streams. This walkthrough drives the
    // state machine via real HTTP, so grant the canonical consent
    // record up-front.
    app(ConsentService::class)->recordConsent($this->user, UserConsent::TYPE_AI_CHAT, true);
});

function sendOnboardingMessage(
    TestCase $testCase,
    User $user,
    int $conversationId,
    string $message
): string {
    Sanctum::actingAs($user->fresh());

    return $testCase->postJson(
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

        // Step 3 — journey_selection bubble: "Protecting What Matters" → base_personal
        // (Canonical label to match /onboarding/welcome and lifeStageConfig.js)
        sendOnboardingMessage($this, $this->user, $conversation->id, 'Protecting What Matters');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_PERSONAL)
            ->and($this->user->onboarding_fyn_selection)->toBe('protection');

        // Step 4 — simulate grouped_extract (base_personal). Single route
        // takes us past base_spouse straight to base_dependants.
        jumpTo($this->user->id, OnboardingStateMachine::STATE_BASE_DEPENDANTS, [
            'date_of_birth' => '1985-01-15',
            'marital_status' => 'single',
        ]);

        // Step 5 — base_dependants bubble: "No" → profile_review_family (Phase 10)
        sendOnboardingMessage($this, $this->user, $conversation->id, 'No');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_PROFILE_REVIEW_FAMILY)
            ->and($this->user->onboarding_fyn_context['has_dependants'] ?? null)->toBeFalse();

        // Step 5b — profile_review_family bubble: "Looks correct" → base_employment
        sendOnboardingMessage($this, $this->user, $conversation->id, 'Looks correct');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_EMPLOYMENT);

        // Step 6 — base_employment bubble: "Full-time" → base_work.
        // parseEmploymentFromText canonicalises "Full-time" to 'full_time'
        // (the user column value); the bubble id 'employed' is only used
        // when the parser returns null.
        sendOnboardingMessage($this, $this->user, $conversation->id, 'Full-time');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_WORK)
            ->and($this->user->employment_status)->toBe('full_time');

        // Step 7 — simulate grouped_extract work capture; advance into the
        // new multi-job loop state (Phase 10).
        jumpTo($this->user->id, OnboardingStateMachine::STATE_BASE_EMPLOYMENT_MORE, [
            'employer' => 'Dentsu',
            'occupation' => 'Chief Marketing Officer',
            'annual_employment_income' => 50000,
        ]);

        // Step 7b — base_employment_more bubble: "No, that's everything" → base_expenditure
        sendOnboardingMessage($this, $this->user, $conversation->id, "No, that's everything");

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_BASE_EXPENDITURE);

        // Step 8 — base_expenditure free_text: "10000" → profile_review_expenditure (Phase 10)
        $expenditureStream = sendOnboardingMessage($this, $this->user, $conversation->id, '10000');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_PROFILE_REVIEW_EXPENDITURE)
            ->and((float) $this->user->monthly_expenditure)->toBe(10000.0)
            ->and($this->user->expenditure_entry_mode)->toBe('simple')
            ->and($expenditureStream)->toContain('"type":"capture_complete"');

        // Step 8a — profile_review_expenditure bubble: "Looks correct" → asset_capture
        sendOnboardingMessage($this, $this->user, $conversation->id, 'Looks correct');

        $this->user->refresh();
        expect($this->user->onboarding_fyn_step)
            ->toBe(OnboardingStateMachine::STATE_ASSET_CAPTURE);

        // Step 8b — ExpenditureProfile sync (covers bug §4 from 88018a5)
        $profile = ExpenditureProfile::where('user_id', $this->user->id)->first();
        expect($profile)->not->toBeNull()
            ->and((float) $profile->total_monthly_expenditure)->toBe(10000.0);

        // Step 9 — simulate asset_capture delegation advancing to add_more
        jumpTo($this->user->id, OnboardingStateMachine::STATE_ADD_MORE, [
            'onboarding_fyn_context' => json_encode(['visited_focuses' => ['protection']]),
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
            'onboarding_fyn_selection' => 'protection',
            'date_of_birth' => '1955-01-15',
            'marital_status' => 'single',
            'onboarding_fyn_context' => json_encode([]),
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
            'onboarding_fyn_selection' => 'protection',
            'onboarding_fyn_context' => json_encode(['visited_focuses' => ['protection']]),
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
