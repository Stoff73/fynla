<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/proc-cmd-'.uniqid();
    config(['fyn.memory.procedural_path' => $this->corpus]);
});

afterEach(fn () => File::deleteDirectory($this->corpus));

function writeCmdProc(string $root, string $kind, string $module, string $file, array $frontmatter): void
{
    $dir = "{$root}/{$kind}/{$module}";
    @mkdir($dir, 0777, true);
    $fm = '';
    foreach ($frontmatter as $k => $v) {
        $fm .= $k.': '.(is_bool($v) ? ($v ? 'true' : 'false') : $v)."\n";
    }
    file_put_contents("{$dir}/{$file}.md", "---\n{$fm}---\n\nbody\n");
}

it('exits 0 and summarises a valid corpus', function (): void {
    writeCmdProc($this->corpus, 'tool_schema', 'retirement', 'x', [
        'procedure_id' => 'retirement.tool.x', 'kind' => 'tool_schema', 'module' => 'retirement',
        'version' => 1, 'active' => true, 'effective_from' => '2026-06-02',
    ]);

    $this->artisan('fyn:procedural:validate')
        ->expectsOutputToContain('1 procedure')
        ->assertExitCode(0);
});

it('exits 0 on an empty corpus', function (): void {
    $this->artisan('fyn:procedural:validate')->assertExitCode(0);
});

it('exits non-zero and reports the offending file on an invalid corpus', function (): void {
    writeCmdProc($this->corpus, 'tool_schema', 'retirement', 'a', [
        'procedure_id' => 'retirement.tool.x', 'kind' => 'tool_schema', 'module' => 'retirement',
        'version' => 1, 'active' => true, 'effective_from' => '2026-06-02',
    ]);
    writeCmdProc($this->corpus, 'tool_schema', 'retirement', 'b', [
        'procedure_id' => 'retirement.tool.x', 'kind' => 'tool_schema', 'module' => 'retirement',
        'version' => 2, 'active' => true, 'effective_from' => '2026-06-02',
    ]);

    $this->artisan('fyn:procedural:validate')
        ->expectsOutputToContain('multiple active versions')
        ->assertExitCode(1);
});
