# CoALA Pointer Registry Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the pointer registry — the heart of procedural memory (v0.5) — that routes Fyn to live data sources at the moment of need (markdown routes, code executes), with both deterministic pre-fetch and LLM-tool trigger modes and lightweight provenance.

**Architecture:** A markdown pointer corpus (`fyn-memory/procedural/pointers/*.md`) is loaded fail-closed by `PointerRegistry`, which routes each pointer's `handler` string to a code-defined `FetchHandler` from a closed whitelist. A `FetchDispatcher` runs the handler and records provenance on `ai_messages.metadata`. Pre-fetch pointers are matched against the query and injected into an additive `<live_data>` block (sibling to Phase-1's `<knowledge>`); tool pointers register into Fyn's tool catalogue and route through the same dispatcher. Nothing in markdown executes.

**Tech Stack:** Laravel 10 / PHP 8.2, `Symfony\Component\Yaml`, Pest. No new Composer deps. Reuses the Phase-1 `SemanticCorpusLoader`/`SemanticRetriever` patterns.

**Spec:** `docs/superpowers/specs/2026-06-01-coala-pointer-registry-design.md`. **Base branch:** off `feat/coala-phase1-semantic-memory` (reuses its loader patterns); CoALA PRs target `coala`. Confirm base at execution time.

**Test DB convention:** prefix Pest with `DB_DATABASE=laravel_testing` (NEVER `php artisan --env=testing` — MEMORY.md). Tests point the registry at a temp dir via `config(['fyn.memory.pointers_path' => $tmp])`.

---

## File structure

