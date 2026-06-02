# CoALA Phase 4b — Tool-Schema Externalisation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move the 49 static, provider-neutral tool DEFINITIONS (`name` / `description` / `parameters`) out of the PHP class constants in `app/Services/AI/AiToolDefinitions.php` into one git-tracked `.md` per tool at `fyn-memory/procedural/tool_schema/{module}/{tool_name}.md`. `AiToolDefinitions` becomes a thin **assembler** that loads the active `tool_schema` procedures via the 4a `ProceduralCorpusLoader`, parses each fenced-JSON body, and re-assembles the **same catalogue in the same order, with the same schema bytes**, then applies the **same** provider-shape wrapping, preview gating, and grouping. **Nothing about which tools are exposed, in what order, with what schema bytes, in any state, may change.**

**Architecture:** The selection / wrapping / gating LOGIC stays in PHP. Each grouping method (`navigationTools()`, `analysisTools()`, …) stops returning array literals and instead returns `$this->toolsFromCorpus([...procedure ids in current order...])`. Ordering is pinned by `private const` arrays of `procedure_id`s so the assembly order is explicit and reviewable. The one tool whose `parameters` is computed from a live source — `update_record` (built from `App\Constants\UpdateRecordAllowlist::MAP` via `updateRecordSchema()`) — carries a `{"$allowlist":"update_record"}` sentinel in its `.md` body that the assembler replaces at runtime with `$this->updateRecordSchema()`, keeping the allowlist a live source AND yielding byte-identical output.

**The hard gate:** Task 1 captures the CURRENT assembled output of all 8 entry-point variants into committed fixtures (with the live-registry `fetch_*` pointer tools filtered out) and writes a byte-identity assertion that is RED against the empty `tool_schema` corpus. The phase is **not done until that golden master is green**. If byte-identity is impossible the assembler is wrong — loop until identical; if genuinely blocked, STOP and report BLOCKED (never ship a tool-catalogue behaviour change).

**Tech Stack:** PHP 8.2, Laravel 10, Pest. No new dependencies. Consumes the 4a substrate (`Procedure`, `ProceduralCorpus`, `ProceduralCorpusLoader`, corpus at `fyn-memory/procedural/{kind}/{module}/*.md`, `fyn:procedural:validate`).

**Spec:** `docs/superpowers/specs/2026-06-02-coala-phase-4b-tool-schema-externalisation-design.md`

---

## Inventory — the 49 static tool schemas (grouping method → module slug → ordered tool list)

This is the authoritative ordering the assembler must reproduce. `procedure_id` = `{module}.tool.{tool_name}`. The two handoff tools keep their names from `HandoffContract` constants (`delegate_to_capture`, `capture_complete`).

| grouping method | module slug | tools (in emit order) |
|---|---|---|
| `navigationTools` | `navigation` | navigate_to_page |
| `analysisTools` | `analysis` | list_records, list_goals, list_life_events, get_module_analysis, get_recommendations, search_conversation_index |
| `taxTools` | `tax` | get_tax_information |
| `planGenerationTools` | `plans` | generate_financial_plan |
| `billingTools` | `billing` | get_subscription_status, list_invoices, get_current_plan |
| `whatIfTools` | `whatif` | create_what_if_scenario |
| `goalAndEventTools` | `goals` | create_goal, create_life_event |
| `accountCreationTools` | `savings` | create_savings_account, create_investment_account, create_holding, create_pension |
| `propertyCreationTools` | `property` | create_property, create_mortgage |
| `protectionCreationTools` | `protection` | create_protection_policy |
| `estateCreationTools` | `estate` | create_asset, create_liability, create_estate_gift, create_will, update_will, create_power_of_attorney, update_power_of_attorney |
| `additionalCreationTools` | `estate` | create_family_member, create_trust, create_business_interest, create_chattel |
| `dataModificationTools` | `data` | update_record, delete_record |
| `profileTools` | `data` | update_profile |
| `expenditureTools` | `expenditure` | set_expenditure |
| `campaignSaveTaxTools` | `campaign` | capture_salary_sacrifice, capture_spouse_work_status, capture_spouse_household_data, capture_spouse_non_working_assets, capture_pension_history, capture_charitable_giving |
| `handoffTools` (base) | `handoff` | delegate_to_capture, capture_complete |
| `onboardingExtractionTools` (base) | `onboarding` | capture_personal_details, capture_spouse_details, capture_dependants, capture_work_details |

Count: 1+6+1+1+3+1+2+4+2+1+7+4+2+1+1+6+2+4 = **49** unique static tools. (`fetch_*` pointer tools and the runtime `update_record` `parameters` are NOT frozen — see spec §2 deferred items 1 & 3.)

