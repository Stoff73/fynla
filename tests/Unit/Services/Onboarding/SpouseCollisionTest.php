<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Exceptions\SpouseCollisionException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\SpouseLinkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers PRD FR-M13 (F2) — spouse-email collision distinct path.
 *
 * Three layers exercised:
 *   1. SpouseLinkingService throws SpouseCollisionException when the
 *      target email belongs to a third party's household.
 *   2. CoordinatingAgent::handleCaptureSpouseDetails converts the
 *      exception into an `onboarding_capture_error` result with
 *      error_type='spouse_collision'.
 *   3. OnboardingChatDirector::handleGroupedExtractTurn picks up the
 *      `onboarding_capture_error` event, calls emitTerminalError with
 *      the handler's message, and leaves state on base_spouse.
 *
 * PRD: April/April20Updates/PRD-fyn-driven-onboarding.md §FR-M13
 */
afterEach(function () {
    Mockery::close();
});

describe('SpouseLinkingService::linkOrCreateSpouse (collision path)', function () {
    it('throws SpouseCollisionException when the email is linked to another household', function () {
        $currentUser = User::factory()->create(['marital_status' => 'married']);
        $thirdParty = User::factory()->create();
        $alreadyLinkedSpouse = User::factory()->create([
            'email' => 'busy@example.com',
            'spouse_id' => $thirdParty->id,
        ]);

        $service = app(SpouseLinkingService::class);

        expect(fn () => $service->linkOrCreateSpouse($currentUser, [
            'first_name' => 'Pat',
            'email' => 'busy@example.com',
            'date_of_birth' => '1990-01-01',
        ]))->toThrow(SpouseCollisionException::class);
    });

    /**
     * Regression for P0.8 — the spouse email lookup must be case-insensitive.
     * Previously a mixed-case input ("Busy@Example.com") did not match a
     * lowercase stored email and either fell through to creating a duplicate
     * account or, on case-mismatched collations, surfaced confusing collision
     * errors against the wrong row.
     */
    it('matches a stored lowercase email when the input is mixed-case', function () {
        $currentUser = User::factory()->create(['marital_status' => 'married']);
        $thirdParty = User::factory()->create();
        User::factory()->create([
            'email' => 'busy@example.com',
            'spouse_id' => $thirdParty->id,
        ]);

        $service = app(SpouseLinkingService::class);

        expect(fn () => $service->linkOrCreateSpouse($currentUser, [
            'first_name' => 'Pat',
            'email' => 'Busy@Example.COM',
            'date_of_birth' => '1990-01-01',
        ]))->toThrow(SpouseCollisionException::class);
    });
});

describe('CoordinatingAgent::handleCaptureSpouseDetails (collision branch)', function () {
    it('returns onboarding_capture_error with error_type=spouse_collision', function () {
        $currentUser = User::factory()->create(['marital_status' => 'married']);
        $thirdParty = User::factory()->create();
        User::factory()->create([
            'email' => 'busy@example.com',
            'spouse_id' => $thirdParty->id,
        ]);

        $agent = app(CoordinatingAgent::class);
        $reflection = new ReflectionMethod($agent, 'handleCaptureSpouseDetails');
        $reflection->setAccessible(true);

        $result = $reflection->invoke($agent, [
            'first_name' => 'Pat',
            'email' => 'busy@example.com',
            'date_of_birth' => '1990-01-01',
        ], $currentUser);

        expect($result['onboarding_capture_error'] ?? false)->toBeTrue()
            ->and($result['error_type'] ?? null)->toBe('spouse_collision')
            ->and($result['message'] ?? '')->toContain('already registered');
    });
});

describe('OnboardingChatDirector::handleGroupedExtractTurn (spouse collision)', function () {
    it('emits emitTerminalError and leaves state on base_spouse when capture error arrives', function () {
        $user = User::factory()->create([
            'first_name' => 'Test',
            'is_preview_user' => false,
            'marital_status' => 'married',
            'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_SPOUSE,
        ]);

        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'status' => 'active',
            'model_used' => 'director',
            'title' => 'Onboarding',
        ]);

        // Mock the delegated generator — yield an onboarding_capture_error
        // event as if the real tool executor had caught the collision.
        $fakeEvents = [
            ['type' => 'tool_use', 'tool' => 'capture_spouse_details'],
            [
                'type' => 'onboarding_capture_error',
                'field_group' => 'spouse',
                'error_type' => 'spouse_collision',
                'message' => "That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?",
            ],
            ['type' => 'content', 'text' => 'This text must be dropped.'],
            ['type' => 'done', 'message_id' => 99],
        ];

        $mock = Mockery::mock(CoordinatingAgent::class);
        $mock->shouldReceive('chatWithPromptOverride')
            ->once()
            ->andReturnUsing(function () use ($fakeEvents) {
                foreach ($fakeEvents as $event) {
                    yield $event;
                }
            });
        $this->instance(CoordinatingAgent::class, $mock);

        $director = app(OnboardingChatDirector::class);

        $received = [];
        foreach ($director->handleUserMessage(
            $user,
            $conversation,
            'Pat, born 1990-01-01, busy@example.com'
        ) as $event) {
            $received[] = $event;
        }

        // Director emits the terminal error content + done event.
        $contentEvents = array_values(array_filter($received, fn ($e) => ($e['type'] ?? null) === 'content'));
        expect($contentEvents)->toHaveCount(1)
            ->and($contentEvents[0]['text'])->toContain('already registered');

        // State must stay on base_spouse — no advance to base_dependants.
        $user->refresh();
        expect($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_SPOUSE);

        // The "is_terminal_error" assistant message row is persisted with
        // the error_type metadata so the audit trail captures the branch.
        $terminalMessage = AiMessage::where('conversation_id', $conversation->id)
            ->where('role', 'assistant')
            ->latest('id')
            ->first();
        expect($terminalMessage)->not->toBeNull();
        $meta = is_array($terminalMessage->metadata)
            ? $terminalMessage->metadata
            : json_decode((string) $terminalMessage->metadata, true);
        expect($meta['is_terminal_error'] ?? false)->toBeTrue()
            ->and($meta['error_type'] ?? null)->toBe('spouse_collision');
    });
});
