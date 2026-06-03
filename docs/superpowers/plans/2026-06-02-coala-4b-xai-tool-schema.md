# CoALA Phase 4b-xai — xAI Tool Schema Externalisation (TDD Implementation Plan)

**Branch:** `feat/coala-4b-xai-tool-schema` (off `feat/coala-4f-admin-viewer`)
**Spec:** `docs/superpowers/specs/2026-06-02-coala-4b-xai-tool-schema-design.md`
**Date:** 2026-06-02

Strict TDD: each task is red → green → commit. `declare(strict_types=1)` in every PHP file
incl. tests; Pest `it()` syntax; British user-facing / American code spelling. Run
`./vendor/bin/pint <files>` before each commit (must pass). Watch the Pint unused-`use`-stripping
quirk — prefer import+usage in a single Write.

Conventional commit footer on every commit:
```
Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>
```

---

## Verified live-state facts (from this run)

- `XaiToolDefinitions` public surface: `getTools(bool $isPreviewMode = false)` and
  `handoffTools()` (no provider arg). No `onboardingExtractionTools()`.
- `getTools(false)` xAI static (non-`fetch_`) order = **43 tools**; `getTools(true)` = first **12**;
  `handoffTools()` = `delegate_to_capture`, `capture_complete`.
- xAI `dataCreationTools()` = goals → savings → property → protection → estate → **expenditure**
  (set_expenditure nested at the tail of dataCreation, BEFORE additional/modification/profile/campaign).
  This differs from Anthropic's ORDER (expenditure is a separate top-level group after profile).
- `wrapTool()`: `{type:function, function:{name, description, parameters:{type:object,
  properties, required, additionalProperties:false}, [strict:true]}}`. Empty `properties` → `(object) []`.
  `strict:true` appended **only** when `$strict` true (omitted for `create_what_if_scenario`,
  `update_record`, `update_profile`).
- `nullableEnum()` → `{anyOf:[{type:string,enum:[…]},{type:null}], description:…}`.
- xAI `update_record` parameters = bespoke strict-shape with the union of all allowlist field names
  as `['string','number','boolean','null']` props + `enum: entityTypes()`, `strict:false`. It does
  **NOT** use the Anthropic `$allowlist`/`oneOf` sentinel. Captured verbatim into the `.xai.md`.
- Config path: `config('fyn.memory.procedural_path')` = `base_path('fyn-memory/procedural')`.
- Loader scans `KINDS = ['system_prompt_overlay','workflow','tool_schema','fca_block']` via
  `File::allFiles()`; picks `getExtension()==='md'`; skips `_TEMPLATE.md`/`README.md`.
  `.xai.md` files have `getExtension()==='md'` and are picked up. Provider comes from frontmatter.
- **THREE positional `asOf` callers of `active()`** (spec listed only ProceduralCorpusTest):
  1. `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php:50,55,63`
  2. `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php:86`
  All become `active($id, asOf: …)` after the signature change. `AiToolDefinitions:149`
  (`active($procedureId)`) and `ProceduralCorpusLoaderTest:133` (`active($id)`) pass only the
  first positional and are unaffected.
- Module dir map for procedure_ids (xAI tool → `{module}.tool.{name}`):
  - navigation: `navigate_to_page`
  - analysis: `list_records, list_goals, list_life_events, get_module_analysis,
    get_recommendations, search_conversation_index`
  - tax: `get_tax_information`
  - plans: `generate_financial_plan`
  - billing: `get_subscription_status, list_invoices, get_current_plan`
  - whatif: `create_what_if_scenario`
  - goals: `create_goal, create_life_event`
  - savings: `create_savings_account, create_investment_account, create_holding, create_pension`
  - property: `create_property, create_mortgage`
  - protection: `create_protection_policy`
  - estate: `create_asset, create_liability, create_estate_gift, create_will, update_will,
    create_power_of_attorney, update_power_of_attorney`  (creation group),
    `create_family_member, create_trust, create_business_interest, create_chattel` (additional group)
  - expenditure: `set_expenditure`
  - data: `update_record, delete_record, update_profile`
  - campaign: `capture_salary_sacrifice, capture_spouse_work_status, capture_spouse_household_data,
    capture_spouse_non_working_assets, capture_pension_history, capture_charitable_giving`
  - handoff: `delegate_to_capture, capture_complete`
  Total getTools(false): 43; + handoff 2 = **45 xai tool_schema files**.

---

## Task 0 — Plan committed

Commit this plan.

```
docs(coala-4b-xai): TDD implementation plan for xAI tool schema externalisation
```

---

## Task 1 — Golden-master fixtures captured from the CURRENT hardcoded XaiToolDefinitions (FIRST, before any refactor)

**Goal:** freeze the live hardcoded xAI catalogue bytes BEFORE touching anything.

### 1.1 Write the test `tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php`

Mirror `tests/Feature/AI/ToolSchemaGoldenMasterTest.php`. The `fetch_*` filter reads the **xAI**
name path `$t['function']['name']` (xAI tools are pre-wrapped).

