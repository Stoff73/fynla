<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/proc-'.uniqid();
    config(['fyn.memory.procedural_path' => $this->corpus]);
});

afterEach(fn () => File::deleteDirectory($this->corpus));

/** Write a procedure .md at {kind}/{module}/{file}.md with the given frontmatter + body. */
function writeProc(string $root, string $kind, string $module, string $file, array $frontmatter, string $body = 'Procedure body.'): void
{
    $dir = "{$root}/{$kind}/{$module}";
    @mkdir($dir, 0777, true);
    $fm = '';
    foreach ($frontmatter as $k => $v) {
        $fm .= $k.': '.(is_bool($v) ? ($v ? 'true' : 'false') : $v)."\n";
    }
    file_put_contents("{$dir}/{$file}.md", "---\n{$fm}---\n\n{$body}\n");
}

function validFrontmatter(array $overrides = []): array
{
    return array_merge([
        'procedure_id' => 'retirement.tool.create_dc_pension',
        'kind' => 'tool_schema',
        'module' => 'retirement',
        'version' => 1,
        'active' => true,
        'effective_from' => '2026-06-02',
    ], $overrides);
}

it('returns an empty corpus when the directory is missing', function (): void {
    expect(app(ProceduralCorpusLoader::class)->loadStrict()->all())->toBe([]);
});

it('parses a single valid procedure', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension', validFrontmatter(), "```json\n{}\n```");

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();

    expect($corpus->all())->toHaveCount(1)
        ->and($corpus->all()[0]->procedureId)->toBe('retirement.tool.create_dc_pension')
        ->and($corpus->all()[0]->kind)->toBe('tool_schema')
        ->and($corpus->all()[0]->module)->toBe('retirement')
        ->and($corpus->all()[0]->body)->toContain('json');
});

it('ignores the pointers/ sibling and README/_TEMPLATE files', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension', validFrontmatter());
    @mkdir("{$this->corpus}/pointers", 0777, true);
    file_put_contents("{$this->corpus}/pointers/isa.md", "---\nfoo: bar\n---\nnot a procedure\n");
    @mkdir("{$this->corpus}/tool_schema/retirement", 0777, true);
    file_put_contents("{$this->corpus}/tool_schema/retirement/README.md", "---\nx: y\n---\nreadme\n");

    expect(app(ProceduralCorpusLoader::class)->loadStrict()->all())->toHaveCount(1);
});

it('rejects a missing mandatory field', function (): void {
    $fm = validFrontmatter();
    unset($fm['version']);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', $fm);

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, "missing 'version'");
});

it('rejects an unknown kind in frontmatter', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(['kind' => 'nonsense']));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, "unknown kind 'nonsense'");
});

it('rejects a frontmatter kind that disagrees with the path', function (): void {
    writeProc($this->corpus, 'workflow', 'retirement', 'x', validFrontmatter(['kind' => 'tool_schema']));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, 'disagrees with path kind');
});

it('rejects a frontmatter module that disagrees with the path', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(['module' => 'estate']));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, 'disagrees with path module');
});

it('rejects version < 1', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(['version' => 0]));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, 'version must be >= 1');
});

it('rejects a non-boolean active', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(['active' => 'yes']));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, "'active' must be a boolean");
});

it('rejects duplicate (procedure_id, version)', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'a', validFrontmatter(['active' => true]));
    writeProc($this->corpus, 'tool_schema', 'retirement', 'b', validFrontmatter(['active' => false]));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, 'duplicate retirement.tool.create_dc_pension@1');
});

it('rejects more than one active version of the same procedure_id', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'a', validFrontmatter(['version' => 1, 'active' => true]));
    writeProc($this->corpus, 'tool_schema', 'retirement', 'b', validFrontmatter(['version' => 2, 'active' => true]));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, 'multiple active versions');
});

it('allows two actives with the same procedure_id under different providers', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'anth', validFrontmatter(['version' => 1, 'active' => true]), "```json\n{}\n```");
    writeProc($this->corpus, 'tool_schema', 'retirement', 'xai', validFrontmatter(['version' => 1, 'active' => true, 'provider' => 'xai']), "```json\n{}\n```");

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();

    expect($corpus->active('retirement.tool.create_dc_pension', 'anthropic'))->not->toBeNull()
        ->and($corpus->active('retirement.tool.create_dc_pension', 'anthropic')->provider)->toBe('anthropic')
        ->and($corpus->active('retirement.tool.create_dc_pension', 'xai'))->not->toBeNull()
        ->and($corpus->active('retirement.tool.create_dc_pension', 'xai')->provider)->toBe('xai');
});

it('rejects an out-of-range provider', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(['provider' => 'openai']), "```json\n{}\n```");

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, "unknown provider 'openai'");
});

it('defaults provider to anthropic when omitted', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(), "```json\n{}\n```");

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();
    expect($corpus->all()[0]->provider)->toBe('anthropic');
});

