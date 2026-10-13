# Fyn memory architecture — map

Everything below is read from the code at the cited `path:line`. Where I did not open backing code I say so.

---

## 1. Design in one sentence

Fyn implements the CoALA (Cognitive Architectures for Language Agents) four-memory model: **working memory** is assembled per turn in PHP, **procedural** and **semantic** memory are committed markdown at the repo root, **episodic** memory is split between runtime markdown and a forensic SQL+blob pair, and anything with a live owner is not stored at all — it is a **pointer** fetched at the moment of need.

The design statement is in `fyn-memory/README.md:1-16`, and the canonical pointer rule at `fyn-memory/README.md:17`: *"Memory holds pointers, not copies."*

Paths are configured once, in `config/fyn.php:49-59`:

| Config key | Resolves to |
|---|---|
| `procedural_path` | `fyn-memory/procedural` |
| `episodic_rubric` | `fyn-memory/episodic/RUBRIC.md` |
| `episodic_path` | `fyn-memory/episodic/episodes` |
| `semantic_path` | `fyn-memory/semantic` |
| `semantic_index` | `storage/app/memory/semantic/index.json` |
| `pointers_path` | `fyn-memory/procedural/pointers` |
| `user_semantic_path` | `storage/app/memory/semantic-user` |
| `semantic_top_k` | 4 (`FYN_SEMANTIC_TOP_K`) |
| `learning_enabled` | false (`FYN_LEARNING_ENABLED`, `config/fyn.php:68`) |

Neither `FYN_LEARNING_ENABLED` nor `FYN_SEMANTIC_TOP_K` appears in `.env` or `.env.example`, so both run on the defaults above.

---

## 2. `app/Services/AI/Memory/` — every class

**Root**

- `FynMemoryStore.php:23` — the read/write adapter for the markdown stores. Loads procedures (`:43`), relevance-filters them (`:86`), renders the procedural context block (`:117`), returns the episodic rubric (`:138`), recalls a user's episodes (`:160`), renders them as a `## What I remember about you` block (`:186`), writes an episode (`:209`), and deletes a user's whole episode tree for GDPR (`:244`). Pure file I/O over markdown-with-YAML-frontmatter; the salience decision belongs to the planner.
- `SemanticCorpusLoader.php:17` — loads and validates `fyn-memory/semantic/{category}/*.md`, fail-closed. Categories are fixed at `:21`; `source` is mandatory for `fca`/`product` (`:24`), `valid_from` for `fca`/`tax`/`allowance` (`:26`). Duplicate `fact_id` throws (`:53`).
- `SemanticFact.php:13` — immutable fact; answers effective-dating at `:27`.
- `SemanticRetriever.php:22` — sparse keyword retrieval (section 3 below).
- `UserSemanticStore.php:17` — per-user runtime facts under `storage/app/memory/semantic-user/{userId}/`, written only from an approved proposal. `put` at `:19`, `forUser` at `:37`, `forget` at `:60`.

**`Recall/`**

- `RecallScorer.php:12` — interface. The docblock records the decision: dense embeddings deferred until roughly 500 concurrent users (CSJ, 2026-06-01).
- `SparseRecallScorer.php:16` — token-overlap ranking over the episode body, same grammar as the semantic retriever; stable sort so recency is the tiebreak.

**`Procedural/`**

- `ProceduralCorpusLoader.php:24` — loads `fyn-memory/procedural/{kind}/{module}/*.md` for the four kinds at `:26`. Runtime `load()` (`:44`) never throws and degrades to the last-good corpus (`:73-79`); `loadStrict()` backs the deploy gate. Cached cross-request by a directory signature (max mtime + file count, `:90`), re-stat throttled by `procedural_reload_interval`.
- `ProceduralCorpus.php:13` and `Procedure.php:14` — immutable corpus and one versioned procedure, with `active()` resolving the effective version per provider (`ProceduralCorpus.php:43`).
- `ProceduralContributionCollector.php:17` — request-scoped accumulator of contributed overlay/FCA procedures.

**`Episodic/`**

