# Action Detail Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every open action, on web, `/m` and iOS, opens its own detail card (the approved design C), with the user's own figures, why it matters to them, how to do it, Ask Fyn and Mark as done.

**Architecture:** One server endpoint, `GET /api/recommendations/actions/{id}`, builds the whole card from the same pipeline that feeds the actions list (`NextActionsService::buildAll`, `RecommendationsAggregatorService`, `ComposedTaxPlanService`). The card fields the pipeline drops today are carried through instead of being rebuilt. Clients render the payload as sent and compute nothing (CSJ 2026-08-23: `/m` never works anything out; CLAUDE.md Rules 19 and 20). How-to steps are fixed text per action definition, drafted and then reviewed by CSJ before release (CSJ ruling 2026-09-25).

**Tech Stack:** Laravel 10 / Pest, Vue 3 (web SPA and the `/m` bundle) / Vitest, SwiftUI / XCTest.

**Spec:**
- The approved design canvas "Fynla Actions Layouts" (https://claude.ai/artifact/6mqya3ujQRPjZkAjfqbYor): artboard C (decision cards, chosen detail) and the Mobile artboard. Its text is extracted in the Appendix.
- CSJ's request of 2026-09-25 (memory `project_tax_plan_howto_programme_2026_09_25`).
- CSJ's instruction of 2026-09-26: "the actions go to THEIR OWN detail card with all the details", on `/m`, iOS and web.

## Global Constraints

- **No icons, emoji or Unicode glyphs on the card** (Rule 15; the canvas has none). No scores (Rule 12).
- **Palette tokens only.** Deadline chip `violet-*`; never amber or orange (Rules 8 and 11).
- **No tax figure or year hardcoded** in PHP, Vue or Swift (Rule 2). Every tax fact in a "why" bullet or a how-to step names its source (Rule 23).
- **Acronyms spelled out** on first use on the card (Rule 9).
- **British English** in user-facing copy.
- **Web card wraps in `<AppLayout>`; `/m` card wraps in `<MobileChrome>`** (Rule 13).
- **Guidance, not advice.** Any card whose source item has `requires_advice`, or any protection or investment product card, shows the canvas disclaimer verbatim: "Fynla does not recommend products. This is guidance based on the figures you have entered."
- **Card ids are the stable `NextActionsService` ids** (`tax_isa_topup_vs_psa`, a `recommendation_id`, `unlock:<module>`, `household:spouse_link`, `strategy_unlock:<type>`), never the positional `{planType}_action_{n}` ids.
- **Done means walked on web, `/m` and iOS** (Rule 19; iOS is native against fynla.org, so the iOS walk follows the web and `/m` release per memory `feedback_release_order_web_m_first_ios_after`).

## Review Focus

1. **An action completed in another tab.** Opening `/actions/{id}` for an action already marked done must show the card as done, with its completion date and no Mark as done button, never a 404.
2. **Another user's action id.** `GET /api/recommendations/actions/{id}` must 404 for an id that is not in the requesting user's list (the isolation case, tests/CLAUDE.md).
3. **Unlock and spouse-link items.** These have no figures. Their card shows the canvas "Waiting on you" shape ("What this changes" and "Add it now"), never an empty "Why this matters" section.
4. **Joint assets.** A figure drawn from a joint account must be the user's own share (Rule 6), the same number the list shows.
5. **A how-to that CSJ has not reviewed yet.** The card shows no steps section rather than draft text (`how_to_status` must be `approved`).

---

## File Structure

**Backend (new)**
- `app/Services/Actions/ActionCardService.php`: builds one card from an id. It is the only place card fields are decided.
- `app/Services/Actions/ActionCardFigures.php`: key figure and "why" bullets for tax items, reading the strategy extras.
- `database/migrations/2026_09_27_000001_add_how_to_to_action_definitions.php`: adds `how_to_steps` (json, nullable) and `how_to_status` (enum `draft`/`approved`, default `draft`) to all six `*_action_definitions` tables.

**Backend (modified)**
- `app/Http/Controllers/Api/RecommendationsController.php`: `show(string $id)`.
- `routes/api.php`: `GET recommendations/actions/{id}`, next to `recommendations/actions` (`:1124`).
- `app/Services/Mobile/NextActionsService.php`: keeps `timeline`, `personalised_context`, `conflict_note`, `potential_benefit`, `requires_advice` and `rule_key` on each item under a `card` key (`recommendationItems()` `:334-401`).
- `app/Services/Coordination/RecommendationsAggregatorService.php` `composedModuleRecs()` (`:306-333`): stops dropping `requires_advice` and the adapter's `action_template`.

**Web**
- `resources/js/views/Actions/ActionCardView.vue` (new): the card, route `/actions/:actionId` (name `ActionCard`).
- `resources/js/router/index.js`: the route, beside `/actions` (`:1042`).
- Links to the card from:
  - `resources/js/views/Actions/ActionsDashboard.vue` `goToAction` (`:171`);
  - `resources/js/views/GamifiedDashboard.vue` `openRec` (`:519`), for navigate rows;
  - `resources/js/components/TaxStrategy/StrategyRecommendationList.vue`, where the card title links to it. This list also switches from `dashboard.recommendations` to `composed_plan.items`, the same list `/m` and iOS render (handover 2026-09-25, Plan C).

**`/m`**
- `resources/mobile/views/ActionCard.vue` (new): route `/actions/:id`, name `m-action-card`, in `resources/mobile/router.js` (`:71`).
- Links to the card from `resources/mobile/views/Actions.vue` `openItem` (`:116`), `resources/mobile/views/Dashboard.vue` `onActionTap` (`:836`, navigate rows) and `resources/mobile/views/TaxStrategy.vue` (item title).

**iOS**
- `ios-native/Fynla/Features/Actions/ActionsListView.swift`, `ActionCardView.swift`, `ActionsModels.swift` and `ActionsClient.swift` (all new).
- `ios-native/Fynla/App/AppRouter.swift`: adds `.actions` and `.actionCard(id:)`.
- `ios-native/Fynla/Features/Dashboard/DashboardView.swift:128-130`: "See all actions" goes to `.actions`, not `.achievements`.
- `ios-native/Fynla/Features/TaxStrategy/TaxStrategyView.swift`: each item opens `.actionCard`.

**Content**
- `database/seeders/ActionHowToSeeder.php` (new): how-to steps per definition key, status `draft`, and a runner that flips reviewed batches to `approved`.
- `docs/action-how-to/2026-09-26-tax-batch.md`: the tax batch (21 strategies) for CSJ's review, each step sourced.

---

### Task 1: The card payload, one server builder

**Files:**
- Create: `app/Services/Actions/ActionCardService.php`, `app/Services/Actions/ActionCardFigures.php`
- Modify: `app/Services/Mobile/NextActionsService.php:334-401`, `app/Http/Controllers/Api/RecommendationsController.php`, `routes/api.php:1124`
- Test: `tests/Feature/Actions/ActionCardEndpointTest.php`

**Interfaces:**
- Produces: `ActionCardService::for(User $user, string $id): ?array`, returning:

  ```
  {id, type ('recommendation'|'unlock'|'household'), module, module_label, topic,
   deadline: {label, closes_on}|null,  // 'Closes 5 April' from timeline immediate + a tax-year allowance; 'Worth reviewing' otherwise
   title, description,
   why: string[],                      // "Why this matters for you" (empty for unlock items)
   what_this_changes: string[],        // unlock and household items only
   key_figure: {label, value, sub}|null,
   how_to: string[],                   // only when how_to_status = approved
   conflict_note: string|null,
   disclaimer: string|null,
   ask_fyn: {kind:'contextual', request}|{kind:'prompt', prompt},
   primary: {kind:'mark_done', recommendation_id}|{kind:'capture', prompt},
   funding: {accounts:[{id,type,name,balance,warning}], selected_id, selected_type}|null,  // Task 1b
   done: bool, completed_at: string|null}
  ```

- Produces: `GET /api/recommendations/actions/{id}` → `{data: card}`, or 404.

- [ ] **Step 1: Write the failing endpoint tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('returns the card for an open action in the user list, with the same title the list shows', function () {
    $user = actionCardUserWithIsaHeadroom(); // helper below: £60,000 salary, £40,000 easy-access cash
    Sanctum::actingAs($user);

    $item = collect($this->getJson('/api/recommendations/actions')->json('open'))->firstWhere('module', 'tax');
    $card = $this->getJson('/api/recommendations/actions/'.urlencode($item['id']))->assertOk()->json('data');

    expect($card['id'])->toBe($item['id'])
        ->and($card['title'])->toBe($item['title'])
        ->and($card['why'])->not->toBeEmpty()
        ->and($card['key_figure']['value'])->toBeString()
        ->and($card['primary']['kind'])->toBe('mark_done');
});

it('404s an id that is not in the requesting user list', function () {
    $owner = actionCardUserWithIsaHeadroom();
    $other = User::factory()->create();
    Sanctum::actingAs($owner);
    $id = collect($this->getJson('/api/recommendations/actions')->json('open'))->first()['id'];

    Sanctum::actingAs($other);
    $this->getJson('/api/recommendations/actions/'.urlencode($id))->assertNotFound();
});

it('shows a completed action as done, not missing', function () {
    $user = actionCardUserWithIsaHeadroom();
    Sanctum::actingAs($user);
    $item = collect($this->getJson('/api/recommendations/actions')->json('open'))->firstWhere('type', 'recommendation');
    $this->postJson('/api/recommendations/'.urlencode($item['id']).'/mark-done', ['module' => $item['module'], 'recommendation_text' => $item['title']])->assertOk();

    $card = $this->getJson('/api/recommendations/actions/'.urlencode($item['id']))->assertOk()->json('data');

    expect($card['done'])->toBeTrue()->and($card['completed_at'])->not->toBeNull();
});

it('gives an unlock item the waiting-on-you shape', function () {
    $user = User::factory()->create(['onboarding_completed' => true]);
    Sanctum::actingAs($user);
    $unlock = collect($this->getJson('/api/recommendations/actions')->json('open'))->firstWhere('type', 'unlock');

    $card = $this->getJson('/api/recommendations/actions/'.urlencode($unlock['id']))->assertOk()->json('data');

    expect($card['why'])->toBe([])
        ->and($card['what_this_changes'])->not->toBeEmpty()
        ->and($card['primary']['kind'])->toBe('capture');
});
```

The helper `actionCardUserWithIsaHeadroom()` goes in the same file. It creates the user with `annual_employment_income` 60000, `employment_status` `employed` and `onboarding_completed` true, plus one `SavingsAccount` (`is_isa` false, `current_balance` 40000, `interest_rate` 4.5, `ownership_type` `individual`). Use factories, pinning every field that drives a strategy (memory `feedback_check_fixture_keys_against_real_payloads`).

- [ ] **Step 2: Run to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Actions/ActionCardEndpointTest.php`
Expected: FAIL, 404 on the new route.

- [ ] **Step 3: Carry the dropped fields.** In `NextActionsService::recommendationItems()` (`:352`), add to each item:

```php
'card' => [
    'rule_key' => $rec['rule_key'] ?? null,
    'category' => $rec['category'] ?? null,
    'timeline' => $rec['timeline'] ?? null,
    'personalised_context' => array_values((array) ($rec['personalised_context'] ?? [])),
    'conflict_note' => $rec['conflict_note'] ?? null,
    'potential_benefit' => $rec['potential_benefit'] ?? null,
    'requires_advice' => (bool) ($rec['requires_advice'] ?? false),
],
```

In `RecommendationsAggregatorService::composedModuleRecs()` (`:310-330`), keep `requires_advice` from the composed item.

- [ ] **Step 4: Write `ActionCardService`.** `for()` looks the id up in `NextActionsService::buildAll($user->id)`. If it isn't there, it looks in the completed `recommendation_tracking` rows for that user (the same query `RecommendationsController::actions` uses, `:275`); if it's in neither, it returns null.

  For each field:
  - **`why`:** tax items (id prefix `tax_`) take it from `ActionCardFigures::why($user, $strategyType)`. Other recommendations take `card.personalised_context`. Unlock and household items get `[]`.
  - **`what_this_changes`:** for unlock items, `RecommendationRouting::unlockConsequences($module)`. That is a new const map beside `unlockPrompt()`, with one sentence per module saying what the missing data blocks (for example "Your retirement projection is understated until this is in", from the canvas).
  - **`key_figure`:** tax items take it from `ActionCardFigures::keyFigure()`. Others use `card.potential_benefit` as `{label:'Saves about', value:'£X a year'}` through `CurrencyFormatter`. Otherwise it is null.
  - **`deadline`:** `Closes 5 April` when `card.timeline === 'immediate'` and the strategy is an annual allowance (the list is in `ActionCardFigures::ANNUAL_ALLOWANCE_TYPES`). The date comes from the active tax year's `effective_to` in `TaxConfigService`, never a literal. Everything else gets `Worth reviewing`.
  - **`how_to`:** the definition's `how_to_steps`, only when `how_to_status === 'approved'`.
  - **`ask_fyn`:** `item.action.contextual` when present, otherwise `{kind:'prompt', prompt: 'Tell me more about: '.$title}`.
  - **`disclaimer`:** the Global Constraints text when `card.requires_advice`, or when the module is protection or investment.

- [ ] **Step 5: Write `ActionCardFigures`.** For each tax `strategy_type`, it builds `why` bullets and a key figure from the extras the strategy already publishes. Examples:
  - **`isa_topup_vs_psa`:** reads `isa_remaining`, `taxable_interest_sheltered` and `marginal_rate`.
  - **`pension_tax_relief`:** reads `suggested_contribution`, `relief_rate` and `limit_basis`.

  Every bullet is a sentence with the user's figure formatted through `CurrencyFormatter`. A strategy with no extras gets `why = []` and `key_figure = null`, never an invented figure. Unit-test each strategy type in `tests/Unit/Services/Actions/ActionCardFiguresTest.php` from the strategy's real output (run the strategy, feed its item in), asserting that the bullet contains the item's own figure. That rules out a fixture shape the strategy never produces.

- [ ] **Step 6: Wire the route and controller.** `Route::get('recommendations/actions/{id}', [RecommendationsController::class, 'show'])->where('id', '.*');` `show()` returns `response()->json(['data' => $card])`, or aborts 404.

- [ ] **Step 7: Run the tests.** Run: `./vendor/bin/pest tests/Feature/Actions tests/Unit/Services/Actions`. Expected: PASS.

- [ ] **Step 8: Commit.** Message: `feat(actions): one card payload per action — GET /api/recommendations/actions/{id}`.

### Task 1b: "Fund from" on money-moving actions (CSJ 2026-09-26: build it in the first pass)

**Why:** Design C's ISA card lists "Fund from" (the user's accounts, with balances), and the user picks one. The plans pages have this today, built twice: `InvestmentPlanService::buildEligibleFundingAccounts` (`:729`) and `RetirementPlanService::buildEligibleFundingAccounts` (`:567`). The retirement copy hardcodes 6 months for the emergency-fund warning. The ownership check in `PlanController::updateFundingSource` (`:190`) rejects joint accounts (Rule 6). One source replaces both copies.

