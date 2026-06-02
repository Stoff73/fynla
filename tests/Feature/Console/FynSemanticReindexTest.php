<?php

declare(strict_types=1);
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    $this->index = sys_get_temp_dir().'/idx-'.uniqid().'.json';
    config(['fyn.memory.semantic_path' => $this->corpus, 'fyn.memory.semantic_index' => $this->index]);
    @mkdir("$this->corpus/fca", 0777, true);
    file_put_contents("$this->corpus/fca/a.md", "---\nfact_id: fca-a\ncategory: fca\ntitle: A\nsource: COBS\nversion: 1\nvalid_from: 2024-04-06\n---\n\nBody.\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->corpus);
    @unlink($this->index);
});

it('validates the corpus and writes a cached index', function (): void {
    $this->artisan('fyn:semantic:reindex')->assertExitCode(0);

    expect(file_exists($this->index))->toBeTrue();
    $idx = json_decode(file_get_contents($this->index), true);
    expect($idx['count'])->toBe(1)->and($idx['facts']['fca-a']['version'])->toBe(1);
});

it('exits non-zero and writes nothing on a malformed corpus', function (): void {
    file_put_contents("$this->corpus/fca/bad.md", "---\nfact_id: fca-a\ncategory: fca\ntitle: dup\nsource: x\nversion: 1\nvalid_from: 2024-04-06\n---\n\nDup id.\n");

    $this->artisan('fyn:semantic:reindex')->assertExitCode(1);
    expect(file_exists($this->index))->toBeFalse();
});
