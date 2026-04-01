# Fyn Phase 2 — Detailed Task List

**Plan:** fynUpgrade2.md
**Branch:** new branch from `fynImprovement`
**Total tasks:** 78

---

## Phase 1: Prompt Refactor (2 sessions)

### 1.1 Create directory structure
- [ ] Create `app/Services/AI/Prompts/` directory
- **Command:** `mkdir -p app/Services/AI/Prompts`

### 1.2 Create QuerySchemas constant class
- [ ] Create `app/Constants/QuerySchemas.php`
- [ ] Define all 22 query types as constants (`RETIREMENT_CONTRIBUTION`, `SAVINGS_EMERGENCY`, `DATA_ENTRY`, `NAVIGATION`, etc.)
- [ ] Define `ADVICE_TYPES` array (all types that go through FCA process)
- [ ] Define `BYPASS_TYPES` array (`data_entry`, `navigation` — skip FCA process)
- [ ] Define `IMPLICIT_RELATED` map (pension → always adds tax + affordability, etc.)
- [ ] Define `MODULE_MAP` (query type → module names)
- **Agent:** code-architect for schema design
- **Test:** `php -l app/Constants/QuerySchemas.php`

### 1.3 Extract Layer 1 — CoreIdentity
- [ ] Create `app/Services/AI/Prompts/CoreIdentity.php`
- [ ] Move `<identity>` block from HasAiChat (lines 462-465)
- [ ] Move `<security>` block (lines 467-478)
- [ ] Move `<scope>` block (lines 552-560)
- [ ] Move `<personality>` block (lines 576-588)
- [ ] Move `<response_format>` block (lines 562-574)
- [ ] Expose as `CoreIdentity::get(string $firstName): string`
- **Read:** `app/Traits/HasAiChat.php` lines 448-588
- **Test:** `php -l app/Services/AI/Prompts/CoreIdentity.php`

### 1.4 Extract Layer 2 — ComplianceRules
- [ ] Create `app/Services/AI/Prompts/ComplianceRules.php`
- [ ] Move `<instructions>` block from HasAiChat (lines 480-492)
- [ ] Move `<regulatory_compliance>` block (lines 494-502)
- [ ] Include: no acronyms (17 terms), no IDs, no icons/emoji/Unicode, no jargon
- [ ] Include: joint ownership rule — name BOTH owners with shares
- [ ] Expose as `ComplianceRules::get(): string`
- **Test:** `php -l app/Services/AI/Prompts/ComplianceRules.php`

### 1.5 Extract Layer 3 — FcaProcessInstructions
- [ ] Create `app/Services/AI/Prompts/FcaProcessInstructions.php`
- [ ] Write the 6-step FCA process instructions (check data → fetch tools → analyse → recommend → implement → follow up)
- [ ] Move `<available_actions>` block (lines 590-635) — tool usage rules
- [ ] Move `<data_creation_guidance>` block (lines 647-667) — for non-preview users
- [ ] Move `<preview_mode>` block (lines 637-644) — for preview users
- [ ] Expose as `FcaProcessInstructions::get(bool $isPreview): string`
- **Test:** `php -l app/Services/AI/Prompts/FcaProcessInstructions.php`