```php
<?php

declare(strict_types=1);

use App\Services\AI\XaiToolDefinitions;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 4b-xai hard gate. The fixtures in tests/Fixtures/XaiToolSchema are the
 * byte-for-byte assembled xAI tool catalogue BEFORE externalisation. After the
 * refactor the corpus-driven assembly must reproduce them exactly.
 *
 * The dynamic `fetch_*` pointer tools (live PointerRegistry) are out of scope
 * and filtered out of every captured/asserted catalogue so the golden master
 * is deterministic. A separate assertion confirms the pointer tool names/count
 * are unchanged across the refactor (produced by the untouched pointerTools()).
 */
$fixtureDir = __DIR__.'/../../Fixtures/XaiToolSchema';

/** Deterministic, ordering-faithful encoding of an xAI tool list with fetch_* removed. */
$encode = function (array $tools): string {
    $static = array_values(array_filter(
        $tools,
        static fn (array $t): bool => ! str_starts_with(($t['function']['name'] ?? ''), 'fetch_'),
    ));

    return json_encode($static, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
};

/**
 * The 3 variants. xAI tools are pre-wrapped; getTools() reads no cache key for
 * shape (always OpenAI function objects), so no Cache::put is needed, but we set
 * the provider cache the way the live path does for parity with pointerTools().
 */
$variants = function () {
    return [
        'getTools_xai_live' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(XaiToolDefinitions::class)->getTools(false);
        },
        'getTools_xai_preview' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(XaiToolDefinitions::class)->getTools(true);
        },
        'handoffTools_xai' => fn (): array => app(XaiToolDefinitions::class)->handoffTools(),
    ];
};

it('captures the current xAI catalogue into fixtures', function () use ($fixtureDir, $encode, $variants): void {
    if (getenv('CAPTURE_XAI_TOOL_SCHEMA_GOLDEN') !== '1') {
        $this->markTestSkipped('Capture only runs with CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1.');
    }

    if (! is_dir($fixtureDir)) {
        mkdir($fixtureDir, 0777, true);
    }

    foreach ($variants() as $name => $build) {
        file_put_contents($fixtureDir.'/'.$name.'.json', $encode($build()));
    }

    expect(glob($fixtureDir.'/*.json'))->toHaveCount(3);
});

it('assembles each xAI variant byte-identical to the committed fixture', function (string $name, $build) use ($fixtureDir, $encode): void {
    $fixturePath = $fixtureDir.'/'.$name.'.json';
    expect(file_exists($fixturePath))->toBeTrue("Missing fixture {$name}.json — run the capture step first.");

    expect($encode($build()))->toBe(file_get_contents($fixturePath));
})->with(function () use ($variants) {
    $out = [];
    foreach ($variants() as $name => $build) {
        $out[$name] = [$name, $build];
    }

    return $out;
});

it('keeps the xAI pointer-tool names and count unchanged (out of scope)', function (): void {
    Cache::put('ai_provider', 'xai');
    $tools = app(XaiToolDefinitions::class)->getTools(false);
    $fetchNames = array_values(array_filter(
        array_map(static fn (array $t): string => $t['function']['name'] ?? '', $tools),
        static fn (string $n): bool => str_starts_with($n, 'fetch_'),
    ));

    $baselinePath = __DIR__.'/../../Fixtures/XaiToolSchema/_pointer_baseline.json';
    if (getenv('CAPTURE_XAI_TOOL_SCHEMA_GOLDEN') === '1') {
        file_put_contents($baselinePath, json_encode($fetchNames, JSON_PRETTY_PRINT));
        $this->markTestSkipped('Captured xAI pointer baseline.');
    }

    expect(file_exists($baselinePath))->toBeTrue();
    expect($fetchNames)->toBe(json_decode(file_get_contents($baselinePath), true));
});
```

### 1.2 Capture the fixtures from the current hardcoded class

```bash
CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```

Expect: `tests/Fixtures/XaiToolSchema/getTools_xai_live.json`,
`getTools_xai_preview.json`, `handoffTools_xai.json`, `_pointer_baseline.json` written.

### 1.3 Verify the assertion variants pass against the just-captured fixtures (still hardcoded)

```bash
./vendor/bin/pest tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```

Expect: the 3 assertion cases + pointer-count case GREEN, capture case skipped.

**Sanity checks on the captured fixtures (eyeball before committing):**
- `getTools_xai_live.json` has 43 tools, each `{type:function, function:{…}}`.
- `getTools_xai_preview.json` has 12 tools.
- `create_what_if_scenario`, `update_record`, `update_profile` have **no** `strict` key inside
  `function`; all others have `strict:true`.
- nullable enums render as `anyOf`.

### 1.4 Commit

```bash
./vendor/bin/pint tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```

```
test(coala-4b-xai): capture xAI tool catalogue golden-master fixtures from hardcoded class
```

> This is the byte-identity contract. Everything after must reproduce these bytes.

---

## Task 2 — Substrate: `Procedure.$provider`

### 2.1 Red — add the assertion to `ProcedureTest.php`

Open `tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php`, add:

```php
it('defaults provider to anthropic when not supplied', function (): void {
    $proc = new Procedure(
        procedureId: 'x.tool.y',
        kind: 'tool_schema',
        module: 'x',
        version: 1,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: 'body',
    );
    expect($proc->provider)->toBe('anthropic');
});

it('accepts an explicit provider', function (): void {
    $proc = new Procedure(
        procedureId: 'x.tool.y',
        kind: 'tool_schema',
        module: 'x',
        version: 1,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: 'body',
        provider: 'xai',
    );
    expect($proc->provider)->toBe('xai');
});
```

(If `ProcedureTest.php` does not yet import `Carbon`, add `use Illuminate\Support\Carbon;`.)

Run → red (no `provider` property).

### 2.2 Green — `app/Services/AI/Memory/Procedural/Procedure.php`

Add `provider` as the **last** ctor param with default `'anthropic'`:

```php
    public function __construct(
        public readonly string $procedureId,
        public readonly string $kind,
        public readonly string $module,
        public readonly int $version,
        public readonly bool $active,
        public readonly Carbon $effectiveFrom,
        public readonly ?Carbon $effectiveTo,
        public readonly string $body,
        public readonly string $provider = 'anthropic',
    ) {}
```

Run `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php` → green.

### 2.3 Commit

```bash
./vendor/bin/pint app/Services/AI/Memory/Procedural/Procedure.php tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php
```

```
feat(coala-4b-xai): add provider axis to Procedure VO (default anthropic)
```

---

## Task 3 — Substrate: `ProceduralCorpus::active()` provider selector

### 3.1 Red — update `ProceduralCorpusTest.php`

This is the breaking-signature edit. Change the three positional `asOf` calls to named, and add a
provider-selection case.

Replace lines 45-64 region:

```php
it('resolves the active version effective on a date', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, false, '2025-01-01', '2025-12-31'),
        proc('a', 2, true, '2026-01-01'),
    ]);
    expect($corpus->active('a', asOf: Carbon::parse('2026-06-02'))?->version)->toBe(2);
});

it('returns null when no active version is effective on the date', function (): void {
    $corpus = new ProceduralCorpus([proc('a', 2, true, '2027-01-01')]);
    expect($corpus->active('a', asOf: Carbon::parse('2026-06-02')))->toBeNull();
});

it('returns the highest-version active when several qualify', function (): void {
    $corpus = new ProceduralCorpus([
        proc('a', 1, true, '2025-01-01'),
        proc('a', 3, true, '2026-01-01'),
    ]);
    expect($corpus->active('a', asOf: Carbon::parse('2026-06-02'))?->version)->toBe(3);
});

it('resolves the active version per provider', function (): void {
    $anth = new Procedure(
        procedureId: 'a', kind: 'tool_schema', module: 'retirement', version: 1, active: true,
        effectiveFrom: Carbon::parse('2026-01-01'), effectiveTo: null, body: 'anth', provider: 'anthropic',
    );
    $xai = new Procedure(
        procedureId: 'a', kind: 'tool_schema', module: 'retirement', version: 1, active: true,
        effectiveFrom: Carbon::parse('2026-01-01'), effectiveTo: null, body: 'xai', provider: 'xai',
    );
    $corpus = new ProceduralCorpus([$anth, $xai]);

    expect($corpus->active('a')?->body)->toBe('anth')           // defaults to anthropic
        ->and($corpus->active('a', 'anthropic')?->body)->toBe('anth')
        ->and($corpus->active('a', 'xai')?->body)->toBe('xai');
});
```

`ProceduralCorpusTest.php` already imports `Procedure` and `Carbon`. Run → red (signature mismatch:
the new provider-selection case fails because `active()` has no provider param yet).

### 3.2 Green — `app/Services/AI/Memory/Procedural/ProceduralCorpus.php`

New signature (provider 2nd positional, asOf 3rd):

```php
    /** The active version effective on $asOf (default now) for $provider; highest version wins ties. */
    public function active(string $procedureId, string $provider = 'anthropic', ?Carbon $asOf = null): ?Procedure
    {
        $asOf ??= Carbon::now();
        $candidates = array_values(array_filter(
            $this->procedures,
            fn (Procedure $p): bool => $p->procedureId === $procedureId
                && $p->provider === $provider
                && $p->active
                && $p->effectiveOn($asOf),
        ));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn (Procedure $a, Procedure $b): int => $b->version <=> $a->version);

        return $candidates[0];
    }
```

### 3.3 Fix the OTHER positional `asOf` caller

`tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php:86`:

```php
    $proc = $corpus->active('onboarding.workflow.fyn-onboarding', asOf: Carbon::now());
```

### 3.4 Run

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php \
  tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php
```
Green.

> `AiToolDefinitions:149` `$corpus->active($procedureId)` and `ProceduralCorpusLoaderTest:133`
> `active($id)` pass only the first positional → resolve `provider='anthropic'`, `asOf=now`.
> Byte-identical behaviour preserved.

### 3.5 Commit

```bash
./vendor/bin/pint app/Services/AI/Memory/Procedural/ProceduralCorpus.php \
  tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php \
  tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php
