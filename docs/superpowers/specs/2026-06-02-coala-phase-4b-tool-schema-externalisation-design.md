# CoALA Phase 4b — Tool-Schema Externalisation (Design Spec)

- **Phase:** 4b (master plan `fynla-coala-implementation-plan.md` sec 778)
- **Branch:** `feat/coala-4b-tool-schema`
- **Risk:** HIGH — touches the live tool catalogue. Golden-master byte-identity is the hard gate.
- **Depends on:** 4a procedural substrate (already merged on this branch — `Procedure`, `ProceduralCorpus`, `ProceduralCorpusLoader`, `fyn:procedural:validate`, corpus at `fyn-memory/procedural/{kind}/{module}/*.md`).
- **Date:** 2026-06-02

---

## 1. Goal

Move the **static** tool DEFINITIONS (provider-neutral JSON schemas: `name`, `description`,
input-`parameters`) out of the PHP class constants in
`app/Services/AI/AiToolDefinitions.php` into one git-tracked `.md` file per tool under
`fyn-memory/procedural/tool_schema/{module}/{tool_name}.md`. After this phase a tool's
schema changes via a PR-to-`.md`, not a PHP edit.

The **selection / wrapping / gating LOGIC stays in PHP**. `AiToolDefinitions` becomes a thin
**assembler**: it loads active `tool_schema` procedures via `ProceduralCorpusLoader`, parses each
fenced-JSON body, and re-assembles the **same catalogue in the same order**, then applies the
**same** provider-shape wrapping, preview gating, and grouping. Nothing about which tools are
exposed, in what order, with what schema bytes, in any state, may change.

---

## 2. Scope & Boundary

### In scope (THIS phase only)

- Externalise the **49 unique static tool schemas** currently hard-coded as PHP array literals
  across these `AiToolDefinitions` methods:
  - `navigationTools` (1), `analysisTools` (5), `taxTools` (1), `planGenerationTools` (1),
    `billingTools` (3), `whatIfTools` (1), `goalAndEventTools` (2), `accountCreationTools` (3),
    `propertyCreationTools` (2), `protectionCreationTools` (1), `estateCreationTools` (7),
    `additionalCreationTools` (4), `dataModificationTools` (2), `profileTools` (1),
    `expenditureTools` (1), `campaignSaveTaxTools` (6), `handoffTools` base set (2),
    `onboardingExtractionTools` base set (4).
- Rewrite `AiToolDefinitions` so each grouping method reads its tool(s) from the corpus instead
  of returning literals, while preserving every public method signature and return shape:
  - `getTools(bool $isPreviewMode = false): array`
  - `handoffTools(string $provider = 'anthropic'): array`
  - `onboardingExtractionTools(string $provider = 'anthropic'): array`
- Module-slug mapping for organisation only (golden master does not depend on it):
  | grouping method | module slug |
  |---|---|
  | navigationTools | `navigation` |
  | analysisTools | `analysis` |
  | taxTools | `tax` |
  | planGenerationTools | `plans` |
  | whatIfTools | `whatif` |
  | accountCreationTools | `savings` |
  | propertyCreationTools | `property` |
  | protectionCreationTools | `protection` |
  | estateCreationTools / additionalCreationTools / goalAndEventTools | `estate` / `goals` (per tool — see plan task 2) |
  | dataModificationTools / profileTools / expenditureTools | `data` / `expenditure` |
  | campaignSaveTaxTools | `campaign` |
  | billingTools | `billing` |
  | handoffTools | `handoff` |
  | onboardingExtractionTools base | `onboarding` |
- A throwaway generator (PHP artisan one-shot or bash) MAY be written to emit the `.md` files from
  the current in-PHP definitions, run once, then **deleted** before final commit. The golden-master
  test is what guarantees correctness, not hand-copying.
- `procedure_id` convention per directive: `{module}.tool.{tool_name}` (e.g.
  `analysis.tool.list_records`, `handoff.tool.delegate_to_capture`,
  `onboarding.tool.capture_personal_details`).