### 1.6 Create SystemPromptBuilder
- [ ] Create `app/Services/AI/SystemPromptBuilder.php`
- [ ] Inject dependencies: `TaxConfigService`, `NetWorthService`, `PrerequisiteGateService`, `DisposableIncomeAccessor`
- [ ] Implement `build(User $user, ?array $classification, ?array $kycResult, ?string $currentRoute, bool $isPreview): string`
- [ ] Layer 1: call `CoreIdentity::get($firstName)`
- [ ] Layer 2: call `ComplianceRules::get()`
- [ ] Layer 3: call `FcaProcessInstructions::get($isPreview)`
- [ ] Layer 4: call existing `buildUserProfile()` (move from HasAiChat)
- [ ] Layer 5: call existing `buildFinancialContext()` (move from HasAiChat)
- [ ] Layer 6: call existing `buildExistingRecordsSummary()` (move from HasAiChat)
- [ ] Layer 7: call existing `buildPrerequisiteStateContext()` (move from HasAiChat)
- [ ] Layer 8: placeholder — returns empty string (wired in Phase 3)
- [ ] Layer 9: placeholder — returns KYC result if provided (wired in Phase 2)
- [ ] Layer 10: call `getModuleContext()` (move from HasAiChat)
- [ ] Assemble all layers into XML-tagged prompt string
- **Read:** `app/Traits/HasAiChat.php` full buildSystemPrompt method
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php`

### 1.7 Move dynamic builders from HasAiChat to SystemPromptBuilder
- [ ] Move `buildUserProfile()` → `SystemPromptBuilder::buildUserProfile()`
- [ ] Move `buildFinancialContext()` → `SystemPromptBuilder::buildFinancialContext()`
- [ ] Move `buildExistingRecordsSummary()` → `SystemPromptBuilder::buildExistingRecordsSummary()`
- [ ] Move `buildPrerequisiteStateContext()` → `SystemPromptBuilder::buildPrerequisiteStateContext()`
- [ ] Move `getModuleContext()` → `SystemPromptBuilder::getModuleContext()`
- [ ] Move `calculateTotalUserIncome()` → `SystemPromptBuilder`
- [ ] Move `estimateTaxBand()` → `SystemPromptBuilder`
- [ ] Move `calculateTotalExpenditure()` → `SystemPromptBuilder`
- [ ] Move `formatInvestmentAccountType()` → `SystemPromptBuilder`
- [ ] Keep original methods in HasAiChat as thin wrappers calling builder (backward compat)
- **Test:** `php -l app/Traits/HasAiChat.php` and `php -l app/Services/AI/SystemPromptBuilder.php`

### 1.8 Rewire HasAiChat.buildSystemPrompt()
- [ ] Replace 670-line `buildSystemPrompt()` with call to `SystemPromptBuilder::build()`
- [ ] Verify the assembled prompt matches the current output (compare token by token for a test user)
- **Command:** `php artisan tinker` — compare old vs new prompt for test user
- **Test:** `./vendor/bin/pest tests/Unit/` — ensure no regressions

### 1.9 Phase 1 browser testing
- [ ] Log in as `fyntest@example.com` on dev
- [ ] Send "What is my net worth?" — verify response quality matches pre-refactor
- [ ] Send "How do I maximise my pension contributions?" — verify response quality
- [ ] Send "I have a new savings account with £5,000" — verify data creation still works
- [ ] Send "Take me to my property page" — verify navigation still works
- [ ] Check rolling status messages still work
- [ ] Check user message scrolls to top
- **Tool:** Playwright browser testing
- **Command:** `php artisan db:seed` before testing

### 1.10 Phase 1 commit
- [ ] Commit all Phase 1 files
- [ ] Update fyn2Tasks.md with completed checkboxes

---

## Phase 2: Query Classification + KYC (2 sessions)

### 2.1 Create QueryClassifier
- [ ] Create `app/Services/AI/QueryClassifier.php`
- [ ] Implement `classify(string $message, ?string $currentRoute): array`
- [ ] Returns `['primary' => string, 'related' => string[], 'modules' => string[]]`
- [ ] First check: data_entry patterns ("I have", "I earn", "add my", "my X is £Y") → `data_entry`
- [ ] Second check: navigation patterns ("take me to", "show me", "go to", "navigate") → `navigation`
- [ ] Third check: keyword matching against query type definitions in `QuerySchemas`
- [ ] Fourth check: route-based fallback (if on /net-worth/retirement, bias toward retirement types)
- [ ] Apply implicit related types from `QuerySchemas::IMPLICIT_RELATED`
- [ ] Fallback: if no match, return `general`
- **Read:** `app/Constants/QuerySchemas.php` for type definitions
- **Test:** `php -l app/Services/AI/QueryClassifier.php`

### 2.2 Write Pest tests for QueryClassifier
- [ ] Create `tests/Unit/Services/AI/QueryClassifierTest.php`
- [ ] Test: "I have a pension with £50,000" → primary: `data_entry`
- [ ] Test: "Take me to estate planning" → primary: `navigation`
- [ ] Test: "How do I maximise my pension?" → primary: `retirement_contribution`, related includes `tax_optimisation`, `affordability`
- [ ] Test: "Do I have enough life cover?" → primary: `protection_cover`
- [ ] Test: "What should I do with my bonus?" → primary: `holistic_health`
- [ ] Test: "What is my net worth?" → primary: `general`
- [ ] Test: "Should I pay off my mortgage or invest?" → primary: `savings_debt`, related includes `investment_tax`, `affordability`
- [ ] Test: "How is my financial health?" → primary: `holistic_health`
- [ ] Test: "Update my ISA balance to £15,000" → primary: `data_entry`
- [ ] Test: route-based fallback (on /protection page, "tell me more" → protection)
- **Command:** `./vendor/bin/pest tests/Unit/Services/AI/QueryClassifierTest.php`

### 2.3 Create KycGateChecker
- [ ] Create `app/Services/AI/KycGateChecker.php`
- [ ] Inject `PrerequisiteGateService` and per-module `DataReadinessService` classes
- [ ] Implement `check(User $user, array $classification): array`
- [ ] Returns `['passed' => bool, 'missing' => [...], 'prompt_text' => string]`
- [ ] Check universal requirements (DOB, marital, employment, income, expenditure)
- [ ] Check module-specific requirements for ALL classified modules (primary + related)
- [ ] Build plain-text `<kyc_status>` block (no icons, no emoji)
- [ ] If blocked: include instruction telling Fyn to ask for data, not give advice
- [ ] If `data_entry` or `navigation`: always return `passed: true` (bypass)
- **Read:** `app/Services/PrerequisiteGateService.php`, all `*DataReadinessService.php` files
- **Test:** `php -l app/Services/AI/KycGateChecker.php`

### 2.4 Write Pest tests for KycGateChecker
- [ ] Create `tests/Unit/Services/AI/KycGateCheckerTest.php`
- [ ] Test: user with all data → passes for retirement_contribution
- [ ] Test: user missing expenditure → blocked for retirement_contribution
- [ ] Test: user missing dependants → blocked for protection_cover
- [ ] Test: user missing risk profile → blocked for investment_portfolio
- [ ] Test: data_entry type → always passes regardless of missing data
- [ ] Test: navigation type → always passes
- [ ] Test: holistic_health → checks ALL module gates
- [ ] Test: multi-type (retirement + savings) → checks BOTH module requirements
- **Command:** `./vendor/bin/pest tests/Unit/Services/AI/KycGateCheckerTest.php`

### 2.5 Wire classification + KYC into chat() method
- [ ] Modify `HasAiChat::chat()` — add classify step before AI call
- [ ] Add KYC check after classification
- [ ] Pass classification + KYC result to `SystemPromptBuilder::build()`
- [ ] If `data_entry` or `navigation`: skip KYC, use standard prompt (Layers 1-7 + 10)
- [ ] If KYC blocked: inject `<kyc_status>` with BLOCKED instruction
- [ ] If KYC passed: inject `<kyc_status>` with PASS and data summary
- **Read:** `app/Traits/HasAiChat.php` chat() method
- **Test:** `php -l app/Traits/HasAiChat.php`

### 2.6 Phase 2 browser testing
- [ ] Test: ask "How much pension contribution should I make?" with missing expenditure → Fyn asks for data
- [ ] Test: ask "Do I have enough life cover?" with missing dependants → Fyn asks for data
- [ ] Test: say "I have a new savings account" → Fyn creates record (no KYC check)
- [ ] Test: ask "What is my net worth?" → no KYC needed, responds directly
- [ ] Test: ask "How is my total financial health?" → checks all modules
- **Tool:** Playwright browser testing
- **Command:** `php artisan db:seed` before testing

### 2.7 Phase 2 commit
- [ ] Commit all Phase 2 files
- [ ] Update fyn2Tasks.md

---

## Phase 3: Knowledge RAG + Record Filtering (1-2 sessions)

### 3.1 Refactor FinancialPlanningKnowledge into per-domain methods
- [ ] Modify `app/Constants/FinancialPlanningKnowledge.php`
- [ ] Make each constant accessible individually: `getIncomeClassifications()`, `getPensionKnowledge()`, `getInvestmentTaxWrappers()`, `getEstatePlanningConcepts()`, `getProtectionConcepts()`, `getRecommendationFramework()`, `getAffordabilityRules()`
- [ ] Keep `getSystemPromptKnowledge()` as the "all domains" method (used for holistic)
- **Test:** `php -l app/Constants/FinancialPlanningKnowledge.php`

### 3.2 Create QueryKnowledge
- [ ] Create `app/Services/AI/Prompts/QueryKnowledge.php`
- [ ] Implement `getForClassification(array $classification): string`
- [ ] Map each query type to relevant knowledge domain(s)
- [ ] Merge knowledge from primary + all related types (deduplicated)
- [ ] `holistic_health` → returns ALL domains
- [ ] `data_entry` / `navigation` → returns empty string
- [ ] `general` → returns empty string (no knowledge needed for factual queries)
- **Read:** `app/Constants/FinancialPlanningKnowledge.php`, `app/Constants/QuerySchemas.php`
- **Test:** `php -l app/Services/AI/Prompts/QueryKnowledge.php`

### 3.3 Write Pest tests for QueryKnowledge
- [ ] Create `tests/Unit/Services/AI/QueryKnowledgeTest.php`
- [ ] Test: retirement_contribution → includes pension + income + affordability knowledge
- [ ] Test: protection_cover → includes protection knowledge only
- [ ] Test: holistic_health → includes ALL knowledge domains
- [ ] Test: data_entry → returns empty string
- [ ] Test: general → returns empty string
- [ ] Test: multi-type (retirement + tax) → includes pension + income + tax knowledge
- **Command:** `./vendor/bin/pest tests/Unit/Services/AI/QueryKnowledgeTest.php`

### 3.4 Add record filtering to SystemPromptBuilder
- [ ] Modify `buildExistingRecordsSummary()` to accept classification
- [ ] Map query types to relevant record types:
  - `retirement_*` → DC pensions, DB pensions, state pension
  - `savings_*` → savings accounts, cash accounts
  - `investment_*` → investment accounts, holdings
  - `protection_*` → life insurance, critical illness, income protection, family members
  - `estate_*` → properties, trusts, gifts, liabilities, family members
  - `property` → properties, mortgages
  - `holistic_health` → ALL records
  - `general` → ALL records
  - `data_entry` → ALL records (needed for duplicate detection)
- [ ] Always include surplus/income regardless of type
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php`

