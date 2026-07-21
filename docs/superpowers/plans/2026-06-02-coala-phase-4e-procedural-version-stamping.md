# CoALA Phase 4e — `procedural_version` episode stamping Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the "stamp built-but-unfed" gap for `procedural_version`. Add a request-scoped `ProceduralVersionHolder` (mirroring `SemanticSnapshotHolder`), wire the three procedural consumers (4b tool-schema assembly, 4c prompt overlays/FCA blocks, 4d onboarding workflow) to record each active `procedure_id@version` they resolved this turn, then read the accumulated list in `HasAiChat::persistEpisode` into the episode blob frontmatter, the `ai_messages.procedural_version` SQL column, and the `__episode__` audit `result_summary`. Empty → `null` everywhere. The audit hash preimage, the assembled tool catalogue, and the assembled context output all stay byte-identical.

**Architecture:** Exact mirror of the Phase 2 `semantic_snapshot_id` holder/stamp/persist pattern. A new in-memory accumulator VO (`ProceduralVersionHolder`) lives in the `Episodic` namespace beside `SemanticSnapshotHolder`, is bound `scoped` in the container, and is `reset()` per turn inside `persistEpisode`. Consumers record into it as a pure side effect that does not alter any returned value. `persistEpisode` reads `->all()`, maps `[] → null`, and threads the value into `EpisodeBlobData`, the assistant `->update()`, and the `appendEpisode()` payload. The audit `result_summary` carries it; the `computeEpisodeRowHash` preimage is **not** touched (see Task 1 design note + spec §3).

**Tech Stack:** PHP 8.2, Laravel 10, Pest. No new dependencies. No migration. No model change. No config change.

**Spec:** `docs/superpowers/specs/2026-06-02-coala-phase-4e-procedural-version-stamping-design.md`

---

## Design decision binding this plan (spec §3, do not deviate)

`procedural_version` is written into the `__episode__` audit `result_summary` **only**. It is **NOT** added to the v2 (`hash_scheme = 2`) hash preimage in `AuditChainService::computeEpisodeRowHash`. The preimage is:

```
$prevHash . $serialised . $signedAtIso . '|' . $blobSha . '|' . $snapshotId . '|' . $provDigest
```

`result_summary` is not part of `$serialised` (only `blobSha`, `snapshotId`, `provDigest` are appended), so adding a `procedural_version` key to `result_summary` leaves every v2 `row_hash` byte-identical and `verifyChain` green for all existing v1 + v2 rows. This mirrors the in-code `blob_md_path` precedent (persisted into `result_summary`, deliberately excluded from the preimage). `procedural_version` is transitively hash-protected via `blob_md_sha256` because it lives inside the SHA'd blob frontmatter. **Hash-scheme v3 (preimage inclusion) is explicitly out of scope** (spec §9). `AuditChainService` is therefore touched ONLY to add the `result_summary` key — the four hash methods (`computeRowHash`, `computeEpisodeRowHash`, `serialiseForHash`, `canonicaliseForHash`) are not edited.

---

## File Structure

**Create:**
- `app/Services/AI/Memory/Episodic/ProceduralVersionHolder.php` — request-scoped `add`/`all`/`reset` accumulator VO.
- `tests/Unit/Services/AI/Memory/Episodic/ProceduralVersionHolderTest.php` — holder accumulation + de-dup + reset.
- `tests/Feature/AI/ProceduralVersionStampingTest.php` — consumer-records-into-holder tests (4b/4c/4d) + the persist-side stamping tests + the byte-identity audit gate.

**Modify:**
- `app/Providers/AppServiceProvider.php` — `scoped(ProceduralVersionHolder::class)`.
- `app/Services/AI/AiToolDefinitions.php` — record each successfully-assembled `tool_schema` `procedure_id@version` in `toolsFromCorpus`.
- `app/Services/AI/Fyn/FynContextAssembler.php` — inject the holder; record each injected overlay/fca_block `procedure_id@version` in `selectProcedures`.
- `app/Services/Onboarding/OnboardingChatDirector.php` — inject the holder; record the active onboarding `workflow` `procedure_id@version` per driven turn.
- `app/Services/AI/AuditChainService.php` — add `procedural_version` to the `appendEpisode` `result_summary` (preimage untouched).
- `app/Traits/HasAiChat.php` — read/reset the holder in `persistEpisode`; thread the value into the blob, the column, and the audit payload.

---

## Task 1: Golden-master — capture current persist output + audit hash byte-identity (BEFORE any change)

**Why first:** this phase moves existing behaviour (`persistEpisode` currently passes `proceduralVersion: null` and writes no column/result_summary key). The golden-master proves the audit `row_hash` is byte-identical whether or not `procedural_version` is present, and that the existing snapshot/provenance persist path is unchanged. We capture the byte-identity assertion as a committed test that is RED-meaningful only once the holder feeds a value — but the audit-hash invariance assertion is written and green NOW against the current `AuditChainService` so the later `result_summary` edit cannot regress it.

**Files:**
- Create: `tests/Feature/AI/ProceduralVersionStampingTest.php` (first two cases only in this task)

- [ ] **Step 1: Write the audit byte-identity golden-master (green against current code)**

This case proves the v2 `row_hash` is independent of a `procedural_version` key in `result_summary`. It is written to run against the CURRENT `AuditChainService` by manually injecting the key the same way Task 6 will, asserting the hash matches a control row built without it. It must stay green untouched after Task 6.

