# CoALA Phase 4a — Procedural Corpus Substrate Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a consumer-free procedural-memory corpus substrate — a loader that parses, validates, caches, and hot-reloads `fyn-memory/procedural/{kind}/{module}/*.md`, exposes a typed read model, and ships a fail-closed deploy-gate command — with zero change to live prompts or the tool catalogue.

**Architecture:** Mirrors the Phase 1 semantic corpus (`app/Services/AI/Memory/SemanticCorpusLoader` + `SemanticFact`) and the pointer registry. An immutable `Procedure` VO and `ProceduralCorpus` read model carry the data; `ProceduralCorpusLoader` (bound `singleton`) does the I/O with a cross-request Laravel-cache layer keyed by a directory signature (max mtime + file count) and a 60s re-stat throttle, atomic swap on success, and keep-last-good on validation failure. Runtime never throws; the `fyn:procedural:validate` command is the only hard-fail surface.

**Tech Stack:** PHP 8.2, Laravel 10, Symfony YAML, Pest. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-06-02-coala-phase-4a-procedural-substrate-design.md`

---

## File Structure

**Create:**
- `app/Services/AI/Memory/Procedural/Procedure.php` — immutable value object (one procedure version).
- `app/Services/AI/Memory/Procedural/ProceduralCorpus.php` — immutable loaded collection + typed read surface.
- `app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php` — I/O, parse, validate, cache, hot-reload.
- `app/Console/Commands/FynProceduralValidate.php` — `fyn:procedural:validate` deploy gate.
- `fyn-memory/procedural/README.md` — documents the convention + frontmatter schema.
- `tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php`
- `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php`
- `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php`
- `tests/Feature/Console/FynProceduralValidateTest.php`

**Modify:**
- `config/fyn.php` — add `memory.procedural_reload_interval`.
- `app/Providers/AppServiceProvider.php` — `singleton(ProceduralCorpusLoader::class)`.
- `CLAUDE.md` — append `fyn:procedural:validate` to the two deploy command chains (operational line; flag to CSJ).

---

## Task 1: `Procedure` value object

**Files:**
- Create: `app/Services/AI/Memory/Procedural/Procedure.php`
- Test: `tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\Procedure;
use Illuminate\Support\Carbon;

it('exposes its frontmatter fields', function (): void {
    $p = new Procedure(
        procedureId: 'retirement.tool.create_dc_pension',
        kind: 'tool_schema',
        module: 'retirement',
        version: 2,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: '```json\n{}\n```',
    );

    expect($p->procedureId)->toBe('retirement.tool.create_dc_pension')
        ->and($p->kind)->toBe('tool_schema')
        ->and($p->module)->toBe('retirement')
        ->and($p->version)->toBe(2)
        ->and($p->active)->toBeTrue()
        ->and($p->effectiveTo)->toBeNull();
});

it('is in force on or after effective_from with no end', function (): void {
    $p = new Procedure('id', 'workflow', 'global', 1, true, Carbon::parse('2026-06-01'), null, 'body');

    expect($p->effectiveOn(Carbon::parse('2026-05-31')))->toBeFalse()
        ->and($p->effectiveOn(Carbon::parse('2026-06-01')))->toBeTrue()
        ->and($p->effectiveOn(Carbon::parse('2030-01-01')))->toBeTrue();
});