### 3.5 Add recommendation filtering to SystemPromptBuilder
- [ ] Modify `buildFinancialContext()` to accept classification
- [ ] Filter ranked recommendations to only include relevant modules
- [ ] `holistic_health` and `general` → all recommendations (no filter)
- [ ] `retirement_*` → only retirement + tax recommendations
- [ ] `protection_*` → only protection recommendations
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php`

### 3.6 Wire Layer 8 into SystemPromptBuilder
- [ ] Replace Layer 8 placeholder with `QueryKnowledge::getForClassification($classification)`
- [ ] Include knowledge in `<financial_knowledge>` XML tag
- [ ] If empty (data_entry, navigation, general): omit the tag entirely
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php`

### 3.7 Phase 3 browser testing
- [ ] Test: ask "How much pension?" → check prompt only contains pension/income/affordability knowledge (add temporary logging)
- [ ] Test: ask "What is my property worth?" → check no pension/protection knowledge in prompt
- [ ] Test: ask "How is my financial health?" → check ALL knowledge included
- [ ] Test: say "I have a SIPP" → check all records included (for duplicate detection)
- [ ] Verify token count reduced vs Phase 1 (add logging)
- **Tool:** Playwright browser testing + Laravel log review
- **Command:** `php artisan db:seed` before testing

