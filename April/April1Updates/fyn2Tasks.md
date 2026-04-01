# Fyn Phase 2 — Detailed Task List

**Plan:** fynUpgrade2.md
**Branch:** new branch from `fynImprovement`
**Total tasks:** 78

---

## Phase 1: Prompt Refactor (2 sessions)

### 1.1 Create directory structure
- [x] Create `app/Services/AI/Prompts/` directory
- **Command:** `mkdir -p app/Services/AI/Prompts`

### 1.2 Create QuerySchemas constant class
- [x] Create `app/Constants/QuerySchemas.php`
- [x] Define all 22 query types as constants (`RETIREMENT_CONTRIBUTION`, `SAVINGS_EMERGENCY`, `DATA_ENTRY`, `NAVIGATION`, etc.)
- [x] Define `ADVICE_TYPES` array (all types that go through FCA process)
- [x] Define `BYPASS_TYPES` array (`data_entry`, `navigation` — skip FCA process)
- [x] Define `IMPLICIT_RELATED` map (pension → always adds tax + affordability, etc.)
- [x] Define `MODULE_MAP` (query type → module names)
- Also defined: `KEYWORD_PATTERNS`, `UNIVERSAL_KYC`, `MODULE_KYC`, `REQUIRED_TOOLS`, `RELEVANT_TRIGGERS`, `KNOWLEDGE_DOMAINS`, `RECORD_TYPES`, `HOLISTIC_PRIORITY`, and helper methods
- **Test:** `php -l app/Constants/QuerySchemas.php` — PASS

### 1.3 Extract Layer 1 — CoreIdentity
- [x] Create `app/Services/AI/Prompts/CoreIdentity.php`
- [x] Move `<identity>` block from HasAiChat
- [x] Move `<security>` block
- [x] Move `<scope>` block
- [x] Move `<personality>` block
- [x] Move `<response_format>` block
- [x] Expose as `CoreIdentity::get(string $firstName): string`
- **Test:** `php -l app/Services/AI/Prompts/CoreIdentity.php` — PASS

### 1.4 Extract Layer 2 — ComplianceRules
- [x] Create `app/Services/AI/Prompts/ComplianceRules.php`
- [x] Move `<instructions>` block from HasAiChat
- [x] Move `<regulatory_compliance>` block
- [x] Include: no acronyms (17 terms), no IDs, no icons/emoji/Unicode, no jargon
- [x] Include: joint ownership rule — name BOTH owners with shares
- [x] Expose as `ComplianceRules::get(): string`
- **Test:** `php -l app/Services/AI/Prompts/ComplianceRules.php` — PASS

### 1.5 Extract Layer 3 — FcaProcessInstructions
- [x] Create `app/Services/AI/Prompts/FcaProcessInstructions.php`
- [x] Write the 6-step FCA process instructions (check data → fetch tools → analyse → recommend → implement → follow up)
- [x] Move `<available_actions>` block — tool usage rules
- [x] Move `<data_creation_guidance>` block — for non-preview users
- [x] Move `<preview_mode>` block — for preview users
- [x] Expose as `FcaProcessInstructions::get(bool $isPreview): string`
- **Test:** `php -l app/Services/AI/Prompts/FcaProcessInstructions.php` — PASS

