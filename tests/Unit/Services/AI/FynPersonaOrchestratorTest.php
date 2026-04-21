<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\FynPersonaInvoker;
use App\Services\AI\FynPersonaOrchestrator;
use App\Services\AI\HandoffContract;
use App\Services\AI\QueryClassifier;

/**
 * Orchestrator state-transition tests using a fake invoker — the invoker's
 * handoff-capture contract is verified at integration level by its own
 * test + the registry integrity test. Here we drive the orchestrator's
 * dispatch logic deterministically so state machine bugs surface early.
 */
beforeEach(function () {
    $this->invoker = new class extends FynPersonaInvoker
    {
        public array $calls = [];

        public ?array $nextHandoff = null;

        public array $queuedEvents = [];

        public function __construct()
        {
            // Skip parent constructor — we don't need the DI graph.
        }

        public function invoke(
            string $persona,
            User $user,
            AiConversation $conversation,
            string $message,
            ?string $currentRoute = null,
            ?\App\ValueObjects\CaptureContext $captureContext = null,
            bool $persistUserMessage = true,
        ): \Generator {
            $this->calls[] = [
                'persona' => $persona,
                'message' => $message,
                'capture_context' => $captureContext?->toArray(),
                'persist_user_message' => $persistUserMessage,
            ];

            foreach ($this->queuedEvents as $event) {
                yield $event;
            }
            $this->queuedEvents = [];
        }

        public function lastHandoff(): ?array
        {
            $h = $this->nextHandoff;
            $this->nextHandoff = null;

            return $h;
        }
    };

    $this->orchestrator = new FynPersonaOrchestrator($this->invoker, app(QueryClassifier::class));

    $this->user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    $this->conversation = AiConversation::factory()->create(['user_id' => $this->user->id]);
});

describe('advice state', function () {
    it('invokes advice persona on a normal question', function () {
        $this->invoker->nextHandoff = null;
        $this->invoker->queuedEvents = [
            ['type' => 'content', 'text' => 'Here is some advice.'],
            ['type' => 'done'],
        ];

        $events = iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'Should I contribute more to my pension?',
        ));

        expect($this->invoker->calls)->toHaveCount(1)
            ->and($this->invoker->calls[0]['persona'])->toBe('advice');

        $types = array_column($events, 'type');
        expect($types)->toContain('content')
            ->and($types)->toContain('done');
    });

    it('fast-paths a clean data-entry message to data_capture', function () {
        config(['fyn.classifier_fast_path_enabled' => true]);

        $this->invoker->queuedEvents = [
            ['type' => 'content', 'text' => 'Got it, adding it now.'],
            ['type' => 'done'],
        ];

        iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'Add my Nationwide cash ISA at £5000',
        ));

        expect($this->invoker->calls)->toHaveCount(1)
            ->and($this->invoker->calls[0]['persona'])->toBe('data_capture');
    });

    it('falls through to advice when fast-path disabled', function () {
        config(['fyn.classifier_fast_path_enabled' => false]);

        $this->invoker->queuedEvents = [
            ['type' => 'done'],
        ];

        iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'Add my Nationwide cash ISA at £5000',
        ));

        expect($this->invoker->calls[0]['persona'])->toBe('advice');
    });

    it('falls through to advice for advice-shaped data-entry ("add my ISA, thoughts?")', function () {
        config(['fyn.classifier_fast_path_enabled' => true]);

        $this->invoker->queuedEvents = [['type' => 'done']];

        iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'Add my ISA £5k, thoughts?',
        ));

        expect($this->invoker->calls[0]['persona'])->toBe('advice');
    });

    it('transitions to capturing on delegate_to_capture handoff', function () {
        $this->invoker->nextHandoff = [
            'handoff_type' => HandoffContract::DELEGATE_TO_CAPTURE,
            'payload' => [
                'reason' => 'retirement advice blocked on missing pension data',
                'entity_types' => ['dc_pension'],
                'fields_needed' => ['scheme_name', 'current_fund_value'],
            ],
        ];
        $this->invoker->queuedEvents = [
            ['type' => 'content', 'text' => 'Let me note your pension details first.'],
            ['type' => 'done'],
        ];

        iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'What should I do about my pensions?',
        ));

        $state = $this->conversation->fresh()->persona_state;
        expect($state['current'])->toBe('capturing')
            ->and($state['pending_advice_question'])->toBe('What should I do about my pensions?')
            ->and($state['capture_context']['entity_types'])->toBe(['dc_pension']);
    });
});