```

```
feat(coala-4b-xai): add provider selector to ProceduralCorpus::active()
```

---

## Task 4 — Substrate: `ProceduralCorpusLoader` parse + validate `provider`, (id,provider) uniqueness

### 4.1 Red — update `ProceduralCorpusLoaderTest.php`

The `writeProc()`/`validFrontmatter()` helpers gain optional `provider` support (just pass it as a
frontmatter key — `writeProc` already serialises arbitrary keys). Edits:

**(a)** Keep the existing "rejects more than one active version of the same procedure_id" test as-is
(both files default to `provider:anthropic` → same `(id,provider)` → still throws).

**(b)** ADD a positive provider-split case:

```php
it('allows two actives with the same procedure_id under different providers', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'anth', validFrontmatter(['version' => 1, 'active' => true]), "```json\n{}\n```");
    writeProc($this->corpus, 'tool_schema', 'retirement', 'xai', validFrontmatter(['version' => 1, 'active' => true, 'provider' => 'xai']), "```json\n{}\n```");

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();

    expect($corpus->active('retirement.tool.create_dc_pension', 'anthropic'))->not->toBeNull()
        ->and($corpus->active('retirement.tool.create_dc_pension', 'anthropic')->provider)->toBe('anthropic')
        ->and($corpus->active('retirement.tool.create_dc_pension', 'xai'))->not->toBeNull()
        ->and($corpus->active('retirement.tool.create_dc_pension', 'xai')->provider)->toBe('xai');
});
```

> NB both files share `procedure_id@version` = `retirement.tool.create_dc_pension@1`. The duplicate
> key must incorporate provider (`id@version|provider`) so these do NOT false-positive as a
> duplicate. This case proves the version-dedup key is provider-aware.

**(c)** ADD a negative unknown-provider case:

```php
it('rejects an out-of-range provider', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(['provider' => 'openai']), "```json\n{}\n```");

    expect(fn () => app(ProceduralCorpusLoader::class)->loadStrict())
        ->toThrow(RuntimeException::class, "unknown provider 'openai'");
});
```

**(d)** ADD a default-provider case:

```php
it('defaults provider to anthropic when omitted', function (): void {
    writeProc($this->corpus, 'tool_schema', 'retirement', 'x', validFrontmatter(), "```json\n{}\n```");

    $corpus = app(ProceduralCorpusLoader::class)->loadStrict();
    expect($corpus->all()[0]->provider)->toBe('anthropic');
});
```

Run → red (provider not parsed/validated; (id,provider) key not yet in place — case (b) fails on
duplicate-version error).

### 4.2 Green — `app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php`

**(i)** In `parseAndValidate()`, after the `$active` block and before `$effectiveFrom`, add provider
parse + validation:

```php
        $provider = isset($meta['provider']) ? (string) $meta['provider'] : 'anthropic';
        if (! in_array($provider, ['anthropic', 'xai'], true)) {
            throw new RuntimeException("Procedural corpus: unknown provider '{$provider}' ({$path}).");
        }
```

Pass it into the ctor (last arg):

```php
        return new Procedure(
            procedureId: $procedureId,
            kind: $kind,
            module: $module,
            version: $version,
            active: $active,
            effectiveFrom: $effectiveFrom,
            effectiveTo: $effectiveTo,
            body: $body,
            provider: $provider,
        );
```

**(ii)** In `parse()`, change the two dedup keys to incorporate provider:

```php
                $vk = $proc->procedureId.'@'.$proc->version.'|'.$proc->provider;
                if (isset($seenVersion[$vk])) {
                    throw new RuntimeException("Procedural corpus: duplicate {$vk} ({$file->getPathname()}).");
                }
                $seenVersion[$vk] = $file->getPathname();

                if ($proc->active) {
                    $activeKey = $proc->procedureId.'|'.$proc->provider;
                    if (isset($activeById[$activeKey])) {
                        throw new RuntimeException("Procedural corpus: multiple active versions for '{$proc->procedureId}' ({$file->getPathname()}).");
                    }
                    $activeById[$activeKey] = $file->getPathname();
                }