it('accepts multiple inactive versions plus one active', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'v1', validFrontmatter(['version' => 1, 'active' => false]));
    writeProc($this->corpus, 'tool_schema', 'retirement', 'v2', validFrontmatter(['version' => 2, 'active' => true]));

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();
    expect($corpus->versions('retirement.tool.create_dc_pension'))->toHaveCount(2)
        ->and($corpus->active('retirement.tool.create_dc_pension')?->version)->toBe(2);
});

it('load() returns the parsed corpus', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    expect(app(ProceduralCorpusLoader::class)->load()->all())->toHaveCount(1);
});

it('load() serves stale within the reload interval', function (): void {
    config(['fyn.memory.procedural_reload_interval' => 3600]);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    $loader = app(ProceduralCorpusLoader::class);
    expect($loader->load()->all())->toHaveCount(1);

    writeProc($this->corpus, 'workflow', 'onboarding', 'y', validFrontmatter(['procedure_id' => 'onboarding.flow.main', 'kind' => 'workflow', 'module' => 'onboarding']));
    expect($loader->load()->all())->toHaveCount(1); // still stale within window
});

it('load() reloads when the interval is zero and the signature changes', function (): void {
    config(['fyn.memory.procedural_reload_interval' => 0]);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    $loader = app(ProceduralCorpusLoader::class);
    expect($loader->load()->all())->toHaveCount(1);

    writeProc($this->corpus, 'workflow', 'onboarding', 'y', validFrontmatter(['procedure_id' => 'onboarding.flow.main', 'kind' => 'workflow', 'module' => 'onboarding']));
    expect($loader->load()->all())->toHaveCount(2);
});

it('load() keeps the last-good corpus when a reload turns invalid', function (): void {
    config(['fyn.memory.procedural_reload_interval' => 0]);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    $loader = app(ProceduralCorpusLoader::class);
    expect($loader->load()->all())->toHaveCount(1);

    // Corrupt the corpus (second active version of the same id) — parse throws.
    writeProc($this->corpus, 'tool_schema', 'retirement', 'dupe', validFrontmatter(['version' => 9, 'active' => true]));
    $result = $loader->load();
    expect($result->all())->toHaveCount(1); // degraded to last-good, no throw
});

it('load() returns an empty corpus on a cold-boot invalid corpus (never throws)', function (): void {
    config(['fyn.memory.procedural_reload_interval' => 0]);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'a', validFrontmatter(['version' => 1, 'active' => true]));
    writeProc($this->corpus, 'tool_schema', 'retirement', 'b', validFrontmatter(['version' => 2, 'active' => true]));

    expect(app(ProceduralCorpusLoader::class)->load()->all())->toBe([]);
});

it('load() populates the cross-request cache', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    app(ProceduralCorpusLoader::class)->load();
    expect(Cache::has('fyn:procedural:corpus'))->toBeTrue()
        ->and(Cache::has('fyn:procedural:corpus:sig'))->toBeTrue();
});

it('a cold instance adopts the cross-request cache', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    app(ProceduralCorpusLoader::class)->load(); // populates the Laravel cache

    // Fresh instance ($this->corpus === null) must adopt the cached corpus
    // (signature unchanged) via the cold-cache branch, not start empty.
    app()->forgetInstance(ProceduralCorpusLoader::class);
    expect(app(ProceduralCorpusLoader::class)->load()->all())->toHaveCount(1);
});

it('ships A1/A2 overlays inactive — validated but never injected', function (): void {
    // Point at the REAL shipped corpus (not the temp dir from beforeEach) —
    // loadStrict() is the fyn:procedural:validate deploy gate, so this pins
    // "the real corpus validates AND the A1/A2 overlays are inactive in it".
    config(['fyn.memory.procedural_path' => base_path('fyn-memory/procedural')]);

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();

    // Validated: both procedures ARE in the corpus…
    $ids = array_map(fn ($p) => $p->procedureId, $corpus->all());
    expect($ids)->toContain('general.overlay.a1_answer_first')
        ->and($ids)->toContain('general.overlay.a2_ack_hygiene');

    // …but inactive: no active version resolves, so they are never injected.
    expect($corpus->active('general.overlay.a1_answer_first'))->toBeNull()
        ->and($corpus->active('general.overlay.a2_ack_hygiene'))->toBeNull();
});

it('load() never throws when statting the corpus raises mid-scan', function (): void {
    config(['fyn.memory.procedural_reload_interval' => 0]);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    $loader = app(ProceduralCorpusLoader::class);
    expect($loader->load()->all())->toHaveCount(1); // last-good populated

    // Simulate a concurrent corpus swap: a file vanishes between enumeration
    // and stat, so the filesystem layer raises. load() must degrade, not throw.
    // partialMock so only allFiles is overridden — isDirectory and the afterEach
    // deleteDirectory still hit the real filesystem.
    File::partialMock()->shouldReceive('allFiles')->andThrow(new RuntimeException('file vanished mid-scan'));

    expect($loader->load()->all())->toHaveCount(1); // degraded to last-good
});
