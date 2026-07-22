# PRD — CoALA Phase 4: Procedural Memory and Version Pinning

**Project:** Fyn brain rewire — Phase 4 (Externalise procedures)
**Owner:** CSJ
**Status:** Draft — codebase audit completed during plan revisions v0.1 → v0.4
**Date:** 27 May 2026
**Spec & Plan:** `/Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md` (v0.4)
**Canonical contract:** `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/00-canonical.md`
**Codebase audit:** Performed during plan v0.1 → v0.4 revision cycle

> **AMENDED by plan v0.5 (2026-06-01) — the pointer registry is the heart of procedural memory.** Procedural memory's core is now the **pointer registry**: typed fetch-skills (`{topic, source ∈ md_fact|tax_config|model_query|service_call|engine_run, fetch, effective_dating}`) that route Fyn to the *live* source for any data with an authoritative owner, so nothing is duplicated or frozen. The overlays / workflows / tool-schemas described in this PRD are the *rest* of procedural memory; the registry is its centre. A pointer fetches — it never widens write permission. See `fynla-coala-implementation-plan.md` → "v0.5 amendment".

---

## 1. Context & Why

### Problem

Fyn's "how to" knowledge — system prompt overlays, workflow definitions, tool schemas, FCA process blocks — currently lives entirely in PHP code:

- **Static prompt:** `app/Services/AI/Fyn/FynSystemPrompt.php:20` — single `static text(): string` heredoc. Designed for Anthropic prefix-cache byte-invariance.
- **Modular fragments:** `app/Services/AI/Prompts/CoreIdentity.php`, `ComplianceRules.php`, `FcaProcessInstructions.php`, `QueryKnowledge.php`, `EmptyDataGuard.php`, `UserContentSanitiser.php` — all class constants.
- **Onboarding state machine:** `app/Services/Onboarding/OnboardingStateMachine.php` — state-transition logic and data interleaved.
- **Tool catalogue:** `app/Services/AI/AiToolDefinitions.php` — flat PHP arrays of function definitions, grouped by category (navigation, analysis, tax, plan-generation, billing, what-if, data-creation, modification, profile, expenditure). Plus parallel xAI shape at `XaiToolDefinitions.php`.

Every change requires a code PR and a deploy. Product teams cannot edit overlay content, workflow state-transition data, or tool descriptions without engineering involvement.

CoALA Section 4.1 calls this out: *"procedural memory must be initialized by the designer with proper code to bootstrap the agent. Finally, while learning new actions by writing to procedural memory is possible, it is significantly riskier than writing to episodic or semantic memory."* The risk is real — procedural memory edits can introduce bugs or subvert designer intent. But that risk argues for **gated, reviewable edits**, not for **edits requiring deploy**.

The deploy-coupling has two specific costs:

1. **Velocity.** Wording tweaks to FCA blocks, house-view stance, onboarding step prompts wait for code review + deploy queue.
2. **Auditability.** Today a change to a prompt is a git diff on `app/Services/AI/Prompts/*.php`. There's no per-procedure version record an episode can reference. We can't say "this turn was produced by `fyn.advice.fca_block.suitability.v1.0.3`" because version doesn't exist as a concept.

### Business case

- **Product velocity for non-static content.** Overlays, FCA blocks, house-view text, workflow step prompts, tool descriptions can ship via PR against the corpus without an engineering deploy. Engineering still reviews via CODEOWNERS, but the change set is smaller and the deploy step is content-only.
- **Per-episode procedural attribution.** Phase 5's per-action cost telemetry includes `procedural_version`. Phase 2's episode row carries `procedural_version`. Without Phase 4, the value is a static "0.0.0" placeholder — useless for attribution.
- **A/B-able procedure changes.** Two versions of the same `procedure_id` can coexist; `active: true` toggles which is live. Combined with the existing `FYN_PROMPT_ARCH` flag, this gives us a real platform for safe procedure experimentation.

### Strategic fit

Reuses the `.md` corpus + loader + frontmatter validation pattern established in Phase 1. Touches every Fyn turn (via the procedural version stamped on the episode), but with **no end-user UX change**.

**Critical constraint:** The static `FynSystemPrompt::text()` heredoc does **not** move. It stays in PHP as a byte-identical heredoc to preserve Anthropic prefix-cache hit rate (canonical contract `00-canonical.md`, MEMORY.md `reference_unified_prompt_has_no_billing_layer.md`). Only overlays / workflows / tool schemas / FCA blocks move to `.md`.

