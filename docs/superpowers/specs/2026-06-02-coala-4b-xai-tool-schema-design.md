# CoALA Phase 4b-xai — xAI Tool Schema Externalisation (Design Spec)

**Branch:** `feat/coala-4b-xai-tool-schema` (off `feat/coala-4f-admin-viewer`)
**Date:** 2026-06-02
**Status:** Approved for implementation (CSJ-delegated, self-approval granted for this run)

---

## 1. Context & problem

Phase 4b externalised the **Anthropic** tool catalogue. `AiToolDefinitions` is now a thin
assembler (`toolsFromCorpus()` / `toolFromCorpus()`) that reads provider-neutral Anthropic
schemas from `fyn-memory/procedural/tool_schema/{module}/*.md` (49 files), decodes each fenced
```json``` body, and applies Anthropic vs xAI output wrapping based on `Cache::get('ai_provider')`.

**But the live xAI request path does NOT use `AiToolDefinitions`.** `HasAiChat.php:212` resolves
`app(XaiToolDefinitions::class)` when the provider is xAI, and `XaiToolDefinitions` (1192 lines,
`app/Services/AI/XaiToolDefinitions.php`) is **still fully hardcoded**. It is what actually serves
chat turns when `AI_PROVIDER=xai` (the live provider for this phase).

The xAI schemas are NOT byte-compatible with the Anthropic `.md` bodies. They differ structurally:

- **OpenAI function-calling wrapper:** every tool is `{type: function, function: {name, description, parameters, strict}}`.
- **Strict mode (`strict: true`):** all fields land in `required`; optionals are expressed as nullable
  types (`['number','null']`) rather than being omitted.
- **Nullable enums via `anyOf`:** strict mode forbids `type: [string,null]` + `enum`; instead
  `{anyOf: [{type: string, enum: [...]}, {type: null}], description: ...}` (see `nullableEnum()`).
- **Enriched property schemas:** e.g. property tools cover every form field; expenditure covers
  every spend category as nullable numbers.