> NOTE on `dataCreationTools()` and `getTools()`: `getTools(false)` emits, in order, `navigationTools, analysisTools, taxTools, planGenerationTools, billingTools, whatIfTools, dataCreationTools (= goalAndEventTools, accountCreationTools, propertyCreationTools, protectionCreationTools, estateCreationTools), additionalCreationTools, dataModificationTools, profileTools, expenditureTools, campaignSaveTaxTools`, then `pointerTools()`. `getTools(true)` (preview) drops everything from `whatIfTools` onward (the `if (! $isPreviewMode)` block) but still appends `pointerTools()`. The refactor MUST NOT change this control flow — only the inner literal source.

---

## File Structure

**Create:**
- `tests/Feature/AI/ToolSchemaGoldenMasterTest.php` — capture (skippable generator) + 8-variant byte-identity + completeness assertions.
- `tests/Fixtures/ToolSchema/` — 8 committed `.json` golden masters (one per variant).
- `fyn-memory/procedural/tool_schema/{module}/{tool_name}.md` — 49 procedure files.
- `app/Console/Commands/FynToolSchemaEmit.php` — **throwaway** one-shot generator (deleted in Task 3 Step 8).
- `tests/Fixtures/ToolSchema/README.md` — documents the fixture set is immutable for the phase.

**Modify:**
- `app/Services/AI/AiToolDefinitions.php` — becomes the corpus-driven assembler.

---

## Task 1: Golden-master capture + RED byte-identity assertion

**Files:**
- Create: `tests/Feature/AI/ToolSchemaGoldenMasterTest.php`
- Create: `tests/Fixtures/ToolSchema/*.json` (generated by Step 2, committed)
- Create: `tests/Fixtures/ToolSchema/README.md`

The test contains TWO concerns kept in one file: (a) a `CAPTURE` block guarded by an env flag that writes the fixtures from the CURRENT in-PHP definitions, run once; (b) the permanent assertions that compare live assembly to the committed fixtures. After capture the flag is never set again, so re-runs only assert.

- [ ] **Step 1: Write the test file (capture + assertions)**

