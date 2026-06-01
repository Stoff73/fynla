# CoALA Phase 1 — Semantic Memory (sparse, additive) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up Fyn's semantic memory as a git-tracked, effective-date-aware **domain-knowledge corpus** (FCA narrative + product reference + house-view), retrieved by **sparse keyword scoring** and injected into the per-turn `<knowledge>` block — without touching the prefix-cached static prompt.

**Architecture:** A markdown-with-YAML-frontmatter corpus under `fyn-memory/semantic/{category}/*.md`, loaded + validated fail-closed at boot by `SemanticCorpusLoader` (mirroring the existing `FynMemoryStore` parser), queried by `SemanticRetriever` (keyword over title+body, **effective-date filter applied before ranking**), and surfaced by `FynContextAssembler` as an additive `<knowledge>` block. No embeddings, no dense retrieval, no new external provider (deferred until ~500 concurrent users). Numeric tax values continue to flow through `TaxConfigService` (Rule #3) — the `tax` category holds narrative only.

**Tech Stack:** Laravel 10 / PHP 8.2, `Symfony\Component\Yaml`, `Illuminate\Support\Facades\File`, Pest. No new Composer dependencies.

---

## Decisions locked (CSJ, 2026-06-01)

1. **Static prompt = Option A (additive).** `FynSystemPrompt::text()` stays byte-identical; the compliance backbone remains baked in. Semantic memory is an *additional* per-turn `<knowledge>` channel for deeper/conditional knowledge — never the sole carrier of compliance text.
2. **Sparse-only, no embeddings, in any phase.** Dense retrieval deferred until ~500 concurrent active users. `fyn:semantic:reindex` is a validate-and-cache step, not an embeddings generator. (This also descopes Phase 6's dense "similar-case recall".)
3. **No embeddings provider** introduced.
4. **Corpus location = `fyn-memory/semantic/`** (already wired via `config('fyn.memory.semantic_path')`), not the plan's `app/Resources/Memory/Semantic/`.

## Conflicts reconciled (read before starting)

- **"Semantic memory" naming clash.** `fyn-memory/semantic/README.md` sketched a *per-user distilled-facts* store (`semantic/<user_id>/profile.md`). The CoALA plan's Phase 1 is the *global domain-knowledge corpus*. **Phase 1 builds the domain corpus** under `semantic/{fca,product,house_view,tax,allowance}/`. The per-user distilled-facts store is a **reserved future layer** (populated by episodic consolidation, out of scope here). Task 1 updates the README to say so.
- **FCA content lives in the cached static prompt, not in per-turn heredocs.** Confirmed: `FynSystemPrompt::text()` contains `<regulatory_compliance>`, `<fca_process>`, `<fca_signposting>` with a "DO NOT reword" guard. Per Decision A, **we do not move it.** The `fca` corpus is *additional depth* (worked examples, edge-case narrative, citations), not a relocation of the backbone. Task 9 pins the static prompt's hash so a regression is caught.
- **Plan estimate correction.** Plan guessed "~30–80 fca files / ~50–150 product files". Reality: the FCA prompt narrative is ~306 source lines (→ a handful of seed exemplars, grown over time) and `tax_product_reference` is **50 rows** (→ ≤50 generated files). Phase 1 ships the *engine* complete plus a *bootstrapped* corpus (product generated mechanically + a few FCA exemplars); corpus growth is ongoing content work, flagged, not blocking.

## File structure

**Create:**
- `app/Services/AI/Memory/SemanticCorpusLoader.php` — parse + validate + index the corpus at boot; fail-closed.
- `app/Services/AI/Memory/SemanticFact.php` — immutable value object for one fact (frontmatter + body).
- `app/Services/AI/Memory/SemanticRetriever.php` — sparse keyword scoring, effective-date filter before ranking, top-K.
- `app/Console/Commands/FynSemanticReindex.php` — `fyn:semantic:reindex` (validate + write cached index JSON).
- `app/Console/Commands/FynSemanticGenerateProducts.php` — `fyn:semantic:generate-products` (tax_product_reference → `.md`).
- `fyn-memory/semantic/_TEMPLATE.md` — frontmatter contract + example.
- `fyn-memory/semantic/fca/` `+ product/ + house_view/ + tax/ + allowance/` — category dirs (`.gitkeep` where empty).
- `tests/Unit/Services/AI/Memory/SemanticCorpusLoaderTest.php`
- `tests/Unit/Services/AI/Memory/SemanticRetrieverTest.php`
- `tests/Feature/Console/FynSemanticReindexTest.php`
- `tests/Feature/Console/FynSemanticGenerateProductsTest.php`
- `tests/Unit/Services/AI/Fyn/FynContextAssemblerKnowledgeTest.php`
- `tests/Unit/Services/AI/SemanticCorpusContentTest.php` — corpus-hygiene guards (no hardcoded tax numbers in `tax`; valid frontmatter).
- `tests/Architecture/FynStaticPromptByteInvarianceTest.php` — pin `FynSystemPrompt::text()` hash.

**Modify:**
- `config/fyn.php` — add `memory.semantic_index` path + `memory.semantic_categories` enum + `memory.semantic_top_k`.
- `app/Services/AI/Fyn/FynContextAssembler.php:34,73` — inject `SemanticRetriever`, emit `<knowledge>` after `<remembered>`.
- `fyn-memory/semantic/README.md` — reconcile the naming clash.

**Frontmatter contract (the corpus interface — every `.md` MUST satisfy):**
```yaml
fact_id: fca-suitability-signposting     # unique across the corpus; kebab-case
category: fca                             # one of: fca|product|house_view|tax|allowance
title: When to signpost a regulated adviser
source: "FCA COBS 9.2 / Consumer Duty"   # citation; mandatory for fca|product
version: 1                               # integer >= 1
valid_from: 2024-04-06                   # mandatory for fca|tax|allowance; ISO date
valid_to: null                           # null = still in force
```

**Test DB note:** corpus tasks are pure file I/O (no DB). Tasks reading `tax_product_reference` or building a `User` use the isolated DB: prefix Pest with `DB_DATABASE=laravel_testing` (per MEMORY.md `feedback_never_artisan_env_testing`). Point the loader at a temp corpus dir in tests via `config(['fyn.memory.semantic_path' => $tmp])` — never the real tree (mirrors the episodic test convention).

---

### Task 1: Corpus scaffold + frontmatter contract + README reconciliation

**Files:**
- Create: `fyn-memory/semantic/_TEMPLATE.md`, `fyn-memory/semantic/{fca,product,house_view,tax,allowance}/.gitkeep`
- Modify: `fyn-memory/semantic/README.md`

- [ ] **Step 1: Create the category dirs with `.gitkeep`**

```bash
mkdir -p fyn-memory/semantic/{fca,product,house_view,tax,allowance}
touch fyn-memory/semantic/{fca,product,house_view,tax,allowance}/.gitkeep
```

- [ ] **Step 2: Create `fyn-memory/semantic/_TEMPLATE.md`**

```markdown
---
fact_id: example-fact-id
category: fca
title: Human-readable fact title
source: "Citation — FCA handbook ref / internal house view owner"
version: 1
valid_from: 2024-04-06
valid_to: null
---

The fact body, in plain prose. This is narrative knowledge Fyn may draw on —
NEVER numeric tax values (those come from TaxConfigService, CLAUDE.md Rule #3).
One fact per file. Keep it focused; sparse retrieval matches title + body.
```

- [ ] **Step 3: Reconcile the README**

Replace `fyn-memory/semantic/README.md` body so it states: Phase 1 semantic memory is the **global domain-knowledge corpus** organised by `category/` (`fca`, `product`, `house_view`, `tax`, `allowance`), retrieved sparsely and injected per-turn; the previously-sketched per-user `semantic/<user_id>/profile.md` distilled-facts store is a **reserved future layer** populated by episodic consolidation, not built in Phase 1.

- [ ] **Step 4: Commit**

```bash
git add fyn-memory/semantic
git commit -m "feat(coala): semantic corpus scaffold + frontmatter contract (Phase 1)"
```

---

### Task 2: `SemanticFact` value object

**Files:**
- Create: `app/Services/AI/Memory/SemanticFact.php`
- Test: `tests/Unit/Services/AI/Memory/SemanticCorpusLoaderTest.php` (shared file; VO covered via loader tests in Task 3)

- [ ] **Step 1: Write `SemanticFact`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;

/**
 * One immutable semantic-memory fact (frontmatter + body). Effective-dating is
 * answered here so the retriever filters before ranking.
 */
final class SemanticFact
{
    /** @param array<string,mixed> $meta */
    public function __construct(
        public readonly string $factId,
        public readonly string $category,
        public readonly string $title,
        public readonly string $source,
        public readonly int $version,
        public readonly ?Carbon $validFrom,
        public readonly ?Carbon $validTo,
        public readonly string $body,
    ) {}

    /** True when this fact is in force on $on. */
    public function effectiveOn(Carbon $on): bool
    {
        if ($this->validFrom !== null && $on->lt($this->validFrom)) {
            return false;
        }
        if ($this->validTo !== null && $on->gt($this->validTo)) {
            return false;
        }

        return true;
    }

    /** Lower-cased "title body" haystack for sparse matching. */
    public function haystack(): string
    {
        return mb_strtolower($this->title.' '.$this->body);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/AI/Memory/SemanticFact.php
git commit -m "feat(coala): SemanticFact value object (Phase 1)"
```

---

### Task 3: `SemanticCorpusLoader` (parse + validate, fail-closed)

**Files:**
- Create: `app/Services/AI/Memory/SemanticCorpusLoader.php`
- Test: `tests/Unit/Services/AI/Memory/SemanticCorpusLoaderTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticCorpusLoader;

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

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->corpus));

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
    file_put_contents("$this->corpus/fca/.gitkeep", '');
    copy(base_path('fyn-memory/semantic/_TEMPLATE.md'), "$this->corpus/_TEMPLATE.md");

    expect(app(SemanticCorpusLoader::class)->all())->toBe([]);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Memory/SemanticCorpusLoaderTest.php`
Expected: FAIL — `Class "App\Services\AI\Memory\SemanticCorpusLoader" not found`

- [ ] **Step 3: Write `SemanticCorpusLoader`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads + validates the semantic-memory corpus (fyn-memory/semantic/{category}/*.md).
 * Fail-closed: a malformed corpus throws rather than silently serving a partial
 * knowledge base to a compliance-facing assistant. Mirrors FynMemoryStore::parse.
 */
final class SemanticCorpusLoader
{
    private const SKIP = ['_TEMPLATE.md', 'README.md'];

    private const CATEGORIES = ['fca', 'product', 'house_view', 'tax', 'allowance'];

    /** source mandatory for these; valid_from mandatory for those. */
    private const SOURCE_REQUIRED = ['fca', 'product'];

    private const VALID_FROM_REQUIRED = ['fca', 'tax', 'allowance'];

    /** @var array<string, SemanticFact>|null */
    private ?array $cache = null;

    /** @return array<string, SemanticFact> keyed by fact_id */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $root = (string) config('fyn.memory.semantic_path');
        $facts = [];

        if (! File::isDirectory($root)) {
            return $this->cache = [];
        }

        foreach (File::allFiles($root) as $file) {
            if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                continue;
            }

            $fact = $this->parseAndValidate($file->getPathname(), File::get($file->getPathname()));

            if (isset($facts[$fact->factId])) {
                throw new RuntimeException("Semantic corpus: duplicate fact_id '{$fact->factId}' ({$file->getPathname()}).");
            }

            $facts[$fact->factId] = $fact;
        }

        return $this->cache = $facts;
    }

    private function parseAndValidate(string $path, string $contents): SemanticFact
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) !== 1) {
            throw new RuntimeException("Semantic corpus: missing frontmatter ({$path}).");
        }

        $meta = Yaml::parse($m[1]);
        $meta = is_array($meta) ? $meta : [];
        $body = trim($m[2]);

        $require = function (string $key) use ($meta, $path): mixed {
            if (! array_key_exists($key, $meta) || $meta[$key] === null || $meta[$key] === '') {
                throw new RuntimeException("Semantic corpus: missing '{$key}' ({$path}).");
            }

            return $meta[$key];
        };

        $factId = (string) $require('fact_id');
        $category = (string) $require('category');
        if (! in_array($category, self::CATEGORIES, true)) {
            throw new RuntimeException("Semantic corpus: unknown category '{$category}' ({$path}).");
        }

        $title = (string) $require('title');
        $version = (int) $require('version');
        if ($version < 1) {
            throw new RuntimeException("Semantic corpus: version must be >= 1 ({$path}).");
        }

        $source = in_array($category, self::SOURCE_REQUIRED, true)
            ? (string) $require('source')
            : (string) ($meta['source'] ?? '');

        $validFromRaw = in_array($category, self::VALID_FROM_REQUIRED, true)
            ? $require('valid_from')
            : ($meta['valid_from'] ?? null);

        return new SemanticFact(
            factId: $factId,
            category: $category,
            title: $title,
            source: $source,
            version: $version,
            validFrom: $validFromRaw !== null ? Carbon::parse((string) $validFromRaw)->startOfDay() : null,
            validTo: isset($meta['valid_to']) && $meta['valid_to'] !== null ? Carbon::parse((string) $meta['valid_to'])->endOfDay() : null,
            body: $body,
        );
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Memory/SemanticCorpusLoaderTest.php`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Memory/SemanticCorpusLoader.php tests/Unit/Services/AI/Memory/SemanticCorpusLoaderTest.php
git commit -m "feat(coala): SemanticCorpusLoader — parse + validate fail-closed (Phase 1)"
```

---

### Task 4: `SemanticRetriever` (sparse scoring, effective-date filter before ranking)

**Files:**
- Create: `app/Services/AI/Memory/SemanticRetriever.php`
- Test: `tests/Unit/Services/AI/Memory/SemanticRetrieverTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticRetriever;
use Illuminate\Support\Carbon;

function writeFact2(string $dir, string $cat, string $name, string $fm, string $body): void
{
    @mkdir("$dir/$cat", 0777, true);
    file_put_contents("$dir/$cat/$name.md", "---\n$fm\n---\n\n$body\n");
}

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    config(['fyn.memory.semantic_path' => $this->corpus, 'fyn.memory.semantic_top_k' => 3]);
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->corpus));

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
```

- [ ] **Step 2: Run to verify failure**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Memory/SemanticRetrieverTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Write `SemanticRetriever`**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory;

use Illuminate\Support\Carbon;

/**
 * Sparse keyword retrieval over the semantic corpus. No embeddings — dense is
 * deferred until ~500 concurrent users (CSJ, 2026-06-01). Effective-date filter
 * is applied BEFORE ranking so a historical query returns historically-correct
 * facts.
 */
final class SemanticRetriever
{
    public function __construct(private readonly SemanticCorpusLoader $loader) {}

    /**
     * @param  list<string>|null  $categories  null = all categories
     * @return list<SemanticFact>  highest score first, capped at top_k
     */
    public function retrieve(string $query, Carbon $effectiveDate, ?array $categories = null): array
    {
        $terms = $this->tokenise($query);
        if ($terms === []) {
            return [];
        }

        $scored = [];
        foreach ($this->loader->all() as $fact) {
            if ($categories !== null && ! in_array($fact->category, $categories, true)) {
                continue;
            }
            if (! $fact->effectiveOn($effectiveDate)) {
                continue; // filter BEFORE ranking
            }

            $score = $this->score($terms, $fact->haystack());
            if ($score > 0) {
                $scored[] = ['score' => $score, 'fact' => $fact];
            }
        }

        usort($scored, fn (array $a, array $b): int => $b['score'] <=> $a['score'] ?: strcmp($a['fact']->factId, $b['fact']->factId));

        $topK = (int) config('fyn.memory.semantic_top_k', 4);

        return array_map(fn (array $r): SemanticFact => $r['fact'], array_slice($scored, 0, $topK));
    }

    /** Deterministic provenance id for the returned set (audit trail). */
    public function snapshotId(array $facts): string
    {
        $pairs = array_map(fn (SemanticFact $f): string => $f->factId.'@'.$f->version, $facts);
        sort($pairs);

        return hash('sha256', implode(',', $pairs));
    }

    /** @return list<string> */
    private function tokenise(string $text): array
    {
        preg_match_all('/[a-z0-9]{3,}/', mb_strtolower($text), $m);

        return array_values(array_unique($m[0]));
    }

    /** @param list<string> $terms */
    private function score(array $terms, string $haystack): int
    {
        $score = 0;
        foreach ($terms as $term) {
            $score += substr_count($haystack, $term);
        }

        return $score;
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Memory/SemanticRetrieverTest.php`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Memory/SemanticRetriever.php tests/Unit/Services/AI/Memory/SemanticRetrieverTest.php
git commit -m "feat(coala): SemanticRetriever — sparse, effective-date-filtered (Phase 1)"
```

---

### Task 5: `fyn:semantic:reindex` (validate + cache, no embeddings)

**Files:**
- Create: `app/Console/Commands/FynSemanticReindex.php`
- Modify: `config/fyn.php` (add `memory.semantic_index`, `memory.semantic_top_k`)
- Test: `tests/Feature/Console/FynSemanticReindexTest.php`

- [ ] **Step 1: Add config keys**

In `config/fyn.php`, inside the `memory` array (after `semantic_path`):

```php
            'semantic_index' => storage_path('app/memory/semantic/index.json'),
            'semantic_top_k' => (int) env('FYN_SEMANTIC_TOP_K', 4),