```

> The "rejects duplicate (procedure_id, version)" existing test expects message
> `'duplicate retirement.tool.create_dc_pension@1'`. The new key string is
> `retirement.tool.create_dc_pension@1|anthropic`, and `toThrow(..., 'duplicate
> retirement.tool.create_dc_pension@1')` matches as a substring — **still green**.
> The "multiple active versions" message is unchanged.

### 4.3 Run

```bash
./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php
```
Green (all old + 3 new cases).

### 4.4 Commit

```bash
./vendor/bin/pint app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php \
  tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php
```

```
feat(coala-4b-xai): loader parses+validates provider, (id,provider) active-uniqueness
```

---

## Task 5 — Author the ~45 xAI `tool_schema` `.xai.md` files via a throwaway generator

### 5.1 Write the throwaway generator `scripts/gen_xai_tool_schema.php`

It boots the app, calls the **still-hardcoded** `XaiToolDefinitions->getTools(false)` and
`handoffTools()`, unwraps each `['function']`, maps `name → {module}.tool.{name}`, and writes the
inner function object (with `strict` present iff present) as pretty JSON into the fenced body of
`fyn-memory/procedural/tool_schema/{module}/{name}.xai.md`.

```php
<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\AI\XaiToolDefinitions;
use Illuminate\Support\Facades\Cache;

Cache::put('ai_provider', 'xai');

// tool name → module dir (matches the AiToolDefinitions ORDER module axis).
$moduleMap = [
    'navigate_to_page' => 'navigation',
    'list_records' => 'analysis', 'list_goals' => 'analysis', 'list_life_events' => 'analysis',
    'get_module_analysis' => 'analysis', 'get_recommendations' => 'analysis',
    'search_conversation_index' => 'analysis',
    'get_tax_information' => 'tax',
    'generate_financial_plan' => 'plans',
    'get_subscription_status' => 'billing', 'list_invoices' => 'billing', 'get_current_plan' => 'billing',
    'create_what_if_scenario' => 'whatif',
    'create_goal' => 'goals', 'create_life_event' => 'goals',
    'create_savings_account' => 'savings', 'create_investment_account' => 'savings',
    'create_holding' => 'savings', 'create_pension' => 'savings',
    'create_property' => 'property', 'create_mortgage' => 'property',
    'create_protection_policy' => 'protection',
    'create_asset' => 'estate', 'create_liability' => 'estate', 'create_estate_gift' => 'estate',
    'create_will' => 'estate', 'update_will' => 'estate', 'create_power_of_attorney' => 'estate',
    'update_power_of_attorney' => 'estate', 'create_family_member' => 'estate',
    'create_trust' => 'estate', 'create_business_interest' => 'estate', 'create_chattel' => 'estate',
    'set_expenditure' => 'expenditure',
    'update_record' => 'data', 'delete_record' => 'data', 'update_profile' => 'data',
    'capture_salary_sacrifice' => 'campaign', 'capture_spouse_work_status' => 'campaign',
    'capture_spouse_household_data' => 'campaign', 'capture_spouse_non_working_assets' => 'campaign',
    'capture_pension_history' => 'campaign', 'capture_charitable_giving' => 'campaign',
    'delegate_to_capture' => 'handoff', 'capture_complete' => 'handoff',
];

$defs = app(XaiToolDefinitions::class);
$tools = array_merge($defs->getTools(false), $defs->handoffTools());

$root = __DIR__.'/../fyn-memory/procedural/tool_schema';
$written = 0;

foreach ($tools as $tool) {
    $fn = $tool['function'] ?? null;
    if ($fn === null) {
        continue;
    }
    $name = $fn['name'];
    if (str_starts_with($name, 'fetch_')) {
        continue; // pointer tools out of scope
    }
    $module = $moduleMap[$name] ?? null;
    if ($module === null) {
        fwrite(STDERR, "NO MODULE for {$name}\n");
        exit(1);
    }

    // Inner object exactly as emitted: {name, description, parameters, [strict]}.
    // Key order preserved by iterating the source array.
    $inner = $fn;

    $json = json_encode($inner, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $procedureId = "{$module}.tool.{$name}";
    $fm = "---\n"
        ."procedure_id: '{$procedureId}'\n"
        ."kind: tool_schema\n"
        ."module: {$module}\n"
        ."provider: xai\n"
        ."version: 1\n"
        ."active: true\n"
        ."effective_from: 2026-06-02\n"
        ."---\n\n"
        ."```json\n{$json}\n```\n";

    $dir = "{$root}/{$module}";
    @mkdir($dir, 0777, true);
    file_put_contents("{$dir}/{$name}.xai.md", $fm);
    $written++;
}

echo "Wrote {$written} xAI tool_schema files.\n";
```

### 5.2 Run it

```bash
php scripts/gen_xai_tool_schema.php
```

Expect `Wrote 45 xAI tool_schema files.` and:

```bash
find fyn-memory/procedural/tool_schema -name '*.xai.md' | wc -l   # 45
```

### 5.3 Delete the generator

```bash
rm scripts/gen_xai_tool_schema.php
```

> The golden master is the correctness guarantee, not the generator.

### 5.4 Validate the corpus still loads strictly

```bash
php artisan fyn:procedural:validate
```

Expect exit 0 (now 49 anthropic + 45 xai tool_schema + existing workflow/overlay/fca_block kinds;
no `(id,provider)` collision because the xai files all carry `provider: xai`).

### 5.5 Commit

(No PHP to pint here — only `.xai.md` corpus files. `pint` is a no-op but run it on nothing / skip.)

```
feat(coala-4b-xai): author 45 xAI provider tool_schema corpus files
```

---

## Task 6 — `XaiToolDefinitions` becomes a thin corpus-driven assembler

### 6.1 Rewrite `app/Services/AI/XaiToolDefinitions.php`

Replace the body with a thin assembler mirroring `AiToolDefinitions::toolsFromCorpus()` /
`toolFromCorpus()`, preserving `getTools()` composition + preview gating + `pointerTools()` exactly.
The `ORDER` map encodes the **xAI** emission order (set_expenditure nested at the tail of
dataCreation).

```php
<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Services\AI\Memory\Episodic\ProceduralVersionHolder;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Pointers\Pointer;
use App\Services\AI\Pointers\PointerRegistry;

/**
 * xAI-optimised tool definitions with strict function calling.
 *
 * Thin corpus-driven assembler (Phase 4b-xai): reads provider=xai tool_schema
 * procedures from the procedural corpus, decodes each fenced ```json``` body,
 * and re-applies the OpenAI {type:function, function:{…}} wrapper. The strict
 * schema shapes (strict mode, anyOf nullable enums, enriched property schemas,
 * bespoke gathering instructions) live in the corpus bodies, NOT in code.
 *
 * The byte-for-byte contract is XaiToolSchemaGoldenMasterTest.
 */
class XaiToolDefinitions
{
    /**
     * Get all tool definitions in OpenAI function-calling format with strict mode.
     * Tools are pre-wrapped — no further wrapping needed in HasAiChat.
     */
    public function getTools(bool $isPreviewMode = false): array
    {
        $tools = [
            ...$this->navigationTools(),
            ...$this->analysisTools(),
            ...$this->taxTools(),
            ...$this->planGenerationTools(),
            ...$this->billingTools(),
        ];

        if (! $isPreviewMode) {
            $tools = array_merge(
                $tools,
                $this->whatIfTools(),
                $this->dataCreationTools(),
                $this->additionalCreationTools(),
                $this->dataModificationTools(),
                $this->profileTools(),
                $this->campaignSaveTaxTools(),
            );
        }

        // CoALA pointer tools — read-only `fetch_{pointer_id}` tools mirroring
        // the Anthropic catalogue so tool-name parity holds across providers.
        // Exposed in preview mode too (read-only). Degrades to none on error.
        $tools = array_merge($tools, $this->pointerTools());

        return $tools;
    }

    /**
     * Ordered procedure_id lists per grouping method, in the xAI emission order.
     * Guarded byte-for-byte by XaiToolSchemaGoldenMasterTest — do not reorder.
     * NB: set_expenditure is nested at the TAIL of dataCreation (xAI ordering),
     * which differs from the Anthropic ORDER map.
     */
    private const ORDER = [
        'navigation' => ['navigation.tool.navigate_to_page'],
        'analysis' => [
            'analysis.tool.list_records',
            'analysis.tool.list_goals',
            'analysis.tool.list_life_events',
            'analysis.tool.get_module_analysis',
            'analysis.tool.get_recommendations',
            'analysis.tool.search_conversation_index',
        ],
        'tax' => ['tax.tool.get_tax_information'],
        'plans' => ['plans.tool.generate_financial_plan'],
        'billing' => [
            'billing.tool.get_subscription_status',
            'billing.tool.list_invoices',
            'billing.tool.get_current_plan',
        ],
        'whatif' => ['whatif.tool.create_what_if_scenario'],
        'goals' => ['goals.tool.create_goal', 'goals.tool.create_life_event'],
        'savings' => [
            'savings.tool.create_savings_account',
            'savings.tool.create_investment_account',
            'savings.tool.create_holding',
            'savings.tool.create_pension',
        ],
        'property' => ['property.tool.create_property', 'property.tool.create_mortgage'],
        'protection' => ['protection.tool.create_protection_policy'],
        'estate' => [
            'estate.tool.create_asset',
            'estate.tool.create_liability',
            'estate.tool.create_estate_gift',
            'estate.tool.create_will',
            'estate.tool.update_will',
            'estate.tool.create_power_of_attorney',
            'estate.tool.update_power_of_attorney',
        ],
        'expenditure' => ['expenditure.tool.set_expenditure'],
        'additional' => [
            'estate.tool.create_family_member',
            'estate.tool.create_trust',
            'estate.tool.create_business_interest',
            'estate.tool.create_chattel',
        ],
        'modification' => ['data.tool.update_record', 'data.tool.delete_record'],
        'profile' => ['data.tool.update_profile'],
        'campaign' => [
            'campaign.tool.capture_salary_sacrifice',
            'campaign.tool.capture_spouse_work_status',
            'campaign.tool.capture_spouse_household_data',
            'campaign.tool.capture_spouse_non_working_assets',
            'campaign.tool.capture_pension_history',
            'campaign.tool.capture_charitable_giving',
        ],
        'handoff' => ['handoff.tool.delegate_to_capture', 'handoff.tool.capture_complete'],
    ];

    /**
     * Assemble pre-wrapped OpenAI function tools from the xAI procedural corpus,
     * in the given procedure_id order. Degrades at runtime (missing/undecodable
     * schema skipped + report()ed). Records each resolved procedure_id@version
     * into ProceduralVersionHolder so Phase 4e stamping fires on xAI turns.
     *
     * @param  list<string>  $procedureIds
     * @return list<array<string, mixed>>
     */
    private function toolsFromCorpus(array $procedureIds): array
    {
        $corpus = app(ProceduralCorpusLoader::class)->load();
        $versions = app(ProceduralVersionHolder::class);
        $tools = [];

        foreach ($procedureIds as $procedureId) {
            $procedure = $corpus->active($procedureId, 'xai');
            $tool = $this->toolFromCorpus($procedure);
            if ($tool !== null) {
                $tools[] = $tool;
                $versions->add($procedure->procedureId, $procedure->version);
            }
        }

        return $tools;
    }

    /**
     * Decode one xAI tool_schema procedure body (a fenced ```json block holding
     * the inner {name, description, parameters, [strict]} function object) and
     * re-wrap it into the OpenAI {type:function, function:{…}} shape. The strict
     * key is preserved iff present in the body. Returns null (and report()s) on
     * any failure so the catalogue degrades rather than emptying mid-turn.
     *
     * @return array<string, mixed>|null
     */
    private function toolFromCorpus(?Procedure $procedure): ?array
    {
        if ($procedure === null) {
            report(new \RuntimeException('xAI tool schema corpus: missing active procedure.'));

            return null;
        }

        $body = trim($procedure->body);
        $body = preg_replace('/^```json\s*\n/', '', $body);
        $body = preg_replace('/\n```\s*$/', '', (string) $body);

        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded) || ! isset($decoded['name'], $decoded['description'], $decoded['parameters'])) {
            report(new \RuntimeException("xAI tool schema corpus: undecodable body for '{$procedure->procedureId}'."));

            return null;
        }

        // Restore empty-object shape: json_decode(..., true) turns `{}` into `[]`,
        // which re-encodes as `[]` and breaks byte-identity with wrapTool's
        // `(object) []`. Re-objectify empty `properties`.
        $params = $decoded['parameters'];
        if (isset($params['properties']) && $params['properties'] === []) {
            $params['properties'] = (object) [];
        }
        $decoded['parameters'] = $params;

        return ['type' => 'function', 'function' => $decoded];
    }

    /**
     * CoALA pointer tools in OpenAI function-calling shape. One tool per
     * tool/both-mode pointer in the registry. Degrades to none on any error.
     *
     * @return list<array<string, mixed>>
     */
    private function pointerTools(): array
    {
        try {
            $pointers = app(PointerRegistry::class)->toolPointers();
        } catch (\Throwable $e) {
            return [];
        }

        return array_map(static fn (Pointer $pointer): array => [
            'type' => 'function',
            'function' => [
                'name' => 'fetch_'.str_replace('-', '_', $pointer->pointerId),
                'description' => $pointer->body,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object) [],
                    'required' => [],
                    'additionalProperties' => false,
                ],
                'strict' => true,
            ],
        ], $pointers);
    }

    private function navigationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['navigation']);
    }

    private function analysisTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['analysis']);
    }

    private function taxTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['tax']);
    }

    private function planGenerationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['plans']);
    }

    private function whatIfTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['whatif']);
    }

    private function dataCreationTools(): array
    {
        return [
            ...$this->goalAndEventTools(),
            ...$this->accountCreationTools(),
            ...$this->propertyCreationTools(),
            ...$this->protectionCreationTools(),
            ...$this->estateCreationTools(),
            ...$this->expenditureTools(),
        ];
    }

    private function goalAndEventTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['goals']);
    }

    private function accountCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['savings']);
    }

    private function propertyCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['property']);
    }

    private function protectionCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['protection']);
    }

    private function estateCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['estate']);
    }

    private function expenditureTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['expenditure']);
    }

    private function additionalCreationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['additional']);
    }

    private function dataModificationTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['modification']);
    }

    private function profileTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['profile']);
    }

    private function campaignSaveTaxTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['campaign']);
    }

    private function billingTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['billing']);
    }

    /**
     * Handoff tools — surfaced only during the onboarding inline-capture turn.
     * Mirrors the Anthropic handoff list, in OpenAI function-calling shape.
     *
     * @return list<array<string, mixed>>
     */
    public function handoffTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['handoff']);
    }
}
```

> **Pointer-tools byte-shape note:** the OLD `pointerTools()` used `wrapTool(..., [], [])`, which
> produced `function:{name, description, parameters:{type:object, properties:{}, required:[],
> additionalProperties:false}, strict:true}`. The new `pointerTools()` reproduces that exact shape
> explicitly (so any future test that does NOT filter `fetch_*` still matches). Pointer tools are
> filtered from the golden master, so this is belt-and-braces. The `_pointer_baseline.json`
> name/count assertion only checks names, which are unchanged.
>
> **`wrapTool`/`nullableEnum` removal:** both helpers become unused after the rewrite. Remove them
> (they are gone in the rewrite above). `UpdateRecordAllowlist` import is also dropped (the bespoke
> update_record shape is now in the `.xai.md` body). Run pint; if it flags an unused `use`, it is
> already removed in the Write above — verify no remaining references with
> `grep -n 'wrapTool\|nullableEnum\|UpdateRecordAllowlist' app/Services/AI/XaiToolDefinitions.php`
> → expect no output.

### 6.2 Run the golden master

```bash
./vendor/bin/pest tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php
```

**Expected:** the 3 byte-identity variants + pointer-count case GREEN.

**If RED (byte diff):** LOOP. Diff the assembled output vs the fixture:
- Re-run with `--filter` on the failing variant; dump both strings and `diff` them.
- Likely causes: (a) a `.xai.md` body key order differs from the live emission — the generator
  preserved source order, so re-inspect; (b) an empty-`properties` `{}` re-objectification missed a
  nested object (only top-level `parameters.properties` is re-objectified — verify no xAI tool has a
  nested empty object; the billing/handoff zero-param tools have top-level empty properties only);
  (c) a `strict` key present/absent mismatch (check the offending `.xai.md` body).
- Fix the `.xai.md` body or the assembler, re-run. **Repeat until byte-identical.**
- If genuinely unrepresentable in a static `.md`, STOP — status BLOCKED. Do not ship.

### 6.3 Commit

```bash
./vendor/bin/pint app/Services/AI/XaiToolDefinitions.php
grep -n 'wrapTool\|nullableEnum\|UpdateRecordAllowlist' app/Services/AI/XaiToolDefinitions.php || true
```

```
feat(coala-4b-xai): XaiToolDefinitions thin corpus-driven assembler + version recording
```

---

## Task 7 — Regression sweep + validate + pint

### 7.1 Run the full regression set

```bash
./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php \
  tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php \
  tests/Unit/Services/AI/Memory/Procedural \
  tests/Unit/Services/AI \
  tests/Feature/AI \
  tests/Feature/Console/FynProceduralValidateTest.php \
  tests/Architecture/ToolCatalogueParityTest.php \
  tests/Architecture/PreviewModeToolCatalogueTest.php \
  tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php
