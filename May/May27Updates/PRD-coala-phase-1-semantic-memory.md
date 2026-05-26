# PRD — CoALA Phase 1: Semantic Memory and Retrieval

**Project:** Fyn brain rewire — Phase 1 (Foundation)
**Owner:** CSJ
**Status:** Draft — codebase audit completed during plan revisions v0.1 → v0.4 (no separate audit pass)
**Date:** 27 May 2026
**Spec & Plan:** `/Users/CSJ/Desktop/fynla/fynla-coala-implementation-plan.md` (v0.4)
**Canonical contract:** `/Users/CSJ/Desktop/fynla/April/April24Updates/spec/00-canonical.md`
**Codebase audit:** Performed during plan v0.1 → v0.4 revision cycle — see Risks & Dependencies for residual concerns

---

## 1. Context & Why

### Problem

Fyn's authoritative knowledge about UK tax rules, FCA handbook content, and product reference data lives in two incompatible places today:

- **Numeric tax values** (bands, allowances, thresholds) live in `tax_configurations.config_data` JSON and are accessed via `TaxConfigService`. This works.
- **Narrative content** (FCA handbook rules, "house view" guidance, product treatment text) lives in PHP class constants — `app/Services/AI/Prompts/ComplianceRules.php`, `FcaProcessInstructions.php`, `CoreIdentity.php`, `QueryKnowledge.php`, and inline in the `FynSystemPrompt::text()` heredoc. Every edit requires a code PR and a deploy.

There is **no vector store, no embeddings, and no effective-date filter** on narrative content. The codebase has zero hits for `pgvector|pinecone|weaviate|chromadb|embedding`. The active `tax_configurations` row is selected by an `is_active` boolean flag, not by date — so a session that starts at 23:59 on 5 April receives whichever tax year was flagged active at request time, with no "as-of-date" API.

This is the difference between *RAG that mostly works* and *RAG that's safe to advise from*. Fyn currently has neither.

### Business case

- **Regulatory.** FCA suitability rules require traceable advice. If Fyn cites an FCA rule, we must be able to demonstrate which rule, at which effective date, sourced from which canonical document. Today's PHP-heredoc approach provides none of that.
- **Product velocity.** Every change to FCA wording, house-view stance, or product narrative requires an engineering PR and a deploy. Product cannot edit the corpus directly. This bottlenecks every content refresh — most acutely at budget day and at tax-year rollover.
- **Quality.** Without effective-date filtering, Fyn can quote rules that have since been updated. Without retrieval, the prompt has to carry every fact Fyn might need on every turn — bloating context and pushing real information out.

### Strategic fit

Foundational. Phases 2–6 of the CoALA plan all depend on the semantic memory module existing. Phase 6's "learning actions" feed proposed amendments into semantic memory. Phase 5's per-action cost attribution measures retrieval cost as a category. Phase 4's procedural store reuses the `.md` + frontmatter loader pattern this phase establishes.

Touches: Coordination module (AI chat surface), every advice-mode module (Protection / Savings / Investment / Retirement / Estate / Goals) by virtue of being the knowledge layer they query.

---

## 2. Target Persona

**Infrastructure — indirectly benefits all personas.** No end-user visible change in Phase 1. Indirect benefit: every persona receives advice grounded in versioned, effective-date-filtered content rather than baked-in-code heredocs.

**Primary internal beneficiary:** Product / content authors who need to edit FCA narrative or house-view guidance without an engineering PR.

**Secondary internal beneficiary:** Compliance — auditable provenance for every narrative claim Fyn makes.

---

## 3. Success Metrics (KPIs)

| Metric | Baseline | Target | Measurement |
|--------|----------|--------|-------------|
| % of Fyn-output FCA citations sourced from semantic memory | 0% (all from PHP heredocs) | 100% post-cutover | Audit log `ai_audit_events` cross-referenced with `semantic_snapshot_id` on the episode row |
| Effective-date filter coverage on `tax` / `fca` / `product` facts | 0% (no `valid_from` column exists) | 100% of files have mandatory `valid_from` frontmatter | Boot-time validation; fail-closed on missing field |
| Semantic retrieval p95 latency | n/a (no retriever exists) | < 50ms in-memory hybrid scoring | Per-call instrumentation, reported in Phase 5 telemetry once that ships |
| Prefix-cache hit rate (Anthropic) | TBD: measure pre-Phase-1 baseline | No regression vs baseline | Phase 5 telemetry, gated as a release blocker for Phase 1 cutover |
| FCA/product narrative changes shipped via PR (vs full code deploy) | 0% | 100% of post-cutover edits | Git log of `app/Resources/Memory/Semantic/` vs `app/Services/AI/Prompts/` |
| Confabulation rate (Fyn citing FCA rules without `semantic_snapshot_id` match) | Unmeasured today | Measured + reduce quarter-over-quarter | Random sample of advice logs, manual review (initial baseline ~30 conversations) |