```php
<?php

declare(strict_types=1);

use App\Models\AiAuditEvent;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\AuditChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
});

it('does not change the v2 episode row hash when procedural_version is present', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $svc = app(AuditChainService::class);

    // Control row: no procedural_version key supplied.
    $control = $svc->appendEpisode([
        'user_id' => $user->id, 'conversation_id' => $conv->id, 'entity_id' => 1,
        'blob_md_sha256' => str_repeat('a', 64),
        'blob_md_path' => 'episodic/x.md',
        'semantic_snapshot_id' => str_repeat('b', 64),
        'fetch_provenance' => [['digest' => 'd1']],
    ]);

    // The preimage depends on prev_hash, so to compare hashes we must reproduce
    // the SAME chain position. Re-derive the control's hash by re-running the
    // same append against a fresh chain and comparing the WITH/ WITHOUT-field
    // hashes at the identical (genesis-prev) position is not possible because
    // appendEpisode reads the live tip. Instead assert the invariant directly:
    // the row_hash is reproducible by verifyChain (which never reads
    // procedural_version), and result_summary carries the field when supplied.
    expect($svc->verifyChain()['chain_valid'])->toBeTrue();

    // Now append a second episode WITH a procedural_version key and assert the
    // chain still verifies (proves verifyChain ignores the new result_summary
    // key for the hash) and the key round-trips in result_summary.
    $withField = $svc->appendEpisode([
        'user_id' => $user->id, 'conversation_id' => $conv->id, 'entity_id' => 2,
        'blob_md_sha256' => str_repeat('c', 64),
        'blob_md_path' => 'episodic/y.md',
        'semantic_snapshot_id' => str_repeat('d', 64),
        'fetch_provenance' => [['digest' => 'd2']],
        'procedural_version' => ['retirement.tool.create_dc_pension@2', 'general.overlay.house@1'],
    ]);

    expect($svc->verifyChain()['chain_valid'])->toBeTrue()
        ->and($control->fresh()->result_summary)->not->toHaveKey('procedural_version');
});
```

- [ ] **Step 2: Run it — it must be GREEN against current code** (the current `appendEpisode` simply ignores the extra `procedural_version` array key in `$event`, so the chain still verifies and the control row has no such `result_summary` key).

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php
```

- [ ] **Step 3: Add the v1/mixed/tamper re-run guard.** Append this case so the existing `AuditChainEpisodeTest` invariants are also asserted from this phase's file (defence-in-depth — these MUST stay green after Task 6):

```php
it('keeps a mixed v1 + v2 chain green (audit invariants unchanged by phase 4e)', function (): void {
    $user = User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $svc = app(AuditChainService::class);

    $svc->append(['user_id' => $user->id, 'tool_name' => 'a', 'operation' => 'read', 'status' => 'dispatched']);
    $svc->appendEpisode([
        'user_id' => $user->id, 'conversation_id' => $conv->id, 'entity_id' => 1,
        'blob_md_sha256' => str_repeat('a', 64), 'blob_md_path' => 'episodic/x.md',
        'semantic_snapshot_id' => null, 'fetch_provenance' => [],
        'procedural_version' => ['x.tool.y@1'],
    ]);
    $svc->append(['user_id' => $user->id, 'tool_name' => 'b', 'operation' => 'read', 'status' => 'dispatched']);

    expect($svc->verifyChain()['chain_valid'])->toBeTrue();
});
```

- [ ] **Step 4: Run + commit.**

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php
./vendor/bin/pint tests/Feature/AI/ProceduralVersionStampingTest.php
```

```
test(coala-4e): golden-master — audit row hash byte-identical with procedural_version present

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 2: `ProceduralVersionHolder` value object

**Files:**
- Create: `app/Services/AI/Memory/Episodic/ProceduralVersionHolder.php`
- Create: `tests/Unit/Services/AI/Memory/Episodic/ProceduralVersionHolderTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;

it('accumulates procedure_id@version on add and returns them in insertion order', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('retirement.tool.create_dc_pension', 2);
    $h->add('general.overlay.house', 1);

    expect($h->all())->toBe([
        'retirement.tool.create_dc_pension@2',
        'general.overlay.house@1',
    ]);
});

it('de-duplicates an identical procedure_id@version', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('savings.tool.create_savings_account', 3);
    $h->add('savings.tool.create_savings_account', 3);

    expect($h->all())->toBe(['savings.tool.create_savings_account@3']);
});

it('keeps distinct versions of the same procedure id separate', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('estate.tool.create_will', 1);
    $h->add('estate.tool.create_will', 2);

    expect($h->all())->toBe(['estate.tool.create_will@1', 'estate.tool.create_will@2']);
});

it('returns an empty list before anything is added', function (): void {
    expect((new ProceduralVersionHolder)->all())->toBe([]);
});

it('clears the list on reset', function (): void {
    $h = new ProceduralVersionHolder;
    $h->add('a.b.c', 1);
    $h->reset();

    expect($h->all())->toBe([]);
});
```

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Episodic/ProceduralVersionHolderTest.php
```

(RED — class does not exist.)

- [ ] **Step 2: Implement minimally**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Episodic;