- **Bespoke gathering instructions** baked into `description` strings ("Call this IMMEDIATELY
  when…", "Fill in every category…").
- **`strict: false` exceptions:** `create_what_if_scenario` (dynamic-key `parameters` object,
  `additionalProperties: true`) omits the `strict` flag entirely.

Therefore the xAI tools **cannot reuse the existing Anthropic `.md` bodies**.

### CSJ decision (the approach implemented here)

**Per-provider `tool_schema` `.md`, keyed by a `provider` frontmatter field.** Same logical
`procedure_id`, with a new **provider axis**. The 49 existing Anthropic files stay byte-unchanged
(they get an implicit default `provider: anthropic`); a parallel set of xAI files
(`provider: xai`) carries the strict OpenAI schemas. `XaiToolDefinitions` becomes a thin assembler
mirroring `AiToolDefinitions::toolsFromCorpus()`.

---

## 2. Live-file inventory (verified in this run)

### `XaiToolDefinitions` public surface (the byte-identity contract)
Only **two** public entry points exist on the class:
1. `getTools(bool $isPreviewMode = false): array` — pre-wrapped OpenAI function objects.
2. `handoffTools(): array` — **no provider arg** (xAI-only; returns wrapped function objects).
   NB: the *live* handoff dispatch in `AdviceFyn.php:445` actually calls
   `AiToolDefinitions::handoffTools('xai')`, not this method. `XaiToolDefinitions::handoffTools()`
   has no live caller today, but it is a public method on the class and so is in scope for the
   golden master (completeness — guard against silent drift).

There is **no** `onboardingExtractionTools()` on the xAI class (that lives only on
`AiToolDefinitions`, provider-branched). Out of scope here.

### `getTools()` assembly order (xAI — the contract to preserve byte-for-byte)
```
getTools(false):
  navigationTools, analysisTools, taxTools, planGenerationTools, billingTools,
  [if !preview] whatIfTools, dataCreationTools, additionalCreationTools,
                dataModificationTools, profileTools, campaignSaveTaxTools,
  pointerTools()        // fetch_* — OUT OF SCOPE, filtered from golden master
```
`dataCreationTools()` (xAI) = goalAndEvent, accountCreation, propertyCreation, protectionCreation,
estateCreation, **expenditureTools** (so `set_expenditure` sits at the *end* of dataCreation, after
estate). This DIFFERS from the Anthropic ordering, where `expenditure` is a separate top-level group
placed after `profile`. **The xAI `ORDER` map must encode the xAI ordering, not copy Anthropic's.**

Verified live xAI static (non-`fetch_`) tool list, `getTools(false)` — **43 tools** in this order:
```
navigate_to_page, list_records, list_goals, list_life_events, get_module_analysis,
search_conversation_index, get_recommendations, get_tax_information, generate_financial_plan,
get_subscription_status, list_invoices, get_current_plan, create_what_if_scenario, create_goal,
create_life_event, create_savings_account, create_investment_account, create_holding,
create_pension, create_property, create_mortgage, create_protection_policy, create_asset,
create_liability, create_estate_gift, create_will, update_will, create_power_of_attorney,
update_power_of_attorney, set_expenditure, create_family_member, create_trust,
create_business_interest, create_chattel, update_record, delete_record, update_profile,
capture_salary_sacrifice, capture_spouse_work_status, capture_spouse_household_data,
capture_spouse_non_working_assets, capture_pension_history, capture_charitable_giving
```
`getTools(true)` (preview) — **12 tools**: the first 12 above (nav, analysis×6, tax, plans, billing×3).
`handoffTools()` — `delegate_to_capture`, `capture_complete`.

> NB: the Anthropic catalogue has `analysis.tool.list_records` etc. — names match across providers
> (tool-name parity is independently guarded by `ToolCatalogueParityTest`). What differs is the
> *shape* of each tool, never the *name set* (modulo `expenditure` which xAI nests but still emits).

### `wrapTool()` shape details that MUST be reproduced byte-for-byte
- `parameters.properties` empty → `(object) []` (encodes as `{}`, not `[]`).
- `parameters.required` always present (possibly `[]`).
- `parameters.additionalProperties` always `false`.
- `strict: true` appended **only** when `$strict` true; omitted entirely when false
  (`create_what_if_scenario`).
- `nullableEnum()` → `{anyOf: [{type:string, enum:[…]}, {type:null}], description: …}`.

### 4a procedural substrate (to extend)
- `Procedure` (`app/Services/AI/Memory/Procedural/Procedure.php`): readonly VO; ctor params
  `procedureId, kind, module, version, active, effectiveFrom, effectiveTo, body`.
- `ProceduralCorpus`: `all()`, `ofKind()`, `versions()`, `active(string $procedureId, ?Carbon $asOf=null)`.
- `ProceduralCorpusLoader`: `load()` (degrade), `loadStrict()` (deploy gate), `parse()`,
  `parseAndValidate()`. Active-uniqueness invariant is enforced in `parse()` via
  `$activeById[$proc->procedureId]`.
- `ProceduralVersionHolder` (Episodic): `add($procedureId, $version)` request-scoped accumulator
  consumed by 4e stamping.

---

## 3. Design — substrate changes (provider axis)

> Goal: ADD a provider axis without changing any existing 4a/4b behaviour for `provider=anthropic`.
> Every existing call site must remain byte-identical.

### 3.1 `Procedure` — add `provider`
Add a `public readonly string $provider` to the constructor, **appended as the last parameter** so
all existing positional constructions in tests (`ProcedureTest`, `ProceduralCorpusTest::proc()`)
keep working — those use **named** args for the documented fields and do not pass `provider`, so the
new param **must have a default** of `'anthropic'`.

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
Rationale for default `'anthropic'`: the 49 existing files omit `provider`, and
`ProceduralCorpusTest::proc()` / `ProcedureTest` build `Procedure` without it. A default keeps all
of them valid and unedited.

### 3.2 `ProceduralCorpusLoader` — parse + validate `provider`
In `parseAndValidate()`:
- Parse optional `provider` frontmatter: `$provider = isset($meta['provider']) ? (string)$meta['provider'] : 'anthropic';`
  (default `'anthropic'` when absent — keeps the 49 files valid).
- Validate: when present, must be one of `['anthropic','xai']`; else throw
  `"unknown provider '{$provider}'"`.
- Pass `provider: $provider` into the `Procedure` ctor.

In `parse()` change the **active-uniqueness key** from `procedure_id` to `(procedure_id, provider)`:
- `$activeById[$proc->procedureId.'|'.$proc->provider]` instead of `$activeById[$proc->procedureId]`.
- The duplicate-version key stays `procedure_id@version` BUT must also incorporate provider so the
  same `procedure_id@version` under different providers does not false-positive as a duplicate file.
  Change to `procedure_id@version|provider` → `$proc->procedureId.'@'.$proc->version.'|'.$proc->provider`.
  (Same procedure_id + same version + same provider in two files = still a hard duplicate error.)

Effect: **two actives with the same `procedure_id` but different `provider` are now ALLOWED**; same
`procedure_id` + same `provider` + >1 active is **still rejected**.

The `KINDS`/`SKIP`/signature/caching machinery is unchanged. `.xai.md` files live under the same
`tool_schema/{module}/` dirs and are picked up by `File::allFiles()` (extension is `md` because the
filename ends `.xai.md` → `getExtension()` returns `md`). **Provider is derived from frontmatter,
NOT the filename** — the `.xai.md` suffix is purely to avoid a filesystem collision with the
existing anthropic `{tool}.md`.

> Path-vs-frontmatter guards (`kind`, `module`) are unaffected: `getRelativePath()` still yields
> `{module}`, and the frontmatter `kind`/`module` still match the path. `getFilename()` is only used
> for the SKIP check (`README.md`, `_TEMPLATE.md`), which `.xai.md` files do not collide with.

### 3.3 `ProceduralCorpus::active()` — add provider selector
New signature (provider inserted as the **second** positional param, `asOf` shifts to third):
```php
public function active(string $procedureId, string $provider = 'anthropic', ?Carbon $asOf = null): ?Procedure
```
Filter additionally on `$p->provider === $provider`.

**Caller-compatibility audit (must stay byte-identical):**
- `AiToolDefinitions::toolsFromCorpus()` calls `$corpus->active($procedureId)` — single positional
  arg → resolves `provider='anthropic'`, `asOf=now`. **Unchanged behaviour.** ✓
- `ProceduralCorpusTest` calls `$corpus->active('a', Carbon::parse(...))` — **positional `asOf`**.
  This is the one breaking call: with the new signature the second positional becomes `provider`,
  so `Carbon` would bind to `string $provider`. **These test calls must be updated** to pass
  `asOf` by name: `$corpus->active('a', asOf: Carbon::parse(...))`. This is a test-only edit and
  preserves intent (the assertions are unchanged).
- No other callers of `active()` exist (grep-verified: `AiToolDefinitions`, the new
  `XaiToolDefinitions`, and the two test files).

`ofKind()` already supports kind filtering; no new provider-filtered list method is strictly
required for the assembler (it resolves by `(procedureId, provider)` one at a time, mirroring 4b).
If a provider-scoped `ofKind` proves convenient it can be added, but it is not in the critical path.

### 3.4 `fyn:procedural:validate`
`FynProceduralValidate` calls `loadStrict()` only; no behaviour change needed. After authoring the
xAI files it validates **49 anthropic + ~43 xai** tool_schema procedures (plus the existing
workflow/overlay/fca_block kinds) and must exit 0. The summary line counts grow; no assertion in
`FynProceduralValidateTest` pins the absolute corpus count, so it stays green. The
"multiple active versions" test uses two same-`procedure_id`+same-(default)provider files → still
correctly rejected under the new `(id,provider)` key.

---

## 4. Design — xAI corpus content (authoring the `.xai.md` files)

One `provider: xai` `tool_schema` `.md` per current xAI tool, at
`fyn-memory/procedural/tool_schema/{module}/{tool}.xai.md`.

Frontmatter:
```yaml
---
procedure_id: '{module}.tool.{tool}'
kind: tool_schema
module: {module}
provider: xai
version: 1
active: true
effective_from: 2026-06-02
---
```
Body = the **inner function object** (`{name, description, parameters, strict}` — provider-neutral
of the OpenAI `{type:function, function:{…}}` wrapper) in a fenced ```json``` block. The assembler
re-applies the `{type:function, function:{…}}` wrap at runtime (mirroring how `toolFromCorpus`
strips/re-applies for Anthropic). The `strict` key is stored **inside** the JSON body exactly as the
hardcoded class emits it (present-and-true for strict tools; **absent** for `create_what_if_scenario`).

`procedure_id` ↔ module mapping mirrors the Anthropic `ORDER` map (same logical IDs), with
`expenditure.tool.set_expenditure` belonging to the `expenditure` module dir. The xAI assembler's
`ORDER` arranges these IDs in the **xAI emission order** (set_expenditure nested at the tail of
dataCreation), which differs from Anthropic.

### 4.1 Throwaway generator
Author the `.xai.md` files with a **throwaway PHP generator** that:
1. Boots the app, sets `Cache::put('ai_provider','xai')`.
2. Calls the *current* `XaiToolDefinitions->getTools(false)` (full live set) **and**
   `handoffTools()`.
3. For each function object, unwraps `['function']`, maps tool `name` → `{module}.tool.{name}`
   (using the known module map), and writes the inner object as pretty JSON into the fenced body of
   `tool_schema/{module}/{name}.xai.md` with the frontmatter above.
4. Preserves key order and the present/absent `strict` flag exactly (encode the decoded inner object
   with the same flags used by the assembler so re-decode round-trips byte-identically).

The generator is **deleted** after authoring — the golden master is the correctness guarantee, not
the generator.

> Authoring note: byte-identity is asserted on the *assembled* output, not the `.md` body text. The
> `.md` JSON only needs to decode to the same structure the assembler re-encodes; the golden-master
> test enforces the final bytes.

---

## 5. Design — `XaiToolDefinitions` thin-assembler rewrite

Mirror `AiToolDefinitions::toolsFromCorpus()` / `toolFromCorpus()`:

- Add a `private const ORDER` mapping group → ordered list of `procedure_id`s, in the **xAI**
  emission order (§3/§2).
- `toolsFromCorpus(array $procedureIds): array`:
  - `$corpus = app(ProceduralCorpusLoader::class)->load();`
  - `$versions = app(ProceduralVersionHolder::class);`
  - for each id: `$procedure = $corpus->active($procedureId, 'xai');` → `toolFromCorpus($procedure)`
    → on success append AND `$versions->add($procedure->procedureId, $procedure->version)`
    (so 4e stamping fires on xAI turns, mirroring `AiToolDefinitions::toolsFromCorpus`).
- `toolFromCorpus(?Procedure $procedure): ?array`:
  - degrade-on-null + `report()`, same as Anthropic.
  - strip the ```json fences, `json_decode(..., true)`.
  - re-objectify empty `properties` `[]` → `(object) []` (byte-identity with `wrapTool`).
  - **No `$allowlist` sentinel** for xAI: xAI's `update_record` schema is the bespoke strict shape
    authored directly into the `.md` body (xAI does not use the Anthropic `oneOf`/allowlist
    `updateRecordSchema()`). Decode and wrap as-is. (Confirm during implementation by inspecting the
    live xAI `update_record` shape; whichever it is, it is captured verbatim into the `.xai.md`.)
  - re-wrap into the OpenAI shape: `['type'=>'function', 'function'=>$inner]` where `$inner` is the
    decoded `{name, description, parameters, [strict]}` with the `strict` key present **iff** it was
    present in the body.
- Group methods (`navigationTools()`, `analysisTools()`, …, `expenditureTools()`,
  `campaignSaveTaxTools()`, `handoffTools()`) each `return $this->toolsFromCorpus(self::ORDER[...])`,
  preserving the existing `getTools()` composition and preview-gating logic **unchanged**.
- `pointerTools()` and `wrapTool()`/`nullableEnum()` helpers: `pointerTools()` stays (it already
  reads the live registry — out of golden-master scope). `wrapTool`/`nullableEnum` become unused
  once bodies are externalised and should be removed **only if** Pint/usage confirms no remaining
  caller; otherwise leave (do not over-clean — note: if removed, watch the Pint "unused use" quirk).

### 5.1 Preview-gating + WRITE_TOOLS + parity invariants preserved
`getTools(false)` vs `getTools(true)` gating, the `!$isPreviewMode` block, and `pointerTools()`
append all stay structurally identical. `ToolCatalogueParityTest` and `PreviewModeToolCatalogueTest`
(name-set parity Anthropic↔xAI) must stay green — names are unchanged.

---

## 6. Golden-master byte-identity gate (FIRST plan task)

New test `tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php` mirroring
`ToolSchemaGoldenMasterTest.php`:

- **Capture step** (guarded by `CAPTURE_XAI_TOOL_SCHEMA_GOLDEN=1`), run **before** the refactor
  against the *current hardcoded* `XaiToolDefinitions`, writing fixtures to
  `tests/Fixtures/XaiToolSchema/`:
  - `getTools_xai_live.json`   ← `getTools(false)`
  - `getTools_xai_preview.json` ← `getTools(true)`
  - `handoffTools_xai.json`    ← `handoffTools()`
  - `_pointer_baseline.json`   ← the `fetch_*` name list (count-stable assertion, like 4b)
- **Encoding:** identical to 4b — `json_encode($static, JSON_UNESCAPED_SLASHES |
  JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)` with `fetch_*` tools filtered out (the dynamic
  pointer tools are out of scope and non-deterministic). The filter must read the **xAI** name path
  `$t['function']['name']` (not top-level `$t['name']`).
- **Assertion step** (always runs): after the refactor, `$encode($build())` must `toBe()` the
  committed fixture **byte-for-byte** — tool order, `strict` flags (present/absent),
  `anyOf` nullable-enum patterns, every description byte, empty-`properties` `{}` shape.
- The fixtures are committed in the FIRST task (red/baseline), captured from the live hardcoded
  class, then the refactor must reproduce them.

**Failure protocol:** if byte-identity cannot be reached, the corpus/assembler is wrong — **loop**
(diagnose the diff, fix the `.md` body or the assembler, re-run) until identical. If genuinely
impossible (e.g. the live class emits something structurally unrepresentable in a static `.md`),
**STOP — status BLOCKED**; never ship a live xAI catalogue change that is not byte-identical.

---

## 7. Regression set (all must be green; pint clean)

- **NEW:** `tests/Feature/AI/XaiToolSchemaGoldenMasterTest.php` — byte-identity (the gate).
- **UNCHANGED-GREEN:** `tests/Feature/AI/ToolSchemaGoldenMasterTest.php` — the 4b Anthropic golden
  master (provider defaults to anthropic; the 49 files are untouched, so all 8 variants stay
  byte-identical). Its "XaiToolDefinitions untouched by 4b" guard test asserts only that the xAI
  name list is non-empty — still true after the rewrite.
- **UPDATED (intent-preserving):** `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusTest.php`
  — the two `active('a', Carbon::parse(...))` positional calls become `active('a', asOf: …)`. The
  active-uniqueness *semantics* test is in the *loader* test, not here — see next.
- **UPDATED (the active-uniqueness rule):**
  `tests/Unit/Services/AI/Memory/Procedural/ProceduralCorpusLoaderTest.php` — the existing
  "rejects more than one active version of the same procedure_id" test keeps its intent (same
  procedure_id + **same provider** + >1 active still throws "multiple active versions"; its two
  files default to `provider:anthropic` so it already exercises that). ADD a positive case: two
  actives with the same `procedure_id` but `provider: anthropic` vs `provider: xai` load without
  error and both resolve via `active($id, 'anthropic')` / `active($id, 'xai')`. ADD a negative case:
  an out-of-range `provider: openai` throws "unknown provider 'openai'". The
  `writeProc()`/`validFrontmatter()` helpers gain optional `provider` support.
- **UNCHANGED-GREEN:** `ProcedureTest.php` (named-arg ctor, `provider` defaults), `FynProceduralValidateTest.php`
  (no absolute-count assertion; "multiple active versions" rejection still fires).
- **UNCHANGED-GREEN:** `tests/Architecture/ToolCatalogueParityTest.php`,
  `tests/Architecture/PreviewModeToolCatalogueTest.php` (name-set parity — names unchanged),
  `tests/Feature/AI/PointerToolModeTest.php`, `tests/Feature/AI/SearchConversationIndexTest.php`
  (`XaiToolDefinitions registers search_conversation_index with strict mode` — the strict shape is
  preserved by byte-identity), `tests/Feature/Fyn/AdviceFynToolListTest.php`,
  `tests/Feature/Stores/PropertyFynCaptureTest.php`.
- **Commands:** `php artisan fyn:procedural:validate` → exit 0 (49 anthropic + ~43 xai tool_schema
  + existing other kinds).
- **Suites to run green:** `tests/Unit/Services/AI`, `tests/Feature/AI`,
  `tests/Unit/Services/AI/Memory/Procedural`, `tests/Feature/Console/FynProceduralValidateTest`.
- `./vendor/bin/pint` clean on every touched file before each commit. Watch the known Pint
  unused-`use`-stripping quirk; prefer import+usage in a single Write.

---

## 8. Done-when

1. Branch `feat/coala-4b-xai-tool-schema` off `feat/coala-4f-admin-viewer`; spec committed.
2. xAI golden-master fixtures captured from the **current hardcoded** `XaiToolDefinitions` and
   committed (FIRST task).
3. Substrate provider axis landed: `Procedure.$provider`, loader parse/validate + `(id,provider)`
   active-uniqueness, `ProceduralCorpus::active($id, $provider, $asOf)`. All 4a tests green (only the
   documented test edits in §7).
4. ~43 `provider: xai` `tool_schema` `.md` files authored (generator used then deleted).
5. `XaiToolDefinitions` is a thin corpus-driven assembler; records `procedure_id@version` into
   `ProceduralVersionHolder`; pointer/preview/parity logic preserved.
6. `XaiToolSchemaGoldenMasterTest` asserts byte-identity — **GREEN**.
7. 4b Anthropic golden master + all regression suites in §7 — **GREEN**.
8. `fyn:procedural:validate` — exit 0.
9. `pint` clean.

## 9. Out of scope

- The dynamic `fetch_*` pointer tools (live `PointerRegistry`) — filtered from the golden master,
  produced by untouched `pointerTools()` (matches 4b's treatment).
- `AiToolDefinitions` / the 49 Anthropic `.md` files — **untouched** (default `provider:anthropic`).
- `onboardingExtractionTools()` (xAI has none; lives on `AiToolDefinitions` only).
- Any change to `HasAiChat` dispatch, the two-Fyn contract, or the provider-selection logic.
- The two pre-existing unrelated working-tree deletions (`public/images/logos/*.png`) — left alone.
- Phases 4c/4d/4e/4f content — this phase only adds the xAI provider axis + xAI tool_schema corpus.
