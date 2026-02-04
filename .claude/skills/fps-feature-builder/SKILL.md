---
name: fps-feature-builder
description: Guide Claude through adding or extending features within existing Fynla modules. Use this skill when the user requests to add functionality to an existing module (e.g., "Add pension type to Retirement module", "Extend Protection module to calculate...", "The retirement planning module needs to change..."). This skill provides patterns for common feature additions including new calculations, data types, charts, tabs, and cross-module integrations while maintaining Fynla coding standards and architecture.
---

# Fynla Feature Builder

## Overview

Add or extend features within existing Fynla modules following established patterns. This skill helps you integrate new functionality seamlessly into the existing codebase without breaking existing features.

## When to Use This Skill

Use this skill when **adding or modifying features** in existing modules:

- "Add pension type to Retirement module"
- "Extend Protection module to calculate income protection coverage"
- "We need to change the IHT calculation to include..."
- "Add a new chart to the Investment dashboard"
- "Create a 'What-If Scenarios' tab in Estate module"

**Do NOT use this skill for**:
- Creating entirely new modules (use `fps-module-builder` instead)
- Creating standalone components (use `fps-component-builder` instead)

## Critical Rule: Maintain Application Functionality

**⚠️ THE APPLICATION MUST REMAIN FULLY FUNCTIONAL AT ALL TIMES.**

Before making changes:
1. **Understand the full scope** of impact
2. **Identify all files and components** that will be modified
3. **DO NOT REMOVE** existing features, pages, views, or working code
4. **ONLY WORK** on the specific section explicitly requested
5. **IF OTHER AREAS ARE AFFECTED**:
   - STOP and explain what will be impacted
   - ASK PERMISSION before making changes
   - PROVIDE DETAILS about why changes are necessary
   - WAIT FOR APPROVAL before proceeding

## Common Feature Patterns

---

### Pattern 1: Adding a New Calculation

**Use case**: "Add net worth calculation to Estate module"

#### Steps:

1. **Create/Update Service**
   - Location: `app/Services/{Module}/{Service}.php`
   - Implement calculation logic
   - Return structured data
   - Reference: `references/feature-patterns.md` (Pattern 1)

2. **Update Agent**
   - Add service to constructor (dependency injection)
   - Call service method in analysis
   - Generate recommendations based on result

3. **Add Controller Method** (if new endpoint needed)
   - Add method to existing controller
   - Use Form Request for validation
   - Return JSON response

4. **Add Route** (if new endpoint needed)
   - Add to existing route group in `routes/api.php`

5. **Write Tests**
   - Unit test for service calculation
   - Feature test for API endpoint
   - Run tests: `./vendor/bin/pest`

6. **Frontend Updates**
   - Add method to API service
   - Add Vuex action/mutation
   - Update component to display result

**See `references/feature-patterns.md` for complete example.**

---

### Pattern 2: Adding a New Data Type

**Use case**: "Add DB pensions to Retirement module"

#### Steps:

1. **Create Migration**
   - `php artisan make:migration create_{table}_table`
   - Include ownership fields: `ownership_type`, `joint_owner_id`, `trust_id`
   - Add indexes on `user_id` and frequently queried columns
   - Run: `php artisan migrate`

2. **Create Model**
   - Location: `app/Models/{Module}/{Model}.php`
   - Define `$fillable`, `$casts`
   - Add relationships (`user()`, `jointOwner()`)
   - Reference: `references/coding-standards.md`

3. **Add CRUD Methods to Controller**
   - Add `index()`, `store()`, `update()`, `destroy()` methods
   - Reference existing controller methods for pattern

4. **Create Form Request**
   - Location: `app/Http/Requests/{Module}/Store{Model}Request.php`
   - Define validation rules
   - Add custom validation if needed (e.g., ISA ownership check)

5. **Register Routes**
   - Add to existing route group in `routes/api.php`

6. **Frontend Updates**
   - Add API service methods (get, store, update, delete)
   - Add Vuex state, mutations, actions
   - Create form modal component
   - Add data table to display items
   - Update dashboard view

**See `references/feature-patterns.md` for complete example.**

---

### Pattern 3: Adding a Chart/Visualization

**Use case**: "Add performance chart to Investment dashboard"

#### Steps:

1. **Backend: Add Endpoint**
   - Create controller method to return chart data
   - Structure data with `series` and `categories`
   - Reference: `references/feature-patterns.md` (Pattern 3)

