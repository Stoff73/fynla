# CoALA Phase 4c — Prompt-Overlay / FCA-Block Consumption (Design Spec)

- **Phase:** 4c (master plan `fynla-coala-implementation-plan.md` sec 779)
- **Branch:** `feat/coala-4c-prompt-overlays`
- **Risk:** MED — prefix-cache sensitive. The static `FynSystemPrompt::text()` MUST stay byte-identical.
- **Depends on:** 4a procedural substrate (already on this branch — `Procedure`, `ProceduralCorpus`, `ProceduralCorpusLoader`, corpus at `fyn-memory/procedural/{kind}/{module}/*.md`, kinds include `system_prompt_overlay` and `fca_block`); 4b tool-schema externalisation (already on this branch).
- **Date:** 2026-06-02

---

## 1. Goal

Build the **consumption mechanism** for the two remaining prose-bearing procedural kinds —
`system_prompt_overlay` and `fca_block` — so that, once content is authored, those per-turn
prompt layers change via a PR-to-`.md` rather than a PHP edit.

The mechanism mirrors the existing `<knowledge>` (semantic) and `<live_data>` (pointer) layers in
`app/Services/AI/Fyn/FynContextAssembler::build()`: it loads the **active** procedures of those two
kinds (via the bound `ProceduralCorpusLoader`), selects the ones whose `module` matches the current
turn, and injects them as **additive per-turn layers AFTER the static prefix** — wrapped as
`<overlay>…</overlay>` and `<fca_block>…</fca_block>`.

Phase 4c ships with an **empty** `system_prompt_overlay` and `fca_block` corpus. Because the corpus
is empty, the mechanism is provably a **no-op today**: `build()` output is byte-identical to the
current output for representative turns. This proves the layer is purely additive and zero-regression
before any content is ever authored.

**No static content moves in this phase.** Identifying content that *should* eventually become a
per-turn overlay is recorded in §10 (Deferred) for human review — moving prefix-cache-sensitive
content is a separate, CSJ-reviewed decision, not part of 4c.

---

## 2. Scope & Boundary

### In scope (THIS phase only)

- A new consumption path inside `FynContextAssembler::build()` that:
  - loads active `system_prompt_overlay` procedures via `ProceduralCorpusLoader::load()`, filters to
    those matching the current turn's module, and emits one `<overlay>…</overlay>` block (degrades to
    no block on empty/error);
  - loads active `fca_block` procedures the same way and emits one `<fca_block>…</fca_block>` block
    (degrades to no block on empty/error);
  - records the injected `procedure_id@version` of each contributed overlay/fca_block procedure into
    a request-scoped collector (`ProceduralContributionCollector`, new — see §3), so 4e can later read
    it at persist time. 4c only *populates* the collector; the episode stamping itself is 4e.
- A request-scoped `ProceduralContributionCollector` (mirrors `FetchProvenanceCollector` /
  `SemanticSnapshotHolder` exactly), scoped-bound in `AppServiceProvider`.
- The empty `system_prompt_overlay/` and `fca_block/` corpus state (no `.md` content files; the
  loader already tolerates absent kind directories — see §6).
- Golden-master fixtures + a byte-identity test proving `build()` is unchanged on representative
  turns with the empty corpus.
- Tests that, with a *temporary fixture corpus* (written into a temp dir, as the existing
  `FynContextAssemblerKnowledgeTest` does), the `<overlay>` / `<fca_block>` blocks appear, are
  module-scoped, degrade on malformed input, and record contributions.

### Out of scope / explicitly NOT touched

- `app/Services/AI/Fyn/FynSystemPrompt::text()` — **byte-frozen.** No edits. The static FCA backbone
  and every static block stay exactly where they are.
