# CoALA Pointer Registry — Design Spec

**Status:** Approved design (CSJ, 2026-06-01) — ready for implementation plan
**Owner:** CSJ
**Phase:** CoALA Phase 4 (procedural memory) — the pointer registry is its heart (v0.5)
**Canonical:** `fynla-coala-implementation-plan.md` → "v0.5 amendment" · `fyn-memory/procedural/README.md`
**Builds on:** CoALA Phase 1 semantic substrate (`feat/coala-phase1-semantic-memory`) — reuses its `.md` loader + sparse-retriever patterns.

---

## 1. Context & motivation

The v0.5 canonical amendment established: **memory holds pointers, not copies.** Nothing with a live authoritative source is ever frozen into the corpus. The pointer registry is the mechanism — typed fetch-skills routing the agent to the live source (`TaxConfigService`, models, engines) at the moment of need, so figures stay current, context stays clean, and drift is near-zero.

This mostly **formalises** what Fyn already does (`FynContextAssembler` injects live `financial_context` / `existing_records` / tax-year every turn; `AdviceFyn` reads engines and accounts live via tools) **and extends** it with new fetch reach — without the big-bang refactor of routing *all* existing wiring through the registry (that is explicitly out of scope for v1).

### Locked decisions (brainstorm, 2026-06-01)
1. **v1 scope:** formalise existing fetches **and** add new reach. NOT the full route-everything refactor.
2. **Trigger:** **both** modes — deterministic loop pre-fetch *and* LLM-invoked tool — declared per pointer.
3. **Mechanism:** **named code handlers** (closed, reviewed whitelist). Markdown holds routing only; fetch code is typed PHP; nothing executes from markdown.
4. **Provenance:** **lightweight, now** — recorded on `ai_messages.metadata` (where cost-telemetry already writes). Migrates to the Phase-2 episodic blob later.

---

## 2. Architecture

```
Pointer corpus (.md)  ─►  PointerRegistry (loader, fail-closed)
                              │  routes topic → named handler
   user turn ─► loop ─► [prefetch: match query→pointers] ─► FetchDispatcher ─► FetchHandler (code) ─► live source
                   └─► [tool: LLM calls a pointer-tool] ─►      │                                  (TaxConfigService, models, engines)
                                                                ▼
                                      FetchResult {value, sourceLabel, sourceVersion, digest}
                                                                ▼
                          inject into <live_data> context block  +  record provenance on ai_messages.metadata
```

**One execution path, two entry points.** Both the deterministic pre-fetch and the LLM tool-call route through the same `FetchDispatcher` → same handler. The mode only decides *who triggers*.

---

## 3. Components

### 3.1 Pointer corpus — `fyn-memory/procedural/pointers/*.md`
One `.md` per pointer, in procedural memory. Loaded by the registry (reusing the Phase-1 `SemanticCorpusLoader` parse pattern). Frontmatter holds **routing only — never execution**:
```yaml
pointer_id: isa-annual-allowance        # unique kebab-case, matches filename
topic: ISA annual subscription allowance
triggers: [isa, allowance, subscription, contribute]  # keywords for pre-fetch query matching
mode: both                              # prefetch | tool | both
handler: tax_allowance                  # MUST resolve to a registered FetchHandler (fail-closed if not)
source_label: TaxConfigService          # human-readable source, for provenance
version: 1
```
Body = plain-language "when to use" description. Doubles as the LLM tool description (tool mode) and author documentation. Authors add/route pointers via markdown PR; the fetch code never lives here.

**Frontmatter contract (validated fail-closed):** `pointer_id`, `topic`, `mode` (enum), `handler` (must resolve), `version` (int ≥1) always required; `triggers` required when `mode` ∈ {prefetch, both}; `source_label` required (provenance needs it).

