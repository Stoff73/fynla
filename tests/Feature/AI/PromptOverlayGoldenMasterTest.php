<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

/**
 * Phase 4c hard gate. The fixtures in tests/fixtures/PromptOverlay are the
 * byte-for-byte FynContextAssembler::build() output BEFORE the overlay/fca_block
 * consumption mechanism is added, captured with the empty 4c corpus. After the
 * mechanism lands the assembler must reproduce them exactly — proving the new
 * <overlay> / <fca_block> layers are purely additive and a no-op while the
 * corpus is empty (zero prefix-cache / context regression).
 *
 * The static FynSystemPrompt::text() prefix is prepended by the caller, not by
 * build(), so it is out of scope here and is locked separately by
 * FynSystemPromptTest (prefix-cache byte-invariance).
 */
$fixtureDir = __DIR__.'/../../fixtures/PromptOverlay';

/**
 * Build a deterministic turn for a variant. A freshly-factoried user with no
 * financial data + the seeded TaxConfiguration makes build() deterministic:
 * tax year, profile, module context and bucket membership are all fixed for a
 * data-free user.
 */
$buildVariant = function (string $name): string {
    $user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'User',
    ]);

    $ctx = match ($name) {
        'advice_dashboard' => FynTurnContext::make(
            user: $user,
            message: 'How am I doing overall?',
            currentRoute: '/dashboard',
            mode: 'advice',
            onboardingFocus: null,
            isPreview: false,
            classification: ['primary' => 'general'],
        ),
        'advice_retirement_position' => FynTurnContext::make(
            user: $user,
            message: 'Am I on track for retirement?',
            currentRoute: '/net-worth/retirement',
            mode: 'advice',
            onboardingFocus: null,
            isPreview: false,
            classification: ['primary' => 'retirement'],
        ),
        'onboarding_savings' => FynTurnContext::make(
            user: $user,
            message: 'I have a savings account.',
            currentRoute: null,
            mode: 'onboarding',
            onboardingFocus: 'savings',
            isPreview: false,
            classification: null,
        ),
        'onboarding_protection' => FynTurnContext::make(
            user: $user,
            message: 'I have life insurance.',
            currentRoute: null,
            mode: 'onboarding',
            onboardingFocus: 'protection',
            isPreview: false,
            classification: null,
        ),
        default => throw new InvalidArgumentException("Unknown variant {$name}"),
    };

    // No $orchestrateAnalysis closure: the POSITION bucket's buildFinancialContext
    // deterministically emits its "analysis service not provided" sentinel for a
    // data-free user, which is identical pre/post-mechanism. We are proving the
    // overlay layer is additive, not exercising the analysis service.
    return app(FynContextAssembler::class)->build($ctx);
};

$variants = ['advice_dashboard', 'advice_retirement_position', 'onboarding_savings', 'onboarding_protection'];

it('captures the current build() output into fixtures', function () use ($fixtureDir, $buildVariant, $variants): void {
    if (getenv('CAPTURE_PROMPT_OVERLAY_GOLDEN') !== '1') {
        $this->markTestSkipped('Capture only runs with CAPTURE_PROMPT_OVERLAY_GOLDEN=1.');
    }

    if (! is_dir($fixtureDir)) {
        mkdir($fixtureDir, 0777, true);
    }

    foreach ($variants as $name) {
        // Capture twice and require stability before committing — guards against
        // any latent non-determinism in build() for a fixed data-free user.
        $first = $buildVariant($name);
        $second = $buildVariant($name);
        expect($second)->toBe($first, "build() is non-deterministic for variant {$name} — fix before capture.");

        // JSON wrapping is only a stable on-disk container; the output string is
        // stored verbatim (as 4b's ToolSchema golden master does).
        file_put_contents(
            $fixtureDir.'/'.$name.'.json',
            json_encode(['output' => $first], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        );
    }

    expect(glob($fixtureDir.'/*.json'))->toHaveCount(4);
});

it('build() is byte-identical to the committed fixture for each variant', function (string $name) use ($fixtureDir, $buildVariant): void {
    $fixturePath = $fixtureDir.'/'.$name.'.json';
    expect(file_exists($fixturePath))->toBeTrue("Missing fixture {$name}.json — run the capture step first.");

    $expected = json_decode(file_get_contents($fixturePath), true)['output'];

    expect($buildVariant($name))->toBe($expected);
})->with([
    'advice_dashboard',
    'advice_retirement_position',
    'onboarding_savings',
    'onboarding_protection',
]);