- `EpisodeBlobData.php:10` / `toMarkdown()` `:35` — the verbatim per-turn forensic body: frontmatter plus `system_prompt`, `assembled_context`, optional `reasoning_trace`, `tool_calls`, `tool_results`.
- `EpisodeBlobWriter.php:19` — atomic write: temp file, delete target, rename, then sha256. Path shape `episodic/Y/m/d/{conversationId}/{messageId}.md` (`:23-24`).
- `EpisodeBlobLocator.php:11` — resolves hot `episodic/` or cold `episodic-cold/` (`:41-53`); `eraseForUser` at `:21` deletes blobs before rows.
- `EpisodeBlobRef.php:8`, `EpisodeRetriever.php:12` (SQL-only list), `EpisodeProjection.php:11` (list is SQL, detail lazy-loads the blob).
- `FetchProvenanceCollector.php:14`, `ProceduralVersionHolder.php:20`, `SemanticSnapshotHolder.php:15` — three request-scoped holders, bound `scoped` in `app/Providers/AppServiceProvider.php:147,150,163`, flushed at persist time.

**Tables behind them**

`ai_conversations` (`database/migrations/2026_02_27_200001_create_ai_conversations_table.php:13`) plus later columns:

| Column | Migration |
|---|---|
| `persona_state` | `2026_04_22_000002_add_persona_state_to_ai_conversations.php` |
| `onboarding_parked_facts` | `2026_04_22_000003_...:15` |
| `summary`, `topics`, `entities_mentioned`, `intents_stated`, `summarised_at` | `2026_05_02_000001_add_conversation_index_columns.php:14-19` |
| `pending_resumption` | `2026_05_30_000003_...` |

`ai_messages` (`2026_02_27_200002_create_ai_messages_table.php:13`) plus `system_prompt`, `assembled_context` (`2026_05_18_135313_...`), `persona` (`2026_04_22_000001_...`), `status` enum queued/processing/answered/cancelled/expired (`2026_05_30_000001_...`), and the five episode columns `procedural_version`, `semantic_snapshot_id`, `fetch_provenance`, `blob_md_path`, `blob_md_sha256` (`2026_06_01_000001_add_episode_columns_to_ai_messages.php:14-18`).

`proposed_semantic_facts` (`2026_06_15_000001_...:16-38`) and `proposed_procedure_amendments` (`2026_06_15_000002_...`, migration file exists; I did not open it).

There is **no embeddings table and no vector column anywhere** — retrieval is sparse by design.

---

## 3. `MemoryRetrieverService.php` — the retrieval algorithm

This class is not the CoALA store reader. It is the "known facts about this user" assembler, four layers with strict gap-fill fall-through (`app/Services/AI/MemoryRetrieverService.php:13-48`).

1. **Authoritative DB** (`:86`) — name, date of birth, marital and employment status, employer, occupation, three numeric fields, onboarding flag, spouse presence, dependants count via `DependantsReach` (`:122`), and five record counts.
2. **Parked facts** (`:151`) — `ai_conversations.onboarding_parked_facts` flattened across five buckets (`:51`). Spouse keys get a `spouse_` prefix.
3. **Current conversation** (`:215`) — re-runs `OnboardingFactExtractor` over the last 6 user messages, catching the just-typed turn before parking flushes.
4. **Conversation index** (`:254`) — the five most recent *other* summarised conversations, contributing only `prior_topics` and `prior_intents` (capped at 5), deliberately hint-shaped so it can never overwrite a Layer-1 truth.

Merging is add-only (`mergeNewKeys`, `:334`). Output is rendered as a `<known_facts>` block closing with "Do not ask the user for any field above." (`:306-325`).

No embeddings, no scoring, no recency weighting — it is a priority-ordered union.

Callers: `AdvicePromptBuilder.php:65`, `Fyn/FynContextAssembler.php:51`, `Onboarding/OnboardingPromptBuilder.php:38`, `Onboarding/OnboardingChatDirector.php:120`.

**The sparse scorer that does rank** is `SemanticRetriever::retrieve` (`SemanticRetriever.php:52`): stopwords dropped from the query (`:31-41`), effective-date filter applied *before* ranking (`:68`), word-boundary token counts with plus-or-minus "s" plural variants (`:181,194`), and a fact admitted only when it matches at least `min(2, distinct content tokens)` distinct query tokens (`:61,83`). The docblock at `:18-20` records why: a substring scorer was loading four full strategy bodies on every English turn because "the" scored 50 to 67 per file. `FynMemoryStore::matchingProcedures` (`:86`) and `SparseRecallScorer` (`:18`) implement the identical grammar, and share the one stopword list.

