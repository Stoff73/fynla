# CoALA Phase 4d — Onboarding Workflow Transition Table → `.md` (Design Spec)

- **Phase:** 4d (master plan `fynla-coala-implementation-plan.md` sec 780)
- **Branch:** `feat/coala-4d-onboarding-workflow`
- **Risk:** MED — onboarding behaviour must not change. Golden-master on the transition table.
- **Depends on:** 4a procedural substrate (already on this branch — `Procedure`, `ProceduralCorpus`,
  `ProceduralCorpusLoader`, corpus at `fyn-memory/procedural/{kind}/{module}/*.md`, kinds include
  `workflow`); 4b tool-schema externalisation; 4c prompt-overlay consumption (both already on this
  branch).
- **Date:** 2026-06-02

---

## 1. Goal

Externalise the **transition DATA** of the Fyn-driven onboarding state machine
(`app/Services/Onboarding/OnboardingStateMachine.php`) out of the PHP class into the git-tracked
procedural corpus, so that the parts of the flow that are pure data — *which state follows which on a
static transition, and each state's turn shape* — change via a PR-to-`.md` rather than a PHP edit.

The state-machine **code stays in PHP**. A new load path resolves the active `workflow` procedure for
the onboarding flow via `ProceduralCorpusLoader`, parses its fenced ```yaml``` transition table, and
makes it available to `OnboardingStateMachine`. The in-code table remains as a **fallback** when the
corpus procedure is absent or malformed (degrade — never break onboarding). When the procedure *is*
present and valid, the loaded transition table **MUST deep-equal** the cleanly-separable subset of the
in-code table — proven by a golden-master test that is the phase's hard gate.

This phase moves the **cleanly-separable data subset only** (see §3). The parts of the table that are
PHP code — branching `next` callables, `skip_if` predicates, callable `prompt_text` builders — are
**not** data and cannot be round-tripped through YAML to deep-equal a closure/callable. They stay in
PHP and are referenced from the `.md` by their opaque string token exactly as the in-code table already
stores them (`next` is already `"App\\…\\OnboardingStateMachine::nextFromPathChoice"` — a string).
`skip_if` (stored in-code as the array callable `[self::class, 'method']`) is **out of scope** for the
table extraction (see §3, §10) because it is not currently a string token.

---

## 2. Scope & Boundary

### In scope (THIS phase only)

