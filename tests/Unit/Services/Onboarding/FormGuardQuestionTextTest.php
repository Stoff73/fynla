<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;

/*
 * The duplicate guard asks; it never refuses (CSJ 2026-10-08: "ask, never
 * refuse"). After a form save its question reached the user as "I couldn't
 * save Easy access savings: You already have …, or the same one?" (csjones,
 * Jamie, 2026-10-08), which reads as a failure. The question is asked as the
 * guard wrote it; only a real failure says "I couldn't save".
 */
function formErrorTextFor(array $errors, array $created = []): string
{
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'formErrorText');
    $method->setAccessible(true);

    return $method->invoke($director, 'savings', $errors, $created);
}

it('asks the duplicate question without calling it a failure', function () {
    $question = 'You already have a savings account recorded as "Santander easy access savings", with exactly these details. Is this a separate savings account you also hold, or the same one?';

    $text = formErrorTextFor(['easy_access' => ['message' => $question, 'error_type' => 'confirm_duplicate_required']]);

    expect($text)->toBe($question)
        ->and($text)->not->toContain("couldn't save");
});

it('asks the edit question without calling it a failure', function () {
    $question = 'You already have a savings account recorded as "Santander", with different details. Is this the same savings account you\'d like me to update, or a separate one?';

    expect(formErrorTextFor(['easy_access' => ['message' => $question, 'error_type' => 'confirm_edit_required']]))->toBe($question);
});

it('still says a real failure did not save', function () {
    $text = formErrorTextFor(['easy_access' => ['message' => 'The balance must be a number.', 'error_type' => 'validation_failed']]);

    expect($text)->toStartWith("I couldn't save ");
});

it('says a shared household spending figure is the user\'s half', function () {
    // Jamie (csjones 503, 2026-10-08) typed £3,800 for the household and was
    // told "I've noted monthly spending of £1,900", with no word that the
    // account holds its half (SharedExpenditure joint mode).
    $alex = User::factory()->create(['marital_status' => 'married']);
    $jamie = User::factory()->create([
        'marital_status' => 'married', 'spouse_id' => $alex->id,
        'onboarding_fyn_selection' => 'savetax', 'expenditure_sharing_mode' => 'joint',
        'monthly_expenditure' => 1900,
    ]);
    $alex->update(['spouse_id' => $jamie->id]);

    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'expenditureAck');
    $method->setAccessible(true);

    expect($method->invoke($director, $jamie->fresh()))
        ->toContain('monthly spending of £1,900, your half of the £3,800 your household spends');
});

it('saves the guard question outside the walk as a capture still asking about its record', function () {
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'emitFormProblem');
    $method->setAccessible(true);
    $errors = ['easy_access' => ['message' => 'You already have … Is this a separate savings account you also hold, or the same one?', 'error_type' => 'confirm_duplicate_required', 'entity_id' => 893, 'fields' => []]];

    $run = function (?string $stateId) use ($method, $director, $conversation, $errors) {
        $gen = $method->invoke($director, $conversation, 'savings', $errors, 'question', $stateId);
        foreach ($gen as $_) {
        }

        return $gen->getReturn();
    };

    $outside = $run(null);
    expect($outside->persona)->toBe('data_capture')
        ->and($outside->metadata['capture_record_id'])->toBe(893)
        ->and($outside->metadata['capture_write_landed'])->toBeFalse();

    // Inside the walk the step holds the question; the row is unchanged.
    $inside = $run('campaign_savings');
    expect($inside->persona)->toBeNull()
        ->and($inside->metadata)->not->toHaveKey('capture_record_id');
});

it('asks the duplicate question with its two answers as bubbles, and a failure as plain text', function () {
    // Regression walk 2026-10-09, R19 (CSJ: "why no bubble on the duplicate
    // message?"): the two-way question came with no bubbles, so the answer had
    // to be typed.
    $user = User::factory()->create();
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);
    $method = new ReflectionMethod($director, 'emitFormProblem');
    $method->setAccessible(true);

    $run = function (array $errors) use ($method, $director, $conversation): array {
        $gen = $method->invoke($director, $conversation, 'savings', $errors, 'the line', 'base_savings');
        $events = [];
        foreach ($gen as $event) {
            $events[] = $event;
        }

        return [$events, $gen->getReturn()];
    };

    [$events, $saved] = $run(['easy_access' => ['message' => 'You already have … or the same one?', 'error_type' => 'confirm_duplicate_required', 'entity_id' => 893, 'fields' => []]]);
    $replies = collect($events)->firstWhere('type', 'quick_replies');

    expect($replies['prompt_text'])->toBe('the line')
        ->and(collect($replies['bubbles'])->pluck('label')->all())->toBe(['The same one', 'A separate one'])
        ->and(collect($events)->where('type', 'content')->all())->toBe([])
        ->and($saved->metadata['bubbles'])->toBe($replies['bubbles']);

    [$events, $saved] = $run(['easy_access' => ['message' => 'The balance must be a number.', 'error_type' => 'validation_failed', 'fields' => []]]);

    expect(collect($events)->firstWhere('type', 'content')['text'])->toBe('the line')
        ->and(collect($events)->firstWhere('type', 'quick_replies'))->toBeNull()
        ->and($saved->metadata['bubbles'] ?? null)->toBeNull();
});
