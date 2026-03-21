# Life Events Recommendation Expansion — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Extend life event-driven recommendations beyond Investment to all modules, add 5 new life event types, and enrich AI context with per-module impact summaries.

**Architecture:** Extends existing `LifeEventIntegrationService` EVENT_MODULE_MAP with new event types. Observer cache invalidation extended. AI context enriched via existing `getModuleImpactSummary()`.

**Tech Stack:** Laravel 10, MySQL 8, Pest

**Spec:** `docs/superpowers/specs/2026-03-21-goals-whatif-integration-design.md` (Sub-Project 3)

---

## Task 1: Migration — Add 5 New Life Event Types

**Files:**
- Create: `database/migrations/2026_03_21_000001_add_new_life_event_types.php`

The existing `event_type` enum on `life_events` has 16 values. Add 5 new ones: `divorce`, `marriage`, `new_child`, `job_loss`, `income_change`.

MySQL enums can be altered with `ALTER TABLE ... MODIFY COLUMN`.

- [ ] **Step 1: Create migration**

```php
<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE life_events MODIFY COLUMN event_type ENUM(
            'inheritance', 'gift_received', 'bonus', 'redundancy_payment',
            'property_sale', 'business_sale', 'pension_lump_sum', 'lottery_windfall',
            'large_purchase', 'home_improvement', 'wedding', 'education_fees',
            'gift_given', 'medical_expense', 'custom_income', 'custom_expense',
            'divorce', 'marriage', 'new_child', 'job_loss', 'income_change'
        ) NOT NULL");
    }

    public function down(): void
    {
        // Cannot safely remove enum values if data exists — leave as-is
    }
};
```

- [ ] **Step 2: Run migration locally**

```bash
php artisan migrate
```

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_03_21_000001_add_new_life_event_types.php
git commit -m "feat: add divorce, marriage, new_child, job_loss, income_change life event types"
```

---

## Task 2: Update LifeEventIntegrationService — New Event Type Mappings

**Files:**
- Modify: `app/Services/Goals/LifeEventIntegrationService.php`
- Modify: `app/Models/LifeEvent.php` (add display labels for new types)

- [ ] **Step 1: Read LifeEventIntegrationService EVENT_MODULE_MAP and MODULE_CONTEXT**

- [ ] **Step 2: Add new event types to EVENT_MODULE_MAP**

```php
'divorce' => ['primary' => 'estate', 'secondary' => ['protection', 'retirement', 'savings']],
'marriage' => ['primary' => 'protection', 'secondary' => ['estate', 'savings']],
'new_child' => ['primary' => 'protection', 'secondary' => ['savings', 'estate']],
'job_loss' => ['primary' => 'protection', 'secondary' => ['savings', 'retirement']],
'income_change' => ['primary' => 'savings', 'secondary' => ['retirement', 'investment']],
```

- [ ] **Step 3: Add MODULE_CONTEXT entries for new types**

Add context messages for each new type per module, e.g.:
- `'estate' => ['divorce' => 'Divorce will significantly restructure your estate. Assets may need to be divided and Inheritance Tax position recalculated.']`
- `'protection' => ['marriage' => 'Marriage changes your protection needs. Review life cover and consider joint policies.', 'new_child' => 'A new child increases your family protection needs. Review life cover and income protection.', 'job_loss' => 'Job loss means your income protection is critical. Review existing cover urgently.']`
- etc.

- [ ] **Step 4: Update LifeEvent model display_event_type accessor**

In `app/Models/LifeEvent.php`, find the `getDisplayEventTypeAttribute()` accessor and add labels:
```php
'divorce' => 'Divorce',
'marriage' => 'Marriage',
'new_child' => 'New Child',
'job_loss' => 'Job Loss',
'income_change' => 'Income Change',
```

- [ ] **Step 5: Commit**

```bash
git add app/Services/Goals/LifeEventIntegrationService.php app/Models/LifeEvent.php
git commit -m "feat: add 5 new life event types to integration service and model"
```

---

## Task 3: Extend Observer Cache Invalidation

**Files:**
- Modify: `app/Observers/LifeEventMonteCarloObserver.php`

Currently clears Monte Carlo and Goals Projection caches. Extend to also clear module recommendation caches for affected modules.

- [ ] **Step 1: Read the observer**

- [ ] **Step 2: Add module cache invalidation**

In `clearUserCache()`, after the existing cache clearing, add:

```php
// Clear recommendation caches for affected modules
$eventType = $event->event_type;
$integrationService = app(\App\Services\Goals\LifeEventIntegrationService::class);
$affectedModules = $integrationService->getEventModules($event);

foreach ($affectedModules as $module) {
    Cache::tags([$module, 'user_' . $event->user_id])->flush();
}
```

This uses the existing tagged cache pattern that agents use (`$this->remember()` with tags like `['savings', 'user_123']`).

- [ ] **Step 3: Commit**

```bash
git add app/Observers/LifeEventMonteCarloObserver.php
git commit -m "feat: life event observer clears affected module recommendation caches"
```

---

## Task 4: Enrich AI Context with Per-Module Life Event Summaries

**Files:**
- Modify: `app/Traits/HasAiChat.php`

Currently the AI gets life events via the CoordinatingAgent's orchestrated analysis, but without per-module impact context. Enrich `buildFinancialContext()` to include specific impact summaries.

- [ ] **Step 1: Read HasAiChat buildFinancialContext()**

- [ ] **Step 2: Add life event impact summaries to financial context**

After the existing context building, before the return, add:

```php
// Enrich with per-module life event impact summaries
$integrationService = app(\App\Services\Goals\LifeEventIntegrationService::class);
$modules = ['savings', 'investment', 'retirement', 'protection', 'estate'];
$lifeEventContext = [];

foreach ($modules as $module) {
    $impact = $integrationService->getModuleImpactSummary($user->id, $module);
    if ($impact['event_count'] > 0) {
        $lifeEventContext[$module] = $impact;
    }
}

if (!empty($lifeEventContext)) {
    $context .= "\n\nLIFE EVENT IMPACTS BY MODULE:\n";
    foreach ($lifeEventContext as $module => $impact) {
        $context .= "- {$module}: {$impact['event_count']} events, net impact " .
            ($impact['net_impact'] >= 0 ? '+' : '') . "£" . number_format(abs($impact['net_impact']), 0) . "\n";
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Traits/HasAiChat.php
git commit -m "feat: AI context includes per-module life event impact summaries"
```

---

## Task 5: Tests and Verification

- [ ] **Step 1: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 2: Run all agent tests**

```bash
./vendor/bin/pest tests/Unit/Agents/ -v
```

- [ ] **Step 3: Seed database**

```bash
php artisan db:seed
```

- [ ] **Step 4: Format check**

```bash
./vendor/bin/pint --test
```