it('respects effective_to when set', function (): void {
    $p = new Procedure('id', 'workflow', 'global', 1, true, Carbon::parse('2026-06-01'), Carbon::parse('2026-12-31'), 'body');

    expect($p->effectiveOn(Carbon::parse('2026-12-31')))->toBeTrue()
        ->and($p->effectiveOn(Carbon::parse('2027-01-01')))->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php`
Expected: FAIL — `Class "App\Services\AI\Memory\Procedural\Procedure" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

use Illuminate\Support\Carbon;

/**
 * One immutable procedural-memory procedure version (frontmatter + body).
 * Mirrors SemanticFact. Effective-dating is answered here so the corpus can
 * resolve the active version for a given date.
 */
final class Procedure
{
    public function __construct(
        public readonly string $procedureId,
        public readonly string $kind,
        public readonly string $module,
        public readonly int $version,
        public readonly bool $active,
        public readonly Carbon $effectiveFrom,
        public readonly ?Carbon $effectiveTo,
        public readonly string $body,
    ) {}

    /** True when this version is in force on $on. */
    public function effectiveOn(Carbon $on): bool
    {
        if ($on->lt($this->effectiveFrom)) {
            return false;
        }
        if ($this->effectiveTo !== null && $on->gt($this->effectiveTo)) {
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Memory/Procedural/Procedure.php tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php
git commit -m "feat(coala): Procedure VO for Phase 4a procedural substrate"
```

---

## Task 2: `ProceduralCorpus` read model

**Files:**
- Create: `app/Services/AI/Memory/Procedural/ProceduralCorpus.php`
- Test: `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Memory\Procedural\ProceduralCorpus;
use Illuminate\Support\Carbon;

function proc(string $id, int $version, bool $active, string $from, ?string $to = null, string $kind = 'tool_schema', string $module = 'retirement'): Procedure
{
    return new Procedure(
        procedureId: $id,
        kind: $kind,
        module: $module,
        version: $version,
        active: $active,
        effectiveFrom: Carbon::parse($from),
        effectiveTo: $to !== null ? Carbon::parse($to) : null,
        body: 'body',
    );
}

it('returns all procedures', function (): void {
    $corpus = new ProceduralCorpus([proc('a', 1, true, '2026-01-01'), proc('b', 1, true, '2026-01-01')]);
    expect($corpus->all())->toHaveCount(2);
});

it('filters by kind', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, true, '2026-01-01', null, 'tool_schema'),
        proc('b', 1, true, '2026-01-01', null, 'workflow'),
    ]);
    expect($corpus->ofKind('workflow'))->toHaveCount(1)
        ->and($corpus->ofKind('workflow')[0]->procedureId)->toBe('b');
});

it('lists all versions of a procedure ascending', function (): void {
    $corpus = new ProceduralCorpus([proc('a', 2, false, '2026-01-01'), proc('a', 1, false, '2025-01-01')]);
    $versions = $corpus->versions('a');
    expect($versions)->toHaveCount(2)
        ->and($versions[0]->version)->toBe(1)
        ->and($versions[1]->version)->toBe(2);
});

it('resolves the active version effective on a date', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, false, '2025-01-01', '2025-12-31'),
        proc('a', 2, true, '2026-01-01'),
    ]);
    expect($corpus->active('a', Carbon::parse('2026-06-02'))?->version)->toBe(2);
});

it('returns null when no active version is effective on the date', function (): void {
    $corpus = new ProceduralCorpus([proc('a', 2, true, '2027-01-01')]);
    expect($corpus->active('a', Carbon::parse('2026-06-02')))->toBeNull();
});

it('returns the highest-version active when several qualify', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, true, '2025-01-01'),
        proc('a', 3, true, '2026-01-01'),
    ]);
    expect($corpus->active('a', Carbon::parse('2026-06-02'))?->version)->toBe(3);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php`
Expected: FAIL — `Class "App\Services\AI\Memory\Procedural\ProceduralCorpus" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

use Illuminate\Support\Carbon;

/**
 * Immutable loaded procedural corpus + the typed read surface that Phase 4b–4d
 * consumers use. Pure data, no I/O.
 */
final class ProceduralCorpus
{
    /** @param list<Procedure> $procedures */
    public function __construct(private readonly array $procedures) {}

    /** @return list<Procedure> */
    public function all(): array
    {
        return array_values($this->procedures);
    }

    /** @return list<Procedure> */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter($this->procedures, fn (Procedure $p): bool => $p->kind === $kind));
    }

    /** @return list<Procedure> all versions of one procedure, ascending by version */
    public function versions(string $procedureId): array
    {
        $matches = array_values(array_filter(
            $this->procedures,
            fn (Procedure $p): bool => $p->procedureId === $procedureId,
        ));
        usort($matches, fn (Procedure $a, Procedure $b): int => $a->version <=> $b->version);

        return $matches;
    }

    /** The active version effective on $asOf (default now); highest version wins ties. */
    public function active(string $procedureId, ?Carbon $asOf = null): ?Procedure
    {
        $asOf ??= Carbon::now();
        $candidates = array_values(array_filter(
            $this->procedures,
            fn (Procedure $p): bool => $p->procedureId === $procedureId && $p->active && $p->effectiveOn($asOf),
        ));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn (Procedure $a, Procedure $b): int => $b->version <=> $a->version);

        return $candidates[0];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php`
Expected: PASS (6 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Memory/Procedural/ProceduralCorpus.php tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php
git commit -m "feat(coala): ProceduralCorpus read model (all/ofKind/versions/active)"
```