**Create:**
- `app/Services/AI/Pointers/Pointer.php` — immutable VO (one pointer's routing).
- `app/Services/AI/Pointers/PointerRegistry.php` — loads/validates the pointer corpus, fail-closed; matches pre-fetch pointers by query.
- `app/Services/AI/Pointers/FetchContext.php` — `{User $user, string $query, array $params}`.
- `app/Services/AI/Pointers/FetchResult.php` — `{string $value, string $sourceLabel, string $sourceVersion, string $digest}`.
- `app/Services/AI/Pointers/FetchHandler.php` — the interface.
- `app/Services/AI/Pointers/FetchHandlerRegistry.php` — the closed whitelist (id → handler).
- `app/Services/AI/Pointers/FetchDispatcher.php` — run handler + record provenance.
- `app/Services/AI/Pointers/Handlers/TaxAllowanceHandler.php` — config archetype.
- `app/Services/AI/Pointers/Handlers/UserFinancialHandler.php` — model/builder archetype.
- `app/Services/AI/Pointers/Handlers/RecommendationHandler.php` — engine archetype.
- `app/Console/Commands/FynPointersReindex.php` — `fyn:pointers:reindex` validate command.
- `fyn-memory/procedural/pointers/{_TEMPLATE,README}.md` + three pointer `.md` files.
- Tests mirroring each.

**Modify:**
- `config/fyn.php` — add `memory.pointers_path`.
- `app/Providers/AppServiceProvider.php` — bind `FetchHandlerRegistry` with the three handlers; register `PointerRegistry`/loader.
- `app/Services/AI/Fyn/FynContextAssembler.php` — inject the registry + dispatcher; emit `<live_data>` after `<knowledge>`.
- `app/Services/AI/AiToolDefinitions.php` + `app/Agents/CoordinatingAgent.php` (`executeTool`) — register `tool`/`both` pointers + route their calls through the dispatcher.

**Shared types (use these exact names/signatures across all tasks):**
- `Pointer { string $pointerId; string $topic; array $triggers; string $mode; string $handler; string $sourceLabel; int $version; string $body; }`
- `FetchContext { User $user; string $query; array $params; }`
- `FetchResult { string $value; string $sourceLabel; string $sourceVersion; string $digest; }`
- `interface FetchHandler { public function id(): string; public function fetch(FetchContext $ctx): FetchResult; }`

---

### Task 1: Pointer corpus scaffold + config key

**Files:**
- Create: `fyn-memory/procedural/pointers/_TEMPLATE.md`, `fyn-memory/procedural/pointers/README.md`, `fyn-memory/procedural/pointers/.gitkeep`
- Modify: `config/fyn.php`

- [ ] **Step 1: Add config key.** In `config/fyn.php`, inside `'memory' => [...]` after `'semantic_top_k' => ...,`:
```php
            'pointers_path' => base_path('fyn-memory/procedural/pointers'),
```

- [ ] **Step 2: Create the dir + `_TEMPLATE.md`:**
```bash
mkdir -p fyn-memory/procedural/pointers && touch fyn-memory/procedural/pointers/.gitkeep
```
`fyn-memory/procedural/pointers/_TEMPLATE.md`:
```markdown
---
pointer_id: example-pointer
topic: Human-readable topic this pointer covers
triggers: [keyword, another-keyword]   # required for prefetch/both; query words that fire it
mode: both                             # prefetch | tool | both
handler: tax_allowance                 # MUST be a registered FetchHandler id (fail-closed if not)
source_label: TaxConfigService         # human-readable source, for provenance
version: 1
---

Plain-language "when to use" description. Doubles as the LLM tool description in
tool mode. The fetch CODE lives in the named handler — NEVER here. No values, no
numbers, no £ figures (those come from the live source via the handler).
```

- [ ] **Step 3: Create `README.md`** explaining: this is the v0.5 pointer registry (heart of procedural memory); markdown holds routing only; the named `handler` must resolve to a registered code `FetchHandler`; modes (prefetch/tool/both); authors route pointers in markdown PRs, new fetch capability is a code handler (dev PR). No emoji (Rule #16).

- [ ] **Step 4: Commit:**
```bash
git add fyn-memory/procedural/pointers config/fyn.php
git commit -m "feat(coala): pointer corpus scaffold + config (registry)"
```

---

### Task 2: `Pointer` VO + value objects (`FetchContext`, `FetchResult`)

**Files:**
- Create: `app/Services/AI/Pointers/Pointer.php`, `app/Services/AI/Pointers/FetchContext.php`, `app/Services/AI/Pointers/FetchResult.php`

- [ ] **Step 1: Write `Pointer.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

/** One pointer's routing (frontmatter + body). Immutable; holds no fetch code. */
final class Pointer
{
    /** @param list<string> $triggers */
    public function __construct(
        public readonly string $pointerId,
        public readonly string $topic,
        public readonly array $triggers,
        public readonly string $mode,        // prefetch | tool | both
        public readonly string $handler,
        public readonly string $sourceLabel,
        public readonly int $version,
        public readonly string $body,
    ) {}

    public function isPrefetch(): bool
    {
        return $this->mode === 'prefetch' || $this->mode === 'both';
    }

    public function isTool(): bool
    {
        return $this->mode === 'tool' || $this->mode === 'both';
    }

    /** Lower-cased trigger set for sparse matching. */
    public function triggerHaystack(): string
    {
        return mb_strtolower(implode(' ', $this->triggers));
    }
}
```

- [ ] **Step 2: Write `FetchContext.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use App\Models\User;

/** Inputs a handler needs to fetch. Immutable. */
final class FetchContext
{
    /** @param array<string,mixed> $params */
    public function __construct(
        public readonly User $user,
        public readonly string $query,
        public readonly array $params = [],
    ) {}
}
```

- [ ] **Step 3: Write `FetchResult.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

/** A handler's result + provenance. Immutable. */
final class FetchResult
{
    public function __construct(
        public readonly string $value,        // rendered text injected into context / returned to the tool
        public readonly string $sourceLabel,  // e.g. "TaxConfigService"
        public readonly string $sourceVersion, // source as-of, e.g. the active tax year
        public readonly string $digest,        // short hash of $value for provenance
    ) {}

    /** Build a result, deriving the digest from the value. */
    public static function make(string $value, string $sourceLabel, string $sourceVersion): self
    {
        return new self($value, $sourceLabel, $sourceVersion, substr(hash('sha256', $value), 0, 16));
    }

    /** Provenance tuple for ai_messages.metadata. @return array<string,string> */
    public function provenance(string $pointerId, string $handler): array
    {
        return [
            'pointer_id' => $pointerId,
            'handler' => $handler,
            'source_label' => $this->sourceLabel,
            'source_version' => $this->sourceVersion,
            'digest' => $this->digest,
        ];
    }
}
```

- [ ] **Step 4: Commit:**
```bash
git add app/Services/AI/Pointers/Pointer.php app/Services/AI/Pointers/FetchContext.php app/Services/AI/Pointers/FetchResult.php
git commit -m "feat(coala): Pointer + FetchContext + FetchResult value objects (registry)"
```

---

### Task 3: `FetchHandler` interface + `FetchHandlerRegistry` (the whitelist)

**Files:**
- Create: `app/Services/AI/Pointers/FetchHandler.php`, `app/Services/AI/Pointers/FetchHandlerRegistry.php`
- Test: `tests/Unit/Services/AI/Pointers/FetchHandlerRegistryTest.php`

- [ ] **Step 1: Write `FetchHandler.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

interface FetchHandler
{
    /** Stable id referenced by a pointer's `handler` frontmatter (e.g. 'tax_allowance'). */
    public function id(): string;

    public function fetch(FetchContext $ctx): FetchResult;
}
```

- [ ] **Step 2: Write the failing test** `tests/Unit/Services/AI/Pointers/FetchHandlerRegistryTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;

function fakeHandler(string $id): FetchHandler
{
    return new class($id) implements FetchHandler
    {
        public function __construct(private string $id) {}

        public function id(): string
        {
            return $this->id;
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            return FetchResult::make('v', 'src', '2026/27');
        }
    };
}

it('resolves a registered handler by id', function (): void {
    $reg = new FetchHandlerRegistry([fakeHandler('tax_allowance')]);
    expect($reg->has('tax_allowance'))->toBeTrue()
        ->and($reg->get('tax_allowance')->id())->toBe('tax_allowance');
});

it('reports an unknown handler as absent', function (): void {
    $reg = new FetchHandlerRegistry([fakeHandler('tax_allowance')]);
    expect($reg->has('nope'))->toBeFalse();
});

it('throws when getting an unknown handler', function (): void {
    $reg = new FetchHandlerRegistry([]);
    expect(fn () => $reg->get('nope'))->toThrow(RuntimeException::class, 'nope');
});

it('exposes all registered ids', function (): void {
    $reg = new FetchHandlerRegistry([fakeHandler('a'), fakeHandler('b')]);
    expect($reg->ids())->toContain('a')->toContain('b');
});
```

- [ ] **Step 3: Run — expect FAIL.** `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Pointers/FetchHandlerRegistryTest.php`

- [ ] **Step 4: Write `FetchHandlerRegistry.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use RuntimeException;

/** The closed whitelist of code-defined fetchers. Markdown pointers reference these by id. */
final class FetchHandlerRegistry
{
    /** @var array<string, FetchHandler> */
    private array $handlers = [];

    /** @param iterable<FetchHandler> $handlers */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $h) {
            $this->handlers[$h->id()] = $h;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->handlers[$id]);
    }

    public function get(string $id): FetchHandler
    {
        if (! isset($this->handlers[$id])) {
            throw new RuntimeException("Pointer registry: no registered FetchHandler for '{$id}'.");
        }

        return $this->handlers[$id];
    }

    /** @return list<string> */
    public function ids(): array
    {
        return array_keys($this->handlers);
    }
}
```

- [ ] **Step 5: Run — expect 4 passed.** Pint both files. Commit:
```bash
git add app/Services/AI/Pointers/FetchHandler.php app/Services/AI/Pointers/FetchHandlerRegistry.php tests/Unit/Services/AI/Pointers/FetchHandlerRegistryTest.php
git commit -m "feat(coala): FetchHandler interface + whitelist registry (registry)"
```

---

### Task 4: `PointerRegistry` loader (fail-closed, handler-resolution check)

**Files:**
- Create: `app/Services/AI/Pointers/PointerRegistry.php`
- Test: `tests/Unit/Services/AI/Pointers/PointerRegistryTest.php`

Mirrors `app/Services/AI/Memory/SemanticCorpusLoader.php` (READ IT for the parse pattern). Difference: it also takes the `FetchHandlerRegistry` and **fails closed if a pointer's `handler` is not registered**, and matches pre-fetch pointers by query.

- [ ] **Step 1: Write the failing test** `tests/Unit/Services/AI/Pointers/PointerRegistryTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\PointerRegistry;

function handlerStub(string $id): FetchHandler
{
    return new class($id) implements FetchHandler
    {
        public function __construct(private string $id) {}

        public function id(): string
        {
            return $this->id;
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            return FetchResult::make('v', 's', '2026/27');
        }
    };
}

function writePointer(string $dir, string $name, string $frontmatter, string $body = 'When to use this pointer.'): void
{
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/$name.md", "---\n$frontmatter\n---\n\n$body\n");
}

function registryWith(string $dir, array $handlerIds): PointerRegistry
{
    config(['fyn.memory.pointers_path' => $dir]);

    return new PointerRegistry(new FetchHandlerRegistry(array_map('handlerStub', $handlerIds)));
}

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/ptr-'.uniqid();
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->dir));

it('loads a valid pointer indexed by pointer_id', function (): void {
    writePointer($this->dir, 'isa', "pointer_id: isa-allowance\ntopic: ISA allowance\ntriggers: [isa, allowance]\nmode: both\nhandler: tax_allowance\nsource_label: TaxConfigService\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);
    expect($reg->all())->toHaveCount(1)
        ->and($reg->all()['isa-allowance']->handler)->toBe('tax_allowance');
});

it('fails closed when a pointer references an unregistered handler', function (): void {
    writePointer($this->dir, 'x', "pointer_id: x\ntopic: X\ntriggers: [x]\nmode: prefetch\nhandler: ghost\nsource_label: S\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);
    expect(fn () => $reg->all())->toThrow(RuntimeException::class, 'ghost');
});

it('fails closed on a duplicate pointer_id', function (): void {
    writePointer($this->dir, 'a', "pointer_id: dup\ntopic: A\ntriggers: [a]\nmode: tool\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    writePointer($this->dir, 'b', "pointer_id: dup\ntopic: B\ntriggers: [b]\nmode: tool\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);
    expect(fn () => $reg->all())->toThrow(RuntimeException::class, 'duplicate');
});

it('fails closed on an unknown mode', function (): void {
    writePointer($this->dir, 'a', "pointer_id: a\ntopic: A\ntriggers: [a]\nmode: nonsense\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    expect(fn () => registryWith($this->dir, ['tax_allowance'])->all())->toThrow(RuntimeException::class, 'mode');
});

it('fails closed when a prefetch pointer has no triggers', function (): void {
    writePointer($this->dir, 'a', "pointer_id: a\ntopic: A\nmode: prefetch\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    expect(fn () => registryWith($this->dir, ['tax_allowance'])->all())->toThrow(RuntimeException::class, 'triggers');
});

it('matches prefetch pointers whose triggers appear in the query', function (): void {
    writePointer($this->dir, 'isa', "pointer_id: isa\ntopic: ISA\ntriggers: [isa, allowance]\nmode: prefetch\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    writePointer($this->dir, 'tool-only', "pointer_id: rec\ntopic: Rec\ntriggers: [recommend]\nmode: tool\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    $reg = registryWith($this->dir, ['tax_allowance']);

    $matched = $reg->matchPrefetch('what is my isa allowance');
    expect($matched)->toHaveCount(1)->and($matched[0]->pointerId)->toBe('isa'); // tool-mode excluded from prefetch
    expect($reg->matchPrefetch('hello there'))->toBe([]);
});

it('returns tool-mode pointers for catalogue registration', function (): void {
    writePointer($this->dir, 'rec', "pointer_id: rec\ntopic: Rec\ntriggers: [recommend]\nmode: both\nhandler: tax_allowance\nsource_label: S\nversion: 1");
    expect(registryWith($this->dir, ['tax_allowance'])->toolPointers())->toHaveCount(1);
});
```

- [ ] **Step 2: Run — expect FAIL.** `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Pointers/PointerRegistryTest.php`

- [ ] **Step 3: Write `PointerRegistry.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads + validates the pointer corpus (fyn-memory/procedural/pointers/*.md),
 * fail-closed. Routes each pointer's `handler` to a registered FetchHandler and
 * refuses to load a pointer whose handler is not on the whitelist. Mirrors
 * SemanticCorpusLoader's parser.
 */
final class PointerRegistry
{
    private const SKIP = ['_TEMPLATE.md', 'README.md'];

    private const MODES = ['prefetch', 'tool', 'both'];

    /** @var array<string, Pointer>|null */
    private ?array $cache = null;

    public function __construct(private readonly FetchHandlerRegistry $handlers) {}

    /** @return array<string, Pointer> keyed by pointer_id */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $dir = (string) config('fyn.memory.pointers_path');
        $pointers = [];

        if (! File::isDirectory($dir)) {
            return $this->cache = [];
        }

        foreach (File::files($dir) as $file) {
            if ($file->getExtension() !== 'md' || in_array($file->getFilename(), self::SKIP, true)) {
                continue;
            }

            $p = $this->parse($file->getPathname(), File::get($file->getPathname()));

            if (isset($pointers[$p->pointerId])) {
                throw new RuntimeException("Pointer registry: duplicate pointer_id '{$p->pointerId}' ({$file->getPathname()}).");
            }

            $pointers[$p->pointerId] = $p;
        }

        return $this->cache = $pointers;
    }

    /** Pre-fetch pointers whose triggers appear in the query (sparse). @return list<Pointer> */
    public function matchPrefetch(string $query): array
    {
        $q = mb_strtolower($query);
        $matched = [];

        foreach ($this->all() as $p) {
            if (! $p->isPrefetch()) {
                continue;
            }
            foreach ($p->triggers as $t) {
                if ($t !== '' && str_contains($q, mb_strtolower((string) $t))) {
                    $matched[] = $p;
                    break;
                }
            }
        }

        return $matched;
    }

    /** Pointers exposed as LLM tools. @return list<Pointer> */
    public function toolPointers(): array
    {
        return array_values(array_filter($this->all(), static fn (Pointer $p): bool => $p->isTool()));
    }

    public function get(string $pointerId): ?Pointer
    {
        return $this->all()[$pointerId] ?? null;
    }

    private function parse(string $path, string $contents): Pointer
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n?(.*)$/s', $contents, $m) !== 1) {
            throw new RuntimeException("Pointer registry: missing frontmatter ({$path}).");
        }

        $meta = Yaml::parse($m[1]);
        if (! is_array($meta)) {
            throw new RuntimeException("Pointer registry: frontmatter must be a YAML mapping ({$path}).");
        }

        $require = function (string $k) use ($meta, $path): mixed {
            if (! array_key_exists($k, $meta) || $meta[$k] === null || $meta[$k] === '') {
                throw new RuntimeException("Pointer registry: missing '{$k}' ({$path}).");
            }

            return $meta[$k];
        };

        $mode = (string) $require('mode');
        if (! in_array($mode, self::MODES, true)) {
            throw new RuntimeException("Pointer registry: unknown mode '{$mode}' ({$path}).");
        }

        $triggers = array_values(array_map('strval', (array) ($meta['triggers'] ?? [])));
        if (($mode === 'prefetch' || $mode === 'both') && $triggers === []) {
            throw new RuntimeException("Pointer registry: prefetch/both pointer needs triggers ({$path}).");
        }

        $handler = (string) $require('handler');
        if (! $this->handlers->has($handler)) {
            throw new RuntimeException("Pointer registry: pointer references unregistered handler '{$handler}' ({$path}).");
        }

        return new Pointer(
            pointerId: (string) $require('pointer_id'),
            topic: (string) $require('topic'),
            triggers: $triggers,
            mode: $mode,
            handler: $handler,
            sourceLabel: (string) $require('source_label'),
            version: (int) $require('version'),
            body: trim($m[2]),
        );
    }
}
```

- [ ] **Step 4: Run — expect 7 passed.** Pint. Commit:
```bash
git add app/Services/AI/Pointers/PointerRegistry.php tests/Unit/Services/AI/Pointers/PointerRegistryTest.php
git commit -m "feat(coala): PointerRegistry loader — fail-closed, handler-resolution, prefetch match (registry)"
```

---

### Task 5: `FetchDispatcher` + provenance recorder

**Files:**
- Create: `app/Services/AI/Pointers/FetchDispatcher.php`
- Test: `tests/Unit/Services/AI/Pointers/FetchDispatcherTest.php`

- [ ] **Step 1: Write the failing test** `tests/Unit/Services/AI/Pointers/FetchDispatcherTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\AiMessage;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchDispatcher;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\Pointer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function okHandler(): FetchHandler
{
    return new class implements FetchHandler
    {
        public function id(): string
        {
            return 'ok';
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            return FetchResult::make('Your ISA allowance is fetched live.', 'TaxConfigService', '2026/27');
        }
    };
}

function boomHandler(): FetchHandler
{
    return new class implements FetchHandler
    {
        public function id(): string
        {
            return 'boom';
        }

        public function fetch(FetchContext $ctx): FetchResult
        {
            throw new RuntimeException('engine down');
        }
    };
}

function pointer(string $handler): Pointer
{
    return new Pointer('p1', 'topic', ['isa'], 'both', $handler, 'TaxConfigService', 1, 'body');
}

it('runs the handler and returns its result', function (): void {
    $d = new FetchDispatcher(new FetchHandlerRegistry([okHandler()]));
    $user = \App\Models\User::factory()->create();
    $res = $d->run(pointer('ok'), new FetchContext($user, 'what is my isa allowance'));
    expect($res)->not->toBeNull()
        ->and($res->value)->toContain('fetched live');
});

it('returns null and does not throw when the handler fails', function (): void {
    $d = new FetchDispatcher(new FetchHandlerRegistry([boomHandler()]));
    $user = \App\Models\User::factory()->create();
    expect($d->run(pointer('boom'), new FetchContext($user, 'x')))->toBeNull();
});

it('records provenance onto an AiMessage metadata when given one', function (): void {
    $d = new FetchDispatcher(new FetchHandlerRegistry([okHandler()]));
    $user = \App\Models\User::factory()->create();
    $msg = AiMessage::factory()->create(['role' => 'assistant']);

    $d->run(pointer('ok'), new FetchContext($user, 'isa'), $msg);

    $msg->refresh();
    expect($msg->metadata['fetch_provenance'][0]['handler'])->toBe('ok')
        ->and($msg->metadata['fetch_provenance'][0]['source_version'])->toBe('2026/27');
});
```
> Note: confirm an `AiMessage` factory exists (`grep -rl AiMessage database/factories`); if not, build the row via `AiConversation::factory()` + `->messages()->create([...])` matching the existing schema. Adjust the test's row creation to the real factory/relationship.

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Write `FetchDispatcher.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use App\Models\AiMessage;
use Throwable;

/**
 * Runs a pointer's handler and (optionally) records fetch provenance on the
 * assistant message. A handler failure degrades to null + a logged report —
 * never breaks the turn (Phase-1 resilience posture).
 */
final class FetchDispatcher
{
    public function __construct(private readonly FetchHandlerRegistry $handlers) {}

    public function run(Pointer $pointer, FetchContext $ctx, ?AiMessage $message = null): ?FetchResult
    {
        try {
            $result = $this->handlers->get($pointer->handler)->fetch($ctx);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        if ($message !== null) {
            $this->recordProvenance($message, $result->provenance($pointer->pointerId, $pointer->handler));
        }

        return $result;
    }

    /** @param array<string,string> $entry */
    private function recordProvenance(AiMessage $message, array $entry): void
    {
        $meta = $message->metadata ?? [];
        $meta['fetch_provenance'] = array_merge($meta['fetch_provenance'] ?? [], [$entry]);
        $message->update(['metadata' => $meta]);
    }
}
```

- [ ] **Step 4: Run — expect 3 passed.** Pint. Commit:
```bash
git add app/Services/AI/Pointers/FetchDispatcher.php tests/Unit/Services/AI/Pointers/FetchDispatcherTest.php
git commit -m "feat(coala): FetchDispatcher + provenance on ai_messages.metadata (registry)"
```

---

### Task 6: `TaxAllowanceHandler` (config archetype) + pointer

**Files:**
- Create: `app/Services/AI/Pointers/Handlers/TaxAllowanceHandler.php`, `fyn-memory/procedural/pointers/isa-annual-allowance.md`
- Test: `tests/Unit/Services/AI/Pointers/Handlers/TaxAllowanceHandlerTest.php`

- [ ] **Step 1: Confirm the source shape.** Read `app/Services/TaxConfigService.php` lines around 158-180 (`getISAAllowances()`, `getPensionAllowances()`, `getTaxYear()`) and note the exact array keys returned (e.g. ISA allowance key). Use the real keys in Step 3.

- [ ] **Step 2: Write the failing test** `tests/Unit/Services/AI/Pointers/Handlers/TaxAllowanceHandlerTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\TaxAllowanceHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\TaxConfigurationSeeder::class));

it('fetches the live ISA + pension allowances with the active tax year as source version', function (): void {
    $user = \App\Models\User::factory()->create();
    $handler = app(TaxAllowanceHandler::class);

    $res = $handler->fetch(new FetchContext($user, 'what is my isa allowance'));

    expect($handler->id())->toBe('tax_allowance')
        ->and($res->sourceLabel)->toBe('TaxConfigService')
        ->and($res->sourceVersion)->toBe(app(\App\Services\TaxConfigService::class)->getTaxYear())
        ->and($res->value)->toContain('ISA')          // narrative mentions ISA
        ->and($res->value)->toContain('£');            // carries the LIVE figure (fetched, not frozen)
});
```
> The £ assertion here is correct and intended: the figure is FETCHED LIVE from `TaxConfigService` at request time — it is never frozen into a `.md`. (The no-£ corpus guard applies to `.md` files, not handler output.)

- [ ] **Step 3: Write `TaxAllowanceHandler.php`** (use the REAL array keys confirmed in Step 1):
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use App\Services\TaxConfigService;

/** Config archetype — UK allowance figures, live from TaxConfigService (Rule #3). */
final class TaxAllowanceHandler implements FetchHandler
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function id(): string
    {
        return 'tax_allowance';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $isa = $this->taxConfig->getISAAllowances();
        $pension = $this->taxConfig->getPensionAllowances();
        $year = $this->taxConfig->getTaxYear();

        // Use the real keys from TaxConfigService (confirmed in Step 1). Example shape:
        $isaAllowance = $isa['annual_allowance'] ?? $isa['isa'] ?? null;
        $pensionAllowance = $pension['annual_allowance'] ?? null;

        $value = "ISA annual allowance for {$year}: ".$this->fmt($isaAllowance).". "
            ."Pension annual allowance: ".$this->fmt($pensionAllowance).".";

        return FetchResult::make($value, 'TaxConfigService', $year);
    }

    private function fmt(mixed $amount): string
    {
        return is_numeric($amount) ? '£'.number_format((float) $amount) : 'unavailable';
    }
}
```

- [ ] **Step 4: Run — expect 1 passed.** (If the array keys are wrong, the £/ISA assertions fail — fix the keys per Step 1, do not weaken the test.)

- [ ] **Step 5: Author the pointer** `fyn-memory/procedural/pointers/isa-annual-allowance.md`:
```markdown
---
pointer_id: isa-annual-allowance
topic: ISA and pension annual allowances
triggers: [isa, allowance, subscription, contribute, pension annual]
mode: both
handler: tax_allowance
source_label: TaxConfigService
version: 1
---

Use when the user asks how much they can pay into an ISA or pension this year, or
about remaining allowance. The live figures come from TaxConfigService — never
state an allowance from memory.
```

- [ ] **Step 6: Pint. Commit:**
```bash
git add app/Services/AI/Pointers/Handlers/TaxAllowanceHandler.php tests/Unit/Services/AI/Pointers/Handlers/TaxAllowanceHandlerTest.php fyn-memory/procedural/pointers/isa-annual-allowance.md
git commit -m "feat(coala): TaxAllowanceHandler (config archetype) + pointer (registry)"
```

---

### Task 7: `UserFinancialHandler` (model/builder archetype) + pointer

**Files:**
- Create: `app/Services/AI/Pointers/Handlers/UserFinancialHandler.php`, `fyn-memory/procedural/pointers/user-financial-position.md`
- Test: `tests/Unit/Services/AI/Pointers/Handlers/UserFinancialHandlerTest.php`

Formalises the existing `AdvicePromptBuilder::buildExistingRecordsSummary(User, ?array)` (a side-effect-free builder; do NOT use `buildFinancialContext` here — it needs the orchestrateAnalysis closure and is heavier; the records summary is the clean formalization target).

- [ ] **Step 1: Write the failing test:**
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\UserFinancialHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formalises the existing records summary as a fetch result', function (): void {
    $user = \App\Models\User::factory()->create();
    \App\Models\SavingsAccount::factory()->create(['user_id' => $user->id, 'balance' => 5000]);
    $handler = app(UserFinancialHandler::class);

    $res = $handler->fetch(new FetchContext($user, 'what accounts do i have'));

    expect($handler->id())->toBe('user_financial')
        ->and($res->sourceLabel)->toBe('user records')
        ->and($res->value)->toBeString();
});
```
> Confirm `SavingsAccount` factory fields before running; adjust to the real factory.

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Write `UserFinancialHandler.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;

/** Model/builder archetype — formalises the existing records-summary fetch. */
final class UserFinancialHandler implements FetchHandler
{
    public function __construct(private readonly AdvicePromptBuilder $advice) {}

    public function id(): string
    {
        return 'user_financial';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $summary = $this->advice->buildExistingRecordsSummary($ctx->user, null);

        // Source version = newest record touch, so provenance reflects data freshness.
        $version = (string) ($ctx->user->updated_at?->toDateString() ?? 'unknown');

        return FetchResult::make($summary, 'user records', $version);
    }
}
```

- [ ] **Step 4: Run — expect 1 passed.**

- [ ] **Step 5: Author the pointer** `fyn-memory/procedural/pointers/user-financial-position.md`:
```markdown
---
pointer_id: user-financial-position
topic: The user's own accounts, balances and records
triggers: [my accounts, my savings, my balance, what do i have, my investments]
mode: prefetch
handler: user_financial
source_label: user records
version: 1
---

Use when the user asks about their own financial position — accounts, balances,
holdings. The data is read live from their records, never remembered.
```

- [ ] **Step 6: Pint. Commit:**
```bash
git add app/Services/AI/Pointers/Handlers/UserFinancialHandler.php tests/Unit/Services/AI/Pointers/Handlers/UserFinancialHandlerTest.php fyn-memory/procedural/pointers/user-financial-position.md
git commit -m "feat(coala): UserFinancialHandler (formalises existing records fetch) + pointer (registry)"
```

---

### Task 8: `RecommendationHandler` (engine archetype) + pointer

**Files:**
- Create: `app/Services/AI/Pointers/Handlers/RecommendationHandler.php`, `fyn-memory/procedural/pointers/recommendations.md`
- Test: `tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerTest.php`

- [ ] **Step 1: Pin the entry point.** Read `app/Agents/CoordinatingAgent.php` around line 1795 (`handleRecommendations(User $user): array`) — confirm its exact return shape (it returns a recommendations array). This is the engine entry point. If `handleRecommendations` requires prior analysis state, instead use the public `analyze()` → `generateRecommendations()` path; pick the single public call that yields "recommendations for this user" and note which you used.

- [ ] **Step 2: Write the failing test:**
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\RecommendationHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(\Database\Seeders\TaxConfigurationSeeder::class));

