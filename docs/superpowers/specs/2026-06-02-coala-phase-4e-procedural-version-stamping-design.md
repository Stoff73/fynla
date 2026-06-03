# CoALA Phase 4e — `procedural_version` episode stamping (design spec)

- **Phase:** 4e (procedural memory — version pinning onto episodes)
- **Branch:** `feat/coala-4e-procedural-version` (off `feat/coala-4d-onboarding-workflow`)
- **Master plan ref:** `fynla-coala-implementation-plan.md` §782, done-criterion §785 ("Every episode references the exact procedure versions that produced it — one `procedure_id@version` per contributing procedure").
- **Risk:** LOW. Mirrors the `semantic_snapshot_id` holder/stamp/persist pattern that already shipped in Phase 2.

---

## 1. Scope and boundary

### In scope

Close the "stamp built-but-unfed" gap for `procedural_version`, the exact same gap Phase 2 closed for fetch-provenance. Today:

- `app/Services/AI/Memory/Episodic/EpisodeBlobData` already carries a `proceduralVersion` field and renders it into the blob frontmatter (`procedural_version:`), but `HasAiChat::persistEpisode` passes it **`null` always** (`HasAiChat.php:964`).
- The `ai_messages.procedural_version` JSON column already exists (migration `2026_06_01_000001`, fillable, cast `array`, read by `EpisodeProjection.php:47`) but `persistEpisode` **never writes it** to the assistant row's `update()`.
- Phase 4c already built `ProceduralContributionCollector` (request-scoped, scoped-bound) and `FynContextAssembler::selectProcedures` already records each contributed overlay/fca_block procedure into it (`FynContextAssembler.php:297-302`). Nothing reads it.

This phase:

1. Adds a request-scoped **`ProceduralVersionHolder`** (mirroring `SemanticSnapshotHolder`) exposing `add(procedureId, version)` accumulation and `all():list<string>` of `'procedure_id@version'` strings, plus `reset()`.
2. Wires the three procedural consumers to record into it the active procedures they resolved **this turn**:
   - **4b** tool-schema assembly (`AiToolDefinitions::toolsFromCorpus`) — each active `tool_schema` `procedure_id@version` it assembled.
   - **4c** prompt overlays / FCA blocks (`FynContextAssembler::selectProcedures`) — each active `system_prompt_overlay` / `fca_block` `procedure_id@version` it injected.
   - **4d** onboarding workflow (`OnboardingChatDirector` per-turn entry) — the active `workflow` `procedure_id@version` when the onboarding machine drives a turn.
3. Reads `ProceduralVersionHolder->all()` in `HasAiChat::persistEpisode` into:
   - `EpisodeBlobData.proceduralVersion` (blob frontmatter), and
   - the `ai_messages.procedural_version` column on the assistant row `update()`, and
   - the `appendEpisode(...)` audit payload (see §3 for the precise hash-binding decision),
   then `reset()`s the holder alongside the other request-scoped holders.

### Out of boundary (consumed, not rebuilt)

- 4a substrate (`Procedure`, `ProceduralCorpus`, `ProceduralCorpusLoader`, `fyn:procedural:validate`) — consumed.
- `ProceduralCorpusLoader::load()` degrade-never-throw semantics — relied on; this phase adds no new corpus loads.
- The Two-Fyn tool catalogue — UNCHANGED. 4e only **reads** which tool_schema procedures 4b already resolved; it does not alter `AdviceFyn::WRITE_TOOLS` stripping or which tools are exposed in either state.
- `FynSystemPrompt::text()` static prefix — byte-identical, untouched (prefix-cache invariant).
- Phase 2 fetch-provenance / `semantic_snapshot_id` flow — untouched; 4e sits beside it.

---

## 2. Components and files

### New

- **`app/Services/AI/Memory/Episodic/ProceduralVersionHolder.php`** — request-scoped VO accumulator. Methods:
  - `add(string $procedureId, int $version): void` — appends `"{$procedureId}@{$version}"` to an ordered list; de-duplicates exact repeats (a procedure resolved twice in one turn appears once) to keep the stamp deterministic.
  - `all(): list<string>` — the accumulated list in insertion order.
  - `reset(): void` — clears the list.
  - Lives in the `Episodic` namespace (it is an episode-stamping holder, read at persist time), exactly where `SemanticSnapshotHolder` lives.

### Modified

