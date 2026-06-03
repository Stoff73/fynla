# CoALA Phase 4f — Read-only Procedural Admin Viewer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a read-only, admin-gated surface to VIEW the Phase 4a procedural-memory corpus. Two GET endpoints over `ProceduralCorpus`, one Vue admin view (wrapped in `<AppLayout>`, no icons, no scores, British spelling), one lazy admin route, one API service wrapper. Zero behaviour move: no writes, no new AI/chat tools, no change to the assembled tool catalogue, prompt, or either Fyn write-state.

**Architecture:** Mirrors the Phase 2 episodic admin viewer (`AiAuditController@episodes/@episode` + `EpisodicComplianceLog.vue` + `aiAuditService`). The controller holds a `ProceduralCorpusLoader` (the bound singleton) and calls `load()` per request — the never-throws runtime entry that degrades to last-good/empty and preserves the 60s mtime hot-reload + cross-request cache. The controller never binds `ProceduralCorpus` in the container and never calls `loadStrict()` (deploy-gate only). `index` groups `ProceduralCorpus::all()` by `kind` → `module` → `procedure_id` (frontmatter-only summary, no body); `show` returns every version of one `procedure_id` (frontmatter + body) via `ProceduralCorpus::versions()`. Markdown body is rendered verbatim in a monospace `<pre class="whitespace-pre-wrap">` block — no `marked`/`markdown-it` dependency.

**Tech Stack:** PHP 8.2, Laravel 10, Sanctum, Pest, Vue 3. No new dependencies.

**Spec:** `docs/superpowers/specs/2026-06-02-coala-phase-4f-procedural-admin-viewer-design.md`

---

## File Structure

**Create:**
- `app/Http/Controllers/Api/Admin/ProceduralCorpusController.php` — read-only `index` + `show`.
- `resources/js/services/proceduralCorpusService.js` — `getCorpus()`, `getProcedure(procedureId)`.
- `resources/js/views/Admin/ProceduralCorpusViewer.vue` — admin view, `<AppLayout>`-wrapped.
- `tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php` — endpoint contract + auth.
- `tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php` — view exists + wraps `<AppLayout>`, no icons, no scores.

**Modify:**
- `routes/api.php` — add `ProceduralCorpusController` import + two GET routes in the existing `admin` group.
- `resources/js/router/index.js` — add lazy import + one `/admin/procedural-corpus` route.

---

## Golden-master note (spec §5)

4f **moves no existing behaviour** — it is purely additive read-only surface. There is no PHP constant being externalised and no prompt/tool output being re-shaped, so the "captured fixture vs post-refactor output" byte-identity golden-master that guards 4b–4e does **not** apply in that form here. Instead, Task 1 captures and pins the zero-regression baseline that DOES bind 4f: the existing prompt/tool/assembler/loader suites must be green BEFORE any 4f code, and must stay green AFTER (proving 4f did not perturb the tool catalogue, the prompt prefix, or the loader runtime contract). The endpoint contract test (Task 4) is the closest analogue to a golden-master for the new surface and is the acceptance pin for the controller JSON shape.

---

## Task 1: Capture the zero-regression baseline (golden-master gate)

**Files:**
- Create: `docs/superpowers/plans/4f-baseline.txt` (scratch capture; not committed with code — used to prove green-before / green-after).

This task captures the CURRENT green state of every suite 4f must not perturb, so we have a recorded baseline to assert identical against after the refactor (spec §5.1–§5.3).

- [ ] **Step 1: Confirm the branch**

```bash
git rev-parse --abbrev-ref HEAD   # must print: feat/coala-4f-admin-viewer
```

- [ ] **Step 2: Capture the baseline green state of the three guarded suites**