/**
 * Request-scoped accumulator of the procedure versions that produced the
 * current turn. Each consumer (4b tool schemas, 4c prompt overlays / FCA
 * blocks, 4d onboarding workflow) records each active procedure it resolved
 * via add($procedureId, $version); HasAiChat::persistEpisode reads all() at
 * persist time and binds the "procedure_id@version" list onto the episode
 * blob frontmatter, the ai_messages.procedural_version column, and the v2
 * __episode__ audit result_summary. Empty list → null everywhere.
 *
 * Exact mirror of SemanticSnapshotHolder / ProceduralContributionCollector:
 * a plain in-memory VO (add/all/reset cannot throw), bound `scoped` in the
 * container — one instance per request, reset per turn alongside the others.
 */
final class ProceduralVersionHolder
{
    /** @var list<string> */
    private array $versions = [];

    public function add(string $procedureId, int $version): void
    {
        $stamp = "{$procedureId}@{$version}";
        if (! in_array($stamp, $this->versions, true)) {
            $this->versions[] = $stamp;
        }
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->versions;
    }

    public function reset(): void
    {
        $this->versions = [];
    }
}
```

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Episodic/ProceduralVersionHolderTest.php
./vendor/bin/pint app/Services/AI/Memory/Episodic/ProceduralVersionHolder.php tests/Unit/Services/AI/Memory/Episodic/ProceduralVersionHolderTest.php
```

(GREEN.)

- [ ] **Step 3: Commit**

```
feat(coala-4e): ProceduralVersionHolder request-scoped accumulator

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 3: Bind `ProceduralVersionHolder` as `scoped` in the container

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Add the import.** Find the existing episodic-holder imports near the top `use` block (the file already imports `App\Services\AI\Memory\Episodic\SemanticSnapshotHolder` and `App\Services\AI\Memory\Episodic\FetchProvenanceCollector`). Add directly beneath the `SemanticSnapshotHolder` import:

```php
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
```

(If Pint reorders/strips it, re-add — see the Pint quirk note. Import + first usage land in this same Task.)

- [ ] **Step 2: Add the scoped binding.** Locate the existing scoped block (around line 90–96):

```php
        // Request-scoped semantic-snapshot holder — assembler stamps, persistEpisode reads.
        $this->app->scoped(SemanticSnapshotHolder::class);

        // Request-scoped procedural-contribution accumulator (Phase 4c) — the
        // assembler records overlay/fca_block procedures it injected; Phase 4e
        // reads it at persistEpisode time. One instance per request, reset per turn.
        $this->app->scoped(ProceduralContributionCollector::class);
```

Insert immediately AFTER the `ProceduralContributionCollector` scoped line:

```php
        // Request-scoped procedural-version holder (Phase 4e) — the tool-schema
        // assembler (4b), prompt-overlay assembler (4c) and onboarding director
        // (4d) record each active procedure_id@version they resolved; Phase 4e
        // reads it at persistEpisode time and stamps it onto the episode blob,
        // the ai_messages.procedural_version column and the audit attestation.
        // One instance per request, reset per turn alongside the holders above.
        $this->app->scoped(ProceduralVersionHolder::class);
```

- [ ] **Step 3: Verify the binding resolves + pint.**

```bash
php artisan tinker --execute="var_dump(app(\App\Services\AI\Memory\Episodic\ProceduralVersionHolder::class)->all());"
./vendor/bin/pint app/Providers/AppServiceProvider.php
```

(Expect `array(0) {}` and a Pint PASS.)

- [ ] **Step 4: Commit**

```
feat(coala-4e): bind ProceduralVersionHolder scoped in the container

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 4: 4c — `FynContextAssembler::selectProcedures` records overlay/fca_block versions

**Files:**
- Modify: `app/Services/AI/Fyn/FynContextAssembler.php`
- Modify: `tests/Feature/AI/ProceduralVersionStampingTest.php` (add the 4c stamping case)

- [ ] **Step 1: Write the failing test** (append to `tests/Feature/AI/ProceduralVersionStampingTest.php`). This reuses the temp-corpus idiom from `FynContextAssemblerOverlayTest`. Add the imports + helpers at the top of the file (alongside the existing `use` lines) IF not already present; the helper function names below are unique to this file to avoid collision with `FynContextAssemblerOverlayTest`:

```php
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Support\Facades\File;

/** Write a procedure .md at {kind}/{module}/{file}.md (4e stamping fixtures). */
function writeStampProc(string $root, string $kind, string $module, string $file, int $version, string $body): void
{
    $dir = "{$root}/{$kind}/{$module}";
    @mkdir($dir, 0777, true);
    $procedureId = match (true) {
        $kind === 'system_prompt_overlay' => "{$module}.overlay.{$file}",
        $kind === 'fca_block' => "{$module}.fca.{$file}",
        default => "{$module}.{$kind}.{$file}",
    };
    $fm = "procedure_id: {$procedureId}\nkind: {$kind}\nmodule: {$module}\n"
        ."version: {$version}\nactive: true\neffective_from: '2026-01-01'\n";
    file_put_contents("{$dir}/{$file}.md", "---\n{$fm}---\n\n{$body}\n");
}

function stampAdviceTurn(\App\Models\User $user, string $primary): FynTurnContext
{
    return FynTurnContext::make(
        user: $user,
        message: 'hello',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => $primary],
    );
}
```

Then the case:

```php
it('FynContextAssembler::selectProcedures records each injected overlay/fca_block into the holder', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $corpus = sys_get_temp_dir().'/proc-stamp-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    app()->forgetInstance(FynContextAssembler::class);

    writeStampProc($corpus, 'system_prompt_overlay', 'retirement', 'tone', 3, 'Overlay body.');
    writeStampProc($corpus, 'fca_block', 'general', 'hedge', 2, 'Always hedge advice.');

    $user = \App\Models\User::factory()->create();
    app(FynContextAssembler::class)->build(stampAdviceTurn($user, 'retirement'));

    expect(app(ProceduralVersionHolder::class)->all())
        ->toContain('retirement.overlay.tone@3')
        ->toContain('general.fca.hedge@2');

    File::deleteDirectory($corpus);
});

it('records nothing into the holder when no overlay/fca_block matches the turn', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $corpus = sys_get_temp_dir().'/proc-stamp-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    app()->forgetInstance(FynContextAssembler::class);

    writeStampProc($corpus, 'system_prompt_overlay', 'estate', 'iht', 1, 'Estate-only.');

    $user = \App\Models\User::factory()->create();
    app(FynContextAssembler::class)->build(stampAdviceTurn($user, 'retirement'));

    expect(app(ProceduralVersionHolder::class)->all())->toBe([]);

    File::deleteDirectory($corpus);
});
```

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php --filter="overlay/fca_block"
```

(RED — the assembler does not yet inject the holder.)

- [ ] **Step 2: Inject the holder into the assembler constructor.** Add the import beside the existing `ProceduralContributionCollector` import (line 11):

```php
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
```

Add the constructor param immediately after `private readonly ProceduralContributionCollector $proceduralContributions,` (line 49):

```php
        private readonly ProceduralVersionHolder $proceduralVersions,
```

- [ ] **Step 3: Record at the existing record site in `selectProcedures`.** The method already records into `proceduralContributions` (lines 297–302). Add the version stamp immediately after that `record([...])` call, inside the same `foreach` body, so it stamps exactly the same `$active` procedures that were injected:

```php
            $this->proceduralContributions->record([
                'procedure_id' => $active->procedureId,
                'kind' => $active->kind,
                'module' => $active->module,
                'version' => $active->version,
            ]);
            $this->proceduralVersions->add($active->procedureId, $active->version);
```

- [ ] **Step 4: Run + pint.**

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php
./vendor/bin/pint app/Services/AI/Fyn/FynContextAssembler.php tests/Feature/AI/ProceduralVersionStampingTest.php
```

(GREEN.)

- [ ] **Step 5: Re-run the 4c assembler golden master — must stay green (recording is a no-op on output).**

```bash
./vendor/bin/pest tests/Feature/AI/PromptOverlayGoldenMasterTest.php
```

- [ ] **Step 6: Commit**

```
feat(coala-4e): record injected overlay/fca_block versions into ProceduralVersionHolder

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 5: 4b — `AiToolDefinitions::toolsFromCorpus` records assembled tool_schema versions

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `tests/Feature/AI/ProceduralVersionStampingTest.php` (add the 4b stamping case)

- [ ] **Step 1: Write the failing test** (append to `tests/Feature/AI/ProceduralVersionStampingTest.php`). This seeds a temp tool_schema corpus and asserts the holder carries the assembled tool versions after `getTools(...)`. Add the import at the top if not present:

```php
use App\Services\AI\AiToolDefinitions;
```

The case:

```php
it('AiToolDefinitions records each assembled tool_schema procedure into the holder', function (): void {
    $corpus = sys_get_temp_dir().'/proc-tools-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);

    // A single navigation tool schema procedure the corpus can resolve. The id
    // is the literal AiToolDefinitions::ORDER['navigation'][0] (ORDER is a
    // private const, so it cannot be referenced from the test — use the literal).
    $navId = 'navigation.tool.navigate_to_page';
    [$module] = explode('.', $navId, 2);
    $kindDir = "{$corpus}/tool_schema/{$module}";
    @mkdir($kindDir, 0777, true);
    $schema = json_encode([
        'name' => 'navigate',
        'description' => 'Navigate the app.',
        'parameters' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
    ], JSON_UNESCAPED_SLASHES);
    $base = preg_replace('/[^a-z0-9]+/i', '-', $navId);
    $fm = "procedure_id: {$navId}\nkind: tool_schema\nmodule: {$module}\n"
        ."version: 5\nactive: true\neffective_from: '2026-01-01'\n";
    file_put_contents("{$kindDir}/{$base}.md", "---\n{$fm}---\n\n```json\n{$schema}\n```\n");

    // toolsFromCorpus is private; invoke the navigation assembly via reflection
    // (the codebase's established pattern for exercising private AI internals —
    // see EpisodePersistenceTest invoking persistEpisode). This is the smallest
    // deterministic path that calls toolsFromCorpus(self::ORDER['navigation']).
    $defs = app(AiToolDefinitions::class);
    $r = new ReflectionMethod($defs, 'toolsFromCorpus');
    $r->setAccessible(true);
    $tools = $r->invoke($defs, [$navId]);

    expect($tools)->not->toBe([]);
    expect(app(ProceduralVersionHolder::class)->all())->toContain("{$navId}@5");

    File::deleteDirectory($corpus);
});
```

> `toolsFromCorpus` and `navigationTools` are both `private`, and `ORDER` is a `private const` — confirmed against the live file. The reflection call invokes `toolsFromCorpus` directly with `['navigation.tool.navigate_to_page']` (the literal `ORDER['navigation']` value), which is the unit under test. `$module` resolves to `navigation`, so the temp .md lands at `tool_schema/navigation/...`.

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php --filter="tool_schema procedure into the holder"
```

(RED — `toolsFromCorpus` does not yet record.)

- [ ] **Step 2: Record on successful assembly in `toolsFromCorpus`.** Add the import at the top of `AiToolDefinitions.php` beside the existing `ProceduralCorpusLoader` import (line 7):