```

All green. Specifically verify:
- `ToolSchemaGoldenMasterTest` (4b Anthropic) — all 8 variants byte-identical + its
  "XaiToolDefinitions untouched by 4b" guard (asserts xAI name list non-empty — still true).
- `ProceduralVersionStampingTest` — xAI turns now record id@version (the assembler calls
  `$versions->add(...)`).
- `ToolCatalogueParityTest` / `PreviewModeToolCatalogueTest` — name-set parity Anthropic↔xAI
  (names unchanged).
- `SearchConversationIndexTest` ("XaiToolDefinitions registers search_conversation_index with strict
  mode") — strict shape preserved by byte-identity.
- `AdviceFynToolListTest`, `PropertyFynCaptureTest`, `PointerToolModeTest` — green.

If any fail, diagnose with file:line evidence, fix root cause, re-run from 7.1. Do not silence.

### 7.2 Validate command

```bash
php artisan fyn:procedural:validate
```
Exit 0 (49 anthropic + 45 xai tool_schema + existing other kinds).

### 7.3 Pint clean on all touched files

```bash
./vendor/bin/pint app/Services/AI/XaiToolDefinitions.php \
  app/Services/AI/Memory/Procedural/Procedure.php \
  app/Services/AI/Memory/Procedural/ProceduralCorpus.php \
  app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php \
  tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php \
  tests/Unit/Services/AI/Memory/Procedural/ProcedureTest.php \
  tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php \
  tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php \
  tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php
