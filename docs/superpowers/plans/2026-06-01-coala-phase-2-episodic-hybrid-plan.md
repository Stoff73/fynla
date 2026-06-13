# CoALA Phase 2 — Episodic SQL+`.md` Hybrid Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Relocate verbatim per-turn forensic data from `ai_messages` into date-sharded `.md` blobs referenced by path + SHA-256, land live fetch-provenance via a request-scoped collector, give every turn a signed cross-medium tamper-evidence attestation, backfill history, add retrieval + retention + GDPR erasure, and surface a read-only episodic log in the advisor and admin UIs.

**Architecture:** Extend `ai_messages` (not a new table). A request-scoped `FetchProvenanceCollector` accumulates pointer fetches from both trigger paths; `HasAiChat` flushes it onto the assistant row after `saveMessage`. `EpisodeBlobWriter` writes the verbatim `.md` blob via the atomic tmp→fsync→rename→sha protocol. A versioned hash chain adds one v2 `__episode__` event per turn to the existing `ai_audit_events` chain (v1 tool-dispatch rows untouched). Read/retention/erasure are typed services + artisan commands; the UI is two read-only Vue surfaces over `AiAuditController` endpoints.

**Tech Stack:** Laravel 10 / PHP 8.2, Pest, Symfony Yaml (already present), Vue 3 / Vuex / Tailwind. No new Composer/npm deps.

**Spec:** `docs/superpowers/specs/2026-06-01-coala-phase-2-episodic-hybrid-design.md`. **Base branch:** `feat/coala-phase1-semantic-memory`; CoALA PRs target `coala`. Confirm base at execution time.

**Test DB convention:** prefix Pest with `DB_DATABASE=laravel_testing` (NEVER `php artisan --env=testing` — MEMORY.md). Feature tests use `RefreshDatabase`; seed `TaxConfigurationSeeder` where a configured tax env is needed.

---

## File structure

**Create (services):**
- `app/Services/AI/Memory/Episodic/FetchProvenanceCollector.php` — request-scoped accumulator of fetch-provenance tuples.
- `app/Services/AI/Memory/Episodic/EpisodeBlobData.php` — immutable VO carrying the blob's frontmatter + section bodies.
- `app/Services/AI/Memory/Episodic/EpisodeBlobRef.php` — immutable VO `{path, sha256}`.
- `app/Services/AI/Memory/Episodic/EpisodeBlobWriter.php` — atomic write protocol.
- `app/Services/AI/Memory/Episodic/EpisodeBlobLocator.php` — resolve a blob path across hot + cold disks.
- `app/Services/AI/Memory/Episodic/EpisodeRetriever.php` — `findEpisodes(clientId, limit, since)` (SQL list).
- `app/Services/AI/Memory/Episodic/EpisodeProjection.php` — list/detail read model (detail lazy-loads the blob).

**Create (commands):**
- `app/Console/Commands/FynEpisodicBackfillBlobs.php` — `fyn:episodic:backfill-blobs`
- `app/Console/Commands/FynEpisodicColdArchive.php` — `fyn:episodic:cold-archive`
- `app/Console/Commands/FynEpisodicPurge.php` — `fyn:episodic:purge`
- `app/Console/Commands/FynEpisodicReconcile.php` — `fyn:episodic:reconcile`
- `app/Console/Commands/FynUserErase.php` — `fyn:user:erase {user}`

**Create (migrations):**
- `..._add_episode_columns_to_ai_messages.php`
- `..._add_hash_scheme_to_ai_audit_events.php`

**Create (frontend):**
- `resources/js/views/Admin/EpisodicComplianceLog.vue`
- `resources/js/components/Advisor/ClientSessionLog.vue` (panel embedded in `AdvisorClientDetail.vue`)

**Modify:**
- `app/Models/AiMessage.php` — fillable + casts for new columns.
- `app/Services/AI/Pointers/FetchDispatcher.php` — record into the collector.
- `app/Services/AI/AuditChainService.php` — versioned hash + `appendEpisode()`.
- `app/Console/Commands/AiAuditVerifyChainCommand.php` — v2 blob re-hash.
- `app/Traits/HasAiChat.php` — post-`saveMessage` hook (flush collector, write blob, append episode event) around line 873.
- `app/Http/Controllers/Api/AiAuditController.php` — read-only episode endpoints.
- `routes/api.php` — register the endpoints.
- `resources/js/views/Advisor/AdvisorClientDetail.vue` — embed `ClientSessionLog`.
- `app/Console/Kernel.php` — schedule reconcile (nightly) + cold-archive (weekly).
- `CLAUDE.md` — deploy runbook + custom-command table entries.

**Shared types (exact names/signatures used across tasks):**
- `FetchProvenanceCollector { record(array $entry): void; all(): array; reset(): void }`
- `EpisodeBlobRef { string $path; string $sha256 }`
- `EpisodeBlobData { string $episodeId; int $conversationId; int $clientId; string $timestamp; ?string $persona; ?string $module; ?array $proceduralVersion; ?string $semanticSnapshotId; ?string $modelUsed; string $systemPrompt; string $assembledContext; ?string $reasoningTrace; ?array $toolCalls; ?array $toolResults }`
- `EpisodeBlobWriter::write(AiMessage $message, EpisodeBlobData $data): EpisodeBlobRef`
- `EpisodeBlobLocator::resolve(string $relativePath): ?string` (absolute path or null)
- `AuditChainService::appendEpisode(array $event): AiAuditEvent` (writes `hash_scheme = 2`, `tool_name = '__episode__'`)

---

### Task 1: Migration — episode columns on `ai_messages`

**Files:**
- Create: `database/migrations/2026_06_01_000001_add_episode_columns_to_ai_messages.php`
- Modify: `app/Models/AiMessage.php`
- Test: `tests/Unit/Models/AiMessageEpisodeColumnsTest.php`