Run each and record the summary line (counts) into the scratch file. ALL THREE MUST BE GREEN before writing any 4f code. If any is red on the untouched branch, STOP and report BLOCKED — 4f cannot establish a clean baseline.

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural 2>&1 | tee docs/superpowers/plans/4f-baseline.txt
./vendor/bin/pest tests/Feature/AI tests/Unit/Services/AI 2>&1 | tee -a docs/superpowers/plans/4f-baseline.txt
```

- [ ] **Step 3: Record the baseline assertion**

The recorded "Tests: N passed" totals for:
1. `tests/Unit/Services/AI/Memory/Procedural` (loader/corpus/procedure — proves §5.2 loader runtime contract).
2. `tests/Feature/AI` + `tests/Unit/Services/AI` (prompt/tool/assembler golden-masters — proves §5.1 tool catalogue + prompt unchanged).

are the golden-master. The phase is **not done** until these EXACT totals (plus the new 4f tests added in Tasks 4–5) are green again at Task 6. A reduction in any pre-existing pass count is a regression and must be fixed by looping, not by editing the assertion.

- [ ] **Step 4: Commit nothing yet** — this task is a measurement gate, not a code change. Proceed to Task 2 only once Step 2 is green.

---

## Task 2: `ProceduralCorpusController` — `index` + `show`

**Files:**
- Create: `app/Http/Controllers/Api/Admin/ProceduralCorpusController.php`
- Test: covered by Task 4 (the feature test). This task implements the controller; Task 4 writes the failing test first per TDD. To honour TDD ordering, **do Task 4 Step 1 (write the failing feature test) BEFORE writing this controller**, then return here to implement. The plan presents the controller code here for locality; the worker writes the red test first.

- [ ] **Step 1: Write the controller (after the Task 4 red test exists)**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CoALA Phase 4f — read-only admin viewer over the procedural-memory corpus.
 *
 * Holds the ProceduralCorpusLoader singleton and calls load() per request: the
 * never-throws runtime entry that degrades to the last-good/empty corpus and
 * preserves the 60s mtime hot-reload + cross-request cache. This controller
 * never calls loadStrict() (deploy gate only) and never writes — both endpoints
 * are GET. It imports neither AiToolDefinitions, FynSystemPrompt nor
 * FynContextAssembler and registers no AI/chat tool, so the assembled tool
 * catalogue, the prompt prefix, and the Two-Fyn write-states are untouched.
 */
final class ProceduralCorpusController extends Controller
{
    public function __construct(
        private readonly ProceduralCorpusLoader $loader
    ) {}

    /**
     * GET /api/admin/procedural-corpus
     * All procedures grouped by kind -> module, frontmatter-only summary (no body).
     */
    public function index(): JsonResponse
    {
        $corpus = $this->loader->load();

        // Bucket every procedure version by kind, then module, then procedure_id.
        $byKind = [];
        foreach ($corpus->all() as $proc) {
            $byKind[$proc->kind][$proc->module][$proc->procedureId][] = $proc;
        }

        $groups = [];
        foreach ($byKind as $kind => $modules) {
            ksort($modules);
            $moduleList = [];
            foreach ($modules as $module => $procedures) {
                ksort($procedures);
                $procList = [];
                foreach ($procedures as $procedureId => $versions) {
                    // Ascending by version for stable display.
                    usort($versions, fn (Procedure $a, Procedure $b): int => $a->version <=> $b->version);

                    $active = null;
                    foreach ($versions as $v) {
                        if ($v->active) {
                            $active = $v->version;
                        }
                    }

                    $procList[] = [
                        'procedure_id' => $procedureId,
                        'active_version' => $active,
                        'version_count' => count($versions),
                        'versions' => array_map(fn (Procedure $v): array => [
                            'version' => $v->version,
                            'active' => $v->active,
                            'effective_from' => $v->effectiveFrom->toDateString(),
                            'effective_to' => $v->effectiveTo?->toDateString(),
                        ], $versions),
                    ];
                }
                $moduleList[] = [
                    'module' => $module,
                    'procedures' => $procList,
                ];
            }
            $groups[] = [
                'kind' => $kind,
                'modules' => $moduleList,
            ];
        }

        // Stable kind ordering for deterministic display.
        usort($groups, fn (array $a, array $b): int => strcmp($a['kind'], $b['kind']));

        return response()->json([
            'success' => true,
            'data' => ['groups' => $groups],
        ]);
    }

    /**
     * GET /api/admin/procedural-corpus/{procedureId}
     * Every version (frontmatter + body) of one procedure, ascending by version.
     * Unknown id -> empty versions array (200), never a 404 — a stale UI link
     * cannot break the page.
     */
    public function show(Request $request): JsonResponse
    {
        $procedureId = (string) $request->route('procedureId');
        $corpus = $this->loader->load();

        $versions = array_map(fn (Procedure $v): array => [
            'kind' => $v->kind,
            'module' => $v->module,
            'version' => $v->version,
            'active' => $v->active,
            'effective_from' => $v->effectiveFrom->toDateString(),
            'effective_to' => $v->effectiveTo?->toDateString(),
            'body' => $v->body,
        ], $corpus->versions($procedureId));

        return response()->json([
            'success' => true,
            'data' => [
                'procedure_id' => $procedureId,
                'versions' => $versions,
            ],
        ]);
    }
}
```

- [ ] **Step 2: Pint**

