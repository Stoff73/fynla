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

it('respects the categories filter', function (): void {
    writeFact2($this->corpus, 'fca', 'r1', "fact_id: fca-r1\ncategory: fca\ntitle: FCA rule\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Regulated advice matters.');
    writeFact2($this->corpus, 'product', 'p1', "fact_id: prod-p1\ncategory: product\ntitle: Product rule\nsource: ref\nversion: 1", 'Product advice matters.');

    $hits = app(SemanticRetriever::class)->retrieve('advice matters', Carbon::now(), ['fca']);

    expect($hits)->toHaveCount(1)->and($hits[0]->factId)->toBe('fca-r1');
});

it('returns [] when top_k is misconfigured to zero or negative', function (): void {
    config(['fyn.memory.semantic_top_k' => -1]);
    writeFact2($this->corpus, 'fca', 'a', "fact_id: fca-a\ncategory: fca\ntitle: Advice\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Regulated advice.');

    expect(app(SemanticRetriever::class)->retrieve('advice', Carbon::now()))->toBe([]);
});

// ── §8.2 conformance: stopwords, word-boundary scoring, distinct-match gate ──

it('returns nothing for a query made only of stopwords', function (): void {
    // Body is a substring trap: "how" sits inside "However"/"shows", "they" appears
    // verbatim — the old substr_count scorer matched this fact for a pure-chatter query.
    writeFact2($this->corpus, 'fca', 'trap', "fact_id: fca-trap\ncategory: fca\ntitle: Substring trap\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'However, the approach shows rather more than they admit.');

    expect(app(SemanticRetriever::class)->retrieve('How should they do this?', Carbon::now()))->toBe([]);
});

it('does not match stopwords as substrings inside words (the in them/rather)', function (): void {
    writeFact2($this->corpus, 'fca', 'them', "fact_id: fca-them\ncategory: fca\ntitle: Boundary trap\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Them and rather and whether, together.');

    // "the" is a stopword; "zebra" matches nothing — the fact must not be admitted
    // even though "the" occurs four times as a substring of its body words.
    expect(app(SemanticRetriever::class)->retrieve('the zebra', Carbon::now()))->toBe([]);
});

it('does not match content tokens as substrings inside words (rate in moderate/corporate)', function (): void {
    writeFact2($this->corpus, 'fca', 'rate', "fact_id: fca-rate\ncategory: fca\ntitle: Word boundary\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Moderate growth and corporate bonds.');

    expect(app(SemanticRetriever::class)->retrieve('rate', Carbon::now()))->toBe([]);
});

it('matches simple plural variants of query tokens (pensions finds pension, and back)', function (): void {
    writeFact2($this->corpus, 'fca', 'sing', "fact_id: fca-sing\ncategory: fca\ntitle: Pension relief\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'A pension contribution attracts relief.');

    $plural = app(SemanticRetriever::class)->retrieve('pensions contributions', Carbon::now());
    expect($plural)->toHaveCount(1)->and($plural[0]->factId)->toBe('fca-sing');

    writeFact2($this->corpus, 'fca', 'plur', "fact_id: fca-plur\ncategory: fca\ntitle: ISA limits\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'ISAs shelter savings from dividend taxes.');

    $singular = app(SemanticRetriever::class)->retrieve('isa dividend', Carbon::now());
    expect($singular)->toHaveCount(1)->and($singular[0]->factId)->toBe('fca-plur');
});

it('still retrieves on a single content-token query', function (): void {
    writeFact2($this->corpus, 'fca', 'one', "fact_id: fca-one\ncategory: fca\ntitle: Pension advice\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'A pension needs regulated advice.');

    $hits = app(SemanticRetriever::class)->retrieve('pension?', Carbon::now());

    expect($hits)->toHaveCount(1)->and($hits[0]->factId)->toBe('fca-one');
});

it('excludes facts matching only one distinct content token on multi-token queries', function (): void {
    // Fact A repeats a single query token; fact B matches two distinct tokens.
    // Under the old scorer A outscored B (5 vs 2) — the gate must exclude A entirely.
    writeFact2($this->corpus, 'fca', 'repeat', "fact_id: fca-repeat\ncategory: fca\ntitle: Repetition\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Pension pension pension pension pension schemes.');
    writeFact2($this->corpus, 'fca', 'two', "fact_id: fca-two\ncategory: fca\ntitle: Coverage\nsource: COBS\nversion: 1\nvalid_from: 2020-01-01", 'Pension contribution limits.');

    $hits = app(SemanticRetriever::class)->retrieve('pension contribution tax', Carbon::now());

    expect($hits)->toHaveCount(1)->and($hits[0]->factId)->toBe('fca-two');
});