### 3.8 Phase 3 commit
- [ ] Commit all Phase 3 files
- [ ] Update fyn2Tasks.md

---

## Phase 4: Mandatory Tool Sequences (1 session)

### 4.1 Define tool sequences in QuerySchemas
- [ ] Add `REQUIRED_TOOLS` map to `QuerySchemas.php`
- [ ] Define per-query-type tool call arrays (see fynUpgrade2.md section D)
- [ ] Define merge logic: tools from primary + related types, deduplicated
- **Test:** `php -l app/Constants/QuerySchemas.php`

### 4.2 Define trigger mappings in QuerySchemas
- [ ] Add `RELEVANT_TRIGGERS` map to `QuerySchemas.php`
- [ ] Map each query type to ActionDefinition trigger keys (see fynUpgrade2.md section E)
- [ ] Define merge logic: triggers from primary + related types
- **Test:** `php -l app/Constants/QuerySchemas.php`

### 4.3 Inject required tools and triggers into prompt
- [ ] Modify `SystemPromptBuilder::build()` to add `<required_tools>` block
- [ ] Build tool list from merged classification
- [ ] Modify `SystemPromptBuilder::build()` to add `<relevant_triggers>` block
- [ ] Build trigger list from merged classification
- [ ] Skip both blocks for `data_entry`, `navigation`, `general`
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php`

### 4.4 Phase 4 browser testing
- [ ] Test: ask "How much pension?" → Fyn calls `get_tax_information(pension_allowances)` before responding
- [ ] Test: ask "Do I have enough emergency fund?" → Fyn calls `get_module_analysis(savings)` before responding
- [ ] Test: ask "What is my IHT position?" → Fyn calls `get_tax_information(inheritance_tax)` before responding
- [ ] Test: say "I have a new ISA" → no mandatory tool calls, just creates record
- **Tool:** Playwright browser testing + Laravel log review (check tool call order)
- **Command:** `php artisan db:seed` before testing

### 4.5 Phase 4 commit
- [ ] Commit all Phase 4 files
- [ ] Update fyn2Tasks.md

---

## Phase 5: Decision Tree Binding (1-2 sessions)

### 5.1 Map all ActionDefinition triggers to query types
- [ ] Read all 6 ActionDefinition seeders and extract every trigger key
- [ ] Create mapping in `QuerySchemas::TRIGGER_DEFINITIONS` with trigger key, description, module
- [ ] Verify all 130+ triggers are mapped
- **Read:** All `database/seeders/*ActionDefinitionSeeder.php` files
- **Agent:** Explore agent to extract all triggers
- **Test:** `php -l app/Constants/QuerySchemas.php`

### 5.2 Include trigger evaluation results in prompt
- [ ] Modify `SystemPromptBuilder::buildFinancialContext()` to include decision trace details for relevant triggers
- [ ] For each relevant trigger: show whether it fired, the threshold, and the result
- [ ] Format as plain text in `<trigger_results>` block
- **Read:** `app/Services/*/ActionDefinitionService.php` — how trigger results are structured
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php`