```bash
./vendor/bin/pint app/Http/Controllers/Api/Admin/ProceduralCorpusController.php
```

Must report `PASS`. (Pint quirk reminder: if it strips the `Procedure` or `ProceduralCorpusLoader` import while a momentary inconsistency exists, re-add the import once the usage is present and re-run. Both imports are used in the code above, so this should be stable.)

---

## Task 3: Register the two admin routes

**Files:**
- Modify: `routes/api.php`

- [ ] **Step 1: Add the controller import**

Add to the `use App\Http\Controllers\Api\Admin\...` import block near the top of `routes/api.php` (alphabetical with its siblings — sits between `InsightTemplateController` and `SavingsMarketRateController`):

```php
use App\Http\Controllers\Api\Admin\ProceduralCorpusController;
```

- [ ] **Step 2: Add the two routes inside the existing admin group**

Inside the existing group opened at `Route::middleware(['auth:sanctum', 'permission:admin.access'])->prefix('admin')->group(function () {` (the same group that hosts the `ai-audit` sub-group), add — directly after the closing `});` of the `Route::prefix('ai-audit')->group(...)` block (around routes/api.php:1169):

```php
        // CoALA Phase 4f — read-only procedural-memory corpus viewer (admin).
        Route::get('/procedural-corpus', [ProceduralCorpusController::class, 'index']);
        Route::get('/procedural-corpus/{procedureId}', [ProceduralCorpusController::class, 'show'])
            ->where('procedureId', '.*');
```

The `->where('procedureId', '.*')` constraint allows the dotted ids (e.g. `retirement.tool.create_dc_pension`). Because the SPA fetches the detail by query/route param on a single SPA route, no second SPA route is needed.

- [ ] **Step 3: Verify the routes are registered behind the admin middleware**

```bash
php artisan route:list --path=admin/procedural-corpus
```

Expect two rows, both showing `auth:sanctum` and `permission:admin.access` in the middleware column.

- [ ] **Step 4: Pint**

```bash
./vendor/bin/pint routes/api.php
```

Must report `PASS`.

---

## Task 4: Feature test — endpoint contract + auth (TDD red FIRST)

**Files:**
- Create: `tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php`

**TDD order:** Write this test FIRST (Step 1), run it RED (controller/routes absent), THEN implement Task 2 + Task 3, then run GREEN. The plan lists Task 2/3 above for code locality, but the worker writes this red test before the controller exists.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->corpus = sys_get_temp_dir().'/proc-4f-'.uniqid();
    config(['fyn.memory.procedural_path' => $this->corpus]);
    Cache::flush();
});

afterEach(function (): void {
    File::deleteDirectory($this->corpus);
    Cache::flush();
});

/** Write a procedure .md at {kind}/{module}/{file}.md with the given frontmatter + body. */
function writeCorpusProc(string $root, string $kind, string $module, string $file, array $frontmatter, string $body = 'Procedure body.'): void
{
    $dir = "{$root}/{$kind}/{$module}";
    @mkdir($dir, 0777, true);
    $fm = '';
    foreach ($frontmatter as $k => $v) {
        $fm .= $k.': '.(is_bool($v) ? ($v ? 'true' : 'false') : $v)."\n";
    }
    file_put_contents("{$dir}/{$file}.md", "---\n{$fm}---\n\n{$body}\n");
}

function frontmatter4f(array $overrides = []): array
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

describe('procedural corpus admin endpoints — auth', function (): void {
    it('rejects an unauthenticated request with 401', function (): void {
        $this->getJson('/api/admin/procedural-corpus')->assertUnauthorized();
    });

    it('rejects a non-admin user with 403', function (): void {
        $plain = User::factory()->create(['is_admin' => false, 'is_advisor' => false]);
        $this->actingAs($plain)->getJson('/api/admin/procedural-corpus')->assertForbidden();
    });
});