### Out of scope / deferred

1. **`pointerTools()` (the `fetch_*` tools)** — runtime-generated from `PointerRegistry`
   (a *different* CoALA subsystem; the `pointers/` sibling dir is explicitly ignored by the
   procedural loader). These stay 100% in PHP. NOT externalised.
2. **`XaiToolDefinitions`** — a *separate* class that is the actual catalogue for the live `xai`
   provider path (`HasAiChat:211` selects `XaiToolDefinitions` when provider is xai;
   `AdviceFyn:439` likewise). The directive names `AiToolDefinitions` as the only target for 4b.
   `XaiToolDefinitions` keeps its own literals this phase. Externalising it (or collapsing the two
   classes onto the shared corpus) is a **follow-up**, flagged for CSJ. See §9.
3. **`update_record` schema** — its `parameters` is *computed* at runtime from
   `App\Constants\UpdateRecordAllowlist::MAP` via `updateRecordSchema()` (a `oneOf` of
   per-entity branches). The allowlist is a **live source** that must never be frozen into `.md`
   (consistent with the v0.5 pointer-model law: live-source procedural facts are pointers, never
   copies). DECISION: externalise only `update_record`'s `name` + `description` to `.md`; the
   `parameters` body in the `.md` carries a sentinel (`{"$allowlist": "update_record"}`) that the
   assembler replaces with the live `updateRecordSchema()` output. This keeps the allowlist a live
   source AND yields byte-identical assembled output. (See plan task 3 for the exact sentinel
   contract.)
4. The static prompt (`FynSystemPrompt::text()`) and the per-turn `FynContextAssembler` layers —
   untouched. 4b does not move prompt overlays or FCA blocks (those are 4c/4d).
5. No change to handlers, dispatch, `AdviceFyn::WRITE_TOOLS`, preview interception, or any
   onboarding behaviour.

---

## 3. Components & Files

### New corpus files (~49 `.md`)

`fyn-memory/procedural/tool_schema/{module}/{tool_name}.md`. Each:

```markdown
---
procedure_id: '{module}.tool.{tool_name}'
kind: tool_schema
module: {module}
version: 1
active: true
effective_from: 2026-06-02
---

```json
{
  "name": "...",
  "description": "...",
  "parameters": { "type": "object", "properties": {...}, "required": [...], "additionalProperties": false }
}
```
```

Body = a single fenced ```json block holding the **provider-neutral** definition only
(`name`, `description`, `parameters`) — NOT the Anthropic `input_schema` wrapper nor the xAI
`{type:function,function:{…}}` wrapper. `effective_from` matches 4a frontmatter contract; exactly
one `active: true` version per `procedure_id`; frontmatter `kind`+`module` MUST equal the path dirs
(enforced by `ProceduralCorpusLoader`).

### Modified PHP

- `app/Services/AI/AiToolDefinitions.php` — becomes the assembler:
  - New private helper `toolFromCorpus(string $procedureId): array` (or a batch
    `toolsFromCorpus(array $procedureIds): array`) that:
    1. Calls `app(ProceduralCorpusLoader::class)->load()->ofKind('tool_schema')` (cached/atomic per 4a).
    2. Resolves the **active** procedure for each `procedure_id` (corpus `active()`), in the **same
       fixed order** the PHP grouping methods currently emit.
    3. `json_decode`s the fenced body (strip the ```json fences) into the native
       `['name','description','parameters']` shape.
    4. For `update_record`, replaces the `{"$allowlist":"update_record"}` sentinel with
       `$this->updateRecordSchema()`.
  - Each grouping method (`navigationTools()`, …) returns
    `$this->toolsFromCorpus([...procedure ids in current order...])` instead of literals.
  - `updateRecordSchema()` stays in PHP unchanged.
  - `getTools()`, `handoffTools()`, `onboardingExtractionTools()` keep their existing
    wrapping/gating/order code verbatim — only the *source* of the inner literals changes.
