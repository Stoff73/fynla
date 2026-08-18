<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\Onboarding\OnboardingStateMachine;
use App\ValueObjects\CaptureContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Every onboarding turn that asks the user for something must be able to record
 * the answer. When it cannot, the model has no tool that fits and its only exits
 * are the prompt-injection refusal — which the director renders as "Sorry, I
 * didn't catch that", on every retry, forever — or silently dropping the answer,
 * because the capture block tells it to ignore anything outside its tool list.
 *
 * That has now shipped three times: 'pensioncheck', then 'budgeting' (live user
 * 80, 2026-08-18), and quietly in the estate and goals catalogues. This walks
 * all four capture mechanisms so the fourth cannot.
 */
$transitionTable = static function (): array {
    return (new ReflectionMethod(OnboardingStateMachine::class, 'transitionTable'))->invoke(null);
};

it('gives every grouped_extract state an extraction tool both providers can offer', function () use ($transitionTable): void {
    $definitions = app(AiToolDefinitions::class);

    $catalogue = [];
    foreach (['anthropic', 'xai'] as $provider) {
        $catalogue[$provider] = collect($definitions->onboardingExtractionTools(provider: $provider))
            ->map(fn (array $t): ?string => $t['name'] ?? ($t['function']['name'] ?? null))
            ->filter()
            ->all();
    }

    $missing = [];
    foreach ($transitionTable() as $id => $state) {
        if (($state['turn_type'] ?? null) !== 'grouped_extract') {
            continue;
        }

        $tool = (string) ($state['extraction_tool'] ?? '');
        foreach ($catalogue as $provider => $names) {
            if ($tool === '' || ! in_array($tool, $names, true)) {
                $missing[] = "{$id} → ".($tool === '' ? '(none)' : $tool)." [{$provider}]";
            }
        }
    }

    // The director hard-errors with "Onboarding is temporarily unavailable" when
    // the filter finds nothing, so a missing tool is a dead state, not a retry.
    expect($missing)->toBe([]);
});

/** Delegated campaign state → the write tool it exists to call, and its focus. */
const DELEGATED_STATE_TOOLS = [
    'campaign_isa_holdings' => ['savetax', 'create_savings_account'],
    'campaign_bank_accounts' => ['savetax', 'create_savings_account'],
    'campaign_investment_accounts' => ['savetax', 'create_investment_account'],
    'campaign_occupational_scheme' => ['savetax', 'capture_salary_sacrifice'],
    'campaign_pension_contribs' => ['savetax', 'create_pension'],
    'campaign2_pension_pots' => ['pensioncheck', 'update_record'],
    'campaign2_pension_db' => ['pensioncheck', 'create_pension'],
    'campaign2_flexible_access' => ['pensioncheck', 'update_record'],
    'campaign2_spouse_pensions' => ['pensioncheck', 'create_pension'],
];

it('arms every delegated campaign state with the tool its question needs', function () use ($transitionTable): void {
    $table = $transitionTable();

    $missing = [];
    foreach (DELEGATED_STATE_TOOLS as $state => [$focus, $tool]) {
        expect($table)->toHaveKey($state);

        if (! in_array($tool, OnboardingPromptBuilder::toolsForFocus($focus), true)) {
            $missing[] = "{$state} → {$tool} (focus {$focus})";
        }
    }

    expect($missing)->toBe([]);
});

it('lists every delegated state in the table above, so a new one cannot slip through', function () use ($transitionTable): void {
    $delegated = [];
    foreach ($transitionTable() as $id => $state) {
        if (($state['turn_type'] ?? null) === 'delegated') {
            $delegated[] = $id;
        }
    }

    // asset_capture is covered by CaptureFocusToolCoverageTest (one per focus);
    // campaign_verify_edit carries its own explicit tool list per section.
    $accountedFor = array_merge(
        array_keys(DELEGATED_STATE_TOOLS),
        ['asset_capture', 'campaign_verify_edit'],
    );

    expect(array_diff($delegated, $accountedFor))->toBe([]);
});

it('gives the advice-side capture handoff every write tool the onboarding walk can call', function (): void {
    $inline = (new ReflectionMethod(OnboardingChatDirector::class, 'captureToolSet'))
        ->invoke(app(OnboardingChatDirector::class), new CaptureContext('advice handoff', ['savings_account']));

    // The write intents an advice-mode user can express that the walk also
    // owns. set_expenditure is here because "I spend £5,000 a month" must land
    // somewhere from advice mode too, not only inside the budgeting focus.
    foreach (['set_expenditure', 'create_life_event', 'create_business_interest', 'create_chattel'] as $tool) {
        expect($inline)->toContain($tool);
    }
});