describe('procedural corpus admin index', function (): void {
    it('returns an empty groups array for a missing/empty corpus (clean 200)', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->getJson('/api/admin/procedural-corpus');

        $response->assertOk()
            ->assertJsonStructure(['success', 'data' => ['groups']]);
        expect($response->json('success'))->toBeTrue()
            ->and($response->json('data.groups'))->toBe([]);
    });

    it('groups procedures by kind then module then procedure_id with a frontmatter-only summary', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);

        // Two versions of one tool_schema procedure: v1 inactive, v2 active.
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v1', frontmatter4f([
            'version' => 1, 'active' => false, 'effective_to' => '2026-06-01',
        ]));
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v2', frontmatter4f([
            'version' => 2, 'active' => true, 'effective_from' => '2026-06-02',
        ]));
        // A different kind/module procedure.
        writeCorpusProc($this->corpus, 'fca_block', 'protection', 'cover_disclaimer', frontmatter4f([
            'procedure_id' => 'protection.fca.cover_disclaimer',
            'kind' => 'fca_block', 'module' => 'protection', 'version' => 1, 'active' => true,
        ]));

        $response = $this->actingAs($admin)->getJson('/api/admin/procedural-corpus');

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => [
                'groups' => [
                    [
                        'kind',
                        'modules' => [
                            [
                                'module',
                                'procedures' => [
                                    [
                                        'procedure_id',
                                        'active_version',
                                        'version_count',
                                        'versions' => [['version', 'active', 'effective_from', 'effective_to']],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $groups = $response->json('data.groups');
        // Kinds sorted: fca_block before tool_schema.
        expect($groups[0]['kind'])->toBe('fca_block')
            ->and($groups[1]['kind'])->toBe('tool_schema');

        // The tool_schema retirement procedure has two versions, active=2.
        $retirement = collect($groups[1]['modules'])->firstWhere('module', 'retirement');
        expect($retirement)->not->toBeNull();
        $proc = collect($retirement['procedures'])->firstWhere('procedure_id', 'retirement.tool.create_dc_pension');
        expect($proc['version_count'])->toBe(2)
            ->and($proc['active_version'])->toBe(2)
            ->and($proc['versions'][0]['version'])->toBe(1)   // ascending
            ->and($proc['versions'][1]['version'])->toBe(2)
            ->and($proc['versions'][0]['active'])->toBeFalse()
            ->and($proc['versions'][1]['active'])->toBeTrue()
            ->and($proc['versions'][0]['effective_to'])->toBe('2026-06-01');

        // index summary carries NO body field.
        expect($proc['versions'][0])->not->toHaveKey('body');
    });

    it('serialises dates as ISO date strings and null effective_to as null', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        writeCorpusProc($this->corpus, 'workflow', 'onboarding', 'transition', frontmatter4f([
            'procedure_id' => 'onboarding.workflow.transition',
            'kind' => 'workflow', 'module' => 'onboarding', 'version' => 1, 'active' => true,
            'effective_from' => '2026-06-02',
        ]));

        $response = $this->actingAs($admin)->getJson('/api/admin/procedural-corpus');

        $version = $response->json('data.groups.0.modules.0.procedures.0.versions.0');
        expect($version['effective_from'])->toBe('2026-06-02')
            ->and($version['effective_to'])->toBeNull();
    });
});

describe('procedural corpus admin show', function (): void {
    it('returns every version (frontmatter + body) of one dotted procedure id', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v1', frontmatter4f([
            'version' => 1, 'active' => false, 'effective_to' => '2026-06-01',
        ]), 'BODY ONE');
        writeCorpusProc($this->corpus, 'tool_schema', 'retirement', 'create_dc_pension_v2', frontmatter4f([
            'version' => 2, 'active' => true,
        ]), 'BODY TWO');

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/procedural-corpus/retirement.tool.create_dc_pension');

        $response->assertOk()->assertJsonStructure([
            'success',
            'data' => [
                'procedure_id',
                'versions' => [['kind', 'module', 'version', 'active', 'effective_from', 'effective_to', 'body']],
            ],
        ]);
        expect($response->json('data.procedure_id'))->toBe('retirement.tool.create_dc_pension');
        $versions = $response->json('data.versions');
        expect($versions)->toHaveCount(2)
            ->and($versions[0]['version'])->toBe(1)        // ascending
            ->and($versions[0]['body'])->toBe('BODY ONE')
            ->and($versions[1]['version'])->toBe(2)
            ->and($versions[1]['body'])->toBe('BODY TWO');
    });

    it('returns an empty versions array (200, not 404) for an unknown procedure id', function (): void {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/procedural-corpus/does.not.exist');

        $response->assertOk();
        expect($response->json('success'))->toBeTrue()
            ->and($response->json('data.procedure_id'))->toBe('does.not.exist')
            ->and($response->json('data.versions'))->toBe([]);
    });

    it('rejects a non-admin user on the detail endpoint with 403', function (): void {
        $plain = User::factory()->create(['is_admin' => false, 'is_advisor' => false]);
        $this->actingAs($plain)
            ->getJson('/api/admin/procedural-corpus/retirement.tool.create_dc_pension')
            ->assertForbidden();
    });
});
```

- [ ] **Step 2: Run RED** (controller + routes absent yet)

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php
```

