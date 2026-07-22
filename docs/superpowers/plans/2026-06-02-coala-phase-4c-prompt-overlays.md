# CoALA Phase 4c — Prompt-Overlay / FCA-Block Consumption Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the **consumption mechanism** for the two remaining prose-bearing procedural kinds — `system_prompt_overlay` and `fca_block` — so that, once content is authored, those per-turn prompt layers change via a PR-to-`.md` rather than a PHP edit. The mechanism mirrors the existing `<knowledge>` (semantic) and `<live_data>` (pointer) layers in `FynContextAssembler::build()`: load **active** procedures of those two kinds (via the bound `ProceduralCorpusLoader`), select those whose `module` matches the current turn (or the `general` wildcard), and inject them as **additive per-turn layers AFTER the static prefix** — wrapped as `<overlay>…</overlay>` and `<fca_block>…</fca_block>`. Ships with an **empty** overlay/fca_block corpus so it is provably a **no-op today**.

**Architecture:** Mirrors the `<knowledge>` / `<live_data>` layers (`FynContextAssembler.php:90-131`) and the `SemanticSnapshotHolder` request-scoped stamping pattern (`FynContextAssembler.php:99-101`). A request-scoped `ProceduralContributionCollector` (exact mirror of `FetchProvenanceCollector`) accumulates each contributed `procedure_id@version`; 4e will read it at persist time. No static content moves; `FynSystemPrompt::text()` is byte-frozen.

**Tech Stack:** PHP 8.2, Laravel 10, Pest. No new dependencies. Consumes the 4a substrate (`ProceduralCorpusLoader`, `ProceduralCorpus`, `Procedure`) unchanged.

**Spec:** `docs/superpowers/specs/2026-06-02-coala-phase-4c-prompt-overlays-design.md`

---

## Design decisions fixed by this plan (read before coding)