- [ ] **Step 1: Write the migration.**
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->json('procedural_version')->nullable()->after('model_used');
            $table->char('semantic_snapshot_id', 64)->nullable()->after('procedural_version');
            $table->json('fetch_provenance')->nullable()->after('semantic_snapshot_id');
            $table->string('blob_md_path', 255)->nullable()->after('fetch_provenance');
            $table->char('blob_md_sha256', 64)->nullable()->after('blob_md_path');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table): void {
            $table->dropColumn([
                'procedural_version',
                'semantic_snapshot_id',
                'fetch_provenance',
                'blob_md_path',
                'blob_md_sha256',
            ]);
        });
    }
};
```

- [ ] **Step 2: Add to `AiMessage` fillable + casts.** In `app/Models/AiMessage.php`, add to `$fillable` (after `'metadata'`): `'procedural_version', 'semantic_snapshot_id', 'fetch_provenance', 'blob_md_path', 'blob_md_sha256'`. Add to `$casts`: `'procedural_version' => 'array', 'fetch_provenance' => 'array'`.

- [ ] **Step 3: Write the test** `tests/Unit/Models/AiMessageEpisodeColumnsTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\AiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists and casts the new episode columns', function (): void {
    $msg = AiMessage::factory()->create([
        'procedural_version' => ['fyn.advice.overlay@1.0.0'],
        'semantic_snapshot_id' => str_repeat('a', 64),
        'fetch_provenance' => [['pointer_id' => 'isa', 'handler' => 'tax_allowance', 'source_label' => 'TaxConfigService', 'source_version' => '2026/27', 'digest' => 'abcd']],
        'blob_md_path' => 'episodic/2026/06/01/1/1.md',
        'blob_md_sha256' => str_repeat('b', 64),
    ]);

    $msg->refresh();

    expect($msg->procedural_version)->toBe(['fyn.advice.overlay@1.0.0'])
        ->and($msg->fetch_provenance[0]['handler'])->toBe('tax_allowance')
        ->and($msg->semantic_snapshot_id)->toHaveLength(64)
        ->and($msg->blob_md_path)->toBe('episodic/2026/06/01/1/1.md');
});
```

- [ ] **Step 4: Run migration on the test DB + run test.**
Run: `DB_DATABASE=laravel_testing php artisan migrate && DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Models/AiMessageEpisodeColumnsTest.php`
Expected: PASS. (Also run `php artisan migrate` on the dev DB so local chat keeps working; then `php artisan db:seed` is NOT needed — additive nullable columns.)

- [ ] **Step 5: Pint + commit.**
```bash
./vendor/bin/pint app/Models/AiMessage.php database/migrations/2026_06_01_000001_add_episode_columns_to_ai_messages.php
git add database/migrations/2026_06_01_000001_add_episode_columns_to_ai_messages.php app/Models/AiMessage.php tests/Unit/Models/AiMessageEpisodeColumnsTest.php
git commit -m "feat(coala): add episode columns to ai_messages (phase 2)"
```

---

### Task 2: `FetchProvenanceCollector` (request-scoped) + dispatcher wiring

**Files:**
- Create: `app/Services/AI/Memory/Episodic/FetchProvenanceCollector.php`
- Modify: `app/Services/AI/Pointers/FetchDispatcher.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Unit/Services/AI/Memory/Episodic/FetchProvenanceCollectorTest.php`, `tests/Unit/Services/AI/Pointers/FetchDispatcherProvenanceTest.php`

- [ ] **Step 1: Write the failing collector test** `tests/Unit/Services/AI/Memory/Episodic/FetchProvenanceCollectorTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;

it('accumulates, returns, and resets provenance entries', function (): void {
    $c = new FetchProvenanceCollector();
    expect($c->all())->toBe([]);

    $c->record(['pointer_id' => 'isa', 'handler' => 'tax_allowance', 'source_label' => 'TaxConfigService', 'source_version' => '2026/27', 'digest' => 'd1']);
    $c->record(['pointer_id' => 'rec', 'handler' => 'recommendations', 'source_label' => 'recommendation engine', 'source_version' => '2026-06-01', 'digest' => 'd2']);

    expect($c->all())->toHaveCount(2)
        ->and($c->all()[0]['handler'])->toBe('tax_allowance');

    $c->reset();
    expect($c->all())->toBe([]);
});

it('is the same instance within a request (scoped singleton)', function (): void {
    $a = app(FetchProvenanceCollector::class);
    $b = app(FetchProvenanceCollector::class);
    $a->record(['pointer_id' => 'x', 'handler' => 'h', 'source_label' => 's', 'source_version' => 'v', 'digest' => 'd']);
    expect($b->all())->toHaveCount(1);
});
```

- [ ] **Step 2: Run — expect FAIL.** `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Memory/Episodic/FetchProvenanceCollectorTest.php`

- [ ] **Step 3: Write `FetchProvenanceCollector.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/**
 * Request-scoped accumulator of pointer fetch-provenance for the current turn.
 * Both trigger paths (pre-fetch in FynContextAssembler, tool-mode in
 * CoordinatingAgent::executeTool) flow through FetchDispatcher, which records
 * here; HasAiChat flushes onto the assistant ai_messages row at persist time.
 * Bound `scoped` in the container — one instance per request, reset per turn.
 */
final class FetchProvenanceCollector
{
    /** @var list<array<string,string>> */
    private array $entries = [];

    /** @param array<string,string> $entry */
    public function record(array $entry): void
    {
        $this->entries[] = $entry;
    }

    /** @return list<array<string,string>> */
    public function all(): array
    {
        return $this->entries;
    }

    public function reset(): void
    {
        $this->entries = [];
    }
}
```

- [ ] **Step 4: Bind scoped** in `AppServiceProvider::register()` (next to the pointer bindings):
```php
        $this->app->scoped(\App\Services\AI\Memory\Episodic\FetchProvenanceCollector::class);
```

- [ ] **Step 5: Wire the dispatcher.** In `app/Services/AI/Pointers/FetchDispatcher.php`, inject the collector and record on success. Change the constructor to also take the collector, and after a successful fetch record its provenance. Read the current file first; the edit is:
```php
    public function __construct(
        private readonly FetchHandlerRegistry $handlers,
        private readonly \App\Services\AI\Memory\Episodic\FetchProvenanceCollector $collector,
    ) {}
```
and inside `run()`, after `$result = ...->fetch($ctx);` succeeds and before the optional `$message` block:
```php
        $this->collector->record($result->provenance($pointer->pointerId, $pointer->handler));
```
> Keep the existing `?AiMessage $message` direct-record path intact (legacy; the Task-5 `FetchDispatcherTest` still passes). The collector is the new primary channel. `FetchDispatcher` is already a container singleton — switch its binding to a non-shared resolution OR leave it singleton but resolve the scoped collector lazily; simplest: since the collector is `scoped` and the dispatcher is `singleton`, change the dispatcher binding to `bind` (not `singleton`) so it re-resolves the scoped collector per request. Confirm: `AppServiceProvider` currently has `$this->app->singleton(FetchDispatcher::class)` — change to `$this->app->bind(...)`.

- [ ] **Step 6: Write the dispatcher provenance test** `tests/Unit/Services/AI/Pointers/FetchDispatcherProvenanceTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchDispatcher;
use App\Services\AI\Pointers\FetchHandler;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\Pointer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('records provenance into the collector on a successful fetch', function (): void {
    $handler = new class implements FetchHandler {
        public function id(): string { return 'ok'; }
        public function fetch(FetchContext $ctx): FetchResult { return FetchResult::make('v', 'TaxConfigService', '2026/27'); }
    };
    $collector = new FetchProvenanceCollector();
    $d = new FetchDispatcher(new FetchHandlerRegistry([$handler]), $collector);
    $user = \App\Models\User::factory()->create();
    $pointer = new Pointer('isa', 't', ['isa'], 'both', 'ok', 'TaxConfigService', 1, 'b');

    $d->run($pointer, new FetchContext($user, 'isa allowance'));

    expect($collector->all())->toHaveCount(1)
        ->and($collector->all()[0]['pointer_id'])->toBe('isa')
        ->and($collector->all()[0]['source_version'])->toBe('2026/27');
});