---

## 4. Summarisation

`ConversationSummariser.php:43` compresses up to 50 user and assistant messages (`:47,65-69`) into four index columns on `ai_conversations` (`:85-91`). Provider is xAI, endpoint hardcoded at `:45`, model from `services.xai.vision_model` defaulting to `grok-4.3` (`:137`), temperature 0, `reasoning_effort: none`, JSON object response format (`:166-173`). The system prompt constrains topics to a fixed module tag set and forbids inventing figures or echoing the assistant's recommendations (`:150-154`). A failure logs a warning and leaves the row un-summarised (`:176-209`).

After a successful summary it calls `emitProposedFacts` (`:93,96`), which is a no-op unless `fyn.learning_enabled` (`:98`).

`ConversationSummariserJob.php:26` is a thin queue wrapper with no `tries`/`backoff` on purpose (`:20-25`).

Two dispatch paths:
- End of onboarding, `OnboardingChatDirector.php:7674`.
- The scheduled scan, `SummariseStaleConversationsCommand.php:84`.

`SummariseStaleConversationsCommand.php:34` runs `ai:conversations:summarise-stale`. Default idle threshold 30 minutes (`:37`). It skips in-flight onboarding conversations — the resume-contract carve-out documented at `:21-32` and implemented as a `whereNotExists` on users with `onboarding_completed = false` and `metadata.source = 'fyn_onboarding'` (`:53-65`). The `--pause` flag (`:38`) additionally marks idle active conversations `paused` (`:67-72,85-87`).

It is scheduled twice in `app/Console/Kernel.php`: every three minutes with `--idle-minutes=3 --pause` (`:34-36`) and every thirty minutes plain (`:65`).

Re-use of the summaries: `MemoryRetrieverService::fromConversationIndex` (`:254`) and the `search_conversation_index` tool (`app/Agents/CoordinatingAgent.php:2657`), which queries the user's own summarised conversations by `topics` and `entities_mentioned` JSON containment (`:2669-2684`), excluding the active conversation.

---

## 5. The CoALA memory model as implemented

**Working memory** — `FynTurnContext.php:15` is the immutable turn description (user, message, route, mode, onboarding focus, preview flag, classification, conversation, KYC result). `FynContextSelector.php:14` maps it onto four buckets defined in `ContextBucket.php:15`: IDENTITY, POSITION, READINESS, CAPTURE. Onboarding gets IDENTITY plus CAPTURE; a factual advice turn gets IDENTITY alone; everything else gets IDENTITY, POSITION, READINESS (`FynContextSelector.php:19-35`). `FynContextAssembler.php:47` then builds the `<context>` block. Working memory is in-memory only for the turn, then snapshotted into `ai_messages.assembled_context`.

**Procedural memory** — `fyn-memory/procedural/`. Two distinct shapes share the directory:

- Root-level procedures with `id`/`title`/`applies_when` frontmatter, read by `FynMemoryStore::procedures()` (`:43`, skipping `_TEMPLATE.md` and `README.md` at `:26`). **Exactly one exists today**: `recommendation-routing.md`.
- The kinded corpus `{kind}/{module}/*.md` read by `ProceduralCorpusLoader`, with four kinds (`:26`). On disk today: 2 `system_prompt_overlay` files under `general/`, 1 `workflow` file (`onboarding/fyn-onboarding.v1.md`), and a large `tool_schema` tree with an `.md` plus `.xai.md` variant per tool.

