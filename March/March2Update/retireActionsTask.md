# Retirement Actions Centralisation — Task List

**Date:** 2 March 2026
**Plan:** `March2Update/retireActions.md`

---

## Phase 1: Database Layer

- [ ] **1.1** Create migration `2026_03_03_000001_create_retirement_action_definitions_table.php`
  - Table with: key, source, title_template, description_template, action_template, category, priority, scope, what_if_impact_type, trigger_config (JSON), is_enabled, sort_order, notes, timestamps
  - Indexes on is_enabled, source, sort_order

- [ ] **1.2** Create model `app/Models/RetirementActionDefinition.php`
  - Casts: trigger_config → array, is_enabled → boolean
  - Scopes: getEnabled(), getBySource(), findByKey()
  - Template helpers: renderTitle(), renderDescription(), renderAction() with {placeholder} interpolation

- [ ] **1.3** Create seeder `database/seeders/RetirementActionDefinitionSeeder.php`
  - Seed all 10 action types using updateOrCreate on key
  - 7 agent-sourced + 3 goal-sourced
  - employer_match gets what_if_impact_type: 'contribution' (bug fix)

- [ ] **1.4** Create factory `database/factories/RetirementActionDefinitionFactory.php`
  - States: disabled(), goalSourced()

- [ ] **1.5** Add `RetirementActionDefinitionSeeder` to `DatabaseSeeder.php` after PlanConfigurationSeeder

- [ ] **1.6** Run migration + seed, verify 10 rows created

---

## Phase 2: Backend Service

- [ ] **2.1** Create `app/Services/Retirement/RetirementActionDefinitionService.php`
  - evaluateAgentActions(array $analysisData): array
  - evaluateGoalActions(array $linkedGoals): array
  - getWhatIfImpactType(string $category): string
  - Private evaluators for each trigger condition (10 conditions)

- [ ] **2.2** Integrate with RetirementAgent
  - Add RetirementActionDefinitionService to constructor
  - Replace generateRecommendations() body — delegate to service
  - Keep ContributionOptimizer as calculation helper

- [ ] **2.3** Integrate with RetirementPlanService
  - Add RetirementActionDefinitionService to constructor
  - Replace buildGoalRecommendations() call with service.evaluateGoalActions()
  - Replace str_contains() what-if chain with service.getWhatIfImpactType()

- [ ] **2.4** Run existing plan tests — verify all 42 pass

---

## Phase 3: Admin API

- [ ] **3.1** Create controller `app/Http/Controllers/Api/RetirementActionDefinitionController.php`
  - index(), show(), store(), update(), destroy(), toggleEnabled()
  - Admin-only access check

- [ ] **3.2** Create validation `app/Http/Requests/StoreRetirementActionDefinitionRequest.php`
  - Validate all fields with proper rules

- [ ] **3.3** Add routes to `routes/api.php` within admin middleware group
  - GET/POST /admin/retirement-actions
  - GET/PUT/DELETE /admin/retirement-actions/{id}
  - PATCH /admin/retirement-actions/{id}/toggle

---

## Phase 4: Admin Frontend

- [ ] **4.1** Create `resources/js/components/Admin/AdminRetirementActions.vue`
  - Table with all columns: sort order, title, source, category, priority, scope, what-if type, enabled toggle, thresholds, edit/delete
  - Loading and error states

- [ ] **4.2** Create `resources/js/components/Admin/RetirementActionModal.vue`
  - Form modal for create/edit
  - Dynamic trigger config fields based on condition type
  - Placeholder hints for template fields

- [ ] **4.3** Add "Retirement Actions" tab to `resources/js/views/AdminPanel.vue`

- [ ] **4.4** Add admin service methods to `resources/js/services/adminService.js`
  - getRetirementActions(), createRetirementAction(), updateRetirementAction(), deleteRetirementAction(), toggleRetirementAction()

---

## Phase 5: Testing

- [ ] **5.1** Create `tests/Unit/Services/Retirement/RetirementActionDefinitionServiceTest.php`
  - Test trigger evaluation for each condition type
  - Test threshold values are read from DB
  - Test disabled definitions are skipped
  - Test goal evaluation (3 goal conditions)
  - Test what-if impact type lookup
  - Test template rendering

- [ ] **5.2** Create `tests/Feature/Api/RetirementActionDefinitionTest.php`
  - Admin CRUD endpoints
  - Non-admin 403 rejection
  - Validation errors

- [ ] **5.3** Verify all existing plan tests still pass

---

## Phase 6: Final Verification

- [ ] **6.1** Reseed database
- [ ] **6.2** Browser test: admin panel shows Retirement Actions tab with 10 actions
- [ ] **6.3** Browser test: toggle action off → retirement plan no longer shows it
- [ ] **6.4** Browser test: edit threshold → retirement plan reflects new threshold
- [ ] **6.5** Browser test: create new action → appears in retirement plan
- [ ] **6.6** Browser test: preview personas still work correctly
- [ ] **6.7** Run full test suite — all tests pass

---

## Summary

| Metric | Count |
|--------|-------|
| Files to create | 9 |
| Files to modify | 6 |
| Tasks | 24 |
| Phases | 6 |