it('records nothing when the handler fails', function (): void {
    $handler = new class implements FetchHandler {
        public function id(): string { return 'boom'; }
        public function fetch(FetchContext $ctx): FetchResult { throw new RuntimeException('down'); }
    };
    $collector = new FetchProvenanceCollector();
    $d = new FetchDispatcher(new FetchHandlerRegistry([$handler]), $collector);
    $user = \App\Models\User::factory()->create();
    $pointer = new Pointer('p', 't', ['x'], 'both', 'boom', 'S', 1, 'b');

    expect($d->run($pointer, new FetchContext($user, 'x')))->toBeNull()
        ->and($collector->all())->toBe([]);
});
```

- [ ] **Step 7: Run both test files — expect green. Then run the existing pointer suite to confirm no regression:** `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Pointers tests/Unit/Services/AI/Memory/Episodic` — all green (the Task-5 `FetchDispatcherTest` constructs `new FetchDispatcher(new FetchHandlerRegistry([...]))` with ONE arg — it will now fail to construct; UPDATE that test's two `new FetchDispatcher(...)` calls to pass `new FetchProvenanceCollector()` as the second arg, keeping all assertions). Pint. Commit:
```bash
git add app/Services/AI/Memory/Episodic/FetchProvenanceCollector.php app/Services/AI/Pointers/FetchDispatcher.php app/Providers/AppServiceProvider.php tests/Unit/Services/AI/Memory/Episodic/FetchProvenanceCollectorTest.php tests/Unit/Services/AI/Pointers/FetchDispatcherProvenanceTest.php tests/Unit/Services/AI/Pointers/FetchDispatcherTest.php
git commit -m "feat(coala): FetchProvenanceCollector + dispatcher records provenance (phase 2)"
```

---

### Task 3: `EpisodeBlobData` + `EpisodeBlobRef` VOs

**Files:**
- Create: `app/Services/AI/Memory/Episodic/EpisodeBlobData.php`, `app/Services/AI/Memory/Episodic/EpisodeBlobRef.php`
- Test: `tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobDataTest.php`

- [ ] **Step 1: Write `EpisodeBlobRef.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/** Where a written episode blob lives + its content hash. Immutable. */
final class EpisodeBlobRef
{
    public function __construct(
        public readonly string $path,    // relative, e.g. episodic/2026/06/01/1/1.md
        public readonly string $sha256,
    ) {}
}
```

- [ ] **Step 2: Write `EpisodeBlobData.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/** The verbatim per-turn forensic body, rendered to the .md blob. Immutable. */
final class EpisodeBlobData
{
    /**
     * @param list<string>|null $proceduralVersion
     * @param array<mixed>|null $toolCalls
     * @param array<mixed>|null $toolResults
     */
    public function __construct(
        public readonly string $episodeId,
        public readonly int $conversationId,
        public readonly int $clientId,
        public readonly string $timestamp,        // ISO8601 UTC
        public readonly ?string $persona,
        public readonly ?string $module,
        public readonly ?array $proceduralVersion,
        public readonly ?string $semanticSnapshotId,
        public readonly ?string $modelUsed,
        public readonly string $systemPrompt,
        public readonly string $assembledContext,
        public readonly ?string $reasoningTrace,
        public readonly ?array $toolCalls,
        public readonly ?array $toolResults,
    ) {}

    /** Render to the .md body: YAML frontmatter + verbatim sections. */
    public function toMarkdown(): string
    {
        $fm = [
            'episode_id' => $this->episodeId,
            'conversation_id' => $this->conversationId,
            'client_id' => $this->clientId,
            'timestamp' => $this->timestamp,
            'persona' => $this->persona,
            'module' => $this->module,
            'procedural_version' => $this->proceduralVersion,
            'semantic_snapshot_id' => $this->semanticSnapshotId,
            'model_used' => $this->modelUsed,
        ];
        $yaml = \Symfony\Component\Yaml\Yaml::dump($fm, 2, 2);

        $sections = "## system_prompt\n\n{$this->systemPrompt}\n\n"
            ."## assembled_context\n\n{$this->assembledContext}\n";
        if ($this->reasoningTrace !== null && $this->reasoningTrace !== '') {
            $sections .= "\n## reasoning_trace\n\n{$this->reasoningTrace}\n";
        }
        $sections .= "\n## tool_calls\n\n```json\n".json_encode($this->toolCalls ?? [], JSON_PRETTY_PRINT)."\n```\n";
        $sections .= "\n## tool_results\n\n```json\n".json_encode($this->toolResults ?? [], JSON_PRETTY_PRINT)."\n```\n";

        return "---\n{$yaml}---\n\n{$sections}";
    }
}
```

- [ ] **Step 3: Write the test** `tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobDataTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Episodic\EpisodeBlobData;

it('renders frontmatter and verbatim sections, omitting an empty reasoning_trace', function (): void {
    $data = new EpisodeBlobData(
        episodeId: '42', conversationId: 1, clientId: 7, timestamp: '2026-06-01T10:00:00Z',
        persona: 'advice', module: 'retirement', proceduralVersion: ['fyn.advice.overlay@1.0.0'],
        semanticSnapshotId: str_repeat('a', 64), modelUsed: 'grok-4',
        systemPrompt: 'SYS', assembledContext: 'CTX', reasoningTrace: null,
        toolCalls: [['name' => 'list_goals']], toolResults: [['ok' => true]],
    );

    $md = $data->toMarkdown();

    expect($md)->toStartWith('---')
        ->and($md)->toContain('episode_id:')
        ->and($md)->toContain('## system_prompt')
        ->and($md)->toContain('SYS')
        ->and($md)->toContain('## assembled_context')
        ->and($md)->toContain('## tool_calls')
        ->and($md)->not->toContain('## reasoning_trace');   // omitted when null
});

it('includes a non-empty reasoning_trace section', function (): void {
    $data = new EpisodeBlobData('1', 1, 1, '2026-06-01T10:00:00Z', null, null, null, null, null, 'S', 'C', 'PLAN', null, null);
    expect($data->toMarkdown())->toContain('## reasoning_trace')->toContain('PLAN');
});
```

- [ ] **Step 4: Run — expect green.** `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobDataTest.php`. Pint. Commit:
```bash
git add app/Services/AI/Memory/Episodic/EpisodeBlobData.php app/Services/AI/Memory/Episodic/EpisodeBlobRef.php tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobDataTest.php
git commit -m "feat(coala): EpisodeBlobData + EpisodeBlobRef value objects (phase 2)"
```

---

### Task 4: `EpisodeBlobWriter` (atomic protocol) + `EpisodeBlobLocator`

**Files:**
- Create: `app/Services/AI/Memory/Episodic/EpisodeBlobWriter.php`, `app/Services/AI/Memory/Episodic/EpisodeBlobLocator.php`
- Test: `tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobWriterTest.php`

- [ ] **Step 1: Write the failing test** `tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobWriterTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\AiMessage;
use App\Services\AI\Memory\Episodic\EpisodeBlobData;
use App\Services\AI\Memory\Episodic\EpisodeBlobLocator;
use App\Services\AI\Memory\Episodic\EpisodeBlobWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

function blobData(AiMessage $m): EpisodeBlobData
{
    return new EpisodeBlobData(
        episodeId: (string) $m->id, conversationId: $m->conversation_id, clientId: 7,
        timestamp: '2026-06-01T10:00:00Z', persona: 'advice', module: 'retirement',
        proceduralVersion: null, semanticSnapshotId: null, modelUsed: 'grok-4',
        systemPrompt: 'SYS', assembledContext: 'CTX', reasoningTrace: null, toolCalls: null, toolResults: null,
    );
}

it('writes the blob atomically and returns a ref with the correct sha + sharded path', function (): void {
    $msg = AiMessage::factory()->create();
    $ref = app(EpisodeBlobWriter::class)->write($msg, blobData($msg));

    expect($ref->path)->toContain('episodic/2026/06/01/')
        ->and($ref->path)->toEndWith("/{$msg->id}.md")
        ->and(Storage::disk('local')->exists($ref->path))->toBeTrue();

    $body = Storage::disk('local')->get($ref->path);
    expect($ref->sha256)->toBe(hash('sha256', $body))
        ->and($body)->toContain('## system_prompt');

    // no .tmp left behind
    expect(Storage::disk('local')->exists($ref->path.'.tmp'))->toBeFalse();
});

