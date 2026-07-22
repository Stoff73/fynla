# CoALA Phase 4a — Procedural Corpus Substrate (design)

- **Date:** 2026-06-02
- **Branch target:** `coala`
- **Status:** approved design, pre-plan
- **Master plan:** `fynla-coala-implementation-plan.md` §772–785 ("Phase 4 — Procedural memory and version pinning")
- **Prior art to mirror:** `app/Services/AI/Memory/SemanticCorpusLoader.php` (Phase 1), `app/Services/AI/Pointers/PointerRegistry.php` (Phase 4 core, already merged), `app/Console/Commands/FynSemanticReindex.php` + `FynPointersReindex.php`.

## Context

Phase 4 ("finish the procedural memory externalisation") is 8 deliverables across independent subsystems. It was decomposed into shippable sub-phases, each its own spec → plan → PR to `coala`:

- **4a — Corpus loader substrate** (this spec): directory + loader + `Procedure` VO + `ProceduralCorpus` read model + frontmatter validation + fail-closed duplicate-active + 60s mtime hot-reload + atomic swap + `fyn:procedural:validate` command.
- 4b — Tool-schema externalisation (1682-line `AiToolDefinitions` → per-tool `.md`, byte-identical catalogue, provider-shape wrapping at assembly).
- 4c — Prompt overlay / FCA block / house-view split (per-turn `.md` layers; static `FynSystemPrompt::text()` stays for prefix-cache).
- 4d — Onboarding workflow transition-table → `workflow/onboarding/*.md`.
- 4e — `procedural_version` episode stamping (mirrors the `semantic_snapshot_id` request-scoped holder wired 2026-06-02).
- 4f — Read-only admin viewer.

4a is the foundation every other sub-phase consumes. It ships **consumer-free**: nothing in production reads the corpus yet, so there is zero change to live prompts or the tool catalogue.

## Two corrections to the master plan (grounded in the live codebase)

1. **Path.** The plan §776 says `app/Resources/Memory/Procedural/...`. The as-built convention is **`fyn-memory/procedural/`** — `config/fyn.php` already defines `'procedural_path' => base_path('fyn-memory/procedural')`, and the pointer registry already lives at `fyn-memory/procedural/pointers/`. 4a adopts the as-built path and the pre-existing config key. Procedural kinds sit at `fyn-memory/procedural/{kind}/{module}/*.md` alongside the existing `pointers/` sibling, which the loader **ignores**.

2. **Hot-reload reality.** `SemanticCorpusLoader` has no cross-request cache and no mtime logic — it re-parses every request (in-memory `?array $cache` lives only within the request) and throws fail-closed on malformed (the assembler catches → degrades at runtime; the reindex command surfaces it as a deploy-gate hard fail). On classic PHP-FPM, Laravel reboots per request, so re-parsing already yields always-fresh content; the plan's "60s mtime hot-reload" only earns its keep once a **cross-request cache** exists to throttle re-parsing. 4a builds that cache + mtime mechanism now (decided), so 4b's 1682-line catalogue lands on a substrate that already throttles parse cost.

## Scope & boundary

A complete substrate that parses, validates, caches, and hot-reloads the procedural corpus, exposes a typed read interface, and ships a deploy-gate validate command. **No production `.md` content** (empty corpus + `README.md`), **no consumers**, **no prompt/tool behaviour change**. The corpus directory ships empty; tests drive temp-dir fixtures (the pattern `FynContextAssemblerKnowledgeTest` uses for the semantic corpus).

## Components

All under `app/Services/AI/Memory/Procedural/`.

### `Procedure` (immutable value object)
Mirrors `SemanticFact`. Fields:

| Field | Type | Source frontmatter | Notes |
|-------|------|--------------------|-------|
| `procedureId` | `string` | `procedure_id` | unique logical id |
| `kind` | `string` | `kind` | one of the four kinds |
| `module` | `string` | `module` | module slug, or `global` |
| `version` | `int` | `version` | `>= 1` |
| `active` | `bool` | `active` | exactly one active per `procedure_id` |
| `effectiveFrom` | `Carbon` | `effective_from` | parseable date |
| `effectiveTo` | `?Carbon` | `effective_to` | optional; nullable |
| `body` | `string` | (markdown after frontmatter) | verbatim |

### `ProceduralCorpus` (immutable loaded collection + read surface)
Constructed from `list<Procedure>`. Pure data, no I/O. This is the typed interface 4b–4d consume:

- `all(): list<Procedure>`
- `ofKind(string $kind): list<Procedure>`
- `active(string $procedureId, ?Carbon $asOf = null): ?Procedure` — resolves the current version: the one with `active = true` whose `effective_from <= $asOf` (default now) and (`effective_to` null or `>= $asOf`). When multiple qualify (shouldn't, given fail-closed on duplicate-active), the highest `version` wins.
- `versions(string $procedureId): list<Procedure>` — all versions, for the viewer/audit.

### `ProceduralCorpusLoader` (I/O + cache + hot-reload). Bound `singleton`.
- **Scan:** `fyn-memory/procedural/{kind}/{module}/*.md` for the four known kinds only; ignore `pointers/`, `README.md`, `_TEMPLATE.md`, and any non-kind directory. Derive `kind` from the top dir and `module` from the subdir; the frontmatter `kind`/`module` must agree with the path (mismatch = validation error).
- **Parse:** Symfony Yaml frontmatter, mirroring `SemanticCorpusLoader::parseAndValidate`.
- **Validate** (see rules below).
- **Cache & hot-reload** (see mechanism below).
- `load(): ProceduralCorpus` — the single public entry; returns the current valid corpus (degrading to last-good/empty at runtime).
- `loadStrict(): ProceduralCorpus` — throws on any validation error; used only by the validate command (deploy gate), never on a chat turn.

### `FynProceduralValidate` console command — `fyn:procedural:validate`
Calls `loadStrict()`, prints a summary (`N procedures across K kinds / M modules`, with per-`procedure_id` active version), **exits non-zero on any validation error** with the offending file + reason. Added to both deploy pipelines as a gate, sibling to `fyn:semantic:reindex`/`fyn:pointers:reindex`. Named *validate* (nothing is indexed); open to `:reindex` for pipeline-naming symmetry if preferred.

### Config & filesystem
- `config/fyn.php`: `procedural_path` already exists; add `procedural_reload_interval` (default `60`) and a cache-key constant (`fyn:procedural:corpus`).
- `fyn-memory/procedural/README.md`: documents the `{kind}/{module}/*.md` convention and the frontmatter schema. Kind subdirs are NOT created (corpus ships empty); the loader handles missing dirs gracefully.

## Frontmatter schema

```yaml
---
procedure_id: retirement.tool.create_dc_pension   # unique logical id
kind: tool_schema           # system_prompt_overlay | workflow | tool_schema | fca_block
module: retirement          # module slug, or 'global'
version: 1                  # int >= 1
active: true                # exactly ONE active version per procedure_id
effective_from: 2026-06-02  # parseable date
# effective_to: 2027-04-05  # optional
---
<markdown body — tool JSON in a fenced block for tool_schema,
 transition YAML for workflow, prose for overlay/fca_block>
```

## Validation rules

**Hard fail (throws under `loadStrict`; runtime `load` degrades to last-good/empty + `report()`):**

- Missing any mandatory field (`procedure_id`, `kind`, `module`, `version`, `active`, `effective_from`).
- `kind` not in `{system_prompt_overlay, workflow, tool_schema, fca_block}`.
- `version` not an integer `>= 1`.
- `effective_from` not a parseable date (same for `effective_to` when present).
- Frontmatter `kind`/`module` disagree with the file's path.
- Duplicate `(procedure_id, version)` across files.
- More than one `active: true` for the same `procedure_id`.

## Caching & hot-reload mechanism

- **Within a request:** the singleton holds the `ProceduralCorpus` + a `lastStatCheck` timestamp.
- **Across requests (PHP-FPM):** the parsed corpus is persisted in Laravel cache under `fyn:procedural:corpus`, together with a **signature = max(mtime) over all `.md` files + file count** (detects add / edit / delete).
- **`load()` algorithm:**
  1. If an in-memory corpus exists and `now - lastStatCheck < reload_interval` (60s) → return it (no stat).
  2. Else compute the current signature (re-stat the corpus tree). Update `lastStatCheck`.
  3. If signature == cached signature → adopt the cached corpus (in-memory + return).
  4. If signature changed (or no cache) → re-parse + validate into a **new immutable `ProceduralCorpus`**:
     - On success → **atomic swap**: replace the singleton reference and rewrite the Laravel cache (corpus + signature).
     - On validation failure → **keep last-good**, `report()` the exception, return last-good. If there is no last-good (cold boot on an invalid corpus) → return an **empty** `ProceduralCorpus` + `report()`.
- The runtime path **never throws**; `loadStrict()` (validate command only) is the sole hard-fail surface. This matches the Phase 1 contract: deploy gate hard-fails, runtime degrades, a chat turn is never broken by a malformed corpus.

## Testing (TDD, temp-dir fixtures)

Mirror `FynContextAssemblerKnowledgeTest`'s temp-corpus pattern: write `.md` files into `sys_get_temp_dir()`, point `config('fyn.memory.procedural_path')` at it, `File::deleteDirectory` in `afterEach`.

- **`ProceduralCorpusLoaderTest`** (unit): valid single procedure parses; each validation failure throws under `loadStrict` (missing field, bad kind, non-int version, bad date, path/frontmatter mismatch, duplicate `(id, version)`, duplicate active); empty/missing dir → empty corpus; `pointers/` and `README.md`/`_TEMPLATE.md` ignored; signature change triggers reload; **a reload that turns invalid keeps last-good** (degrades, returns prior corpus, no throw); cold-boot-invalid → empty corpus, no throw.
- **`ProceduralCorpusTest`** (unit): `all()`, `ofKind()`, `versions()`, and `active()` version pinning by `effective_from`/`effective_to`.
- **`FynProceduralValidateTest`** (console/feature): valid corpus → exit 0 + summary; invalid corpus → non-zero + offending file/reason.

## Done when

- The loader parses, validates, caches, and hot-reloads a `fyn-memory/procedural/` corpus.
- `ProceduralCorpus` exposes the typed read surface for 4b–4d.
- Runtime degrades safely (last-good/empty, never throws); `fyn:procedural:validate` hard-fails on any error and is wired into both deploy pipelines.
- Full new test suite green; Pint clean; **zero change to live prompts or the tool catalogue**.

## Explicitly out of scope (later sub-phases)

- Any real `.md` content (4b–4d author it).
- Wiring any consumer — tool catalogue assembly (4b), prompt overlays/FCA (4c), onboarding workflow (4d), `procedural_version` episode stamping (4e), admin viewer (4f).
- Embeddings/index for procedures (none needed; this is exact-match version resolution, not semantic retrieval).