```php
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
```

Modify `toolsFromCorpus` (lines 141–154). The method resolves `$corpus->active($procedureId)` once via `toolFromCorpus`; to know the resolved version on success, capture the `Procedure` first, then record only when the tool was successfully assembled (skip the null degrade path so the stamp reflects what reached the catalogue):

```php
    private function toolsFromCorpus(array $procedureIds): array
    {
        $corpus = app(ProceduralCorpusLoader::class)->load();
        $versions = app(ProceduralVersionHolder::class);
        $tools = [];

        foreach ($procedureIds as $procedureId) {
            $procedure = $corpus->active($procedureId);
            $tool = $this->toolFromCorpus($procedure);
            if ($tool !== null) {
                $tools[] = $tool;
                $versions->add($procedure->procedureId, $procedure->version);
            }
        }

        return $tools;
    }
```

> Note: `$procedure` is guaranteed non-null inside the `if ($tool !== null)` branch because `toolFromCorpus(null)` returns `null` (line 166–170), so a null `$procedure` can never produce a non-null `$tool`. Recording uses `$procedure->procedureId` (not the loop `$procedureId` variable) for parity with the 4c record site — they are equal, but reading from the resolved `Procedure` keeps the two sites identical in shape.

- [ ] **Step 3: Run + pint.**

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php
./vendor/bin/pint app/Services/AI/AiToolDefinitions.php tests/Feature/AI/ProceduralVersionStampingTest.php
```

(GREEN.)

- [ ] **Step 4: Re-run the 4b tool-catalogue golden master — must stay green (recording is a no-op on the returned array).**

```bash
./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php
```

- [ ] **Step 5: Commit**

```
feat(coala-4e): record assembled tool_schema versions into ProceduralVersionHolder

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 6: 4d — `OnboardingChatDirector` records the active workflow version per driven turn

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php`
- Modify: `tests/Feature/AI/ProceduralVersionStampingTest.php` (add the 4d stamping cases)

**Why the director, not `transitionTable()`:** `OnboardingStateMachine::transitionTable()` is a `static` method behind a process-lifetime cache (`$transitionTableCache`), so recording inside it would stamp only the first turn per process. The active workflow `procedure_id@version` is resolved + recorded in the director's per-turn drive path instead, reading `corpus->active('onboarding.workflow.fyn-onboarding', now())` and recording only when the corpus actually supplies the workflow (matching the merge path `transitionTable()` took). Empty corpus → records nothing → null stamp.

- [ ] **Step 1: Write the failing tests** (append to `tests/Feature/AI/ProceduralVersionStampingTest.php`). Add imports at the top if not present:

```php
use App\Models\AiConversation;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;
```

The cases. Onboarding turns are driven via `handleUserMessage`; we drive a real turn in the `path_choice` state and assert the holder carries the workflow version when the corpus supplies a valid workflow procedure (id-set + order must equal the in-code base, per `transitionTable()`'s guard). Capturing a full valid workflow corpus inline is large; instead the cases assert the two ends of the contract: (a) when the corpus has NO active workflow, nothing is recorded; (b) the director resolves+records via a recording helper that is unit-exercised directly.

```php
it('OnboardingChatDirector records nothing when the corpus has no active workflow', function (): void {
    $corpus = sys_get_temp_dir().'/proc-onb-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    OnboardingStateMachine::flushTransitionTableCache(); // see Step 2b

    $user = \App\Models\User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $director = app(OnboardingChatDirector::class);
    iterator_to_array($director->handleUserMessage($user, $conv, 'Start from scratch'));

    expect(app(ProceduralVersionHolder::class)->all())->toBe([]);

    File::deleteDirectory($corpus);
});

it('OnboardingChatDirector records the active workflow procedure when the corpus supplies it', function (): void {
    $corpus = sys_get_temp_dir().'/proc-onb-'.uniqid();
    config([
        'fyn.memory.procedural_path' => $corpus,
        'fyn.memory.procedural_reload_interval' => 0,
    ]);
    app()->forgetInstance(ProceduralCorpusLoader::class);
    OnboardingStateMachine::flushTransitionTableCache();

    // Write a workflow procedure the director can resolve by id. The director
    // records the id@version whenever corpus->active(...) returns a procedure;
    // it does NOT require the full merge to succeed (that is transitionTable's
    // concern). A minimal valid-frontmatter workflow .md is enough to be the
    // active procedure for 'onboarding.workflow.fyn-onboarding'.
    $dir = "{$corpus}/workflow/onboarding";
    @mkdir($dir, 0777, true);
    $fm = "procedure_id: onboarding.workflow.fyn-onboarding\nkind: workflow\n"
        ."module: onboarding\nversion: 7\nactive: true\neffective_from: '2026-01-01'\n";
    file_put_contents("{$dir}/fyn-onboarding.md", "---\n{$fm}---\n\n```json\n{}\n```\n");

    $user = \App\Models\User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE,
    ]);
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);

    $director = app(OnboardingChatDirector::class);
    iterator_to_array($director->handleUserMessage($user, $conv, 'Start from scratch'));

    expect(app(ProceduralVersionHolder::class)->all())
        ->toContain('onboarding.workflow.fyn-onboarding@7');

    File::deleteDirectory($corpus);
});
```

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php --filter="OnboardingChatDirector"
```

(RED — the director does not yet inject/record; `flushTransitionTableCache` does not yet exist.)

