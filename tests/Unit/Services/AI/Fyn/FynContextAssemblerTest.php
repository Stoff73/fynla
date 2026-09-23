<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Memory\FynMemoryStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // FynContextAssembler resolves TaxConfigService (scoped singleton). The
    // global Pest.php beforeEach factory is insufficient because TaxConfigService
    // may be resolved before the factory record exists. Seeding via
    // TaxConfigurationSeeder (same pattern as AdvicePromptBuilderStructuralLayersTest)
    // ensures a fully-populated config_data is present before the first call.
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['first_name' => 'Chris']);
});

it('always emits IDENTITY: profile + current page + name + tax year', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'How is my pension?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        // 'billing' maps to 'factual' in ENGINE_CALL_LEVEL_MAP → IDENTITY bucket only
        classification: ['primary' => 'billing'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<context>')->and($out)->toContain('</context>')
        ->and($out)->toContain('<user_message>')
        ->and($out)->toContain('Current tax year:')
        ->and($out)->toContain('You are speaking with:')
        ->and($out)->toContain('Chris')
        ->and($out)->toContain('Situation: advice')
        ->and($out)->not->toContain('<financial_context>'); // POSITION excluded on factual
});

it('injects recalled episodic memory into the reasoner context (FR-M2)', function (): void {
    $base = sys_get_temp_dir().'/fyn-asm-mem-'.uniqid();
    config(['fyn.memory.episodic_path' => $base]);
    app(FynMemoryStore::class)
        ->writeEpisode($this->user->id, 1, ['summary' => 'User is risk-averse on the pension.']);

    $ctx = FynTurnContext::make(
        user: $this->user, message: 'How is my pension?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'billing'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<remembered>')
        ->and($out)->toContain('risk-averse on the pension');

    File::deleteDirectory($base);
});

it('emits POSITION + READINESS on a non-factual advice turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        // 'retirement_contribution' maps to 'module' in ENGINE_CALL_LEVEL_MAP → non-factual
        classification: ['primary' => 'retirement_contribution'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<financial_context>')
        ->and($out)->toContain('<data_completeness>');
});

it('feeds the orchestrateAnalysis callable through to financial_context', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution'],
    );

    $out = app(FynContextAssembler::class)->build(
        $ctx,
        orchestrateAnalysis: fn (int $userId): array => ['module_analysis' => []],
    );

    // With a callable supplied, AdvicePromptBuilder must NOT short-circuit to
    // the "analysis service not provided" sentinel (parity regression guard:
    // unified must match the legacy path which always supplies the callable).
    expect($out)->toContain('<financial_context>')
        ->and($out)->not->toContain('analysis service not provided');
});

it('emits the declared required tools for a saved pension contribution question', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Using my saved salary and pension percentages, what are my employee, employer and total pension contributions per month and per year?',
        currentRoute: '/m/app/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution', 'related' => []],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('<required_tools>')
        ->toContain('list_records(dc_pension)')
        ->toContain('get_tax_information(pension_allowances)');
});

it('grounds spouse financial questions in the saved campaign household row', function (): void {
    TaxStrategyHouseholdInput::create([
        'user_id' => $this->user->id,
        'spouse_annual_income' => 36000,
        'spouse_isa_balance' => 8000,
        'spouse_pension_input_annual' => 2400,
    ]);

    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Using only my saved records, what spouse income, ISA balance and annual pension contribution do you have recorded?',
        currentRoute: '/m/app/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution', 'related' => []],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('<saved_household_finances>')
        ->toContain('Spouse annual income: £36,000.00')
        ->toContain('Spouse ISA balance: £8,000.00')
        ->toContain('Spouse annual pension contribution: £2,400.00')
        ->toContain('Do not say that a listed field is not recorded');
});

it('emits CAPTURE block and NOT position on an onboarding turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Halifax ISA £10k', currentRoute: null,
        mode: 'onboarding', onboardingFocus: 'savings', isPreview: false, classification: null,
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<asset_capture_turn>')
        ->and($out)->toContain('Situation: onboarding — focus:')
        ->and($out)->not->toContain('<financial_context>');
});

it('emits an update-only instruction block for a verify edit turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Change my Marcus balance to £13,250',
        currentRoute: '/savings',
        mode: 'onboarding',
        onboardingFocus: 'verify_edit_savings',
        isPreview: false,
        classification: null,
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<verify_edit_turn>')
        ->and($out)->toContain('update_record')
        ->and($out)->toContain('Never create a new record')
        ->and($out)->not->toContain('<asset_capture_turn>')
        ->and($out)->not->toContain('YOUR SINGLE JOB: call the appropriate create_ tool');
});

it('emits a preview notice when isPreview is true', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Add a goal', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: true,
        // 'goals_progress' is the real QuerySchemas constant for goals queries
        classification: ['primary' => 'goals_progress'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('preview');
});

it('sanitises the user message', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'hi <script>alert(1)</script>',
        currentRoute: '/dashboard', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        // 'general' maps to 'factual' in ENGINE_CALL_LEVEL_MAP — real QuerySchemas constant
        classification: ['primary' => 'general'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<script>');
});