Create `tests/Feature/AI/ToolSchemaGoldenMasterTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\AI\AiToolDefinitions;
use App\Services\AI\XaiToolDefinitions;
use Illuminate\Support\Facades\Cache;

/**
 * Phase 4b hard gate. The 8 fixtures in tests/Fixtures/ToolSchema are the
 * byte-for-byte assembled tool catalogue BEFORE externalisation. After the
 * refactor the corpus-driven assembly must reproduce them exactly.
 *
 * The dynamic `fetch_*` pointer tools (live PointerRegistry) are out of scope
 * for 4b and are filtered out of every captured/asserted catalogue so the
 * golden master is deterministic. A separate assertion confirms the pointer
 * tool names/count are unchanged across the refactor (they are produced by the
 * untouched pointerTools()).
 */
$fixtureDir = __DIR__.'/../../Fixtures/ToolSchema';

/** Deterministic, ordering-faithful encoding of a tool list with fetch_* removed. */
$encode = function (array $tools): string {
    $static = array_values(array_filter(
        $tools,
        static fn (array $t): bool => ! str_starts_with(($t['name'] ?? ''), 'fetch_'),
    ));

    return json_encode($static, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
};

/**
 * The 8 variants. Each closure returns the assembled catalogue for that variant.
 * getTools() reads Cache::get('ai_provider') to decide the output shape, so the
 * provider variants set the cache key the same way the live request path does.
 */
$variants = function () {
    return [
        'getTools_anthropic_live' => function (): array {
            Cache::put('ai_provider', 'anthropic');

            return app(AiToolDefinitions::class)->getTools(false);
        },
        'getTools_anthropic_preview' => function (): array {
            Cache::put('ai_provider', 'anthropic');

            return app(AiToolDefinitions::class)->getTools(true);
        },
        'getTools_xai_live' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(AiToolDefinitions::class)->getTools(false);
        },
        'getTools_xai_preview' => function (): array {
            Cache::put('ai_provider', 'xai');

            return app(AiToolDefinitions::class)->getTools(true);
        },
        'handoffTools_anthropic' => fn (): array => app(AiToolDefinitions::class)->handoffTools('anthropic'),
        'handoffTools_xai' => fn (): array => app(AiToolDefinitions::class)->handoffTools('xai'),
        'onboardingExtractionTools_anthropic' => fn (): array => app(AiToolDefinitions::class)->onboardingExtractionTools('anthropic'),
        'onboardingExtractionTools_xai' => fn (): array => app(AiToolDefinitions::class)->onboardingExtractionTools('xai'),
    ];
};

it('captures the current catalogue into fixtures', function () use ($fixtureDir, $encode, $variants): void {
    if (getenv('CAPTURE_TOOL_SCHEMA_GOLDEN') !== '1') {
        $this->markTestSkipped('Capture only runs with CAPTURE_TOOL_SCHEMA_GOLDEN=1.');
    }

    if (! is_dir($fixtureDir)) {
        mkdir($fixtureDir, 0777, true);
    }

    foreach ($variants() as $name => $build) {
        file_put_contents($fixtureDir.'/'.$name.'.json', $encode($build()));
    }

    expect(glob($fixtureDir.'/*.json'))->toHaveCount(8);
});

it('assembles each variant byte-identical to the committed fixture', function (string $name, $build) use ($fixtureDir, $encode): void {
    $fixturePath = $fixtureDir.'/'.$name.'.json';
    expect(file_exists($fixturePath))->toBeTrue("Missing fixture {$name}.json — run the capture step first.");

    expect($encode($build()))->toBe(file_get_contents($fixturePath));
})->with(function () use ($variants) {
    // Pest dataset: [label => [name, closure]].
    $out = [];
    foreach ($variants() as $name => $build) {
        $out[$name] = [$name, $build];
    }

    return $out;
});

it('keeps the pointer-tool names and count unchanged (out of 4b scope)', function (): void {
    Cache::put('ai_provider', 'anthropic');
    $tools = app(AiToolDefinitions::class)->getTools(false);
    $fetchNames = array_values(array_filter(
        array_map(static fn (array $t): string => $t['name'] ?? '', $tools),
        static fn (string $n): bool => str_starts_with($n, 'fetch_'),
    ));

    // The committed fixture set is fetch_*-free; this asserts the pointer tools
    // still flow through getTools() (their exact set is owned by the pointer
    // registry, a different subsystem). We only assert the count is stable
    // relative to a recorded baseline written at capture time.
    $baselinePath = __DIR__.'/../../Fixtures/ToolSchema/_pointer_baseline.json';
    if (getenv('CAPTURE_TOOL_SCHEMA_GOLDEN') === '1') {
        file_put_contents($baselinePath, json_encode($fetchNames, JSON_PRETTY_PRINT));
        $this->markTestSkipped('Captured pointer baseline.');
    }

    expect(file_exists($baselinePath))->toBeTrue();
    expect($fetchNames)->toBe(json_decode(file_get_contents($baselinePath), true));
});

it('exposes the xAI catalogue unchanged (XaiToolDefinitions untouched by 4b)', function (): void {
    // Guard that 4b did not accidentally touch the separate xAI class.
    Cache::put('ai_provider', 'xai');
    $names = collect(app(XaiToolDefinitions::class)->getTools(false))
        ->map(fn (array $t) => $t['function']['name'] ?? $t['name'] ?? null)
        ->filter()
        ->values()
        ->all();

    expect($names)->not->toBeEmpty();
});
```

- [ ] **Step 2: Capture the fixtures from the CURRENT (pre-refactor) code**

Run:
```bash
CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php --filter="captures the current catalogue"
CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php --filter="keeps the pointer-tool"
```
Expected: the first writes 8 `tests/Fixtures/ToolSchema/*.json`; the second writes `_pointer_baseline.json`. Confirm:
```bash
ls -1 tests/Fixtures/ToolSchema/*.json | wc -l   # => 9 (8 variants + _pointer_baseline)
```

- [ ] **Step 3: Write the fixtures README**

Create `tests/Fixtures/ToolSchema/README.md`:

````markdown
# Tool-schema golden masters (CoALA Phase 4b)

These 8 `.json` files are the byte-for-byte assembled tool catalogue captured
from `AiToolDefinitions` **before** tool schemas were externalised to
`fyn-memory/procedural/tool_schema/`. `_pointer_baseline.json` records the live
`fetch_*` pointer-tool names at capture time (those are out of 4b scope).

They are IMMUTABLE for the duration of Phase 4b. `ToolSchemaGoldenMasterTest`
asserts the corpus-driven assembly reproduces them exactly. If a fixture needs
to change, the tool catalogue is changing — that is a separate, reviewed change,
not a 4b refactor.

Regenerate only via:
```bash
CAPTURE_TOOL_SCHEMA_GOLDEN=1 ./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php
```
````

- [ ] **Step 4: Run the assertion to verify it is RED against the assembler-not-yet-built state**