- The assembled tool catalogue (4b's domain). 4c injects prose layers only; it does not add, remove,
  reorder, or reshape any tool in either Fyn state.
- The Two-Fyn dispatch / tool-gating. AdviceFyn stays read-only; onboarding stays the only writer.
- The `workflow` kind (onboarding transition data) — that is 4d, not 4c.
- The `tool_schema` kind — that is 4b, already done.
- The `pointers/` sibling subsystem — a different subsystem, ignored by the loader.
- Any migration of existing static content into `.md` (deferred, §10).
- The `procedural_version` episode **stamping** (4e) — 4c only writes into the collector.

---

## 3. Components & Files

### New

| File | Purpose |
|------|---------|
| `app/Services/AI/Memory/Procedural/ProceduralContributionCollector.php` | Request-scoped accumulator of `['procedure_id' => …, 'kind' => …, 'module' => …, 'version' => …]` entries contributed to the current turn's prompt. Exact mirror of `FetchProvenanceCollector`: `record(array)`, `all(): list`, `reset()`. Pure VO; no I/O. |
| `tests/Feature/AI/PromptOverlayGoldenMasterTest.php` | Phase 4c hard gate. Captures `build()` output for representative `FynTurnContext`s with the **empty** corpus into fixtures; asserts post-mechanism output is byte-identical. |
| `tests/Unit/Services/AI/Fyn/FynContextAssemblerOverlayTest.php` | Behavioural tests: with a temp fixture corpus the `<overlay>`/`<fca_block>` blocks appear, are module-scoped, degrade on error, and populate the collector. Mirrors `FynContextAssemblerKnowledgeTest`. |
| `tests/fixtures/PromptOverlay/*.json` | Byte-frozen golden-master fixtures (one per representative turn variant). |

### Modified

| File | Change |
|------|--------|
| `app/Services/AI/Fyn/FynContextAssembler.php` | Inject the `ProceduralCorpusLoader` + `ProceduralContributionCollector` via the constructor; add the two additive layers after the existing `<live_data>` block, inside a `try/catch` that degrades to no block. Reads `ProceduralCorpusLoader::load()` and filters active procedures by current-turn module. |
| `app/Providers/AppServiceProvider.php` | `$this->app->scoped(ProceduralContributionCollector::class);` alongside the existing `SemanticSnapshotHolder` / `FetchProvenanceCollector` scoped bindings. |

### Consumed unchanged (4a / 4b substrate)

- `ProceduralCorpusLoader::load()` (never-throws runtime entry, bound singleton).
- `ProceduralCorpus::ofKind('system_prompt_overlay')` / `ofKind('fca_block')` and
  `active($procedureId, $asOf)` for effective-dated active resolution.
- `Procedure` VO (`procedureId`, `kind`, `module`, `version`, `active`, `effectiveFrom`,
  `effectiveTo`, `body`, `effectiveOn()`).

---

## 4. Data Flow

```
AiChatController / HasAiChat
  → FynContextAssembler::build(FynTurnContext $ctx, ?callable $orchestrate)
      … existing static-prefix-following layers (user_profile, knowledge, live_data, …) …
      → ProceduralCorpusLoader::load()                       (never throws)
      → ofKind('system_prompt_overlay'); keep active() per procedure_id, effectiveOn(now),
        module matches ctx-module                            (module-scoped selection)
          → wrap selected bodies as <overlay>…</overlay>     (additive, after prefix)
          → collector->record(id@version) per contributed proc
      → ofKind('fca_block'); same selection
          → wrap as <fca_block>…</fca_block>
          → collector->record(...)
      … existing trailing layers (preview_mode, </context>, <user_message>) …
  ← assembled per-turn block (static FynSystemPrompt::text() prepended by the caller, untouched)
```

Module of the current turn (the selection key):

- Onboarding turns: `$ctx->onboardingFocus` (already the module-ish key used by `focusLabel`).
- Advice turns: derived from `$ctx->currentRoute` / `$ctx->classification` via the **same** resolution
  the assembler already uses for module context (`AdvicePromptBuilder::moduleContextFor`), reused — no
  new module-mapping logic invented. A turn with no resolvable module selects only procedures whose
  `module` is the wildcard `general` (the corpus convention used elsewhere — `general` = applies to
  every turn). The exact wildcard token is fixed in the plan's Task-2 test, not invented here.

Because the corpus is empty in 4c, **zero** procedures are ever selected, so the two new blocks are
never emitted and the collector stays empty — output is byte-identical to today. The selection logic
is exercised only by the temp-fixture behavioural tests.

---

## 5. Error Handling — degrade, never break a turn

Identical discipline to the `<knowledge>` and `<live_data>` layers:

- The whole overlay/fca_block section is wrapped in `try { … } catch (\Throwable $e) { report($e); }`
  and the catch leaves the block list empty. A malformed corpus, a missing file mid-deploy, or any
  loader fault degrades to **no overlay block / no fca_block** — the turn still builds with the full
  static prefix + every other layer.
- `ProceduralCorpusLoader::load()` is itself never-throwing (4a), so the catch is defence-in-depth.
- An empty selection (no matching active procedures) emits no block at all — never an empty
  `<overlay></overlay>` (matching the `<knowledge>` "omit when empty" rule).
- The collector is only written **after** a procedure is confirmed selected and wrapped, so the
  recorded contribution set is exactly what reached the prompt.

---

## 6. Empty-corpus shipping state

The 4a loader already tolerates absent kind directories (`signature()` and `parse()` both
`continue` when `{root}/{kind}` is not a directory). 4c therefore ships with **no**
`system_prompt_overlay/` or `fca_block/` content directories required. If the plan chooses to add a
`.gitkeep`-bearing placeholder directory for discoverability, it must contain **no `.md` files** (a
stray `.md` would be parsed; an empty dir is a clean no-op). The golden-master test (§7) is the gate
that proves the shipped corpus state yields byte-identical output.

---

## 7. Golden-Master Strategy (the zero-regression proof)

Two independent gates, both must be green:

### 7a. Static prompt frozen (already exists, re-assert)

`tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php` already asserts `FynSystemPrompt::text()` is
byte-stable and arg-free. 4c does not touch `FynSystemPrompt`, so this test stays green untouched. It
is the prefix-cache byte-invariance gate. (4c adds no new fixture for `text()` because the existing
self-equality + structural assertions already lock it; if the plan wants belt-and-braces it may hash
`text()` into a one-line fixture, but it must NOT edit `FynSystemPrompt`.)

### 7b. `build()` byte-identical on representative turns (new — the 4c gate)

`PromptOverlayGoldenMasterTest` mirrors `ToolSchemaGoldenMasterTest` exactly:

- A `CAPTURE_PROMPT_OVERLAY_GOLDEN=1`-gated capture step writes `build()` output for each
  representative `FynTurnContext` variant into `tests/fixtures/PromptOverlay/{variant}.json` (the
  output string is stored verbatim; JSON wrapping is only for a stable on-disk container, as 4b does).
- A non-capture assertion step rebuilds each variant and asserts the bytes equal the committed
  fixture.
- **Capture is taken with the empty 4c corpus and on the post-mechanism code** — i.e. the fixture is
  the *current* (pre-content) `build()` output, and the test proves the mechanism, with the empty
  corpus, reproduces it. Equivalently the fixtures are captured on the parent (4b) tip before the
  assembler edit; either order is acceptable provided the committed fixture equals the empty-corpus
  output. The plan fixes one order; both prove the same byte-identity.

Representative variants (small, deterministic, no live LLM):

| Variant | mode | route / focus | preview | classification |
|---------|------|---------------|---------|----------------|
| `advice_dashboard` | advice | `/dashboard` | false | `['primary' => 'general']` |
| `advice_retirement_position` | advice | `/retirement` | false | `['primary' => 'retirement']` (triggers POSITION bucket) |
| `onboarding_savings` | onboarding | focus `savings` | false | null |
| `onboarding_protection` | onboarding | focus `protection` | false | null |

These cover both Fyn states and a couple of modules (the directive's requirement). Each turn uses a
freshly factoried user and the seeded `TaxConfiguration`, so `build()` is deterministic. Any field in
`build()` that is non-deterministic across runs (none identified — tax year, profile, module context
are all deterministic for a fixed user) would have to be normalised before hashing; the plan's Task-1
verifies the capture is stable across two runs before committing.

If 7b is ever red, the mechanism changed behaviour — that is a bug, loop until byte-identical; if
genuinely impossible, STOP and report BLOCKED (do not ship a behaviour change).

---

## 8. Validation

- `fyn:procedural:validate` (4a deploy gate) already validates `system_prompt_overlay` and
  `fca_block` files when present (path↔frontmatter kind/module agreement, exactly one active version
  per `procedure_id`, version ≥ 1, boolean `active`, non-empty body, effective-dating). 4c authors no
  content, so the gate passes trivially on the empty corpus; it becomes load-bearing the moment
  content is added in a future PR.
- `./vendor/bin/pint` on every changed/new file before commit; must report `passed`.
- Architecture suite stays green (the new collector is a class, not an interface — no
  services-are-classes exception needed).

---

## 9. Testing

| Test | Type | Asserts |
|------|------|---------|
| `FynSystemPromptTest` (existing, unchanged) | Unit | `text()` byte-stable — prefix-cache invariance. |
| `PromptOverlayGoldenMasterTest` (new) | Feature | `build()` byte-identical to committed fixtures on 4 representative turns with the empty corpus (the 4c hard gate). |
| `FynContextAssemblerOverlayTest` (new) | Unit | With a temp fixture corpus: (a) `<overlay>` appears for a matching module; (b) `<fca_block>` appears; (c) blocks are module-scoped (a different-module procedure is NOT injected); (d) malformed corpus degrades to no block, turn still builds (`<context>` present); (e) `<overlay>` omitted when no match (no empty tag); (f) the contribution collector holds the injected `procedure_id@version` for each contributed procedure and is empty when nothing matched. |
| Existing `FynContextAssemblerKnowledgeTest` / `FynContextAssemblerLiveDataTest` / `FynContextAssemblerTest` | Unit | Stay green (regression guard — the new layers are additive and ordered after `<live_data>`). |
| Full suite | — | Green before commit per TDD. |

TDD order: write the failing golden-master + overlay tests first, run red, implement the minimal
assembler change + collector, run green, commit. Conventional commits with the required trailer.

---

## 10. Deferred (record only — do NOT move in 4c)

Recorded for CSJ; moving any of these is a separate, human-reviewed, prefix-cache-sensitive decision:

1. **Static FCA backbone in `FynSystemPrompt::text()`** — the compliance/FCA prose currently lives in
   the cached static prompt. It is a candidate for becoming per-module `fca_block` overlays *only if* a
   per-module split is wanted; until then it must stay in `text()` to preserve the prefix cache. NOT
   moved in 4c.
2. **Per-tier / per-module prompt overlays** — if Fyn should say different things per subscription tier
   or per module beyond what the current static prompt + dynamic context already cover, those are the
   natural first `system_prompt_overlay` authored content. None authored in 4c.
3. **`semantic_snapshot_id` plumbed-but-null** (pre-existing, from Phase 2) — unrelated to 4c but noted
   so it is not conflated with the new procedural-contribution collector.
4. **4e episode stamping** — 4c populates `ProceduralContributionCollector`; reading it at
   `persistEpisode` time and binding it onto the episode blob/audit attestation is 4e.

---

## 11. Done-when

- [ ] `FynContextAssembler::build()` emits `<overlay>` / `<fca_block>` layers after `<live_data>`,
      module-scoped, degrade-on-error, populating `ProceduralContributionCollector`.
- [ ] `ProceduralContributionCollector` exists, scoped-bound, mirrors `FetchProvenanceCollector`.
- [ ] 4c ships with empty `system_prompt_overlay` / `fca_block` corpus (no `.md` content).
- [ ] `PromptOverlayGoldenMasterTest` green — `build()` byte-identical on all 4 representative turns.
- [ ] `FynContextAssemblerOverlayTest` green — appearance, module-scoping, degrade, collector all proven
      against a temp fixture corpus.
- [ ] `FynSystemPromptTest` still green untouched (prefix-cache invariance).
- [ ] Full Pest suite green; `pint` passed on every changed file.
- [ ] No tool-catalogue change in either Fyn state (4b golden master still green); Two-Fyn contract
      intact.