### 5.3 Write Pest tests for trigger mapping
- [ ] Create `tests/Unit/Constants/QuerySchemasTest.php`
- [ ] Test: every trigger key in seeders has a mapping in QuerySchemas
- [ ] Test: retirement_contribution query type includes employer_match, contribution_increase triggers
- [ ] Test: protection_cover includes life_insurance_gap, income_protection_gap triggers
- [ ] Test: holistic_health includes ALL triggers
- **Command:** `./vendor/bin/pest tests/Unit/Constants/QuerySchemasTest.php`

### 5.4 Phase 5 browser testing
- [ ] Test: ask "Why is Fyn recommending I increase my pension?" → response references specific trigger (employer_match) with threshold values
- [ ] Test: ask "How much life cover do I need?" → response references coverage gap calculation
- [ ] Test: ask "How is my financial health?" → response references multiple triggers across modules
- **Tool:** Playwright browser testing
- **Command:** `php artisan db:seed` before testing

### 5.5 Phase 5 commit
- [ ] Commit all Phase 5 files
- [ ] Update fyn2Tasks.md

---

## Phase 6: Review System (1 session)

### 6.1 Create ai_advice_log migration
- [ ] Create `database/migrations/*_create_ai_advice_log_table.php`
- [ ] Columns: `id`, `user_id`, `conversation_id`, `message_id`, `query_type`, `classification` (JSON), `kyc_status` (JSON), `recommendations` (JSON — trigger keys + amounts), `tools_called` (JSON), `created_at`, `updated_at`
- [ ] Index on `user_id` + `created_at`
- **Command:** `php artisan make:migration create_ai_advice_log_table`
- **Test:** `php artisan migrate`

