<?php

declare(strict_types=1);

use App\Services\Onboarding\OnboardingChatDirector;

/**
 * The everyday ways a user says "none" at a capture step. csjones 2026-09-16:
 * "I don't have any other investments" (user 403) and "I don't have an ISA"
 * (user 399) both fell to "Sorry, I didn't catch that".
 */
function isCompletionDeclaration(string $message): bool
{
    $method = new ReflectionMethod(OnboardingChatDirector::class, 'isCompletionDeclaration');
    $method->setAccessible(true);

    return (bool) $method->invoke(null, $message);
}

it('recognises a declaration of none', function (string $message): void {
    expect(isCompletionDeclaration($message))->toBeTrue();
})->with([
    'No', 'none', "That's everything", 'Nothing', 'No more',
    "I don't have any other investments", "I don't have an ISA", "I don't have a pension", 'I have no investments',
    "I haven't got any", 'Not got one', 'Nothing else to add', 'No other pensions',
    // Laura, 2026-09-18: the bare-noun form.
    "I don't have investment", "I don't have investments", "I don't have investment accounts", 'I do not have any savings accounts', "I don't have a pension",
]);

it('does not mistake an answer for a declaration of none', function (string $message): void {
    expect(isCompletionDeclaration($message))->toBeFalse();
})->with([
    'A Halifax savings account with £4,000', 'Just mine', 'Nationwide, £15,000', 'Yes, add another', 'I have a workplace pension with Aviva',
    "I don't have the exact figure, about 20k", "I don't have it to hand but roughly £45,000",
]);