- **`app/Providers/AppServiceProvider.php`** — add `$this->app->scoped(ProceduralVersionHolder::class);` next to the existing `SemanticSnapshotHolder` / `ProceduralContributionCollector` / `FetchProvenanceCollector` scoped bindings (~line 91-96).
- **`app/Services/AI/AiToolDefinitions.php`** — in `toolsFromCorpus`, after a tool is successfully assembled from `$corpus->active($procedureId)`, record the resolved `procedureId@version` into `ProceduralVersionHolder`. Resolved via `app(ProceduralVersionHolder::class)` (the class already resolves the corpus via `app(...)`, so this is consistent). Only record on **successful** assembly (skip the null/undecodable degrade paths) so the stamp reflects what actually reached the catalogue.
- **`app/Services/AI/Fyn/FynContextAssembler.php`** — in `selectProcedures`, alongside the existing `proceduralContributions->record([...])` call, also record into `ProceduralVersionHolder` (`add($active->procedureId, $active->version)`). Inject the holder via the constructor (mirroring the `ProceduralContributionCollector $proceduralContributions` constructor param already present at line 49) **or** resolve via `app(...)` at the record site — constructor injection preferred for testability and parity with the existing 4c collector.
- **`app/Services/Onboarding/OnboardingChatDirector.php`** — at the per-turn drive point (where the director resolves the active onboarding workflow to drive a state turn), record the active `workflow` `procedure_id@version`. See §3 for why this lives in the director's per-turn path, **not** inside `OnboardingStateMachine::transitionTable()` (which is statically cached and would only record once per process).
- **`app/Traits/HasAiChat.php`** — in `persistEpisode`:
  - resolve `app(ProceduralVersionHolder::class)`, read `->all()`, `->reset()` (alongside the existing `SemanticSnapshotHolder` and `FetchProvenanceCollector` reset block at lines 949-955);
  - map empty list → `null`, non-empty → the `list<string>`;
  - pass into `EpisodeBlobData(proceduralVersion: $proceduralVersion, ...)` (replaces the hardcoded `null` at line 964);
  - add `'procedural_version' => $proceduralVersion` to the assistant `->update([...])` payload (lines 976-980) so the SQL column is written, not just the blob;
  - pass `'procedural_version' => $proceduralVersion` into the `appendEpisode([...])` payload (lines 982-990).

No migration. No model change (`procedural_version` already fillable + cast `array` on `AiMessage`). No new config.

---

## 3. Data flow and the ONE design decision (audit hash-binding)

### Per-turn flow

```
turn begins (scoped container fresh)
  ├─ AiToolDefinitions::toolsFromCorpus()      → Holder->add(tool_schema id@v)  ×N
  ├─ FynContextAssembler::selectProcedures()   → Holder->add(overlay/fca id@v)  ×M
  └─ OnboardingChatDirector (onboarding turns)  → Holder->add(workflow id@v)     ×1
...turn completes, persistEpisode():
  proceduralVersion = Holder->all() ?: null      (empty → null)
  Holder->reset()
  ├─ EpisodeBlobData.proceduralVersion  → blob frontmatter `procedural_version:`
  ├─ ai_messages.procedural_version     → SQL column (JSON array | null)
  └─ appendEpisode payload              → audit __episode__ result_summary (see below)
```

### Design decision: `procedural_version` goes into `result_summary`, NOT into the v2 hash preimage

The directive says "mirror exactly how `semantic_snapshot_id` flows into both the blob frontmatter and the v2 hash preimage." There is a direct tension between that phrasing and the golden-master / byte-identity safety net, and the master plan resolves it in favour of byte-identity. The decision, with full rationale:

**The v2 (`hash_scheme = 2`) episode preimage is FROZEN.** `AuditChainService::computeEpisodeRowHash` builds:

```
$prevHash . $serialised . $signedAtIso . '|' . $blobSha . '|' . $snapshotId . '|' . $provDigest
```

`verifyChain` re-derives every persisted v2 row from this exact string using values read back out of `result_summary`. **Appending `procedural_version` to this preimage changes the hash input for every v2 row**, so every already-persisted v2 attestation would fail `verifyChain` — a hard regression of the audit chain, and impossible to make byte-identical without bumping to a `hash_scheme = 3` (which the LOW-risk, "small" framing of this phase explicitly rules out, and which §785 does not ask for).

**The master plan does not require hash-binding.** §782 says only: *"Stamp `procedural_version` on every episode (Phase 2 column). When multiple procedures contribute to a turn, store as a JSON array of `procedure_id@version`."* §785's done-criterion is *"Every episode references the exact procedure versions that produced it"* — satisfied by the column + blob + the audit row's `result_summary`. Neither mandates the cryptographic preimage.