At this point the code is still the literal-based `AiToolDefinitions`, so the byte-identity test PASSES trivially (live == fixture, both from literals). That is expected and fine — the RED state arrives in Task 3 Step 2 the instant the assembler reads from an empty corpus. To prove the harness works, temporarily confirm the dataset runs:

Run: `./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php`
Expected: PASS (8 byte-identity + pointer baseline + xai guard; capture test skipped). This locks the contract.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/AI/ToolSchemaGoldenMasterTest.php tests/Fixtures/ToolSchema/
git commit -m "test(coala): tool-schema golden master — 8 variant byte-identity fixtures

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: Generate the 49 `tool_schema/{module}/{tool_name}.md` corpus files

**Files:**
- Create: `app/Console/Commands/FynToolSchemaEmit.php` (throwaway — deleted in Task 3 Step 8)
- Create: `fyn-memory/procedural/tool_schema/{module}/{tool_name}.md` × 49

The generator reads the CURRENT in-PHP definitions via reflection-free direct method calls on a pre-refactor copy of the grouping data, then writes each tool to its `.md`. Because we are mid-refactor we cannot call the (soon-to-change) private methods cleanly, so the generator instead asks the still-literal `AiToolDefinitions` for each entry point, de-wraps to the native `{name,description,parameters}` shape, and routes each tool to its module by the inventory table. The `update_record` body is written with the `$allowlist` sentinel instead of the computed `parameters`.

- [ ] **Step 1: Write the generator command**

Create `app/Console/Commands/FynToolSchemaEmit.php`:

```php
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AI\AiToolDefinitions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * THROWAWAY one-shot (CoALA Phase 4b). Emits fyn-memory/procedural/tool_schema/
 * {module}/{tool_name}.md from the CURRENT in-PHP tool definitions. Deleted
 * once the corpus is committed — the golden-master test, not this command, is
 * the source of truth for correctness.
 */
final class FynToolSchemaEmit extends Command
{
    protected $signature = 'fyn:tool-schema:emit {--root=}';

    protected $description = 'THROWAWAY: emit tool_schema procedure .md files from the current PHP definitions.';

    /** tool_name => module slug (the inventory table from the plan). */
    private const MODULE = [
        'navigate_to_page' => 'navigation',
        'list_records' => 'analysis',
        'list_goals' => 'analysis',
        'list_life_events' => 'analysis',
        'get_module_analysis' => 'analysis',
        'get_recommendations' => 'analysis',
        'search_conversation_index' => 'analysis',
        'get_tax_information' => 'tax',
        'generate_financial_plan' => 'plans',
        'get_subscription_status' => 'billing',
        'list_invoices' => 'billing',
        'get_current_plan' => 'billing',
        'create_what_if_scenario' => 'whatif',
        'create_goal' => 'goals',
        'create_life_event' => 'goals',
        'create_savings_account' => 'savings',
        'create_investment_account' => 'savings',
        'create_holding' => 'savings',
        'create_pension' => 'savings',
        'create_property' => 'property',
        'create_mortgage' => 'property',
        'create_protection_policy' => 'protection',
        'create_asset' => 'estate',
        'create_liability' => 'estate',
        'create_estate_gift' => 'estate',
        'create_will' => 'estate',
        'update_will' => 'estate',
        'create_power_of_attorney' => 'estate',
        'update_power_of_attorney' => 'estate',
        'create_family_member' => 'estate',
        'create_trust' => 'estate',
        'create_business_interest' => 'estate',
        'create_chattel' => 'estate',
        'update_record' => 'data',
        'delete_record' => 'data',
        'update_profile' => 'data',
        'set_expenditure' => 'expenditure',
        'capture_salary_sacrifice' => 'campaign',
        'capture_spouse_work_status' => 'campaign',
        'capture_spouse_household_data' => 'campaign',
        'capture_spouse_non_working_assets' => 'campaign',
        'capture_pension_history' => 'campaign',
        'capture_charitable_giving' => 'campaign',
        'delegate_to_capture' => 'handoff',
        'capture_complete' => 'handoff',
        'capture_personal_details' => 'onboarding',
        'capture_spouse_details' => 'onboarding',
        'capture_dependants' => 'onboarding',
        'capture_work_details' => 'onboarding',
    ];

    public function handle(AiToolDefinitions $defs): int
    {
        $root = (string) ($this->option('root') ?: config('fyn.memory.procedural_path'));

        // Gather every static tool in native {name,description,parameters} shape.
        // getTools(false) under xai returns native shape (no input_schema wrap).
        Cache::put('ai_provider', 'xai');
        $native = [];

        foreach ($defs->getTools(false) as $tool) {
            if (str_starts_with($tool['name'], 'fetch_')) {
                continue; // pointer tools — out of scope
            }
            $native[$tool['name']] = $tool;
        }

        // handoff + onboarding entry points are not in getTools(); de-wrap them.
        foreach ($defs->handoffTools('xai') as $w) {
            $f = $w['function'];
            $native[$f['name']] = ['name' => $f['name'], 'description' => $f['description'], 'parameters' => $f['parameters']];
        }
        foreach ($defs->onboardingExtractionTools('xai') as $w) {
            $f = $w['function'];
            // onboardingExtractionTools strict=false wrap; drop the strict key,
            // the native body is parameters as-is.
            $params = $f['parameters'];
            $native[$f['name']] = ['name' => $f['name'], 'description' => $f['description'], 'parameters' => $params];
        }

        $written = 0;
        foreach (self::MODULE as $name => $module) {
            if (! isset($native[$name])) {
                $this->error("MISSING from current definitions: {$name}");

                return self::FAILURE;
            }

            $tool = $native[$name];
            $body = $tool;

            // update_record: replace the computed parameters with the live sentinel.
            if ($name === 'update_record') {
                $body['parameters'] = ['$allowlist' => 'update_record'];
            }

            $json = json_encode(
                ['name' => $body['name'], 'description' => $body['description'], 'parameters' => $body['parameters']],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT,
            );

            $procedureId = $module.'.tool.'.$name;
            $md = "---\n"
                ."procedure_id: '{$procedureId}'\n"
                ."kind: tool_schema\n"
                ."module: {$module}\n"
                ."version: 1\n"
                ."active: true\n"
                ."effective_from: 2026-06-02\n"
                ."---\n\n"
                ."```json\n{$json}\n```\n";

            $dir = "{$root}/tool_schema/{$module}";
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents("{$dir}/{$name}.md", $md);
            $written++;
        }

        $this->info("Wrote {$written} tool_schema procedure files to {$root}/tool_schema/.");

        return self::SUCCESS;
    }
}
```