- One new corpus file: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`, frontmatter
  `{procedure_id: 'onboarding.workflow.fyn-onboarding', kind: workflow, module: onboarding,
  version: 1, active: true, effective_from: 2026-06-02}`, body containing the transition table in a
  single fenced ```yaml``` block. (`{flow_name}` = `fyn-onboarding` — the single Fyn onboarding flow
  this machine drives; the file name encodes `{procedure_id}.v{version}.md` per the corpus naming the
  4b/4c tool-schema files already use.)
- A new pure parser/reader, `OnboardingWorkflowTable` (working name; final name fixed in the plan),
  under `app/Services/Onboarding/` that:
  - given a `Procedure` (kind `workflow`, the active onboarding procedure), parses the fenced
    ```yaml``` block in its body into the transition-table array shape;
  - degrades to `null` (not an exception) on any parse/shape error so the caller falls back to the
    in-code table.
- A load path on `OnboardingStateMachine` (a new static method, e.g.
  `transitionTable(): array`) that:
  - asks the bound `ProceduralCorpusLoader::load()` for the active
    `onboarding.workflow.fyn-onboarding` procedure;
  - if present and the parser yields a valid table → returns the **merged** state table, where the
    extracted data fields come from the `.md` and the **PHP-only fields** (callable `next`,
    `skip_if`, callable `prompt_text`, `bubble_capture`, `value_parser`, `extraction_tool`, etc.
    per §3) are re-attached from the in-code definition by state id;
  - if absent / malformed / loader error → returns the existing in-code `states()` array unchanged
    (fallback).
- `states()` / `getState()` route through the new `transitionTable()` so every existing consumer
  (`OnboardingChatDirector`, `AiChatController`) transparently gets the corpus-backed table when
  present and the in-code table otherwise — **no consumer signature changes**.
- Golden-master test proving the `.md`-loaded extracted subset deep-equals the in-code extracted
  subset, plus the full onboarding test suite staying green (§7).

### Out of scope / explicitly NOT touched

- The PHP **callable bodies** — `nextFromPathChoice`, `nextFromPersonal`, `nextFromEmployment`,
  `nextFromDependants`, `nextFromEmploymentMore`, `nextFromExpenditureReview`,
  `nextFromCampaignIntro`, `nextFromSpouseWork`, `nextFromAddMore`, every `build*Prompt`, every
  `skipIf*`, `matchBubble`, `interpolate`, `resolvePromptText`, `getNextStateId`, `applySkipRules`.
  These are code and stay byte-identical in PHP.
- The `skip_if` field of the table (array callable `[self::class, 'method']`). It is not a string
  token today and converting it to one is a behaviour-adjacent refactor; recorded in §10 deferred.
- Onboarding **behaviour** — no change to which state follows which, what prompts say, what bubbles
  show, what tools fire, what gets written. The merged table must produce identical `getState()` /
  `getNextStateId()` / `applySkipRules()` results.
- `FynSystemPrompt::text()`, the assembled tool catalogue, the Two-Fyn dispatch / tool-gating.
  4d touches onboarding workflow data only; it does **not** add/remove/reorder any tool in either Fyn
  state (4b golden master stays green) and does not touch the read-only AdviceFyn contract.
- `FynContextAssembler` (4c's domain — `system_prompt_overlay` / `fca_block`). The `workflow` kind is
  **not** injected into any prompt; it is consumed only by `OnboardingStateMachine`.
- The `pointers/`, `tool_schema/`, `system_prompt_overlay/`, `fca_block/` corpus subsystems.
- Any new `workflow`-kind episode stamping / provenance (that is 4e's domain if ever wanted; 4d adds
  no collector — the workflow table is structural config, not per-turn prompt prose).

---

## 3. The extraction boundary — pure data vs. PHP code

The in-code `states()` table mixes pure data with PHP callables. The extraction boundary is drawn so
that **only fields that are JSON/YAML-representable and round-trip byte-stable** move to `.md`. The
rest are re-attached from PHP by state id.

### Fields that MOVE to `.md` (the extracted subset)

Per state, the **structural / static** fields:

| Field | Why it is pure data |
|-------|---------------------|
| `turn_type` | enum string (`bubbles` / `free_text` / `grouped_extract` / `delegated` / `terminal`) |
| `bubbles` | array of `{id, label}` (+ optional `description`) — already pure data, NO ICONS preserved |
| `capture_field` | string column name or `null` |
| `next` **only when it is a static string state id** | e.g. `STATE_JOURNEY_SELECTION → 'base_personal'`. A static string transition is the literal definition of a transition-table edge. |
| `layout` | string (`standard`) |
| `navigate_to` | string route |
| `retry_text` | static string |
| `prompt_text` **only when it is a static string** | the literal prompt for states whose prompt is not built by a callable |
| `skip_link` | `{label, color}` static data |
| `value_parser` | string method name (an opaque token naming an `OnboardingValueInterpreter` method) |
| `extraction_tool` | string tool name |
| `bubble_capture` | static array (`{tool, input_for_bubble}`) |

### Fields that STAY in PHP, re-attached by state id (the code subset)

| Field | Why it cannot move as data |
|-------|----------------------------|
| `next` when it is a **callable reference** (`Class::method`) | It names a branching function whose body is PHP. Stored in-code already as a `Class::method` **string**, so the `.md` *could* carry the same token — BUT the golden-master would then need the `.md` to reproduce the fully-qualified class string byte-for-byte, and the routing helper bodies still live in PHP. To keep the data file free of PHP namespaces and avoid a brittle FQCN-in-YAML coupling, **callable `next` stays attached from PHP**. The `.md` records `next: { branch: <method-name> }` purely as a **descriptive marker** (documentation of which branch helper applies), and the merge re-attaches the actual `Class::method` callable from the in-code table by state id. The golden master proves the merged table equals the in-code table. |
| `prompt_text` when it is a **callable** (`Class::buildXxxPrompt`) | PHP closure/string-callable that runs at turn time. The `.md` records `prompt_text: { builder: <method-name> }` as a descriptive marker; the merge re-attaches the in-code value. |
| `skip_if` (`[self::class, 'method']`) | Array callable; not a string token today. Re-attached from PHP unchanged; not represented in the `.md` at all (recorded in §10 deferred). |

### The merge rule (single source of truth, no drift)

`transitionTable()` builds the effective table as:

```
for each state id in the in-code states() table:
    base   = in-code state array            (authoritative for code fields + fallback)
    if corpus table present and has this state id:
        for each EXTRACTED data field present in the corpus state:
            overwrite base[field] with the corpus value
    # PHP-only fields (callable next, skip_if, callable prompt_text) are NEVER
    # overwritten from the corpus — they always come from the in-code table.
    effective[state id] = base
