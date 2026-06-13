<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Memory\Procedural\ProceduralContributionCollector;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->corpus = sys_get_temp_dir().'/proc-overlay-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $this->corpus,
        'fyn.memory.procedural_reload_interval' => 0, // re-stat every load() in-test
    ]);
    // The loader is a singleton; forget it so each test reads its own temp corpus.
    app()->forgetInstance(ProceduralCorpusLoader::class);
    app()->forgetInstance(FynContextAssembler::class);
    $this->user = User::factory()->create();
});

afterEach(fn () => File::deleteDirectory($this->corpus));

/** Write a procedure .md at {kind}/{module}/{file}.md. */
function writeOverlayProc(string $root, string $kind, string $module, string $file, array $frontmatter, string $body): void
{
    $dir = "{$root}/{$kind}/{$module}";
    @mkdir($dir, 0777, true);
    $fm = '';
    foreach ($frontmatter as $k => $v) {
        $fm .= $k.': '.(is_bool($v) ? ($v ? 'true' : 'false') : $v)."\n";
    }
    file_put_contents("{$dir}/{$file}.md", "---\n{$fm}---\n\n{$body}\n");
}

function overlayFm(string $procedureId, string $kind, string $module, array $overrides = []): array
{
    return array_merge([
        'procedure_id' => $procedureId,
        'kind' => $kind,
        'module' => $module,
        'version' => 1,
        'active' => true,
        'effective_from' => '2026-01-01',
    ], $overrides);
}

function adviceTurn(User $user, string $primary): FynTurnContext
{
    return FynTurnContext::make(
        user: $user,
        message: 'hello',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => $primary],
    );
}

it('emits an <overlay> block for a procedure matching the turn module', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'retirement', 'tone',
        overlayFm('retirement.overlay.tone', 'system_prompt_overlay', 'retirement'),
        'Be especially careful about defined benefit transfers.',
    );

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->toContain('<overlay>')
        ->and($out)->toContain('Be especially careful about defined benefit transfers')
        ->and($out)->toContain('</overlay>');
});

it('emits an <fca_block> block for a matching procedure', function (): void {
    writeOverlayProc(
        $this->corpus, 'fca_block', 'retirement', 'dbtransfer',
        overlayFm('retirement.fca.dbtransfer', 'fca_block', 'retirement'),
        'A defined benefit transfer almost always requires regulated advice.',
    );

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->toContain('<fca_block>')
        ->and($out)->toContain('almost always requires regulated advice')
        ->and($out)->toContain('</fca_block>');
});

it('is module-scoped — a different-module overlay is NOT injected', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'estate', 'iht',
        overlayFm('estate.overlay.iht', 'system_prompt_overlay', 'estate'),
        'Estate-only overlay text.',
    );

    // Turn module is retirement, not estate.
    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->not->toContain('<overlay>')
        ->and($out)->not->toContain('Estate-only overlay text');
});

it('injects a general (wildcard) overlay on any turn module', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'general', 'house',
        overlayFm('general.overlay.house', 'system_prompt_overlay', 'general'),
        'House-view tone that applies everywhere.',
    );

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->toContain('<overlay>')
        ->and($out)->toContain('House-view tone that applies everywhere');
});

it('selects an onboarding overlay by the onboarding focus module', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'savings', 'capture',
        overlayFm('savings.overlay.capture', 'system_prompt_overlay', 'savings'),
        'Savings capture overlay text.',
    );

    $ctx = FynTurnContext::make(
        user: $this->user,
        message: 'I have a savings account',
        currentRoute: null,
        mode: 'onboarding',
        onboardingFocus: 'savings',
        isPreview: false,
        classification: null,
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<overlay>')
        ->and($out)->toContain('Savings capture overlay text');
});

it('omits <overlay> entirely when nothing matches (no empty tag)', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'estate', 'iht',
        overlayFm('estate.overlay.iht', 'system_prompt_overlay', 'estate'),
        'Estate-only.',
    );

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->not->toContain('<overlay>')
        ->and($out)->not->toContain('<overlay></overlay>');
});

it('does not inject an inactive overlay version', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'retirement', 'old',
        overlayFm('retirement.overlay.tone', 'system_prompt_overlay', 'retirement', ['version' => 1, 'active' => false]),
        'Old inactive overlay text.',
    );

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->not->toContain('<overlay>')
        ->and($out)->not->toContain('Old inactive overlay text');
});

it('does not inject an overlay whose effective_from is in the future', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'retirement', 'future',
        overlayFm('retirement.overlay.future', 'system_prompt_overlay', 'retirement', ['effective_from' => '2099-01-01']),
        'Not yet effective overlay text.',
    );

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->not->toContain('Not yet effective overlay text');
});

it('degrades to no overlay/fca block when the corpus is malformed (turn still builds)', function (): void {
    // A file with no frontmatter makes loadStrict() throw; load() degrades to
    // empty/last-good. With a cold loader + zero interval, the cold-boot invalid
    // corpus yields an empty corpus — no block, turn still builds.
    @mkdir("{$this->corpus}/fca_block/retirement", 0777, true);
    file_put_contents("{$this->corpus}/fca_block/retirement/broken.md", "no frontmatter at all\n");

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect($out)->not->toContain('<fca_block>')
        ->and($out)->not->toContain('<overlay>')
        ->and($out)->toContain('<context>'); // the rest of the prompt still built
});

it('never injects the shipped inactive A1/A2 overlays — build() carries none of their text', function (): void {
    // Point at the REAL shipped corpus (not the temp dir from beforeEach): the
    // A1/A2 overlays are authored active:false, so a representative turn must
    // not carry their text — the "never injected" half at the consumption layer.
    config(['fyn.memory.procedural_path' => base_path('fyn-memory/procedural')]);

    $out = app(FynContextAssembler::class)->build(adviceTurn($this->user, 'general'));

    expect($out)->toContain('<context>')
        ->and($out)->not->toContain('Answer the user first')
        ->and($out)->not->toContain('Acknowledgement hygiene');
});

it('records the injected procedure_id@version into the contribution collector', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'retirement', 'tone',
        overlayFm('retirement.overlay.tone', 'system_prompt_overlay', 'retirement', ['version' => 3]),
        'Overlay body.',
    );
    writeOverlayProc(
        $this->corpus, 'fca_block', 'general', 'hedge',
        overlayFm('general.fca.hedge', 'fca_block', 'general', ['version' => 2]),
        'Always hedge advice.',
    );

    app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    $recorded = app(ProceduralContributionCollector::class)->all();

    expect($recorded)->toContain([
        'procedure_id' => 'retirement.overlay.tone',
        'kind' => 'system_prompt_overlay',
        'module' => 'retirement',
        'version' => 3,
    ])->and($recorded)->toContain([
        'procedure_id' => 'general.fca.hedge',
        'kind' => 'fca_block',
        'module' => 'general',
        'version' => 2,
    ]);
});

it('leaves the contribution collector empty when nothing matched', function (): void {
    writeOverlayProc(
        $this->corpus, 'system_prompt_overlay', 'estate', 'iht',
        overlayFm('estate.overlay.iht', 'system_prompt_overlay', 'estate'),
        'Estate-only.',
    );

    app(FynContextAssembler::class)->build(adviceTurn($this->user, 'retirement'));

    expect(app(ProceduralContributionCollector::class)->all())->toBe([]);
});
