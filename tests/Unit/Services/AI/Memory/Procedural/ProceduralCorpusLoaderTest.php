<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
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

it('accepts multiple inactive versions plus one active', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'v1', validFrontmatter(['version' => 1, 'active' => false]));
    writeProc($this->corpus, 'tool_schema', 'retirement', 'v2', validFrontmatter(['version' => 2, 'active' => true]));

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();
    expect($corpus->versions('retirement.tool.create_dc_pension'))->toHaveCount(2)
        ->and($corpus->active('retirement.tool.create_dc_pension')?->version)->toBe(2);
});
