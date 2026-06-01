<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticRetriever;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

function writeFact2(string $dir, string $cat, string $name, string $fm, string $body): void
{
    @mkdir("$dir/$cat", 0777, true);
    file_put_contents("$dir/$cat/$name.md", "---\n$fm\n---\n\n$body\n");
}

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    config(['fyn.memory.semantic_path' => $this->corpus, 'fyn.memory.semantic_top_k' => 3]);
});

afterEach(fn () => File::deleteDirectory($this->corpus));

it('returns facts whose terms match the query, highest score first', function (): void {
    writeFact2($this->corpus, 'fca', 'pension', "fact_id: fca-pension\ncategory: fca\ntitle: Pension transfer advice\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'A defined benefit pension transfer needs regulated advice.');
    writeFact2($this->corpus, 'product', 'isa', "fact_id: prod-isa\ncategory: product\ntitle: ISA wrapper\nsource: ref\nversion: 1", 'An ISA shelters savings from tax.');

    $hits = app(SemanticRetriever::class)->retrieve('pension transfer', Carbon::parse('2025-06-01'));

    expect($hits)->toHaveCount(1)->and($hits[0]->factId)->toBe('fca-pension');
});

it('excludes facts not in force on the effective date, before ranking', function (): void {
    writeFact2($this->corpus, 'allowance', 'old', "fact_id: allow-old\ncategory: allowance\ntitle: Old allowance rule\nsource: x\nversion: 1\nvalid_from: 2018-04-06\nvalid_to: 2023-04-05", 'The lifetime allowance applied to pensions.');

    $current = app(SemanticRetriever::class)->retrieve('lifetime allowance', Carbon::parse('2025-06-01'));
    $historic = app(SemanticRetriever::class)->retrieve('lifetime allowance', Carbon::parse('2022-06-01'));

    expect($current)->toBe([])->and($historic)->toHaveCount(1);
});

it('caps results at top_k', function (): void {
    foreach (range(1, 5) as $i) {
        writeFact2($this->corpus, 'fca', "f$i", "fact_id: fca-$i\ncategory: fca\ntitle: Advice rule $i\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Regulated advice signpost.');
    }

    expect(app(SemanticRetriever::class)->retrieve('advice signpost', Carbon::now()))->toHaveCount(3);
});

it('returns the snapshot id as a sha256 over sorted (fact_id, version)', function (): void {
    writeFact2($this->corpus, 'fca', 'a', "fact_id: fca-a\ncategory: fca\ntitle: Advice\nsource: COBS\nversion: 2\nvalid_from: 2020-01-01", 'Regulated advice.');

    $hits = app(SemanticRetriever::class)->retrieve('advice', Carbon::now());
    $snap = app(SemanticRetriever::class)->snapshotId($hits);

    expect($snap)->toBe(hash('sha256', 'fca-a@2'));
});

it('returns nothing for a query with no usable terms', function (): void {
    writeFact2($this->corpus, 'fca', 'a', "fact_id: fca-a\ncategory: fca\ntitle: Advice\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Regulated advice.');

    expect(app(SemanticRetriever::class)->retrieve('a to', Carbon::now()))->toBe([]); // terms <3 chars dropped
});