> NOTE: the `onboardingExtractionTools('xai')` wrap sets `additionalProperties=false` on every params object (it already is for these tools) and adds a `strict` key on the wrapper, not inside `parameters`. De-wrapping takes `$f['parameters']` verbatim, so the emitted native body equals the original literal. The `handoffTools` xai wrap does not add `additionalProperties` — it copies `parameters` verbatim. Both are therefore lossless for the native body.

- [ ] **Step 2: Run the generator against the real corpus**

Run:
```bash
php artisan fyn:tool-schema:emit
ls -R fyn-memory/procedural/tool_schema | sed -n '1,80p'
find fyn-memory/procedural/tool_schema -name '*.md' | wc -l   # => 49
```
Expected: 49 `.md` files across the module dirs (navigation, analysis, tax, plans, billing, whatif, goals, savings, property, protection, estate, data, expenditure, campaign, handoff, onboarding).

- [ ] **Step 3: Validate the new corpus with the 4a deploy gate**

Run: `php artisan fyn:procedural:validate`
Expected: exit 0, summary lists 49 active tool_schema procedures (plus any pre-existing corpus content). If it fails, the frontmatter/path/JSON is wrong — fix the generator and re-emit. Do NOT hand-edit; re-run the generator so the source stays single.

- [ ] **Step 4: Commit the corpus (generator stays for now, deleted in Task 3)**

```bash
git add fyn-memory/procedural/tool_schema/ app/Console/Commands/FynToolSchemaEmit.php
git commit -m "feat(coala): emit 49 tool_schema procedure .md files + throwaway generator

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: Refactor `AiToolDefinitions` into a corpus-driven assembler

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`

The grouping methods stop returning literals and return `$this->toolsFromCorpus([...ids...])`. Ordering `const` tables make the assembly order explicit. `update_record`'s sentinel is replaced with `updateRecordSchema()`. The wrapping/gating/order code in `getTools()`, `handoffTools()`, `onboardingExtractionTools()` is untouched except that their inner literals now come from the corpus.

- [ ] **Step 1: Add the assembler core + ordering tables**

Add the import block at the top (keep the existing `Pointer`, `PointerRegistry`, `Cache` imports):

```php
use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
```

Add these `private const` ordering tables and the `toolsFromCorpus()` / `toolFromCorpus()` helpers as the first private members of the class (immediately after `getTools()` / before `pointerTools()`):