---

## Task 3: `ProceduralCorpusLoader` — parse happy path + `loadStrict()`

**Files:**
- Create: `app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php`
- Test: `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php`

This task establishes the temp-dir fixture harness and the happy-path parse. Validation (Task 4) and caching/hot-reload (Task 5) extend the same file.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php`
Expected: FAIL — `Class "App\Services\AI\Memory\Procedural\ProceduralCorpusLoader" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Loads + validates the procedural-memory corpus
 * (fyn-memory/procedural/{kind}/{module}/*.md). Mirrors SemanticCorpusLoader.
 *
 * Runtime entry is load(): it degrades to the last-good (or empty) corpus and
 * never throws, so a malformed corpus cannot break a chat turn. loadStrict()
 * throws on any validation error and backs the fyn:procedural:validate deploy
 * gate. Cross-request caching is keyed by a directory signature with a re-stat
 * throttle; the swap is atomic because ProceduralCorpus is immutable.
 */
final class ProceduralCorpusLoader
{
    private const KINDS = ['system_prompt_overlay', 'workflow', 'tool_schema', 'fca_block'];

    private const SKIP = ['_TEMPLATE.md', 'README.md'];

    private const CACHE_KEY = 'fyn:procedural:corpus';

    private const SIG_KEY = 'fyn:procedural:corpus:sig';

    private ?ProceduralCorpus $corpus = null;

    private ?string $signature = null;

    private float $lastStatCheck = 0.0;

    /** Strict parse — throws on any validation error. Used only by the deploy gate. */
    public function loadStrict(): ProceduralCorpus
    {
        return $this->parse();
    }

    /** Parse + validate the whole corpus. Throws RuntimeException on the first error. */
    private function parse(): ProceduralCorpus
    {
        $root = (string) config('fyn.memory.procedural_path');
        if (! File::isDirectory($root)) {
            return new ProceduralCorpus([]);
        }

        $procedures = [];
        $seenVersion = [];   // "id@version" => path
        $activeById = [];    // id => path

        foreach (self::KINDS as $kind) {
            $kindDir = $root.'/'.$kind;
            if (! File::isDirectory($kindDir)) {
                continue;
            }

            foreach (File::allFiles($kindDir) as $file) {
                if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                    continue;
                }

                $rel = str_replace('\\', '/', $file->getRelativePath());
                $moduleFromPath = $rel === '' ? '' : explode('/', $rel)[0];

                $proc = $this->parseAndValidate($file->getPathname(), File::get($file->getPathname()), $kind, $moduleFromPath);

                $vk = $proc->procedureId.'@'.$proc->version;
                if (isset($seenVersion[$vk])) {
                    throw new RuntimeException("Procedural corpus: duplicate {$vk} ({$file->getPathname()}).");
                }
                $seenVersion[$vk] = $file->getPathname();

                if ($proc->active) {
                    if (isset($activeById[$proc->procedureId])) {
                        throw new RuntimeException("Procedural corpus: multiple active versions for '{$proc->procedureId}' ({$file->getPathname()}).");
                    }
                    $activeById[$proc->procedureId] = $file->getPathname();
                }

                $procedures[] = $proc;
            }
        }

        return new ProceduralCorpus($procedures);
    }

    private function parseAndValidate(string $path, string $contents, string $kindFromPath, string $moduleFromPath): Procedure
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) !== 1) {
            throw new RuntimeException("Procedural corpus: missing frontmatter ({$path}).");
        }

        $meta = Yaml::parse($m[1]);
        if (! is_array($meta)) {
            throw new RuntimeException("Procedural corpus: frontmatter must be a YAML mapping ({$path}).");
        }

        $body = trim($m[2]);
        if ($body === '') {
            throw new RuntimeException("Procedural corpus: empty body ({$path}).");
        }

        $require = function (string $key) use ($meta, $path): mixed {
            if (! array_key_exists($key, $meta) || $meta[$key] === null || $meta[$key] === '') {
                throw new RuntimeException("Procedural corpus: missing '{$key}' ({$path}).");
            }

            return $meta[$key];
        };

        $procedureId = (string) $require('procedure_id');

        $kind = (string) $require('kind');
        if (! in_array($kind, self::KINDS, true)) {
            throw new RuntimeException("Procedural corpus: unknown kind '{$kind}' ({$path}).");
        }
        if ($kind !== $kindFromPath) {
            throw new RuntimeException("Procedural corpus: frontmatter kind '{$kind}' disagrees with path kind '{$kindFromPath}' ({$path}).");
        }

        $module = (string) $require('module');
        if ($moduleFromPath === '') {
            throw new RuntimeException("Procedural corpus: file must live under {$kind}/{module}/ ({$path}).");
        }
        if ($module !== $moduleFromPath) {
            throw new RuntimeException("Procedural corpus: frontmatter module '{$module}' disagrees with path module '{$moduleFromPath}' ({$path}).");
        }

        $version = (int) $require('version');
        if ($version < 1) {
            throw new RuntimeException("Procedural corpus: version must be >= 1 ({$path}).");
        }

        // active is mandatory and must be a real boolean (false is valid, not "missing").
        if (! array_key_exists('active', $meta) || ! is_bool($meta['active'])) {
            throw new RuntimeException("Procedural corpus: 'active' must be a boolean ({$path}).");
        }
        $active = $meta['active'];

        $effectiveFrom = $this->parseDate($require('effective_from'))->startOfDay();
        $effectiveTo = isset($meta['effective_to']) && $meta['effective_to'] !== null
            ? $this->parseDate($meta['effective_to'])->endOfDay()
            : null;

        return new Procedure(
            procedureId: $procedureId,
            kind: $kind,
            module: $module,
            version: $version,
            active: $active,
            effectiveFrom: $effectiveFrom,
            effectiveTo: $effectiveTo,
            body: $body,
        );
    }

    /** Parse a date that may be an ISO string or a Symfony-YAML-coerced timestamp int. */
    private function parseDate(mixed $value): Carbon
    {
        if ($value === '' || $value === null) {
            throw new RuntimeException('Procedural corpus: date value must not be empty.');
        }
        if (is_int($value)) {
            return Carbon::createFromTimestamp($value);
        }

        return Carbon::parse((string) $value);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php
git commit -m "feat(coala): ProceduralCorpusLoader parse happy path + loadStrict"
```

---

## Task 4: `ProceduralCorpusLoader` — validation rules

**Files:**
- Modify: `app/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php` (add tests)
- Note: validation logic already lives in `parseAndValidate`/`parse` from Task 3 — this task proves each rule with a test. If a test fails, the rule is missing and must be added to the loader.

- [ ] **Step 1: Write the failing tests** (append to the test file from Task 3, before the final closing of the file)

```php
it('rejects a missing mandatory field', function (): void {
    $fm = validFrontmatter();
    unset($fm['version']);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', $fm);

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, "missing 'version'");
});