### 3.2 `PointerRegistry` (loader)
Mirrors `SemanticCorpusLoader`: parses the pointer `.md` corpus, validates frontmatter, indexes by `pointer_id` and by trigger term. **Fails closed** on: duplicate `pointer_id`, malformed frontmatter, unknown `mode`, **a `handler` that does not resolve to a registered `FetchHandler`** (the critical whitelist check), or `prefetch`/`both` mode with no `triggers`. Validated at boot and by a `fyn:pointers:reindex`-style command at deploy.

### 3.3 `FetchHandler` interface — the closed code whitelist (security boundary)
```php
interface FetchHandler {
    public function id(): string;                  // e.g. 'tax_allowance'
    public function fetch(FetchContext $ctx): FetchResult;
}
```
- `FetchContext` = `{ User $user, string $query, array $params }` (immutable).
- `FetchResult` = `{ string $value, string $sourceLabel, string $sourceVersion, string $digest }` where `value` is the rendered text injected into context / returned to the tool; `sourceVersion` is the source's as-of (e.g. the active tax year, a model's `updated_at`); `digest` = a short hash of the value for provenance.

Handlers are registered in a service provider (e.g. a `FetchHandlerRegistry` tagged-binding or an explicit map). The registry resolves `handler` strings against this set. **Adding a genuinely new fetch capability = a new code handler (dev PR); routing a new pointer to an existing handler = a markdown PR.** This is "write-safety / skills stay in code" applied to fetch capability.