Expect failures (404/route-not-found or class-not-found). This confirms the test exercises the real surface.

- [ ] **Step 3: Implement Task 2 (controller) + Task 3 (routes)**, then run GREEN

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php
```

All assertions must pass. Loop (diagnose → fix → re-run) until green; do not edit assertions to pass.

- [ ] **Step 4: Pint the test file**

```bash
./vendor/bin/pint tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php
```

Must report `PASS`.

---

## Task 5: Frontend — API service, Vue view, route, and layout test

**Files:**
- Create: `resources/js/services/proceduralCorpusService.js`
- Create: `resources/js/views/Admin/ProceduralCorpusViewer.vue`
- Create: `tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php`
- Modify: `resources/js/router/index.js`

- [ ] **Step 1: Write the failing layout/compliance test (TDD red FIRST)**

This is a PHP string-assertion test (matching the repo's `DesignSystemInvariantsTest` file-reading convention) that pins Rule #14 (`<AppLayout>`), Rule #16 (no icons/emoji/Unicode-as-icon/icon-font/`::before` glyph), and Rule #13 (no numeric score) on the new Vue file, plus that the service + route exist.

```php
<?php

declare(strict_types=1);

it('ships the procedural corpus viewer view wrapped in AppLayout', function (): void {
    $path = base_path('resources/js/views/Admin/ProceduralCorpusViewer.vue');
    expect(file_exists($path))->toBeTrue();

    $contents = (string) file_get_contents($path);
    expect($contents)->toContain('<AppLayout>')
        ->and($contents)->toContain('</AppLayout>')
        ->and($contents)->toContain("import AppLayout from '@/layouts/AppLayout.vue'");
});

it('ships the procedural corpus API service with the two read methods', function (): void {
    $path = base_path('resources/js/services/proceduralCorpusService.js');
    expect(file_exists($path))->toBeTrue();

    $contents = (string) file_get_contents($path);
    expect($contents)->toContain('getCorpus')
        ->and($contents)->toContain('getProcedure')
        ->and($contents)->toContain('/admin/procedural-corpus');
});

it('registers the procedural corpus admin route as a lazy admin route', function (): void {
    $contents = (string) file_get_contents(base_path('resources/js/router/index.js'));
    expect($contents)->toContain('/admin/procedural-corpus')
        ->and($contents)->toContain('ProceduralCorpusViewer')
        ->and($contents)->toContain("import('@/views/Admin/ProceduralCorpusViewer.vue')");
});

it('the procedural corpus viewer contains no icon-font, emoji, Unicode-as-icon, or pseudo-element glyph (Rule #16)', function (): void {
    $contents = (string) file_get_contents(base_path('resources/js/views/Admin/ProceduralCorpusViewer.vue'));

    // Icon fonts / mascot inline icons.
    expect($contents)->not->toContain('fa-')
        ->and($contents)->not->toContain('material-icons')
        ->and($contents)->not->toContain('::before')
        ->and($contents)->not->toContain('::after');

    // No emoji or Unicode-as-icon glyphs anywhere in the file.
    $banned = ['★', '✓', '✗', '→', '←', '⚠', 'ℹ', '▲', '▼', '🔥', '🎯', '📈', '⭐', '🏆'];
    foreach ($banned as $glyph) {
        expect($contents)->not->toContain($glyph);
    }
    // Catch any other emoji via a broad pictographic/dingbat/arrow range.
    expect(preg_match('/[\x{2190}-\x{21FF}\x{2300}-\x{27BF}\x{1F000}-\x{1FAFF}\x{2600}-\x{26FF}]/u', $contents))
        ->toBe(0);
});

it('the procedural corpus viewer surfaces no numeric score in user-facing copy (Rule #13)', function (): void {
    $contents = (string) file_get_contents(base_path('resources/js/views/Admin/ProceduralCorpusViewer.vue'));
    // No "/100" score formatting and no "score" label in the template copy.
    expect($contents)->not->toContain('/100')
        ->and(strtolower($contents))->not->toContain('score');
});
```

Run RED:

```bash
./vendor/bin/pest tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php
```

Expect failures (files absent). Then implement Steps 2–4 and re-run GREEN.

- [ ] **Step 2: Create the API service wrapper**

`resources/js/services/proceduralCorpusService.js`:

```javascript
import api from './api';

const proceduralCorpusService = {
    async getCorpus() {
        const response = await api.get('/admin/procedural-corpus');
        return response.data;
    },

    async getProcedure(procedureId) {
        const response = await api.get(`/admin/procedural-corpus/${encodeURIComponent(procedureId)}`);
        return response.data;
    },
};