1. **Module key of a turn** (the selection key):
   - Onboarding turn (`$ctx->isOnboarding()`): `$ctx->onboardingFocus` (the module-ish slug, e.g. `savings`, `protection`).
   - Advice turn: `$ctx->classification['primary'] ?? null` (e.g. `general`, `retirement`).
   - No new route→slug mapping is invented. `moduleContextFor` returns prose, not a slug, so it is **not** reused as the key (the spec's intent — "the same resolution the assembler already uses for module context" — is honoured by reading the slug the selector already keys on: `classification['primary']` / `onboardingFocus`).
2. **Wildcard token = `general`.** A procedure whose `module` is `general` applies to **every** turn (module-agnostic). A procedure is **selected** for a turn iff `procedure.module === turnModule` **OR** `procedure.module === 'general'`. This token is asserted in Task 3's tests so it is fixed, not floating. (`general` is the canonical wildcard primary already used across the classification convention; the corpus README's `global` is the deploy-gate's accepted module string but 4c authors no content so neither is exercised in shipping state.)
3. **Active resolution.** For each distinct `procedure_id` returned by `ofKind($kind)`, resolve the single active version via `ProceduralCorpus::active($procedureId, Carbon::now())`. Skip `null` (no effective active version). This guarantees one body per procedure_id and respects effective-dating exactly like 4b.
4. **Block ordering & emptiness.** `<overlay>` then `<fca_block>`, both **after** the `<live_data>` block and **before** the `POSITION` bucket block. Omit a block entirely when its selection is empty (never `<overlay></overlay>`), matching the `<knowledge>` "omit when empty" rule.
5. **Determinism.** Within a block, bodies are emitted in the order `ofKind()` returns them (corpus file-walk order), de-duplicated by `procedure_id`. No sorting is introduced (the empty corpus makes this moot for the golden master; the temp-fixture tests assert presence, not inter-procedure order).
6. **Fixtures dir.** `tests/fixtures/PromptOverlay/` (lowercase `fixtures` — the real dir, same as `tests/fixtures/ToolSchema` resolved via `__DIR__` in `ToolSchemaGoldenMasterTest`).

---

## File Structure

**Create:**
- `app/Services/AI/Memory/Procedural/ProceduralContributionCollector.php` — request-scoped accumulator (exact mirror of `FetchProvenanceCollector`).
- `tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php`
- `tests/Feature/AI/PromptOverlayGoldenMasterTest.php` — the 4c hard gate (byte-identity on 4 representative turns with the empty corpus).
- `tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php` — behavioural tests against a temp fixture corpus.
- `tests/fixtures/PromptOverlay/*.json` — byte-frozen golden-master fixtures (committed; produced by the capture step).

**Modify:**
- `app/Services/AI/Fyn/FynContextAssembler.php` — inject `ProceduralCorpusLoader` + `ProceduralContributionCollector`; add the two additive layers after `<live_data>`.
- `app/Providers/AppServiceProvider.php` — `scoped(ProceduralContributionCollector::class)`.

**Consumed unchanged (4a substrate):** `ProceduralCorpusLoader::load()`, `ProceduralCorpus::ofKind()/active()`, `Procedure`.

**NOT touched:** `FynSystemPrompt.php` (byte-frozen), `AiToolDefinitions.php` (4b's tool catalogue), Two-Fyn dispatch/gating.

---

## Task 1: Capture the golden-master fixtures (current `build()` output, empty corpus)

**Files:**
- Create: `tests/Feature/AI/PromptOverlayGoldenMasterTest.php`
- Create (via capture run): `tests/fixtures/PromptOverlay/{advice_dashboard,advice_retirement_position,onboarding_savings,onboarding_protection}.json`

This is the **first task and the hard gate**: it captures the **current** (pre-mechanism) `build()` output for four representative turns into committed fixtures, then asserts post-mechanism output is byte-identical. The capture runs on the current branch tip (4b, before the assembler edit) with the empty overlay/fca_block corpus; Task 4 re-runs the assertion after the mechanism lands and it must stay green.

- [ ] **Step 1: Write the golden-master test**

Create `tests/Feature/AI/PromptOverlayGoldenMasterTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
        'name' => 'Test User',
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
```

- [ ] **Step 2: Capture the fixtures on the CURRENT (pre-mechanism) code**

Run the capture (writes the 4 fixtures, asserts twice-stable):

```bash
CAPTURE_PROMPT_OVERLAY_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/PromptOverlayGoldenMasterTest.php --filter="captures the current build"
```

Expected: PASS, and `tests/fixtures/PromptOverlay/` now holds 4 `.json` files. If the twice-capture `expect(...)->toBe(...)` fails, `build()` has a non-deterministic field for a data-free user — STOP and investigate (do not commit a flaky fixture); normalise the offending field before re-capturing.

- [ ] **Step 3: Run the assertion step on the current code to prove the fixtures are self-consistent**

```bash
./vendor/bin/pest tests/Feature/AI/PromptOverlayGoldenMasterTest.php
```

Expected: 4 passed + 1 skipped (the capture step skips without the env var). This proves the committed fixtures equal the current pre-mechanism `build()` output.

- [ ] **Step 4: Commit the fixtures + the gate test**

```bash
./vendor/bin/pint tests/Feature/AI/PromptOverlayGoldenMasterTest.php
git add tests/Feature/AI/PromptOverlayGoldenMasterTest.php tests/fixtures/PromptOverlay
git commit -m "test(coala): Phase 4c golden-master — capture current build() output for 4 representative turns

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: `ProceduralContributionCollector` (request-scoped accumulator)

**Files:**
- Create: `app/Services/AI/Memory/Procedural/ProceduralContributionCollector.php`
- Test: `tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php`

Exact mirror of `FetchProvenanceCollector`. 4c only **populates** it; 4e reads it at persist time.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralContributionCollector;

it('starts empty', function (): void {
    expect((new ProceduralContributionCollector)->all())->toBe([]);
});

it('records contributions in order', function (): void {
    $c = new ProceduralContributionCollector;
    $c->record(['procedure_id' => 'retirement.overlay.tone', 'kind' => 'system_prompt_overlay', 'module' => 'retirement', 'version' => 1]);
    $c->record(['procedure_id' => 'general.fca.dbtransfer', 'kind' => 'fca_block', 'module' => 'general', 'version' => 2]);

    expect($c->all())->toBe([
        ['procedure_id' => 'retirement.overlay.tone', 'kind' => 'system_prompt_overlay', 'module' => 'retirement', 'version' => 1],
        ['procedure_id' => 'general.fca.dbtransfer', 'kind' => 'fca_block', 'module' => 'general', 'version' => 2],
    ]);
});

it('reset clears all recorded contributions', function (): void {
    $c = new ProceduralContributionCollector;
    $c->record(['procedure_id' => 'x', 'kind' => 'fca_block', 'module' => 'general', 'version' => 1]);
    $c->reset();

    expect($c->all())->toBe([]);
});

it('is bound scoped in the container (one instance per resolution scope)', function (): void {
    expect(app(ProceduralContributionCollector::class))
        ->toBe(app(ProceduralContributionCollector::class));
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php
```

Expected: FAIL — `Class "App\Services\AI\Memory\Procedural\ProceduralContributionCollector" not found`. (The scoped-binding test will also fail until Task 5; that is expected — it goes green once the binding lands.)

- [ ] **Step 3: Write the minimal implementation**

Create `app/Services/AI/Memory/Procedural/ProceduralContributionCollector.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

/**
 * Request-scoped accumulator of the procedural contributions (overlay /
 * fca_block procedures) injected into the current turn's prompt by
 * FynContextAssembler. Each entry is
 * ['procedure_id' => …, 'kind' => …, 'module' => …, 'version' => int].
 * Exact mirror of FetchProvenanceCollector / SemanticSnapshotHolder: the
 * assembler records here as it wraps each contributed procedure; Phase 4e
 * reads it at persistEpisode time and binds it onto the episode attestation.
 * Bound `scoped` in the container — one instance per request, reset per turn.
 */
final class ProceduralContributionCollector
{
    /** @var list<array{procedure_id:string,kind:string,module:string,version:int}> */
    private array $entries = [];

    /** @param array{procedure_id:string,kind:string,module:string,version:int} $entry */
    public function record(array $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return list<array{procedure_id:string,kind:string,module:string,version:int}> */
    public function all(): array
    {
        return $this->entries;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
```

- [ ] **Step 4: Run test to verify it passes (except the scoped-binding case)**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php
```

Expected: 3 passed, 1 failed (the scoped-binding case — without an explicit `scoped` binding the container `make`s a fresh instance each time so the two `app()` calls are not `===`). That last case goes green in Task 5. Leave it red for now (TDD: the binding is a separate task).

> If the worker prefers an all-green commit here, move the `is bound scoped` test into Task 5's step 1 instead. The plan keeps it here so the collector's full contract is co-located; either placement is acceptable.

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/AI/Memory/Procedural/ProceduralContributionCollector.php tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php
git add app/Services/AI/Memory/Procedural/ProceduralContributionCollector.php tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php
git commit -m "feat(coala): ProceduralContributionCollector request-scoped accumulator (Phase 4c)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Behavioural tests for the overlay/fca_block layers (RED)

**Files:**
- Create: `tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php`

Mirrors `FynContextAssemblerKnowledgeTest`: a temp procedural corpus, then assert the blocks appear, are module-scoped, degrade on error, omit when empty, and populate the collector. These are written **before** the assembler change and must go RED first.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php`:

```php
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
```

- [ ] **Step 2: Run the tests to verify they fail (RED)**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php
```

Expected: the `<overlay>` / `<fca_block>` / collector assertions FAIL (no mechanism yet). The "omits", "module-scoped", "degrades", "inactive", "future", and "collector empty when nothing matched" cases may pass vacuously (the blocks never appear because the mechanism is absent) — that is fine; they exist to stay green after the mechanism lands. The positive-presence and collector-record cases must be RED.

- [ ] **Step 3: Do NOT commit yet** — these go green in Task 4.

---

## Task 4: Implement the overlay/fca_block consumption in `FynContextAssembler` (GREEN)

**Files:**
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php`

- [ ] **Step 1: Add the two constructor dependencies**

In `app/Services/AI/Fyn/FynContextAssembler.php`, add the imports next to the other AI memory imports (top of file, after the existing `use App\Services\AI\Memory\...` lines):

```php
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Memory\Procedural\ProceduralContributionCollector;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
```

Then extend the constructor (it currently ends with `private readonly FetchDispatcher $dispatcher,`):

```php
    public function __construct(
        private readonly FynContextSelector $selector,
        private readonly AdvicePromptBuilder $advice,
        private readonly MemoryRetrieverService $memory,
        private readonly TaxConfigService $taxConfig,
        private readonly FynMemoryStore $memoryStore,
        private readonly SemanticRetriever $semantic,
        private readonly PointerRegistry $pointers,
        private readonly FetchDispatcher $dispatcher,
        private readonly ProceduralCorpusLoader $proceduralLoader,
        private readonly ProceduralContributionCollector $proceduralContributions,
    ) {}
```

> The assembler is auto-resolved by the container (no explicit binding), so the two new constructor params are wired automatically. `ProceduralCorpusLoader` is already a bound singleton (4a); `ProceduralContributionCollector` is bound scoped in Task 5.

- [ ] **Step 2: Inject the two additive layers after `<live_data>`**

Locate the `<live_data>` block. It ends with:

```php
        if ($liveBlocks !== []) {
            $lines[] = "<live_data>\n".implode("\n\n", $liveBlocks)."\n</live_data>";
        }
```

Immediately **after** that closing `}` and **before** the `if ($has(ContextBucket::POSITION))` block, insert:

```php
        // CoALA Phase 4c — procedural prompt overlays + FCA blocks. Additive
        // per-turn layers AFTER the static prefix and after <live_data>,
        // mirroring <knowledge>/<live_data>: load the active procedures of the
        // two prose-bearing kinds, keep those whose module matches this turn
        // (or the 'general' wildcard), and emit one block per kind. The corpus
        // is empty today, so this is a no-op (proven by the golden master). A
        // malformed corpus degrades to no block — never breaks the turn.
        $turnModule = $ctx->isOnboarding()
            ? (string) $ctx->onboardingFocus
            : (string) ($ctx->classification['primary'] ?? '');

        try {
            $corpus = $this->proceduralLoader->load();
            $now = Carbon::now();

            $overlayBodies = $this->selectProcedures($corpus->ofKind('system_prompt_overlay'), $turnModule, $now);
            $fcaBodies = $this->selectProcedures($corpus->ofKind('fca_block'), $turnModule, $now);
        } catch (\Throwable $e) {
            report($e);
            $overlayBodies = [];
            $fcaBodies = [];
        }

        if ($overlayBodies !== []) {
            $lines[] = "<overlay>\n".implode("\n\n", $overlayBodies)."\n</overlay>";
        }
        if ($fcaBodies !== []) {
            $lines[] = "<fca_block>\n".implode("\n\n", $fcaBodies)."\n</fca_block>";
        }
```

- [ ] **Step 3: Add the `selectProcedures` helper**

Add this private method to the class (e.g. after `focusLabel`, before the final `}`):

```php
    /**
     * From the procedures of one kind, resolve the active version of each
     * distinct procedure_id and keep those whose module matches this turn (or
     * the 'general' wildcard). Records each kept procedure into the
     * request-scoped contribution collector and returns the bodies in
     * corpus-walk order.
     *
     * @param  list<Procedure>  $procedures
     * @return list<string>
     */
    private function selectProcedures(array $procedures, string $turnModule, Carbon $now): array
    {
        $bodies = [];
        $seen = [];
        foreach ($procedures as $proc) {
            if (isset($seen[$proc->procedureId])) {
                continue;
            }

            if ($proc->module !== 'general' && $proc->module !== $turnModule) {
                continue;
            }

            // Resolve the single active, in-force version for this id.
            $active = null;
            foreach ($procedures as $candidate) {
                if ($candidate->procedureId === $proc->procedureId
                    && $candidate->active
                    && $candidate->effectiveOn($now)
                    && ($active === null || $candidate->version > $active->version)) {
                    $active = $candidate;
                }
            }
            if ($active === null) {
                continue;
            }

            $seen[$proc->procedureId] = true;
            $bodies[] = $active->body;
            $this->proceduralContributions->record([
                'procedure_id' => $active->procedureId,
                'kind' => $active->kind,
                'module' => $active->module,
                'version' => $active->version,
            ]);
        }

        return $bodies;
    }
```

> Active resolution is done inline (highest active in-force version per id) rather than via `ProceduralCorpus::active()` so the corpus instance does not need re-querying per id; behaviour is identical (highest-version active, effective on `$now`). The `$proc->module` gate is checked on the first-seen version; because `module`+`kind` are path-derived and identical across versions of one `procedure_id` (4a invariant), gating on the first version is correct.

- [ ] **Step 4: Run the behavioural tests (now GREEN)**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php
```

Expected: ALL green. If any positive-presence case is still red, check the block ordering and the `general`-wildcard branch.

- [ ] **Step 5: Run the golden master — MUST stay byte-identical**

```bash
./vendor/bin/pest tests/Feature/AI/PromptOverlayGoldenMasterTest.php
```

Expected: 4 passed (+1 skipped). The empty shipping corpus means `selectProcedures` returns `[]` for both kinds, so no block is emitted and `build()` is byte-identical to the captured fixtures. **If this is RED, the mechanism changed behaviour — that is a bug; loop (diagnose → fix → re-verify) until byte-identical. If genuinely impossible, STOP and report BLOCKED — do not ship a behaviour change.**

- [ ] **Step 6: Run the existing assembler regression tests**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextAssemblerKnowledgeTest.php tests/Unit/Services/AI/Fyn/FynContextAssemblerLiveDataTest.php tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php
```

Expected: ALL green (the new layers are additive, ordered after `<live_data>`; `FynSystemPrompt` untouched).

- [ ] **Step 7: Pint + commit**

```bash
./vendor/bin/pint app/Services/AI/Fyn/FynContextAssembler.php tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php
git add app/Services/AI/Fyn/FynContextAssembler.php tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php
git commit -m "feat(coala): inject <overlay>/<fca_block> per-turn layers in FynContextAssembler (Phase 4c)

Module-scoped, degrade-on-error, records contributions; no-op against the
empty corpus (golden master byte-identical). Static prefix untouched.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Bind `ProceduralContributionCollector` as scoped

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the import**

In `app/Providers/AppServiceProvider.php`, next to the existing procedural import (`use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;`), add:

```php
use App\Services\AI\Memory\Procedural\ProceduralContributionCollector;
```

- [ ] **Step 2: Add the scoped binding**

In `register()`, immediately after the existing `$this->app->scoped(SemanticSnapshotHolder::class);` line, add:

```php
        // Request-scoped procedural-contribution accumulator (Phase 4c) — the
        // assembler records overlay/fca_block procedures it injected; Phase 4e
        // reads it at persistEpisode time. One instance per request, reset per turn.
        $this->app->scoped(ProceduralContributionCollector::class);
```

- [ ] **Step 3: Run the collector test (now fully green) + behavioural tests**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php
```

Expected: ALL green — the scoped-binding case now passes (two `app()` resolutions return the same instance), and the collector-record / collector-empty cases rely on the scoped instance being shared between the assembler and the test's `app(...)` lookup.

- [ ] **Step 4: Pint + commit**

```bash
./vendor/bin/pint app/Providers/AppServiceProvider.php
git add app/Providers/AppServiceProvider.php
git commit -m "feat(coala): scoped binding for ProceduralContributionCollector (Phase 4c)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: Confirm the shipped empty-corpus state + deploy gate

**Files:** none (verification only — 4c authors no `.md` content)

- [ ] **Step 1: Confirm no overlay/fca_block content ships**

```bash
ls fyn-memory/procedural/system_prompt_overlay fyn-memory/procedural/fca_block 2>/dev/null || echo "no overlay/fca_block content dirs — correct empty shipping state"
```

Expected: the directories are absent (the 4a loader tolerates absent kind dirs). Do NOT create a placeholder dir with a stray `.md` — an empty dir or no dir is the clean no-op. If discoverability is wanted, a `.gitkeep`-only dir is acceptable, but it must contain **no `.md` files**.

- [ ] **Step 2: Confirm the deploy gate passes against the real corpus**

```bash
php artisan fyn:procedural:validate
```

Expected: exit 0. The overlay/fca_block kinds are empty; only the 4b `tool_schema` content (and `pointers/` which is ignored) is present, so the gate validates the existing corpus unchanged. (This is the same gate 4a wired into the deploy chains; 4c adds no new wiring.)

---

## Task 7: Full-suite regression + Pint sweep

**Files:** none (verification only)

- [ ] **Step 1: Run the full 4c + adjacent AI surface**

```bash
./vendor/bin/pest tests/Feature/AI/PromptOverlayGoldenMasterTest.php tests/Unit/Services/AI/Memory/Procedural/ tests/Unit/Services/AI/Fyn/ tests/Feature/AI/
```

Expected: ALL green. Notably the 4b `ToolSchemaGoldenMasterTest` must stay green (4c does not touch the tool catalogue) and `FynSystemPromptTest` stays green (prefix-cache invariance).

- [ ] **Step 2: Run the whole suite**

```bash
./vendor/bin/pest
```

Expected: green. If a pre-existing flake appears (e.g. Actuarial — see MEMORY index), re-run that file in isolation to confirm it is unrelated to 4c; 4c touches only the additive assembler layer + a new scoped binding.

- [ ] **Step 3: Pint sweep over every changed/new file**

```bash
./vendor/bin/pint \
  app/Services/AI/Memory/Procedural/ProceduralContributionCollector.php \
  app/Services/AI/Fyn/FynContextAssembler.php \
  app/Providers/AppServiceProvider.php \
  tests/Unit/Services/AI/Memory/Procedural/ProceduralContributionCollectorTest.php \
  tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php \
  tests/Feature/AI/PromptOverlayGoldenMasterTest.php
```

Expected: `PASS`. **KNOWN PINT QUIRK:** if Pint strips a freshly-added `use` import (e.g. `Procedure` if its only usage is the `@param list<Procedure>` docblock — Pint may treat a docblock-only import as unused), re-add the import after confirming the usage and re-run. The `Procedure` import is used in the `selectProcedures` docblock type hint; if Pint removes it and PHPStan/static analysis is not run, it is harmless but keep it for the docblock. If Pint insists on removing it, drop the `use Procedure` import and fully-qualify in the docblock (`@param list<\App\Services\AI\Memory\Procedural\Procedure>`), then re-Pint.

- [ ] **Step 4: Architecture suite**

```bash
./vendor/bin/pest --testsuite=Architecture
```

Expected: green — the new collector is a concrete `final class`, not an interface.

- [ ] **Step 5: Final commit (only if Pint reformatted anything)**

```bash
git add -A
git commit -m "style(coala): pint Phase 4c prompt-overlay consumption

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Done-when checklist (verify against the spec §11)

- [ ] `FynContextAssembler::build()` emits `<overlay>` / `<fca_block>` layers after `<live_data>`, module-scoped (`module === turnModule` or `'general'`), degrade-on-error, populating `ProceduralContributionCollector`.
- [ ] `ProceduralContributionCollector` exists, scoped-bound, mirrors `FetchProvenanceCollector` (`record`/`all`/`reset`).
- [ ] 4c ships with empty `system_prompt_overlay` / `fca_block` corpus (no `.md` content).
- [ ] `PromptOverlayGoldenMasterTest` green — `build()` byte-identical on all 4 representative turns.
- [ ] `FynContextAssemblerOverlayTest` green — appearance, module-scoping, wildcard, onboarding-focus selection, omit-when-empty, inactive-skip, future-effective-skip, degrade, collector populate + collector empty all proven against a temp fixture corpus.
- [ ] `FynSystemPromptTest` still green untouched (prefix-cache invariance).
- [ ] `ToolSchemaGoldenMasterTest` still green (no tool-catalogue change — Two-Fyn contract intact).
- [ ] Full Pest suite green; `pint` passed on every changed file; Architecture suite green.
- [ ] No static content moved; deferred items (§10 of spec) recorded, not actioned.