describe('capturing state', function () {
    it('invokes data_capture when state is capturing', function () {
        $this->conversation->update([
            'persona_state' => [
                'current' => 'capturing',
                'pending_advice_question' => 'What should I do?',
                'capture_context' => [
                    'reason' => 'test',
                    'entity_types' => ['dc_pension'],
                ],
                'turns_in_capture' => 1,
            ],
        ]);

        $this->invoker->queuedEvents = [['type' => 'done']];

        iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'Scottish Widows SIPP £50k DC',
        ));

        expect($this->invoker->calls[0]['persona'])->toBe('data_capture');
    });

    it('resets to advice and re-invokes advice on capture_complete with pending question', function () {
        $this->conversation->update([
            'persona_state' => [
                'current' => 'capturing',
                'pending_advice_question' => 'What should I do?',
                'capture_context' => [
                    'reason' => 'test',
                    'entity_types' => ['dc_pension'],
                ],
                'turns_in_capture' => 1,
            ],
        ]);

        $this->invoker->nextHandoff = [
            'handoff_type' => HandoffContract::CAPTURE_COMPLETE,
            'payload' => [
                'summary' => 'Added Scottish Widows SIPP £50k',
                'records_created' => [['type' => 'dc_pension', 'id' => 42]],
            ],
        ];
        $this->invoker->queuedEvents = [['type' => 'done']];

        $events = iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'Scottish Widows SIPP £50k DC',
        ));

        // Two invocations: data_capture then advice re-invoke.
        expect($this->invoker->calls)->toHaveCount(2)
            ->and($this->invoker->calls[0]['persona'])->toBe('data_capture')
            ->and($this->invoker->calls[1]['persona'])->toBe('advice');

        // State reset.
        $state = $this->conversation->fresh()->persona_state;
        expect($state['current'])->toBe('advice');

        // capture_complete emitted.
        $types = array_column($events, 'type');
        expect($types)->toContain('capture_complete');
    });

    it('flips back to advice on cancel pattern', function () {
        $this->conversation->update([
            'persona_state' => [
                'current' => 'capturing',
                'pending_advice_question' => 'What should I do?',
                'capture_context' => ['reason' => 'test', 'entity_types' => ['dc_pension']],
                'turns_in_capture' => 1,
            ],
        ]);

        $this->invoker->queuedEvents = [['type' => 'done']];

        iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'never mind',
        ));

        $state = $this->conversation->fresh()->persona_state;
        expect($state['current'])->toBe('advice')
            ->and($state['pending_advice_question'])->toBeNull();

        // Invoked advice with cancel-aware suffix (NOT data_capture).
        expect($this->invoker->calls[0]['persona'])->toBe('advice');
    });

    it('force-flips to advice on capture timeout (6+ turns)', function () {
        $this->conversation->update([
            'persona_state' => [
                'current' => 'capturing',
                'pending_advice_question' => 'What should I do?',
                'capture_context' => ['reason' => 'test', 'entity_types' => ['dc_pension']],
                'turns_in_capture' => 6,  // hits the default FYN_CAPTURE_MAX_TURNS=6
            ],
        ]);

        $events = iterator_to_array($this->orchestrator->dispatch(
            $this->user,
            $this->conversation,
            'yet another unrelated reply',
        ));

        $state = $this->conversation->fresh()->persona_state;
        expect($state['current'])->toBe('advice');

        // No invoker call on timeout — just the fallback message.
        expect($this->invoker->calls)->toHaveCount(0);

        $types = array_column($events, 'type');
        expect($types)->toContain('persona_state_change')
            ->and($types)->toContain('content');
    });
});

describe('preview mode', function () {
    it('short-circuits delegate_to_capture from preview users', function () {
        $previewUser = User::factory()->create(['is_preview_user' => true]);
        $conversation = AiConversation::factory()->create(['user_id' => $previewUser->id]);

        $this->invoker->nextHandoff = [
            'handoff_type' => HandoffContract::DELEGATE_TO_CAPTURE,
            'payload' => ['reason' => 'test', 'entity_types' => ['dc_pension']],
        ];
        $this->invoker->queuedEvents = [['type' => 'done']];

        $events = iterator_to_array($this->orchestrator->dispatch(
            $previewUser,
            $conversation,
            'What should I do about my pensions?',
        ));

        // State does NOT flip to capturing.
        $state = $conversation->fresh()->persona_state;
        expect($state['current'])->toBe('advice');

        // Preview CTA emitted.
        $types = array_column($events, 'type');
        expect($types)->toContain('preview_cta');
    });
});