export default proceduralCorpusService;
```

- [ ] **Step 3: Create the Vue view**

`resources/js/views/Admin/ProceduralCorpusViewer.vue`. No icons/emoji/Unicode-as-icons (Rule #16), no numeric scores (Rule #13), British spelling, `<AppLayout>`-wrapped (Rule #14). Mirrors `EpisodicComplianceLog.vue`'s loading/error/empty/expand pattern and `<pre class="whitespace-pre-wrap">` body rendering (no markdown dependency). British date display via the existing `dateFormatter` util.

```vue
<template>
  <AppLayout>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <header class="mb-6">
        <h1 class="text-3xl font-black text-horizon-500" style="letter-spacing:-0.02em;">Procedural memory corpus</h1>
        <p class="text-sm text-neutral-500 mt-1">
          Read-only view of every procedure Fyn loads from the git-tracked corpus, grouped by kind and module.
          Procedures are edited via pull request to the corpus, never from this page.
        </p>
      </header>

      <!-- Loading -->
      <div v-if="loading" class="flex items-center justify-center py-16">
        <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="card-lg text-center text-raspberry-500 py-10">{{ error }}</div>

      <!-- Empty -->
      <div v-else-if="groups.length === 0" class="card-lg text-center text-neutral-500 py-10">
        No procedures are present in the corpus.
      </div>

      <!-- Grouped list -->
      <template v-else>
        <section v-for="group in groups" :key="group.kind" class="mb-8">
          <h2 class="text-lg font-bold text-horizon-500 mb-3">{{ formatKind(group.kind) }}</h2>

          <div v-for="moduleGroup in group.modules" :key="`${group.kind}-${moduleGroup.module}`" class="mb-4">
            <h3 class="text-sm font-semibold text-neutral-500 mb-2">{{ formatModule(moduleGroup.module) }}</h3>

            <div class="card-lg overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr class="text-left text-neutral-500 border-b border-light-gray">
                    <th class="py-2 pr-3 font-medium">Procedure</th>
                    <th class="py-2 px-3 font-medium">Active version</th>
                    <th class="py-2 px-3 font-medium">Versions</th>
                    <th class="py-2 pl-3 font-medium text-right">Detail</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-for="proc in moduleGroup.procedures" :key="proc.procedure_id">
                    <tr
                      class="border-b border-light-gray cursor-pointer hover:bg-savannah-100 transition-colors"
                      @click="toggleProcedure(proc.procedure_id)"
                    >
                      <td class="py-3 pr-3 text-horizon-500 font-semibold break-all">{{ proc.procedure_id }}</td>
                      <td class="py-3 px-3 text-neutral-500">
                        {{ proc.active_version === null ? 'None active' : `v${proc.active_version}` }}
                      </td>
                      <td class="py-3 px-3 text-neutral-500">{{ proc.version_count }}</td>
                      <td class="py-3 pl-3 text-right text-neutral-500">
                        {{ expandedId === proc.procedure_id ? 'Hide' : 'View' }}
                      </td>
                    </tr>

                    <!-- Expanded detail row -->
                    <tr v-if="expandedId === proc.procedure_id" :key="`detail-${proc.procedure_id}`">
                      <td colspan="4" class="bg-eggshell-500 px-4 py-4">
                        <div v-if="detailLoading" class="flex justify-center py-8">
                          <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin"></div>
                        </div>

                        <div v-else-if="detailError" class="py-6 text-sm text-raspberry-500 font-semibold">
                          {{ detailError }}
                        </div>

                        <div v-else-if="detailVersions.length === 0" class="py-6 text-sm text-neutral-500">
                          No versions found for this procedure.
                        </div>

                        <div v-else class="space-y-4">
                          <div
                            v-for="version in detailVersions"
                            :key="version.version"
                            class="bg-white border border-light-gray rounded-lg overflow-hidden"
                          >
                            <!-- Frontmatter header table -->
                            <table class="w-full text-xs">
                              <tbody>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500 w-40">Version</th>
                                  <td class="py-2 px-4 text-horizon-500">v{{ version.version }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Status</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ version.active ? 'Active' : 'Inactive' }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Kind</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ formatKind(version.kind) }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Module</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ formatModule(version.module) }}</td>
                                </tr>
                                <tr class="border-b border-light-gray">
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Effective from</th>
                                  <td class="py-2 px-4 text-horizon-500">{{ formatDate(version.effective_from) }}</td>
                                </tr>
                                <tr>
                                  <th class="text-left py-2 px-4 font-semibold text-neutral-500">Effective to</th>
                                  <td class="py-2 px-4 text-horizon-500">
                                    {{ version.effective_to ? formatDate(version.effective_to) : 'Open-ended' }}
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                            <!-- Body, verbatim, no markdown rendering -->
                            <pre
                              class="scrollbar-thin m-0 px-4 py-3 max-h-96 overflow-auto bg-eggshell-500 border-t border-light-gray text-xs text-horizon-500 whitespace-pre-wrap break-words font-mono"
                            >{{ version.body }}</pre>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </template>
                </tbody>
              </table>
            </div>
          </div>
        </section>
      </template>
    </div>
  </AppLayout>