it('rejects an unknown kind in frontmatter', function (): void {
    // Path kind is valid (tool_schema) but frontmatter claims a bad kind.
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(['kind' => 'nonsense']));

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, "unknown kind 'nonsense'");
});

it('rejects a frontmatter kind that disagrees with the path', function (): void {
    // File is under workflow/ but frontmatter says tool_schema.
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
```

Add the `RuntimeException` import at the top of the test file:

```php
use RuntimeException;
```

- [ ] **Step 2: Run tests**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php`
Expected: PASS (12 passed). All rules were implemented in Task 3, so these tests pass without code changes. If any FAILS, add the corresponding check to `parseAndValidate`/`parse` and re-run until green.

- [ ] **Step 3: Commit**

```bash
git add tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php
git commit -m "test(coala): cover all ProceduralCorpusLoader validation rules"
```

---

## Task 5: `ProceduralCorpusLoader` — caching, hot-reload, `load()` degrade

**Files:**
- Modify: `app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php` (add `load()`, `signature()`)
- Modify: `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php` (add tests)

- [ ] **Step 1: Write the failing tests** (append to the loader test file)

```php
it('load() returns the parsed corpus', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    expect(app(ProceduralCorpusLoader::class)->load()->all())->toHaveCount(1);
});

it('load() serves stale within the reload interval', function (): void {
    config(['fyn.memory.procedural_reload_interval' => 3600]);
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter());
    $loader = app(ProceduralCorpusLoader::class);
    expect($loader->load()->all())->toHaveCount(1);

    // add a second procedure; within the 3600s window the loader must NOT re-stat.
    writeProc($this->corpus, 'workflow', 'onboarding', 'y', validFrontmatter(['procedure_id' => 'onboarding.flow.main', 'kind' => 'workflow', 'module' => 'onboarding']));
    expect($loader->load()->all())->toHaveCount(1); // still stale
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

    // Corrupt the corpus (duplicate active) — signature changes, parse throws.
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
    expect(\Illuminate\Support\Facades\Cache::has('fyn:procedural:corpus'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Cache::has('fyn:procedural:corpus:sig'))->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php --filter="load"`
Expected: FAIL — `Call to undefined method ...::load()`.

- [ ] **Step 3: Add `load()` and `signature()` to the loader**

Insert these two methods into `ProceduralCorpusLoader` immediately above `loadStrict()`:

```php
    /**
     * Runtime entry. Serves the in-memory corpus within the reload interval;
     * otherwise re-stats the corpus dir and reloads only when the signature
     * changed. Degrades to last-good (or empty) and never throws.
     */
    public function load(): ProceduralCorpus
    {
        $now = microtime(true);
        $interval = (int) config('fyn.memory.procedural_reload_interval', 60);

        if ($this->corpus !== null && ($now - $this->lastStatCheck) < $interval) {
            return $this->corpus;
        }
        $this->lastStatCheck = $now;

        $sig = $this->signature();

        if ($this->corpus !== null && $this->signature === $sig) {
            return $this->corpus;
        }

        // Cold instance: try the cross-request cache before re-parsing.
        if ($this->corpus === null) {
            $cached = Cache::get(self::CACHE_KEY);
            if ($cached instanceof ProceduralCorpus && Cache::get(self::SIG_KEY) === $sig) {
                $this->signature = $sig;

                return $this->corpus = $cached;
            }
        }

        try {
            $fresh = $this->parse();
        } catch (Throwable $e) {
            report($e);

            return $this->corpus ?? ($this->corpus = new ProceduralCorpus([]));
        }

        // Atomic swap — ProceduralCorpus is immutable, so replacing the reference is safe.
        $this->corpus = $fresh;
        $this->signature = $sig;
        Cache::put(self::CACHE_KEY, $fresh);
        Cache::put(self::SIG_KEY, $sig);

        return $this->corpus;
    }

    /** Signature of the corpus on disk: max mtime over .md files + file count. */
    private function signature(): string
    {
        $root = (string) config('fyn.memory.procedural_path');
        if (! File::isDirectory($root)) {
            return '0:0';
        }

        $maxMtime = 0;
        $count = 0;
        foreach (self::KINDS as $kind) {
            $kindDir = $root.'/'.$kind;
            if (! File::isDirectory($kindDir)) {
                continue;
            }
            foreach (File::allFiles($kindDir) as $file) {
                if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                    continue;
                }
                $maxMtime = max($maxMtime, $file->getMTime());
                $count++;
            }
        }

        return $maxMtime.':'.$count;
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php`
Expected: PASS (18 passed).

Note on the stale-within-interval test: two `load()` calls happen in the same test (same singleton instance), so `lastStatCheck` is set on the first call and the second call (well within 3600s) short-circuits to the in-memory corpus — proving the throttle. The zero-interval test forces a re-stat every call.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php
git commit -m "feat(coala): ProceduralCorpusLoader cache + 60s mtime hot-reload + keep-last-good"
```

---

## Task 6: Config + container binding

**Files:**
- Modify: `config/fyn.php` (memory block)
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the reload-interval config**

In `config/fyn.php`, inside the `'memory' => [ ... ]` array, add after the `pointers_path` line:

```php
        'procedural_reload_interval' => (int) env('FYN_PROCEDURAL_RELOAD_INTERVAL', 60),
```

- [ ] **Step 2: Bind the loader as a singleton**

In `app/Providers/AppServiceProvider.php`, add the import near the other AI memory imports:

```php
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
```

And in `register()`, next to the other `scoped`/`singleton` memory bindings (after the `FetchProvenanceCollector` / `SemanticSnapshotHolder` scoped bindings), add:

```php
        // Procedural corpus loader — singleton so the in-memory corpus + 60s
        // re-stat throttle persist within a request (and across requests under Octane).
        $this->app->singleton(ProceduralCorpusLoader::class);
```

- [ ] **Step 3: Verify nothing regressed**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ --filter="load"`
Expected: PASS (still 18 passed; the singleton binding is now explicit rather than auto-resolved).

- [ ] **Step 4: Commit**

```bash
git add config/fyn.php app/Providers/AppServiceProvider.php
git commit -m "feat(coala): config reload-interval + singleton binding for ProceduralCorpusLoader"
```

---

## Task 7: `fyn:procedural:validate` command

**Files:**
- Create: `app/Console/Commands/FynProceduralValidate.php`
- Test: `tests/Feature/Console/FynProceduralValidateTest.php`

- [ ] **Step 1: Write the failing test**

```php
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

    $this->artisan('fyn:procedural:validate')->assertExitCode(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Console/FynProceduralValidateTest.php`
Expected: FAIL — command `fyn:procedural:validate` is not defined.

- [ ] **Step 3: Write the command**

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Console\Command;
use Throwable;

/**
 * Validate the Fyn procedural corpus (fyn-memory/procedural/{kind}/{module}/*.md).
 * Fail-closed deploy gate: non-zero exit on any validation error, no partial
 * acceptance. Runtime serving (ProceduralCorpusLoader::load) degrades instead.
 */
final class FynProceduralValidate extends Command
{
    protected $signature = 'fyn:procedural:validate';

    protected $description = 'Validate the Fyn procedural corpus (fail-closed deploy gate).';

    public function handle(ProceduralCorpusLoader $loader): int
    {
        try {
            $corpus = $loader->loadStrict();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $all = $corpus->all();
        $kinds = array_unique(array_map(fn ($p) => $p->kind, $all));
        $modules = array_unique(array_map(fn ($p) => $p->module, $all));

        $this->info(sprintf(
            'Procedural corpus valid: %d procedure(s) across %d kind(s), %d module(s).',
            count($all),
            count($kinds),
            count($modules),
        ));

        foreach ($all as $p) {
            if ($p->active) {
                $this->line("  active: {$p->procedureId} v{$p->version} ({$p->kind}/{$p->module})");
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Console/FynProceduralValidateTest.php`
Expected: PASS (3 passed). Laravel auto-discovers commands in `app/Console/Commands`, so no manual registration is needed.

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/FynProceduralValidate.php tests/Feature/Console/FynProceduralValidateTest.php
git commit -m "feat(coala): fyn:procedural:validate deploy-gate command"
```

---

## Task 8: Corpus README + deploy-pipeline wiring

**Files:**
- Create: `fyn-memory/procedural/README.md`
- Modify: `CLAUDE.md` (two deploy command chains)

- [ ] **Step 1: Write the corpus README**

Create `fyn-memory/procedural/README.md`:

````markdown
# Procedural memory corpus

CoALA Phase 4 procedural memory. Each `.md` file is one **version** of one
procedure. Files live at:

```
fyn-memory/procedural/{kind}/{module}/{name}.md
```

`pointers/` is a separate subsystem (the pointer registry) and is NOT part of
this corpus — the procedural loader ignores it.

## Kinds

| kind | purpose |
|------|---------|
| `system_prompt_overlay` | per-tier / per-module prompt overlays (Phase 4c) |
| `fca_block` | FCA / house-view narrative blocks (Phase 4c) |
| `tool_schema` | one LLM tool definition, JSON in a fenced block (Phase 4b) |
| `workflow` | onboarding state-machine transition tables (Phase 4d) |

## Frontmatter

```yaml
---
procedure_id: retirement.tool.create_dc_pension   # unique logical id
kind: tool_schema           # system_prompt_overlay | workflow | tool_schema | fca_block
module: retirement          # module slug, or 'global' — MUST match the path
version: 1                  # integer >= 1
active: true                # exactly ONE active version per procedure_id
effective_from: 2026-06-02  # date
# effective_to: 2027-04-05  # optional
---
<markdown body>
```

`module` and `kind` must match the file's directory. Validate locally with:

```bash
php artisan fyn:procedural:validate
```

The corpus is empty until Phase 4b–4d author content. The loader degrades to an
empty/last-good corpus at runtime and never breaks a chat turn; the validate
command is the only place that hard-fails.
````

- [ ] **Step 2: Verify the validate command passes against the real (empty) corpus**

Run: `php artisan fyn:procedural:validate`
Expected: exit 0, `Procedural corpus valid: 0 procedure(s) across 0 kind(s), 0 module(s).` (the README is skipped — it is not under a kind directory).

- [ ] **Step 3: Wire into both deploy pipelines**

> NOTE TO ENGINEER: `CLAUDE.md` is owned by CSJ. This is an operational deploy-command edit, not a rule change. If unsure, leave it and flag for CSJ.

In `CLAUDE.md`, in BOTH the "Deploying to dev" and "Deploying to production" finalise blocks, extend the reindex line:

Find:
```bash
php artisan fyn:semantic:reindex && php artisan fyn:pointers:reindex
```
Replace with:
```bash
php artisan fyn:semantic:reindex && php artisan fyn:pointers:reindex && php artisan fyn:procedural:validate
```

- [ ] **Step 4: Commit**

```bash
git add fyn-memory/procedural/README.md CLAUDE.md
git commit -m "docs(coala): procedural corpus README + deploy-gate wiring"
```

---

## Task 9: Full-suite regression + Pint

**Files:** none (verification only)

- [ ] **Step 1: Run the full new Procedural suite + the command test**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ tests/Feature/Console/FynProceduralValidateTest.php`
Expected: PASS (30 passed: Procedure 3, ProceduralCorpus 6, ProceduralCorpusLoader 18, FynProceduralValidate 3). Exact count may differ slightly; all must be green.

- [ ] **Step 2: Confirm no wider regression in the AI memory + audit surface**

Run: `./vendor/bin/pest tests/Unit/Services/AI/ tests/Feature/AI/`
Expected: PASS — no existing test touched (substrate has no consumers).

- [ ] **Step 3: Pint the changed files**

Run:
```bash
./vendor/bin/pint app/Services/AI/Memory/Procedural/ app/Console/Commands/FynProceduralValidate.php app/Providers/AppServiceProvider.php config/fyn.php tests/Unit/Services/AI/Memory/Procedural/ tests/Feature/Console/FynProceduralValidateTest.php
```
Expected: `PASS`. If Pint reformats and strips a freshly-added `use` import (a known quirk in this repo when an import lands before its first usage), re-add the import and re-run.

- [ ] **Step 4: Final commit (if Pint changed anything)**

```bash
git add -A
git commit -m "style(coala): pint Phase 4a procedural substrate"
```

---

## Done-when checklist (verify against the spec)

- [ ] `fyn-memory/procedural/{kind}/{module}/*.md` is parsed/validated by `ProceduralCorpusLoader`.
- [ ] `ProceduralCorpus` exposes `all()`, `ofKind()`, `versions()`, `active()`.
- [ ] Runtime `load()` degrades to last-good/empty and never throws; `loadStrict()` / `fyn:procedural:validate` hard-fails on any error.
- [ ] 60s mtime hot-reload + cross-request cache + atomic swap implemented and tested.
- [ ] `pointers/` and README/_TEMPLATE ignored; path/frontmatter agreement enforced.
- [ ] Deploy pipelines run `fyn:procedural:validate`.
- [ ] Full new suite green, Pint clean, zero change to live prompts or the tool catalogue.