**The in-code precedent is `blob_md_path`.** It is persisted into `result_summary` (`AuditChainService.php:149`) but **deliberately excluded** from `computeEpisodeRowHash` — the path is recorded-but-not-hashed because only the content SHA needs cryptographic binding. `procedural_version` follows the same precedent: it is attestation **metadata recorded into `result_summary`**, not a content digest that needs to be in the preimage. The episode's content integrity is already bound via `blob_md_sha256` (the blob frontmatter — which now contains `procedural_version` — is inside the SHA'd file), so `procedural_version` is *already transitively hash-protected through `blob_md_sha256`* without touching the preimage string.

**Therefore:** `appendEpisode` writes `procedural_version` into `result_summary` (and `EpisodeProjection` / the audit UI can surface it), but `computeEpisodeRowHash` / `computeRowHash` are **byte-for-byte unchanged**, and `verifyChain` stays byte-identical for v1 and v2 rows. This is the only safe reconciliation of "mirror semantic_snapshot_id's storage" with the golden-master discipline. It is the one decision a reviewer must sign off; it is reconcilable (not a blocker) because the master plan backs the column/result_summary path and the LOW-risk framing forbids a hash-scheme bump.

> If CSJ later decides `procedural_version` MUST be in the cryptographic preimage, that is a separate `hash_scheme = 3` migration with its own golden-master against the v2 corpus — explicitly **out of scope** here and flagged in §9.

### Why 4d recording lives in the director, not `transitionTable()`

`OnboardingStateMachine::transitionTable()` is a `static` method guarded by a process-lifetime static cache (`$transitionTableCache`). It resolves `corpus->active('onboarding.workflow.fyn-onboarding')` exactly once per process; every later turn returns the cached array without touching the corpus. Recording into a **request-scoped** holder from inside that method would stamp only the first turn in a process and leave every subsequent onboarding turn unstamped — wrong and non-deterministic across the eval harness / queue worker lifetime. The active workflow `procedure_id@version` is therefore resolved and recorded in the **`OnboardingChatDirector` per-turn path** (the code that actually drives a state turn), reading `corpus->active('onboarding.workflow.fyn-onboarding', now())` for the id+version and recording it only when the corpus actually supplied the workflow data (i.e. the merge path that `transitionTable()` took). When the corpus has no active workflow procedure (the empty-corpus default today), nothing is recorded — onboarding turns stamp `null`, matching the in-code-table fallback.

---

## 4. Error handling (degrade, never break a turn)

- **`ProceduralVersionHolder`** is a plain in-memory accumulator: `add`/`all`/`reset` cannot throw.
- **Recording sites** are guarded by the existing try/catch blocks that already wrap each consumer's corpus access (`AiToolDefinitions::toolsFromCorpus` degrade path, `FynContextAssembler`'s `try { ... } catch` around `selectProcedures`, `OnboardingChatDirector`/`transitionTable`'s `try/catch`). A throw inside recording is impossible (pure VO), but if a consumer's surrounding corpus access fails, it degrades to recording nothing for that kind — the stamp simply omits that procedure, never breaks the turn.
- **`persistEpisode`** is already wrapped in `try { ... } catch (\Throwable $e) { report($e); }` — any failure reading the holder or writing the column/blob/audit is reported and swallowed; the verbatim `ai_messages` columns remain the forensic fallback (Phase 2 contract). Empty holder → `null` everywhere (no empty `[]` arrays persisted).
- **Reset discipline:** the holder is `reset()` inside `persistEpisode` in the same block as `SemanticSnapshotHolder->reset()` / `FetchProvenanceCollector->reset()`, so a scoped instance reused across turns (eval harness, queue worker) never leaks last turn's stamp into the next.

---

## 5. Golden-master strategy (zero-regression proof)

Three independent byte-identity / green-suite gates:

1. **Audit-chain byte-identity (the critical one).** A test in `tests/Unit/Services/AI/AuditChainEpisodeTest.php` (or a sibling) asserts that after this phase:
   - the `computeEpisodeRowHash` preimage string is unchanged — proven by re-running the existing "v1 chain still verifies green", "mixed v1 + v2 chain verifies green", and "detects tampering with a v2 row hash" cases, all of which must stay green untouched;
   - an `appendEpisode` call carrying a non-null `procedural_version` produces a row whose `row_hash` equals the row_hash it would have produced WITHOUT `procedural_version` (i.e. `procedural_version` is provably absent from the preimage), while `result_summary['procedural_version']` carries the value. This is the explicit assertion that the hash is byte-identical regardless of the new field.
2. **Tool-catalogue invariance.** The Phase 4b golden-master (the committed fixture of the assembled tool catalogue + provider-shape wrapping) must stay green — recording into the holder is a side effect that must not alter the returned `toolsFromCorpus` array. Re-run the 4b golden-master test unchanged.
3. **Assembler output invariance.** The Phase 4c golden-master (`build()` output for the 4 representative turns) must stay green — recording is a side effect that must not change a single byte of the assembled context. Re-run the 4c golden-master unchanged.