it('fetches live recommendations for the user as rendered text', function (): void {
    $user = \App\Models\User::factory()->create();
    $handler = app(RecommendationHandler::class);

    $res = $handler->fetch(new FetchContext($user, 'what should i do'));

    expect($handler->id())->toBe('recommendations')
        ->and($res->sourceLabel)->toBe('recommendation engine')
        ->and($res->value)->toBeString()
        ->and($res->sourceVersion)->not->toBe('');
});
```

- [ ] **Step 3: Write `RecommendationHandler.php`** (wire to the entry point confirmed in Step 1; the example assumes `CoordinatingAgent::handleRecommendations(User): array` returning a list of `['title' => ...]`-shaped items — adjust to the real shape):
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers\Handlers;

use App\Agents\CoordinatingAgent;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchResult;
use Illuminate\Support\Carbon;

/** Engine archetype — live recommendations from the recommendation engine. */
final class RecommendationHandler implements FetchHandler
{
    public function __construct(private readonly CoordinatingAgent $coordinator) {}

    public function id(): string
    {
        return 'recommendations';
    }

    public function fetch(FetchContext $ctx): FetchResult
    {
        $recs = $this->coordinator->handleRecommendations($ctx->user);

        $lines = [];
        foreach (($recs['data']['recommendations'] ?? $recs['recommendations'] ?? $recs) as $r) {
            $title = is_array($r) ? ($r['title'] ?? $r['description'] ?? '') : (string) $r;
            if ($title !== '') {
                $lines[] = '- '.$title;
            }
        }

        $value = $lines === [] ? 'No current recommendations.' : "Current recommendations:\n".implode("\n", $lines);

        // Engine output is computed-now; stamp the request instant as the as-of.
        return FetchResult::make($value, 'recommendation engine', Carbon::now()->toDateString());
    }
}
```
> Note: `Carbon::now()` is fine here (runtime handler, not a workflow script). Adjust the array-shape extraction to the real `handleRecommendations` return confirmed in Step 1.