2. **Backend: Agent Method**
   - Format data appropriately for ApexCharts
   - Return structured array

3. **Frontend: Update API Service**
   - Add method to fetch chart data

4. **Frontend: Create Chart Component**
   - Use `fps-component-builder` skill for chart patterns
   - Reference: `references/chart-pattern.md`
   - Import appropriate chart type (line, area, donut, etc.)

5. **Frontend: Add to Dashboard**
   - Import chart component
   - Fetch data in `mounted()` or via Vuex
   - Pass data as prop

**See `references/feature-patterns.md` for complete example.**

---

### Pattern 4: Adding a New Tab to Module Dashboard

**Use case**: "Add 'Scenarios' tab to Estate dashboard"

#### Steps:

1. **Update Dashboard Component**
   - Add tab to `tabs` array in `data()`
   - Add tab content section with `v-if="activeTab === 'scenarios'"`

2. **Create Tab Component**
   - Location: `resources/js/components/{Module}/ScenariosView.vue`
   - Implement tab content
   - Import into dashboard

3. **Add Backend Support** (if needed)
   - Add controller method for tab-specific data
   - Add Vuex action to fetch data

**See `references/feature-patterns.md` for complete example.**

---

### Pattern 5: Adding Validation Rules

**Use case**: "Add ISA ownership validation"

#### Steps:

1. **Backend: Update Form Request**
   - Add validation rules to `rules()` method
   - Use `withValidator()` for custom validation logic
   - Return appropriate error messages

2. **Backend: Add Controller Validation**
   - Add additional validation in controller if needed
   - Return 422 status with error message

3. **Frontend: Add Form Validation**
   - Add validation method to form component
   - Check before emitting save event
   - Display error messages

**See `references/feature-patterns.md` for complete example.**

---

### Pattern 6: Extending Agent Recommendations

**Use case**: "Add new recommendation logic to Protection agent"

#### Steps:

1. **Update Agent Method**
   - Modify `generateRecommendations()` method
   - Add new recommendation logic based on analysis
   - Return structured recommendation array

2. **Write Tests**
   - Test that new recommendation appears when conditions are met
   - Test that recommendation doesn't appear when not applicable

**See `references/feature-patterns.md` for complete example.**

---

### Pattern 7: Adding Cross-Module Integration

**Use case**: "Estate IHT calculation needs Investment data"

#### Steps:

1. **Create/Update Service**
   - Import models from other modules
   - Fetch data from multiple sources
   - Perform cross-module calculations
   - Reference: `references/integration-points.md`

2. **Use in Agent**
   - Inject service via constructor
   - Call service method
   - Generate recommendations

3. **Handle Cache Invalidation**
   - Invalidate cache when source data changes
   - Example: When property added, invalidate estate cache

**See `references/feature-patterns.md` for complete example.**

---

## Workflow: Adding a Feature

### Step 1: Understand the Request

Clarify what the user wants to add or modify:

1. What is the feature/functionality?
2. Which module does it belong to?
3. Does it require new data storage?
4. Does it integrate with other modules?
5. What calculations or business logic is needed?
6. How should it be displayed in the UI?

### Step 2: Plan the Changes

**Create a plan using TodoWrite tool**:

Backend changes:
- [ ] Database migration (if new data)
- [ ] Model updates (if new data)
- [ ] Service updates (calculations)
- [ ] Agent updates (business logic)
- [ ] Controller updates (API endpoints)
- [ ] Form Request updates (validation)
- [ ] Routes updates
- [ ] Tests (unit + feature)

Frontend changes:
- [ ] API service updates
- [ ] Vuex updates (state, mutations, actions)
- [ ] Component updates
- [ ] Dashboard updates
- [ ] Form updates (if needed)
- [ ] Chart updates (if needed)

### Step 3: Implement Backend Changes

Follow the appropriate pattern from above:
- New calculation → Pattern 1
- New data type → Pattern 2
- Cross-module → Pattern 7

**Always**:
- Follow coding standards (`references/coding-standards.md`)
- Write tests before implementation
- Run tests after implementation
- Check for integration impacts (`references/integration-points.md`)

### Step 4: Implement Frontend Changes

**Always**:
- Update API service first
- Add Vuex actions/mutations
- Update or create components
- Test in browser (use `npm run dev`)

### Step 5: Test Integration

1. Test backend API endpoints (Postman or feature tests)
2. Test frontend UI (browser testing)
3. Test cross-module impacts (if applicable)
4. Verify existing functionality still works