**Files:**
- Create: `app/Services/Plans/FundingAccounts.php`, providing `eligibleFor(User $user): array` and `recommend(array $accounts): ?array`, moved from the two copies. The emergency threshold comes from `PlanConfigService::getEmergencyFundTargetMonths()`. Accounts come from `SavingsStore::forUser` (non-ISA, liquid types) and GIA investment accounts. Each carries `{id, type, name, balance, warning}`, and `balance` is the user's share for joint accounts (`CalculatesOwnershipShare`).
- Modify: `InvestmentPlanService` and `RetirementPlanService` to call it and delete their copies. Modify `PlanController::updateFundingSource` so the ownership check is `where(fn ($q) => $q->where('user_id', $id)->orWhere('joint_owner_id', $id))`.
- Modify: `ActionCardService`. For money-moving tax strategies (`ActionCardFigures::FUNDED_TYPES` = `isa_topup_vs_psa`, `pension_tax_relief`, `spouse_pension_topup`, `junior_isa`, `lifetime_isa`) the card carries `funding: {accounts, selected_id, selected_type}`. The selection is read from `plan_action_funding_selections` with `plan_type = 'tax'`, `action_category = strategy_type` and `target_account_id = 0`. When there is no saved selection, `selected` is `FundingAccounts::recommend()`.
- Saving: reuse `PUT /api/plans/tax/funding-source`. Allow `tax` in the route's `{type}` constraint, then check how the plan type is stored against the `string(20)` column.
- Clients (Tasks 3, 4 and 5): a "Fund from" radio list under the key figure. Show each warning under its account. Save on change.
- Test: `tests/Feature/Actions/ActionCardFundingTest.php`:
  - the ISA card lists the user's cash accounts, largest first, with balances;
  - a joint account appears at the user's share;
  - a saved choice is returned as `selected_id` on the next load;
  - another user's account id is rejected with 422;
  - the plans pages still return the same `eligible_accounts` (a regression pin for both plans).