- [ ] **Step 4: Run — expect 1 passed.**

- [ ] **Step 5: Author the pointer** `fyn-memory/procedural/pointers/recommendations.md`:
```markdown
---
pointer_id: recommendations
topic: Fyn's recommended actions for the user
triggers: [recommend, what should i do, suggestions, advice on what]
mode: tool
handler: recommendations
source_label: recommendation engine
version: 1
---

Use when the user asks what they should do, or for recommendations. The
recommendation engine computes these live from the user's current position —
exposed as a tool because it is a heavier, explicit ask, not a blanket pre-fetch.
```

- [ ] **Step 6: Pint. Commit:**
```bash
git add app/Services/AI/Pointers/Handlers/RecommendationHandler.php tests/Unit/Services/AI/Pointers/Handlers/RecommendationHandlerTest.php fyn-memory/procedural/pointers/recommendations.md
git commit -m "feat(coala): RecommendationHandler (engine archetype) + pointer (registry)"
```

---

### Task 9: Bindings — register handlers + registry in the container

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Services/AI/Pointers/PointerBindingsTest.php`

- [ ] **Step 1: Write the failing test:**
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\PointerRegistry;

it('binds the three proof handlers into the whitelist', function (): void {
    $reg = app(FetchHandlerRegistry::class);
    expect($reg->ids())->toContain('tax_allowance')->toContain('user_financial')->toContain('recommendations');
});

it('resolves PointerRegistry from the container', function (): void {
    expect(app(PointerRegistry::class))->toBeInstanceOf(PointerRegistry::class);
});

it('loads the real shipped pointer corpus without throwing (every handler resolves)', function (): void {
    expect(fn () => app(PointerRegistry::class)->all())->not->toThrow(Throwable::class);
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Add bindings** in `AppServiceProvider::register()`:
```php
        $this->app->singleton(\App\Services\AI\Pointers\FetchHandlerRegistry::class, function ($app) {
            return new \App\Services\AI\Pointers\FetchHandlerRegistry([
                $app->make(\App\Services\AI\Pointers\Handlers\TaxAllowanceHandler::class),
                $app->make(\App\Services\AI\Pointers\Handlers\UserFinancialHandler::class),
                $app->make(\App\Services\AI\Pointers\Handlers\RecommendationHandler::class),
            ]);
        });

        $this->app->singleton(\App\Services\AI\Pointers\PointerRegistry::class);
        $this->app->singleton(\App\Services\AI\Pointers\FetchDispatcher::class);