```

So the corpus is authoritative for the **data subset** and the in-code table is authoritative for the
**code subset**. With the shipped v1 `.md` (which is a faithful transcription of the current data), the
merged table is **deep-equal** to the in-code table — that is the golden master. The fallback (corpus
absent) returns the in-code table directly. This guarantees zero behaviour change in both modes.

A guard in the golden-master test (and optionally in `fyn:procedural:validate` extension, §8) asserts
the corpus state-id set **exactly** equals the in-code state-id set — a missing or extra state in the
`.md` is a hard error caught at test/validate time, never a silent onboarding gap.

---

## 4. Components & Files

### New

| File | Purpose |
|------|---------|
| `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` | The transition table in a fenced ```yaml``` block + the required frontmatter. Faithful transcription of the current extracted data subset. |
| `app/Services/Onboarding/OnboardingWorkflowTable.php` | Pure reader: `fromProcedure(Procedure $p): ?array` parses the fenced ```yaml``` block into the extracted-subset table; returns `null` on any error. No I/O beyond the passed-in `Procedure->body`. |
| `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` | The 4d hard gate: asserts the `.md`-loaded + merged table deep-equals the in-code `states()` table (ordering + values), and that the corpus state-id set equals the in-code state-id set. |
| `tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php` | Behavioural: parser degrades to `null` on malformed YAML / missing block / wrong shape; merge re-attaches PHP-only fields; fallback path returns the in-code table when the corpus procedure is absent. |

### Modified

| File | Change |
|------|--------|
| `app/Services/Onboarding/OnboardingStateMachine.php` | Add `transitionTable(): array` (corpus-load + merge + fallback) and route `states()` / `getState()` through it. Extract the current literal table into a new private `inCodeStates(): array` (verbatim move of the current `states()` body — **no value changes**) so the fallback and the merge base share one definition. Add a tiny dependency-resolution helper to fetch the bound `ProceduralCorpusLoader` (the machine is a static utility, so it resolves via `app(ProceduralCorpusLoader::class)` inside a try/catch, mirroring how other static helpers reach container services). |

### Consumed unchanged (4a substrate)

- `ProceduralCorpusLoader::load()` (never-throws runtime entry, bound singleton).
- `ProceduralCorpus::active('onboarding.workflow.fyn-onboarding', now)`.
- `Procedure` VO (`body`, `kind`, `module`, `version`, `active`, `effectiveOn()`).

---

## 5. Data Flow

```
OnboardingChatDirector / AiChatController
  → OnboardingStateMachine::getState($id)  /  ::states()
      → OnboardingStateMachine::transitionTable()
          → app(ProceduralCorpusLoader::class)->load()         (never throws)
          → corpus->active('onboarding.workflow.fyn-onboarding', now())
              → null  → return inCodeStates()                  (FALLBACK — corpus absent)
              → Procedure → OnboardingWorkflowTable::fromProcedure($proc)
                  → null  → return inCodeStates()               (FALLBACK — malformed)
                  → array → merge(inCodeStates(), corpusData)    (corpus wins on DATA fields;
                                                                  PHP-only fields kept from code)
                              → return merged table
      ← effective state array  (identical values whether corpus-backed or fallback)
  ← getState/getNextStateId/applySkipRules behave identically (callables resolved from PHP as today)
