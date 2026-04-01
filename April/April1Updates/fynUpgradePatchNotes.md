# Fyn AI Phase 2 — Patch Notes

**Version:** v0.9.5
**Date:** 1 April 2026
**Branch:** `fynImprovement` (42 commits)
**Stats:** 45 files changed, 7,097 insertions, 176 deletions
**Tests:** 71 new tests (145 assertions), full regression 2,139/2,148 passing

---

## System Prompt Refactor

The 670-line monolithic system prompt in `HasAiChat.php` has been decomposed into a **10-layer composable architecture** via `SystemPromptBuilder`:

| Layer | Component | Type |
|-------|-----------|------|
| 1 | Core Identity | STATIC — identity, security, scope, personality, response format |
| 2 | Compliance Rules | STATIC — FCA hedging language, 17 banned acronyms, no-icons, joint ownership |
| 3 | FCA Process | STATIC — 6-step advice process, tool usage, data creation guidance |
| 4 | User Profile | DYNAMIC — name, age, income, employment, family |
| 5 | Financial Position | DYNAMIC — net worth, module metrics, ranked recommendations |
| 6 | Existing Records | DYNAMIC — record summaries filtered by query type |
| 7 | Data Completeness | DYNAMIC — prerequisite gate status, navigation rules |
| 7b | Review Due | DYNAMIC — data changes since last advice, annual review prompts |
| 8 | Query Knowledge | DYNAMIC — per-domain financial knowledge (RAG) |
| 8b | Tools & Triggers | DYNAMIC — mandatory tool calls, decision tree triggers |
| 9 | KYC Status | DYNAMIC — data completeness check result (PASS/BLOCKED) |
| 10 | Page Context | DYNAMIC — current page context |

Each layer is a separate class, independently testable, and assembled per-query based on classification.

---

## Multi-Label Query Classification

Every user message is classified before the AI sees it. The `QueryClassifier` identifies a **primary type** plus **related types** — a pension question also triggers tax, savings, and affordability checks.

**22 query types** across 7 modules: protection (2), savings (3), retirement (3), investment (3), estate (2), goals (1), tax (1), plus property, income, holistic, general, data_entry, navigation, affordability.

**Critical bypass:** `data_entry` and `navigation` queries skip the entire FCA process — no KYC check, no knowledge injection, no mandatory tools. "I have a pension with £50,000" creates the record immediately. "Take me to my property page" navigates instantly.

**Implicit related types:** Pension advice always adds tax + savings + affordability. Any "maximise" query adds emergency fund check. Holistic health queries check all modules.

---

## KYC Gates — Data Check Before Advice

`KycGateChecker` verifies data completeness before Fyn gives advice:

- **Universal requirements:** date of birth, marital status, employment, income, expenditure
- **Module-specific:** protection needs dependants, investment needs risk profile, estate needs assets
- **Checks ALL classified modules** — pension + savings + affordability all checked

If data is missing, Fyn lists what's needed, offers to help enter it conversationally, and navigates to the **exact correct page** (not a guess — routes specified in the prompt).

---

## Knowledge RAG

Instead of injecting all 1,800 tokens of financial knowledge into every prompt, `QueryKnowledge` returns only the domains relevant to the classified query:

- **Pension query:** pension + income + affordability knowledge (~1,328 tokens saved)
- **Data entry:** no knowledge at all (~3,109 tokens saved)
- **Holistic review:** all domains (unchanged)

Records and recommendations are also filtered by classification — a pension question only sees pension records and retirement recommendations.

---

## Mandatory Tool Sequences

`<required_tools>` block injected per query type tells the AI which tools to call BEFORE responding:

- Pension questions → `get_tax_information(pension_allowances)`, `get_module_analysis(retirement)`
- Estate IHT questions → `get_tax_information(inheritance_tax)`, `get_module_analysis(estate)`
- Data entry → no mandatory tools

---

## Decision Tree Binding

`<relevant_triggers>` block maps each query type to the ActionDefinition triggers that should be referenced:

- Pension → `employer_match`, `contribution_increase`, `tax_relief`, `annual_allowance_exceeded`
- Protection → `life_insurance_gap`, `income_protection_gap`, `critical_illness_gap`
- Holistic → all 51 triggers across all modules

Recommendations now include description, estimated saving (£ amounts), action steps, and trigger keys in the prompt — giving the AI specific calculation results to reference rather than inventing its own numbers.

---

## Response Validation

`StructuredResponseValidator` checks every AI response before it reaches the user:

| Check | Severity | Action |
|-------|----------|--------|
| 17 banned acronyms (IHT, CGT, SIPP, NRB, etc.) | High | Logged |
| Exposed record IDs (ID:123) | High | Stripped by sanitiser |
| Emoji / Unicode symbols | Medium | Logged |
| Planning jargon (waterfall, prioritise affordability) | Medium | Logged |
| Filler phrases (Certainly!, Of course!) | Low | Logged |
| Missing £ amounts in advice | High | Logged |
| HTML injection (script tags) | Critical | Stripped by sanitiser |
| Context block leaks ([Context: ...]) | High | Stripped by sanitiser |

Violations stored in message metadata for audit review.

---

## Review System

- **`ai_advice_logs` table:** logs every advice interaction with query type, classification, KYC status, tools called, and user data snapshot (income, expenditure, employment, marital status at time of advice)
- **Data change detection:** `AdviceReviewService` compares current user data against the snapshot from their last advice — flags income changes >£1,000, expenditure changes >£100, employment/marital changes
- **Annual review prompts:** modules where advice is >12 months old get flagged in the system prompt — Fyn offers to review

---

## AI Audit Dashboard

New admin tab at `/admin` → **AI Audit** — three-panel layout for full audit trail:

- **Left panel:** all users with AI conversations, searchable
- **Middle panel:** conversations for selected user, sorted by date
- **Right panel:** full message thread with:
  - User message and Fyn response
  - **Expandable system prompt** — the exact prompt sent to the LLM (stored per message)
  - **Tool calls** — which tools were called with inputs and results
  - **Classification** — query type + related types
  - **KYC status** — PASS/BLOCKED with missing items
  - **Validation violations** — any compliance issues detected
  - **Token usage** — input/output token counts

---

## Admin Panel UI

Tab bar grouped into dropdowns:
- **Users** → User Metrics, User Management
- **AI** → AI Audit, AI Provider

Cleaner navigation with fewer top-level tabs.

---

## Other Fixes

- **Chat scroll:** user message scrolls to top on send, rolling status messages while Fyn thinks
- **Suggestion bypass:** suggestions now send directly, bypassing canSend reactivity issue
- **Income field:** removed "Other Income" — all income must be categorised by type
- **Affordability rules:** added to Fyn's knowledge base — checks surplus, emergency fund, debt before recommending contributions
- **Response quality:** removed irrelevant concepts, enforced specific £ amounts, banned planning jargon
- **UserMetricsServiceTest:** fixed date edge case on month boundaries
- **xAI token tracking:** added `stream_options.include_usage` for token counts