```

- [ ] **Step 4: Run — expect 3 passed** (the third proves the shipped corpus is valid against the real whitelist — the fail-closed guarantee end-to-end). Pint. Commit:
```bash
git add app/Providers/AppServiceProvider.php tests/Unit/Services/AI/Pointers/PointerBindingsTest.php
git commit -m "feat(coala): bind FetchHandlerRegistry + PointerRegistry + dispatcher (registry)"
```

---

### Task 10: Pre-fetch integration — additive `<live_data>` block in the assembler

**Files:**
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php`
- Test: `tests/Unit/Services/AI/Fyn/FynContextAssemblerLiveDataTest.php`

Mirror the Phase-1 `<knowledge>` wiring (same file, same additive + try/catch-degrade pattern). The `<live_data>` block goes immediately AFTER the `<knowledge>` block.

- [ ] **Step 1: Write the failing test** (construct `FynTurnContext::make(...)` exactly as the sibling `FynContextAssemblerKnowledgeTest.php` does — read it first):
```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $this->dir]);
    @mkdir($this->dir, 0777, true);
    // a prefetch pointer routing to the real, bound user_financial handler
    file_put_contents("$this->dir/uf.md", "---\npointer_id: uf\ntopic: position\ntriggers: [my accounts]\nmode: prefetch\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nWhen to use.\n");
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

afterEach(fn () => \Illuminate\Support\Facades\File::deleteDirectory($this->dir));

it('emits a <live_data> block when a prefetch pointer matches the query', function (): void {
    $user = User::factory()->create();
    $ctx = /* FynTurnContext::make(... message: 'show my accounts' ...) per the sibling test */;

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<live_data>');
});

it('omits <live_data> when no pointer matches', function (): void {
    $user = User::factory()->create();
    $ctx = /* FynTurnContext::make(... message: 'hello there' ...) */;

    expect(app(FynContextAssembler::class)->build($ctx))->not->toContain('<live_data>');
});
```

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Inject deps + emit the block.** In `FynContextAssembler.php` constructor, after the `SemanticRetriever $semantic` param, add:
```php
        private readonly \App\Services\AI\Pointers\PointerRegistry $pointers,
        private readonly \App\Services\AI\Pointers\FetchDispatcher $dispatcher,
```
Immediately AFTER the `<knowledge>` block emission (after the `if ($knowledgeFacts !== []) { ... }` block), add:
```php
        // CoALA pointer registry — live data fetched at the moment of need (v0.5).
        // Additive sibling to <knowledge>; a fetch error degrades to no entry, never
        // breaks the turn. Lazy: only pointers whose triggers match the query fire.
        try {
            $liveBlocks = [];
            foreach ($this->pointers->matchPrefetch($ctx->message) as $pointer) {
                $res = $this->dispatcher->run($pointer, new \App\Services\AI\Pointers\FetchContext($ctx->user, $ctx->message));
                if ($res !== null) {
                    $liveBlocks[] = "### {$pointer->topic} (source: {$res->sourceLabel}, as of {$res->sourceVersion})\n{$res->value}";
                }
            }
        } catch (\Throwable $e) {
            report($e);
            $liveBlocks = [];
        }
        if ($liveBlocks !== []) {
            $lines[] = "<live_data>\n".implode("\n\n", $liveBlocks)."\n</live_data>";
        }
```
> Provenance recording is omitted here deliberately: the assembler builds the prompt *before* the assistant `AiMessage` row exists. Provenance is recorded in tool mode (Task 11, where the message exists) and is wired into the pre-fetch path when the loop passes the assistant message into the assembler — tracked as a follow-up. For v1, pre-fetch fetches are live but their provenance row lands only for tool-mode fetches. (Flag this as DONE_WITH_CONCERNS if it matters to compliance sign-off.)