- [ ] Step 1: Write `ActionCardFundingTest` (the cases above). Run it and watch it fail.
- [ ] Step 2: Move the builder into `FundingAccounts` and point both plan services at it. Run the existing plan tests: `./vendor/bin/pest tests/Unit/Services/Plans tests/Feature/Plans`.
- [ ] Step 3: Make the ownership check joint-aware, and allow the `tax` plan type.
- [ ] Step 4: Add `funding` to the card for `FUNDED_TYPES`. Run the Task 1 and 1b tests and see them pass.
- [ ] Step 5: Commit. Message: `feat(actions): Fund from on money-moving action cards; one eligible-accounts source`.

### Task 2: How-to steps, stored once and gated on review

**Files:**
- Create: `database/migrations/2026_09_27_000001_add_how_to_to_action_definitions.php`, `database/seeders/ActionHowToSeeder.php`, `docs/action-how-to/2026-09-26-tax-batch.md`
- Test: `tests/Unit/Services/Actions/ActionCardHowToTest.php`

- [ ] **Step 1: Failing test.** An approved definition's steps appear on its card; a draft definition's steps don't.

```php
it('shows steps only once CSJ has approved them', function () {
    $user = actionCardUserWithIsaHeadroom();
    TaxActionDefinition::where('strategy_type', 'isa_topup_vs_psa')->update([
        'how_to_steps' => json_encode(['Open or choose a cash ISA.']),
        'how_to_status' => 'draft',
    ]);
    $id = 'tax_isa_topup_vs_psa';

    expect(app(ActionCardService::class)->for($user, $id)['how_to'])->toBe([]);

    TaxActionDefinition::where('strategy_type', 'isa_topup_vs_psa')->update(['how_to_status' => 'approved']);
    expect(app(ActionCardService::class)->for($user, $id)['how_to'])->toBe(['Open or choose a cash ISA.']);
});
```