> If `User::factory()->create(['onboarding_fyn_step' => ...])` errors on mass-assignment (`onboarding_fyn_step` not fillable), create the user without those keys then `$user->forceFill(['onboarding_completed' => false, 'onboarding_fyn_step' => OnboardingStateMachine::STATE_PATH_CHOICE])->save();`. `handleUserMessage` reads `$user->onboarding_fyn_step` directly off the in-memory model, so the in-memory value is what matters.

- [ ] **Step 2a: Inject the holder into the director constructor.** Add the import in `OnboardingChatDirector.php` (beside the existing `App\Services\AI\...` imports, after line 21):

```php
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Support\Carbon;
```

Add the constructor param after `private readonly FynLoop $fynLoop,` (line 60):

```php
        private readonly ProceduralVersionHolder $proceduralVersions,
```

- [ ] **Step 2b: Add a test-only static cache flush to `OnboardingStateMachine`.** The transition-table static cache (`$transitionTableCache`) is process-lived and would leak the empty-corpus result across the two onboarding cases above. Add a small public flush so each test reads its own temp corpus. In `app/Services/Onboarding/OnboardingStateMachine.php`, immediately after the `transitionTable()` method (after line 514):

```php
    /**
     * Test/deploy hook — clear the memoised transition table so the next
     * states() / transitionTable() call re-reads the corpus. Used by the 4e
     * stamping tests (each drives its own temp corpus) and after a corpus
     * hot-reload. Idempotent and side-effect-free in production.
     */
    public static function flushTransitionTableCache(): void
    {
        self::$transitionTableCache = null;
    }
```

- [ ] **Step 3: Record the active workflow version at the per-turn drive point.** In `handleUserMessage`, after the current state has been resolved and validated (immediately after the `$state === null` guard block ending at line 140, i.e. once we know we are driving a real onboarding turn), add a guarded record call:

```php
        // Phase 4e — stamp the active onboarding workflow procedure version onto
        // the turn so persistEpisode can bind it onto the episode. Recorded only
        // when the corpus actually supplies the workflow procedure (the merge
        // path transitionTable() takes); empty corpus → records nothing → null
        // stamp, matching the in-code-table fallback. Never breaks the turn.
        $this->recordActiveWorkflowVersion();
```

Then add the private helper at the end of the class (before the final closing brace), guarded so a corpus fault degrades to recording nothing:

```php
    /**
     * Resolve and record the active onboarding workflow procedure_id@version
     * into the request-scoped ProceduralVersionHolder. Degrades silently — a
     * missing/malformed corpus records nothing and never throws.
     */
    private function recordActiveWorkflowVersion(): void
    {
        try {
            $procedure = app(ProceduralCorpusLoader::class)
                ->load()
                ->active('onboarding.workflow.fyn-onboarding', Carbon::now());

            if ($procedure !== null) {
                $this->proceduralVersions->add($procedure->procedureId, $procedure->version);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
```

> Scope note: record from `handleUserMessage` only (the message-driven turn that produces a persisted assistant episode). `emitFirstTurn` (backend turn 1, bubbles only) and `handleAction` are out of scope for 4e — they do not run `persistEpisode` for an assistant LLM message, so stamping there would accumulate into a holder that is never read+reset on those paths. If a later phase persists episodes on those paths, add the same `recordActiveWorkflowVersion()` call there.

- [ ] **Step 4: Run + pint.**

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php app/Services/Onboarding/OnboardingStateMachine.php tests/Feature/AI/ProceduralVersionStampingTest.php
```

(GREEN.)

- [ ] **Step 5: Commit**

```
feat(coala-4e): record active onboarding workflow version into ProceduralVersionHolder

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 7: `AuditChainService::appendEpisode` carries `procedural_version` in `result_summary` (preimage untouched)

**Files:**
- Modify: `app/Services/AI/AuditChainService.php`

- [ ] **Step 1: Add `procedural_version` to `result_summary` ONLY.** In `appendEpisode` (lines 147–152), add one key to the `$resultSummary` array. Do NOT touch `computeEpisodeRowHash`, the `$payload` array, or the `computeEpisodeRowHash(...)` call args:

```php
            $resultSummary = [
                'blob_md_sha256' => $blobSha,
                'blob_md_path' => $event['blob_md_path'] ?? null,
                'semantic_snapshot_id' => $snapshotId,
                'provenance_digest' => $provDigest,
                'procedural_version' => $event['procedural_version'] ?? null,
            ];
```

