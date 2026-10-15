# Fyn widget: plan to extract Fyn, the calculations and the tax configuration into a new application

**Date:** 14 October 2026 (amended the same day after CSJ's correction, section 1)
**Status:** PLAN ONLY. Nothing has been copied, changed or committed. Another agent is working in `/Users/CSJ/Desktop/fynla`, so this plan reads from git, never from that live checkout.
**Source snapshot:** `dev` at `01290a768` (13 October 2026, 10:01 BST). Copy from this pinned commit (section 8.1), so the other agent's uncommitted work is not picked up.

---

## 1. The product (CSJ, 14 October 2026)

> "this is not a second Fyn, this is another application entirely, different use case, different users and different application."

- **What it is:** a new, separate hosted product. A business installs the Fyn widget in its own banking or financial app, and that business's clients get a Fyn overlay that gives them financial advice.
- **Data:** Fyn works from **the business's database and client information**, not from Fynla's.
- **Dashboard (built later):** the business uses it to **connect the widget to its database** and **customise Fyn to whatever it wants it to be**.
- **Hosting:** **we host Fyn and the large language model (LLM).**
- **What ships with the widget:** the **calculations and the tax configuration** are extracted and ship with it.

Because this is a separate application with different users, Rule 20 ("every Fyn change once, in one place") governs Fyn inside Fynla. It does not tie the two products together. Each application has one Fyn of its own.

**This plan covers step one:** a separate folder holding a fully working Fyn, with its prompts, memory and tools, plus the calculation engines and tax configuration it needs. CSJ builds the widget from there. Section 10 lists the later phases (tenancy, the database connector, the widget embed and the dashboard), so nothing done now blocks them.

---

## 2. What Fyn is today (the parts to extract)

Source of truth: `April/April24Updates/spec/00-canonical.md` (summarised in `.claude/skills/fyn-architecture/SKILL.md`).

```
chat client (today: web, /m, native; tomorrow: the widget)
   |  POST /api/ai-chat/conversations/{id}/messages   (one endpoint, SSE stream)
   v
AiChatController::sendMessage            app/Http/Controllers/Api/AiChatController.php:213
   |  dispatch predicate (onboarding write state, or advice read-only state)
   +--> OnboardingChatDirector  (WRITES: bubbles, capture forms, inline capture)
   |        app/Services/Onboarding/OnboardingChatDirector.php  (9,744 lines; handleInlineCapture at :8838)
   +--> AdviceFyn               (READ-ONLY: write tools stripped, AdviceFyn::WRITE_TOOLS at :170)
   v
Prompt = FynSystemPrompt::text()  (app/Services/AI/Fyn/FynSystemPrompt.php:97)
       + FynContextAssembler::build() per turn (app/Services/AI/Fyn/FynContextAssembler.php:73)
   v
HasAiChat trait (provider loop, history, streaming)   app/Traits/HasAiChat.php (2,423 lines)
   v
Tools -> CoordinatingAgent::executeTool  app/Agents/CoordinatingAgent.php:1001 (7,375 lines)
   v
CALCULATIONS: module agents + calculation services
   v
TAX CONFIGURATION: TaxConfigService -> tax_configurations (one JSON row per tax year)
   v
Fynla financial data model (Eloquent models -> MySQL)
```

---

## 3. Findings that shape the extraction

### 3.1 Fyn reaches 42% of the backend

I traced every PHP class reachable from the Fyn core: the `AI` and `Onboarding` services, `HasAiChat`, `HasAiGuardrails`, `CoordinatingAgent` and `AiChatController`. The trace followed `use` statements, fully qualified `\App\...` references and same-folder class names.

| Reachable from Fyn | Count |
|---|---|
| PHP files | **557 of 1,334 in `app/` (42%)** |
| Lines of code | **153,192** |
| Services | 349 (AI 101, Stores 35, Estate 27, Coordination 26, Onboarding 25, Retirement 18, Investment 18, Tax 12, Savings 11, Protection 11, …) |
| Models | 116 of 125 |
| Events | 30 |
| Agents | all 9 |

This is a floor, not a ceiling. It cannot see classes the container resolves by string, event listeners, observers, or service-provider bindings (`app/Providers/AppServiceProvider.php:19-39, 110`).

### 3.2 The calculations are built on Fynla's data model

The calculation services take a `User` and read Fynla's models (`DCPension`, `Property`, `SavingsAccount` and the rest). They are not pure functions of plain inputs.

| Service folder | Files | Import models or take a `User` | Run database queries |
|---|---|---|---|
| Tax | 49 | 37 | 8 |
| Investment | 59 | 31 | 20 |
| Estate | 34 | 30 | 15 |
| Coordination | 27 | 18 | 12 |
| Retirement | 19 | 19 | 9 |
| Savings | 14 | 8 | 3 |
| Plans | 13 | 11 | 10 |
| Goals | 12 | 12 | 7 |
| Protection | 11 | 10 | 5 |
| Property, NetWorth, Income, Expenditure, Risk, Trust, Actions, Shared | 21 | 17 | 12 |
| **Total** | **258** | **193** | **102** |

**Consequence:** the engines cannot be pointed straight at a business's own tables. Either the business's client data is mapped into Fynla's data model (section 4.1), or the 193 model-bound services are rewritten. The data model therefore ships with the calculations.

### 3.3 The tax configuration extracts cleanly

- **Storage:** one versioned JSON document per tax year in `tax_configurations`. It is written through `app/Services/Stores/TaxConfigStore.php` (342 lines) and read through `app/Services/TaxConfigService.php` (923 lines: income tax, National Insurance, ISA, pension, inheritance tax, capital gains tax, dividends, stamp duty, gifting, trusts, reliefs, benefits, domicile, savings, protection, retirement, investment and estate settings).
- **Years:** `database/seeders/TaxConfigurationSeeder.php` (1,760 lines) seeds six UK tax years, 2021/22 to 2026/27, and marks the active one from a single constant (`ACTIVE_TAX_YEAR`).
- **Dependencies:** `TaxConfigService` depends only on `TaxConfiguration`, `TaxConfigurationAudit`, `TaxConfigStore`, `TaxConfigNormaliser`, `ReferenceDataStore`, the `ReferenceDataUpdated` event, `IncomeTaxBands`, `TaxDefaults`, `Money`, `FinancialCalculationException` and `User` (for `blindPersonsAllowanceFor`).
- **Result:** it moves as a small, self-contained unit.

### 3.4 Most base tables come from a schema dump, not migrations

`tax_configurations`, `tax_product_references`, `actuarial_life_tables` and the other early tables are created by `database/schema/mysql-schema.sql`. No `create` migration exists for them in `database/migrations/`. The schema dump must be copied, or the new database cannot be built.

### 3.5 Fyn's identity is hardcoded, so customisation needs work

"Fyn" and "Fynla" are written into the prompts and the memory corpus:

| File | Mentions |
|---|---|
| `FynSystemPrompt.php` | 6 |
| `Prompts/CoreIdentity.php` | 4 |
| `Prompts/EmptyDataGuard.php` | 3 |
| `Prompts/ComplianceRules.php` | 2 |
| `Prompts/FcaProcessInstructions.php` | 2 |
| `FynContextAssembler.php` | 20 |
| `OnboardingChatDirector.php` | 45 |
| `CaptureForms.php` | 10 |
| `fyn-memory/` | 36 files (52 "Fyn", 70 "Fynla") |

Letting a business rename and reshape Fyn means turning these into per-business settings (section 10, phase 3). The copy keeps them unchanged.

---

## 4. Architecture for the new product

Section 4a records CSJ's decisions. The rest of section 4 is the recommended shape, so that the extraction suits it. None of it is built in step one.

### 4a. Decisions taken (CSJ, 14 October 2026)

| Topic | CSJ's answer | Where it lands |
|---|---|---|
| Target folder | `/Users/CSJ/Desktop/fyn-widget/` is good | 8.2 |
| AI provider | **OpenAI, `gpt-6-luna`** | 4.6, 8.4 |
| Databases | **One database per business** (CSJ confirmed, 14 October 2026). | 4.2 |
| Customisation | "around colours, name of agent etc". The greeting and deep links into the host app are also included (confirmed). "There are other areas to customise, which we can get into once we start work on the dashboard." It does not change how Fyn reasons, its guards or its compliance wording. | 4.4 |
| Tax figures | "we will update the tax figures for the clients". Fynla maintains the widget's tax data each year for every business. | Package C |
| Regulation | "The advice will be given under the clients licence, so Fynla becomes purely a development company for them." The business is the regulated firm. Fynla builds and hosts the technology. | Section 5 |

("Clients" in CSJ's wording means the businesses that buy the widget. Their customers are called "clients" or "end users" elsewhere in this plan.)

### 4.1 Fyn's data model, filled by a connector

**What "connector" means.** It is the part of the product that gets a business's customer data out of the business's own system and into the shape Fyn's calculations read.

- **The problem it solves:** Fyn's calculations only understand Fynla's data structure (3.2). They read `users.date_of_birth`, a `dc_pensions` row with a `current_value`, a `savings_accounts` row marked as an ISA, and so on. A bank will hold the same facts under its own names, for example a `customers` table with a `dob` column, or an `accounts` table with a product code of `ISA01`. The connector is the translator between the two.
- **What a business does in the dashboard (phase 5):**
  1. Gives Fyn access to its data.
  2. Maps its fields to Fyn's. For example, its `customers.dob` is Fyn's date of birth, its product code `ISA01` is a cash ISA, and its `pension_value` is a pension's current value.
  3. Chooses when the data is refreshed: on a schedule, or each time a customer opens the widget.
- **What runs behind it:** a sync job reads the business's records, applies the mapping, checks each value with the same validation Fynla's forms use (`app/Services/Stores/`), and writes it into the business's Fyn database. The calculations, tools and memory then run exactly as they do in Fynla.
- **How Fyn gets access (for CSJ to choose in phase 5):**
  - **a. A read-only connection to the business's database.** This is closest to "connect the widget to a database". Many banks will not open their databases to an outside host.
  - **b. The business sends its data to an API we provide**, either each night or when a record changes. This is the usual way banks share data with a supplier. The mapping still lives in the dashboard.
  - **c. A file upload or scheduled export** (CSV or similar) for smaller firms.
  - **Recommendation:** build b first, because it works for every business and needs no access into their systems. Add a later as an option for businesses that allow it.
- **Recommendation:** keep Fynla's data structure as the widget's own "Fyn data model", filled by the connector. The calculations, tools, pointers and memory then keep working unchanged.
- **Alternative:** rewrite the calculations to read the business's database directly. That touches the 193 model-bound services, every figure would need re-proving against `TaxConfigService`, and it would have to be redone for every business's different structure.

### 4.2 One database per business (CSJ agreed, see 4a)

- **Shape:** a central database holding the businesses, their Fyn settings (name, colours), keys and usage, plus **one database per business** holding its customers' financial data, conversations and episodes.
- **Why:** the 557 files have no business column, so per-business databases need no change to them. One business's customer data is also kept physically apart from another's.
- **Alternative:** add a business identifier to every table and query, which touches every model.

### 4.3 How a client is identified

- **Recommendation:** the business's server signs a short-lived token naming its client. The widget backend checks it with that business's secret and maps it to the client's record.
- **Why:** today Fyn identifies people through Sanctum logins on Fynla's own accounts (`routes/api.php:1510`), which the business's clients will not have.

### 4.4 What a business can customise, and what stays fixed (CSJ decided, see 4a)

- **Customisable in the dashboard** (CSJ: "colours, name of agent etc"):
  - the agent's name (replacing the hardcoded "Fyn" and "Fynla", 3.5);
  - colours, and the character or logo shown in the widget;
  - the greeting, and deep links into the host app in place of `navigate_to_page`'s Fynla routes (CSJ confirmed);
  - further areas CSJ will set out when dashboard work starts (phase 7).
- **Not customisable by the business:**
  - how Fyn reasons and answers;
  - the compliance wording (`Prompts/ComplianceRules.php`, `Prompts/FcaProcessInstructions.php`), which Fynla sets per business to match that business's licence (section 5);
  - the figure guards (`CertaintyFilter`, `ArithmeticCheck`);
  - read-only advice (`AdviceFyn::WRITE_TOOLS`);
  - tax figures only from `TaxConfigService`;
  - `store: false` at the AI provider (`app/Services/AI/AiProvider.php:244`).

### 4.5 Where Fyn's captured data goes (still open)

Fyn's write state (onboarding and capture forms) enters data today. In the widget, a client may tell Fyn something the business's database does not hold. It could be kept in the business's Fyn database only, or written back to the business's database through the connector.

**DECISION (CSJ)**, later. The copy keeps the write state working as it is.

### 4.6 Hosting the LLM

- **Provider (CSJ, 4a):** OpenAI, model `gpt-6-luna`. It already runs on Chat Completions with function calling (`AiProvider.php:20-25`, citing https://developers.openai.com/api/docs/models/gpt-6-luna). Set `AI_PROVIDER=openai`, `OPENAI_MODEL=gpt-6-luna`, `OPENAI_ADVANCED_CHAT_MODEL=gpt-6-luna` and `OPENAI_VISION_MODEL=gpt-6-luna` (the keys in `config/services.php:52-58`).
- **Keys:** our own OpenAI key, held in the environment, never the business's. A separate key or project from Fynla's keeps widget usage and billing apart.
- **Retention:** `store: false` stays on every OpenAI call (`AiProvider.php:244`). Any provider added later must have its retention default checked before its first call.
- **The conversation summariser** (memory 7) follows the active provider through `AiProvider::helperConnection()` (`AiProvider.php:226-229`, used at `ConversationSummariser.php:126`), so OpenAI covers it and no xAI key is needed. Found: the class's header comment (`ConversationSummariser.php:26-33`) still says it runs on xAI. That is out of date, not a dependency.
- **Cost:** cost per business comes from the existing cost tracking (`app/Services/AI/Cost/`, `AiCostAttribution`).
- **Limits:** the per-tier weekly limit (`app/Services/Tiers/`, `AiDailyUsage`) becomes a limit per business.

---

## 5. Regulatory and data-protection points (before any real client uses it)

1. **Advice runs under the business's licence (CSJ, 4a).** "The advice will be given under the clients licence, so Fynla becomes purely a development company for them."
   - **The activity:** advising on investments is regulated under article 53 of the Financial Services and Markets Act 2000 (Regulated Activities) Order 2001 (https://www.legislation.gov.uk/uksi/2001/544/article/53). The business, as the authorised firm, holds that permission and the responsibility for the advice.
   - **What changes in Fyn:** today's prompts carry Fynla's own position, which is guidance with hedged wording and pointing users to "a regulated financial adviser" (`Prompts/ComplianceRules.php:42-50`, `Prompts/FcaProcessInstructions.php:66`). Under a business's licence that wording has to match what each business's permission and compliance team allow. For example, "speak to one of our advisers" can replace an outside adviser. That is work for phase 3, set by Fynla per business and not editable in the dashboard (4.4).
   - **What the business will ask of us:** a firm that relies on an outside provider for a key function is subject to the FCA's outsourcing rules (SYSC 8, https://www.handbook.fca.org.uk/handbook/SYSC/8/1.html). Each business's compliance team will expect the audit trail (`AuditChainService`), the advice log (`AdviceReviewService`, `AiAdviceLog`) and the record retention Fyn already keeps. Those are copied in step one (Package F).

2. **We will be a data processor.** We will process each business's customer data on its behalf, which needs a written processor contract under UK GDPR article 28 (https://www.legislation.gov.uk/eur/2016/679/article/28). The existing erasure and retention machinery (`fyn:user:erase`, episodic purge and cold archive) carries over and runs per business database.

---

## 6. Inventory: what is extracted

Phase 1 copies the whole backend (section 8), because 3.1 shows a hand-picked copy would be a guess that fails silently. This inventory groups the copy into the **packages** the new product is made of. Each package is proved in section 9. Paths are relative to the repo root.

### Package A: Fyn core

**Prompts**

| Element | Path |
|---|---|
| Unified system prompt | `app/Services/AI/Fyn/FynSystemPrompt.php` |
| Per-turn context | `app/Services/AI/Fyn/FynContextAssembler.php`, `FynContextSelector.php`, `FynTurnContext.php`, `ContextBucket.php`, `FynPromptMode.php` |
| Turn instructions | `FynCaptureTurnInstructions.php`, `FynVerifyEditTurnInstructions.php` |
| Output guards | `CertaintyFilter.php`, `ClaimTier.php`, `ArithmeticCheck.php`, `RecaptureGuard.php`; `app/Services/AI/Support/AckSentenceDeduper.php`; `app/Support/XaiFunctionCallLeakStripper.php` |
| Building blocks | `app/Services/AI/Prompts/` (`CoreIdentity`, `ComplianceRules`, `FcaProcessInstructions`, `QueryKnowledge`, `EmptyDataGuard`, `UserContentSanitiser`) |
| Rollback builders | `AdvicePromptBuilder.php`, `OnboardingPromptBuilder.php` (keep `FYN_PROMPT_ARCH=unified`) |
| Knowledge constants | `app/Constants/FinancialPlanningKnowledge.php`, `QuerySchemas.php`, `GateRoutes.php`, `ProfileEnums.php`, `ValidationLimits.php`, `UpdateRecordAllowlist.php` |

**Dispatch and loop**

| Element | Path |
|---|---|
| Endpoint | `AiChatController.php`; `routes/api.php:1510-1532` |
| Chat engine | `app/Traits/HasAiChat.php`, `HasAiGuardrails.php` |
| The two states | `AdviceFyn.php`; `OnboardingChatDirector.php`, `OnboardingStateMachine.php`, `OnboardingWorkflowTable.php`, `OnboardingService.php`, `JourneyStateService.php`, `JourneyFieldResolver.php` |
| CoALA loop | `app/Services/AI/Loop/` |
| Write safety | `Ground/GroundGate.php`, `WriteIntentClassifier.php`, `HandoffContract.php`, `HandoffPayloadValidator.php`, `KycGateChecker.php`, `PrerequisiteGateService.php` |
| Response shaping | `QueryClassifier`, `StructuredResponseValidator`, `ToolResultContract`, `ToolResults`, `NavigationCommand`, `DuplicateAcknowledgement`, `RecordDuplicateChecker`, `Enums/FynTurnIntent`, `Enums/AiMessageStatus` |
| "Ask Fyn about this" | `app/Services/AI/ContextualConversation/` |
| UI actions | `app/Services/AI/Actions/` |

**Tools.** The catalogue is built by `AiToolDefinitions.php` (Anthropic format) and `XaiToolDefinitions.php` (OpenAI format) from `fyn-memory/procedural/tool_schema/`. The order is guarded by `tests/Feature/AI/ToolSchemaGoldenMasterTest.php`.

| Group | Tools |
|---|---|
| Navigation (1) | `navigate_to_page` |
| Analysis (6) | `list_records`, `list_goals`, `list_life_events`, `get_module_analysis`, `get_recommendations`, `search_conversation_index` |
| Tax and plans (2) | `get_tax_information`, `generate_financial_plan` |
| Billing (3) | `get_subscription_status`, `list_invoices`, `get_current_plan` (Fynla's own subscription: replace in phase 2) |
| What-if (1) | `create_what_if_scenario` |
| Create (20) | goals and life events, savings, investments, holdings, pensions, property, mortgages, protection, estate items, family members, trusts, businesses, chattels |
| Modify (4) | `update_record`, `delete_record`, `update_profile`, `set_expenditure` |
| Save Tax campaign (8) | the `capture_*` campaign tools (Fynla's marketing funnel: decide in phase 2) |
| Handoff (2) | `delegate_to_capture`, `capture_complete` |
| Onboarding (6) | `capture_personal_details`, `capture_spouse_details`, `capture_dependants`, `capture_work_details`, `capture_monthly_expenditure`, `capture_employer_benefits` |

That makes 53 tools. There are also 10 read-only `fetch_*` pointer tools (`fyn-memory/procedural/pointers/`, `app/Services/AI/Pointers/` with 10 handlers). Tools run through `CoordinatingAgent::executeTool` (`app/Agents/CoordinatingAgent.php:1001`). Writes go through `app/Services/Stores/`.

**Memory (nine systems)**

| # | Memory | Where it lives | Carry data across? | After copying |
|---|---|---|---|---|
| 1 | Procedural (tool schemas, pointers, overlays, onboarding workflow, recommendation routing) | `fyn-memory/procedural/`; `app/Services/AI/Memory/Procedural/` | yes | `fyn:procedural:validate`, `fyn:pointers:reindex` |
| 2 | Semantic, global (house view 21, tax 2, FCA 1, allowance 1, product 1) | `fyn-memory/semantic/`; `SemanticCorpusLoader`, `SemanticRetriever`, `HouseViewApplicability` | yes | `fyn:semantic:reindex` (builds `storage/app/memory/semantic/index.json`) |
| 3 | Semantic, per user | `storage/app/memory/semantic-user/` (`UserSemanticStore`) | **no**: starts empty | none |
| 4 | Episodic | `ai_messages` episode columns plus blobs under `episodic/Y/m/d/<conversation>/` (`EpisodeBlobWriter.php:23`); `fyn-memory/episodic/RUBRIC.md` | code and rubric only | none |
| 5 | Recall scoring | `Memory/Recall/`, `EpisodeRetriever` | yes | none |
| 6 | Known facts (four layers) | `MemoryRetrieverService.php`, `OnboardingFactExtractor.php` | yes | none |
| 7 | Conversation index | `ai_conversations` columns; `ConversationSummariser`, `ConversationSummariserJob`, `ConversationWindow` | yes | scheduler running |
| 8 | Markdown store adapter | `Memory/FynMemoryStore.php`; `config/fyn.php` `memory` | yes | none |
| 9 | Gated learning | `app/Services/AI/Learning/`, `proposed_semantic_facts`, `proposed_procedure_amendments` | yes, **OFF** (`FYN_LEARNING_ENABLED=false`) | none |

Copy the memory READMEs and `_TEMPLATE.md` files with the corpus.

**Capture (forms only).** `CaptureForms.php` (do not split: [[feedback_do_not_split_captureforms]]), `RecordEditForms.php`, `TypedFormFill.php`, `WalkFormPrefill.php`, `CaptureAccuracyGate.php`, `AssetCaptureEntityExtractor.php`, `OnboardingValueInterpreter.php`, the phrasing classes (`OwnershipPhrasings`, `PropertyPhrasings`, `SpouseHouseholdPhrasings`), `EstateOnboardingFlow.php`, `HouseholdProvisioner.php`, `DashboardPromptService.php`, the spouse services, and `app/ValueObjects/CaptureContext.php`. The server defines the forms and the client only renders them.

### Package B: Calculations (ships with the widget)

| Part | Paths |
|---|---|
| Module agents | `app/Agents/` (Coordinating, Protection, Savings, Investment, Retirement, Estate, Goals, TaxOptimisation, Base) |
| Calculation services | `app/Services/` Tax (49), Investment (59), Estate (34), Coordination (27), Retirement (19), Savings (14), Plans (13), Goals (12), Protection (11), Property, NetWorth, Income, Expenditure, Risk, Trust, Actions, Shared, WhatIf, UserProfile; `UKTaxCalculator.php` (963 lines) |
| Write layer | `app/Services/Stores/` (35), including normalisers, recalculation and snapshots |
| Shared maths | traits `CalculatesOwnershipShare`, `ResolvesIncome`, `ResolvesExpenditure`, `HasJointOwnership`, `CalculatesOCF`, `FormatsCurrency`; `app/Support/` (`Money`, `SharedOwnership`, `HouseholdPooling`, `HoldingValuation`, `SavingsInterestRate`, `PremiumAnnualiser`, `StrategyNextStep`, …) |
| Events the engines fire | `app/Events/` (Pension, Savings, Property, Mortgage, Investment, ReferenceData) and their listeners |

### Package C: Tax configuration and reference data (ships with the widget)

| Part | Paths |
|---|---|
| Read and write | `TaxConfigService.php`, `Stores/TaxConfigStore.php`, `Stores/Normalisers/TaxConfigNormaliser`, `Stores/ReferenceData/ReferenceDataStore`, `TaxConfigSnapshotService.php`, `Services/Tax/IncomeTaxBands`, `Constants/TaxDefaults.php` |
| Models | `TaxConfiguration`, `TaxConfigurationAudit`, `TaxProductReference`, `TaxActionDefinition` and the five other action-definition models |
| Tax years | `TaxConfigurationSeeder` (2021/22 to 2026/27, active year from `ACTIVE_TAX_YEAR`) |
| Other reference data | `TaxProductReferenceSeeder`, `ActuarialLifeTablesSeeder`, `SavingsMarketRatesSeeder` (with the quarterly `savings:refresh-market-rates` job, `app/Console/Kernel.php`), `CurrencyRatesSeeder`, `OccupationCodeSeeder` |
| Recommendation catalogue | six `*ActionDefinitionSeeder`s (Tax, Savings, Investment, Retirement, Protection, Estate), `ActionHowToSeeder` with `database/seeders/data/action-how-to/`, and their migrations (`2026_03_03` to `2026_09_27`) |

**Yearly upkeep.** Each April, Fynla's tax figures are updated for the new year. The widget now carries its own copy, so the same update has to land in both.

**Decided (CSJ, 4a):** "we will update the tax figures for the clients". Fynla maintains the widget's tax data for every business, the same way it does for Fynla: a new year is added to `TaxConfigurationSeeder` with its GOV.UK sources, and `ACTIVE_TAX_YEAR` is moved on. With one database per business, the update is applied to each business's database. A single command that does this belongs in phase 4.

### Package D: Fyn data model

- **Models:** all of `app/Models/` (116 of the 125 are reachable).
- **Schema:** `database/schema/mysql-schema.sql`, which creates the base tables (3.4).
- **Migrations:** all 355 (37 are Fyn-specific, but they alter shared tables such as `users`).
- **Test clients:** `TestUsersSeeder` and `PreviewUserSeeder`, so there is someone to chat as.
- **Role:** this becomes the target the connector maps into (4.1).

### Package E: Chat client (the widget's starting point)

The SSE event contract the widget must handle:
- **Terminal events:** `content`, `done`, `error`, `token_limit`, `consent_required`, `resume` (`AiChatController.php:51`).
- **Other events:** `tool_use`, `tool_result`, `thinking`, `title`, `quick_replies`, `navigation`, `fill_form`, `capture_form`, `capture_form_errors`, `form_received`, `capture_write_result`, `capture_complete`, `entity_created`, `onboarding_advance`, `onboarding_field_captured`, `onboarding_complete`, `onboarding_layout_change`, `onboarding_capture_error`, `skip_link`, `level_up`, `handoff_error`.
- **Internal:** `handoff` never reaches the client.

**Base: the `/m` chat.**
- `resources/mobile/mixins/onboardingChat.js` (1,018 lines)
- `utils/fynStream.js` (91)
- `utils/fynText.js` (21, `renderFynText`)
- `components/FynBubbles.vue`, `components/FynCaptureForm.vue`
- `views/ConversationHistory.vue`
- the chat calls in `resources/mobile/api.js`, and their tests

It is already a compact, phone-sized chat, which is close to a widget.

**Reference only.**
- The web chat: `resources/js/components/Shared/AiChatPanel.vue`, `components/Fyn/`, `store/modules/aiChat.js`, `aiFormFill.js`, `services/aiChatService.js`.
- The native chat in `ios-native/`.

**Fyn character.** `public/images/Fyn/`. Per-business characters come in phase 3.

### Package F: Running and safety

| Part | Paths and notes |
|---|---|
| Config | `config/fyn.php`, `onboarding.php`, `ai_chat.php`, `ai_pricing.php`, `fyn_eval.php`, `services.php:35-63` |
| Provider | `app/Services/AI/AiProvider.php`. Production runs Fyn on OpenAI GPT-6 Luna ([[project_release_2026_10_09_aa]]) |
| Scheduler (`app/Console/Kernel.php:34-70`) | conversation pause and summarise jobs, idempotency cleanup, audit retention and chain check, episodic reconcile and cold archive |
| Commands | `fyn:*`, `ai:*`, `eval:*` |
| Safety | consent gate (`AiChatController.php:217-219`), `ai-chat` rate limit of 20 a minute (`RouteServiceProvider.php:130`), idempotency, weekly limits, audit hash chain (`AuditChainService`), cost tracking, erasure (`fyn:user:erase`), advice log (`AdviceReviewService`) |
| Tests | `tests/Feature/AI`, `tests/Feature/Fyn`, `tests/Feature/Onboarding`, `tests/Unit/Services/AI`, `tests/Unit/Services/Onboarding`, `tests/Support/Fyn` (243 PHP files), plus the calculation and tax tests under `tests/Unit` and `tests/Feature` |
| Evals | `app/Services/Eval/`, `app/Events/Eval/`, `config/fyn_eval.php`, `Eval*` commands |
| Docs | `April/April24Updates/spec/00-canonical.md`, the `fyn-architecture` skill, the `fyn-memory` READMEs |

Found: `.env.example:132-134` still names the retired `grok-4-1-fast-*` models, while `config/services.php:42-44` defaults to `grok-4.3`. The new `.env` takes model names from `config/services.php`.

---

## 7. Fynla-only parts to remove in phase 2

These are copied in phase 1 so nothing breaks unseen. They are then removed one area at a time, re-proving section 9 after each removal ([[feedback_never_strip_established_work_on_a_guess]]).

- **Front end and public site:** Fynla's web SPA (`resources/js/`, kept as reference until the widget's own chat stands), public pages, the server-rendered homepage, and insights and news articles.
- **Marketing:** the marketing pipeline and social posts.
- **Billing and accounts:** Fynla subscriptions and payments (Revolut, Apple), Fynla's admin and advisor portals, and email campaigns.
- **Native app:** `ios-native/`.
- **Fynla-specific Fyn parts:**
  - the billing tools (replaced by nothing or by the business's own);
  - the Save Tax campaign path (`config/onboarding.php:91-99`, the 8 campaign tools);
  - `navigate_to_page`'s Fynla routes (replaced by per-business deep links, 4.4);
  - full-page form fill (`aiFormFill`, which drives Fynla's own pages).

---

## 8. How to make the copy (when CSJ says go)

1. **Snapshot without touching the live checkout:** `git -C /Users/CSJ/Desktop/fynla archive 01290a768 | tar -x -C <target>`.
   - This copies only committed files: no `.env`, `vendor`, `node_modules` or gitignored `storage/app/memory`, and none of the other agent's work in progress.
   - The schema dump (3.4) is committed, so it comes across.
2. **Target folder and repo:** `/Users/CSJ/Desktop/fyn-widget/` (CSJ, 4a). Make it a new git repository, with a `SOURCE.md` recording the base commit, the date and CSJ's product statement (section 1).
3. **Dependencies:** `composer install`, `npm ci`.
4. **New environment:**
   - a new `.env` from `.env.example`, with a new `APP_KEY` and a **new database** (for example `fyn_widget`);
   - `AI_PROVIDER=openai` and the four `OPENAI_*` model keys set to `gpt-6-luna`, with our own OpenAI key (4.6);
   - a Vite port other than fynla's `:5173`.
5. **Database:** create the empty database, run `php artisan migrate` (on the new empty database, never `migrate:fresh`), then `php artisan db:seed`.
6. **Memory indexes:** `fyn:procedural:validate`, `fyn:pointers:reindex`, `fyn:semantic:reindex`.
7. **Scheduler and queue:** `schedule:work` and a queue worker.
8. **Prove it:** section 9.

---

## 9. Proof that the copy is a fully working Fyn

Step one is done only when every check below holds in the new folder, against the new database:

1. **Catalogue:** `fyn:procedural:validate` passes, and the 53 tools plus the 10 `fetch_*` tools are in the catalogue.
2. **Tests:** the test folders in Package F pass, run per folder (never a full suite), together with the `/m` chat Vitest specs.
3. **Tax configuration:** `TaxConfigService::getTaxYear()` returns 2026/27, and a spot set of figures matches the seeder and the GOV.UK source cited in it (for example the personal allowance and the higher-rate threshold).
4. **Calculations:** for a seeded client, `get_module_analysis` and `get_recommendations` return the same figures as Fynla does for the same client at `01290a768`.
5. **Onboarding writes:** a new client is greeted on the bubbles, answers employment, and the value is saved.
6. **Capture writes through a form:** "I have a savings account" opens the savings form, and saving it creates the row.
7. **Advice answers from the engines, read-only:** a pension tax relief question runs a tool and quotes `TaxConfigService` figures. A write request in advice mode goes through `delegate_to_capture`, and `handoff` never reaches the client.
8. **Memory:**
   - a second conversation recalls the first through the conversation index;
   - an episode blob is written;
   - the semantic index exists.
9. **Safety:**
   - no consent gives `consent_required`;
   - the 21st message in a minute is refused;
   - `ai:audit:verify-chain` passes;
   - OpenAI requests carry `store: false`.

Checks 5 to 9 are walked as a user in a browser on the copied `/m` chat ([[feedback_never_walk_headless_use_chrome]]). Anything not walked is reported as "I COULD NOT TEST THIS".

---

## 10. Later phases (not part of step one)

| Phase | What | Depends on |
|---|---|---|
| 1 | Copy and prove (this plan) | Ready: folder and provider decided (4a) |
| 2 | Remove the Fynla-only parts (section 7) | Phase 1 green |
| 3 | Rebrand per business: the agent's name, colours and character become per-business settings (3.5, 4.4); Fynla sets each business's compliance wording to its licence (section 5) | Decided (4a) |
| 4 | Tenancy: a central database plus one database per business (4.2); identity by signed client token (4.3); usage and limits per business (4.6); one command to roll a new tax year into every business database (Package C) | Decided (4a) |
| 5 | Connector: get a business's customer data into the Fyn data model (4.1), by API first; decide where captured data goes (4.5) | Decisions 3 and 4 below |
| 6 | The widget: an embeddable chat built from Package E, served by us and added to the host app with a snippet | Phases 3 and 4 |
| 7 | The business dashboard: connect the database, customise Fyn, see usage | Phases 3 to 5 |
| 8 | Per-business compliance wording and the outsourcing and processor contracts (section 5) | Before the first real customer |

---

## 11. Task list for step one

The six-box format for Fynla fix plans ([[feedback_plan_task_list_and_change_only_testing]]) assumes dev and production deploys, which a new local application does not have. The boxes are adapted. Each is ticked only with dated evidence.

| # | Task | Copy | Boot | Test | Walk |
|---|---|---|---|---|---|
| 0 | Decisions for step one taken (CSJ, 14 October 2026: folder, OpenAI `gpt-6-luna`) | n/a | n/a | n/a | n/a |
| 1 | Snapshot from `01290a768`, new repo, `SOURCE.md` (8.1-8.2) | [ ] | n/a | n/a | n/a |
| 2 | Dependencies and new `.env` (8.3-8.4) | [ ] | [ ] | n/a | n/a |
| 3 | New database: schema, migrations, seeders (8.5) | [ ] | [ ] | [ ] | n/a |
| 4 | Memory indexes (8.6) | [ ] | [ ] | [ ] | n/a |
| 5 | Scheduler and queue (8.7) | [ ] | [ ] | [ ] | n/a |
| 6 | Proof checks 1-4: catalogue, tests, tax configuration, calculations | n/a | n/a | [ ] | n/a |
| 7 | Proof checks 5-9 walked live on the copied `/m` | n/a | n/a | [ ] | [ ] |

---

## 12. Decisions (CSJ)

**Decided on 14 October 2026** (detail in 4a):
- the target folder;
- OpenAI `gpt-6-luna`;
- one database per business (confirmed);
- customisation covers colours, the agent's name, the greeting and app deep links, with more to come at the dashboard stage;
- Fynla maintains the tax figures;
- advice runs under each business's licence, with Fynla as the technology provider.

**Still open**

1. **The connector's first route (phase 5):** an API that businesses send data to (recommended), a read-only connection to their database, or file uploads (4.1).
2. **Data a customer gives Fyn (phase 5):** kept in the business's Fyn database only, or sent back to the business's own system (4.5).
3. **The rest of the customisation list (phase 7):** CSJ will set it out when dashboard work starts.
4. **Contracts (phase 8, with legal advice):** the outsourcing terms each business's compliance team will need, and the UK GDPR article 28 processor contract (section 5).