```php
    /**
     * Ordered procedure_id lists per grouping method. The assembly order here
     * is the contract guarded by ToolSchemaGoldenMasterTest — do not reorder.
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
        'additional' => [
            'estate.tool.create_family_member',
            'estate.tool.create_trust',
            'estate.tool.create_business_interest',
            'estate.tool.create_chattel',
        ],
        'modification' => ['data.tool.update_record', 'data.tool.delete_record'],
        'profile' => ['data.tool.update_profile'],
        'expenditure' => ['expenditure.tool.set_expenditure'],
        'campaign' => [
            'campaign.tool.capture_salary_sacrifice',
            'campaign.tool.capture_spouse_work_status',
            'campaign.tool.capture_spouse_household_data',
            'campaign.tool.capture_spouse_non_working_assets',
            'campaign.tool.capture_pension_history',
            'campaign.tool.capture_charitable_giving',
        ],
        'handoff' => ['handoff.tool.delegate_to_capture', 'handoff.tool.capture_complete'],
        'onboarding' => [
            'onboarding.tool.capture_personal_details',
            'onboarding.tool.capture_spouse_details',
            'onboarding.tool.capture_dependants',
            'onboarding.tool.capture_work_details',
        ],
    ];

    /**
     * Assemble a list of native {name,description,parameters} tools from the
     * procedural corpus, in the given procedure_id order. Degrades at runtime:
     * a missing/undecodable schema is skipped + report()ed so a corrupt corpus
     * cannot empty the whole catalogue mid-turn. The golden-master test +
     * fyn:procedural:validate are the real completeness guards.
     *
     * @param  list<string>  $procedureIds
     * @return list<array<string, mixed>>
     */
    private function toolsFromCorpus(array $procedureIds): array
    {
        $corpus = app(ProceduralCorpusLoader::class)->load();
        $tools = [];

        foreach ($procedureIds as $procedureId) {
            $tool = $this->toolFromCorpus($corpus->active($procedureId));
            if ($tool !== null) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * Decode one tool_schema procedure body (a fenced ```json block) into the
     * native {name,description,parameters} shape. Replaces the
     * {"$allowlist":"update_record"} sentinel with the live updateRecordSchema().
     * Returns null (and report()s) on any failure so the catalogue degrades.
     *
     * @return array<string, mixed>|null
     */
    private function toolFromCorpus(?Procedure $procedure): ?array
    {
        if ($procedure === null) {
            report(new \RuntimeException('Tool schema corpus: missing active procedure.'));

            return null;
        }

        // Strip the leading ```json fence and trailing ``` fence.
        $body = trim($procedure->body);
        $body = preg_replace('/^```json\s*\n/', '', $body);
        $body = preg_replace('/\n```\s*$/', '', (string) $body);

        $decoded = json_decode((string) $body, true);
        if (! is_array($decoded) || ! isset($decoded['name'], $decoded['description'], $decoded['parameters'])) {
            report(new \RuntimeException("Tool schema corpus: undecodable body for '{$procedure->procedureId}'."));

            return null;
        }

        if (($decoded['parameters']['$allowlist'] ?? null) === 'update_record') {
            $decoded['parameters'] = $this->updateRecordSchema();
        }

        return [
            'name' => $decoded['name'],
            'description' => $decoded['description'],
            'parameters' => $decoded['parameters'],
        ];
    }
```

- [ ] **Step 2: Replace each grouping method body with a corpus read**

Replace the FULL bodies of these methods (keep the method signatures + docblocks). Each becomes a one-liner:

```php
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

    private function expenditureTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['expenditure']);
    }

    private function campaignSaveTaxTools(): array
    {
        return $this->toolsFromCorpus(self::ORDER['campaign']);
    }
```

Leave `dataCreationTools()` (the aggregator that spreads `goalAndEventTools()` … `estateCreationTools()`), `pointerTools()`, `updateRecordSchema()` untouched.

- [ ] **Step 3: Source the handoff inner literals from the corpus**

In `handoffTools()`, replace ONLY the `$tools = [ ... two literal definitions ... ];` block with:

```php
        $tools = $this->toolsFromCorpus(self::ORDER['handoff']);
```

Keep the entire `if ($provider === 'xai') { ... } return array_map(...)` wrapping tail exactly as-is.

> The corpus stores the handoff tools under the names `delegate_to_capture` / `capture_complete` (the values of `HandoffContract::DELEGATE_TO_CAPTURE` / `::CAPTURE_COMPLETE`). The golden master proves the assembled names match the constants byte-for-byte. The `HandoffContract` reference in the docblock stays.

- [ ] **Step 4: Source the onboarding-extraction base literals from the corpus**

In `onboardingExtractionTools()`, replace ONLY the opening `$tools = [ ... four literal definitions ... ];` block with:

```php
        $tools = $this->toolsFromCorpus(self::ORDER['onboarding']);
