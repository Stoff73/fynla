<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Support\Facades\File;

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