```

The corpus read happens behind `getState()` / `states()`. Because the table is small and the loader
already caches cross-request with a 60s mtime throttle, the per-call cost is a cache hit plus the merge;
the merge result MAY be memoised per-request inside `transitionTable()` (static request-scoped cache,
reset is unnecessary for a deterministic pure function of corpus+code). The plan fixes whether to
memoise; correctness does not depend on it.

---

## 6. Error Handling — degrade, never break onboarding

Identical discipline to the 4a loader and 4c assembler layers:

- `transitionTable()` wraps the entire corpus path in `try { … } catch (\Throwable $e) { report($e);
  return $this->inCodeStates(); }`. Any loader fault, missing procedure, YAML error, or shape
  mismatch falls back to the in-code table — onboarding always has a working state machine.
- `ProceduralCorpusLoader::load()` is itself never-throwing (4a), so the catch is defence-in-depth.
- `OnboardingWorkflowTable::fromProcedure()` returns `null` (never throws) on: missing fenced
  ```yaml``` block, non-array YAML, a state whose shape is wrong, or a corpus state-id set that does
  not match the in-code set. A `null` return triggers the fallback.
- The corpus state-id-set-equality check inside the merge means a `.md` that drifts from the code
  (state added/removed in one but not the other) **fails closed** to the in-code table at runtime and
  **fails the golden-master + validate gate** at CI — it can never ship a half-extracted onboarding
  flow.

---

## 7. Golden-Master Strategy (the zero-regression proof)

The hard gate is a **deep-equality** assertion (not a byte-hash of a prompt, because the table is a
PHP array with callables that are not serialisable). Two complementary assertions, both must be green:

### 7a. Merged table deep-equals in-code table

`OnboardingWorkflowTableGoldenMasterTest`:

- Loads the real shipped `.md` via `OnboardingWorkflowTable::fromProcedure()` using the active
  `onboarding.workflow.fyn-onboarding` procedure from the real corpus.
- Builds the **merged** table via `OnboardingStateMachine::transitionTable()`.
- Builds the **in-code** table via the (test-visible) `inCodeStates()` (exposed to the test via a
  reflection call or a `@internal` static, since `states()` now routes through the merge — the plan
  fixes the access mechanism; reflection on the private method is acceptable and keeps the API clean).
- Asserts the two are **deep-equal**, comparing the **extracted data subset field-by-field** plus
  asserting the **PHP-only fields are object-identical** (same callable string / same array callable)
  between merged and in-code. Ordering of states and of `bubbles` arrays is asserted (the directive
  requires deep-equal incl. ordering).
- Asserts `array_keys(merged) === array_keys(inCodeStates())` (state-id set + order).

Because callables (`Closure` / `Class::method` strings / `[class, method]` arrays) cannot be compared
by value across a YAML round-trip, the data subset is compared after **stripping the PHP-only keys**
from both sides, and the PHP-only keys are then asserted equal directly from the in-code side only
(they were never in the `.md`). This is the precise meaning of "the `.md`-loaded transition table
deep-equals the current hardcoded transitions" given the directive's own escape hatch: *the
cleanly-separable subset is what is compared for byte/value identity; the code subset is asserted
unchanged in PHP*.

### 7b. Full onboarding suite green (behaviour unchanged)

Run, and require green, the existing onboarding tests after the change (they exercise `getState`,
`getNextStateId`, `applySkipRules`, bubble matching, campaign branching, prompt resolution — i.e. the
real consumed behaviour, with the corpus-backed table active):

- `tests/Unit/Services/Onboarding/OnboardingStateMachineTest.php`
- `tests/Unit/Services/Onboarding/CampaignStateMachineBranchTest.php`
- `tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php`
- `tests/Unit/Services/Onboarding/CampaignBubbleCaptureTest.php`
- `tests/Unit/Services/Onboarding/OnboardingValueInterpreterTest.php`
- `tests/Unit/Services/Onboarding/OnboardingFactExtractorTest.php`
- any `tests/Feature` / `tests/Browser/scenarios` touching onboarding (`BS-01`, `BS-07`).

These pass with the corpus present (proving the merged table drives real onboarding identically) and
must also pass with the corpus temporarily absent (proving the fallback is behaviour-identical — the
plan adds one fallback-path test that removes/overrides the corpus path and re-asserts a representative
`getNextStateId` chain).

If 7a or 7b is ever red, the extraction changed behaviour — that is a bug; loop until the merged table
deep-equals the in-code table and the suite is green. If a clean extraction is genuinely impossible for
a field, **shrink the extracted subset** (move that field back to code-only, record it in §10) rather
than contorting the machine — prefer correctness + zero behaviour change over completeness, per the
directive.

---

## 8. Validation

- `php artisan fyn:procedural:validate` (4a deploy gate) already validates the new `workflow/onboarding`
  file: path↔frontmatter `kind`/`module` agreement (`workflow` / `onboarding`), exactly one active
  version per `procedure_id`, version ≥ 1, boolean `active`, non-empty body, effective-dating. The new
  `.md` must pass it. (The 4a validator does **not** parse the fenced ```yaml``` body — the body is
  opaque to it; the YAML-shape + state-set validation is enforced by `OnboardingWorkflowTable` and the
  golden-master test. The plan MAY add an optional `workflow`-aware body check to the validator, but it
  is not required for 4d and must not change the 4a validator's behaviour for other kinds.)
- `./vendor/bin/pint` on every changed/new PHP file before commit; must report `passed`. (Known Pint
  import-strip quirk: add import + first usage in a single Write.)
- Architecture suite stays green (`OnboardingWorkflowTable` is a final class, not an interface).

---

## 9. Testing

| Test | Type | Asserts |
|------|------|---------|
| `OnboardingWorkflowTableGoldenMasterTest` (new) | Unit | Merged `.md`-backed table deep-equals in-code `states()` (data subset value-identical incl. ordering; PHP-only fields unchanged); state-id set + order identical. **The 4d hard gate.** |
| `OnboardingWorkflowTableTest` (new) | Unit | Parser returns a valid table for the real `.md`; returns `null` on malformed YAML / missing fenced block / wrong shape / mismatched state set; merge re-attaches PHP-only fields by state id; fallback returns in-code table when corpus procedure absent. |
| `OnboardingStateMachineTest` (existing) | Unit | Stays green untouched — `getState`/`getNextStateId`/`applySkipRules`/`matchBubble` behave identically with the corpus-backed table. |
| `CampaignStateMachineBranchTest`, `CampaignBubbleCaptureTest`, `OnboardingChatDirectorFixesTest` (existing) | Unit | Stay green — campaign branching, bubble capture, director flow unchanged. |
| `FynProceduralValidateTest` (existing) | Feature | Stays green; the new `workflow/onboarding` file passes the 4a validator. |
| `ToolSchemaGoldenMasterTest` / `PromptOverlayGoldenMasterTest` (existing) | — | Stay green — 4d touches no tool catalogue and no prompt prose. |
| Full suite | — | Green before commit per TDD. |

TDD order: (1) write the shipped `.md` as a faithful transcription; (2) write
`OnboardingWorkflowTableGoldenMasterTest` against the not-yet-built `transitionTable()` → run red;
(3) implement `OnboardingWorkflowTable` + `transitionTable()` + `inCodeStates()` minimally → run green;
(4) write the behavioural / fallback / degrade tests → green; (5) run the full onboarding suite → green;
`pint`; commit with the required trailer.

---

## 10. Deferred (record only — do NOT move in 4d)

Recorded for CSJ; each is a separate, reviewed decision:

1. **`skip_if` predicates** — array callables `[self::class, 'skipIf*']`. Not string tokens today;
   representing them as data would require a registry of named predicates and a behaviour-adjacent
   refactor. Stay in PHP, re-attached by state id. Candidate for a future "named predicate registry"
   if more workflows are externalised.
2. **Branching `next` callables** (`nextFrom*`) and **callable `prompt_text` builders** (`build*Prompt`)
   — the routing/prompt-building *logic* is genuinely PHP. 4d records each in the `.md` as a
   descriptive marker (`next: {branch: …}` / `prompt_text: {builder: …}`) for human readability and
   re-attaches the real callable from PHP. Turning the branch logic itself into declarative data (e.g.
   a condition DSL over `user.*` columns) is a much larger, separate design — explicitly NOT 4d.
3. **Other Fyn workflows** — only the single Fyn onboarding flow has a state machine today. If other
   flows (e.g. a future advice wizard) gain state machines, they each get their own
   `workflow/{module}/{flow}.vN.md`. None exist now.
4. **A `workflow`-aware body validator in `fyn:procedural:validate`** — optional hardening so a drifted
   `.md` is caught at deploy as well as at test time. Nice-to-have; the golden-master + runtime
   state-set-equality fail-closed already cover correctness.

---

## 11. Done-when

- [ ] `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` exists, passes
      `fyn:procedural:validate`, and is a faithful transcription of the extracted data subset.
- [ ] `OnboardingWorkflowTable::fromProcedure()` parses the fenced ```yaml``` block, degrades to `null`
      on any error.
- [ ] `OnboardingStateMachine::transitionTable()` merges corpus-data over in-code base (corpus wins on
      DATA fields; PHP-only fields always from code), falls back to `inCodeStates()` on absent/malformed
      corpus, and never throws.
- [ ] `states()` / `getState()` route through `transitionTable()`; no consumer signature changes.
- [ ] `OnboardingWorkflowTableGoldenMasterTest` green — merged table deep-equals in-code table
      (data subset value-identical incl. ordering; PHP-only fields unchanged; state-id set + order
      identical). **Hard gate.**
- [ ] `OnboardingWorkflowTableTest` green — parser degrade, merge re-attach, fallback all proven.
- [ ] Full onboarding test suite green with corpus present AND with corpus absent (fallback).
- [ ] `FynProceduralValidateTest` green; new `.md` passes the validator.
- [ ] No tool-catalogue change in either Fyn state (4b golden master green); 4c overlay golden master
      green; `FynSystemPromptTest` green; Two-Fyn contract intact.
- [ ] Full Pest suite green; `pint` passed on every changed file.
- [ ] NO ICONS: the new `.md` carries no emoji/Unicode-as-icons/glyphs (bubbles are `{id, label}`
      only — Rule #16); no user-facing scores (Rule #13). The 4d admin viewer is **4f**, not 4d — no
      Vue is touched in 4d.
```