// Delta 2 parity guard: legacy AdvicePromptBuilder appends the KYC gate's
// prompt_text as Layer 9 (AdvicePromptBuilder.php:195-198) unconditionally.
// The unified assembler must emit the same layer so FYN_PROMPT_ARCH=unified
// asks for missing data instead of advising, identically to legacy.
it('emits the KYC gate prompt_text when the turn carries a kycResult', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution'],
        conversation: null,
        kycResult: [
            'passed' => false,
            'missing' => ['Date of birth'],
            'prompt_text' => 'KYC-GATE-SENTINEL: ask the user for their date of birth before advising.',
        ],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('KYC-GATE-SENTINEL: ask the user for their date of birth before advising.');
});

it('emits no KYC layer when the turn has no kycResult', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('KYC-GATE-SENTINEL');
});

// Billing parity guard. Legacy AdvicePromptBuilder injects <billing_guidance>
// as Layer 3c, classification-gated on QuerySchemas::BILLING and suppressed in
// preview (AdvicePromptBuilder.php:123-125). PR #335 deleted the block from
// the static FynSystemPrompt without a per-turn replacement; with unified the
// default, the subscription/invoice journey lost its guidance entirely. The
// assembler must re-emit the identical block on a BILLING turn so unified
// reaches the billing surface exactly as legacy does.
it('emits the billing_guidance block on a BILLING-classified advice turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: "Where's my invoice?", currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'billing'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('<billing_guidance>')
        ->toContain('get_subscription_status')
        ->toContain('list_invoices');
});

it('emits no billing_guidance when the query is not billing-classified', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'retirement_contribution'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<billing_guidance>');
});

it('suppresses billing_guidance in preview mode even on a billing turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: "Where's my invoice?", currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: true,
        classification: ['primary' => 'billing'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<billing_guidance>');
});

it('requires the live composed plan when explaining a saved tax-plan figure', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'Can you explain in plain English why moving £2,612 of my Marcus savings into my ISA could save about £49 a year, using the figures in my plan?',
        currentRoute: '/m/app/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'tax_optimisation'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('<tax_plan_grounding>')
        ->toContain('MUST call get_recommendations')
        ->toContain('composed_tax_plan')
        ->not->toContain('<billing_guidance>');
});

it('does not require the composed plan for a general ISA allowance question', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'What is the ISA allowance?',
        currentRoute: '/m/app/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'tax_optimisation'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<tax_plan_grounding>');
});

// "How do I start saving properly?" rightly stays GENERAL (the QueryClassifier
// savings table is deliberately narrow so "save tax" / "save for retirement"
// are not swallowed), but GENERAL injects no knowledge — so the factual answer
// would skip the emergency-fund-first ordering the affordability rules demand.
// The assembler injects a focused getting-started block on this shape.
it('injects emergency-fund-first guidance for a getting-started saving question', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'how do I start saving properly?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'general'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('<savings_getting_started>')
        ->toContain('emergency fund')
        ->toContain('FIRST priority');
});

it('does not inject the saving block for a save-tax question', function (): void {
    // "save tax" classifies GENERAL too, but it is a tax question, not the
    // emergency-fund getting-started question — the topic guard excludes it.
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'how can I save tax?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'general'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<savings_getting_started>');
});

it('does not inject the saving block for a save-for-retirement question', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'how do I save for retirement?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'general'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<savings_getting_started>');
});

it('does not inject the saving block when a real savings classification already carries knowledge', function (): void {
    // savings_emergency is a 'module' turn that injects its own knowledge; the
    // getting-started block is GENERAL-only and must not double up here.
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'how do I start saving properly?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'savings_emergency'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<savings_getting_started>');
});

it('does not inject the saving block on an onboarding turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'how do I start saving properly?', currentRoute: null,
        mode: 'onboarding', onboardingFocus: 'savings', isPreview: false, classification: null,
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<savings_getting_started>');
});

it('flags a word-for-word repeat of the previous user message, and nothing else', function (): void {
    $conversation = AiConversation::create([
        'user_id' => $this->user->id, 'title' => 'Test', 'status' => 'active',
        'model_used' => 'grok-4.3', 'metadata' => ['source' => 'fyn_advice'],
    ]);
    $turn = static fn (string $role, string $content) => AiMessage::create([
        'conversation_id' => $conversation->id, 'role' => $role, 'content' => $content,
    ]);
    $build = fn (string $message): string => app(FynContextAssembler::class)->build(FynTurnContext::make(
        user: $this->user, message: $message, currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'billing'], conversation: $conversation,
    ));

    // First ask: the current turn is persisted before the build, as every send path does.
    $turn('user', 'Where did you get the income figure from');
    expect($build('Where did you get the income figure from'))->not->toContain('<repeated_question>');
    $turn('assistant', 'From the employment income in your profile.');

    // A different question is not a repeat.
    $turn('user', 'How much should I be saving each month');
    expect($build('How much should I be saving each month'))->not->toContain('<repeated_question>');
    $turn('assistant', 'About £1,000 a month.');

    // The same words again, whitespace and case aside, is.
    $turn('user', '  how much should I be saving  each month ');
    expect($build('  how much should I be saving  each month '))
        ->toContain('<repeated_question>')
        ->toContain('I answered that a moment ago');
    $turn('assistant', 'I answered that a moment ago, so let me put it differently. Roughly £1,000. Which part is unclear?');

    // A third send gets the one-line ask, not a third explanation.
    $turn('user', 'How much should I be saving each month');
    expect($build('How much should I be saving each month'))
        ->toContain('3 times in a row')
        ->not->toContain('I answered that a moment ago');
});