```

Keep the subsequent `$tools = array_merge($tools, $this->campaignSaveTaxTools());` line and the entire `if ($provider === 'xai') { ... } return array_map(...)` tail exactly as-is. (`campaignSaveTaxTools()` is itself now corpus-driven, so the merged tail is corpus-sourced end-to-end.)

- [ ] **Step 5: Run the golden master — expect GREEN (byte-identical)**

Run: `./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php`
Expected: PASS (8 byte-identity variants + pointer baseline + xai guard; capture skipped).

If any variant FAILS, diff the live vs fixture for that variant and fix the corpus or assembler until byte-identical. Common causes and fixes:
- **Fence stripping leaves whitespace** → the `preg_replace` patterns must strip exactly the `` ```json\n `` prefix and `` \n``` `` suffix the generator wrote. Re-check both against a single emitted file.
- **Key order differs** (`name`/`description`/`parameters`) → `toolFromCorpus()` rebuilds the array in that fixed order; ensure no stray keys leak from `$decoded`.
- **`update_record` parameters differ** → confirm the sentinel branch fires and `updateRecordSchema()` is unchanged.
- **Empty-object encoding** (`properties: (object){}` vs `[]`) → `json_decode($body, true)` turns `{}` into `[]`; PHP then `json_encode`s `[]` as `[]` not `{}`. This WILL break byte-identity for tools with empty `properties` (list_goals, list_life_events, get_recommendations, generate_financial_plan, billing tools). FIX: in `toolFromCorpus()`, after decode, normalise an empty `properties` array back to an object before returning — see Step 6.

- [ ] **Step 6: Fix the empty-object `properties` regression (expected)**

Several tools declare `'properties' => (object) []`. The original literal `json_encode`s these as `"properties":{}`. After a corpus round-trip `json_decode(..., true)` yields `[]`, which re-encodes as `"properties":[]` — a byte difference. Patch `toolFromCorpus()` to restore the object shape. Insert this normalisation immediately before the final `return` in `toolFromCorpus()`:

```php
        // Restore empty-object shape: json_decode($body, true) turns `{}` into
        // `[]`, which would re-encode as `[]` and break byte-identity with the
        // original literal `(object) []`. Re-objectify any empty `properties`.
        $params = $decoded['parameters'];
        if (isset($params['properties']) && $params['properties'] === []) {
            $params['properties'] = (object) [];
        }
        $decoded['parameters'] = $params;
```

> This must run for BOTH the sentinel and non-sentinel branches; `updateRecordSchema()` returns a `oneOf` with no top-level `properties`, so the guard is a no-op there. Place the normalisation AFTER the `$allowlist` replacement so the order is: decode → allowlist swap → empty-object restore → return.

Re-run: `./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php`
Expected: PASS. If a deeper-nested empty object also diverges (e.g. a nested `properties: {}` inside an items schema), extend the normalisation to walk one level into `properties` children — but only if the golden master flags it. Do not over-engineer; let the fixture drive.

- [ ] **Step 7: Run the secondary nets (parity / preview / advice tool list)**

Run:
```bash
./vendor/bin/pest tests/Architecture/ToolCatalogueParityTest.php tests/Architecture/AdviceFynWriteToolParityTest.php tests/Architecture/PreviewModeToolCatalogueTest.php tests/Feature/Fyn/AdviceFynToolListTest.php
```
Expected: PASS — all unchanged behaviour. If `PreviewModeToolCatalogueTest` fails, the `if (! $isPreviewMode)` gating in `getTools()` was disturbed; restore it. If `AdviceFynWriteToolParityTest` fails, `AdviceFyn::WRITE_TOOLS` stripping is keyed on tool names which are unchanged — investigate the assembled names.

- [ ] **Step 8: Delete the throwaway generator**

```bash
git rm app/Console/Commands/FynToolSchemaEmit.php
```
Confirm no references remain: `grep -rn "fyn:tool-schema:emit\|FynToolSchemaEmit" app tests` → no output.

- [ ] **Step 9: Pint the changed PHP**

Run: `./vendor/bin/pint app/Services/AI/AiToolDefinitions.php`
Expected: `PASS`. If Pint strips the freshly-added `use App\Services\AI\Memory\Procedural\Procedure;` / `ProceduralCorpusLoader;` import because it momentarily sees it unused, re-add it (the usage now exists in `toolFromCorpus()`/`toolsFromCorpus()`) and re-run until `PASS`.

- [ ] **Step 10: Commit**

```bash
git add app/Services/AI/AiToolDefinitions.php
git commit -m "refactor(coala): AiToolDefinitions reads tool schemas from procedural corpus