- Module-slug ↔ `procedure_id` ordering tables live as `private const` arrays in
  `AiToolDefinitions` so the assembly order is explicit and reviewable.

### New test + fixtures

- `tests/Architecture/ToolSchemaGoldenMasterTest.php` (or `tests/Feature/AI/…`) — see §5.
- `tests/fixtures/tool_schema_golden/*.json` — committed golden masters per variant.

---

## 4. Data Flow

```
AiToolDefinitions::getTools($preview)
  → navigationTools()/analysisTools()/… each call toolsFromCorpus([procedure_ids in fixed order])
      → ProceduralCorpusLoader::load()              (4a: cached, atomic, degrade-never-throw)
        → ProceduralCorpus::ofKind('tool_schema') + active($id)
        → fenced-JSON body → json_decode → ['name','description','parameters']
        → (update_record only) sentinel → updateRecordSchema()
  → array_merge in the SAME order as today + pointerTools() (unchanged) 
  → provider wrap (Anthropic input_schema vs xAI function) — unchanged
  → preview gating (drop write groups when $preview) — unchanged
```

`handoffTools($provider)` and `onboardingExtractionTools($provider)` follow the identical pattern:
corpus-sourced inner literals, unchanged provider-wrap tail.

---

## 5. Golden-Master Strategy (the hard gate)

**Task 1 of the plan (BEFORE any refactor)** captures the CURRENT assembled output of every entry
point and variant, serialised deterministically, into committed fixtures. After the refactor, a
test asserts the corpus-driven assembly is **byte-identical** (deep-equal incl. tool ordering and
schema bytes) for every variant.

Captured variants (the full matrix):

| entry point | variant |
|---|---|
| `getTools(false)` | provider = `anthropic` (cache `ai_provider=anthropic`) |
| `getTools(true)` | provider = `anthropic` |
| `getTools(false)` | provider = `xai` (cache `ai_provider=xai`) |
| `getTools(true)` | provider = `xai` |
| `handoffTools('anthropic')` | — |
| `handoffTools('xai')` | — |
| `onboardingExtractionTools('anthropic')` | — |
| `onboardingExtractionTools('xai')` | — |

Serialisation: `json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)` with array
order preserved exactly as PHP emits it (PHP associative arrays are insertion-ordered, so
`json_encode` is stable and ordering-faithful). Fixtures are byte-for-byte the encoded string.

**Pointer-tool caveat:** `getTools()` appends `pointerTools()`, which depends on the live
`PointerRegistry` corpus. To keep the golden master deterministic, the capture and the assertion
run in the SAME corpus state (the test seeds/asserts against the committed `fyn-memory/procedural`
tree). If pointer non-determinism is a problem, the golden-master test compares `getTools()` output
with the `fetch_*` entries filtered out (pointer tools are out of scope and unchanged by 4b), and a
separate assertion confirms the pointer-tool count/names are unchanged. Decision recorded in plan
task 1.

The capture is generated by running the CURRENT (pre-refactor) `AiToolDefinitions` and writing the
fixtures; it is committed in task 1 and is immutable for the rest of the phase. The phase is **not
done until the golden-master is green**. If byte-identity is impossible, the assembler is wrong —
loop until identical; if genuinely blocked, STOP and report BLOCKED (never ship a tool-catalogue
behaviour change).

Additionally the pre-existing guards MUST stay green and act as secondary nets:
- `tests/Architecture/ToolCatalogueParityTest.php` (Anthropic vs xAI name parity)
- `tests/Architecture/AdviceFynWriteToolParityTest.php` (`WRITE_TOOLS` stripping intact)
- `tests/Architecture/PreviewModeToolCatalogueTest.php` (preview gating intact)
- `tests/Feature/Fyn/AdviceFynToolListTest.php`

---

## 6. Error Handling (degrade, never break a turn)