---

## 2. Target Persona

**Infrastructure — indirectly benefits all personas.** No end-user UX change.

**Primary internal beneficiary:** Product / content authors editing FCA process blocks, onboarding wording, tool descriptions, house-view overlays — they ship via PR not deploy.

**Secondary internal beneficiary:** Compliance — gets per-procedure-version attribution on every Fyn turn.

**Tertiary:** Engineers — gets cleaner separation between procedure data and procedure code, enabling Phase 6 procedural-learning patterns.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| Overlay / workflow-data / tool-schema / fca-block changes shipped without code deploys | 0% | 100% of in-scope changes | Git log of `app/Resources/Memory/Procedural/` vs `app/Services/AI/Prompts/*.php` and `app/Services/AI/AiToolDefinitions.php` |
| `procedural_version` populated per episode | 0% (column doesn't exist post-Phase-2 yet) | 100% post-cutover | `SELECT COUNT(*) WHERE procedural_version IS NULL` |
| Boot-time fail-closed on duplicate active `procedure_id` | n/a | 100% — application refuses to start | Validate in CI via `php artisan fyn:procedural:validate` |
| Static prompt byte-identical guarantee | Maintained (CI hash check from Phase 3) | Maintained | CI hash check |
| Hot-reload latency (mtime poll → reload) | n/a | < 60s in steady state | Measured by introducing a procedure version bump in staging and observing time-to-effect |
| Prefix-cache hit rate | TBD: baseline established in Phase 5 | No regression | Phase 5 telemetry |

---

## 4. User Stories & Scenarios

### User stories

- As a **content author**, I want to amend an FCA block's wording in a markdown file with frontmatter and ship via PR so that I can update Fyn's guidance without engineering deploys.
- As a **product designer**, I want to author a new onboarding workflow's state-transition table in YAML inside a `.md` file so that I can iterate on the flow without changing PHP code.
- As **Compliance**, I want every Fyn turn's episode to reference the exact procedure versions that contributed so that I can demonstrate procedural provenance to a regulator.
- As an **engineer**, I want adding a new tool to be a corpus PR (with a `.md` per tool containing the JSON schema in a fenced block) so that the catalogue stays diffable per-tool rather than as a 1000-line PHP array.

### Key scenarios

**Scenario 1 — Authoring an FCA block amendment:**

1. Content author edits `app/Resources/Memory/Procedural/fca_block/shared/suitability.v1.0.4.md`, sets `active: true` on the new file and updates the previous version's file to `active: false`.
2. Opens PR. CI runs `php artisan fyn:procedural:validate` which parses frontmatter, validates mandatory fields (`procedure_id`, `kind`, `module`, `version`, `active`, `effective_from`), and confirms exactly one `active: true` per `procedure_id`.
3. CSJ reviews PR, merges to `dev`.
4. Deploy to csjones.co. Hot-reload (60s mtime poll) picks up the new version within a minute. No application restart needed.
5. Next Fyn turn that touches the suitability FCA block uses v1.0.4. Episode's `procedural_version` field records `fyn.shared.fca_block.suitability.v1.0.4`.

**Scenario 2 — Adding a new tool:**

1. Engineer authors `app/Resources/Memory/Procedural/tool_schema/shared/new_tool_name.v1.0.0.md`. Body contains the tool JSON schema in a fenced ```json block. Frontmatter sets `procedure_id`, `module` (which Fyn module the tool belongs to), `version`, `active: true`.
2. CI validates the JSON schema parses, the tool name is unique, the schema matches whatever shape `AiToolDefinitions::toolForProvider()` expects.
3. The tool is also added to whichever `session_mode` allowlist it belongs in (Phase 5's `ground` surface gate). This part stays in code — the safety boundary doesn't move into the corpus.
4. Merge + deploy. Boot loader assembles the tool catalogue from `.md` files, applies the provider-format wrapping (Anthropic vs xAI) at assembly time, hands the assembled catalogue to `CoordinatingAgent`.

**Scenario 3 — Editing an onboarding workflow's state-transition data:**

1. Product designer edits `app/Resources/Memory/Procedural/workflow/onboarding/savings_capture.v1.2.0.md`. Body contains the state-transition table in a fenced ```yaml block.
2. `OnboardingStateMachine` code (in PHP) is unchanged — it consumes whichever transition data is loaded from the corpus at boot.
3. Hot-reload picks up the new version. Existing in-flight onboarding flows: they were pinned to the previous version at session start and keep using it (per `WorkingMemory.procedural_version` pinning). New sessions get v1.2.0.

**Scenario 4 — Hot reload:**

1. Boot loads procedural corpus into singleton. Records each file's mtime.
2. Every 60s, `ProceduralCorpusLoader` checks for mtime changes.
3. On detected change: parse all candidate files into a new corpus instance. If validation passes (no duplicate active `procedure_id`, no malformed frontmatter), atomically swap the singleton reference.
4. On validation failure: log error, alert, keep the old corpus active (fail-closed).

**Scenario 5 — `FynSystemPrompt::text()` remains static:**

1. CI hash check on `app/Services/AI/Fyn/FynSystemPrompt.php` blocks any PR that modifies the file outside an explicit prompt-revision sub-task.
2. A Phase 4 PR that tries to move the heredoc into the corpus FAILS CI.
3. Documented constraint: the static prompt is in code for prefix-cache. Procedural memory holds the OVERLAYS the assembler adds per turn, not the static layer.

**Unhappy path — boot fails on duplicate active `procedure_id`:**

1. Engineer accidentally leaves `active: true` on both v1.0.3 and v1.0.4 of the same `procedure_id`.
2. CI validation catches before merge.
3. If somehow it gets past CI: boot-time `ProceduralCorpusLoader` throws fatal error.
4. Application refuses to start. No silent acceptance of ambiguous procedure state.

---

## 5. Functional Requirements

### Must-have

- **FR-M1:** Stand up the corpus directory `app/Resources/Memory/Procedural/{system_prompt_overlay|workflow|tool_schema|fca_block}/{advice|onboarding|shared}/*.md`. Git-tracked, deploy-bundled. _Touches: filesystem; new directory tree._
- **FR-M2:** `ProceduralCorpusLoader` service parses every `.md` file in the corpus at boot, validates YAML frontmatter (mandatory: `procedure_id`, `kind`, `module`, `version`, `active`, `effective_from`), indexes by `procedure_id`. Fail-closed at boot on duplicate active `procedure_id` or malformed frontmatter. _Touches: new service `app/Services/AI/Memory/Procedural/ProceduralCorpusLoader.php`._
- **FR-M3:** Hot-reload via mtime polling every 60s. Atomic singleton swap on successful validation; keep old corpus active on failure. _Touches: scheduled task or background process._
- **FR-M4:** Migrate `app/Services/AI/AiToolDefinitions.php` tool definitions into `tool_schema` `.md` files — one `.md` per tool, JSON schema in a fenced block. Boot loader assembles them into the in-memory catalogue. Provider-format wrapping (Anthropic `input_schema` vs xAI `function`) happens at assembly time, not in the `.md`. _Touches: new `.md` files; updates to `app/Agents/CoordinatingAgent.php` tool-catalogue consumption; `AiToolDefinitions.php` becomes a thin shim during cutover, deleted after._
- **FR-M5:** Migrate per-tier overlays + FCA blocks + house-view content into `system_prompt_overlay` / `fca_block` `.md` files. Bodies are plain markdown / prose. The reading path is `FynContextAssembler::build()` retrieving overlays from the corpus when assembling the per-turn `<context>` block. _Touches: `.md` files; updates to `FynContextAssembler` to read from corpus instead of `app/Services/AI/Prompts/*.php` constants; legacy constant files removed after cutover._
- **FR-M6:** Formalise `OnboardingStateMachine` config as `workflow/onboarding/{flow_name}.v{N}.md` files containing the state-transition table in a fenced YAML block. The machine *code* stays in PHP. Only the transition *data* moves. _Touches: new `.md` files; refactor of `OnboardingStateMachine` to load transitions from the corpus._
- **FR-M7:** Stamp `procedural_version` on every episode (Phase 2 column). When multiple procedures contribute to a turn, store as a JSON array of `procedure_id@version`. _Touches: `EpisodeBlobWriter` (Phase 2 service); `FynLoop` (Phase 5)._
- **FR-M8:** Procedure-id naming convention enforced by validator: module-prefixed, dotted, SemVer-suffixed (`fyn.{advice|onboarding|shared}.{kind}.{name}.v{MAJOR}.{MINOR}.{PATCH}`). Examples: `fyn.advice.system_prompt_overlay.billing.v1.0.3`, `fyn.shared.fca_block.suitability.v1.0.3`, `fyn.shared.tool_schema.create_savings_account.v3.0.0`. _Touches: validator in `ProceduralCorpusLoader`._
- **FR-M9:** CI job `php artisan fyn:procedural:validate` parses every corpus file, validates schema and uniqueness. Runs on every PR. _Touches: GitHub Actions workflow + new artisan command._
- **FR-M10:** Static `FynSystemPrompt::text()` heredoc remains byte-identical. CI hash check on the file. Block any PR that modifies it outside an explicit prompt-revision sub-task. _Touches: CI workflow (extension of Phase 3's check if shipped, else new)._
- **FR-M11:** Admin UI for browsing procedures read-only (Phase 4 — no editing). Lists procedures by kind / module / active status. Detail view renders frontmatter as a header table and body as markdown. Wraps in `AppLayout`. _Touches: new `resources/js/views/Admin/ProceduralCorpusViewer.vue` + supporting controller._

### Should-have

- **FR-S1:** Validation rule: an `active: true` procedure must have its `effective_from` timestamp in the past or present. Future-dated `active: true` rows fail boot. _Touches: validator._
- **FR-S2:** Procedure-version history view in admin — for a given `procedure_id`, show all on-disk versions, their `active` status, and when they were authored (from git log). _Touches: admin view._

### Nice-to-have

- **FR-N1:** Schema migration tool — given an existing PHP-resident overlay or tool definition, scaffold the corresponding `.md` file with frontmatter pre-filled. Useful during Phase 4 migration, less useful after. _Touches: one-off artisan command._
- **FR-N2:** Per-tenant procedural overlays. Out of scope for v0.4 plan — Fynla is single-tenant. Flagged here as a future capability the `.md` schema accommodates if needed. _Touches: future._

---

## 6. User Flow & UX/Design

### Engineering flow (no end-user UX)

```
Deploy
  └─ Code update
  └─ Procedural corpus update (.md files)

Boot
  └─ ProceduralCorpusLoader walks corpus directory
  └─ For each .md: parse frontmatter, validate, index by procedure_id
  └─ Fail-closed on duplicate active procedure_id or malformed frontmatter
  └─ Indexed by:
       - kind (overlay / workflow / tool_schema / fca_block)
       - module (advice / onboarding / shared)
       - procedure_id (active version)

Steady state (every 60s)
  └─ Check corpus directory mtimes
  └─ On change: parse new candidate corpus
       └─ Validate
            └─ Pass: atomic singleton swap
            └─ Fail: log + alert, keep old corpus

Per Fyn turn
  └─ WorkingMemoryBuilder pins procedural_version at session start
  └─ FynContextAssembler::build() reads active overlay + fca_block
     for the current module from the procedural corpus (pinned version)
  └─ Tool catalogue assembled from tool_schema corpus, provider-wrapped
  └─ OnboardingStateMachine reads transition data from workflow corpus
  └─ Episode write stamps procedural_version JSON array
```

### Admin UI (FR-M11)

- **Design system:** `fynlaDesignGuide.md` v1.3.0. Standard `AppLayout` chrome. Category and module filters use horizon nav tokens. Procedure rows use standard `.card` variant. `active: true` highlighted with `spring-*` badge; `active: false` (historical) uses `neutral-*` badge.
- **Reusable components:** standard admin table patterns, expand/collapse for detail.
- **New components:** `ProceduralCorpusViewer.vue` (list), `ProcedureDetail.vue` (detail, renders frontmatter table + markdown body).
- **No editing in Phase 4.** Read-only. Editing path remains PR-based.
- **Accessibility:** Keyboard navigation through the list, ARIA expand/collapse.
- **No icons** — admin list/detail UI, no functional necessity.

---

## 7. Out of Scope

- Wiki-style in-app editing of procedures. Phase 4 is read-only admin UI. Editing remains PR-gated until Phase 6 ships and proves the loop is stable — and even then, never for regulatory content (`fca_block`).
- Moving `FynSystemPrompt::text()` to the corpus. Stays as a PHP heredoc for prefix-cache byte-invariance.
- Moving the `Onboarding StateMachine` *code* to the corpus. Only the transition *data* moves.
- Per-user procedure overlays. Procedures are global.
- Hot-reload sub-60s. The 60s mtime poll is the contract; sub-second reload not justified.
- Automatic semantic versioning bumps. Authors set `version` manually in frontmatter. SemVer convention enforced by validator but not auto-computed.
- Procedure rollback UI. Rollback is a git revert + redeploy or a manual `active: false` flip on the live version. No bespoke admin action.

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Prefix-cache hit-rate regression when overlays move from heredocs to corpus-loaded strings | Medium | High | Strict invariant: corpus content lands ONLY in the per-turn `<context>` block, never in `FynSystemPrompt::text()`. CI check on the static prompt. Phase 5 telemetry as ongoing guardrail. |
| Boot failure on malformed corpus blocks deploy | Low | High | `php artisan fyn:procedural:validate` runs in CI on every PR. Same validator as boot. Deploy script also runs it as a pre-start gate. |
| Hot-reload mid-turn surprises a turn that's pinned to the old version | Low | Low | `WorkingMemory.procedural_version` pins at session start. In-flight turns finish on their pinned version; only new sessions pick up the change. |
| Tool catalogue migration is error-prone (1000-line PHP array → ~50–80 individual files) | Medium | Medium | Migrate in batches by tool category (navigation tools first — simplest — then analysis, then writes). Test parity by snapshotting the assembled catalogue from PHP vs corpus and diffing. |
| `OnboardingStateMachine` state-transition data layout doesn't map cleanly to `.md` + fenced YAML | Medium | Medium | Prototype the mapping first; if YAML expressivity is insufficient, fall back to keeping transitions in PHP and just version the prompt-text portion. |
| `FYN_PROMPT_ARCH=legacy` rollback path breaks when overlays leave PHP heredocs | Medium | Medium | Legacy `AdvicePromptBuilder` and `OnboardingPromptBuilder` keep their existing PHP-resident overlays untouched. The legacy path doesn't read from the corpus. CSJ directive (2026-05-18): legacy is permanent. Confirm legacy still builds with Phase 4 changes. |
| PR review velocity bottleneck for high-volume procedure refresh | Medium | Low | Same trade-off as Phase 1's semantic corpus. CSJ as sole reviewer per CODEOWNERS. Revisit if it becomes a real problem. |

### Technical dependencies

- Pattern shared with Phase 1 — `.md` + frontmatter loader. If Phase 1 ships first, Phase 4 reuses the parsing/validation utilities.
- `FynContextAssembler` — integration point for overlays. Phase 4 modifies its read path.
- `FynSystemPrompt::text()` — explicit invariant: byte-identical.
- `OnboardingStateMachine` — refactor to read transitions from the corpus.
- `CoordinatingAgent` — tool catalogue consumption changes from PHP-array import to corpus-driven assembly.
- `FYN_PROMPT_ARCH=legacy` — must continue to work post-Phase-4. CSJ directive (`00-canonical.md`).

### Sequencing dependencies

- **Blocked by:** Phase 2 (provides `procedural_version` column on the episode row). Phase 1 strongly recommended first (establishes the `.md` corpus pattern).
- **Blocks:** Phase 5 — decision loop needs the procedural catalogue to be assemblable from the corpus so `procedure_version` attribution on the episode is meaningful.
- **Recommended order:** 1 → 2 → 3 → **4** → 5 → 6.

### Residual concerns from codebase audit

- **xAI tool schema parity.** `AiToolDefinitions.php` has a sibling `XaiToolDefinitions.php`. The `.md` corpus uses a single schema per tool; provider-format wrapping happens at assembly time. Confirm the assembly layer handles both Anthropic and xAI shapes during migration; provider-specific quirks (e.g. xAI's `function` wrapper vs Anthropic's `input_schema`) must be encoded in the assembly logic, not duplicated in the corpus.
- **Tool category metadata.** `AiToolDefinitions.php` groups tools by category (navigation, analysis, tax, billing, what-if, data-creation, modification, profile, expenditure, campaign). Decide whether category lives in frontmatter or is implicit in the `module` field. Recommendation: `category` as an explicit frontmatter field; `module` indicates ownership (which Fyn module the tool primarily serves).
- **`HasAiGuardrails` tier-aware token budgets** (`app/Traits/HasAiGuardrails.php:83-167`) — runtime configuration via `TierConfigurationStore`. Not in scope for Phase 4 procedural memory. Tier config stays as data on the user/subscription, not as a procedure.
- **Preview-mode tool stripping** — today, `AiToolDefinitions` strips writes via the `! $isPreviewMode` branch. After migration, the assembly layer must apply the same preview-mode filter. Confirm: this is an assembly-time filter, not a corpus concept (the corpus has the tool, the assembler decides whether to expose it).

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 27 May 2026 | Initial draft from CoALA v0.4 plan Phase 4 | prd-writer skill |