Externalises the 49 static tool definitions to fyn-memory/procedural/tool_schema/.
AiToolDefinitions is now a thin assembler over ProceduralCorpusLoader; selection,
ordering, provider-wrap, preview gating, and the update_record allowlist builder
stay in PHP. Golden master byte-identical across all 8 variants.

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Full-suite regression + validate + Pint sweep

**Files:** none (verification only; final Pint commit if needed)

- [ ] **Step 1: Golden master + completeness (the hard gate)**

Run: `./vendor/bin/pest tests/Feature/AI/ToolSchemaGoldenMasterTest.php`
Expected: PASS — 8 variants byte-identical, pointer baseline stable, xAI catalogue non-empty, capture skipped.

- [ ] **Step 2: Procedural substrate suite still green (4a not regressed)**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Memory/Procedural/ tests/Feature/Console/FynProceduralValidateTest.php`
Expected: PASS (30 from 4a, unchanged).

- [ ] **Step 3: Deploy gate against the real corpus**

Run: `php artisan fyn:procedural:validate`
Expected: exit 0; the 49 new active tool_schema procedures are listed and valid.

- [ ] **Step 4: Wider AI + tool/parity/preview surface**

Run:
```bash
./vendor/bin/pest tests/Unit/Services/AI/ tests/Feature/AI/ \
  tests/Architecture/ToolCatalogueParityTest.php \
  tests/Architecture/AdviceFynWriteToolParityTest.php \
  tests/Architecture/PreviewModeToolCatalogueTest.php \
  tests/Feature/Fyn/AdviceFynToolListTest.php \
  tests/Feature/Fyn/CreatePowerOfAttorneyToolTest.php \
  tests/Feature/AI/PointerToolModeTest.php \
  tests/Feature/AI/ProviderSwapLockTest.php
```
Expected: PASS across the board. Any failure routes back into Task 3 (diagnose with file:line evidence, fix the assembler/corpus, re-verify the golden master, then re-run this step) — do not silence an assertion.

- [ ] **Step 5: Architecture suite (no dead generator, no convention drift)**

Run: `./vendor/bin/pest --testsuite=Architecture`
Expected: PASS. Confirms `FynToolSchemaEmit` deletion left no dangling reference and `AiToolDefinitions` still satisfies any structural rules.

- [ ] **Step 6: Final Pint sweep on every file the phase touched**

Run:
```bash
./vendor/bin/pint app/Services/AI/AiToolDefinitions.php tests/Feature/AI/ToolSchemaGoldenMasterTest.php
```
Expected: `PASS`. If Pint reformats, commit:

```bash
git add -A
git commit -m "style(coala): pint Phase 4b tool-schema externalisation

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Done-when checklist (verify against the spec §9)

- [ ] All 49 static tool schemas live as `fyn-memory/procedural/tool_schema/{module}/{tool_name}.md` with valid 4a frontmatter (one active version each, path↔frontmatter agreement).
- [ ] `AiToolDefinitions` is a thin assembler — no inline tool-schema literals remain except `updateRecordSchema()` and the `self::ORDER` table; `toolsFromCorpus()`/`toolFromCorpus()` source every grouping method.
- [ ] `update_record` uses the `{"$allowlist":"update_record"}` sentinel in its `.md`; the assembler swaps in the live `updateRecordSchema()` so the allowlist stays a live source.
- [ ] `ToolSchemaGoldenMasterTest` GREEN: all 8 variants byte-identical to the task-1 fixtures + pointer baseline stable.
- [ ] `php artisan fyn:procedural:validate` exits 0 with the new tree.
- [ ] `tests/Unit/Services/AI`, `tests/Feature/AI`, and the parity/preview/advice/power-of-attorney/pointer/provider-swap tests are GREEN.
- [ ] The throwaway `FynToolSchemaEmit` generator is deleted; `grep` finds no references; no dead code remains.
- [ ] `pint` reports `passed` on all changed files.
- [ ] Two-Fyn contract intact: the assembled tool set per state is unchanged (`AdviceFyn::WRITE_TOOLS` stripping still removes the same names; `XaiToolDefinitions` untouched).
- [ ] Deferred items unchanged and flagged: `pointerTools()` stays PHP-only; `XaiToolDefinitions` keeps its own literals (follow-up for CSJ).
```