---

## 4. User Stories & Scenarios

### User stories

- As a **content author**, I want to amend an FCA rule in a markdown file and ship via PR so that I can update Fyn's guidance without engineering involvement on the body text.
- As a **compliance reviewer**, I want every FCA-derived claim Fyn makes to reference a versioned canonical document so that I can demonstrate the provenance trail to the regulator.
- As an **engineer maintaining `TaxConfigService`**, I want semantic memory's `tax` category to be narrative-only (never numeric) so that the single-source-of-truth for tax bands stays intact.
- As **Fyn** (the agent), I want to retrieve only facts that are effective on the session's pinned `tax_year` so that I never quote an expired rule.

### Key scenarios

**Scenario 1 — Authoring an FCA rule amendment:**

1. Content author edits `app/Resources/Memory/Semantic/fca/suitability-cobs-9-2-1.md`, updates the body and bumps `version: 1.0.4` in the frontmatter.
2. Opens PR. CI parses frontmatter and validates: `fact_id` present and unique, `category` is one of `tax|allowance|fca|product|house_view`, `valid_from` present, `version` is valid SemVer.
3. CSJ reviews PR, merges to `dev`.
4. Deploy to csjones.co triggers `php artisan fyn:semantic:reindex` automatically.
5. Next Fyn turn that queries the affected topic retrieves the new version. Old version remains on disk for replay.

**Scenario 2 — Fyn answering an advice question that needs an FCA rule:**

1. User asks "what does FCA say about pension transfers?"
2. `FynContextAssembler::build()` calls `SemanticMemory::retrieve("pension transfer", effective_date: session.tax_year_start, categories: ['fca', 'product'])`.
3. Retriever applies date filter, then hybrid sparse+dense scoring, returns top N facts.
4. Facts inserted into `<context>` block (per-turn assembler, never the static prompt).
5. Fyn answers with the rule text and citation. `semantic_snapshot_id` (SHA-256 over the returned `(fact_id, version)` tuples) recorded on the `ai_messages` row.
6. Audit verification can later resolve the snapshot back to the exact `.md` versions that produced the answer.

**Scenario 3 — Tax-year rollover edge case:**

1. New tax year takes effect 6 April. Content author has already authored new-year FCA narrative with `valid_from: 2027-04-06` and updated the previous version's `valid_to: 2027-04-05`.
2. Session opened at 23:59 on 5 April. `WorkingMemory.tax_year` pinned to 2026-27 at session start.
3. Session continues past midnight. Fyn keeps using 2026-27 facts because the session pinned them — by design, to preserve consistency within a single advisor conversation.
4. Next session opened on 6 April pins to 2027-28 and retrieves the new content.

**Unhappy path — boot fails on duplicate `fact_id`:**

1. Engineer accidentally copies an `.md` file without changing `fact_id`.
2. Boot-time `SemanticCorpusLoader` detects duplicate, throws fatal error.
3. Application refuses to start. No silent merge of conflicting facts.

---

## 5. Functional Requirements

### Must-have

