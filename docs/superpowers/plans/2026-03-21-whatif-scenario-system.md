# What-If Scenario System — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans.

**Goal:** Persistent, AI-driven what-if scenario dashboard with living comparisons across all affected modules.

**Architecture:** New `what_if_scenarios` table stores scenario parameters (no snapshots). `WhatIfScenarioService` creates transient user copies with overrides, runs standard `analyze()` on both real and modified user, returns comparison. AI tool `create_what_if_scenario` replaces `run_what_if_scenario`. Frontend: new `WhatIfDashboard.vue` replaces existing `WhatIfScenarios.vue` as parent, with Death of Spouse becoming a child route.

**Tech Stack:** Laravel 10, MySQL 8, Vue 3, Vuex, Pest

**Spec:** `docs/superpowers/specs/2026-03-21-goals-whatif-integration-design.md` (Sub-Project 1)

---

## Task 1: Database — Migration, Model, Factory

**Files:**
- Create: `database/migrations/2026_03_21_000002_create_what_if_scenarios_table.php`
- Create: `app/Models/WhatIfScenario.php`
- Create: `database/factories/WhatIfScenarioFactory.php`

- [ ] **Step 1: Create migration**

- [ ] **Step 2: Create model with SoftDeletes, Auditable traits**

- [ ] **Step 3: Create factory**

- [ ] **Step 4: Run migration, commit**

---

## Task 2: Backend Service — WhatIfScenarioService

**Files:**
- Create: `app/Services/WhatIf/WhatIfScenarioService.php`

The core service that:
1. Accepts scenario parameters
2. Identifies affected modules from parameter types
3. Creates a transient user copy with overrides applied
4. Runs `analyze()` on real user (Now) and modified copy (What If)
5. Calculates deltas
6. Returns structured comparison

- [ ] **Step 1: Create service with calculateComparison() method**

- [ ] **Step 2: Create test**

- [ ] **Step 3: Commit**

---

## Task 3: API — Controller, Routes, Form Request

**Files:**
- Create: `app/Http/Controllers/Api/WhatIfScenarioController.php`
- Create: `app/Http/Requests/StoreWhatIfScenarioRequest.php`
- Create: `app/Http/Resources/WhatIfScenarioResource.php`
- Modify: `routes/api.php`

Endpoints: index, count, show (with live comparison), store, update (rename), destroy (soft delete)

- [ ] **Step 1: Create form request, resource, controller**

- [ ] **Step 2: Add routes**

- [ ] **Step 3: Commit**

---

## Task 4: AI Tool — Replace run_what_if_scenario

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php`
- Modify: `app/Agents/CoordinatingAgent.php`

Replace ephemeral `run_what_if_scenario` with persistent `create_what_if_scenario`. Handler creates scenario via `WhatIfScenarioService`, returns comparison + auto-navigation.

- [ ] **Step 1: Update tool definition**

- [ ] **Step 2: Update handler in CoordinatingAgent**

- [ ] **Step 3: Exclude tool for preview users**

- [ ] **Step 4: Commit**

---

## Task 5: Frontend — Vuex Store + API Service

**Files:**
- Create: `resources/js/store/modules/whatIf.js`
- Create: `resources/js/services/whatIfService.js`
- Modify: `resources/js/store/index.js`

- [ ] **Step 1: Create API service**

- [ ] **Step 2: Create Vuex store module**

- [ ] **Step 3: Register in store index**

- [ ] **Step 4: Commit**

---

## Task 6: Frontend — WhatIfDashboard View + Components

**Files:**
- Create: `resources/js/views/Planning/WhatIfDashboard.vue`
- Create: `resources/js/components/WhatIf/ScenarioCard.vue`
- Create: `resources/js/components/WhatIf/ScenarioDetail.vue`
- Create: `resources/js/components/WhatIf/ModuleComparison.vue`
- Modify: `resources/js/router/index.js`

The main What-If page with scenario list + detail view. Death of Spouse scenario becomes a child route.

- [ ] **Step 1: Create components**

- [ ] **Step 2: Update router**

- [ ] **Step 3: Commit**

---

## Task 7: Preview Seeder + Sidebar Badge

**Files:**
- Create: `database/seeders/WhatIfScenarioSeeder.php`
- Modify: sidebar component for count badge

- [ ] **Step 1: Create seeder with 2-3 example scenarios for preview personas**

- [ ] **Step 2: Add sidebar count badge**

- [ ] **Step 3: Commit**

---

## Task 8: Tests + Browser Verification

- [ ] Run all tests
- [ ] Browser test: navigate to What-If page, verify scenario list, detail view
- [ ] Verify preview user sees greyed-out empty state
