<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingStateMachine;

/**
 * The savings campaign section spans bank accounts AND ISAs (a Cash ISA is cash
 * held in an ISA wrapper). Fyn's verify prompts must label the section by what
 * the user actually holds — it must never call an ISA a "bank account" (CSJ).
 */
it('labels the savings verify section by what the user holds, never calling an ISA a bank account', function (array $assets, string $expected, string $notExpected) {
    $user = User::factory()->create([
        'onboarding_fyn_path' => 'campaign',
        'funnel_answers' => ['assets' => $assets],
        'onboarding_fyn_context' => ['verify_section' => 'savings'],
    ]);

    $prompt = OnboardingStateMachine::verifyPromptMore('', $user);

    expect($prompt)->toContain($expected);
    if ($notExpected !== '') {
        expect($prompt)->not->toContain($notExpected);
    }
})->with([
    'ISA only → ISA accounts' => [['isa'], 'your ISA accounts?', 'bank accounts'],
    'bank only → bank accounts' => [['bank'], 'your bank accounts?', ''],
    'savings only → bank accounts' => [['savings'], 'your bank accounts?', ''],
    'both → savings and ISA accounts' => [['isa', 'bank'], 'your savings and ISA accounts?', ''],
]);