- [ ] **Step 4: Run new test — expect 2 passed. Run full `tests/Unit/Services/AI/Fyn` — expect all green (additive, no regression).** Confirm `FynSystemPrompt.php` untouched. Pint. Commit:
```bash
git add app/Services/AI/Fyn/FynContextAssembler.php tests/Unit/Services/AI/Fyn/FynContextAssemblerLiveDataTest.php
git commit -m "feat(coala): additive <live_data> block — pre-fetch pointers into context (registry)"
```

---

### Task 11: Tool-mode integration — register tool pointers + route through the dispatcher

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php` (append pointer tools), `app/Agents/CoordinatingAgent.php` (`executeTool` — route pointer-tool calls)
- Test: `tests/Feature/AI/PointerToolModeTest.php`

This is the highest-integration task — READ `AiToolDefinitions::getTools()` and `CoordinatingAgent::executeTool()` fully first. The goal: each `tool`/`both` pointer becomes a tool named `fetch_{pointer_id}` (underscored) with the pointer body as its description; an LLM call routes through `FetchDispatcher` to the handler, recording provenance on the assistant message.

- [ ] **Step 1: Write the failing test** (drive via the existing stream-mock harness `tests/Support/Fyn` — read an existing test that uses it; assert a `fetch_recommendations` tool call routes to the handler and the response carries the fetched text):
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\PointerRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes tool-mode pointers as fetch_{id} tools in the catalogue', function (): void {
    // point the registry at a temp corpus with a tool-mode pointer routing to user_financial
    $dir = sys_get_temp_dir().'/ptr-'.uniqid();
    config(['fyn.memory.pointers_path' => $dir]);
    @mkdir($dir, 0777, true);
    file_put_contents("$dir/uf.md", "---\npointer_id: position\ntopic: position\ntriggers: [x]\nmode: tool\nhandler: user_financial\nsource_label: user records\nversion: 1\n---\n\nFetch the user's position.\n");

    $tools = app(\App\Services\AI\AiToolDefinitions::class)->getTools();
    $names = array_map(fn ($t) => $t['name'] ?? ($t['function']['name'] ?? null), $tools);

    expect($names)->toContain('fetch_position');

    \Illuminate\Support\Facades\File::deleteDirectory($dir);
});
```
> The exact tool-array shape (`name` vs `function.name`) depends on `AiToolDefinitions`' format — confirm in Step 0 and assert the real shape.

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Append pointer tools in `AiToolDefinitions::getTools()`.** After the existing tool array is assembled (before the final return), merge in pointer tools built from `app(PointerRegistry::class)->toolPointers()`, each shaped to match the existing tool schema (name `fetch_{pointer_id}`, description = pointer body, minimal/empty input schema). Mirror the existing array shape exactly.