- [ ] **Step 2: Migration.** For each of the six tables (`tax`, `retirement`, `investment`, `protection`, `savings` and `estate` `_action_definitions`), add `$table->json('how_to_steps')->nullable(); $table->enum('how_to_status', ['draft', 'approved'])->default('draft');`, each guarded with `Schema::hasColumn`. Never run `migrate:fresh` (CLAUDE.md).
- [ ] **Step 3: Draft the tax batch.** For the 21 tax strategy rows, write the steps in `docs/action-how-to/2026-09-26-tax-batch.md`:
  - 3 to 6 short steps each;
  - figures written as placeholders filled from the card, such as `{isa_remaining}`;
  - each step naming its gov.uk or HMRC source (Rule 23).

  `ActionHowToSeeder` loads that file's approved entries only.
- [ ] **Step 4: CSJ reviews the tax batch.** **Stop here and hand the document to CSJ.** Flip only the entries CSJ approves. The other modules follow in batches after the tax batch, in this order: savings (54), protection (32), retirement (26), investment (17), estate (12).
- [ ] **Step 5: Run the tests and commit.** Message: `feat(actions): how-to steps per definition, shown only once approved`.

### Task 3: Web card

**Files:**
- Create: `resources/js/views/Actions/ActionCardView.vue`
- Modify: `resources/js/router/index.js:1042`, `resources/js/views/Actions/ActionsDashboard.vue:171`, `resources/js/views/GamifiedDashboard.vue:519`, `resources/js/components/TaxStrategy/StrategyRecommendationList.vue`
- Test: `tests/frontend/views/Actions/ActionCardView.test.js`