- Runtime assembly uses `ProceduralCorpusLoader::load()`, which **never throws** (4a contract:
  degrades to last-good/empty, cross-request cache, 60s mtime hot-reload). If the corpus is empty
  or a tool's active version is missing, `toolsFromCorpus()` must **fail loud at the deploy gate
  but degrade safely at runtime**:
  - Runtime: a missing/undecodable tool schema is skipped (logged via `report()`), so a corrupt
    corpus cannot empty the catalogue mid-turn beyond what's missing — the turn still runs.
  - Deploy gate: `fyn:procedural:validate` (4a, fail-closed `loadStrict()`) catches malformed
    frontmatter/JSON before release. We additionally add a **completeness assertion** to the
    golden-master test so a *missing* tool (valid corpus, absent file) fails CI — `loadStrict()`
    validates shape but not "all 49 expected tools present".
- `json_decode` failure on a body → skip that tool at runtime + `report()`; the golden-master /
  validate gate is the real guard.

---

## 7. Validation

- `php artisan fyn:procedural:validate` must pass (exit 0) with the new `tool_schema/{module}/`
  tree (frontmatter shape, single active version, path↔frontmatter agreement — all enforced by
  the 4a loader).
- New completeness check (in the golden-master test): every `procedure_id` the assembler expects
  resolves to an active procedure.

---

## 8. Testing

Run green, all of:

- New `ToolSchemaGoldenMasterTest` — 8 variants byte-identical + completeness.
- `php artisan fyn:procedural:validate`
- `tests/Unit/Services/AI/...` (incl. `tests/Unit/Services/AI/Memory/Procedural/*`)
- `tests/Feature/AI/...`
- `tests/Architecture/ToolCatalogueParityTest.php`, `AdviceFynWriteToolParityTest.php`,
  `PreviewModeToolCatalogueTest.php`
- `tests/Feature/Fyn/AdviceFynToolListTest.php`, `CreatePowerOfAttorneyToolTest.php`
- `tests/Feature/AI/PointerToolModeTest.php`, `ProviderSwapLockTest.php`

TDD discipline: write the golden-master fixtures + assertion FIRST (red against an
incomplete/empty `tool_schema` tree), then generate the `.md` files + refactor the assembler until
green. `./vendor/bin/pint` on every changed file before each commit.

---

## 9. Done-When

1. All 49 static tool schemas live as `tool_schema/{module}/{tool_name}.md` in the corpus.
2. `AiToolDefinitions` is a thin assembler — no inline tool-schema literals remain except the
   `updateRecordSchema()` builder and the explicit ordering/slug `const` tables.
3. `ToolSchemaGoldenMasterTest` is GREEN: all 8 variants byte-identical to the task-1 fixtures, plus
   completeness.
4. `fyn:procedural:validate` exits 0.
5. The full `tests/Unit/Services/AI` + `tests/Feature/AI` suites and all listed
   tool/parity/preview tests are GREEN.
6. The throwaway generator is deleted; no dead code left behind.
7. `pint` reports passed on all changed files.

---

## 10. Explicit Deferred / Flag-for-CSJ list

- **`XaiToolDefinitions` not migrated this phase** — it remains the live xAI catalogue with its
  own literals. Recommend a follow-up phase to point it at the same `tool_schema` corpus (or
  collapse the two classes) so xAI schema edits also become PR-to-`.md`. Flagged for CSJ; the
  Two-Fyn contract and the existing `ToolCatalogueParityTest` already guard name-parity, but the
  *schema bytes* diverge by design between the two classes (xAI strict-mode nullable-enum
  handling), so they cannot share an identical body without a transform layer.
- **`update_record` allowlist sentinel** — the one tool whose `parameters` is assembled from a live
  source (`UpdateRecordAllowlist::MAP`) rather than frozen. Documented here so a future reviewer
  does not "tidy" the sentinel into a frozen schema.
- **No icons / Rule #16** — no admin viewer is built in 4b (that is 4f). N/A here.
