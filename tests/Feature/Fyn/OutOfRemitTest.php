<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\QueryClassifier;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['first_name' => 'Alex', 'surname' => 'Carter']);
    $this->conversation = AiConversation::create([
        'user_id' => $this->user->id,
        'title' => 'Out-of-remit test',
        'status' => 'active',
        'model_used' => 'test',
        'message_count' => 0,
    ]);
});

/**
 * S0.14 — INV-2.3.4.
 *
 * Out-of-remit messages produce the canonical refusal:
 *   "I'm able to help you with your finances. {context} is out of scope."
 * with `{context}` from the classifier's `detected_topic`. AdviceFyn emits
 * a single `content` event followed by `done`, with zero tool calls.
 */
function collectAdviceFynStream(AdviceFyn $fyn, User $user, AiConversation $conversation, string $message): array
{
    $events = [];
    foreach ($fyn->handle($user, $conversation, $message) as $event) {
        $events[] = $event;
    }

    return $events;
}

describe('AdviceFyn out-of-remit refusal (S0.14)', function () {
    it('emits the canonical refusal for medical queries', function () {
        $events = collectAdviceFynStream(
            app(AdviceFyn::class),
            $this->user,
            $this->conversation,
            'Should I take antibiotics for a persistent cough?',
        );

        expect($events)->toHaveCount(2)
            ->and($events[0]['type'])->toBe('content')
            ->and($events[0]['text'])->toBe("I'm able to help you with your finances. Medical advice is out of scope.")
            ->and($events[1]['type'])->toBe('done');
    });

    it('emits the canonical refusal for legal queries', function () {
        $events = collectAdviceFynStream(
            app(AdviceFyn::class),
            $this->user,
            $this->conversation,
            'My husband wants a divorce and is suing me for custody — what should I do?',
        );

        expect($events[0]['text'])->toBe("I'm able to help you with your finances. Legal advice is out of scope.");
    });

    it('emits the canonical refusal for emotional support queries', function () {
        $events = collectAdviceFynStream(
            app(AdviceFyn::class),
            $this->user,
            $this->conversation,
            "I've been really depressed lately and feel completely lonely.",
        );

        expect($events[0]['text'])->toBe("I'm able to help you with your finances. Emotional support is out of scope.");
    });

    it('emits the canonical refusal for general-knowledge queries', function () {
        $events = collectAdviceFynStream(
            app(AdviceFyn::class),
            $this->user,
            $this->conversation,
            'What is the capital of Argentina?',
        );

        expect($events[0]['text'])->toBe("I'm able to help you with your finances. General knowledge is out of scope.");
    });

    it('emits zero tool_use events on the out-of-remit path', function () {
        $events = collectAdviceFynStream(
            app(AdviceFyn::class),
            $this->user,
            $this->conversation,
            'Tell me a joke about pirates.',
        );

        $toolEvents = array_filter($events, fn ($e) => ($e['type'] ?? '') === 'tool_use');
        expect($toolEvents)->toBeEmpty();
    });

    it('persists user + assistant messages on the out-of-remit path', function () {
        collectAdviceFynStream(
            app(AdviceFyn::class),
            $this->user,
            $this->conversation,
            'Recipe for sourdough bread please.',
        );

        $messages = $this->conversation->messages()->orderBy('id')->get();
        expect($messages)->toHaveCount(2)
            ->and($messages[0]->role)->toBe('user')
            ->and($messages[0]->content)->toBe('Recipe for sourdough bread please.')
            ->and($messages[1]->role)->toBe('assistant')
            ->and($messages[1]->content)->toBe("I'm able to help you with your finances. General knowledge is out of scope.");
    });

    it('does NOT short-circuit financial questions that incidentally mention non-financial terms', function () {
        // Mentions "depressed" but the financial keyword "pension" wins.
        $classifier = app(QueryClassifier::class);
        $classification = $classifier->classify("I'm depressed about my pension pot — am I on track for retirement?");

        expect($classification['primary'])->not->toBe(QuerySchemas::OUT_OF_REMIT);
    });

    it('classifies an isolated medical message as out_of_remit with detected_topic', function () {
        $classifier = app(QueryClassifier::class);
        $classification = $classifier->classify('Should I take antibiotics for a cough?');

        expect($classification['primary'])->toBe(QuerySchemas::OUT_OF_REMIT)
            ->and($classification['detected_topic'])->toBe('Medical advice');
    });
});