### 1.6 Create SystemPromptBuilder
- [x] Create `app/Services/AI/SystemPromptBuilder.php`
- [x] Inject dependencies: `TaxConfigService`, `PrerequisiteGateService` (NetWorthService resolved inline, orchestrateAnalysis passed as callable)
- [x] Implement `build(User $user, ?array $classification, ?array $kycResult, ?string $currentRoute, bool $isPreview, ?callable $orchestrateAnalysis): string`
- [x] Layer 1: call `CoreIdentity::get($firstName)`
- [x] Layer 2: call `ComplianceRules::get()`
- [x] Layer 3: call `FcaProcessInstructions::get($isPreview)`
- [x] Layer 4: call `buildUserProfile()` (moved from HasAiChat)
- [x] Layer 5: call `buildFinancialContext()` (moved from HasAiChat)
- [x] Layer 6: call `buildExistingRecordsSummary()` (moved from HasAiChat)
- [x] Layer 7: call `buildPrerequisiteStateContext()` (moved from HasAiChat)
- [x] Layer 8: placeholder — returns all knowledge (Phase 3 will filter by query type)
- [x] Layer 9: placeholder — returns KYC result if provided (wired in Phase 2)
- [x] Layer 10: call `getModuleContext()` (moved from HasAiChat)
- [x] Assemble all layers into XML-tagged prompt string
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php` — PASS
- **Test:** Container resolution via `app(SystemPromptBuilder::class)` — PASS

### 1.7 Move dynamic builders from HasAiChat to SystemPromptBuilder
- [x] Move `buildUserProfile()` → `SystemPromptBuilder::buildUserProfile()`
- [x] Move `buildFinancialContext()` → `SystemPromptBuilder::buildFinancialContext()`
- [x] Move `buildExistingRecordsSummary()` → `SystemPromptBuilder::buildExistingRecordsSummary()`
- [x] Move `buildPrerequisiteStateContext()` → `SystemPromptBuilder::buildPrerequisiteStateContext()`
- [x] Move `getModuleContext()` → `SystemPromptBuilder::getModuleContext()`
- [x] Move `calculateTotalUserIncome()` → `SystemPromptBuilder`
- [x] Move `estimateTaxBand()` → `SystemPromptBuilder`
- [x] Move `calculateTotalExpenditure()` → `SystemPromptBuilder`
- [x] Move `formatInvestmentAccountType()` → `SystemPromptBuilder`
- [x] Original methods kept in HasAiChat as dead code (legacy wrapper exists but never called)
- **Test:** `php -l app/Traits/HasAiChat.php` — PASS
- **Test:** `php -l app/Services/AI/SystemPromptBuilder.php` — PASS

### 1.8 Rewire HasAiChat.buildSystemPrompt()
- [x] Replace 670-line `buildSystemPrompt()` with call to `SystemPromptBuilder::build()`
- [x] Verify the assembled prompt contains all 16 expected XML sections for non-preview user
- [x] Verify preview user gets `<preview_mode>` and NOT `<data_creation_guidance>`
- [x] Full integration test via tinker: prompt generated correctly (31,278 chars) with real user data
- **Test:** Architecture Pest tests — 89 PASS (all deprecated, pre-existing)

### 1.9 Phase 1 browser testing
- [x] Log in as `john@example.com` on dev (verification code fetched from DB)
- [x] Send "What is my net worth?" — Fyn responded with £0.00, explained no assets/liabilities recorded, offered to help add data. PASS.
- [x] Send "How do I maximise my pension contributions?" — Fyn referenced £75,000 income, higher-rate band, £60,000 Annual Allowance, 40% tax relief with specific £ amounts. Included risk warning and tax caveat. PASS.
- [x] Send "I have a new savings account with Barclays, it has £5,000 in it" — Fyn created Barclays savings account (£5,000 visible on Cash Management page), navigated to page, gave follow-up pension advice with £ amounts. PASS.
- [x] Send "Take me to my property page" — Fyn navigated to Property page, showed "Navigating to Property." confirmation. PASS.
- [x] Rolling status messages work — tool use status indicators visible during streaming.
- [x] Chat panel scrolls to latest message correctly.

### 1.10 Phase 1 commit
- [ ] Commit all Phase 1 files
- [ ] Update fyn2Tasks.md with completed checkboxes

---

## Phase 2: Query Classification + KYC (2 sessions)

### 2.1 Create QueryClassifier
- [x] Create `app/Services/AI/QueryClassifier.php`
- [x] Implement `classify(string $message, ?string $currentRoute): array`
- [x] Returns `['primary' => string, 'related' => string[], 'modules' => string[]]`
- [x] First check: data_entry patterns (refined: "I have a/an/my", "I earn £", "update my", etc.)
- [x] Second check: navigation patterns ("take me to", "show me", "go to")
- [x] Third check: keyword matching against `QuerySchemas::KEYWORD_PATTERNS`
- [x] Fourth check: route-based fallback (15 routes mapped to types)
- [x] Apply implicit related types from `QuerySchemas::IMPLICIT_RELATED`
- [x] Fallback: if no match, return `general`
- [x] Fixed: "Should I pay off" was false-matching data_entry (tightened "I pay" to "I pay £")
- [x] Fixed: "What should I do with my bonus?" now matches holistic_health
- **Test:** `php -l` PASS + tinker smoke tests all correct

### 2.2 Write Pest tests for QueryClassifier
- [x] Create `tests/Unit/Services/AI/QueryClassifierTest.php`
- [x] Test: "I have a pension with £50,000" → primary: `data_entry` PASS
- [x] Test: "Take me to estate planning" → primary: `navigation` PASS
- [x] Test: "How do I maximise my pension?" → primary: `retirement_contribution`, related includes `tax_optimisation`, `affordability` PASS
- [x] Test: "Do I have enough life cover?" → primary: `protection_cover` PASS
- [x] Test: "What should I do with my bonus?" → primary: `holistic_health` PASS
- [x] Test: "What is my net worth?" → primary: `general` PASS
- [x] Test: "Should I pay off my mortgage or invest?" → primary: `savings_debt`, related includes `affordability` PASS
- [x] Test: "How is my financial health?" → primary: `holistic_health` PASS
- [x] Test: "Update my ISA balance to £15,000" → primary: `data_entry` PASS
- [x] Test: route-based fallback (on /protection page, "tell me more" → protection) PASS
- [x] Additional tests: "Show me my investments" → navigation, "I earn £75,000" → data_entry, emergency fund → savings_emergency, module mapping, empty modules for data_entry
- **Result:** 17 tests, 27 assertions — ALL PASS

### 2.3 Create KycGateChecker
- [x] Create `app/Services/AI/KycGateChecker.php`
- [x] Inject `PrerequisiteGateService` (delegates to per-module DataReadinessService classes)
- [x] Implement `check(User $user, array $classification): array`
- [x] Returns `['passed' => bool, 'missing' => [...], 'prompt_text' => string]`
- [x] Check universal requirements (DOB, marital, employment, income, expenditure)
- [x] Check module-specific requirements for ALL classified modules via PrerequisiteGateService
- [x] Build plain-text `<kyc_status>` block (no icons, no emoji)
- [x] If blocked: BLOCKED instruction telling Fyn to ask for data, not give advice
- [x] If passed: PASSED with module summary for FCA 6-step process
- [x] If `data_entry`, `navigation`, or `general`: always return `passed: true` (bypass, empty prompt_text)
- **Test:** `php -l` PASS + tinker smoke tests all correct

### 2.4 Write Pest tests for KycGateChecker
- [x] Create `tests/Unit/Services/AI/KycGateCheckerTest.php`
- [x] Test: data_entry → always passes regardless of missing data PASS
- [x] Test: navigation → always passes PASS
- [x] Test: general → always passes PASS
- [x] Test: user missing DOB → blocked PASS
- [x] Test: user missing income → blocked PASS
- [x] Test: user missing expenditure → blocked PASS
- [x] Test: user with all data for savings → passes PASS
- [x] Test: blocked prompt contains "KYC CHECK: BLOCKED" and "Do NOT give advice" PASS
- [x] Test: passed prompt contains "KYC CHECK: PASSED" PASS
- **Result:** 9 tests, 17 assertions — ALL PASS

### 2.5 Wire classification + KYC into chat() method
- [x] Modify `HasAiChat::chat()` — classify step added before prompt building
- [x] KYC check runs after classification (skipped for bypass/general types)
- [x] Pass classification + KYC result to `SystemPromptBuilder::build()`
- [x] If `data_entry` or `navigation`: skip KYC, kycResult stays null
- [x] If KYC blocked: `<kyc_status>` BLOCKED injected into Layer 9
- [x] If KYC passed: `<kyc_status>` PASSED injected into Layer 9
- [x] `buildSystemPrompt()` updated to accept classification + kycResult parameters
- **Test:** `php -l` PASS, tinker integration test confirmed full pipeline works

### 2.6 Phase 2 browser testing
- [x] Database seeded before testing
- [x] Test: "How much pension contribution should I make?" with missing expenditure → Fyn identified missing expenditure, explained why it's needed, listed it as a bullet point, offered to help enter data conversationally. Did NOT give pension advice. PASS.
- [x] Test: "I have a new savings account with HSBC with £3,000" → Fyn created HSBC account (£3,000 visible on Cash Management page) immediately, no KYC blocking. PASS.
- [x] Test: "What is my net worth?" → Fyn responded directly with £3,000.00 net worth breakdown (HSBC savings), no KYC blocking. PASS.
- **Note:** "Do I have enough life cover?" and holistic tests deferred — john@example.com is a minimal test user. Core KYC blocking/bypass behaviour verified.

### 2.7 Phase 2 commit
- [ ] Commit all Phase 2 files
- [x] Update fyn2Tasks.md

---

## Phase 3: Knowledge RAG + Record Filtering (1-2 sessions)

### 3.1 Refactor FinancialPlanningKnowledge into per-domain methods
- [x] Already done in Phase 1 — per-domain accessors added: `getIncomeClassifications()`, `getPensionKnowledge()`, `getInvestmentTaxWrappers()`, `getEstatePlanningConcepts()`, `getProtectionConcepts()`, `getRecommendationFramework()`, `getAffordabilityRules()`, `getKnowledgeCaveat()`
- [x] `getSystemPromptKnowledge()` kept as "all domains" method (used for holistic + backward compat)
- **Test:** `php -l` PASS

### 3.2 Create QueryKnowledge
- [x] Create `app/Services/AI/Prompts/QueryKnowledge.php`
- [x] Implement `getForClassification(?array $classification): string`
- [x] Map query types to domains via `QuerySchemas::KNOWLEDGE_DOMAINS`
- [x] Merge knowledge from primary + related types (deduplicated)
- [x] `holistic_health` → returns ALL domains
- [x] `data_entry` / `navigation` / `general` → returns empty string
- [x] null classification → returns ALL (backward compat)
- [x] Always appends KNOWLEDGE_CAVEAT to non-empty results
- **Test:** `php -l` PASS

### 3.3 Write Pest tests for QueryKnowledge
- [x] Create `tests/Unit/Services/AI/QueryKnowledgeTest.php` — 7 tests, 25 assertions
- [x] Test: retirement_contribution → includes pension + income + affordability, excludes estate/protection PASS
- [x] Test: protection_cover → includes protection only, excludes pension/estate/investment PASS
- [x] Test: holistic_health → includes ALL domains PASS
- [x] Test: data_entry → returns empty string PASS
- [x] Test: general → returns empty string PASS
- [x] Test: multi-type (tax + retirement) → merged + deduplicated, income appears once PASS
- [x] Test: null classification → returns all knowledge (backward compat) PASS

### 3.4 Add record filtering to SystemPromptBuilder
- [x] `buildExistingRecordsSummary()` now accepts `?array $classification`
- [x] `getRelevantRecordTypes()` helper resolves record types from classification
- [x] Each record section (savings, investments, pensions, properties, protection, trusts, business, chattels, liabilities, gifts, family) wrapped in `$include()` guard
- [x] Empty RECORD_TYPES (holistic, general, data_entry) → include ALL records
- [x] Merged types from primary + related for cross-cutting queries
- **Test:** `php -l` PASS

### 3.5 Add recommendation filtering to SystemPromptBuilder
- [x] `buildFinancialContext()` now accepts `?array $classification`
- [x] Ranked recommendations filtered to relevant modules when classification provided
- [x] Empty modules (holistic, general) → no filter, all recommendations shown
- **Test:** `php -l` PASS

### 3.6 Wire Layer 8 into SystemPromptBuilder
- [x] Layer 8 now calls `QueryKnowledge::getForClassification($classification)`
- [x] Knowledge wrapped in `<financial_knowledge>` XML tag
- [x] Empty result (data_entry, navigation, general) → tag omitted entirely
- **Test:** `php -l` PASS

### 3.7 Phase 3 testing
- [x] Tinker token count verification:
  - No classification (all knowledge): 30,637 chars
  - Pension query (filtered): 25,323 chars — **1,328 tokens saved**
  - Data entry (no knowledge): 18,201 chars — **3,109 tokens saved**
  - General (no knowledge): 18,201 chars — **3,109 tokens saved**
- [x] Pension query correctly includes PENSION KNOWLEDGE, excludes ESTATE/PROTECTION
- [x] Browser test: pension question with RAG filtering active → Fyn response references affordability (from included knowledge domain), mentions £4,504.78 surplus, identifies missing expenditure. PASS.
- [x] All 33 AI tests pass (17 QueryClassifier + 9 KycGateChecker + 7 QueryKnowledge)

### 3.8 Phase 3 commit
- [ ] Commit all Phase 3 files
- [x] Update fyn2Tasks.md

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
