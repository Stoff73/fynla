<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticCorpusLoader;
use Illuminate\Support\Facades\File;

function writeFact(string $dir, string $category, string $name, string $frontmatter, string $body = 'A fact body.'): void
{
    @mkdir("$dir/$category", 0777, true);
    file_put_contents("$dir/$category/$name.md", "---\n$frontmatter\n---\n\n$body\n");
}

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    @mkdir($this->corpus, 0777, true);
    config(['fyn.memory.semantic_path' => $this->corpus]);
});

afterEach(fn () => File::deleteDirectory($this->corpus));

it('loads a valid fact indexed by fact_id', function (): void {
    writeFact($this->corpus, 'fca', 'a', "fact_id: fca-a\ncategory: fca\ntitle: A\nsource: COBS\nversion: 1\nvalid_from: 2024-04-06\nvalid_to: null");

    $facts = app(SemanticCorpusLoader::class)->all();

    expect($facts)->toHaveCount(1)
        ->and($facts['fca-a']->category)->toBe('fca')
        ->and($facts['fca-a']->version)->toBe(1);
});

it('fails closed on a duplicate fact_id', function (): void {
    writeFact($this->corpus, 'fca', 'a', "fact_id: dup\ncategory: fca\ntitle: A\nsource: COBS\nversion: 1\nvalid_from: 2024-04-06");
    writeFact($this->corpus, 'product', 'b', "fact_id: dup\ncategory: product\ntitle: B\nsource: ref\nversion: 1");

    expect(fn () => app(SemanticCorpusLoader::class)->all())
        ->toThrow(RuntimeException::class, 'duplicate fact_id');
});

it('fails closed on a missing mandatory field', function (): void {
    writeFact($this->corpus, 'fca', 'a', "fact_id: fca-a\ncategory: fca\ntitle: A\nversion: 1\nvalid_from: 2024-04-06"); // no source

    expect(fn () => app(SemanticCorpusLoader::class)->all())
        ->toThrow(RuntimeException::class, 'source');
});

it('fails closed on an unknown category', function (): void {
    writeFact($this->corpus, 'fca', 'a', "fact_id: fca-a\ncategory: nonsense\ntitle: A\nsource: x\nversion: 1\nvalid_from: 2024-04-06");

    expect(fn () => app(SemanticCorpusLoader::class)->all())
        ->toThrow(RuntimeException::class, 'category');
});

it('skips .gitkeep and the template', function (): void {
    @mkdir("$this->corpus/fca", 0777, true);
    file_put_contents("$this->corpus/fca/.gitkeep", '');
    copy(base_path('fyn-memory/semantic/_TEMPLATE.md'), "$this->corpus/_TEMPLATE.md");

    expect(app(SemanticCorpusLoader::class)->all())->toBe([]);
});