### 3.4 `FetchDispatcher`
Given a `pointer` + `FetchContext`: resolve the handler, call `fetch()`, return the `FetchResult`, and hand the provenance tuple to the recorder. Single seam used by both trigger modes. Catches handler exceptions and degrades (a failed fetch yields no `<live_data>` entry for that pointer + a logged `report()`, never a broken turn — same resilience posture as Phase-1's `<knowledge>` block).

### 3.5 Trigger modes
- **Pre-fetch (`prefetch`/`both`):** the loop tokenises the user query and scores it against pointer `triggers` (reuse `SemanticRetriever`'s tokenise/score), runs the matched pointers through the dispatcher, and injects each `FetchResult.value` into an **additive `<live_data>`** block in `FynContextAssembler` — a sibling to Phase-1's `<knowledge>` block, emitted before the LLM responds. Lazy: only matched pointers fire (no running every engine every turn).
- **Tool (`tool`/`both`):** these pointers register into Fyn's existing tool catalogue (`AiToolDefinitions` + the provider-shape wrapping). An LLM tool-call routes through `executeTool` → `FetchDispatcher` → the same handler, returning `FetchResult.value` to the model.

### 3.6 Provenance recorder
After each fetch, append `{ pointer_id, handler, source_label, source_version, digest }` to `ai_messages.metadata['fetch_provenance'][]` on the assistant turn's row (the same JSON column cost-telemetry uses — no migration). Gives "on date T, Fyn served value V from source S@vN" auditability from day one. Phase 2 later relocates/extends this onto the episodic blob.

---

## 4. How it sits with Phase 1 semantic memory
`<knowledge>` (source-less narrative — "how an ISA shelters gains from tax") and `<live_data>` (pointer fetches — "your remaining ISA allowance is £X, fetched live") are **siblings** in `FynContextAssembler`. A query like "explain my ISA and what's left to contribute" is answered with narrative *and* a live, never-frozen number — the v0.5 model end-to-end. Both blocks are additive; the static prefix-cached prompt is untouched.

---

## 5. v1 scope (the proof)
Ship the **full mechanism** (corpus + `PointerRegistry` loader + `FetchHandler` interface + `FetchDispatcher` + both trigger modes + `<live_data>` block + provenance recorder + a validate/reindex command) plus **three proof handlers**, one per source archetype:
1. **`tax_allowance`** (`TaxAllowanceHandler`) — ISA / pension annual allowances via `TaxConfigService`. The canonical "never freeze £20,000" case; a **config-lookup** source. Registered in `both` modes. Proves the "add reach / single source of truth" half.
2. **`user_financial`** (`UserFinancialHandler`) — **formalises the existing** `AdvicePromptBuilder::buildFinancialContext` / `buildExistingRecordsSummary` fetch behind a handler; a **model/builder** source. Proves the "formalise what exists" half.
3. **`recommendations`** (`RecommendationHandler`) — fetches Fyn's **live recommendations** for the user via the existing recommendation engine (the plan pins the exact entry point — e.g. `CoordinatingAgent` / the module agents' `generateRecommendations` / `RecommendationEngine`); an **engine-run** (compute-on-demand) source, the heaviest archetype. Registered primarily as `tool` mode (recommendations are an explicit "what should I do?" ask, not blanket pre-fetch), demonstrating an engine-backed handler down the LLM tool path. Proves the third source type and that heavy fetches stay lazy/LLM-gated.

Together the three exercise every source archetype (config / model / engine) and both trigger modes. Each ships with a `fyn-memory/procedural/pointers/*.md` pointer routing to it.

---

## 6. Security & constraints
- **Closed code whitelist.** Only registered `FetchHandler`s execute. The registry fails closed if a pointer names an unknown handler. No code path executes anything authored in markdown.
- **Write-safety unchanged.** A pointer *fetches* — it never creates/updates/deletes and never widens write permission (`SurfaceAllowlist` / `ground` gate stay in code, untouched).
- **Prefix-cache invariant.** `FynSystemPrompt::text()` is not touched; `<live_data>` is per-turn dynamic context only.
- **Rule #3.** Tax numbers come only from `TaxConfigService` via a handler — never copied into a pointer `.md`.
- **Lazy fetch.** Pre-fetch fires only matched pointers; tool-mode fires only on LLM request. No blanket per-turn engine runs.
- **Graceful degradation.** A handler error or a malformed corpus degrades that fetch (logged), never breaks the turn (Phase-1 posture).

---

## 7. Testing strategy
- **Loader (fail-closed):** duplicate id, malformed frontmatter, unknown mode, **unknown handler**, missing triggers for prefetch mode → each throws; valid corpus loads + indexes.
- **Handlers:** `tax_allowance` returns the live `TaxConfigService` allowance + correct `sourceVersion` (active tax year); `user_financial` returns the formalized financial summary for a seeded user; `recommendations` returns live engine recommendations for a seeded user with a `sourceVersion`; all three produce a stable `digest`.
- **Dispatcher:** routes pointer→handler, returns `FetchResult`, records provenance; a throwing handler degrades (no entry, no exception escapes).
- **Pre-fetch integration:** a query matching `triggers` injects a `<live_data>` block with the live value; a non-matching query omits it; provenance lands on `ai_messages.metadata`.
- **Tool integration:** a `tool`-mode pointer appears in the catalogue; an LLM tool-call routes through the dispatcher to the same handler (driven via the existing stream-mock harness `tests/Support/Fyn`).
- **Invariant guards:** `FynSystemPrompt` byte-snapshot still passes; no `£` literal in any pointer `.md`.

---

## 8. Out of scope (v1) / future
- **The full "route everything through the registry" refactor** (decision 1) — existing assembler/tool wiring keeps working alongside; migrating it wholesale is a later phase.
- **Phase-2 episodic-blob provenance** — v1 records on `ai_messages.metadata`; the richer blob integration follows Phase 2.
- **Declarative (no-code) handlers** — explicitly rejected for v1 (injection risk); all handlers are code.
- **Beyond the three proof handlers** — more handlers (other engines, more config domains) grow the registry incrementally by dev PR; new routings grow by content PR.

---

## 9. Dependencies & open items
- Reuses Phase-1 `SemanticCorpusLoader` / `SemanticRetriever` patterns (loader parse, sparse scoring) — Phase 1 substrate is merged-ready on `feat/coala-phase1-semantic-memory`.
- The `user_financial` handler reuses `AdvicePromptBuilder` public builders (no behavioural drift).
- Branch/PR target: CoALA work → `coala` (via the `feat/coala-fynloop` train); confirm base at plan time.