- [ ] **Step 4: Route the call in `CoordinatingAgent::executeTool()`.** Add a branch: if `$toolName` starts with `fetch_`, resolve the pointer (`fetch_` + id → `PointerRegistry::get($id)`), and if found and tool-enabled, run `app(FetchDispatcher::class)->run($pointer, new FetchContext($user, ''), $assistantMessage)` and return the `FetchResult->value` as the tool result in the existing tool-result shape. (Pass the conversation's assistant message if available so provenance records; otherwise pass null.)

- [ ] **Step 5: Run the new test + the full `tests/Unit/Services/AI/Fyn` + `tests/Feature/...AiChat...` suites — expect green.** Pint. Commit:
```bash
git add app/Services/AI/AiToolDefinitions.php app/Agents/CoordinatingAgent.php tests/Feature/AI/PointerToolModeTest.php
git commit -m "feat(coala): expose tool-mode pointers + route tool calls through the dispatcher (registry)"
```

---

### Task 12: `fyn:pointers:reindex` validate command + hygiene guards

**Files:**
- Create: `app/Console/Commands/FynPointersReindex.php`, `tests/Feature/Console/FynPointersReindexTest.php`, `tests/Unit/Services/AI/PointerCorpusContentTest.php`

- [ ] **Step 1: Write the reindex command** (mirrors `FynSemanticReindex`): resolve `PointerRegistry`, call `all()` (fail-closed validates every pointer + every handler resolves), print the count, return FAILURE on `Throwable`. No index file needed (the registry reads the corpus directly) — the command's value is the deploy-time fail-closed validation. Signature `fyn:pointers:reindex`.

- [ ] **Step 2: Reindex test** `FynPointersReindexTest.php`: a valid temp corpus → exit 0 + count; a corpus with an unregistered handler → exit 1.

- [ ] **Step 3: Hygiene guard** `PointerCorpusContentTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\Pointers\PointerRegistry;

it('loads the real pointer corpus without throwing', function (): void {
    expect(fn () => app(PointerRegistry::class)->all())->not->toThrow(Throwable::class);
});

it('contains no £ figures in any pointer body (figures are fetched live, not frozen)', function (): void {
    $pointers = app(PointerRegistry::class)->all();
    expect($pointers)->toBeArray();
    foreach ($pointers as $p) {
        expect($p->body)->not->toMatch('/£\s?\d/', "pointer {$p->pointerId} body has a £ figure — values are fetched via the handler, never frozen in the .md");
    }
});
```

- [ ] **Step 4: Run all three — expect green. Pint. Commit:**
```bash
git add app/Console/Commands/FynPointersReindex.php tests/Feature/Console/FynPointersReindexTest.php tests/Unit/Services/AI/PointerCorpusContentTest.php
git commit -m "feat(coala): fyn:pointers:reindex + pointer corpus hygiene guards (registry)"
```

- [ ] **Step 5: Add `fyn:pointers:reindex` to the deploy runbook** (CLAUDE.md deploy finalise blocks, next to `fyn:semantic:reindex`). Commit that doc change separately.

---

## Self-Review

**Spec coverage:**
- §3.1 pointer corpus → Tasks 1, 6–8 (pointers). ✅
- §3.2 PointerRegistry fail-closed + handler-resolution → Task 4. ✅
- §3.3 FetchHandler whitelist → Tasks 2, 3, 6–9. ✅
- §3.4 FetchDispatcher + degrade → Task 5. ✅
- §3.5 both trigger modes → Task 10 (pre-fetch `<live_data>`) + Task 11 (tool). ✅
- §3.6 provenance on metadata → Task 5 (mechanism) + Task 11 (tool path records); **pre-fetch provenance is a flagged follow-up** (Task 10 note — assistant message doesn't exist at assembler time). ✅ with documented gap.
- §5 three proof handlers (config/model/engine) → Tasks 6/7/8. ✅
- §6 security (closed whitelist, fail-closed, no md execution, write-safety untouched, prefix-cache untouched) → Tasks 3/4/9 + Task 10 (FynSystemPrompt untouched check). ✅
- §7 testing → each task's tests + Task 12 guards. ✅

**Placeholder scan:** Tasks 6/8/11 contain bounded discovery steps (confirm TaxConfigService keys / handleRecommendations shape / tool-array shape) — these are specified investigations with the exact file:line to read and a fallback, NOT hand-waves. The handler bodies show real code against the confirmed entry points. No "TBD/etc."

**Type consistency:** `Pointer`, `FetchContext`, `FetchResult`, `FetchHandler`, `FetchHandlerRegistry`, `PointerRegistry`, `FetchDispatcher` used with identical signatures across Tasks 2–12. `FetchResult::make()` + `provenance()` consistent. Pointer fields (`pointerId/topic/triggers/mode/handler/sourceLabel/version/body`) consistent. Handler ids (`tax_allowance/user_financial/recommendations`) consistent between handlers, pointers, bindings, and tests.

**Known follow-ups (documented, not gaps):** pre-fetch provenance wiring (needs the loop to pass the assistant message into the assembler); singleton/hot-reload for the corpus loaders (shared Phase-4 concern); migrating provenance to the Phase-2 episodic blob.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-01-coala-pointer-registry-plan.md`. Execution options:
1. **Subagent-Driven (recommended)** — fresh subagent per task, two-stage review between tasks (same flow as Phase 1).
2. **Inline Execution** — batch with checkpoints.