- [ ] **Step 1: Failing Vitest.** Drive the real lifecycle: mock `api.get('/recommendations/actions/tax_isa_topup_vs_psa')` with a payload shaped like the endpoint's (copy one from Task 1's test run; memory `feedback_check_fixture_keys_against_real_payloads`). Assert:
  - the title, each `why` bullet, the key figure and the deadline chip text render;
  - "Ask Fyn about this" dispatches `aiChat/startContextualConversation` for a contextual `ask_fyn`, and `aiChat/prefillPrompt` then `aiChat/open` for a prompt;
  - "Mark as done" posts to `/recommendations/{id}/mark-done` and then shows the done state;
  - the steps section is absent when `how_to` is empty.
- [ ] **Step 2: Build the view to canvas C.** Order:
  1. the module and topic label, and the deadline chip (`violet-*`);
  2. the heading and description;
  3. "Why this matters for you" (list);
  4. the key-figure panel (label, value, sub);
  5. the steps, when present;
  6. the overlap note, when present;
  7. the disclaimer;
  8. "Ask Fyn about this" and "Mark as done".

  An unlock card shows "What this changes" and "Add it now" (which opens Fyn with `primary.prompt`). Wrap in `<AppLayout>`. Use `currencyMixin` for any formatting the payload doesn't already carry. Add no icons.