### 6.2 Create AiAdviceLog model
- [ ] Create `app/Models/AiAdviceLog.php`
- [ ] Define fillable fields, casts (JSON columns)
- [ ] Relationship: `belongsTo(User::class)`
- [ ] Scope: `forUser($userId)`, `recent($days = 30)`
- **Command:** `php artisan make:model AiAdviceLog`
- **Test:** `php -l app/Models/AiAdviceLog.php`

### 6.3 Add advice logging to CoordinatingAgent
- [ ] Modify `app/Agents/CoordinatingAgent.php`
- [ ] After AI response is complete (in `chat()` method), log the structured advice
- [ ] Log: query type, classification, KYC status, recommendations given, tools called
- [ ] Only log for ADVICE query types (not data_entry, navigation, general)
- **Read:** `app/Traits/HasAiChat.php` — where response is finalised
- **Test:** `php -l app/Agents/CoordinatingAgent.php`

### 6.4 Add data change detection
- [ ] Create `app/Services/AI/AdviceReviewService.php`
- [ ] Implement `checkForChanges(User $user): array`
- [ ] Compare current user data against data at time of last advice
- [ ] Flag: income changed, new assets added, family changed, new liabilities, goals changed
- [ ] Returns list of changes with timestamps
- **Test:** `php -l app/Services/AI/AdviceReviewService.php`