### Step 6: Mark Todo Items Complete

As you complete each item, mark it as complete in TodoWrite.

---

## Integration Checklist

When adding features, check these integration points:

### UK Tax Configuration
- [ ] Does feature use UK tax rates/allowances?
- [ ] Reference `config/uk_tax_config.php` (never hardcode)

### UKTaxCalculator Service
- [ ] Does feature need income tax calculations?
- [ ] Use `App\Services\UKTaxCalculator` for tax/NI

### Cross-Module Data Access
- [ ] Does feature need data from other modules?
- [ ] Import appropriate models
- [ ] Consider cache invalidation

### Spouse Data Sharing
- [ ] Does feature access spouse data?
- [ ] Check permissions in `spouse_permissions` table
- [ ] Verify spouse relationship

### Joint Ownership
- [ ] Does feature create assets?
- [ ] Include ownership fields: `ownership_type`, `joint_owner_id`, `trust_id`
- [ ] Handle ISA restriction (individual only)

### Cache Invalidation
- [ ] Does feature modify data that's cached?
- [ ] Invalidate appropriate cache keys
- [ ] See `references/integration-points.md`

---

## References

This skill includes comprehensive reference documentation:

### references/feature-patterns.md
Seven common patterns for adding features:
1. Adding a new calculation
2. Adding a new data type
3. Adding a chart/visualization
4. Adding a new tab to module dashboard
5. Adding validation rules
6. Extending agent recommendations
7. Adding cross-module integration

Load this reference to see complete code examples for each pattern.

### references/integration-points.md
Fynla integration points across modules:
- UK Tax Configuration usage
- UKTaxCalculator service integration
- Cross-module data access patterns
- Spouse data sharing and permissions
- Joint ownership patterns
- Cache invalidation strategies
- Module dependency map

Load this reference when adding features that integrate with other modules.

---

## Key Principles

1. **Preserve Existing Functionality**: Never remove working features
2. **Follow Established Patterns**: Use existing code as reference
3. **Test Thoroughly**: Unit tests + feature tests + browser testing
4. **Consistent Coding Style**: Follow PSR-12 and Vue style guide
5. **UK Tax Rules**: Always reference centralized config
6. **Cache Invalidation**: Clear cache when data changes
7. **Authorization**: Verify user ownership before data access
8. **Error Handling**: Graceful error messages and fallbacks
9. **Loading States**: Show loading indicators during async operations
10. **TodoWrite Tracking**: Track all tasks with TodoWrite tool

---

## Common Pitfalls to Avoid

1. **Don't break existing features** - Test thoroughly after changes
2. **Don't hardcode tax rates** - Use `config/uk_tax_config.php`
3. **Don't skip tests** - Write unit and feature tests
4. **Don't forget cache invalidation** - Clear cache when data changes
5. **Don't skip authorization checks** - Verify user ownership
6. **Don't forget ISA validation** - ISAs must be individually owned
7. **Don't use `@submit` events** - Use `@save` for form modals
8. **Don't modify unrelated code** - Only change what's necessary
9. **Don't forget to update Vuex** - Keep state management in sync
10. **Don't skip integration testing** - Test cross-module dependencies

---

## Example: Complete Feature Addition

**User request**: "Add income protection coverage to Protection module"

### Planning (TodoWrite):
```
- [ ] Add income_protection_coverage field to protection_policies table
- [ ] Update ProtectionPolicy model
- [ ] Update CoverageGapAnalyzer service to include income protection
- [ ] Update ProtectionAgent to generate income protection recommendations
- [ ] Add API endpoint to store income protection data
- [ ] Update protectionService.js
- [ ] Update Vuex store (state, mutations, actions)
- [ ] Add income protection to form modal
- [ ] Update dashboard to display income protection coverage
- [ ] Write unit tests for service
- [ ] Write feature tests for API
- [ ] Test end-to-end in browser
```

### Implementation:
Follow Pattern 1 (Adding a New Calculation) and Pattern 2 (Adding a New Data Type) from `references/feature-patterns.md`.

### Testing:
```bash
# Run backend tests
./vendor/bin/pest

# Start servers for frontend testing
php artisan serve
npm run dev

# Test in browser
# - Add income protection data
# - Verify calculation
# - Check recommendations
# - Verify existing protection features still work
```

### Completion:
Mark all TodoWrite items as complete and verify application is fully functional.