- [ ] **Step 3: Link to it.**
  - `ActionsDashboard.goToAction`: `this.$router.push({name: 'ActionCard', params: {actionId: action.id}})` for every item. The card's own button carries the capture or navigate action.
  - The `GamifiedDashboard.openRec` navigate branch: push to the card.
  - `StrategyRecommendationList`: render `composed_plan.items`, with the title as a `router-link` to `ActionCard` with `tax_{type}`.
- [ ] **Step 4: Run Vitest, then walk in Playwright** on the local app and on csjones. Open a tax item, a savings item, an unlock item and a completed item from `/actions`, the dashboard and `/tax-strategy`. On each card, fill and press Ask Fyn and Mark as done.
- [ ] **Step 5: Commit.** Message: `feat(web): every action opens its own card`.

### Task 4: `/m` card

**Files:**
- Create: `resources/mobile/views/ActionCard.vue`
- Modify: `resources/mobile/router.js:71`, `resources/mobile/views/Actions.vue:116`, `resources/mobile/views/Dashboard.vue:836`, `resources/mobile/views/TaxStrategy.vue`
- Test: `resources/mobile/views/__tests__/ActionCard.spec.js`

- [ ] **Step 1: Failing spec,** with the same assertions as Task 3 against the `/m` view.
  - Ask Fyn: `this.$refs.chrome.openContextualFyn(request)` or `openFyn()` + `send(prompt)` (`MobileChrome.vue:367`, `Actions.vue:129`).
  - Mark done: through the `/m` api client.
