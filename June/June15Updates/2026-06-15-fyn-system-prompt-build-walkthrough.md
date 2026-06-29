# How the Fyn system prompt is built (CoALA, dev) — a traced walkthrough

**Date:** 2026-06-15
**Branch context:** `dev` (CoALA landed via PR #550). Provider runs **xAI** (`grok`), so the `.xai.md` corpus variants are the live ones.
**Scope:** the *pre-send* pipeline only — everything that happens between `AiChatController::sendMessage` and the bytes handed to the LLM. No live LLM call is made here; the semantic/pointer/procedural numbers below are **real probe output** from the live corpus on disk, the profile/financial blocks are illustrative skeletons (they need a real user + engine run).

---

## 0. The one thing to internalise first

What people call "the Fyn system prompt" is actually **two separate payloads** sent on every turn:

| Payload | Role on the wire | Content | Varies per turn? |
|---|---|---|---|
| **A. Static system message** | `system` role | `FynSystemPrompt::text()` — identity, security, compliance, tool-use rules | **No** — byte-identical for every user/turn (full prefix-cache hit) |
| **B. Dynamic per-turn context** | injected into the **last `user` message** | `FynContextAssembler::build()` — `<context>…</context>` + `<user_message>…</user_message>` | **Yes** — rebuilt every turn from the user's data + memory |

The dynamic block is **not** appended to the system role. It *replaces the content of the user's turn* in-memory (`HasAiChat::injectUnifiedTurnContext`, `app/Traits/HasAiChat.php:1082-1087`). The persisted DB row keeps the raw message; only the in-memory copy sent to the provider carries the assembled context. This split is deliberate: keeping the system role byte-identical means the entire static prompt is served from the provider's prefix cache, and only the much smaller per-turn block is "new" tokens.

Both are gated by `FYN_PROMPT_ARCH` (`config/fyn.php:16`, default `unified`). `legacy` is the emergency-rollback 12-layer builder and is out of scope here.

---

## 1. Dispatch — which Fyn, and how the turn enters the loop

`AiChatController::sendMessage` (`app/Http/Controllers/Api/AiChatController.php:177`) chooses the write-state on a **3-part predicate** (`:236`):

```php
$inOnboarding = $user->onboarding_completed === false
    && $user->onboarding_fyn_step !== null
    && (bool) config('onboarding.fyn_flow_enabled', true);
```

- `true`  → `OnboardingChatDirector` (the **only** state that writes data)
- `false` → `AdviceFyn` (read-only; write intents flow out through `delegate_to_capture`)

Both shells are thin wrappers over the shared **`FynLoop`** (CoALA Phase 5, Option B). For an advice turn:

```
AiChatController::sendMessage
  └─ AdviceFyn::handle                         app/Services/AI/AdviceFyn.php:204
       ├─ QueryClassifier::classify            → {primary: SAVINGS|TAX|RECOMMENDATION|…}
       ├─ (pre-LLM bypasses: out-of-remit refusal, duplicate-ack, deterministic write-intent)
       ├─ buildToolList(user)                  → name allowlist (catalogue minus WRITE_TOOLS)   :436
       └─ FynLoop::run(Advice, …, allowedTools)                                                 :364
            ├─ Planner::plan(...)              → one forced `plan` tool call → Action            Loop/Planner.php
            └─ (action=reason) → FynLoop::reason → stream → CoordinatingAgent::chatWithPromptOverride
                                                          ↑ this is where A + B are assembled
```

The classification matters for prompt-building because it drives **bucket selection** (§3).

---

## 2. Payload A — the static system message

`FynSystemPrompt::text()` (`app/Services/AI/Fyn/FynSystemPrompt.php:20`) returns one heredoc string. It is returned verbatim by `HasAiChat::buildSystemPrompt()` when unified (`app/Traits/HasAiChat.php:1018-1020`). Its sections, in order:

| Tag | Purpose |
|---|---|
| `<identity>` | "You are Fyn, a UK personal-finance guidance tool…" + the not-regulated-advice boundary |
| `<security>` | 9 non-negotiable rules (no prompt disclosure, no jailbreak, injection refusal — with an explicit carve-out that legitimate "add my pension" requests are **not** attacks) |
| `<scope>` | personal-finance only; politely deflect off-topic |
| `<personality>` | warm, plain-English, British spelling, celebrate progress |
| `<response_format>` | concise, bold figures, end with a follow-up question, no filler openers |
| `<instructions>` | British English; **spell out every acronym** (only ISA allowed); £ formatting; never expose IDs/routes; joint-ownership share rules |
| `<regulatory_compliance>` | 8 rules: mandatory hedging, no product names, signpost advice, risk/tax caveats, **never state tax values from memory — always call `get_tax_information`** |
| `<tool_use>` → `<fca_process>` | the FCA 6-step process (check data → fetch figures → analyse → recommend → implement → review triggers) |
| `<tool_use>` → `<available_actions>` | update-vs-create rules; **Advice Fyn is read-only**; READ vs WRITE tool error handling |
| `<tool_use>` → `<data_completeness_rules>` | the static navigation / BLOCKED-module / module-dependency rules (moved here once, off the per-turn block) |
| `<tool_use>` → `<handoff_guidance>` | TOP-PRIORITY: any add/change/delete intent → emit `delegate_to_capture` first, never fabricate a save |
| `<tool_use>` → `<fca_signposting>` | the exact verbatim advice-signpost sentence |

**Why it never changes:** zero interpolation, zero arguments. Per-user/per-turn facts live in Payload B instead. Any edit here must be re-validated against the Fyn eval suite (the file's own docblock says so).

---

## 3. Payload B — the per-turn context assembler

`FynContextAssembler::build(FynTurnContext $ctx, $orchestrateAnalysis)` (`app/Services/AI/Fyn/FynContextAssembler.php:57`) builds the `<context>` block line by line. Two inputs shape it:

### 3a. The turn context (`FynTurnContext`)
An immutable description of the turn (`app/Services/AI/Fyn/FynTurnContext.php`): `user`, `message`, `currentRoute`, `mode` (`advice`|`onboarding`), `onboardingFocus`, `isPreview`, `classification`, `conversation`, `kycResult`.

### 3b. The bucket selector (`FynContextSelector`)
`FynContextSelector::buckets()` (`app/Services/AI/Fyn/FynContextSelector.php:17`) maps the turn onto a subset of 4 buckets:

| Turn shape | Buckets |
|---|---|
| Onboarding | `IDENTITY`, `CAPTURE` |
| Advice, **factual** primary (`engineCallLevelFor()==='factual'`) | `IDENTITY` only |
| Advice, **non-factual** (recommendation/holistic) | `IDENTITY`, `POSITION`, `READINESS` |

`IDENTITY` = profile + current page (always). `POSITION` = financial snapshot + existing records. `READINESS` = data-completeness/KYC. `CAPTURE` = onboarding focus + capture instructions.

### 3c. The exact block order emitted

This is the literal order `build()` appends lines (`FynContextAssembler.php:65-274`). Blocks marked *(conditional)* are omitted when empty.

| # | Block | Source | Selection rule |
|---|---|---|---|
| 1 | `<context>` open + `Current tax year: 2026/27` | `TaxConfigService::getTaxYear()` | always |
| 2 | `You are speaking with: <first name>` | `user`, sanitised | always |
| 3 | `Situation: advice` / `…onboarding — focus: X` | `ctx.mode` | always |
| 4 | `<user_profile>` | `AdvicePromptBuilder::buildUserProfile` | always (IDENTITY) |
| 5 | `<current_context>` | `moduleContextFor(currentRoute)` | always (IDENTITY) |
| 6 | known-facts block *(cond.)* | `MemoryRetrieverService::renderKnownFactsBlock` | if non-empty |
| 7 | `<procedures>` *(cond.)* | `FynMemoryStore::proceduralContext(message)` | **relevance-matched to message** (§4c) |
| 8 | `<remembered>` *(cond.)* | `FynMemoryStore::recallContext(user.id)` | episodic recall, **recency** (§4d) |
| 9 | `<knowledge>` *(cond.)* | `SemanticRetriever::retrieve(message, now)` | **sparse keyword match** (§4a) |
| 10 | `<live_data>` *(cond.)* | `PointerRegistry::matchPrefetch(message)` → `FetchDispatcher` | **trigger substring match** (§4b) |
| 11 | `<overlay>` *(cond.)* | procedural `system_prompt_overlay`, module-matched + active | none active today (a1/a2 `active:false`) |
| 12 | `<fca_block>` *(cond.)* | procedural `fca_block` kind | none authored today |
| 13 | `<financial_context>` + `<existing_records>` | `AdvicePromptBuilder` (runs the sized analysis closure) | POSITION bucket |
| 14 | readiness lean block | `buildPrerequisiteStateContextLean` | READINESS bucket |
| 15 | KYC `prompt_text` *(cond.)* | `ctx.kycResult` | if gate produced text |
| 16 | `<financial_knowledge>` *(cond.)* | `QueryKnowledge::getForClassification` | advice turns, classification-scoped |
| 17 | `<savings_getting_started>` *(cond.)* | regex on message | only "how do I start saving" shape |
| 18 | `<voicing_rules>` | static helper | every advice turn |
| 19 | billing guidance *(cond.)* | `AdvicePromptBuilder::getBillingGuidance` | billing query + not preview |
| 20 | `CAPTURE` instructions | `FynCaptureTurnInstructions::render` | CAPTURE bucket (onboarding) |
| 21 | `<preview_mode>` *(cond.)* | static | preview users |
| 22 | `</context>` + `<user_message>…</user_message>` | sanitised raw message | always |

Every memory block degrades closed: a malformed corpus is caught, `report()`ed, and the block is simply omitted — a memory fault never takes down the turn (`:113-118`, `:148-151`, `:169-179`).

---

## 4. How each memory subsystem decides what to inject

Four independent selectors run per turn. Three key off the **user's message**, one keys off the **user id**.

### 4a. Semantic facts — `<knowledge>` (sparse keyword retrieval)

`SemanticRetriever::retrieve($message, Carbon::now())` (`app/Services/AI/Memory/SemanticRetriever.php:49`). No embeddings — sparse is deliberate until ~500 concurrent users. The algorithm:

1. **Tokenise** the query: `/[a-z0-9]{3,}/`, lowercased, unique (`:106`).
2. **Drop stopwords** — articles, pronouns, auxiliaries, question words (`STOPWORDS`, `:31`). Domain words (tax, isa, pension…) are never stopworded.
3. **Effective-date filter BEFORE ranking** — a fact out of its `valid_from`/`valid_to` window is dropped first, so a historical query gets historically-correct facts (`:65`).
4. **Score** each surviving fact: exact word-boundary token counts over its `haystack()` = lowercased **`title + ' ' + body`** (`SemanticFact.php:40`), plus simple ±"s" plural variants (`:132`).
5. **Relevance gate**: admit a fact only if it matches ≥ `min(2, #query-terms)` **distinct** tokens (`:58,80`). This is what stops `"the"` loading four strategy bodies on every English turn.
6. **Rank** score-desc, `fact_id` asc tiebreak (`:85`); take **top_k = 4** (`fyn.memory.semantic_top_k`).
7. **Stamp a snapshot id** — `sha256` of the sorted `factId@version` set (`:97`) — bound into the episode attestation for audit/provenance.

**Real probe** — message `"Should I move my savings into an ISA to save tax this year?"`:

```
TERMS (post-stopword): move, savings, isa, save, tax, year   | required_distinct = 2
TOTAL FACTS ON DISK: 20
score  distinct  fact_id
42     6         hv-isa-topup-vs-psa     ←┐
36     6         hv-bed-and-isa           ├ top_k=4 → INJECTED into <knowledge>
30     5         hv-isa-topup-spouse      │
29     5         hv-savings-to-spouse    ←┘
25     4         hv-pension-aa-carry-forward
20     5         hv-dividend-allowance-harvest
…                (16 more, not injected)
4      2         hv-tapered-annual-allowance
SNAPSHOT ID: 3e2a9292…7b99b
```

So this turn injects the four highest-scoring house-view facts. `hv-isa-topup-vs-psa` wins because its title and body are dense in *isa / savings / tax / save* — exactly the query's content tokens. The 16 lower-scoring facts (pensions, marriage allowance, junior products…) are correctly left out.

> **Note — `index.json` is not the retrieval path.** `storage/app/memory/semantic/index.json` is a *deploy-time* artifact written/validated by `php artisan fyn:semantic:reindex` (`FynSemanticReindex.php:44`). It is currently **stale** (50 product facts from a 1 June snapshot). Live retrieval reads the **disk corpus directly** via `SemanticCorpusLoader::all()` (recursive `File::allFiles`), which today is the 20 `house_view` facts. The probe above proves the live path uses disk, not the index.

The `<knowledge>` heading format (`FynContextAssembler.php:127`): `### {title} (source: {source})` — but house-view facts carry no `source`, so they render as plain `### {title}` followed by the body.

### 4b. Pointers — `<live_data>` (live fetch at the moment of need)

`PointerRegistry::matchPrefetch($message)` (`app/Services/AI/Pointers/PointerRegistry.php:60`). A pointer fires if any of its `triggers` is a **substring** of the lowercased message **and** its `mode` is `prefetch` or `both`. Each match runs its `handler` through `FetchDispatcher`, producing a live block (`FynContextAssembler.php:142-147`):

```
### {pointer.topic} (source: {result.sourceLabel}, as of {result.sourceVersion})
{result.value}
```

**Real probe**, same message:

```
prefetch fires: isa-annual-allowance     (handler=tax_allowance,  source=TaxConfigService)
prefetch fires: user-financial-position  (handler=user_financial, source=user records)
tool pointers:  isa-annual-allowance, recommendations
```

`isa-annual-allowance` (`triggers: [isa, allowance, …]`, `mode: both`) matches on "isa" → `TaxAllowanceHandler` pulls **live** figures from `TaxConfigService` (`app/Services/AI/Pointers/Handlers/TaxAllowanceHandler.php:31`):

```
### ISA and pension annual allowances (source: TaxConfigService, as of 2026/27)
ISA annual allowance for 2026/27: £20,000. Pension annual allowance: £60,000.
```

`user-financial-position` (`triggers: [my savings, my accounts, …]`, `mode: prefetch`) matches on "my savings" → `user_financial` handler reads the user's own records live. This is the **pointer model**: memory never freezes the £20,000 or the user's balances — it holds a *pointer to the live source* and fetches at use-time, so figures are never stale.

Pointers with `mode: tool`/`both` are *also* exposed as `fetch_*` LLM tools (§5) — `recommendations` is tool-only, so the reasoner must explicitly ask for it; it is never pre-loaded.

### 4c. Procedures — `<procedures>` (how Fyn should handle this turn)

`FynMemoryStore::proceduralContext($message)` (`app/Services/AI/Memory/FynMemoryStore.php:117`). Same §8.2 relevance contract as semantic (stopword-dropped tokens, distinct-match gate), but matched over `title + applies_when + body`. Procedures are CSJ-authored playbooks that shape reasoning.

**Real probe**, same message:

```
procedure: recommendation-routing (v1)

### Recommendation turns — route to the composed plan
## Steps
1. Choose `ground` so the reasoner runs; the reasoner must call
   `get_recommendations` or the `fetch_recommendations` skill rather than
   answering from memory.
2. If a surfaced strategy is locked behind missing data, ask the single
   unlock question the plan names — not propose the action blind.
3. Acknowledge strategies already surfaced this session …
```

> The **planner** path (`FynLoop::plannerSystemPrompt`) injects the **full** procedural corpus (no query filter) — matching `applies_when` to intent IS the planner's job. The **assembler** path injects only the **message-matched** subset (lean-prompt law, `FynContextAssembler.php:89`).

### 4d. Episodes — `<remembered>` (what Fyn remembers about this user)

`FynMemoryStore::recallContext($user->id)` (`:186`). Reads the user's episode tree (`fyn-memory/episodic/episodes/{userId}/{year}/*.md`), **newest-first**, capped at 5, rendered as the one-line summaries. The assembler calls it **without a query** (`FynContextAssembler.php:103`), so recall is recency-ordered, not relevance-ranked. Episodes are written by the planner's `learn` action governed by `episodic/RUBRIC.md` — and because the rubric is still `status: draft` (`version: 0`), `rubric()` returns `''` and no episodes are written yet, so this block is empty for every user today.

---

## 5. The tool catalogue — corpus-driven, then write-stripped

Tools are **not** PHP arrays. `XaiToolDefinitions` (`app/Services/AI/XaiToolDefinitions.php`) is a thin assembler that reads `provider=xai` `tool_schema` procedures from the corpus, decodes each fenced ```` ```json ```` body, and re-wraps it in the OpenAI `{type:function, function:{…}}` shape (`:131-187`). Adding a tool field means editing `fyn-memory/procedural/tool_schema/<module>/<tool>.xai.md`, not code. The emission order is byte-pinned by a golden-master test (`:66 ORDER`).

**Example — how `get_tax_information` becomes a live tool.** The corpus file `fyn-memory/procedural/tool_schema/tax/get_tax_information.xai.md`:

```yaml
---
procedure_id: 'tax.tool.get_tax_information'
kind: tool_schema
module: tax
provider: xai
version: 1
active: true
---
```json
{ "name": "get_tax_information",
  "description": "Get current UK tax year information… ALWAYS use this tool…",
  "parameters": { "type": "object",
    "properties": { "topic": { "type": "string",
      "enum": ["income_tax","isa_allowances","pension_allowances", …] } },
    "required": ["topic"], "additionalProperties": false },
  "strict": true }
```
```

`toolsFromCorpus(['tax.tool.get_tax_information'])` → `corpus->active(id,'xai')` → decode body → emit:

```json
{ "type": "function",
  "function": { "name": "get_tax_information", "description": "…", "parameters": {…}, "strict": true } }
```

**Pointer tools** are appended in the same pass (`pointerTools()`, `:195`): every `tool`/`both` pointer becomes a zero-arg `fetch_<pointer_id>` function whose `description` IS the pointer body. So `recommendations` → `fetch_recommendations`, `isa-annual-allowance` → `fetch_isa_annual_allowance`.

**Then the write-safety strip.** `AdviceFyn::buildToolList($user)` (`app/Services/AI/AdviceFyn.php:436`) takes the full catalogue + handoff tools, extracts the names, and removes everything in `WRITE_TOOLS` (`:158`) — every `create_*`/`update_*`/`delete_*`/`set_expenditure`/`capture_*`, plus `create_what_if_scenario` and even `navigate_to_page` (it had become an LLM escape-hatch for fabricated writes). The result is a **name allowlist** passed to `FynLoop::run(..., $allowedTools)`. So Advice Fyn physically cannot see a write tool. `delegate_to_capture` + `capture_complete` survive — the only write path is the unseen handoff.

This is **belt-and-braces** with the CoALA `GroundGate`: if a write surface somehow reaches dispatch in read-only advice, `HasAiChat` (`~:1095`) mechanically rejects it, writes an `ai_audit_events` row `status:stripped`, and returns a safe observation — the write is never executed.

---

## 6. The planner call — one forced `plan` tool call

Before the reasoner streams, `FynLoop::run` consults the **planner** (`app/Services/AI/Loop/Planner.php`). It is its own small LLM call, *forced* (`tool_choice`) to emit exactly one `plan` tool call. The schema (`Planner::planSchema`, `:300`):

```json
{ "name": "plan",
  "description": "Choose the single next action for this turn…",
  "parameters": { "type": "object",
    "properties": { "action_type": { "enum": ["reason","retrieve","learn","ground","no_action"] },
      "prompt_template_id": {…}, "store": {…}, "query": {…}, "payload": {…},
      "surface": {…}, "args": {…} },
    "required": ["action_type"] } }
```

For a normal question the planner returns `{"action_type":"reason"}`. v1 ships **one** reasoning template, so `reason` runs today's default reasoner with **no override** — byte-identical to the pre-planner turn (`FynLoop.php:213-223`). The planner only **routes**; it never writes prose, and the write-safety decision is *not* made here (a `ground` action is GroundGate-gated downstream). A provider hiccup degrades to a default `reason` rather than dropping the turn (`Planner.php:88-95`). The planner's own token spend is attributed separately (`stage=planner`, `FynLoop.php:330`).

> Cycle cap = 8 (`fyn.cycle_cap`), retrieve budget = 3. `retrieve`/`learn` are effectively no-ops on dev until the stores have content, so a real turn is: `thinking` → plan→`reason` → stream.

---

## 7. The reasoner tool call + result (the live-data round-trip)

Inside the streamed reasoner turn, the LLM has Payload A (system), Payload B (the assembled `<context>`/`<user_message>`), and the allowlisted tool catalogue. Because `<regulatory_compliance>` rule 7 forbids quoting tax values from memory, the model calls:

```
→ tool_call:  get_tax_information({ "topic": "isa_allowances" })
```

`CoordinatingAgent` dispatches it to `TaxConfigService` and streams back a `tool_result`:

```
← tool_result: { "tax_year":"2026/27", "isa_allowance":20000, "junior_isa":9000, … }
```

The model then composes its answer from the user's own figures (from `<financial_context>`) + the freshly-fetched allowance + the four injected house-view facts, hedged per `<voicing_rules>`, and ends with the verbatim FCA signpost line. (Had the user instead said *"add my new Cash ISA"*, the model would emit `delegate_to_capture` — intercepted in `FynLoop::interceptHandoff` (`:444`), routed to `OnboardingChatDirector::handleInlineCapture`, and the synthetic `handoff` event dropped so the UI never sees the switch.)

---

## 8. Worked example — the full assembled turn

**User message:** `"Should I move my savings into an ISA to save tax this year?"`
**Classification:** recommendation-class (non-factual) → buckets `IDENTITY + POSITION + READINESS`.

### Payload A (system role) — *unchanged, byte-identical*
→ the entire `FynSystemPrompt::text()` from §2 (identity / security / scope / personality / response_format / instructions / regulatory_compliance / tool_use). Not repeated here because it never varies.

### Payload B (injected as the user turn) — *rebuilt this turn*

```text
<context>
Current tax year: 2026/27
You are speaking with: «James»
Situation: advice
<user_profile>
«narrative profile — age, household, employment, risk attitude … (AdvicePromptBuilder::buildUserProfile)»
</user_profile>
<current_context>
«the page the user is on, e.g. Cash & Savings»
</current_context>

<procedures>
## Procedures

### Recommendation turns — route to the composed plan (applies when: the user asks what they should do, asks for recommendations, strategies, ways to save tax …)
## Steps
1. Choose `ground` so the reasoner runs; the reasoner must call `get_recommendations`
   or the `fetch_recommendations` skill rather than answering from memory.
2. If a surfaced strategy is locked behind missing data, ask the single unlock question…
3. Acknowledge strategies already surfaced this session…
</procedures>

<knowledge>
### ISA top-up — wrapping cash that earns interest beyond the Personal Savings Allowance
Interest on ordinary savings is tax-free only up to the Personal Savings Allowance…
«full hv-isa-topup-vs-psa body»

### Bed and ISA — harvesting capital gains within the annual exempt amount
A bed-and-ISA move sells holdings in the taxable account and immediately rebuys them inside the ISA…
«full hv-bed-and-isa body»

### Spouse ISA top-up — opening or topping up the partner's own individual ISA
Every adult has their own ISA allowance each tax year…
«full hv-isa-topup-spouse body»

### Savings to spouse — gifting cash to use the lower earner's stacked allowances
In a single-earner couple, cash held in the earning partner's name has its interest taxed…
«full hv-savings-to-spouse body»
</knowledge>

<live_data>
### ISA and pension annual allowances (source: TaxConfigService, as of 2026/27)
ISA annual allowance for 2026/27: £20,000. Pension annual allowance: £60,000.

### The user's own accounts, balances and records (source: user records, as of «v»)
«live read of the user's savings/investment accounts»
</live_data>

<financial_context>
«sized engine analysis for the savings/tax classification — balances, taxed interest, headroom»
</financial_context>
<existing_records>
«the user's accounts so the model UPDATEs not CREATEs»
</existing_records>

«readiness lean block — per-user READY/BLOCKED matrix»

<financial_knowledge>
«QueryKnowledge for this classification»
</financial_knowledge>

<voicing_rules>
Claim tiers govern how you state guidance:
- MECHANICAL claims … state directly and quantified, show the working inline …
- JUDGEMENT claims … hedge ("you may want to consider") and signpost regulated advice.
Proactivity: after answering, you MAY surface AT MOST ONE additional high-value strategy …
</voicing_rules>
</context>
<user_message>
Should I move my savings into an ISA to save tax this year?
</user_message>
```

> `«…»` = real-data blocks that need a live user + engine run (illustrative skeletons). Everything outside `«…»` — the procedure, the four `<knowledge>` headings, the `<live_data>` ISA/pension figures, the `<voicing_rules>` — is the **actual** output of the live corpus + handlers for this exact message.

`<overlay>` and `<fca_block>` are absent (no active prose procedures). `<remembered>` is absent (rubric is draft → no episodes). `<preview_mode>` is absent (real user). If this had been a *factual* turn, only `IDENTITY` would render and `<financial_context>`/`<existing_records>`/readiness would all drop.

---

## 9. End-to-end lifecycle

```
POST /api/ai-chat/conversations/{id}/messages
  │
  ▼  AiChatController::sendMessage      3-part predicate → AdviceFyn (read-only)
  │
  ▼  AdviceFyn::handle
  │     • QueryClassifier::classify → {primary}
  │     • buildToolList → catalogue (corpus-driven) minus WRITE_TOOLS  ── name allowlist
  │
  ▼  FynLoop::run
  │     • Planner::plan → forced `plan` tool call → Action(reason)     ── PAYLOAD: planner system
  │     • emit {type: thinking}
  │
  ▼  FynLoop::reason → CoordinatingAgent::chatWithPromptOverride
  │     ┌── PAYLOAD A: FynSystemPrompt::text()           (system role, cached)
  │     ├── PAYLOAD B: FynContextAssembler::build()       (replaces user msg)
  │     │     selectors run here:
  │     │       SemanticRetriever.retrieve(msg)   → <knowledge>   (top-4)
  │     │       PointerRegistry.matchPrefetch(msg)→ <live_data>   (live fetch)
  │     │       FynMemoryStore.proceduralContext  → <procedures>  (matched)
  │     │       FynMemoryStore.recallContext      → <remembered>  (empty today)
  │     │       AdvicePromptBuilder (POSITION/READINESS buckets)
  │     └── TOOLS: xAI catalogue, write-stripped
  │
  ▼  LLM stream  ── (real call; out of scope here)
        → get_tax_information(topic) → tool_result → composed, hedged answer + FCA signpost
        (write intent instead → delegate_to_capture → interceptHandoff → inline capture)
```

---

## 10. File map

| Concern | File |
|---|---|
| Dispatch (which Fyn) | `app/Http/Controllers/Api/AiChatController.php:236` |
| Shared turn loop | `app/Services/AI/Loop/FynLoop.php` |
| Planner (forced `plan` call) | `app/Services/AI/Loop/Planner.php` |
| Static system prompt | `app/Services/AI/Fyn/FynSystemPrompt.php` |
| Per-turn assembler | `app/Services/AI/Fyn/FynContextAssembler.php` |
| Bucket selector | `app/Services/AI/Fyn/FynContextSelector.php` |
| Turn context VO | `app/Services/AI/Fyn/FynTurnContext.php` |
| Prompt-mode + injection | `app/Traits/HasAiChat.php:1011,1048` |
| Semantic retrieval | `app/Services/AI/Memory/SemanticRetriever.php` + `SemanticCorpusLoader.php` + `SemanticFact.php` |
| Pointers | `app/Services/AI/Pointers/PointerRegistry.php` + `Handlers/*` |
| Procedural / episodic store | `app/Services/AI/Memory/FynMemoryStore.php` |
| Tool catalogue (xAI) | `app/Services/AI/XaiToolDefinitions.php` |
| Write-tool strip | `app/Services/AI/AdviceFyn.php:158,436` |
| Memory corpus (on disk) | `fyn-memory/{semantic,procedural,episodic}/**` |
| Config | `config/fyn.php` (`prompt_architecture`, `memory`, `cycle_cap`, `queue`) |

## 11. Caveats / current-state notes
- **`index.json` is stale and not load-bearing** — live retrieval is disk-direct. Run `fyn:semantic:reindex` to refresh it; it is a health/validation artifact only.
- **No semantic facts have a live owner** — the 20 on disk are all `house_view` (source-less narrative). Tax numbers/user data are pointers, never frozen here.
- **Episodic recall is empty today** — `episodic/RUBRIC.md` is `status: draft`/`version: 0`, so `learn` writes nothing yet.
- **Overlays/fca_block emit nothing** — `a1-answer-first`/`a2-ack-hygiene` are `active: false`; no `fca_block` procedures authored.
- **Planner is a router** — v1 ships one reasoning template, so the `reason` path is byte-identical to the pre-planner turn; the planner exists as the substrate for collapsing the two write-states into one Fyn.

---

## 12. Worked example B — the onboarding / capture turn

The §8 example was the **read-only advice** state. The mirror state is the **write** state — the only state that enters data. It reaches the LLM through two doors, but both converge on the **same CAPTURE bucket + `data_capture` persona + writes-allowed** assembly. The contrast with §8 is the whole point: same static Payload A, a *different* Payload B, and a *different* tool posture.

### 12a. Door 1 — a mid-flow onboarding turn (bubble flow)

**User:** mid-onboarding (`onboarding_completed = false`, `onboarding_fyn_step = 'base_savings'`). Fyn just asked them to describe their savings.
**Message:** `"Halifax Cash ISA £10,000 and a Nationwide saver £5,000"`

**Dispatch** — the 3-part predicate is **true** → `OnboardingChatDirector::handleUserMessage` (`app/Services/Onboarding/OnboardingChatDirector.php:111`) → state machine → `handleAssetCaptureTurn` (`:2077`). It streams through `FynLoop::stream` with the **unified focus set** to the module (`savings`), persona `data_capture`, and a **focus-scoped tool list**.

- **Mode** → `onboarding` (because the agent's `unifiedOnboardingFocus` is non-null, `HasAiChat.php:1062`). → buckets `IDENTITY + CAPTURE` (`FynContextSelector.php:19`).
- **Tools** → `OnboardingPromptBuilder::toolsForFocus('savings')` = `['create_savings_account']` (`:101`). **Writes are allowed here** — this is the data-entry state. No `WRITE_TOOLS` strip, no `GroundGate` rejection.
- **Persona** → `data_capture`.

**Payload A** — *identical bytes to §8* (the static `FynSystemPrompt::text()` never changes; the security rules' own carve-out already permits "add my…" requests).

**Payload B** — the assembler now emits the CAPTURE shape:

```text
<context>
Current tax year: 2026/27
You are speaking with: «James»
Situation: onboarding — focus: Cash & Savings        ← mode line flips (FynContextAssembler.php:69)
<user_profile> … </user_profile>
<current_context> … </current_context>

«<knowledge>/<live_data> may still assemble (semantic + pointers are mode-independent —
 "isa" in the message would match) but the capture instructions below override them»

<asset_capture_turn>
The user is onboarding. They just selected the Cash & Savings module … Their next message
describes one or more records.
MULTI-ENTITY RULE (highest priority): emit ONE tool_use block PER record in your FIRST response …
YOUR SINGLE JOB: call the appropriate create_ tool for EACH record …
Off-script guardrail (FR-M14): acknowledgment text MUST be EXACTLY ONE sentence of 15 words
or fewer, or empty. Do NOT ask any question … Do NOT mention property/mortgages … (out of scope
for this Cash & Savings turn).
Tools available to you in this turn:
create_savings_account
</asset_capture_turn>
</context>
<user_message>
Halifax Cash ISA £10,000 and a Nationwide saver £5,000
</user_message>
```

(`<asset_capture_turn>` is `FynCaptureTurnInstructions::render('Cash & Savings', 'create_savings_account')`, `app/Services/AI/Fyn/FynCaptureTurnInstructions.php:14`.)

**Result:** the model emits `create_savings_account` **× 2** in one turn (multi-entity rule), each persists via `CoordinatingAgent`, and the model replies with a single ≤15-word confirmation — `"Recorded — two savings accounts totalling £15,000."`. The state machine then advances `onboarding_fyn_step` to the next state. Note what is **absent** vs §8: no `<voicing_rules>`, no `<financial_context>`, no `<financial_knowledge>`, no FCA signpost — all advice-only / POSITION-gated (`FynContextAssembler.php:188,219`).

### 12b. Door 2 — the advice → capture handoff (`delegate_to_capture`)

**User:** post-onboarding (`onboarding_completed = true`, `onboarding_fyn_step = null`) → routes to **Advice Fyn** (§1).
**Message:** `"Add my new Vitality income protection — £2,000 a month benefit."`

This is a write intent reaching the **read-only** state. It is caught one of two ways and both land in the same place:

1. **Tier-1 (deterministic):** `WriteIntentClassifier` matches a verb+entity pattern *before* the LLM runs (`AdviceFyn.php:278`) → straight to `handleInlineCapture`.
2. **Tier-2 (LLM safety-net):** the classifier misses, the reasoner runs read-only (no `create_*` in its tool list), sees `<handoff_guidance>` in Payload A, and emits:

```
→ tool_call: delegate_to_capture({ "reason": "user wants to add an income protection policy",
                                    "entity_types": ["protection_policy"] })
```

`FynLoop::interceptHandoff` (`app/Services/AI/Loop/FynLoop.php:444`) consumes the synthetic `handoff` SSE event — **it never reaches the frontend** (INV-2.4.1) — validates the payload, builds a `CaptureContext`, and calls `OnboardingChatDirector::handleInlineCapture` (`:2994`).

`handleInlineCapture` then re-enters the **same capture assembly as 12a**:

- `unifiedFocus = inferFocusesFromEntityTypes(['protection_policy'])[0] ?? 'savings'` → `'protection'` (`:3020`). The **`?? 'savings'` fallback is the deflection guarantee** — a write that reached here has already been cleared, so it must *stay* in capture mode even if the entity maps to no focus; a null focus would demote the turn to advice and the model would deflect with the security refusal (the June13 §6c bug).
- **Tools** → `captureToolSet($context)` (`:3144`) — the broad create/update/delete set (writes allowed).
- **Persona** → `data_capture`; `persistUserMessage: false` (the outer advice turn already saved the user message — re-saving would duplicate the `ai_messages` row, the BS-14 regression).
- Streamed through `FynLoop::stream` with `unifiedFocus = 'protection'`, so Payload B again carries `<asset_capture_turn>` (focus **Protection**, `toolsForFocus('protection') = ['create_protection_policy']`).

**Result:** the model emits `create_protection_policy`, the record persists, and the inline-capture stream **is** the user-visible response. The user never sees a state switch — input placeholder unchanged, no "capturing" pill, no `persona_state_change` event (the canonical Two-Fyn invariant).

### 12c. The three states side by side

| | §8 Advice (read) | 12a Onboarding (write) | 12b Advice→capture (write) |
|---|---|---|---|
| Dispatch predicate | false | true | false, then handoff |
| `mode` / buckets | advice / `IDENTITY+POSITION+READINESS` | onboarding / `IDENTITY+CAPTURE` | onboarding (via `unifiedFocus`) / `IDENTITY+CAPTURE` |
| Persona | advice | `data_capture` | `data_capture` |
| Payload A | `FynSystemPrompt::text()` | **same bytes** | **same bytes** |
| Tools | catalogue **minus `WRITE_TOOLS`** | `toolsForFocus(focus)` | `captureToolSet()` |
| Writes? | no (GroundGate + strip) | **yes** | **yes** |
| Distinctive B block | `<voicing_rules>`, `<financial_context>`, `<knowledge>` | `<asset_capture_turn>` | `<asset_capture_turn>` |

The single static Payload A serving all three states — with write-safety enforced purely at **dispatch + tool-gating**, never in the prompt text — is the canonical "one prompt, two write states, converging to one Fyn" contract.

---

## 13. Diagram

A flow chart of this whole lifecycle (both branches + the `delegate_to_capture` loop) is at:

- Repo: `docs/diagrams/fyn-turn-lifecycle.excalidraw`
- Vault: `fynlaBrain/Diagrams/fyn-turn-lifecycle.excalidraw`

Open by drag-and-drop into the local Excalidraw, or File → Open. If browsing the vault in Obsidian, it is listed in `Diagrams/Diagrams Index`.