it('locator resolves a written blob and returns null for a missing one', function (): void {
    $msg = AiMessage::factory()->create();
    $ref = app(EpisodeBlobWriter::class)->write($msg, blobData($msg));
    $locator = app(EpisodeBlobLocator::class);

    expect($locator->resolve($ref->path))->not->toBeNull()
        ->and($locator->resolve('episodic/2026/06/01/999/999.md'))->toBeNull();
});
```
> The blob date shard derives from `EpisodeBlobData::$timestamp` (UTC), not "now" — confirms determinism. If `Storage::fake` makes the absolute-path resolve awkward, have `EpisodeBlobLocator::resolve` return the storage-relative path when the disk reports `exists()`, and assert on that; keep the "null for missing" assertion.

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Write `EpisodeBlobWriter.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use App\Models\AiMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Atomic episodic blob writer (plan §"Atomic write protocol"):
 * compose -> write .tmp -> fsync -> atomic rename -> sha256.
 * Path is date-sharded by the episode timestamp (UTC), then conversation,
 * then message id — a contract retention/erase scripts depend on.
 */
final class EpisodeBlobWriter
{
    public function write(AiMessage $message, EpisodeBlobData $data): EpisodeBlobRef
    {
        $disk = Storage::disk('local');
        $date = Carbon::parse($data->timestamp)->utc();
        $dir = sprintf('episodic/%s/%d', $date->format('Y/m/d'), $data->conversationId);
        $path = "{$dir}/{$message->id}.md";
        $tmp = "{$path}.tmp";

        $body = $data->toMarkdown();

        $disk->put($tmp, $body);                 // Laravel writes + flushes
        // Atomic rename on the same filesystem. Storage has no rename for local
        // reliably across drivers, so move via the underlying path.
        $disk->delete($path);                    // ensure target clear (idempotent re-write)
        $disk->move($tmp, $path);

        $sha = hash('sha256', $disk->get($path));

        return new EpisodeBlobRef($path, $sha);
    }
}
```
> Note on atomicity: `League\Flysystem` local adapter's `move()` uses `rename()` under the hood on the same disk, which is POSIX-atomic. If the execution reveals `move()` is not atomic for the configured driver, drop to `rename()` on `Storage::disk('local')->path($tmp)` → `->path($path)` directly. Confirm at execution and use the atomic primitive available.

- [ ] **Step 4: Write `EpisodeBlobLocator.php`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use Illuminate\Support\Facades\Storage;

/** Resolve an episodic blob across hot (episodic/) and cold (episodic-cold/) storage. */
final class EpisodeBlobLocator
{
    public function resolve(string $relativePath): ?string
    {
        $disk = Storage::disk('local');
        if ($disk->exists($relativePath)) {
            return $relativePath;
        }
        $cold = str_replace('episodic/', 'episodic-cold/', $relativePath);
        if ($cold !== $relativePath && $disk->exists($cold)) {
            return $cold;
        }

        return null;
    }

    public function get(string $relativePath): ?string
    {
        $resolved = $this->resolve($relativePath);

        return $resolved === null ? null : Storage::disk('local')->get($resolved);
    }
}
```

- [ ] **Step 5: Run — expect green. Pint. Commit:**
```bash
git add app/Services/AI/Memory/Episodic/EpisodeBlobWriter.php app/Services/AI/Memory/Episodic/EpisodeBlobLocator.php tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobWriterTest.php
git commit -m "feat(coala): EpisodeBlobWriter atomic protocol + EpisodeBlobLocator (phase 2)"
```

---

### Task 5: Migration — `hash_scheme` on `ai_audit_events`

**Files:**
- Create: `database/migrations/2026_06_01_000002_add_hash_scheme_to_ai_audit_events.php`
- Test: `tests/Unit/Models/AiAuditEventHashSchemeTest.php`

- [ ] **Step 1: Write the migration.**
```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_audit_events', function (Blueprint $table): void {
            $table->unsignedTinyInteger('hash_scheme')->default(1)->after('signature');
        });
    }

    public function down(): void
    {
        Schema::table('ai_audit_events', function (Blueprint $table): void {
            $table->dropColumn('hash_scheme');
        });
    }
};
```

- [ ] **Step 2: Write the test** `tests/Unit/Models/AiAuditEventHashSchemeTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\AuditChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults existing-style appends to hash_scheme 1', function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    $event = app(AuditChainService::class)->append([
        'user_id' => 1, 'tool_name' => 'list_goals', 'operation' => 'read', 'status' => 'dispatched',
    ]);
    expect((int) $event->hash_scheme)->toBe(1);
});
```

- [ ] **Step 3: Run migration + test — expect green.** `DB_DATABASE=laravel_testing php artisan migrate && DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Unit/Models/AiAuditEventHashSchemeTest.php`. Also run `php artisan migrate` on dev DB. Pint. Commit:
```bash
git add database/migrations/2026_06_01_000002_add_hash_scheme_to_ai_audit_events.php tests/Unit/Models/AiAuditEventHashSchemeTest.php
git commit -m "feat(coala): add hash_scheme to ai_audit_events (phase 2)"
```

---

### Task 6: `AuditChainService::appendEpisode()` — v2 versioned hash

**Files:**
- Modify: `app/Services/AI/AuditChainService.php`
- Test: `tests/Unit/Services/AI/AuditChainEpisodeTest.php`

READ `app/Services/AI/AuditChainService.php` IN FULL first — note `computeRowHash`, `HASHED_FIELDS`, `extractHashedPayload`, the `signed_at` ISO string in the hash, and the existing `append()`. The v2 scheme reuses v1's payload + appends `blob_md_sha256 ‖ semantic_snapshot_id ‖ provenance_digest` to the hash input, and writes `hash_scheme = 2`, `tool_name = '__episode__'`. Existing rows + `append()` are UNTOUCHED.

- [ ] **Step 1: Write the failing test** `tests/Unit/Services/AI/AuditChainEpisodeTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\AuditChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config(['app.ai_audit_hmac_key' => 'test-key']));

it('appends a v2 __episode__ event binding the blob sha and verifies in-chain', function (): void {
    $svc = app(AuditChainService::class);

    // a normal v1 tool-dispatch row first
    $svc->append(['user_id' => 1, 'tool_name' => 'list_goals', 'operation' => 'read', 'status' => 'dispatched']);

    // then a v2 episode attestation
    $ep = $svc->appendEpisode([
        'user_id' => 1, 'conversation_id' => 3, 'entity_id' => 99,
        'blob_md_sha256' => str_repeat('a', 64),
        'semantic_snapshot_id' => str_repeat('b', 64),
        'fetch_provenance' => [['digest' => 'd1'], ['digest' => 'd2']],
    ]);

    expect($ep->tool_name)->toBe('__episode__')
        ->and((int) $ep->hash_scheme)->toBe(2)
        ->and((int) $ep->entity_id)->toBe(99);

    // whole chain (mixed v1 + v2) verifies green
    expect($svc->verifyChain()['chain_valid'])->toBeTrue();
});

it('a v1 chain still verifies green after the v2 method exists (no reserialisation)', function (): void {
    $svc = app(AuditChainService::class);
    $svc->append(['user_id' => 1, 'tool_name' => 'a', 'operation' => 'read', 'status' => 'dispatched']);
    $svc->append(['user_id' => 1, 'tool_name' => 'b', 'operation' => 'read', 'status' => 'dispatched']);
    expect($svc->verifyChain()['chain_valid'])->toBeTrue();
});
```

- [ ] **Step 2: Run — expect FAIL** (`appendEpisode` undefined).

