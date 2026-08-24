<?php

declare(strict_types=1);

use App\Services\AI\AdviceFyn;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;
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

it('gives the advice-side capture handoff every write tool advice mode strips', function (): void {
    $inline = (new ReflectionMethod(OnboardingChatDirector::class, 'captureToolSet'))
        ->invoke(app(OnboardingChatDirector::class), new CaptureContext('advice handoff', ['savings_account']));

    // Advice Fyn is read-only: it strips every WRITE_TOOLS surface and the only
    // way a write reaches the database is the delegate_to_capture handoff. So a
    // tool stripped there but absent here is a request the user can make and
    // nothing in the app can record — advice will not write it, and the Fyn it
    // hands to has no tool for it. navigate_to_page is stripped from advice as
    // an escape hatch (S0.5.t), not because it writes, so it is the one
    // exclusion.
    $stripped = array_diff(AdviceFyn::WRITE_TOOLS, ['navigate_to_page']);

    expect(array_values(array_diff($stripped, $inline)))->toBe([]);
});

it('can actually offer every handoff tool to both providers', function (): void {
    $inline = (new ReflectionMethod(OnboardingChatDirector::class, 'captureToolSet'))
        ->invoke(app(OnboardingChatDirector::class), new CaptureContext('advice handoff', ['spouse']));

    // Naming a tool in the allowlist is not the same as being able to offer it.
    // HasAiChat narrows getTools() by name, and the grouped-extract capture_*
    // schemas are deliberately kept out of getTools() for the token budget, so
    // before 2026-08-19 four of them were allowed and never shown — the model
    // could not call what the turn had just promised.
    $definitions = app(AiToolDefinitions::class);

    foreach (['anthropic', 'xai'] as $provider) {
        $pool = array_merge(
            $provider === 'xai'
                ? app(XaiToolDefinitions::class)->getTools()
                : $definitions->getTools(),
            $definitions->onboardingExtractionTools($provider),
        );

        $offerable = [];
        foreach ($pool as $tool) {
            $offerable[] = $tool['name'] ?? ($tool['function']['name'] ?? null);
        }

        expect(array_values(array_diff($inline, $offerable)))->toBe([], "provider: {$provider}");
    }
});

it('routes a record type it has never seen to a focus that can still record it', function (): void {
    // entity_types on the delegate_to_capture schema is a free-text array, and
    // the module map covers assets only. Everything else — spouse, dependants,
    // personal details, work, expenditure, charitable giving, and anything the
    // model invents — used to fall to the savings focus, so the turn read "you
    // can use create_savings_account; anything else is not in scope for this
    // Cash & Savings turn" while carrying the full write catalogue underneath.
    // That is the budgeting dead end (user 80, conversation 67) reached through
    // the advice door.
    $infer = new ReflectionMethod(OnboardingChatDirector::class, 'inferFocusesFromEntityTypes');
    $director = app(OnboardingChatDirector::class);

    $unmapped = [
        'spouse', 'dependant', 'family_member', 'personal_details', 'work_details',
        'income', 'expenditure', 'budget', 'charitable_giving', 'state_pension',
        'retirement_goals', 'a_type_no_one_has_thought_of_yet',
    ];

    foreach ($unmapped as $entity) {
        // Read production's fallback, never a copy of it — a test that names
        // its own fallback passes whatever the app actually does.
        $focus = $infer->invoke($director, [$entity])[0]
            ?? OnboardingChatDirector::HANDOFF_FALLBACK_FOCUS;
        $advertised = OnboardingPromptBuilder::toolsForFocus($focus);

        // The prompt's tool list is what the model believes it may call. It has
        // to cover the whole write surface, or the turn advertises less than it
        // can dispatch and the model refuses inside its own capability.
        expect(array_values(array_diff(
            array_diff(AdviceFyn::WRITE_TOOLS, ['navigate_to_page']),
            $advertised,
        )))->toBe([], "entity type: {$entity}");
    }
});