### 6.5 Add annual review prompt logic
- [ ] Modify `SystemPromptBuilder` Layer 7 (data completeness)
- [ ] Check `AiAdviceLog` for last advice per module
- [ ] If > 12 months since last review of any module: include prompt in `<review_due>` block
- [ ] Example: "It has been 14 months since protection was last reviewed. Offer to review."
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php`

### 6.6 Write Pest tests for review system
- [ ] Create `tests/Unit/Services/AI/AdviceReviewServiceTest.php`
- [ ] Test: no advice log → no changes flagged
- [ ] Test: income changed since last advice → flagged
- [ ] Test: advice older than 12 months → review due
- [ ] Test: recent advice, no data changes → no flags
- **Command:** `./vendor/bin/pest tests/Unit/Services/AI/AdviceReviewServiceTest.php`

### 6.7 Phase 6 browser testing
- [ ] Test: ask a pension question → check ai_advice_log has entry with correct query_type and recommendations
- [ ] Test: change income after advice → check AdviceReviewService flags the change
- [ ] Test: simulate 12+ months since advice → check review prompt appears in Fyn context
- **Tool:** Playwright browser testing + database verification via tinker
- **Command:** `php artisan db:seed` before testing

### 6.8 Phase 6 commit
- [ ] Commit all Phase 6 files
- [ ] Update fyn2Tasks.md

---

## Final Verification

### 7.1 Full regression test
- [ ] Run full Pest test suite: `./vendor/bin/pest`
- [ ] Verify no regressions across all 1,603+ tests
- **Command:** `./vendor/bin/pest`

### 7.2 End-to-end browser test — advice queries
- [ ] Log in as `fyntest@example.com`
- [ ] "How do I maximise my pension?" → KYC passes, specific £ amounts, PA reclaim flagged, no irrelevant concepts
- [ ] "Do I have enough life cover?" → KYC checks dependants, shows coverage gap if applicable
- [ ] "What is my emergency fund position?" → shows months of cover, target
- [ ] "How is my total financial health?" → holistic view with priority order
- [ ] "What should I do with a £50k bonus?" → multi-classification, covers savings + investment + pension + debt
- **Tool:** Playwright browser testing

### 7.3 End-to-end browser test — data entry
- [ ] "I have a new savings account with Barclays, £10,000" → creates record immediately, no KYC
- [ ] "Update my SIPP to £25,000" → updates existing record, no KYC
- [ ] "Take me to my investments" → navigates, no KYC
- [ ] "I earn £120,000 from employment" → updates income, no KYC
- **Tool:** Playwright browser testing

### 7.4 End-to-end browser test — KYC blocking
- [ ] Create a minimal test user with only DOB and name (no income, no expenditure)
- [ ] "How much pension should I contribute?" → Fyn asks for income and expenditure, offers to help enter
- [ ] Enter income via Fyn → then re-ask → Fyn asks for expenditure
- [ ] Enter expenditure → then re-ask → Fyn gives advice with £ amounts
- **Tool:** Playwright browser testing

### 7.5 Token count verification
- [ ] Add temporary logging to SystemPromptBuilder to output token count
- [ ] Verify standard query prompt is under 4,000 tokens
- [ ] Verify holistic query prompt is under 5,500 tokens
- [ ] Compare against pre-refactor (should be ~50% reduction)
- **Command:** Review Laravel log output

### 7.6 Update documentation
- [ ] Update `April/April1Updates/fynUpgrade2.md` — mark as IMPLEMENTED
- [ ] Update `April/April1Updates/bugsFix.md` — add any new bugs found
- [ ] Update `CSJTODO.md` with completion status
- [ ] Update `CLAUDE.md` if any conventions changed

### 7.7 Final commit
- [ ] Commit all remaining files
- [ ] Create PR from `fynImprovement` to `main`
- [ ] Update fyn2Tasks.md — all checkboxes marked

---

## Summary

| Phase | Tasks | New Files | Modified Files | Tests |
|-------|-------|-----------|---------------|-------|
| 1. Prompt Refactor | 10 | 4 (CoreIdentity, ComplianceRules, FcaProcess, SystemPromptBuilder) | 1 (HasAiChat) | 1 browser suite |
| 2. Query Classification + KYC | 7 | 3 (QueryClassifier, KycGateChecker, QuerySchemas) | 1 (HasAiChat) | 2 Pest + 1 browser |
| 3. Knowledge RAG + Filtering | 8 | 1 (QueryKnowledge) | 2 (FinancialPlanningKnowledge, SystemPromptBuilder) | 1 Pest + 1 browser |
| 4. Mandatory Tools | 5 | 0 | 2 (QuerySchemas, SystemPromptBuilder) | 1 browser |
| 5. Decision Tree Binding | 5 | 0 | 2 (QuerySchemas, SystemPromptBuilder) | 1 Pest + 1 browser |
| 6. Review System | 8 | 3 (migration, AiAdviceLog, AdviceReviewService) | 2 (CoordinatingAgent, SystemPromptBuilder) | 1 Pest + 1 browser |
| 7. Final Verification | 7 | 0 | 2 (docs) | 1 full Pest + 4 browser |
| **Total** | **78** | **11 new files** | **6 modified files** | **6 Pest + 9 browser suites** |