- [ ] **Step 3: Implement `appendEpisode()` + versioned hashing.** Add to `AuditChainService`:
  - A constant `EPISODE_TOOL = '__episode__'`.
  - `appendEpisode(array $event): AiAuditEvent` — mirrors `append()` but: sets `tool_name = self::EPISODE_TOOL`, `operation = 'persist'`, `entity_type = 'ai_message'`, `entity_id = $event['entity_id']`, computes the row hash with the **v2** input (v1 payload serialisation ‖ `($event['blob_md_sha256'] ?? '')` ‖ `($event['semantic_snapshot_id'] ?? '')` ‖ a stable digest of `fetch_provenance` = `hash('sha256', json_encode(array_column($event['fetch_provenance'] ?? [], 'digest')))`), and persists `hash_scheme = 2`.
  - Refactor the row-hash so the **v1 path is byte-identical to today** (do NOT change `computeRowHash`'s existing behaviour). Add a `computeEpisodeRowHash($prevHash, $payload, $signedAtIso, $blobSha, $snapshotId, $provDigest)` that calls the existing v1 serialisation then appends the three extra fields to the hashed string. `append()` is unchanged.
  - `verifyChain()` selects per row: if `row->hash_scheme === 2`, recompute via the episode hash using the row's `entity_id`-linked `ai_messages.blob_md_sha256` / `semantic_snapshot_id` / `fetch_provenance` (fetch the message), AND re-hash the blob file via `EpisodeBlobLocator` to confirm the on-disk `.md` still matches `blob_md_sha256` (fail with a distinct reason on mismatch/missing). Otherwise use the existing v1 recompute.

> Implementation detail to confirm while reading the file: `verifyChain()` currently reconstructs purely from `ai_audit_events` columns. For v2 it must join to `ai_messages` by `entity_id` to get the blob sha/snapshot/provenance used at append time. Store those three on the `ai_audit_events` row too (in `result_summary` JSON) so verification is self-contained and does not depend on a mutable `ai_messages` row — RECOMMENDED: put `{blob_md_sha256, semantic_snapshot_id, provenance_digest}` into `result_summary` at append, and verify against that + re-hash the on-disk blob against `blob_md_sha256`. This keeps the chain self-verifying.

- [ ] **Step 4: Run — expect green (both tests).** Run the EXISTING audit suite too: `DB_DATABASE=laravel_testing ./vendor/bin/pest --filter=Audit` and any `AuditChain`/`verify-chain` tests — confirm pre-existing chain tests stay green. Pint. Commit:
```bash
git add app/Services/AI/AuditChainService.php tests/Unit/Services/AI/AuditChainEpisodeTest.php
git commit -m "feat(coala): AuditChainService v2 __episode__ attestation (phase 2)"
```

---

### Task 7: `verify-chain` command — re-hash v2 episode blobs

**Files:**
- Modify: `app/Console/Commands/AiAuditVerifyChainCommand.php`
- Test: `tests/Feature/Console/AiAuditVerifyChainEpisodeTest.php`

READ the command + `verifyChain()` first. The verifier already returns `{chain_valid, broken_at, row_count}`. For v2 rows it must additionally re-hash the on-disk blob and surface an operational-vs-tamper reason.

- [ ] **Step 1: Write the failing feature test** `tests/Feature/Console/AiAuditVerifyChainEpisodeTest.php`:
```php
<?php

declare(strict_types=1);

use App\Services\AI\AuditChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');
});

it('passes when the v2 episode blob on disk matches its recorded sha', function (): void {
    Storage::disk('local')->put('episodic/2026/06/01/3/99.md', 'BLOB');
    $sha = hash('sha256', 'BLOB');
    app(AuditChainService::class)->appendEpisode([
        'user_id' => 1, 'conversation_id' => 3, 'entity_id' => 99,
        'blob_md_sha256' => $sha, 'blob_md_path' => 'episodic/2026/06/01/3/99.md',
        'semantic_snapshot_id' => null, 'fetch_provenance' => [],
    ]);

    $this->artisan('ai:audit:verify-chain')->assertExitCode(0);
});

it('fails when a v2 episode blob has been tampered on disk', function (): void {
    Storage::disk('local')->put('episodic/2026/06/01/3/99.md', 'BLOB');
    app(AuditChainService::class)->appendEpisode([
        'user_id' => 1, 'conversation_id' => 3, 'entity_id' => 99,
        'blob_md_sha256' => hash('sha256', 'BLOB'), 'blob_md_path' => 'episodic/2026/06/01/3/99.md',
        'semantic_snapshot_id' => null, 'fetch_provenance' => [],
    ]);
    Storage::disk('local')->put('episodic/2026/06/01/3/99.md', 'TAMPERED');

    $this->artisan('ai:audit:verify-chain')->assertExitCode(1);
});
```
> `appendEpisode` should also accept + store `blob_md_path` in `result_summary` so the verifier knows where to look. Add `blob_md_path` to the episode payload stored in `result_summary` in Task 6 if not already (adjust Task 6's `result_summary` to include `blob_md_path`).

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Update the command** so that, after the standard chain walk, for each `hash_scheme = 2` row it loads `result_summary.blob_md_path`, resolves via `EpisodeBlobLocator`, recomputes SHA-256, and compares to `result_summary.blob_md_sha256`. On mismatch/missing, exit non-zero and print a `{path, expected_sha, actual_state}` line (actual_state ∈ `missing|modified`). Keep the existing chain-break behaviour and exit codes for v1.

- [ ] **Step 4: Run — expect green. Pint. Commit:**
```bash
git add app/Console/Commands/AiAuditVerifyChainCommand.php tests/Feature/Console/AiAuditVerifyChainEpisodeTest.php
git commit -m "feat(coala): verify-chain re-hashes v2 episode blobs (phase 2)"
```

---

### Task 8: Wire the persist hook into `HasAiChat`

**Files:**
- Modify: `app/Traits/HasAiChat.php` (after the assistant `saveMessage` at ~line 873)
- Test: `tests/Feature/AI/EpisodePersistenceTest.php`

READ `app/Traits/HasAiChat.php` around lines 840–900. The assistant row is saved at `$assistantMessage = $this->saveMessage($conversation, 'assistant', $sanitisedResponse, $assistantExtra);` (~873). Insert the episode-persist hook immediately after, BEFORE the advice-log block.

- [ ] **Step 1: Write the failing feature test** `tests/Feature/AI/EpisodePersistenceTest.php`. Drive a real assistant-message persistence through the trait's path. Since `HasAiChat` is a trait on `CoordinatingAgent`, the cleanest test calls the persist hook via a small harness: assert that after a turn that recorded provenance, the assistant `ai_messages` row has `blob_md_path`, `blob_md_sha256`, `fetch_provenance`, and that a v2 `__episode__` `ai_audit_events` row exists for it. Use the existing Fyn stream-mock harness under `tests/Support` (grep `tests/Support/Fyn`) — read an existing `tests/Feature/AI` test that drives a full turn and mirror it. Assertions:
```php
// after driving one advice turn that triggers a prefetch pointer (e.g. "show my accounts"):
$assistant = AiMessage::where('role', 'assistant')->latest('id')->first();
expect($assistant->blob_md_path)->not->toBeNull()
    ->and($assistant->blob_md_sha256)->toHaveLength(64)
    ->and(Storage::disk('local')->exists($assistant->blob_md_path))->toBeTrue();
$episodeEvent = \App\Models\AiAuditEvent::where('tool_name', '__episode__')->where('entity_id', $assistant->id)->first();
expect($episodeEvent)->not->toBeNull()->and((int) $episodeEvent->hash_scheme)->toBe(2);
```
> If wiring a full streamed turn in a test is too heavy, extract the hook into a private method `persistEpisode(AiMessage $assistant, string $systemPrompt, string $assembledContext, ?string $model, array $toolCalls, array $toolResults): void` and unit-test THAT method directly with a created `AiMessage` + a pre-seeded `FetchProvenanceCollector`. Prefer the extracted-method test for determinism; still assert the row columns + the `__episode__` event.

- [ ] **Step 2: Run — expect FAIL.**

- [ ] **Step 3: Implement the hook.** Immediately after line ~873 (`$assistantMessage = $this->saveMessage(...)`), add a call to a new private method `persistEpisode(...)` that:
  1. Resolves `app(FetchProvenanceCollector::class)`, reads `all()`, then `reset()`.
  2. Reads `semantic_snapshot_id` from the request-scoped capture (the assembler stamps `app(SemanticRetriever::class)->snapshotId()` into a scoped holder — if no such holder exists yet, compute it here from the retriever's last snapshot, or pass `null` for v1 and wire fully in a follow-up; prefer reading the scoped value).
  3. Builds `EpisodeBlobData` from `$assistantMessage`, `$systemPrompt`, `$this->assembledContext`, tool calls/results, persona, model.
  4. `app(EpisodeBlobWriter::class)->write($assistantMessage, $data)` → updates the row: `blob_md_path`, `blob_md_sha256`, `fetch_provenance` (= collector entries), `semantic_snapshot_id`.
  5. `app(AuditChainService::class)->appendEpisode([... entity_id => $assistantMessage->id, blob_md_path, blob_md_sha256, semantic_snapshot_id, fetch_provenance ...])`.
  6. Wrap in try/catch(Throwable) → `report($e)`; a blob/audit failure must NOT break the turn (the row is already saved with the verbatim columns as fallback). Log + continue.

- [ ] **Step 4: Run — expect green. Run the broad AI suites for no regression:** `DB_DATABASE=laravel_testing ./vendor/bin/pest tests/Feature/AI tests/Unit/Services/AI/Fyn` — green. Pint. Commit:
```bash
git add app/Traits/HasAiChat.php tests/Feature/AI/EpisodePersistenceTest.php
git commit -m "feat(coala): persist episode blob + provenance + attestation per turn (phase 2)"
```

---

### Task 9: `EpisodeRetriever` + `EpisodeProjection`

**Files:**
- Create: `app/Services/AI/Memory/Episodic/EpisodeRetriever.php`, `app/Services/AI/Memory/Episodic/EpisodeProjection.php`
- Test: `tests/Unit/Services/AI/Memory/Episodic/EpisodeRetrieverTest.php`, `tests/Unit/Services/AI/Memory/Episodic/EpisodeProjectionTest.php`

READ how `ai_messages` links to a user: `AiMessage -> conversation (AiConversation) -> user_id`. Confirm `AiConversation` has `user_id` (grep `database/migrations/*ai_conversations*`). `findEpisodes` filters by the conversation's `user_id`.

- [ ] **Step 1: Write the failing `EpisodeRetrieverTest`:**
```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use App\Services\AI\Memory\Episodic\EpisodeRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns a user\'s assistant episodes newest-first, limited, SQL only', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    foreach (range(1, 3) as $i) {
        AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => "episodic/p{$i}.md"]);
    }
    // a different user's episode must not leak
    $other = AiConversation::factory()->create();
    AiMessage::factory()->create(['conversation_id' => $other->id, 'role' => 'assistant']);

    $episodes = app(EpisodeRetriever::class)->findEpisodes($user->id, 2, null);

    expect($episodes)->toHaveCount(2)
        ->and($episodes->first()->conversation->user_id)->toBe($user->id);
});
```
> Confirm the `AiConversation` factory exists + sets `user_id`; adjust if the relationship column differs.

- [ ] **Step 2: Run — expect FAIL. Step 3: Write `EpisodeRetriever`:**
```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

use App\Models\AiMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Typed episodic retrieval — SQL-only list path (no blob I/O). */
final class EpisodeRetriever
{
    public function findEpisodes(int $clientId, int $limit = 20, ?Carbon $since = null): Collection
    {
        return AiMessage::query()
            ->where('role', 'assistant')
            ->whereHas('conversation', fn ($q) => $q->where('user_id', $clientId))
            ->when($since !== null, fn ($q) => $q->where('created_at', '>=', $since))
            ->with('conversation:id,user_id')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
```

- [ ] **Step 4: Write the failing `EpisodeProjectionTest`** asserting `list()` returns SQL fields only and `detail($id)` includes the lazy-loaded blob body. Use `Storage::fake('local')` + write a blob via `EpisodeBlobWriter`, then assert `detail()` returns the markdown body via `EpisodeBlobLocator`.

- [ ] **Step 5: Write `EpisodeProjection`** with `list(int $clientId, int $limit, ?Carbon $since): array` (maps each episode to `{id, created_at, module, persona, model_used, tool_count, has_blob, semantic_snapshot_id}`) and `detail(int $messageId): array` (the SQL row's structured fields + `blob_body` from `EpisodeBlobLocator::get($row->blob_md_path)`, null if unresolved). Module derives from `metadata` or `null`.

- [ ] **Step 6: Run both — green. Pint. Commit:**
```bash
git add app/Services/AI/Memory/Episodic/EpisodeRetriever.php app/Services/AI/Memory/Episodic/EpisodeProjection.php tests/Unit/Services/AI/Memory/Episodic/EpisodeRetrieverTest.php tests/Unit/Services/AI/Memory/Episodic/EpisodeProjectionTest.php
git commit -m "feat(coala): EpisodeRetriever + EpisodeProjection read model (phase 2)"
```

---

### Task 10: `fyn:episodic:backfill-blobs`

**Files:**
- Create: `app/Console/Commands/FynEpisodicBackfillBlobs.php`
- Test: `tests/Feature/Console/FynEpisodicBackfillBlobsTest.php`

- [ ] **Step 1: Write the failing test** `tests/Feature/Console/FynEpisodicBackfillBlobsTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\AiMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake('local'));

it('backfills a blob for a legacy row and is idempotent + retrievable', function (): void {
    $msg = AiMessage::factory()->create([
        'role' => 'assistant', 'system_prompt' => 'SYS', 'assembled_context' => 'CTX', 'blob_md_path' => null,
    ]);

    $this->artisan('fyn:episodic:backfill-blobs')->assertExitCode(0);

    $msg->refresh();
    expect($msg->blob_md_path)->not->toBeNull()
        ->and($msg->blob_md_sha256)->toHaveLength(64)
        ->and(Storage::disk('local')->exists($msg->blob_md_path))->toBeTrue();
    $firstPath = $msg->blob_md_path;

    // idempotent: second run skips already-backfilled rows
    $this->artisan('fyn:episodic:backfill-blobs')->assertExitCode(0);
    expect($msg->fresh()->blob_md_path)->toBe($firstPath);

    // retrievable as a first-class episode (CSJ accessibility guarantee)
    expect(app(\App\Services\AI\Memory\Episodic\EpisodeProjection::class)->detail($msg->id)['blob_body'])->toContain('SYS');
});
```

- [ ] **Step 2: Run — FAIL. Step 3: Write the command** `fyn:episodic:backfill-blobs`: chunk over `AiMessage::whereNull('blob_md_path')->where(fn($q)=>$q->whereNotNull('system_prompt')->orWhereNotNull('assembled_context'))`; for each, build `EpisodeBlobData` from the row (timestamp = `created_at`, clientId via `conversation->user_id`), write via `EpisodeBlobWriter`, update `blob_md_path`+`blob_md_sha256`. Does NOT append a `__episode__` audit event (history not re-chained — spec §4/§5). Print count backfilled + count skipped. Batched (chunkById 200), resumable.

- [ ] **Step 4: Run — green. Pint. Commit:**
```bash
git add app/Console/Commands/FynEpisodicBackfillBlobs.php tests/Feature/Console/FynEpisodicBackfillBlobsTest.php
git commit -m "feat(coala): fyn:episodic:backfill-blobs (phase 2)"
```

---

### Task 11: Retention — `cold-archive`, `purge`, `reconcile`

**Files:**
- Create: `app/Console/Commands/FynEpisodicColdArchive.php`, `FynEpisodicPurge.php`, `FynEpisodicReconcile.php`
- Modify: `app/Console/Kernel.php`
- Test: `tests/Feature/Console/FynEpisodicRetentionTest.php`

- [ ] **Step 1: Write the failing test** covering: (a) cold-archive moves a >12-month blob from `episodic/` to `episodic-cold/` and `EpisodeBlobLocator` still resolves it; (b) purge with no `--force` is a dry-run (deletes nothing) and with `--force` deletes a >6-year row + its cold blob; (c) reconcile flags an orphan blob (no matching `ai_messages` row). Use `Storage::fake('local')` + factory rows with crafted `created_at`.
```php
// sketch — cold archive
it('cold-archives blobs older than 12 months and the locator still resolves them', function (): void {
    Storage::fake('local');
    $msg = AiMessage::factory()->create(['role' => 'assistant', 'created_at' => now()->subMonths(13), 'blob_md_path' => 'episodic/2025/05/01/1/1.md', 'blob_md_sha256' => str_repeat('a',64)]);
    Storage::disk('local')->put($msg->blob_md_path, 'BLOB');

    $this->artisan('fyn:episodic:cold-archive')->assertExitCode(0);

    expect(Storage::disk('local')->exists('episodic/2025/05/01/1/1.md'))->toBeFalse()
        ->and(Storage::disk('local')->exists('episodic-cold/2025/05/01/1/1.md'))->toBeTrue()
        ->and(app(\App\Services\AI\Memory\Episodic\EpisodeBlobLocator::class)->resolve('episodic/2025/05/01/1/1.md'))->not->toBeNull();
});
```

- [ ] **Step 2: Run — FAIL. Step 3: Write the three commands:**
  - `fyn:episodic:cold-archive` — find rows with `created_at < now()->subMonths(12)` and a hot blob; move each `episodic/...` → `episodic-cold/...` (same sub-path); SQL row untouched. Idempotent, batched. Log moved count.
  - `fyn:episodic:purge` — `--force` flag (default dry-run). Rows with `created_at < now()->subYears(6)`: delete the SQL row + the cold/hot blob. Dry-run prints what WOULD be purged. Log purged count. (Chain verification past a purge point intentionally fails for those entries — documented; not this command's concern.)
  - `fyn:episodic:reconcile` — walk `episodic/` (+ `episodic-cold/`) `.md` files; for each, parse `{message_id}` from the filename; if no `ai_messages` row exists, print it as an orphan (do NOT delete — flag only). Log orphan count.

- [ ] **Step 4: Schedule in `app/Console/Kernel.php`** — `fyn:episodic:reconcile` daily; `fyn:episodic:cold-archive` weekly. (Do NOT schedule `purge` — it is a manual, `--force`-guarded operation.)

- [ ] **Step 5: Run — green. Pint. Commit:**
```bash
git add app/Console/Commands/FynEpisodicColdArchive.php app/Console/Commands/FynEpisodicPurge.php app/Console/Commands/FynEpisodicReconcile.php app/Console/Kernel.php tests/Feature/Console/FynEpisodicRetentionTest.php
git commit -m "feat(coala): episodic cold-archive + purge + reconcile (phase 2)"
```

---

### Task 12: GDPR — `fyn:user:erase` spanning both media

**Files:**
- Create: `app/Console/Commands/FynUserErase.php`
- Test: `tests/Feature/Console/FynUserEraseTest.php`

READ first: search for any existing user-deletion path (`grep -rin "function destroy\|deleteUser\|->delete()" app/Services/GDPR app/Http/Controllers | head`) and the GDPR services dir (`app/Services/GDPR`). If a SQL-cascade erase exists, this command EXTENDS it to also walk blobs; if not, this command is the single erase entry point. Confirm the FK cascade from `users` → `ai_conversations` → `ai_messages` (grep the migrations for `onDelete('cascade')`).

- [ ] **Step 1: Write the failing test** `tests/Feature/Console/FynUserEraseTest.php`:
```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('erases a user\'s ai_messages rows and their hot + cold blobs', function (): void {
    Storage::fake('local');
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $hot = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => 'episodic/2026/06/01/1/1.md']);
    $cold = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant', 'blob_md_path' => 'episodic/2025/01/01/1/2.md']);
    Storage::disk('local')->put('episodic/2026/06/01/1/1.md', 'A');
    Storage::disk('local')->put('episodic-cold/2025/01/01/1/2.md', 'B');

    $this->artisan('fyn:user:erase', ['user' => $user->id, '--force' => true])->assertExitCode(0);

    expect(AiMessage::whereIn('id', [$hot->id, $cold->id])->count())->toBe(0)
        ->and(Storage::disk('local')->exists('episodic/2026/06/01/1/1.md'))->toBeFalse()
        ->and(Storage::disk('local')->exists('episodic-cold/2025/01/01/1/2.md'))->toBeFalse();
});
```

- [ ] **Step 2: Run — FAIL. Step 3: Write `fyn:user:erase {user} {--force}`:** dry-run default. Collect the user's `ai_messages` (via their conversations); for each with a `blob_md_path`, delete the resolved blob (hot or cold) via `EpisodeBlobLocator`; then delete the SQL rows (or rely on the existing cascade if one is confirmed — but delete blobs FIRST, since the rows carry the paths). Print counts. `--force` required to execute. If an existing GDPR erase path was found in the READ step, integrate (call it for the SQL side) rather than duplicating.

- [ ] **Step 4: Run — green. Pint. Commit:**
```bash
git add app/Console/Commands/FynUserErase.php tests/Feature/Console/FynUserEraseTest.php
git commit -m "feat(coala): fyn:user:erase spans SQL rows + episodic blobs (phase 2)"
```

---

### Task 13: Backend — read-only episode endpoints on `AiAuditController`

**Files:**
- Modify: `app/Http/Controllers/Api/AiAuditController.php`, `routes/api.php`
- Test: `tests/Feature/AI/EpisodeEndpointsTest.php`

READ `AiAuditController` + how its existing routes are auth-gated (admin middleware? advisor?) in `routes/api.php`. Mirror the existing auth pattern. Add three endpoints; authorise admin for the global list, advisor-scoped (own clients) for the per-client list.

- [ ] **Step 1: Write the failing feature test** `tests/Feature/AI/EpisodeEndpointsTest.php` covering: admin can GET the paginated global episode list; GET `/{id}` returns the structured row + blob body; advisor can GET their own client's episodes but a 403/empty for a non-client; a preview user gets nothing. (Use `Sanctum::actingAs` + the real role/ability setup — read an existing admin-gated controller test to mirror the auth.)

- [ ] **Step 2: Run — FAIL. Step 3: Add controller methods** `episodes(Request)` (paginated, filters: user_id, date range, module, persona — admin only), `episode($id)` (detail via `EpisodeProjection::detail`), `verifyEpisode($id)` (on-demand single-row chain/blob check). For the advisor per-client path, add `clientEpisodes($clientId)` authorising the advisor owns that client (mirror `AdvisorClientDetail` authorisation). Register routes in `routes/api.php` under the existing admin + advisor groups. Read-only (GET except the explicit verify POST).

- [ ] **Step 4: Run — green. Pint. Commit:**
```bash
git add app/Http/Controllers/Api/AiAuditController.php routes/api.php tests/Feature/AI/EpisodeEndpointsTest.php
git commit -m "feat(coala): read-only episode endpoints (phase 2)"
```

---

### Task 14: Frontend — advisor per-client session log

**Files:**
- Create: `resources/js/components/Advisor/ClientSessionLog.vue`
- Modify: `resources/js/views/Advisor/AdvisorClientDetail.vue`
- Test: browser (Playwright) per the CLAUDE.md browser-testing law.

READ `fynlaDesignGuide.md` (v1.3.0) before any UI. READ `AdvisorClientDetail.vue` to match its layout/section pattern + how it gets the client id. Constraints: `<AppLayout>` is already on the parent view; component uses palette tokens + `designSystem.js`; NO decorative icons/emoji/Unicode-as-icon (Rule #16 — detail surface); chain-verify status is a TEXT badge (e.g. "Verified" spring, "Tamper" raspberry, "Pre-extension" horizon) using existing badge classes; no scores (Rule #13).

- [ ] **Step 1: Build `ClientSessionLog.vue`** — props `clientId`; on mount fetch `clientEpisodes(clientId)`; render a list (date, module, persona, model, tool count) as cards/rows per the design guide; clicking a row expands/loads `episode($id)` detail showing the blob sections (`system_prompt`, `assembled_context`, `reasoning_trace`, tool calls/results) in collapsible blocks + a chain-verify text badge. Loading + empty states per design guide (empty state: plain text, no icon).

- [ ] **Step 2: Embed** `<ClientSessionLog :client-id="clientId" />` in `AdvisorClientDetail.vue` as a new "Session log" section, matching the view's existing section styling.

- [ ] **Step 3: Build the SPA** (never raw vite): `./deploy/csjones-fynla/build.sh` is for deploy; for local verification the dev server (`./dev.sh`, Vite on :5173) hot-reloads. Confirm `public/hot` is fresh.

- [ ] **Step 4: BROWSER TEST (Playwright, non-negotiable):** log in as an advisor (local dev — fetch the MFA code from the DB per CLAUDE.md), navigate to a client detail page, confirm the Session log lists episodes, click one, confirm the blob sections render and the chain-verify badge shows. Interact — click/expand/observe — not snapshot-only. Fix until green.

- [ ] **Step 5: Commit:**
```bash
git add resources/js/components/Advisor/ClientSessionLog.vue resources/js/views/Advisor/AdvisorClientDetail.vue
git commit -m "feat(coala): advisor per-client episodic session log (phase 2)"
```

---

### Task 15: Frontend — admin global compliance view

**Files:**
- Create: `resources/js/views/Admin/EpisodicComplianceLog.vue`
- Modify: `resources/js/router/index.js` (route), the admin nav surface (wherever `AiCostDashboard` is linked)
- Test: browser (Playwright).

READ `AiCostDashboard.vue` + how it's routed/linked + admin-gated; mirror it. Same Rule #11/#13/#14/#16 constraints as Task 14.

- [ ] **Step 1: Build `EpisodicComplianceLog.vue`** wrapped in `<AppLayout>` — a paginated, filterable (user, date range, module, persona) table of all episodes via `episodes`; row click opens the same blob-detail + chain-verify view. Server-side pagination. Empty/loading states per design guide, no icons.

- [ ] **Step 2: Add the route** (lazy-loaded, `requiresAuth` + admin meta, mirroring `AiCostDashboard`'s route) and a link from the admin area.

- [ ] **Step 3: Build SPA + BROWSER TEST (Playwright):** log in as admin (`chris@fynla.org` local — fetch MFA code from DB), navigate to the compliance log, filter by a user + date range, paginate, click an episode, confirm blob sections + chain-verify badge render. Interact fully. Fix until green.

- [ ] **Step 4: Commit:**
```bash
git add resources/js/views/Admin/EpisodicComplianceLog.vue resources/js/router/index.js
git commit -m "feat(coala): admin global episodic compliance view (phase 2)"
```

---

### Task 16: Runbook + docs

**Files:**
- Modify: `CLAUDE.md` (custom-command table + deploy runbook)

- [ ] **Step 1:** Add to the "Custom artisan commands" table: `fyn:episodic:backfill-blobs`, `fyn:episodic:cold-archive`, `fyn:episodic:reconcile`, `fyn:episodic:purge` (note `--force`), `fyn:user:erase {user}` (note `--force`). Add the four `fyn:episodic:*` + the reindex commands are not part of the per-deploy finalise EXCEPT `verify-chain` stays manual. Add a one-line note in both deploy finalise blocks: run `php artisan fyn:episodic:backfill-blobs` ONCE after the columns migrate on each environment. Commit:
```bash
git add CLAUDE.md
git commit -m "docs(coala): episodic commands + backfill runbook note (phase 2)"
```

---

## Self-Review

**Spec coverage:**
- §1 columns → Task 1. ✅
- §2 FetchProvenanceCollector + dispatcher wiring + HasAiChat flush → Tasks 2, 8. ✅
- §3 EpisodeBlobWriter atomic protocol → Tasks 3, 4. ✅
- §4 versioned hash chain (hash_scheme col + v2 __episode__ + verify) → Tasks 5, 6, 7. ✅
- §5 backfill (accessibility guarantee) → Task 10 (+ retrievability assertion). ✅
- §6 retrieval + projection → Task 9. ✅
- §7 retention (cold-archive/purge/reconcile) + GDPR erase → Tasks 11, 12. ✅
- §8 UI both surfaces + backend endpoints → Tasks 13, 14, 15. ✅
- Runbook/invariants → Task 16. ✅

**Placeholder scan:** Tasks 6/8/12/13/14/15 carry bounded discovery steps (READ-the-file-first with the exact file:line + a fallback) — specified investigations into real existing-code shapes (AuditChainService internals, HasAiChat persist point, GDPR path, AiAuditController auth, AdvisorClientDetail/AiCostDashboard patterns), not hand-waves. New-component code is complete. No "TBD/etc."

**Type consistency:** `FetchProvenanceCollector` (record/all/reset), `EpisodeBlobData`/`EpisodeBlobRef`/`EpisodeBlobWriter::write`/`EpisodeBlobLocator::resolve|get`, `EpisodeRetriever::findEpisodes`, `EpisodeProjection::list|detail`, `AuditChainService::appendEpisode` + `hash_scheme = 2` + `tool_name = '__episode__'` used identically across Tasks 2–15. Column names (`procedural_version`, `semantic_snapshot_id`, `fetch_provenance`, `blob_md_path`, `blob_md_sha256`) consistent between migration, model, writer, persist hook, projection, and verify.

**Known follow-ups (documented, not gaps):** dropping the legacy `system_prompt`/`assembled_context` LONGTEXT columns is deferred to a later migration after a prod backup cycle (spec non-goal); `semantic_snapshot_id` capture into request scope (Task 8 step 3) falls back to null if the scoped holder isn't wired — wire fully when the assembler stamps it. Dense similar-case recall is Phase 6.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-06-01-coala-phase-2-episodic-hybrid-plan.md`. Execution options:
1. **Subagent-Driven (recommended)** — fresh subagent per task, two-stage review between tasks (same flow that built Phase 1 + the pointer registry).
2. **Inline Execution** — batch with checkpoints.