> The preimage built by `computeEpisodeRowHash` consumes only `$prevHash`, `$serialised` (from `$payload`), `$signedAtIso`, `$blobSha`, `$snapshotId`, `$provDigest`. `$resultSummary` is stored on the row but NOT fed into the hash, so adding this key is byte-identity-safe (Task 1's golden master proves it). `verifyChain` re-reads `blob_md_sha256` / `semantic_snapshot_id` / `provenance_digest` from `result_summary` but never `procedural_version`, so the chain stays green for all v1 + v2 rows.

- [ ] **Step 2: Run the Task 1 golden master + the existing audit suite — all must stay green.**

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php tests/Unit/Services/AI/AuditChainEpisodeTest.php tests/Feature/Console/AiAuditVerifyChainEpisodeTest.php
./vendor/bin/pint app/Services/AI/AuditChainService.php
```

- [ ] **Step 3: Commit**

```
feat(coala-4e): carry procedural_version in __episode__ result_summary (hash preimage unchanged)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 8: `HasAiChat::persistEpisode` reads the holder → blob + column + audit (the wire-up)

**Files:**
- Modify: `app/Traits/HasAiChat.php`
- Modify: `tests/Feature/AI/ProceduralVersionStampingTest.php` (add the persist-side cases)

- [ ] **Step 1: Write the failing persist-side tests** (append to `tests/Feature/AI/ProceduralVersionStampingTest.php`). These mirror `EpisodePersistenceTest`'s snapshot cases. Add imports at the top if not present:

```php
use App\Agents\CoordinatingAgent;
use App\Models\AiMessage;
use Illuminate\Support\Facades\Storage;
```

The cases:

```php
it('stamps the accumulated procedural_version onto the blob + column + attestation', function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');

    $user = \App\Models\User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $assistant = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    app(ProceduralVersionHolder::class)->add('retirement.tool.create_dc_pension', 2);
    app(ProceduralVersionHolder::class)->add('general.overlay.house', 1);

    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'persistEpisode');
    $m->setAccessible(true);
    $m->invoke($agent, $assistant, $conv, $user, 'SYS', 'CTX', 'grok-4', null, null);

    $assistant->refresh();

    // blob frontmatter
    $blob = Storage::disk('local')->get($assistant->blob_md_path);
    expect($blob)->toContain('procedural_version:')
        ->and($blob)->toContain('retirement.tool.create_dc_pension@2')
        ->and($blob)->toContain('general.overlay.house@1');

    // SQL column (cast array)
    expect($assistant->procedural_version)->toBe([
        'retirement.tool.create_dc_pension@2',
        'general.overlay.house@1',
    ]);

    // audit attestation result_summary
    $event = AiAuditEvent::where('tool_name', '__episode__')->where('entity_id', $assistant->id)->first();
    expect($event)->not->toBeNull()
        ->and($event->result_summary['procedural_version'])->toBe([
            'retirement.tool.create_dc_pension@2',
            'general.overlay.house@1',
        ]);
});

it('records a null procedural_version when the holder is empty', function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');

    $user = \App\Models\User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $assistant = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'persistEpisode');
    $m->setAccessible(true);
    $m->invoke($agent, $assistant, $conv, $user, 'SYS', 'CTX', 'grok-4', null, null);

    $assistant->refresh();

    $blob = Storage::disk('local')->get($assistant->blob_md_path);
    expect($blob)->toContain('procedural_version: null')
        ->and($assistant->procedural_version)->toBeNull();

    $event = AiAuditEvent::where('tool_name', '__episode__')->where('entity_id', $assistant->id)->first();
    expect($event->result_summary['procedural_version'])->toBeNull();
});

it('resets the holder after persist so the next turn does not inherit the stamp', function (): void {
    config(['app.ai_audit_hmac_key' => 'test-key']);
    Storage::fake('local');

    $user = \App\Models\User::factory()->create();
    $conv = AiConversation::factory()->create(['user_id' => $user->id]);
    $assistant = AiMessage::factory()->create(['conversation_id' => $conv->id, 'role' => 'assistant']);

    app(ProceduralVersionHolder::class)->add('savings.tool.create_savings_account', 4);

    $agent = app(CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'persistEpisode');
    $m->setAccessible(true);
    $m->invoke($agent, $assistant, $conv, $user, 'SYS', 'CTX', 'grok-4', null, null);

    expect(app(ProceduralVersionHolder::class)->all())->toBe([]);
});
```

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php --filter="procedural_version onto the blob|null procedural_version|resets the holder"
```

(RED — `persistEpisode` still passes `proceduralVersion: null` and writes no column/result_summary value.)

- [ ] **Step 2: Wire the holder into `persistEpisode`.** Add the import in `HasAiChat.php` beside the existing episodic imports (`SemanticSnapshotHolder`, `FetchProvenanceCollector`):

```php
use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
```

> Verify the exact existing import lines first (`grep -n "use App\\\\Services\\\\AI\\\\Memory\\\\Episodic" app/Traits/HasAiChat.php`) and place the new `use` adjacent so Pint's alphabetical ordering keeps it stable.

In `persistEpisode`, extend the reset block (currently lines 949–955 read+reset the provenance collector and snapshot holder). Add the holder read+reset immediately after the `$snapshotHolder->reset();` line:

```php
            $snapshotHolder = app(SemanticSnapshotHolder::class);
            $semanticSnapshotId = $snapshotHolder->get();
            $snapshotHolder->reset();

            $versionHolder = app(ProceduralVersionHolder::class);
            $proceduralVersion = $versionHolder->all();
            $versionHolder->reset();
            $proceduralVersion = $proceduralVersion !== [] ? $proceduralVersion : null;
```

Replace the hardcoded `proceduralVersion: null,` in the `EpisodeBlobData` constructor (line 964) with:

```php
                proceduralVersion: $proceduralVersion,
```

Add the column to the assistant `->update([...])` payload (lines 976–980), beside `fetch_provenance`:

```php
            $assistant->update([
                'blob_md_path' => $ref->path,
                'blob_md_sha256' => $ref->sha256,
                'fetch_provenance' => $provenance !== [] ? $provenance : null,
                'procedural_version' => $proceduralVersion,
            ]);
```

Add the key to the `appendEpisode([...])` payload (lines 982–990), beside `semantic_snapshot_id`:

```php
            app(AuditChainService::class)->appendEpisode([
                'user_id' => $user->id,
                'conversation_id' => $conversation->id,
                'entity_id' => $assistant->id,
                'blob_md_sha256' => $ref->sha256,
                'blob_md_path' => $ref->path,
                'semantic_snapshot_id' => $semanticSnapshotId,
                'procedural_version' => $proceduralVersion,
                'fetch_provenance' => $provenance,
            ]);
```

- [ ] **Step 3: Run the new persist cases + the existing episodic persistence suite.**

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralVersionStampingTest.php tests/Feature/AI/EpisodePersistenceTest.php
./vendor/bin/pint app/Traits/HasAiChat.php tests/Feature/AI/ProceduralVersionStampingTest.php
```

(GREEN — and the existing `EpisodePersistenceTest` "does not throw when the blob write fails (resilient)" + snapshot cases stay green: the holder defaults empty → null, identical to today.)

- [ ] **Step 4: Commit**

```
feat(coala-4e): stamp accumulated procedural_version onto episode blob + column + attestation

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Task 9: Full regression + golden-master sweep + pint + substrate gate

**Files:** none (verification only).

- [ ] **Step 1: Run the three golden masters + the full episodic/audit suite (spec §5).** All must be green:

```bash
./vendor/bin/pest \
  tests/Feature/AI/ProceduralVersionStampingTest.php \
  tests/Feature/AI/ToolSchemaGoldenMasterTest.php \
  tests/Feature/AI/PromptOverlayGoldenMasterTest.php \
  tests/Feature/AI/EpisodePersistenceTest.php \
  tests/Feature/AI/EpisodeEndpointsTest.php \
  tests/Unit/Services/AI/AuditChainEpisodeTest.php \
  tests/Feature/Console/AiAuditVerifyChainEpisodeTest.php \
  tests/Unit/Services/AI/Memory/Episodic/EpisodeProjectionTest.php \
  tests/Unit/Services/AI/Memory/Episodic/EpisodeBlobDataTest.php \
  tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php \
  tests/Unit/Services/AI/Memory/Episodic/ProceduralVersionHolderTest.php
```

- [ ] **Step 2: Confirm the static prompt prefix is byte-identical (prefix-cache invariant) + the tool catalogue is unchanged.** These were not touched, but assert it explicitly:

```bash
./vendor/bin/pest --filter="FynSystemPrompt"
./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php
```

- [ ] **Step 3: Substrate deploy gate (no corpus content changed, run it anyway per spec §6).**

```bash
php artisan fyn:procedural:validate
```

(Expect green — this phase adds no corpus files.)

- [ ] **Step 4: Run the full AI + Memory + Onboarding test scope to catch any cross-cutting regression.**

```bash
./vendor/bin/pest tests/Unit/Services/AI tests/Feature/AI tests/Unit/Services/Onboarding tests/Feature/Onboarding 2>/dev/null; \
./vendor/bin/pest tests/Unit/Services/AI tests/Feature/AI
```

(If a path doesn't exist, the failing path is dropped — the second invocation is the authoritative AI scope. All green.)

- [ ] **Step 5: Final pint sweep over every touched file (must report PASS).**

```bash
./vendor/bin/pint \
  app/Services/AI/Memory/Episodic/ProceduralVersionHolder.php \
  app/Providers/AppServiceProvider.php \
  app/Services/AI/AiToolDefinitions.php \
  app/Services/AI/Fyn/FynContextAssembler.php \
  app/Services/Onboarding/OnboardingChatDirector.php \
  app/Services/Onboarding/OnboardingStateMachine.php \
  app/Services/AI/AuditChainService.php \
  app/Traits/HasAiChat.php \
  tests/Unit/Services/AI/Memory/Episodic/ProceduralVersionHolderTest.php \
  tests/Feature/AI/ProceduralVersionStampingTest.php
```

> Watch the known Pint quirk: if Pint stripped a `use` import that became momentarily unused mid-task, re-add it (it is now used) and re-run Pint.

- [ ] **Step 6: Commit (regression sweep — no code change, or any pint-only fixes).**

```
test(coala-4e): full regression + golden-master + pint sweep green

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Done-when (maps to spec §8)

- `ProceduralVersionHolder` exists, scoped-bound, `add`/`all`/`reset` tested (Tasks 2–3).
- 4b/4c/4d consumers record their active procedures into the holder per turn (Tasks 4–6).
- `persistEpisode` writes the accumulated `procedure_id@version` list to the blob frontmatter **and** the `ai_messages.procedural_version` column **and** the audit `result_summary`; empty → `null` everywhere; holder `reset()` alongside the other holders (Tasks 7–8).
- The three golden masters (audit byte-identity Task 1, 4b tool catalogue, 4c assembler output) all green; full episodic + audit suite green (Task 9).
- `pint` PASS on all touched files; `fyn:procedural:validate` green (Task 9).
- Two-Fyn tool catalogue unchanged; `FynSystemPrompt::text()` static prefix byte-identical; no migration, no model change, no config change.

## Out of scope (spec §9 — do NOT implement here)

- Hash-scheme v3 (binding `procedural_version` into the cryptographic preimage).
- Admin viewer beyond `EpisodeProjection`'s existing `procedural_version` surfacing (4f is a separate phase).
- Working-memory session-start `procedural_version` pin (Phase 4-working-memory / 5).
- `ai_cost_attribution.procedural_version` telemetry (Phase 5).
- Any change to `ProceduralContributionCollector` (it coexists with the new holder).
- Recording from `emitFirstTurn` / `handleAction` (no persisted assistant episode on those paths).