- **FR-M1:** Stand up the corpus directory `app/Resources/Memory/Semantic/{tax|allowance|fca|product|house_view}/*.md`, git-tracked and deploy-bundled. _Touches: filesystem; new directory tree._
- **FR-M2:** `SemanticCorpusLoader` service parses every `.md` file in the corpus at boot, validates YAML frontmatter (mandatory: `fact_id`, `category`, `source`, `version`; `valid_from` mandatory for `tax|allowance|fca`), indexes by `fact_id` into a singleton service. Fail-closed at boot on duplicate `fact_id` or malformed frontmatter. _Touches: new service `app/Services/AI/Memory/Semantic/SemanticCorpusLoader.php`._
- **FR-M3:** `SemanticRetriever` service implementing `retrieve(string $query, Carbon $effectiveDate, array $categories): SemanticFactCollection`. Applies effective-date filter (`valid_from <= effectiveDate AND (valid_to IS NULL OR valid_to >= effectiveDate)`) BEFORE ranking. Hybrid scoring: sparse (keyword over title + body) + dense (cosine over embeddings). _Touches: new service `app/Services/AI/Memory/Semantic/SemanticRetriever.php`._
- **FR-M4:** `php artisan fyn:semantic:reindex` artisan command. Generates embeddings via the chosen provider (TBD: CSJ) for every fact, writes `storage/app/memory/semantic/embeddings.json` keyed by `fact_id`. Idempotent. Runs at deploy time and on demand. _Touches: new command `app/Console/Commands/FynSemanticReindex.php`._
- **FR-M5:** Seed the `fca` corpus by chunking and migrating the bodies of `app/Services/AI/Prompts/ComplianceRules.php`, `FcaProcessInstructions.php`, `CoreIdentity.php`, `QueryKnowledge.php` into one `.md` per logical rule with citations. Estimated 30–80 files. _Touches: removes constants from those PHP files, adds new corpus files._
- **FR-M6:** Seed the `product` corpus by migrating `tax_product_reference` table rows into `.md` files (one per `product_category × tax_aspect`), adding `valid_from` / `valid_to` frontmatter. Estimated 50–150 files. The DB table remains as source-of-record for the migration's input data but consumers move to the corpus retriever. _Touches: new corpus files; eventual deprecation of direct `tax_product_reference` reads from PHP heredocs._
- **FR-M7:** Stand up `house_view` corpus directory empty, ready for content authoring after Phase 1 ships. _Touches: empty directory + README explaining authoring conventions._
- **FR-M8:** `tax` retrieval shim — when `categories` includes `tax`, the retriever returns narrative facts only. The shim NEVER duplicates `TaxConfigService` numeric values into corpus files. Validation: a `tax`-category `.md` containing a `£` symbol or a numeric monetary value triggers boot-time warning. _Touches: validator in `SemanticCorpusLoader`._
- **FR-M9:** Wire `FynContextAssembler` to call `SemanticRetriever::retrieve()` for FCA, product, and house-view content. Replaces the static heredoc paths in `app/Services/AI/Prompts/*.php`. Retrieved content lands in the per-turn `<context>` block, **never** in `FynSystemPrompt::text()`. _Touches: `app/Services/AI/Fyn/FynContextAssembler.php`._
- **FR-M10:** Compute `semantic_snapshot_id` per turn: SHA-256 over the sorted list of `(fact_id, version)` tuples the retriever returned. Persisted on the episode SQL row (column added in Phase 2; Phase 1 emits the value, Phase 2 adds the column). _Touches: working-memory VO; episode write path._
- **FR-M11:** Static `FynSystemPrompt::text()` heredoc remains byte-identical. Phase 1 must not modify it. Prefix-cache hit rate (when Phase 5 ships telemetry) must not regress. _Touches: enforcement in CI — fail PR if `FynSystemPrompt.php` changes outside an explicit prompt-rev sub-task._

### Should-have

- **FR-S1:** Admin UI for browsing the corpus read-only. Lists facts by category, shows frontmatter and body. No editing in Phase 1. Wraps in `AppLayout` per CLAUDE.md Rule #14. _Touches: new `resources/js/views/Admin/SemanticCorpusViewer.vue` + supporting controller._
- **FR-S2:** CI job that warns if any `tax`-category fact has no `valid_to` set within six months of the next tax-year start (per Risks & Open Questions in v0.4 plan). _Touches: GitHub Actions workflow._

### Nice-to-have

- **FR-N1:** Wikilink-style cross-references (`[[other-fact-slug]]`) between facts, surfaced in the admin viewer as related-fact navigation. Aligns with the fynlaBrain Obsidian workflow. _Touches: post-processing in `SemanticCorpusLoader`; admin viewer rendering._
- **FR-N2:** Reverse-index lookup — given an episode's `semantic_snapshot_id`, replay which exact facts were active at the time. _Touches: snapshot resolver utility._

---

## 6. User Flow & UX/Design

### Backend flow (Phase 1 has no end-user UX)

```
Deploy
  └─ Code update
  └─ Corpus update (.md files)
  └─ php artisan fyn:semantic:reindex
       └─ For each .md: generate embedding, write to embeddings.json

Boot
  └─ SemanticCorpusLoader walks corpus directory
  └─ Parse frontmatter, validate, index by fact_id
  └─ Fail-closed on duplicate / malformed
  └─ Load embeddings.json into memory keyed by fact_id

Per Fyn turn
  └─ FynContextAssembler::build()
       └─ SemanticRetriever::retrieve(query, effective_date, categories)
            └─ Date filter
            └─ Hybrid score (sparse + dense)
            └─ Return ranked facts
       └─ Inject into <context> block
       └─ Compute semantic_snapshot_id from returned facts
  └─ LLM call with <context> + static FynSystemPrompt::text()
```

### UX/Design notes (admin viewer only)