```
Clean.

### 7.4 Commit (only if 7.1-7.3 surfaced fixes)

```
test(coala-4b-xai): regression sweep green — Anthropic golden master + parity + validate
```

---

## Done-when checklist

1. ☐ Plan committed (Task 0).
2. ☐ xAI golden-master fixtures captured from the **current hardcoded** `XaiToolDefinitions` and
   committed (Task 1) — captured BEFORE any refactor.
3. ☐ `Procedure.$provider` (default anthropic), `active($id, $provider, $asOf)`, loader
   parse/validate + `(id,provider)` active-uniqueness; all 4a tests green (only the documented edits:
   ProcedureTest add, ProceduralCorpusTest named-asOf + provider case, OnboardingWorkflowTable named
   asOf, loader 3 new cases) (Tasks 2-4).
4. ☐ 45 `provider: xai` `tool_schema` `.xai.md` files authored; generator deleted (Task 5).
5. ☐ `XaiToolDefinitions` thin corpus-driven assembler; records `procedure_id@version` into
   `ProceduralVersionHolder`; pointer/preview/parity logic preserved (Task 6).
6. ☐ `XaiToolSchemaGoldenMasterTest` byte-identical — GREEN (Task 6).
7. ☐ 4b Anthropic golden master + all regression suites — GREEN (Task 7).
8. ☐ `fyn:procedural:validate` — exit 0 (Task 7).
9. ☐ `pint` clean on every touched file (every commit).

## Out of scope (unchanged)

- Dynamic `fetch_*` pointer tools (filtered from golden master).
- `AiToolDefinitions` / the 49 Anthropic `.md` files.
- `HasAiChat` dispatch, two-Fyn contract, provider-selection logic.
- The two pre-existing working-tree deletions (`public/images/logos/*.png`).