</template>

<script>
import AppLayout from '@/layouts/AppLayout.vue';
import proceduralCorpusService from '@/services/proceduralCorpusService';
import { formatDate } from '@/utils/dateFormatter';

export default {
  name: 'ProceduralCorpusViewer',

  components: { AppLayout },

  data() {
    return {
      loading: true,
      error: null,
      groups: [],
      expandedId: null,
      detailVersions: [],
      detailLoading: false,
      detailError: null,
    };
  },

  mounted() {
    this.fetchCorpus();
  },

  methods: {
    async fetchCorpus() {
      this.loading = true;
      this.error = null;
      this.expandedId = null;
      try {
        const response = await proceduralCorpusService.getCorpus();
        const data = response.data || {};
        this.groups = Array.isArray(data.groups) ? data.groups : [];
      } catch (e) {
        this.error = 'Could not load the procedural corpus. Please try again.';
        this.groups = [];
      } finally {
        this.loading = false;
      }
    },

    async toggleProcedure(procedureId) {
      if (this.expandedId === procedureId) {
        this.expandedId = null;
        return;
      }
      this.expandedId = procedureId;
      this.detailVersions = [];
      this.detailError = null;
      this.detailLoading = true;

      try {
        const response = await proceduralCorpusService.getProcedure(procedureId);
        const data = response.data || {};
        this.detailVersions = Array.isArray(data.versions) ? data.versions : [];
      } catch (e) {
        this.detailError = 'Unable to load this procedure. Please try again.';
      } finally {
        this.detailLoading = false;
      }
    },

    formatKind(kind) {
      const labels = {
        system_prompt_overlay: 'System prompt overlay',
        workflow: 'Workflow',
        tool_schema: 'Tool schema',
        fca_block: 'Regulatory block',
      };
      return labels[kind] || (kind || '').replace(/_/g, ' ');
    },

    formatModule(module) {
      if (!module) return 'General';
      return module.charAt(0).toUpperCase() + module.slice(1).replace(/_/g, ' ');
    },

    formatDate(dateStr) {
      if (!dateStr) return '';
      return formatDate(dateStr);
    },
  },
};
</script>
```

> Note on Rule #13: `version_count` and `vN` are version identifiers, not numeric quality ratings — they are not scores. The view shows no adequacy/diversification/health score. The Task 5 Step 1 test also forbids the literal substring `score` and `/100` to keep this clear.

- [ ] **Step 4: Register the lazy admin route**

In `resources/js/router/index.js`:

(a) Add the lazy import alongside the other admin lazy imports (near the `EpisodicComplianceLog` import at index.js:134):

```javascript
const ProceduralCorpusViewer = () => import('@/views/Admin/ProceduralCorpusViewer.vue');
```

(b) Add the route object directly after the `EpisodicComplianceLog` route block (index.js:1210–1223), mirroring its shape:

```javascript
  {
    path: '/admin/procedural-corpus',
    name: 'ProceduralCorpusViewer',
    component: ProceduralCorpusViewer,
    meta: {
      requiresAuth: true,
      requiresAdmin: true,
      breadcrumb: [
        { label: 'Home', path: '/dashboard' },
        { label: 'Admin Panel', path: '/admin' },
        { label: 'Procedural memory', path: '/admin/procedural-corpus' },
      ],
    },
  },
```

- [ ] **Step 5: Run the layout/compliance test GREEN**

```bash
./vendor/bin/pest tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php
```

All five assertions must pass. Loop until green.

- [ ] **Step 6: Pint the new test file**

```bash
./vendor/bin/pint tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php
```

Must report `PASS`. (Frontend files need no Pint; they are JS/Vue.)

---

## Task 6: Regression + Pint sweep (close the golden-master gate)

**Files:** none (verification only).

- [ ] **Step 1: Re-run the three guarded suites and assert identical-or-better against Task 1's baseline**

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural
./vendor/bin/pest tests/Feature/AI tests/Unit/Services/AI
```