Writer is CSJ, by commit (`fyn-memory/README.md:59-61`). Readers are `FynContextAssembler.php:100` (root procedures, relevance-filtered), `FynLoop.php:323` (root procedures, **full corpus**, because matching `applies_when` to intent is the planner's job), `FynContextAssembler.php:199-201` (overlay and FCA blocks), `AiToolDefinitions.php:146` and `XaiToolDefinitions.php` (tool schemas). Lifecycle: no TTL; versions are effective-dated and resolved by `ProceduralCorpus::active()`. Deploy gate `fyn:procedural:validate` (`app/Console/Commands/FynProceduralValidate.php:18`).

**Semantic memory** — two stores.

- Global, committed: `fyn-memory/semantic/{category}/`. Today it holds **20 `house_view` files and nothing else** — `allowance/`, `fca/`, `product/`, `tax/` contain only `.gitkeep`. Written by CSJ, read by `SemanticRetriever` into the `<knowledge>` block (`FynContextAssembler.php:125,144`). Validated and indexed by `fyn:semantic:reindex` (`FynSemanticReindex.php:17`), which writes a summary index to `storage/app/memory/semantic/index.json` (`:44-46`). No TTL; effective-dating by `valid_from`/`valid_to`.
- Per-user, runtime: `storage/app/memory/semantic-user/{userId}/` via `UserSemanticStore`. Written only by `SemanticFactPromoter::approve` (`app/Services/AI/Learning/SemanticFactPromoter.php:18`), read by `SemanticRetriever::retrieveForUser` (`:111`). User facts are appended after global ones and are admitted on match regardless of score, so personal context is never crowded out (`:102-106`).

**Episodic memory** — also two stores, and they are genuinely separate.

- CoALA episodes: `fyn-memory/episodic/episodes/{userId}/{year}/*.md`, written by `FynMemoryStore::writeEpisode` (`:209`) when the planner emits a `learn` action with `store=episodic` (`FynLoop.php:237,341`). Gitignored as user data (`fyn-memory/episodic/episodes/.gitignore`). Recalled by `FynMemoryStore::recall` (`:160`) into `<remembered>`.
- Forensic episodes: one `.md` blob per assistant message under `storage/app/episodic/...`, plus five columns on `ai_messages`. Written by `HasAiChat::persistEpisode` (`app/Traits/HasAiChat.php:1525`, called at `:1466`), which flushes the three request-scoped holders, writes the blob, updates the row, and appends a signed `__episode__` attestation to the audit chain (`:1576-1584`). Every failure is reported and swallowed — the verbatim columns are the fallback (`:1585-1588`).

Lifecycle commands in `app/Console/Commands/`:

| Command | Behaviour | Scheduled |
|---|---|---|
| `fyn:episodic:reconcile` | Flags orphan blobs with no matching row, flag only | Daily, `Kernel.php:70` |
| `fyn:episodic:cold-archive` | Moves blobs older than 12 months to cold storage, idempotent | Weekly, `Kernel.php:71` |
| `fyn:episodic:purge` | Hard-deletes blobs and rows older than 6 years, dry-run unless `--force` | Manual only |
| `fyn:episodic:backfill-blobs` | Backfills blobs for legacy rows | Manual only |
| `fyn:pointers:reindex` | Validates the pointer corpus, fail-closed, writes no index | Manual / deploy |
| `fyn:semantic:reindex` | Validates and writes the semantic index | Manual / deploy |
| `fyn:semantic:promote` | Approves one staged per-user fact | Manual |
| `fyn:procedural:validate` | Deploy gate for the procedural corpus | Manual / deploy |
| `fyn:user:erase` | GDPR erasure, dry-run unless `--force` | Manual |

Retention is framed as FCA SYSC 9.1 in `Kernel.php:68-69`.

**Two findings worth flagging.**

First, **the episodic rubric is still a draft**. `fyn-memory/episodic/RUBRIC.md` carries `version: 0` and `status: draft`, and `FynMemoryStore::rubric()` returns an empty string for exactly that (`:147-149`). So the planner is never given the rubric it is supposed to apply.

Second, **all 414 files under `fyn-memory/episodic/episodes/` are test artefacts**. Every filename matches `cycle-N-learn-xxxx`, and the bodies are the literal summary "cycle 1 learn" with `salience: 0` and empty signals and references. There is no real episodic content in the local store.

---

## 6. The pointer model

A pointer is a markdown file of **routing only** — no fetch code, no values, no figures (`fyn-memory/procedural/pointers/README.md`). Frontmatter carries `pointer_id`, `topic`, `triggers`, `mode`, `handler`, `source_label`, `version`; the body is prose that doubles as the tool description in `tool` mode.

`Pointer.php:8` is the value object. `PointerRegistry.php:17` loads and validates the directory, **fail-closed**: an unknown mode throws (`:112`), a prefetch pointer with no triggers throws (`:117`), and a pointer naming an unregistered handler throws (`:122`). The README is explicit that this hard-breaks corpus load rather than sitting inert, so the code handler must ship before the markdown.

Three modes (`Pointer.php:22-30`):

- `prefetch` — `matchPrefetch` does a plain lowercase substring match of each trigger against the query (`PointerRegistry.php:60-78`); a match runs the handler and injects the result into `<live_data>` before the model sees the turn (`FynContextAssembler.php:151-165`).
- `tool` — exposed to the model as a callable tool (`XaiToolDefinitions.php:200`, executed via `CoordinatingAgent.php:1647-1659`).
- `both`.

`FetchHandlerRegistry.php:10` is the closed whitelist of code-defined fetchers. `FetchDispatcher.php:16` runs one, records provenance into the request-scoped collector (`:33`), and on any throwable degrades to null with a `report()` — never breaking the turn (`:27-31`). `FetchResult::make` (`:24`) derives a 16-char sha256 digest of the rendered value; `provenance()` (`:30`) emits the tuple that lands in `ai_messages.fetch_provenance`.

Nine pointers exist: `isa-annual-allowance`, `recommendations`, `user-financial-position`, and six plan pointers (cross-module, estate, investment, protection, retirement, savings). Ten handlers are implemented under `Pointers/Handlers/`.

How it replaces raw data: `TaxAllowanceHandler.php:30` reads the ISA and pension allowances live from `TaxConfigService` plus the user's recorded subscriptions from `ISATracker`, and stamps the active tax year as the source version (`:57`). Nothing numeric is ever frozen in markdown, which is the CLAUDE.md Rule 3 contract expressed as memory architecture.

The tool path mirrors the pointer path deliberately: `CoordinatingAgent::handleRecommendations` records the same `pointer_id: recommendations` provenance entry with the plan digest and surfaced strategy ids (`app/Agents/CoordinatingAgent.php:2754-2762`), resolving the collector at call time because the agent is a captured singleton (`:2749-2753`).

---

## 7. `fyn-memory/` contents

```
fyn-memory/
  README.md                  the store contract + pointer model
  procedural/
    recommendation-routing.md   the ONE root procedure
    pointers/                   9 pointers + README + _TEMPLATE
    system_prompt_overlay/general/  a1-answer-first, a2-ack-hygiene
    tool_schema/<module>/       ~50 tools, each .md + .xai.md
    workflow/onboarding/        fyn-onboarding.v1.md
  semantic/
    house_view/                 20 strategy-stance files
    allowance|fca|product|tax/  empty (.gitkeep only)
  episodic/
    RUBRIC.md                   draft, version 0
    episodes/                   gitignored; 414 test artefacts locally
```

How each reaches the prompt: root procedures via `FynMemoryStore::proceduralContext` into `<procedures>`; overlays and FCA blocks via `ProceduralCorpusLoader` into `<overlay>` / `<fca_block>`; tool schemas via `AiToolDefinitions::toolsFromCorpus` (`:146`) into the tool catalogue, with each resolved procedure stamped into `ProceduralVersionHolder` (`:157`); the workflow into the onboarding state machine (its own frontmatter says PHP-only fields stay in `OnboardingStateMachine` and are re-attached by state id); semantic facts into `<knowledge>`; pointers into `<live_data>` or the tool catalogue; episodes into `<remembered>`.

---

## 8. Conversation persistence

`AiConversation` (`app/Models/AiConversation.php:15`) uses `SoftDeletes` and casts every JSON column (`:39-52`). `AiMessage` (`app/Models/AiMessage.php:12`) casts `status` to `AiMessageStatus` and the five episode columns.

Session lifecycle: conversations are created `active` (`AiChatController.php:80`) and reactivated on use (`:223`). The three-minute scheduled sweep pauses idle active conversations (`Kernel.php:34`), and a paused conversation reopens when the user next sends. Resumption is a separate surface: `ResumptionService.php:17` flags a conversation that ended unfinished (`:23`), `latestForUser` finds it on the next session (`:54`), and either choice clears it (`:42`). Endpoints at `routes/api.php:1523-1524`.

**History filter.** `HasAiChat::buildMessageHistory` (`app/Traits/HasAiChat.php:1786`) takes the last 20 user and assistant messages (`MAX_HISTORY_MESSAGES = 20`, `:98`) and drops assistant rows whose metadata carries `is_retry` or whose content contains `FynSystemPrompt::CANNED_REFUSAL` (`:1807-1813`). The comment at `:1799-1806` records the live incident: once a few dead-end rows sat in the history, grok pattern-matched and refused even a full entity sentence (production conversation 843, 2026-09-11). Those rows stay in the transcript the user sees and leave only the model-facing history — built in this one place.

**Forensic columns** are `system_prompt`, `assembled_context`, `tool_calls`, `tool_results`, plus the five episode columns. They are described as the fallback when blob writing fails (`:1587`).

---

## 9. Learning and `FYN_LEARNING_ENABLED`

**It is off.** `config/fyn.php:68` defaults to false and the variable is set in neither `.env` nor `.env.example`.

When enabled, three write paths open, and none of them ever auto-applies:

- `ProposedFactSynthesiser.php:16` — called from the summariser (`ConversationSummariser.php:103`) with the transcript. Its prompt (`:28-36`) extracts only durable traits and intentions and explicitly forbids monetary amounts and any figure with a live source, because those are pointer-fetched. Output is staged into `proposed_semantic_facts` as `pending` (`ConversationSummariser.php:104-114`).
- `FynLoop::stageProposedFact` (`app/Services/AI/Loop/FynLoop.php:360`) — the planner's `learn` with `store=semantic`.
- `FynLoop::stageProcedureAmendment` (`:388`) — the planner's `learn` with `store=procedural`, plus one extra "workflow failure" consult when a turn exhausts its cycle cap (`:280-296`).

Promotion is human-gated: `SemanticFactPromoter.php:18` is documented as the only path that writes a per-user semantic fact, and only from an approved proposal, never touching the global corpus (`:10-12`). The CLI is `fyn:semantic:promote {fact} --reviewer=`.

The planner's own schema guards against a well-known confusion: `Planner.php:311,315` tells the model that `learn` stages a note about how to help the user and is **not** for recording the user's financial data, and `FynLoop.php:82` repeats it — choosing `learn` for a stated fact loses the user's answer.

---

## 10. GDPR erasure

Two paths, and they do not cover the same ground.

`fyn:user:erase` (`app/Console/Commands/FynUserErase.php:27`) deletes blobs first because the rows carry the paths (`:61-62`), then the `ai_messages` and `ai_conversations` rows in a transaction (`:66-73`), then the per-user semantic facts (`:76`). Dry-run unless `--force`.

`RetentionPurgeService` (`app/Services/Account/RetentionPurgeService.php:47`) calls `FynMemoryStore::forget($userId)` to delete the CoALA markdown episode tree, then `EpisodeBlobLocator::eraseForUser` (`:53`).

**Gap worth reporting, not fixing:** `fyn:user:erase` does not call `FynMemoryStore::forget`, so the manual per-user erasure leaves `fyn-memory/episodic/episodes/{userId}/` on disk. The only caller of that method is the retention purge (confirmed by grep across `app/`). Conversely, the retention purge does not call `UserSemanticStore::forget`. Each path erases what the other leaves.

---

## 11. Caching layers

- **Per-user prompt caches**: `ai_existing_records_{user_id}` at 60 seconds (`AdvicePromptBuilder.php:763`) and `ai_financial_context_{user_id}_{primary}` at 120 seconds (`:476`). Invalidated by `AdvicePromptCacheInvalidator::forUser` (`app/Services/AI/AdvicePromptCacheInvalidator.php:35`), which forgets the records key and every classification variant including `unknown` (`:38-54`), best-effort and swallowing failures (`:55-65`). The docblock at `:18-27` explains the failure it prevents: a user who creates a record by inline capture and immediately asks a question would otherwise hear "you don't have an ISA" for up to 120 seconds.
- **Procedural corpus cache**: `fyn:procedural:corpus` and `fyn:procedural:corpus:sig` (`ProceduralCorpusLoader.php:30-32`), validated against the directory signature before use (`:67-72`).
- **Per-instance memos**: `FynMemoryStore::$procedures` (`:36`, explicitly never bound singleton so the memo cannot outlive a turn), `PointerRegistry::$cache` (`:24`, bound singleton at `AppServiceProvider.php:141`), `SemanticCorpusLoader::$cache` (`:29`).
- The semantic index JSON at `storage/app/memory/semantic/index.json` is a **validation artefact, not a read path** — `SemanticRetriever` reads the loader, not the index.

---

## 12. What is shared across surfaces

Everything except the client. Web (`resources/js/services/aiChatService.js`), `/m` (`resources/mobile/mixins/onboardingChat.js:93,112,171,667,723`) and native iOS (`ios-native/Fynla/Features/Fyn/FynClient.swift:84-223`) all call the same `api/ai-chat/*` routes registered once at `routes/api.php:1507-1529`.

That means one conversation store, one message history, one memory of every type, one pointer registry, one procedural and semantic corpus, and one episodic trail across all three surfaces. There is no per-surface memory partition anywhere in the code I read. This is the architectural fact behind CLAUDE.md Rule 20.

---

## 13. Memory data flow for one chat turn

**Before the model call:**

1. The controller resolves the conversation and sets it `active` (`AiChatController.php:223`).
2. `FynTurnContext::make` captures the turn; `FynContextSelector::buckets` picks the buckets.
3. `FynLoop::plannerSystemPrompt` (`:315`) layers the planner prompt with the **full** procedural corpus, the user's episodes ranked against the message, and the rubric — which is currently empty because the rubric is a draft.
4. The planner runs, up to the cycle cap (8 by default, `config/fyn.php:37-41`), with a retrieve budget.
5. `FynContextAssembler::build` (`:66`) assembles working memory in this order: profile and current-page context; `<known_facts>` from `MemoryRetrieverService` (`:89`); `<procedures>` relevance-filtered to the message, stamping each matched procedure into `ProceduralVersionHolder` (`:100-111`); `<remembered>` from the five most recent episodes, recency-ordered with no query (`:114`); `<knowledge>` from the semantic retriever, with the snapshot id stamped into `SemanticSnapshotHolder` (`:125-134`); `<live_data>` from every prefetch pointer whose trigger substring appears in the message, each recording provenance into `FetchProvenanceCollector` (`:151-165`); then overlays, FCA blocks, and the bucket-gated financial, records, readiness, KYC, knowledge and billing layers.
6. `buildMessageHistory` (`HasAiChat.php:1786`) supplies the last 20 turns with retry and canned-refusal rows filtered out.
7. Tool schemas come from the procedural corpus, each stamping its version into the holder (`AiToolDefinitions.php:157`); tool-mode pointers join the catalogue.

**After the model call:**

1. Assistant message persisted with `system_prompt`, `assembled_context`, `tool_calls`, `tool_results`.
2. `persistEpisode` (`HasAiChat.php:1466,1525`) drains the three holders, writes the atomic `.md` blob, updates `blob_md_path`, `blob_md_sha256`, `fetch_provenance` and `procedural_version`, and appends the signed `__episode__` audit attestation.
3. If the planner emitted `learn` with `store=episodic`, `FynMemoryStore::writeEpisode` appends a markdown episode under the user's tree (`FynLoop.php:237,341`).
4. With learning enabled only, `learn` with `store=semantic` or `store=procedural` stages a `pending` proposal (`FynLoop.php:360,388`).
5. Any write tool calls `AdvicePromptCacheInvalidator::forUser`, clearing the 60 and 120 second prompt caches.
6. Later, out of band: the scheduler pauses the conversation after 3 idle minutes and dispatches the summariser, which writes `summary`, `topics`, `entities_mentioned`, `intents_stated`, `summarised_at` — and those become Layer 4 of the next conversation's known facts, and the corpus for the `search_conversation_index` tool.

---

## COULD NOT VERIFY

- The bodies of `FynEpisodicPurge`, `FynEpisodicColdArchive`, `FynEpisodicReconcile`, `FynEpisodicBackfillBlobs` and `FynProceduralValidate` beyond their signatures and descriptions.
- `proposed_procedure_amendments` column list — the migration exists but I did not open it.
- `ProceduralCorpusLoader::parse` and `loadStrict` validation rules beyond line 120.
- `AuditChainService::appendEpisode` internals.
- The `/m` and native clients' own memory or caching behaviour beyond confirming they call the shared endpoints.
- Whether `storage/app/memory/semantic-user/` or `storage/app/episodic/` hold data on any deployed environment — I only read the local repository.