Plus: the **full episodic + audit suite** green (`EpisodePersistenceTest`, `EpisodeEndpointsTest`, `AuditChainEpisodeTest`, `AiAuditEventHashSchemeTest`, `AiAuditVerifyChainEpisodeTest`, `FynContextAssemblerKnowledgeTest`).

---

## 6. Validation

- `./vendor/bin/pint` on every touched file → must report `PASS` before each commit. (Watch the known Pint quirk: add import + first usage in one Write.)
- `php artisan fyn:procedural:validate` still green (no corpus content changes in this phase, but run it as the substrate gate).
- No `migrate:fresh`/`migrate:refresh`. No `.env` edit. Local dev DB already seeded.

---

## 7. Testing (TDD, mirror the Phase 2 pattern)

Red-first, mirroring `tests/Feature/AI/EpisodePersistenceTest.php`'s `semantic_snapshot_id` cases and the assembler stamping test:

**Stamping side (holder accumulation):**
- `it('accumulates procedure_id@version on add and returns them in order')`
- `it('de-duplicates an identical procedure_id@version')`
- `it('FynContextAssembler::selectProcedures records each injected overlay/fca_block into the holder')` — drive a turn with a seeded overlay+fca corpus (temp corpus dir), assert `Holder->all()` contains both `id@v`.
- `it('AiToolDefinitions records each assembled tool_schema procedure into the holder')` — assert the holder contains the assembled tool ids@v after `getToolDefinitions(...)`.
- `it('OnboardingChatDirector records the active workflow procedure when the corpus supplies it')` — and `it('records nothing when the corpus has no active workflow')` (empty-corpus default → null stamp).

**Persist side (mirror `EpisodePersistenceTest`):**
- `it('stamps the accumulated procedural_version onto the blob + column + attestation')` — seed the holder via `app(ProceduralVersionHolder::class)->add(...)`, run a turn, assert: blob frontmatter contains `procedural_version:` with the array, `ai_messages.procedural_version` column equals the array, and `appendEpisode` result_summary carries it.
- `it('records a null procedural_version when the holder is empty')` — blob `procedural_version: null`, column `null`, result_summary `procedural_version` null/absent.
- **Byte-identity gate:** `it('does not change the v2 episode row hash when procedural_version is present')` — the §5.1 assertion.
- Existing "does not throw when the blob write fails (resilient)" must stay green.

Implement minimally to green after each red. Conventional commits, trailer line `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`.

---

## 8. Done-when

- `ProceduralVersionHolder` exists, scoped-bound, `add`/`all`/`reset` tested.
- 4b/4c/4d consumers record their active procedures into the holder per turn; verified by the stamping tests.
- `persistEpisode` writes the accumulated `procedure_id@version` list to the blob frontmatter **and** the `ai_messages.procedural_version` column **and** the audit `result_summary`; empty → `null` everywhere; holder `reset()` alongside the others.
- §5 golden masters (audit byte-identity, 4b tool catalogue, 4c assembler output) all green; full episodic + audit suite green.
- `pint` PASS on all touched files; `fyn:procedural:validate` green.
- Two-Fyn tool catalogue unchanged; static prompt byte-identical; no migration.

---

## 9. Out of scope / deferred

- **Hash-scheme v3** (binding `procedural_version` into the cryptographic preimage). Deferred — would need its own golden-master migration against the v2 corpus and contradicts this phase's LOW-risk framing. `procedural_version` is transitively protected via `blob_md_sha256` (it is inside the SHA'd blob frontmatter). Flag for CSJ if cryptographic preimage inclusion is later required.
- **Admin viewer for `procedural_version`** beyond what `EpisodeProjection` already surfaces. The Phase 4f read-only admin procedure viewer is a separate phase; 4e only ensures the column/blob/audit carry the data. No new Vue view, no `<AppLayout>` work here.
- **Working-memory `procedural_version` pin "at session start"** (master plan §141/§175/§541 — pin once per session into `WorkingMemory`). That is the Phase 4-working-memory / Phase 5 concern. 4e stamps **per-turn what actually contributed**, which is the §782 episode-stamp, distinct from the session-start pin.
- **Cost-attribution `procedural_version`** (`ai_cost_attribution.procedural_version`, migration `2026_05_30_000002`) — Phase 5 telemetry; not wired here.
- Any change to `ProceduralContributionCollector` (Phase 4c's structured collector). 4e adds the parallel string-list `ProceduralVersionHolder` per the directive's explicit `all():list<string>` contract; the two coexist (one structured for prompt-contribution forensics, one flat `id@v` for the episode stamp). Consolidating them is not in scope.