```

- [ ] **Step 2: Write the failing test**

```php
<?php

declare(strict_types=1);

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    $this->index = sys_get_temp_dir().'/idx-'.uniqid().'.json';
    config(['fyn.memory.semantic_path' => $this->corpus, 'fyn.memory.semantic_index' => $this->index]);
    @mkdir("$this->corpus/fca", 0777, true);
    file_put_contents("$this->corpus/fca/a.md", "---\nfact_id: fca-a\ncategory: fca\ntitle: A\nsource: COBS\nversion: 1\nvalid_from: 2024-04-06\n---\n\nBody.\n");
});

afterEach(function (): void {
    \Illuminate\Support\Facades\File::deleteDirectory($this->corpus);
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
```

- [ ] **Step 3: Run to verify failure**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Feature/Console/FynSemanticReindexTest.php`
Expected: FAIL — command `fyn:semantic:reindex` not found

- [ ] **Step 4: Write the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\Memory\SemanticCorpusLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Validate the semantic corpus and write a cached index. Sparse-only — no
 * embeddings (deferred until ~500 concurrent users, CSJ 2026-06-01). Runs at
 * deploy time and on demand; fail-closed (non-zero exit, no partial index).
 */
final class FynSemanticReindex extends Command
{
    protected $signature = 'fyn:semantic:reindex';

    protected $description = 'Validate the Fyn semantic corpus and write the cached index (sparse, no embeddings).';

    public function handle(SemanticCorpusLoader $loader): int
    {
        try {
            $facts = $loader->all();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $index = ['count' => count($facts), 'facts' => []];
        foreach ($facts as $id => $fact) {
            $index['facts'][$id] = [
                'category' => $fact->category,
                'title' => $fact->title,
                'version' => $fact->version,
                'valid_from' => $fact->validFrom?->toDateString(),
                'valid_to' => $fact->validTo?->toDateString(),
            ];
        }

        $path = (string) config('fyn.memory.semantic_index');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Semantic corpus reindexed: {$index['count']} facts → {$path}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run to verify pass**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Feature/Console/FynSemanticReindexTest.php`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/FynSemanticReindex.php config/fyn.php tests/Feature/Console/FynSemanticReindexTest.php
git commit -m "feat(coala): fyn:semantic:reindex validate+cache command (Phase 1)"
```

---

### Task 6: Product corpus generator (`tax_product_reference` → `.md`)

**Files:**
- Create: `app/Console/Commands/FynSemanticGenerateProducts.php`
- Test: `tests/Feature/Console/FynSemanticGenerateProductsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\TaxProductReference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    config(['fyn.memory.semantic_path' => $this->corpus]);
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->corpus));

it('writes one product .md per active reference row', function (): void {
    TaxProductReference::create([
        'product_category' => 'investment', 'product_type' => 'isa', 'tax_aspect' => 'cgt',
        'title' => 'ISA capital gains', 'summary' => 'Gains inside an ISA are tax-free.',
        'status' => 'advantage', 'display_order' => 1, 'is_active' => true,
    ]);

    $this->artisan('fyn:semantic:generate-products')->assertExitCode(0);

    $files = glob("$this->corpus/product/*.md");
    expect($files)->toHaveCount(1);
    $body = file_get_contents($files[0]);
    expect($body)->toContain('category: product')
        ->and($body)->toContain('fact_id: product-investment-isa-cgt')
        ->and($body)->toContain('Gains inside an ISA are tax-free.');
});

it('regenerates idempotently (clears prior product files first)', function (): void {
    TaxProductReference::create(['product_category' => 'savings', 'product_type' => 'easy_access', 'tax_aspect' => 'income_tax', 'title' => 'PSA', 'summary' => 'Personal savings allowance applies.', 'status' => 'neutral', 'display_order' => 1, 'is_active' => true]);
    file_put_contents("$this->corpus/product/stale.md", '---\nfact_id: stale\n---\n');
    @mkdir("$this->corpus/product", 0777, true);

    $this->artisan('fyn:semantic:generate-products')->assertExitCode(0);

    expect(file_exists("$this->corpus/product/stale.md"))->toBeFalse()
        ->and(glob("$this->corpus/product/*.md"))->toHaveCount(1);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Feature/Console/FynSemanticGenerateProductsTest.php`
Expected: FAIL — command not found

- [ ] **Step 3: Write the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TaxProductReference;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

/**
 * Generate the `product` semantic corpus from tax_product_reference rows. One
 * .md per active row, fact_id = product-{category}-{type}-{aspect}. Idempotent:
 * clears existing product/*.md first so a re-run reflects the table exactly.
 * Narrative only — no numeric tax values (Rule #3).
 */
final class FynSemanticGenerateProducts extends Command
{
    protected $signature = 'fyn:semantic:generate-products';

    protected $description = 'Generate the product semantic corpus from tax_product_reference.';

    public function handle(): int
    {
        $dir = rtrim((string) config('fyn.memory.semantic_path'), '/').'/product';
        File::ensureDirectoryExists($dir);

        foreach (File::glob($dir.'/*.md') ?: [] as $stale) {
            File::delete($stale);
        }

        $rows = TaxProductReference::query()->where('is_active', true)->orderBy('display_order')->get();
        $count = 0;

        foreach ($rows as $row) {
            $factId = 'product-'.implode('-', array_map(
                static fn (string $p): string => Str::slug((string) $p),
                [$row->product_category, $row->product_type, $row->tax_aspect],
            ));

            $meta = [
                'fact_id' => $factId,
                'category' => 'product',
                'title' => (string) $row->title,
                'source' => 'tax_product_reference#'.$row->id,
                'version' => 1,
            ];

            File::put(
                $dir.'/'.$factId.'.md',
                "---\n".Yaml::dump($meta, 2, 2)."---\n\n".trim((string) $row->summary)."\n",
            );
            $count++;
        }

        $this->info("Generated {$count} product facts → {$dir}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run to verify pass**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Feature/Console/FynSemanticGenerateProductsTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Generate the real corpus + commit**

```bash
php artisan fyn:semantic:generate-products
git add app/Console/Commands/FynSemanticGenerateProducts.php tests/Feature/Console/FynSemanticGenerateProductsTest.php fyn-memory/semantic/product
git commit -m "feat(coala): product semantic corpus generator + generated corpus (Phase 1)"
```

---

### Task 7: Wire the additive `<knowledge>` block into `FynContextAssembler`

**Files:**
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php:34` (ctor), `:73` (after `<remembered>`)
- Test: `tests/Unit/Services/AI/Fyn/FynContextAssemblerKnowledgeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/sem-'.uniqid();
    config(['fyn.memory.semantic_path' => $this->corpus, 'fyn.memory.semantic_top_k' => 4]);
    @mkdir("$this->corpus/fca", 0777, true);
    file_put_contents("$this->corpus/fca/dbtransfer.md", "---\nfact_id: fca-db-transfer\ncategory: fca\ntitle: Defined benefit transfer\nsource: COBS 19\nversion: 1\nvalid_from: 2020-01-01\n---\n\nA defined benefit pension transfer almost always needs regulated advice.\n");
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->corpus));

it('emits a <knowledge> block when the corpus matches the user message', function (): void {
    $user = User::factory()->create();
    $ctx = new FynTurnContext(user: $user, message: 'Should I do a defined benefit pension transfer?', conversation: null, classification: null, currentRoute: '/dashboard');

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<knowledge>')
        ->and($out)->toContain('Defined benefit transfer')
        ->and($out)->toContain('almost always needs regulated advice');
});

it('omits <knowledge> when nothing matches', function (): void {
    $user = User::factory()->create();
    $ctx = new FynTurnContext(user: $user, message: 'hello there', conversation: null, classification: null, currentRoute: '/dashboard');

    expect(app(FynContextAssembler::class)->build($ctx))->not->toContain('<knowledge>');
});
```

> Note: confirm the real `FynTurnContext` constructor signature before running (`grep -n "function __construct" app/Services/AI/Fyn/FynTurnContext.php`) and match the named args. Adjust the `new FynTurnContext(...)` calls to the actual signature.

- [ ] **Step 2: Run to verify failure**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextAssemblerKnowledgeTest.php`
Expected: FAIL — no `<knowledge>` in output (and a ctor arg error until Step 3)

- [ ] **Step 3: Inject the retriever + emit the block**

In `FynContextAssembler.php` constructor (line ~29-35), add the dependency:

```php
        private readonly FynMemoryStore $memoryStore,
        private readonly \App\Services\AI\Memory\SemanticRetriever $semantic,
    ) {}
```

Immediately after the `<remembered>` block (after line 73), add:

```php
        // CoALA Phase 1 — semantic knowledge corpus (additive; the static prompt's
        // compliance backbone is untouched). Sparse retrieval over the current
        // user message; effective-dated to today. Empty until the corpus is authored.
        $knowledgeFacts = $this->semantic->retrieve($ctx->message, \Illuminate\Support\Carbon::now());
        if ($knowledgeFacts !== []) {
            $blocks = array_map(
                static fn (\App\Services\AI\Memory\SemanticFact $f): string => "### {$f->title} (source: {$f->source})\n{$f->body}",
                $knowledgeFacts,
            );
            $lines[] = "<knowledge>\n".implode("\n\n", $blocks)."\n</knowledge>";
        }
```

- [ ] **Step 4: Run to verify pass**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextAssemblerKnowledgeTest.php`
Expected: PASS (2 tests)

- [ ] **Step 5: Run the broader Fyn suite for no regression**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Fyn`
Expected: PASS (existing assembler tests still green — the block is additive)

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Fyn/FynContextAssembler.php tests/Unit/Services/AI/Fyn/FynContextAssemblerKnowledgeTest.php
git commit -m "feat(coala): inject additive <knowledge> block from semantic memory (Phase 1)"
```

---

### Task 8: Corpus-hygiene guard tests (Rule #3 + frontmatter)

**Files:**
- Create: `tests/Unit/Services/AI/SemanticCorpusContentTest.php`

- [ ] **Step 1: Write the guard tests (run against the REAL corpus)**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\SemanticCorpusLoader;

it('loads the real corpus without throwing (fail-closed validation passes)', function (): void {
    // Uses the real config('fyn.memory.semantic_path'); proves the shipped corpus is valid.
    expect(fn () => app(SemanticCorpusLoader::class)->all())->not->toThrow(Throwable::class);
});

it('has no hardcoded currency figures in tax-category facts (Rule #3)', function (): void {
    $facts = app(SemanticCorpusLoader::class)->all();
    foreach ($facts as $fact) {
        if ($fact->category !== 'tax') {
            continue;
        }
        expect($fact->body)->not->toMatch('/£\s?\d/', "tax fact {$fact->factId} contains a £ figure — use TaxConfigService narrative, not numbers");
    }
});
```

- [ ] **Step 2: Run to verify pass**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/SemanticCorpusContentTest.php`
Expected: PASS (2 tests; the real corpus is valid after Tasks 1 + 6)

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/AI/SemanticCorpusContentTest.php
git commit -m "test(coala): semantic corpus hygiene guards — Rule #3 + valid frontmatter (Phase 1)"
```

---

### Task 9: Pin the static prompt's byte-invariance (Decision A guard)

**Files:**
- Create: `tests/Architecture/FynStaticPromptByteInvarianceTest.php`

- [ ] **Step 1: Capture the current hash**

Run: `php -r "require 'vendor/autoload.php'; \$a=require 'bootstrap/app.php'; echo hash('sha256', App\Services\AI\Fyn\FynSystemPrompt::text());"`
Copy the printed hash into the test below as `$EXPECTED`.

- [ ] **Step 2: Write the guard test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynSystemPrompt;

it('keeps FynSystemPrompt::text() byte-identical (Phase 1 Decision A: additive, prefix-cache invariant)', function (): void {
    // If this fails, semantic-memory work changed the cached static prompt — it must NOT.
    // Knowledge belongs in the per-turn <knowledge> block, never the static prompt.
    $expected = '<PASTE_HASH_FROM_STEP_1>';

    expect(hash('sha256', FynSystemPrompt::text()))->toBe($expected);
});
```

- [ ] **Step 3: Run to verify pass**

Run: `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Architecture/FynStaticPromptByteInvarianceTest.php`
Expected: PASS (1 test)

- [ ] **Step 4: Commit**

```bash
git add tests/Architecture/FynStaticPromptByteInvarianceTest.php
git commit -m "test(coala): pin FynSystemPrompt byte-invariance (Phase 1 Decision A guard)"
```

---

### Task 10: Author starter FCA exemplars + deploy wiring (content + ops)

**Files:**
- Create: `fyn-memory/semantic/fca/*.md` (starter set, compliance-reviewed)
- Modify: deploy notes (list `fyn:semantic:reindex` as a deploy step)

- [ ] **Step 1: Author 5–10 FCA exemplar facts**

For each, one file under `fyn-memory/semantic/fca/`, frontmatter per the contract, body sourced from the existing FCA narrative in `app/Services/AI/Prompts/{ComplianceRules,FcaProcessInstructions,QueryKnowledge}.php`. **Copy depth, do not relocate the backbone.** Each `source:` must cite the handbook ref. **Route every fca body through compliance review before commit** (hedging language, no directive phrasing — mirror `<regulatory_compliance>` rules).

- [ ] **Step 2: Reindex + verify the corpus is valid**

Run: `php artisan fyn:semantic:reindex`
Expected: exit 0, `N facts` where N = product rows + fca exemplars.

- [ ] **Step 3: Add the deploy step**

In the deployment runbook (CLAUDE.md "Deploying to dev/production" finalise blocks), add after `php artisan optimize`:
`php artisan fyn:semantic:reindex` (regenerate the cached index; runs on both csjones.co and fynla.org).

- [ ] **Step 4: Commit**

```bash
git add fyn-memory/semantic/fca CLAUDE.md
git commit -m "feat(coala): starter FCA semantic exemplars + deploy reindex step (Phase 1)"
```

---

## Self-Review

**Spec coverage (plan §704–721 "Phase 1" "Done when"):**
- Corpus stood up under category dirs → Task 1. ✅ (location reconciled to `fyn-memory/semantic/` per Decision 4)
- `.md` loader with frontmatter validation, fail-closed on duplicate/malformed → Task 3. ✅
- `fyn:semantic:reindex` → Task 5. ✅ (validate+cache; embeddings intentionally omitted per Decision 2)
- `SemanticMemory::retrieve(query, effective_date, categories)` with effective-date filter before ranking → Task 4. ✅ (sparse only per Decision 2; dense deferred)
- Seed `fca` (Task 10) + `product` (Task 6); `house_view`/`tax`/`allowance` dirs stood up empty (Task 1). ✅
- `tax` retrieval = narrative; numbers via `TaxConfigService`; guarded → Task 8. ✅
- Wire per-turn assembler to retrieve → Task 7. ✅
- Static `FynSystemPrompt::text()` byte-identical → Task 9 (pinned). ✅ (Decision A)
- `semantic_snapshot_id` per turn (SHA-256 over sorted `(fact_id, version)`) → `SemanticRetriever::snapshotId()` Task 4; **persisting it onto the episode SQL row is a Phase 2 column** (`ai_messages`/`ai_episodes`) — out of scope here, noted as the Phase 1→2 seam.
- Dense/embeddings "Done when" clauses → **explicitly descoped** (Decision 2); not a gap.

**Placeholder scan:** No "TBD/handle edge cases/similar to Task N" — every code step has full code. ✅

**Type consistency:** `SemanticFact` (factId/category/title/source/version/validFrom/validTo/body) used identically in Tasks 2/3/4/7. `SemanticCorpusLoader::all()` returns `array<string,SemanticFact>` consumed by `SemanticRetriever`. `retrieve()`/`snapshotId()` signatures match across Tasks 4/7. Config keys `semantic_path`/`semantic_index`/`semantic_top_k` consistent across Tasks 3/4/5. ✅

**Known integration caveat to verify at execution:** the exact `FynTurnContext` constructor signature (Task 7 test) — confirm with grep before running; adjust named args to match.

## Phase 1 → downstream seams

- **Phase 2** persists `semantic_snapshot_id` onto the episode row (the column lands there); Phase 1 only computes it.
- **Phase 6 `learn`** writes *proposed* facts into this corpus (human-reviewed); the corpus + loader this phase builds is its write target.
- **A/B collapse review** has its Phase-1 precondition satisfied once this ships.