- `tests/Unit/Services/AI/Memory/Procedural` pass count MUST equal the Task 1 baseline (loader/corpus untouched → §5.2 proven).
- `tests/Feature/AI` pass count MUST be the Task 1 baseline **plus** the new `ProceduralCorpusAdminEndpointTest` cases; no pre-existing case may flip red (prompt/tool/assembler golden-masters intact → §5.1 proven).
- `tests/Unit/Services/AI` pass count MUST equal the Task 1 baseline.

If any pre-existing test regressed, STOP, diagnose, fix the code (never the assertion), and re-run. Do not declare done on a regressed baseline.

- [ ] **Step 2: Structural proof of "no tool/prompt touch" (spec §5.1)**

```bash
grep -RnE "AiToolDefinitions|FynSystemPrompt|FynContextAssembler" \
  app/Http/Controllers/Api/Admin/ProceduralCorpusController.php \
  resources/js/services/proceduralCorpusService.js \
  resources/js/views/Admin/ProceduralCorpusViewer.vue
```

Expect NO matches — the new files import none of these, register no tool, and touch neither write-state.

- [ ] **Step 3: Confirm read-only (no write verbs in the new surface)**

```bash
grep -RnE "->post\(|->put\(|->patch\(|->delete\(|api\.(post|put|patch|delete)" \
  resources/js/services/proceduralCorpusService.js \
  app/Http/Controllers/Api/Admin/ProceduralCorpusController.php
grep -nE "procedural-corpus" routes/api.php
```

Expect: no write verbs in the service/controller; the two `procedural-corpus` routes are both `Route::get`.

- [ ] **Step 4: Confirm loadStrict() is not used outside the deploy gate**

```bash
grep -Rn "loadStrict" app/Http/Controllers/Api/Admin/ProceduralCorpusController.php
```

Expect NO matches — 4f uses `load()` only.

- [ ] **Step 5: Route-list verification**

```bash
php artisan route:list --path=admin/procedural-corpus
```

Two rows, both `auth:sanctum` + `permission:admin.access`.

- [ ] **Step 6: Final Pint sweep on every touched PHP file**

```bash
./vendor/bin/pint \
  app/Http/Controllers/Api/Admin/ProceduralCorpusController.php \
  routes/api.php \
  tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php \
  tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php
```

Must report `PASS` for all. Re-add any import Pint transiently strips, then re-run.

- [ ] **Step 7: Run the two new test files together one final time**

```bash
./vendor/bin/pest tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php
```

All green.

- [ ] **Step 8: Remove the scratch baseline file and commit**

```bash
rm -f docs/superpowers/plans/4f-baseline.txt
git add app/Http/Controllers/Api/Admin/ProceduralCorpusController.php \
        routes/api.php \
        resources/js/services/proceduralCorpusService.js \
        resources/js/views/Admin/ProceduralCorpusViewer.vue \
        resources/js/router/index.js \
        tests/Feature/AI/ProceduralCorpusAdminEndpointTest.php \
        tests/Unit/Frontend/ProceduralCorpusViewerLayoutTest.php
git commit -m "feat(coala-4f): read-only procedural corpus admin viewer

Add an admin-gated, read-only surface to view the Phase 4a procedural
memory corpus: two GET endpoints over ProceduralCorpus (index grouped by
kind/module, show per procedure_id with body), a Vue admin view wrapped
in AppLayout (no icons, no scores, British spelling), a lazy admin route,
and an API service wrapper. Purely additive: no writes, no new AI tools,
no change to the assembled tool catalogue, prompt prefix, or either Fyn
write-state. Controller calls ProceduralCorpusLoader::load() (never-throws
runtime entry) so a malformed/missing corpus degrades to a clean empty
200, never an error.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Done-when (spec §9)

- [ ] Branch `feat/coala-4f-admin-viewer` carries spec + this plan + code commits.
- [ ] `GET admin/procedural-corpus` + `GET admin/procedural-corpus/{procedureId}` live, admin-gated, read-only.
- [ ] `ProceduralCorpusViewer.vue` exists, wraps `<AppLayout>`, no icons, no scores, British spelling, renders empty state cleanly.
- [ ] Route registered (lazy, `requiresAdmin`).
- [ ] Feature test + view-layout test green.
- [ ] Existing procedural + AI prompt/tool/assembler suites stay green (Task 1 baseline equalled or bettered).
- [ ] `./vendor/bin/pint` reports passed on all touched PHP files.