- [ ] **Step 2: Build the view** in `<MobileChrome :title="card.module_label" back>`, in the same section order as Task 3, using the `/m` tokens.
- [ ] **Step 3: Link to it.**
  - `Actions.vue openItem`: `this.$router.push({name: 'm-action-card', params: {id: item.id}})`.
  - `Dashboard.vue onActionTap`, navigate branch: push to the card.
  - `TaxStrategy.vue`: item title links to the card.
- [ ] **Step 4: Verify with the `verify-m` skill** on the local app and on csjones, covering the same four cards and interactions as Task 3.
- [ ] **Step 5: Commit.** Message: `feat(m): every action opens its own card`.

### Task 5: iOS actions list and card

**Files:**
- Create: `ios-native/Fynla/Features/Actions/ActionsModels.swift`, `ActionsClient.swift`, `ActionsListView.swift`, `ActionCardView.swift`
- Modify: `ios-native/Fynla/App/AppRouter.swift:3-26`, `ios-native/Fynla/Features/Dashboard/DashboardView.swift:128-130`, `ios-native/Fynla/Features/TaxStrategy/TaxStrategyView.swift:255-300`
- Test: `ios-native/FynlaTests/ActionCardTests.swift`

- [ ] **Step 1: Failing XCTest.**
  - `ActionCard` decodes the Task 1 payload (a fixture copied from a real response).
  - `ActionsClient.card(id:)` requests `api/recommendations/actions/{id}` with the id percent-encoded.
  - `AppRoute.actions` exists.
- [ ] **Step 2: Build the views.**
  - **`ActionsListView`:** open and done sections, as in the canvas Mobile artboard; rows push `.actionCard(id:)`.
  - **`ActionCardView`:** in the same section order as Task 3. Ask Fyn goes through the existing `presentContextualFyn` / `presentFyn(prompt:)` (`AppRootView.swift:876`, `:888`); Mark as done goes through `ActionsClient`.
- [ ] **Step 3: Route to them.** "See all actions" goes to `.actions`. Tax Strategy items open `.actionCard(id: item.recommendationId)`.
- [ ] **Step 4: Build and test on the simulator** with the `ios-simulator` skill, then walk it against fynla.org after the web and `/m` release. Both iOS schemes point at production (memory `project_ios_programme_status`).
- [ ] **Step 5: Commit.** Message: `feat(ios): actions list and card; See all actions opens the list`.

### Task 6: Release gate

- [ ] Full Pest (one process only), full Vitest and the iOS test target.
- [ ] Deploy the branch to csjones (`deploy/csjones-fynla/build.sh`, git pull, the migration, `ActionHowToSeeder`, preserve-old-chunks upload).
- [ ] Walk on web and `/m` as a new `/savetax` user: every action on `/actions` opens its card with the user's figures.
- [ ] Then open the PR to `dev`, with Mobile impact: web, `/m` and iOS.

## Appendix: canvas text (artboard C, verbatim excerpts)

- Page: "Your actions / Every open action, with the reasoning and the money attached. Grouped by the part of your plan it belongs to."
- A card: "Closes 5 April · Savings · ISA allowance / Use your remaining ISA allowance / You have paid in £8,600 this tax year. … / Why this matters for you / [three bullets] / Allowance left £11,400 / Saves about £187 in tax a year / Fund from [accounts] / Ask Fyn about this / Mark as done".
- Protection card: "Worth reviewing … / Shortfall £180,000 / Against a £192,000 balance / Fynla does not recommend products. This is guidance based on the figures you have entered."
- "Waiting on you / Not things to do — things we need before the figures above can be right. / Unlock · Retirement · Workplace pension / Add your employer's contribution rate / … / What this changes / [bullets] / Add it now".
- Mobile list: "Your actions / Everything that's open, and what you've already done / This tax year 7 of 14 done / Open … / Done …".

"Fund from" (the canvas ISA card) is Task 1b. CSJ decided on 2026-09-26 to build it in the first pass.