- **Design system:** `fynlaDesignGuide.md` v1.3.0. Standard `AppLayout` wrapper. Category filter uses horizon nav tokens, fact cards use the standard `.card` variant. No icons (Rule #16 — admin viewer is a list/detail UI, no functional necessity for icons).
- **Reusable components:** Existing `FormModal.vue` not needed (read-only). Existing layout chrome from `AppLayout.vue`.
- **New components:** `SemanticCorpusViewer.vue` (list view), `SemanticFactDetail.vue` (detail view).
- **Responsive behaviour:** Standard responsive — admin desktop primary.
- **Accessibility:** Keyboard navigation through the fact list; standard list patterns.
- **Reference artefacts:** None — net-new functionality.

---

## 7. Out of Scope

- Editing facts through the admin UI. Read-only in Phase 1. Editing remains PR-gated indefinitely (CSJ directive — see plan v0.4 Resolved decisions).
- Cross-user semantic retrieval (anonymised similar-case recall). Separate GDPR/FCA spec required.
- Migrating numeric tax values out of `TaxConfigService` into semantic memory. `TaxConfigService` remains canonical for numeric values (CLAUDE.md Rule #3, MEMORY.md `feedback_never_hardcode_tax_values.md`).
- Embedding model fine-tuning. Use whichever embeddings API is chosen as-is.
- Episodic memory dense retrieval over `reasoning_trace`. Phase 6 work.
- Procedural memory (overlays, workflows, tool schemas). Phase 4 work — reuses the loader pattern but with different schema and a different corpus root.
- Changes to `FynSystemPrompt::text()`. Static prompt is byte-identical for prefix-cache (canonical contract `00-canonical.md`).
- A new vector database. Stays in MySQL-adjacent storage; brute-force ANN over in-memory embeddings (expected corpus < 500 files initially; well within budget).

---

## 8. Risks & Dependencies

### Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Prefix-cache hit rate regression when assembler block grows from semantic injection | Medium | High | Phase 5 prefix-cache hit/miss telemetry must ship **before** Phase 1 cutover so regression is measurable. Phase 1 release gated on no-regression confirmation. |
| Embeddings provider choice locks us in | Low | Medium | Embeddings are stored as JSON, regenerable any time via `fyn:semantic:reindex`. Provider can be swapped without data migration. |
| Migration of FCA content from PHP heredocs introduces wording drift | Medium | Medium | Diff each chunked fact against the original PHP body before merging. Test set of advice prompts re-run pre/post cutover to detect tonal changes. |
| Boot-time fail-closed on malformed frontmatter blocks deploy | Low | High | Required pre-deploy step: `php artisan fyn:semantic:validate` runs the same checks as boot. CI runs it on every PR. |
| Corpus PR review velocity (CSJ as sole reviewer per CODEOWNERS) bottlenecks high-volume content refresh — e.g. budget-day update of 50+ facts | Medium | Low | Acceptable trade-off given regulatory content. Revisit only if PR throughput becomes a real problem. |
| Effective-date semantics confuse content authors | Medium | Medium | Authoring guide in the `house_view/README.md` + worked examples in PR template. |

### Technical dependencies

- `TaxConfigService` — stays canonical for numeric tax values. Semantic memory must never duplicate numeric values.
- `FynContextAssembler` — the integration point. Phase 1 modifies its build path.
- `FynSystemPrompt::text()` — must remain byte-identical.
- Embeddings API — TBD. Candidates: Anthropic, xAI, OpenAI. The project already uses Anthropic and xAI for chat; embeddings provider is a separate decision.
- SiteGround filesystem — deploy bundle must include the corpus directory. Manual upload via SiteGround File Manager per CLAUDE.md "Manual File Upload Only".

### Sequencing dependencies

- **Blocks:** Phase 4 (procedural memory) reuses the loader pattern. Phase 6 (learning) feeds proposed amendments into the corpus.
- **Blocked by:** Phase 5 prefix-cache hit/miss telemetry must ship first to establish baseline.
- **Independent of:** Phases 2 and 3 can ship in parallel.

### Residual concerns from codebase audit

- **Embeddings provider not yet chosen.** TBD: CSJ to decide between Anthropic / xAI / OpenAI before Phase 1 sub-plan is drafted.
- **`tax_product_reference` table fate.** After product corpus migration, the table remains as source-of-record for migration input. Consider deprecating in a later phase once corpus is the only consumer.
- **`actuarial_life_tables` not covered by Phase 1.** Stays in DB as a structured lookup table. Not a candidate for semantic memory (it's structured data, not narrative). Confirmed scope decision.

---

## 9. Document History

| Date | Change | By |
|------|--------|-----|
| 27 May 2026 | Initial draft from CoALA v0.4 plan Phase 1 | prd-writer skill |
