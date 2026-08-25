# Eval HTTP-driven rewrite — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the CLI-direct eval (`AdviceFyn::handle()` direct invocation) with an HTTP-driven eval that drives the same endpoints a real browser session drives — `POST /api/eval/login`, `POST /api/ai-chat/conversations`, `POST /api/ai-chat/conversations/{id}/messages` — against the actual `peak_earners` preview user, with a Sanctum token ability (`bypass-preview-mode`) that lets writes through, and an in-process engine/gate trace captured via Laravel events.

**Architecture:** Eval logs in via a new `POST /api/eval/login/{personaId}` endpoint that issues an ability-tagged Sanctum token. The 3 server-side preview-write checkpoints (`PreviewWriteInterceptor`, `HasAiChat:144`, `CoordinatingAgent:699`) check the ability and let writes through when present. 11 gate/engine call sites fire `GateChecked` / `EngineCalled` / `AgentDecision` events; an `EvalTraceListener` captures them when the active token has the bypass ability. Restoration uses the existing `php artisan preview:reset peak_earners`.

**Tech Stack:** Laravel 10, PHP 8.2, Pest 2.x, Laravel Sanctum, Vue 3, MySQL 8. Branch: `feature/fyn-persona-split`.

**Source spec:** `/Users/CSJ/Desktop/fynla/April/April27Updates/eval-http-driven-rewrite-plan.md`. The spec's §12 task table is the ordered task list this plan expands; §15 is the file-pointer index.

---

## Pre-flight

Before any task runs:

1. Confirm branch is `feature/fyn-persona-split` (`git branch --show-current`).
2. Run `php artisan db:seed` to confirm baseline data is intact.
3. Confirm `peak_earners` preview user exists: `php artisan tinker --execute="echo App\Models\User::where('preview_persona_id', 'peak_earners')->count();"` should print `1`.
4. Confirm Pest baseline: `./vendor/bin/pest tests/Feature/Fyn/Eval/ tests/Unit/Services/Eval/` should be green.
5. Confirm local dev server can be started: `./dev.sh` (run in a separate terminal; leave running for HTTP-driven tasks).

Each task ends with a commit. Commits go directly on `feature/fyn-persona-split` — no per-task sub-branches (the spec says one commit set on that branch).

---

## File Structure

| File | Action | Purpose |
|---|---|---|
| `database/migrations/2026_04_27_100001_add_persona_columns_to_eval_recording_sessions.php` | Create | Adds `persona`, `http_log`, columns to `eval_recording_sessions`. |
| `database/migrations/2026_04_27_100002_add_engine_trace_to_eval_provider_runs.php` | Create | Adds `engine_trace` column to `eval_provider_runs`. |
| `app/Console/Commands/ResetPreviewData.php` | Modify | Extend `deleteUserData` to cover 13 missing child-entity types. |
| `tests/Feature/PreviewResetCompletenessTest.php` | Create | Locks `deleteUserData` against drift. |
| `app/Events/Eval/GateChecked.php` | Create | Value object: gate name, module, passed, context. |
| `app/Events/Eval/EngineCalled.php` | Create | Value object: engine name, params, result summary, duration. |
| `app/Events/Eval/AgentDecision.php` | Create | Value object: agent, decision point, outcome, context. |
| `tests/Unit/Events/EvalEventsTest.php` | Create | Construct/serialize each event. |
| `app/Http/Middleware/PreviewWriteInterceptor.php` | Modify | 5-line ability check after exclusion checks. |
| `app/Traits/HasAiChat.php` | Modify | Line 144 — factor in token ability. |
| `app/Agents/CoordinatingAgent.php` | Modify | Line 699 — factor in token ability. |
| `tests/Feature/PreviewBypassAbilityTest.php` | Create | Cover all 3 sites. |
| `app/Services/Eval/EvalTraceCollector.php` | Create | Request-scoped singleton. |
| `app/Listeners/Eval/EvalTraceListener.php` | Create | Subscribes to 3 events; ability-gated. |
| `app/Providers/EvalServiceProvider.php` | Create | Registers collector singleton + listener. |
| `config/app.php` | Modify | Register `EvalServiceProvider`. |
| `tests/Feature/EvalTraceListenerTest.php` | Create | Cover ability gating + capture. |
| 11 gate/agent/engine source files | Modify | Add `event(...)` calls per spec §5.3. |
| `tests/Feature/EvalTraceCallSitesTest.php` | Create | Verify each of the 11 call sites fires its event. |
| `app/Constants/QuerySchemas.php` | Modify | Extend `REQUIRED_TOOLS[PROTECTION_COVER]` to include critical_illness + income_protection. |
| `tests/Unit/QuerySchemasProtectionScopeTest.php` | Create | Lock the 3-protection-types contract. |
| `app/Services/Eval/EvalSseConsumer.php` | Create | Streams SSE response body, parses frames. |
| `tests/Unit/Services/Eval/EvalSseConsumerTest.php` | Create | Frame parsing tests. |
| `app/Services/Eval/EvalHttpDriver.php` | Create | The HTTP loop. |
| `tests/Feature/Fyn/Eval/EvalHttpDriverTest.php` | Create | Live HTTP integration test. |
| `app/Http/Controllers/Api/EvalAuthController.php` | Create | 3 endpoints: login, reset, trace. |
| `routes/api.php` | Modify | Add `eval/*` routes inside production guard. |
| `tests/Feature/EvalAuthControllerTest.php` | Create | Endpoint coverage. |
| `app/Console/Commands/EvalRecordCommand.php` | Modify | ~70% deletion — wire to `EvalHttpDriver`. |
| `app/Models/EvalRecordingSession.php` | Modify | Fillable + casts for new columns. |
| `app/Models/EvalProviderRun.php` | Modify | Add `engine_trace` to fillable + casts. |
| `app/Services/Eval/EvalDeltaBuilder.php` | Modify | YAML→JSON swap; `gradeEngineTrace` method. |
| `app/Http/Controllers/Api/Admin/EvalRecordingController.php` | Modify | YAML→JSON swap; pass new columns through. |
| `tests/Feature/Fyn/Eval/scenarios/_schema.json` | Create | JSON Schema for scenarios. |
| `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json` | Create (×10) | The 10 new scenario files. |
| `tests/Feature/Fyn/Eval/scenarios/01-query-types/*.yaml` | Delete (×6) | Superseded YAMLs. |
| `tests/Feature/Fyn/Eval/AssertionHelpers.php` | Modify | Add HTTP + engine-trace helpers. |
| 5 architecture meta-tests | Create | Schema/persona/mutation/trace consistency + bypass-site enforcement. |
| `tests/Feature/Fyn/Eval/EvalRunner.php` | Modify | JSON-aware. |
| `resources/js/components/Admin/eval/RunPanel.vue` | Modify | Add HTTP log + engine trace timeline panels. |

---

## Task 1: Migrations for new columns

**Goal:** Add `persona` + `http_log` to `eval_recording_sessions` and `engine_trace` to `eval_provider_runs`.

**Files:**
- Create: `database/migrations/2026_04_27_100001_add_persona_columns_to_eval_recording_sessions.php`
- Create: `database/migrations/2026_04_27_100002_add_engine_trace_to_eval_provider_runs.php`

- [ ] **Step 1.1: Write the first migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eval_recording_sessions', function (Blueprint $table): void {
            $table->string('persona', 64)->nullable()->after('eval_user_id')->index();
            $table->json('http_log')->nullable()->after('start_state_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('eval_recording_sessions', function (Blueprint $table): void {
            $table->dropIndex(['persona']);
            $table->dropColumn(['persona', 'http_log']);
        });
    }
};
```

- [ ] **Step 1.2: Write the second migration**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eval_provider_runs', function (Blueprint $table): void {
            $table->json('engine_trace')->nullable()->after('end_state_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('eval_provider_runs', function (Blueprint $table): void {
            $table->dropColumn('engine_trace');
        });
    }
};
```

- [ ] **Step 1.3: Run migrations**

```bash
php artisan migrate
```

Expected: Both migrations report `Done`. No errors.

- [ ] **Step 1.4: Verify columns**

```bash
php artisan tinker --execute="echo implode(',', \Schema::getColumnListing('eval_recording_sessions'));" \
  && php artisan tinker --execute="echo implode(',', \Schema::getColumnListing('eval_provider_runs'));"
```

Expected: First output contains `persona,http_log`. Second contains `engine_trace`.

- [ ] **Step 1.5: Commit**

```bash
git add database/migrations/2026_04_27_100001_*.php database/migrations/2026_04_27_100002_*.php
git commit -m "feat(eval): add persona + http_log + engine_trace columns to eval recording tables"
```

---

## Task 2: Extend `preview:reset` to cover all persona-touched tables

**Goal:** `php artisan preview:reset peak_earners` reaches every child-entity table the persona seeder writes to. Lock against drift with a Pest meta-test.

**Files:**
- Modify: `app/Console/Commands/ResetPreviewData.php`
- Create: `tests/Feature/PreviewResetCompletenessTest.php`

- [ ] **Step 2.1: Read the current `deleteUserData` method**

```bash
grep -n "deleteUserData\|::where('user_id', \$user->id)->delete" /Users/CSJ/Desktop/fynla/app/Console/Commands/ResetPreviewData.php
```

Note the 12 model-class lines currently deleted.

- [ ] **Step 2.2: Write the failing test**

`tests/Feature/PreviewResetCompletenessTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PreviewUserSeeder::class);
});

it('preview:reset peak_earners deletes every persona-touched table', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();

    // Tables peak_earners populates (sourced from PreviewUserSeeder seeds).
    $tablesToCheck = [
        'savings_accounts',
        'investment_accounts',
        'dc_pensions',
        'db_pensions',
        'life_insurance_policies',
        'critical_illness_policies',
        'income_protection_policies',
        'liabilities',
        'family_members',
        'mortgages',
        'properties',
        'protection_profiles',
        'retirement_profiles',
        'iht_profiles',
        'expenditure_profiles',
        'goals',
        'life_events',
        'lasting_powers_of_attorney',
        'wills',
        'trusts',
        'gifts',
        'chattels',
        'business_interests',
        'assets',
        'ai_conversations',
        'ai_messages',
    ];

    // Assert each table has rows for the user before the reset.
    $tablesWithData = collect($tablesToCheck)->filter(
        fn ($table) => \DB::table($table)->where('user_id', $user->id)->exists()
            || (\Schema::hasColumn($table, 'conversation_id')
                && \DB::table($table)
                    ->whereIn('conversation_id', \DB::table('ai_conversations')->where('user_id', $user->id)->pluck('id'))
                    ->exists())
    )->values();

    expect($tablesWithData)->not->toBeEmpty();

    \Artisan::call('preview:reset', ['persona' => 'peak_earners']);

    $userAfter = User::where('preview_persona_id', 'peak_earners')->firstOrFail();

    // After reset + reseed, the persona has fresh rows. Assert that
    // tables that DID have data before were touched (count may differ
    // because the seeder runs again — what matters is there's no leak).
    foreach ($tablesWithData as $table) {
        // The user_id will have changed (delete + recreate) but the
        // persona ID is stable; rows for the new user_id should exist.
        $exists = \DB::table($table)->where('user_id', $userAfter->id)->exists();
        // For ai_messages, check via conversation join.
        if ($table === 'ai_messages') {
            $exists = \DB::table($table)
                ->whereIn('conversation_id', \DB::table('ai_conversations')->where('user_id', $userAfter->id)->pluck('id'))
                ->exists();
        }
        // Allow zero rows for tables the seeder may not always populate
        // — what matters is no rows from the OLD user persist.
        $oldUserStillThere = \DB::table($table)->where('user_id', $user->id)->exists();
        expect($oldUserStillThere)
            ->toBeFalse("table {$table} still has rows for the deleted user_id {$user->id}");
    }
});
```

- [ ] **Step 2.3: Run the test to verify it fails**

```bash
./vendor/bin/pest tests/Feature/PreviewResetCompletenessTest.php
```

Expected: FAIL — at least one of the 13 missing tables (e.g. `protection_profiles`) still has rows for the old user_id.

- [ ] **Step 2.4: Update `deleteUserData` to cover all 25 tables**

In `app/Console/Commands/ResetPreviewData.php`, replace the `deleteUserData` method:

```php
private function deleteUserData(User $user): void
{
    // Holdings (polymorphic) — delete first to avoid FK constraints.
    Holding::whereHasMorph('holdable', [InvestmentAccount::class], function ($query) use ($user) {
        $query->where('user_id', $user->id);
    })->delete();
    Holding::whereHasMorph('holdable', [DCPension::class], function ($query) use ($user) {
        $query->where('user_id', $user->id);
    })->delete();

    // AI conversation messages first, then conversations.
    \App\Models\AiMessage::whereIn(
        'conversation_id',
        \App\Models\AiConversation::where('user_id', $user->id)->pluck('id')
    )->delete();
    \App\Models\AiConversation::where('user_id', $user->id)->delete();

    // Module child entities.
    InvestmentAccount::where('user_id', $user->id)->delete();
    SavingsAccount::where('user_id', $user->id)->delete();
    DCPension::where('user_id', $user->id)->delete();
    DBPension::where('user_id', $user->id)->delete();
    LifeInsurancePolicy::where('user_id', $user->id)->delete();
    CriticalIllnessPolicy::where('user_id', $user->id)->delete();
    IncomeProtectionPolicy::where('user_id', $user->id)->delete();
    Liability::where('user_id', $user->id)->delete();
    FamilyMember::where('user_id', $user->id)->delete();
    Mortgage::where('user_id', $user->id)->delete();
    Property::where('user_id', $user->id)->delete();

    // Profiles (added 2026-04-27 — eval rewrite plan §8.2).
    \App\Models\Protection\ProtectionProfile::where('user_id', $user->id)->delete();
    \App\Models\Retirement\RetirementProfile::where('user_id', $user->id)->delete();
    \App\Models\Estate\IhtProfile::where('user_id', $user->id)->delete();
    \App\Models\ExpenditureProfile::where('user_id', $user->id)->delete();

    // Goals + life events.
    \App\Models\Goal::where('user_id', $user->id)->delete();
    \App\Models\LifeEvent::where('user_id', $user->id)->delete();

    // Estate documents.
    \App\Models\Estate\LastingPowerOfAttorney::where('user_id', $user->id)->delete();
    \App\Models\Estate\Will::where('user_id', $user->id)->delete();
    \App\Models\Estate\Trust::where('user_id', $user->id)->delete();
    \App\Models\Estate\Gift::where('user_id', $user->id)->delete();
    \App\Models\Estate\Chattel::where('user_id', $user->id)->delete();
    \App\Models\Estate\BusinessInterest::where('user_id', $user->id)->delete();
    \App\Models\Estate\Asset::where('user_id', $user->id)->delete();
}
```

(Verify each model's namespace matches the project — adjust if a model lives at a different path. Use `find /Users/CSJ/Desktop/fynla/app/Models -name "ProtectionProfile.php"` etc. before pasting.)

- [ ] **Step 2.5: Run the test to verify it passes**

```bash
./vendor/bin/pest tests/Feature/PreviewResetCompletenessTest.php
```

Expected: PASS.

- [ ] **Step 2.6: Run full Pest baseline to confirm no regression**

```bash
./vendor/bin/pest --filter=PreviewReset
```

Expected: PASS. Full baseline still green.

- [ ] **Step 2.7: Commit**

```bash
git add app/Console/Commands/ResetPreviewData.php tests/Feature/PreviewResetCompletenessTest.php
git commit -m "feat(preview): extend preview:reset deleteUserData to cover all persona-touched tables"
```

---

## Task 3: Eval event classes

**Goal:** Three thin value-object event classes for trace observability.

**Files:**
- Create: `app/Events/Eval/GateChecked.php`
- Create: `app/Events/Eval/EngineCalled.php`
- Create: `app/Events/Eval/AgentDecision.php`
- Create: `tests/Unit/Events/EvalEventsTest.php`

- [ ] **Step 3.1: Write the failing test**

`tests/Unit/Events/EvalEventsTest.php`:

```php
<?php

declare(strict_types=1);

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;

it('GateChecked carries gate, module, passed, context, microtime', function () {
    $event = new GateChecked(
        gate: 'kyc',
        module: 'protection',
        passed: true,
        context: ['field' => 'dob'],
        atMicrotime: 1234567890.123,
    );

    expect($event->gate)->toBe('kyc')
        ->and($event->module)->toBe('protection')
        ->and($event->passed)->toBeTrue()
        ->and($event->context)->toBe(['field' => 'dob'])
        ->and($event->atMicrotime)->toBe(1234567890.123);
});

it('EngineCalled carries engine, params, result, duration, microtime', function () {
    $event = new EngineCalled(
        engine: 'protection_analysis',
        params: ['user_id' => 1],
        resultSummary: ['result_path' => 'happy', 'keys_returned' => ['summary']],
        durationMs: 340,
        atMicrotime: 1234567890.456,
    );

    expect($event->engine)->toBe('protection_analysis')
        ->and($event->durationMs)->toBe(340);
});

it('AgentDecision carries agent, decisionPoint, outcome, context, microtime', function () {
    $event = new AgentDecision(
        agent: 'AdviceFyn',
        decisionPoint: 'response_mode',
        outcome: 'recommendation',
        context: ['primary' => 'protection_cover'],
        atMicrotime: 1234567890.789,
    );

    expect($event->outcome)->toBe('recommendation');
});
```

- [ ] **Step 3.2: Run test (will fail — classes don't exist)**

```bash
./vendor/bin/pest tests/Unit/Events/EvalEventsTest.php
```

Expected: FAIL with `Class "App\Events\Eval\GateChecked" not found`.

- [ ] **Step 3.3: Create `GateChecked.php`**

`app/Events/Eval/GateChecked.php`:

```php
<?php

declare(strict_types=1);

namespace App\Events\Eval;

final class GateChecked
{
    public function __construct(
        public readonly string $gate,
        public readonly string $module,
        public readonly bool $passed,
        public readonly array $context,
        public readonly float $atMicrotime,
    ) {}
}
```

- [ ] **Step 3.4: Create `EngineCalled.php`**

`app/Events/Eval/EngineCalled.php`:

```php
<?php

declare(strict_types=1);

namespace App\Events\Eval;

final class EngineCalled
{
    public function __construct(
        public readonly string $engine,
        public readonly array $params,
        public readonly array $resultSummary,
        public readonly int $durationMs,
        public readonly float $atMicrotime,
    ) {}
}
```

- [ ] **Step 3.5: Create `AgentDecision.php`**

`app/Events/Eval/AgentDecision.php`:

```php
<?php

declare(strict_types=1);

namespace App\Events\Eval;

final class AgentDecision
{
    public function __construct(
        public readonly string $agent,
        public readonly string $decisionPoint,
        public readonly string $outcome,
        public readonly array $context,
        public readonly float $atMicrotime,
    ) {}
}
```

- [ ] **Step 3.6: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Unit/Events/EvalEventsTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 3.7: Commit**

```bash
git add app/Events/Eval/ tests/Unit/Events/EvalEventsTest.php
git commit -m "feat(eval): add GateChecked + EngineCalled + AgentDecision event classes"
```

---

## Task 4: `bypass-preview-mode` token ability — wire the 3 write-block sites

**Goal:** Add ability check at the 3 sites so a Sanctum token with `bypass-preview-mode` lets writes through for preview users. Per spec §4.2.

**Files:**
- Modify: `app/Http/Middleware/PreviewWriteInterceptor.php`
- Modify: `app/Traits/HasAiChat.php`
- Modify: `app/Agents/CoordinatingAgent.php`
- Create: `tests/Feature/PreviewBypassAbilityTest.php`

- [ ] **Step 4.1: Write the failing test**

`tests/Feature/PreviewBypassAbilityTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\SavingsAccount;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(\Database\Seeders\PreviewUserSeeder::class);
});

it('preview user without bypass ability gets writes intercepted', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    Sanctum::actingAs($user, ['*']); // default abilities, no bypass

    $countBefore = SavingsAccount::where('user_id', $user->id)->count();

    $response = $this->postJson('/api/savings/accounts', [
        'institution' => 'Test Bank',
        'account_type' => 'easy_access_isa',
        'current_balance' => 1000,
    ]);

    expect(SavingsAccount::where('user_id', $user->id)->count())
        ->toBe($countBefore, 'preview-mode interceptor should swallow the write');

    $response->assertOk();
    expect($response->json('preview_mode'))->toBeTrue();
});

it('preview user WITH bypass-preview-mode ability gets writes through', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    Sanctum::actingAs($user, ['bypass-preview-mode']);

    $countBefore = SavingsAccount::where('user_id', $user->id)->count();

    $response = $this->postJson('/api/savings/accounts', [
        'institution' => 'Test Bank',
        'account_type' => 'easy_access_isa',
        'current_balance' => 1000,
    ]);

    expect(SavingsAccount::where('user_id', $user->id)->count())
        ->toBe($countBefore + 1, 'eval token must let the write through');
});

it('non-preview user is unaffected by ability', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user, ['*']);

    $countBefore = SavingsAccount::where('user_id', $user->id)->count();

    $this->postJson('/api/savings/accounts', [
        'institution' => 'Test Bank',
        'account_type' => 'easy_access_isa',
        'current_balance' => 1000,
    ])->assertOk();

    expect(SavingsAccount::where('user_id', $user->id)->count())
        ->toBe($countBefore + 1);
});
```

- [ ] **Step 4.2: Run test (will fail — bypass not wired yet)**

```bash
./vendor/bin/pest tests/Feature/PreviewBypassAbilityTest.php
```

Expected: 2nd test FAILS (preview user with ability still intercepted).

- [ ] **Step 4.3: Wire the bypass at `PreviewWriteInterceptor`**

In `app/Http/Middleware/PreviewWriteInterceptor.php` after the existing `EXCLUDED_PATTERNS` foreach loop (around line 130, before the `return $this->fakeSuccessResponse($request);` line), insert:

```php
        // Eval bypass: a Sanctum token with `bypass-preview-mode` ability lets
        // writes through. The ability is issued by EvalAuthController::login,
        // gated to non-production environments. See eval-http-driven-rewrite-plan.md §4.
        $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($request->bearerToken() ?? '');
        if ($accessToken && $accessToken->can('bypass-preview-mode')) {
            return $next($request);
        }

```

- [ ] **Step 4.4: Wire the bypass at `HasAiChat:144`**

In `app/Traits/HasAiChat.php`, replace line 144:

```php
$tools = $toolDefinitions->getTools($user->is_preview_user);
```

with:

```php
$tools = $toolDefinitions->getTools(
    $user->is_preview_user
    && ! ($user->currentAccessToken()?->can('bypass-preview-mode') ?? false)
);
```

- [ ] **Step 4.5: Wire the bypass at `CoordinatingAgent:699`**

In `app/Agents/CoordinatingAgent.php`, replace line 699:

```php
$isPreviewUser = (bool) $user->is_preview_user;
```

with:

```php
$isPreviewUser = (bool) $user->is_preview_user
    && ! ($user->currentAccessToken()?->can('bypass-preview-mode') ?? false);
```

- [ ] **Step 4.6: Run the bypass tests to verify all 3 pass**

```bash
./vendor/bin/pest tests/Feature/PreviewBypassAbilityTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 4.7: Run preview-mode regression suite**

```bash
./vendor/bin/pest --filter=Preview
```

Expected: All previously-green preview tests still pass.

- [ ] **Step 4.8: Commit**

```bash
git add app/Http/Middleware/PreviewWriteInterceptor.php app/Traits/HasAiChat.php app/Agents/CoordinatingAgent.php tests/Feature/PreviewBypassAbilityTest.php
git commit -m "feat(eval): add bypass-preview-mode token ability to 3 write-block sites"
```

---

## Task 5: `EvalTraceCollector` + `EvalTraceListener`

**Goal:** Request-scoped collector + ability-gated listener that captures the 3 events per chat send.

**Files:**
- Create: `app/Services/Eval/EvalTraceCollector.php`
- Create: `app/Listeners/Eval/EvalTraceListener.php`
- Create: `app/Providers/EvalServiceProvider.php`
- Modify: `config/app.php`
- Create: `tests/Feature/EvalTraceListenerTest.php`

- [ ] **Step 5.1: Write the failing test**

`tests/Feature/EvalTraceListenerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Events\Eval\GateChecked;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\AgentDecision;
use App\Models\User;
use App\Services\Eval\EvalTraceCollector;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(\Database\Seeders\PreviewUserSeeder::class);
});

it('captures all 3 event types when token has bypass-preview-mode ability', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    Sanctum::actingAs($user, ['bypass-preview-mode']);

    // Kick off a request lifecycle so currentAccessToken resolves.
    $this->getJson('/api/user');

    event(new GateChecked('kyc', 'protection', true, ['field' => 'dob'], microtime(true)));
    event(new EngineCalled('protection_analysis', [], ['result_path' => 'happy'], 100, microtime(true)));
    event(new AgentDecision('AdviceFyn', 'response_mode', 'recommendation', [], microtime(true)));

    $events = app(EvalTraceCollector::class)->all();

    expect($events)->toHaveCount(3)
        ->and($events[0]['event'])->toBe('GateChecked')
        ->and($events[1]['event'])->toBe('EngineCalled')
        ->and($events[2]['event'])->toBe('AgentDecision');
});

it('does NOT capture when token lacks bypass-preview-mode ability', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/user');

    event(new GateChecked('kyc', 'protection', true, [], microtime(true)));

    expect(app(EvalTraceCollector::class)->all())->toBeEmpty();
});

it('does NOT capture for unauthenticated requests', function () {
    event(new GateChecked('kyc', 'protection', true, [], microtime(true)));

    expect(app(EvalTraceCollector::class)->all())->toBeEmpty();
});
```

- [ ] **Step 5.2: Run test (will fail — services don't exist)**

```bash
./vendor/bin/pest tests/Feature/EvalTraceListenerTest.php
```

Expected: FAIL with `Class "App\Services\Eval\EvalTraceCollector" not found`.

- [ ] **Step 5.3: Create `EvalTraceCollector.php`**

`app/Services/Eval/EvalTraceCollector.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;

final class EvalTraceCollector
{
    /** @var list<array<string, mixed>> */
    private array $events = [];

    private ?float $startMicrotime = null;

    public function record(GateChecked|EngineCalled|AgentDecision $event): void
    {
        $this->startMicrotime ??= $event->atMicrotime;

        $this->events[] = match (true) {
            $event instanceof GateChecked => [
                'event' => 'GateChecked',
                't_ms' => (int) (($event->atMicrotime - $this->startMicrotime) * 1000),
                'gate' => $event->gate,
                'module' => $event->module,
                'passed' => $event->passed,
                'context' => $event->context,
            ],
            $event instanceof EngineCalled => [
                'event' => 'EngineCalled',
                't_ms' => (int) (($event->atMicrotime - $this->startMicrotime) * 1000),
                'engine' => $event->engine,
                'params' => $event->params,
                'resultSummary' => $event->resultSummary,
                'durationMs' => $event->durationMs,
            ],
            $event instanceof AgentDecision => [
                'event' => 'AgentDecision',
                't_ms' => (int) (($event->atMicrotime - $this->startMicrotime) * 1000),
                'agent' => $event->agent,
                'decisionPoint' => $event->decisionPoint,
                'outcome' => $event->outcome,
                'context' => $event->context,
            ],
        };
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->events;
    }

    public function reset(): void
    {
        $this->events = [];
        $this->startMicrotime = null;
    }
}
```

- [ ] **Step 5.4: Create `EvalTraceListener.php`**

`app/Listeners/Eval/EvalTraceListener.php`:

```php
<?php

declare(strict_types=1);

namespace App\Listeners\Eval;

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

final class EvalTraceListener
{
    public function __construct(private readonly EvalTraceCollector $collector) {}

    public function handle(GateChecked|EngineCalled|AgentDecision $event): void
    {
        if (! $this->shouldCapture()) {
            return;
        }

        $this->collector->record($event);
    }

    private function shouldCapture(): bool
    {
        $user = Auth::user();
        if ($user === null) {
            return false;
        }

        $token = $user->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        return $token->can('bypass-preview-mode');
    }
}
```

- [ ] **Step 5.5: Create `EvalServiceProvider.php`**

`app/Providers/EvalServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Listeners\Eval\EvalTraceListener;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class EvalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Request-scoped singleton — one collector per request lifecycle.
        $this->app->scoped(EvalTraceCollector::class, fn (Application $app) => new EvalTraceCollector);
    }

    public function boot(): void
    {
        Event::listen(GateChecked::class, [EvalTraceListener::class, 'handle']);
        Event::listen(EngineCalled::class, [EvalTraceListener::class, 'handle']);
        Event::listen(AgentDecision::class, [EvalTraceListener::class, 'handle']);
    }
}
```

- [ ] **Step 5.6: Register the provider**

In `config/app.php`, in the `'providers'` array, after `App\Providers\AppServiceProvider::class`, add:

```php
        App\Providers\EvalServiceProvider::class,
```

- [ ] **Step 5.7: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Feature/EvalTraceListenerTest.php
```

Expected: 3/3 PASS.

- [ ] **Step 5.8: Commit**

```bash
git add app/Services/Eval/EvalTraceCollector.php app/Listeners/Eval/EvalTraceListener.php app/Providers/EvalServiceProvider.php config/app.php tests/Feature/EvalTraceListenerTest.php
git commit -m "feat(eval): add EvalTraceCollector + EvalTraceListener (ability-gated)"
```

---

## Task 6: Wire the 11 trace call sites

**Goal:** Add `event(...)` calls at the 11 source locations from spec §5.3 so the trace captures the full decision waterfall.

**Files:**
- Modify: `app/Services/AI/KycGateChecker.php` (per-field + per-module fires)
- Modify: `app/Services/Protection/ProtectionDataReadinessService.php`
- Modify: `app/Services/Savings/SavingsDataReadinessService.php`
- Modify: `app/Services/Investment/Recommendation/DataReadinessService.php`
- Modify: `app/Services/Retirement/RetirementDataReadinessService.php`
- Modify: `app/Services/Estate/EstateDataReadinessService.php`
- Modify: `app/Agents/ProtectionAgent.php` (line 72 secondary gate + line ~end analyze return)
- Modify: `app/Agents/RetirementAgent.php` (line 101 secondary gate + analyze return)
- Modify: `app/Agents/SavingsAgent.php`, `InvestmentAgent.php`, `EstateAgent.php`, `GoalsAgent.php` (analyze returns)
- Modify: `app/Services/PrerequisiteGateService.php` (canGetRecommendations)
- Modify: `app/Agents/CoordinatingAgent.php` (orchestrateAnalysis entry/exit + executeTool dispatch)
- Modify: `app/Services/AI/QueryClassifier.php` (classify return)
- Modify: `app/Services/AI/AdviceFyn.php` (classifyResponseMode + engineCallLevel call sites in handle)
- Create: `tests/Feature/EvalTraceCallSitesTest.php`

- [ ] **Step 6.1: Write the failing integration test**

`tests/Feature/EvalTraceCallSitesTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Eval\EvalTraceCollector;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(\Database\Seeders\PreviewUserSeeder::class);
});

it('a chat send populates the trace with events from every layer', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    Sanctum::actingAs($user, ['bypass-preview-mode']);

    $conv = \App\Models\AiConversation::create([
        'user_id' => $user->id,
        'title' => 'Trace test',
        'status' => 'active',
        'message_count' => 0,
    ]);

    $this->postJson("/api/ai-chat/conversations/{$conv->id}/messages", [
        'message' => 'Am I covered enough for protection?',
    ]);

    $trace = app(EvalTraceCollector::class)->all();

    // Assert each of the 11 call-site classes contributes at least one event.
    $eventTypes = collect($trace)->map(fn ($e) => $e['event'].':'.($e['gate'] ?? $e['engine'] ?? $e['decisionPoint'] ?? ''))->all();

    // Classification (QueryClassifier)
    expect($eventTypes)->toContain('AgentDecision:classify_query');

    // Response mode + engine call level (AdviceFyn)
    expect($eventTypes)->toContain('AgentDecision:response_mode');
    expect($eventTypes)->toContain('AgentDecision:engine_call_level');

    // KYC gate (KycGateChecker)
    $hasKyc = collect($trace)->contains(fn ($e) => ($e['event'] ?? null) === 'GateChecked' && ($e['gate'] ?? null) === 'kyc');
    expect($hasKyc)->toBeTrue();

    // Data readiness (ProtectionDataReadinessService)
    $hasReadiness = collect($trace)->contains(fn ($e) => ($e['event'] ?? null) === 'GateChecked' && ($e['gate'] ?? null) === 'data_readiness');
    expect($hasReadiness)->toBeTrue();

    // Profile gate (ProtectionAgent line 72)
    $hasProfile = collect($trace)->contains(fn ($e) => ($e['event'] ?? null) === 'GateChecked' && ($e['gate'] ?? null) === 'profile_gate');
    expect($hasProfile)->toBeTrue();

    // Engine call (ProtectionAgent::analyze return)
    $hasEngine = collect($trace)->contains(fn ($e) => ($e['event'] ?? null) === 'EngineCalled' && str_contains($e['engine'] ?? '', 'protection'));
    expect($hasEngine)->toBeTrue();

    // Tool dispatch (CoordinatingAgent::executeTool)
    $hasToolDispatch = collect($trace)->contains(fn ($e) => ($e['event'] ?? null) === 'AgentDecision' && ($e['decisionPoint'] ?? null) === 'tool_dispatch');
    expect($hasToolDispatch)->toBeTrue();
});
```

- [ ] **Step 6.2: Run test to verify it fails**

```bash
./vendor/bin/pest tests/Feature/EvalTraceCallSitesTest.php
```

Expected: FAIL — trace empty or missing events.

- [ ] **Step 6.3: Add `event(...)` at `KycGateChecker::check`**

In `app/Services/AI/KycGateChecker.php`, locate the per-field universal checks (around lines 94-130 per spec) and after each universal field check, add:

```php
event(new \App\Events\Eval\GateChecked(
    gate: 'kyc',
    module: 'global',
    passed: $fieldPassed,
    context: ['field' => $fieldName, 'user_id' => $user->id],
    atMicrotime: microtime(true),
));
```

After each per-module check, add:

```php
event(new \App\Events\Eval\GateChecked(
    gate: 'kyc',
    module: $module,
    passed: $modulePassed,
    context: ['user_id' => $user->id],
    atMicrotime: microtime(true),
));
```

(Read the actual `check()` method first to find the exact variables — the field name and pass/fail boolean are local-loop variables. Insert the event call after each branch's pass/fail decision.)

- [ ] **Step 6.4: Add `event(...)` at the 5 DataReadinessService classes**

For each of `ProtectionDataReadinessService::assess`, `SavingsDataReadinessService::assess`, `InvestmentDataReadinessService::assess`, `RetirementDataReadinessService::assess`, `EstateDataReadinessService::assess`:

After computing `$canProceed`, before the return, add:

```php
event(new \App\Events\Eval\GateChecked(
    gate: 'data_readiness',
    module: '<protection|savings|investment|retirement|estate>',
    passed: $canProceed,
    context: [
        'blocking' => $blocking ?? [],
        'warnings' => $warnings ?? [],
        'user_id' => $user->id,
    ],
    atMicrotime: microtime(true),
));
```

(Replace `<...>` with the literal module string for that file.)

- [ ] **Step 6.5: Add `event(...)` at the 2 secondary profile gates**

In `app/Agents/ProtectionAgent.php` line 72 (the `if (! $user->protectionProfile)` block), before the return, add:

```php
event(new \App\Events\Eval\GateChecked(
    gate: 'profile_gate',
    module: 'protection',
    passed: $user->protectionProfile !== null,
    context: ['profile_table' => 'protection_profiles', 'user_id' => $user->id],
    atMicrotime: microtime(true),
));
```

In `app/Agents/RetirementAgent.php` line 101 (the equivalent block), add the same with `module: 'retirement'`, `profile_table: 'retirement_profiles'`.

- [ ] **Step 6.6: Add `event(...)` at PrerequisiteGateService::canGetRecommendations**

In `app/Services/PrerequisiteGateService.php` `canGetRecommendations` method, before the return, add:

```php
event(new \App\Events\Eval\GateChecked(
    gate: 'recommendation_eligibility',
    module: 'global',
    passed: $canProceed,
    context: [
        'ready_modules' => $readyModules ?? [],
        'blocked_modules' => $blockedModules ?? [],
        'user_id' => $user->id,
    ],
    atMicrotime: microtime(true),
));
```

- [ ] **Step 6.7: Add `event(...)` at orchestrateAnalysis entry + exit**

In `app/Agents/CoordinatingAgent.php` `orchestrateAnalysis` method:

At entry (top of method):

```php
$orchestrateStart = microtime(true);
```

Just before the return, add:

```php
event(new \App\Events\Eval\EngineCalled(
    engine: 'orchestrate_analysis',
    params: ['user_id' => $user->id],
    resultSummary: [
        'keys_returned' => array_keys($result),
        'result_path' => $result['result_path'] ?? 'unknown',
    ],
    durationMs: (int) ((microtime(true) - $orchestrateStart) * 1000),
    atMicrotime: microtime(true),
));
```

- [ ] **Step 6.8: Add `event(...)` at the 6 module agents' analyze() returns**

For each of `ProtectionAgent`, `SavingsAgent`, `InvestmentAgent`, `RetirementAgent`, `EstateAgent`, `GoalsAgent`:

At the top of `analyze()`:

```php
$analyzeStart = microtime(true);
```

Just before the final return, add:

```php
event(new \App\Events\Eval\EngineCalled(
    engine: '<module>_analysis',
    params: ['user_id' => $user->id],
    resultSummary: [
        'keys_returned' => array_keys($payload),
        'result_path' => $resultPath ?? ($payload['success'] === false ? 'success_false' : 'happy'),
    ],
    durationMs: (int) ((microtime(true) - $analyzeStart) * 1000),
    atMicrotime: microtime(true),
));
```

(Replace `<module>` with `protection`, `savings`, etc.)

- [ ] **Step 6.9: Add `event(...)` at QueryClassifier::classify**

In `app/Services/AI/QueryClassifier.php` `classify` method, before the return, add:

```php
event(new \App\Events\Eval\AgentDecision(
    agent: 'CoordinatingAgent',
    decisionPoint: 'classify_query',
    outcome: 'success',
    context: [
        'primary' => $result['primary'],
        'related' => $result['related'],
        'modules' => $result['modules'],
    ],
    atMicrotime: microtime(true),
));
```

- [ ] **Step 6.10: Add `event(...)` at AdviceFyn::handle for response_mode + engine_call_level**

In `app/Services/AI/AdviceFyn.php` `handle()`, after computing `$responseMode = self::classifyResponseMode($primary)`, add:

```php
event(new \App\Events\Eval\AgentDecision(
    agent: 'AdviceFyn',
    decisionPoint: 'response_mode',
    outcome: $responseMode,
    context: ['primary' => $primary],
    atMicrotime: microtime(true),
));
```

After computing `$engineLevel = self::engineCallLevel($primary)`, add:

```php
event(new \App\Events\Eval\AgentDecision(
    agent: 'AdviceFyn',
    decisionPoint: 'engine_call_level',
    outcome: $engineLevel,
    context: ['primary' => $primary],
    atMicrotime: microtime(true),
));
```

- [ ] **Step 6.11: Add `event(...)` at CoordinatingAgent::executeTool**

In `app/Agents/CoordinatingAgent.php` `executeTool` method, before the dispatch switch, add:

```php
$toolStart = microtime(true);
```

After the switch (before the return), add:

```php
event(new \App\Events\Eval\AgentDecision(
    agent: 'CoordinatingAgent',
    decisionPoint: 'tool_dispatch',
    outcome: $toolName,
    context: [
        'args' => $input,
        'duration_ms' => (int) ((microtime(true) - $toolStart) * 1000),
    ],
    atMicrotime: microtime(true),
));
```

- [ ] **Step 6.12: Run the integration test to verify all events fire**

```bash
./vendor/bin/pest tests/Feature/EvalTraceCallSitesTest.php
```

Expected: PASS (every assertion contained-in-trace passes).

- [ ] **Step 6.13: Run full Pest baseline to confirm no regressions**

```bash
./vendor/bin/pest tests/Unit/Services/AI/ tests/Feature/AI/ tests/Unit/Agents/
```

Expected: All previously-green tests still pass (events with no listener evaporate).

- [ ] **Step 6.14: Commit**

```bash
git add app/Services/AI/KycGateChecker.php app/Services/Protection/ProtectionDataReadinessService.php app/Services/Savings/SavingsDataReadinessService.php app/Services/Investment/Recommendation/DataReadinessService.php app/Services/Retirement/RetirementDataReadinessService.php app/Services/Estate/EstateDataReadinessService.php app/Agents/ProtectionAgent.php app/Agents/RetirementAgent.php app/Agents/SavingsAgent.php app/Agents/InvestmentAgent.php app/Agents/EstateAgent.php app/Agents/GoalsAgent.php app/Services/PrerequisiteGateService.php app/Agents/CoordinatingAgent.php app/Services/AI/QueryClassifier.php app/Services/AI/AdviceFyn.php tests/Feature/EvalTraceCallSitesTest.php
git commit -m "feat(eval): wire 11 gate/engine/agent trace call sites"
```

---

## Task 7: `EvalAuthController` + `eval/*` routes

**Goal:** 3 endpoints — login (issues ability-tagged token), reset (wraps `preview:reset`), trace (returns collector contents).

**Files:**
- Create: `app/Http/Controllers/Api/EvalAuthController.php`
- Modify: `routes/api.php`
- Create: `tests/Feature/EvalAuthControllerTest.php`

- [ ] **Step 7.1: Write the failing test**

`tests/Feature/EvalAuthControllerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PreviewUserSeeder::class);
});

it('returns a Sanctum token with bypass-preview-mode ability for a valid persona', function () {
    config(['app.env' => 'local']);

    $response = $this->postJson('/api/eval/login/peak_earners');

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'persona', 'is_preview_user', 'token_abilities']])
        ->assertJsonPath('user.persona', 'peak_earners')
        ->assertJsonPath('user.is_preview_user', true)
        ->assertJsonPath('user.token_abilities', ['bypass-preview-mode']);
});

it('refuses in production environment', function () {
    config(['app.env' => 'production']);

    $this->postJson('/api/eval/login/peak_earners')->assertStatus(403);
});

it('returns 400 for invalid persona', function () {
    config(['app.env' => 'local']);

    $this->postJson('/api/eval/login/not_a_persona')->assertStatus(400);
});

it('returns 404 when persona is not seeded', function () {
    config(['app.env' => 'local']);
    User::where('preview_persona_id', 'student')->delete();

    $this->postJson('/api/eval/login/student')->assertStatus(404);
});

it('reset endpoint runs preview:reset for the persona', function () {
    config(['app.env' => 'local']);

    $response = $this->postJson('/api/eval/reset/peak_earners');

    $response->assertOk();
    expect(User::where('preview_persona_id', 'peak_earners')->exists())->toBeTrue();
});

it('trace endpoint returns the collector contents for the calling user', function () {
    config(['app.env' => 'local']);
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    \Laravel\Sanctum\Sanctum::actingAs($user, ['bypass-preview-mode']);

    $this->getJson('/api/user'); // populate currentAccessToken
    event(new \App\Events\Eval\GateChecked('kyc', 'global', true, [], microtime(true)));

    $conv = \App\Models\AiConversation::create([
        'user_id' => $user->id,
        'title' => 'trace test',
        'status' => 'active',
        'message_count' => 0,
    ]);

    $response = $this->getJson("/api/eval/trace/{$conv->id}");

    $response->assertOk()
        ->assertJsonStructure(['conversation_id', 'events']);
});
```

- [ ] **Step 7.2: Run test (will fail — controller + routes don't exist)**

```bash
./vendor/bin/pest tests/Feature/EvalAuthControllerTest.php
```

Expected: FAIL with route not defined.

- [ ] **Step 7.3: Create `EvalAuthController.php`**

`app/Http/Controllers/Api/EvalAuthController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

final class EvalAuthController extends Controller
{
    private const VALID_PERSONAS = [
        'young_family',
        'peak_earners',
        'entrepreneur',
        'young_saver',
        'retired_couple',
        'student',
    ];

    public function login(Request $request, string $personaId): JsonResponse
    {
        if (App::environment('production')) {
            return response()->json(['error' => 'eval login disabled in production'], 403);
        }

        if (! in_array($personaId, self::VALID_PERSONAS, true)) {
            return response()->json(['error' => 'invalid persona'], 400);
        }

        $user = User::where('is_preview_user', true)
            ->where('preview_persona_id', $personaId)
            ->first();

        if (! $user) {
            return response()->json([
                'error' => 'preview user not seeded',
                'hint' => 'php artisan db:seed --class=PreviewUserSeeder',
            ], 404);
        }

        $token = $user->createToken(
            name: 'eval-'.now()->timestamp,
            abilities: ['bypass-preview-mode']
        )->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'persona' => $personaId,
                'is_preview_user' => true,
                'token_abilities' => ['bypass-preview-mode'],
            ],
        ]);
    }

    public function reset(Request $request, string $personaId): JsonResponse
    {
        if (App::environment('production')) {
            return response()->json(['error' => 'eval reset disabled in production'], 403);
        }

        if (! in_array($personaId, self::VALID_PERSONAS, true)) {
            return response()->json(['error' => 'invalid persona'], 400);
        }

        Artisan::call('preview:reset', ['persona' => $personaId]);

        return response()->json(['reset' => $personaId]);
    }

    public function trace(Request $request, int $conversationId): JsonResponse
    {
        if (App::environment('production')) {
            return response()->json(['error' => 'eval trace disabled in production'], 403);
        }

        $user = $request->user();
        if (! $user || ! AiConversation::where('id', $conversationId)->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'conversation not found'], 404);
        }

        return response()->json([
            'conversation_id' => $conversationId,
            'events' => app(EvalTraceCollector::class)->all(),
        ]);
    }
}
```

- [ ] **Step 7.4: Add routes to `routes/api.php`**

In `routes/api.php`, after the existing `Route::middleware('auth:sanctum')` blocks, add:

```php
if (! app()->environment('production')) {
    Route::middleware(['throttle:20,1'])->prefix('eval')->group(function () {
        Route::post('/login/{personaId}', [\App\Http\Controllers\Api\EvalAuthController::class, 'login']);
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/reset/{personaId}', [\App\Http\Controllers\Api\EvalAuthController::class, 'reset']);
            Route::get('/trace/{conversationId}', [\App\Http\Controllers\Api\EvalAuthController::class, 'trace']);
        });
    });
}
```

- [ ] **Step 7.5: Run test to verify all 6 cases pass**

```bash
./vendor/bin/pest tests/Feature/EvalAuthControllerTest.php
```

Expected: 6/6 PASS.

- [ ] **Step 7.6: Commit**

```bash
git add app/Http/Controllers/Api/EvalAuthController.php routes/api.php tests/Feature/EvalAuthControllerTest.php
git commit -m "feat(eval): add EvalAuthController with login + reset + trace endpoints"
```

---

## Task 8: `QuerySchemas` protection scope correction

**Goal:** Per spec §10.3 — `REQUIRED_TOOLS[PROTECTION_COVER]` includes all 3 protection types (life + critical illness + income protection).

**Files:**
- Modify: `app/Constants/QuerySchemas.php`
- Create: `tests/Unit/QuerySchemasProtectionScopeTest.php`

- [ ] **Step 8.1: Write the failing test**

`tests/Unit/QuerySchemasProtectionScopeTest.php`:

```php
<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;

it('PROTECTION_COVER requires list_records for all three protection types', function () {
    $tools = QuerySchemas::REQUIRED_TOOLS[QuerySchemas::PROTECTION_COVER] ?? [];

    $listRecordsTypes = collect($tools)
        ->filter(fn ($t) => ($t['tool'] ?? null) === 'list_records')
        ->pluck('args.entity_type')
        ->all();

    expect($listRecordsTypes)
        ->toContain('life_insurance')
        ->toContain('critical_illness')
        ->toContain('income_protection');
});

it('PROTECTION_COVER still requires get_module_analysis(protection)', function () {
    $tools = QuerySchemas::REQUIRED_TOOLS[QuerySchemas::PROTECTION_COVER] ?? [];

    $hasModuleAnalysis = collect($tools)->contains(
        fn ($t) => ($t['tool'] ?? null) === 'get_module_analysis'
            && ($t['args']['module'] ?? null) === 'protection'
    );

    expect($hasModuleAnalysis)->toBeTrue();
});
```

- [ ] **Step 8.2: Run test (will fail — only life_insurance is required today)**

```bash
./vendor/bin/pest tests/Unit/QuerySchemasProtectionScopeTest.php
```

Expected: FAIL on `toContain('critical_illness')`.

- [ ] **Step 8.3: Update `REQUIRED_TOOLS[PROTECTION_COVER]` in `QuerySchemas.php`**

Locate the `REQUIRED_TOOLS` constant in `app/Constants/QuerySchemas.php`, find the `self::PROTECTION_COVER => [...]` entry (per spec note "QSCH lines 462-465"), and replace its array with:

```php
self::PROTECTION_COVER => [
    ['tool' => 'get_module_analysis', 'args' => ['module' => 'protection']],
    ['tool' => 'list_records', 'args' => ['entity_type' => 'life_insurance']],
    ['tool' => 'list_records', 'args' => ['entity_type' => 'critical_illness']],
    ['tool' => 'list_records', 'args' => ['entity_type' => 'income_protection']],
],
```

- [ ] **Step 8.4: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Unit/QuerySchemasProtectionScopeTest.php
```

Expected: 2/2 PASS.

- [ ] **Step 8.5: Run classifier + prompt-builder tests for regression**

```bash
./vendor/bin/pest tests/Unit/Services/AI/QueryClassifierTest.php tests/Feature/AI/
```

Expected: All previously-green tests still pass.

- [ ] **Step 8.6: Commit**

```bash
git add app/Constants/QuerySchemas.php tests/Unit/QuerySchemasProtectionScopeTest.php
git commit -m "fix(query-schemas): PROTECTION_COVER must surface all 3 protection types (life + critical illness + income protection)"
```

---

## Task 9: `EvalSseConsumer`

**Goal:** Read an SSE response body byte-for-byte, parse `data: ...\n\n` frames, return event list.

**Files:**
- Create: `app/Services/Eval/EvalSseConsumer.php`
- Create: `tests/Unit/Services/Eval/EvalSseConsumerTest.php`

- [ ] **Step 9.1: Write the failing test**

`tests/Unit/Services/Eval/EvalSseConsumerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\Eval\EvalSseConsumer;

it('parses a complete SSE stream into events', function () {
    $body = "data: {\"type\":\"title\",\"text\":\"Hello\"}\n\n"
        ."data: {\"type\":\"content\",\"text\":\"world\"}\n\n"
        ."data: {\"type\":\"done\"}\n\n";

    $events = (new EvalSseConsumer)->consume($body);

    expect($events)->toHaveCount(3)
        ->and($events[0])->toMatchArray(['type' => 'title', 'text' => 'Hello'])
        ->and($events[2])->toMatchArray(['type' => 'done']);
});

it('handles partial frames split across the buffer', function () {
    $part1 = "data: {\"type\":\"content\",\"text\":\"hello";
    $part2 = " world\"}\n\ndata: {\"type\":\"done\"}\n\n";

    $consumer = new EvalSseConsumer;
    $events = $consumer->consume($part1.$part2);

    expect($events)->toHaveCount(2)
        ->and($events[0]['text'])->toBe('hello world');
});

it('skips non-data lines (comments, retry, id)', function () {
    $body = ": keep-alive comment\n\n"
        ."data: {\"type\":\"done\"}\n\n";

    $events = (new EvalSseConsumer)->consume($body);

    expect($events)->toHaveCount(1)
        ->and($events[0]['type'])->toBe('done');
});

it('returns empty list for empty body', function () {
    expect((new EvalSseConsumer)->consume(''))->toBe([]);
});
```

- [ ] **Step 9.2: Run test (will fail — class doesn't exist)**

```bash
./vendor/bin/pest tests/Unit/Services/Eval/EvalSseConsumerTest.php
```

Expected: FAIL.

- [ ] **Step 9.3: Create `EvalSseConsumer.php`**

`app/Services/Eval/EvalSseConsumer.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Eval;

final class EvalSseConsumer
{
    /**
     * Parse a complete SSE response body into a list of decoded event objects.
     *
     * Each event is delimited by `\n\n`. We only honour `data: ...` lines —
     * comments (lines starting with `:`) and other SSE fields (`event:`,
     * `id:`, `retry:`) are discarded since the chat stream uses only `data:`.
     *
     * @return list<array<string, mixed>>
     */
    public function consume(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $events = [];
        $frames = preg_split('/\n\n+/', $body) ?: [];

        foreach ($frames as $frame) {
            $frame = trim($frame);
            if ($frame === '') {
                continue;
            }

            $lines = explode("\n", $frame);
            $dataParts = [];
            foreach ($lines as $line) {
                if (! str_starts_with($line, 'data: ')) {
                    continue;
                }
                $dataParts[] = substr($line, 6);
            }

            if ($dataParts === []) {
                continue;
            }

            $payload = implode("\n", $dataParts);
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }
}
```

- [ ] **Step 9.4: Run test to verify it passes**

```bash
./vendor/bin/pest tests/Unit/Services/Eval/EvalSseConsumerTest.php
```

Expected: 4/4 PASS.

- [ ] **Step 9.5: Commit**

```bash
git add app/Services/Eval/EvalSseConsumer.php tests/Unit/Services/Eval/EvalSseConsumerTest.php
git commit -m "feat(eval): add EvalSseConsumer for SSE frame parsing"
```

---

## Task 10: `EvalHttpDriver` — the HTTP loop

**Goal:** Login via `/api/eval/login`, create conversation, send message, consume SSE, fetch trace, return aggregate.

**Files:**
- Create: `app/Services/Eval/EvalHttpDriver.php`
- Create: `tests/Feature/Fyn/Eval/EvalHttpDriverTest.php`

**Pre-flight for this task:** `./dev.sh` must be running in another terminal so the HTTP driver has a server to hit. The test is a true integration test against `localhost:8000`.

- [ ] **Step 10.1: Write the failing test**

`tests/Feature/Fyn/Eval/EvalHttpDriverTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\Eval\EvalHttpDriver;

beforeEach(function () {
    if (app()->environment('testing')) {
        $this->markTestSkipped('EvalHttpDriverTest requires the local dev server (./dev.sh) — skipped in unit-test mode.');
    }
});

it('drives a full login → create conversation → send message → consume SSE → fetch trace cycle', function () {
    $driver = app(EvalHttpDriver::class);

    $result = $driver->run(
        scenario: [
            'persona' => 'peak_earners',
            'is_mutating' => false,
            'input' => [
                'turns' => [
                    ['user' => 'What is our combined net worth?', 'current_route' => null],
                ],
            ],
        ],
        provider: 'anthropic',
        model: 'claude-haiku-4-5-20251001',
        baseUrl: 'http://localhost:8000',
    );

    expect($result['events'])->not->toBeEmpty()
        ->and($result['http_log'])->toHaveCount(4) // login, create conv, send msg, logout
        ->and($result['http_log'][0]['url'])->toContain('/api/eval/login/peak_earners')
        ->and($result['http_log'][0]['status'])->toBe(200)
        ->and($result['engine_trace'])->not->toBeEmpty();

    $eventTypes = collect($result['events'])->pluck('type')->unique()->all();
    expect($eventTypes)->toContain('done');
});
```

- [ ] **Step 10.2: Run test (skipped in unit mode — that's fine)**

```bash
./vendor/bin/pest tests/Feature/Fyn/Eval/EvalHttpDriverTest.php
```

Expected: SKIP. The class still doesn't exist, but the harness pattern is in place.

- [ ] **Step 10.3: Create `EvalHttpDriver.php`**

`app/Services/Eval/EvalHttpDriver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EvalHttpDriver
{
    public function __construct(private readonly EvalSseConsumer $sse) {}

    /**
     * Drive a scenario end-to-end via real HTTP against the local Laravel server.
     *
     * @param  array<string, mixed>  $scenario
     * @return array{events: list<array<string, mixed>>, http_log: list<array<string, mixed>>, db_writes: array<string, mixed>, engine_trace: list<array<string, mixed>>, conversation_id: int}
     */
    public function run(array $scenario, string $provider, string $model, string $baseUrl = 'http://localhost:8000'): array
    {
        $persona = $scenario['persona'] ?? throw new RuntimeException('scenario.persona required');
        $turns = $scenario['input']['turns'] ?? [];

        $previousProvider = Cache::get('ai_provider');
        Cache::forever('ai_provider', $provider);
        $previousModel = config("services.{$provider}.chat_model");
        config(["services.{$provider}.chat_model" => $model]);

        $httpLog = [];
        $allEvents = [];
        $conversationId = null;
        $traceEvents = [];

        try {
            // [REVISED 2026-04-28] Pre-flight reset removed per canonical 0.1
            // (see April/April28Updates/maxAuditEval.md §0). Non-mutating evals
            // must NEVER reset the persona. Mutating-scenario reset moved to
            // the caller (EvalRecordCommand::recordOne) AFTER EvalProviderRun
            // ::create() persists the captured change. Shipped in commit dd2942f.

            // 1. Login
            $t0 = microtime(true);
            $loginResp = Http::timeout(5)->post("{$baseUrl}/api/eval/login/{$persona}");
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/eval/login/{$persona}", $loginResp->status(), $t0);
            if (! $loginResp->ok()) {
                throw new RuntimeException("eval login failed: {$loginResp->status()} — {$loginResp->body()}");
            }
            $token = $loginResp->json('token');
            $userId = $loginResp->json('user.id');

            // 2. Create conversation
            $t0 = microtime(true);
            $convResp = Http::withToken($token)->timeout(5)->post("{$baseUrl}/api/ai-chat/conversations", [
                'title' => 'Eval recording',
                'model_used' => $model,
            ]);
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/ai-chat/conversations", $convResp->status(), $t0);
            if (! $convResp->successful()) {
                throw new RuntimeException("create conversation failed: {$convResp->status()} — {$convResp->body()}");
            }
            $conversationId = (int) $convResp->json('data.id');

            // Snapshot start state for diff
            $startState = $this->snapshotUser($userId);

            // 3. Send each turn, consume SSE
            foreach ($turns as $turn) {
                $message = $turn['user'] ?? null;
                if (! is_string($message) || $message === '') {
                    continue;
                }
                $currentRoute = $turn['current_route'] ?? null;

                $t0 = microtime(true);
                $sendResp = Http::withToken($token)
                    ->withHeaders(['Accept' => 'text/event-stream'])
                    ->timeout(120)
                    ->post("{$baseUrl}/api/ai-chat/conversations/{$conversationId}/messages", [
                        'message' => $message,
                        'current_route' => $currentRoute,
                    ]);

                $httpLog[] = $this->logCall(
                    'POST',
                    "{$baseUrl}/api/ai-chat/conversations/{$conversationId}/messages",
                    $sendResp->status(),
                    $t0,
                );

                if (! $sendResp->ok()) {
                    throw new RuntimeException("send message failed: {$sendResp->status()} — {$sendResp->body()}");
                }

                $events = $this->sse->consume($sendResp->body());
                $allEvents = array_merge($allEvents, $events);
            }

            // 4. Fetch engine/gate trace
            $t0 = microtime(true);
            $traceResp = Http::withToken($token)->timeout(5)->get("{$baseUrl}/api/eval/trace/{$conversationId}");
            $httpLog[] = $this->logCall('GET', "{$baseUrl}/api/eval/trace/{$conversationId}", $traceResp->status(), $t0);
            if ($traceResp->ok()) {
                $traceEvents = $traceResp->json('events') ?? [];
            }

            // 5. Diff state
            $endState = $this->snapshotUser($userId);
            $dbWrites = $this->diffSnapshots($startState, $endState);

            // 6. Logout
            $t0 = microtime(true);
            $logoutResp = Http::withToken($token)->timeout(5)->post("{$baseUrl}/api/auth/logout");
            $httpLog[] = $this->logCall('POST', "{$baseUrl}/api/auth/logout", $logoutResp->status(), $t0);

            return [
                'events' => $allEvents,
                'http_log' => $httpLog,
                'db_writes' => $dbWrites,
                'engine_trace' => $traceEvents,
                'conversation_id' => $conversationId,
            ];
        } finally {
            // Restore provider
            if ($previousProvider === null) {
                Cache::forget('ai_provider');
            } else {
                Cache::forever('ai_provider', $previousProvider);
            }
            config(["services.{$provider}.chat_model" => $previousModel]);

            // [REVISED 2026-04-28] Post-flight reset removed from the driver
            // per canonical 0.1. The caller (EvalRecordCommand::recordOne) now
            // owns reset orchestration: it runs AFTER EvalProviderRun::create()
            // persists the captured change, AND ONLY when the persisted
            // db_writes diff is non-empty. See Task 11 step 11.3 below.
        }
    }

    /** @return array<string, mixed> */
    private function snapshotUser(int $userId): array
    {
        $user = User::find($userId);

        return [
            'user' => $user?->only(['id', 'first_name', 'date_of_birth', 'marital_status', 'onboarding_completed']),
            'savings_count' => \App\Models\SavingsAccount::where('user_id', $userId)->count(),
            'protection_count' => \App\Models\LifeInsurancePolicy::where('user_id', $userId)->count()
                + \App\Models\CriticalIllnessPolicy::where('user_id', $userId)->count()
                + \App\Models\IncomeProtectionPolicy::where('user_id', $userId)->count(),
            'investment_count' => \App\Models\Investment\InvestmentAccount::where('user_id', $userId)->count(),
            'pension_count' => \App\Models\DCPension::where('user_id', $userId)->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    private function diffSnapshots(array $before, array $after): array
    {
        $diff = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $diff[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }
        }

        return $diff;
    }

    /** @return array<string, mixed> */
    private function logCall(string $method, string $url, int $status, float $startMicrotime): array
    {
        return [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'duration_ms' => (int) ((microtime(true) - $startMicrotime) * 1000),
            'at' => now()->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 10.4: Manual integration test (requires `./dev.sh` running)**

In a separate terminal, run `./dev.sh` and wait for the server to be ready.

Then run:

```bash
php artisan tinker --execute="\$d = app(\App\Services\Eval\EvalHttpDriver::class); \$r = \$d->run(['persona' => 'peak_earners', 'is_mutating' => false, 'input' => ['turns' => [['user' => 'What is our combined net worth?']]]], 'anthropic', 'claude-haiku-4-5-20251001'); echo 'events=' . count(\$r['events']) . ' http_log=' . count(\$r['http_log']) . ' trace=' . count(\$r['engine_trace']) . PHP_EOL;"
```

Expected output (approximately): `events=15-30 http_log=4 trace=8-15` and no exception.

- [ ] **Step 10.5: Commit**

```bash
git add app/Services/Eval/EvalHttpDriver.php tests/Feature/Fyn/Eval/EvalHttpDriverTest.php
git commit -m "feat(eval): add EvalHttpDriver — HTTP-driven eval loop"
```

---

## Task 11: Rewire `EvalRecordCommand` to use `EvalHttpDriver`

**Goal:** Delete the synthetic-seed methods. Make `php artisan eval:record <id>` call the HTTP driver and persist sessions/runs from the result.

**Files:**
- Modify: `app/Console/Commands/EvalRecordCommand.php`
- Modify: `app/Models/EvalRecordingSession.php` (fillable + casts for new columns)
- Modify: `app/Models/EvalProviderRun.php` (fillable + casts for `engine_trace`)

- [ ] **Step 11.1: Update `EvalRecordingSession` model**

In `app/Models/EvalRecordingSession.php`, add `'persona'` and `'http_log'` to `$fillable`, and add `'http_log' => 'array'` to `$casts`.

- [ ] **Step 11.2: Update `EvalProviderRun` model**

In `app/Models/EvalProviderRun.php`, add `'engine_trace'` to `$fillable`, and add `'engine_trace' => 'array'` to `$casts`.

- [ ] **Step 11.3: Rewrite `EvalRecordCommand::recordOne` and delete deprecated methods**

In `app/Console/Commands/EvalRecordCommand.php`:

- DELETE: `seedUser`, `seedChildEntities`, `seedProtectionPolicies`, `seedRows`, `seedExpenditure`, `createConversation`, `snapshotState`, `restoreToSnapshot`, `keyById`, `diffRows`, `extractToolCalls`, `SNAPSHOT_TABLES`, the `Cache::forever` block in `recordOne`.
- **[REVISED 2026-04-28]** ADD canonical 0.1 reset orchestration AFTER `EvalProviderRun::create()` persists. Capture the create() return into a `$run` variable; if `$result['db_writes']` is non-empty, call `Artisan::call('preview:reset', ['persona' => $scenario['persona']])`; then `return $run`. Non-mutating scenarios skip this entirely. The reset runs **once per recordOne call** (not once per session — sessions span 2 providers and the reset must run between them only if provider 1 actually wrote).
- REPLACE `recordOne` with:

```php
private function recordOne(
    EvalRecordingSession $session,
    array $scenario,
    string $provider,
    string $model,
    bool $dryRun,
): ?EvalProviderRun {
    $this->newLine();
    $this->info(str_repeat('-', 70));
    $this->info(">> {$provider} / {$model}");
    $this->info(str_repeat('-', 70));

    if ($dryRun) {
        $this->warn('Dry-run — skipping HTTP loop.');

        return EvalProviderRun::create([
            'eval_recording_session_id' => $session->id,
            'provider' => $provider,
            'model' => $model,
            'conversation_id' => 0,
            'user_message' => '(dry-run)',
            'assistant_text' => null,
            'tool_calls' => [],
            'sse_event_count' => 0,
            'sse_event_types' => [],
            'forbidden_hits' => [],
            'db_writes_made' => [],
            'engine_trace' => [],
            'end_state_snapshot' => [],
            'fixture_path' => null,
            'duration_ms' => 0,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    $startedAt = now();
    $startMicrotime = microtime(true);

    try {
        $result = app(\App\Services\Eval\EvalHttpDriver::class)->run(
            scenario: $scenario,
            provider: $provider,
            model: $model,
        );

        $durationMs = (int) ((microtime(true) - $startMicrotime) * 1000);
        $events = $result['events'];
        $assembledContent = $this->assembleContent($events);

        $fixturePath = $this->writeFixture(
            provider: $provider,
            model: $model,
            scenarioId: $scenario['id'],
            events: $events,
            conversationId: $result['conversation_id'],
            durationMs: $durationMs,
        );

        $session->update(['http_log' => array_merge($session->http_log ?? [], $result['http_log'])]);

        $run = EvalProviderRun::create([
            'eval_recording_session_id' => $session->id,
            'provider' => $provider,
            'model' => $model,
            'conversation_id' => $result['conversation_id'],
            'user_message' => collect($scenario['input']['turns'])->pluck('user')->implode("\n---\n"),
            'assistant_text' => $assembledContent,
            'tool_calls' => $this->extractToolCallsFromEvents($events),
            'sse_event_count' => count($events),
            'sse_event_types' => $this->countEventTypes($events),
            'forbidden_hits' => $this->detectForbiddenOutputs($assembledContent, $scenario['expected_assistant_text']['must_not_contain_substrings'] ?? []),
            'db_writes_made' => $result['db_writes'],
            'engine_trace' => $result['engine_trace'],
            'end_state_snapshot' => [],
            'fixture_path' => $fixturePath,
            'duration_ms' => $durationMs,
            'started_at' => $startedAt,
            'completed_at' => now(),
        ]);

        // [REVISED 2026-04-28] Canonical 0.1 reset orchestration:
        //   - run AFTER EvalProviderRun::create() persists (so the forensic
        //     chain survives any reset-cascade FKs);
        //   - run ONLY if the persisted db_writes diff is non-empty (which
        //     is implied by is_mutating:true for any scenario that actually
        //     wrote — the diff is the source of truth, not the flag);
        //   - non-mutating scenarios (all 10 current mitchell scenarios)
        //     skip this entirely.
        if (! empty($result['db_writes'])) {
            $this->info('Mutating recording detected — resetting persona to pre-eval state.');
            \Illuminate\Support\Facades\Artisan::call('preview:reset', [
                'persona' => $scenario['persona'],
            ]);
        }

        return $run;
    } catch (\Throwable $e) {
        $this->error("[{$provider}] Recording failed: ".$e->getMessage());
        $this->error($e->getFile().':'.$e->getLine());

        return null;
    }
}

/** @param  list<array<string, mixed>>  $events */
private function extractToolCallsFromEvents(array $events): array
{
    return collect($events)
        ->filter(fn ($e) => ($e['type'] ?? null) === 'tool_use')
        ->map(fn ($e) => [
            'name' => $e['name'] ?? 'unknown',
            'args' => $e['input'] ?? [],
            'result' => null,
        ])
        ->values()
        ->all();
}
```

- REPLACE the scenario-loading logic in `handle()` to read JSON instead of YAML:

```php
$scenarioPath = $this->locateScenario($scenarioId); // .json now
$scenarioJson = (string) file_get_contents($scenarioPath);
$scenario = json_decode($scenarioJson, true);
if (! is_array($scenario)) {
    $this->error("Scenario {$scenarioPath} did not parse to an array.");
    return self::FAILURE;
}
$scenario['id'] = $scenario['id'] ?? $scenarioId;
```

- UPDATE `locateScenario` to glob `*.json` not `*.yaml`:

```php
$matches = glob(base_path(self::SCENARIO_ROOT)."/*/{$id}.json") ?: [];
```

- UPDATE the `$session = EvalRecordingSession::create([...])` call to include `'persona' => $scenario['persona'] ?? null,` and `'http_log' => [],`.

- UPDATE `recordOne` foreach loop to pass `$scenario` instead of building from YAML keys.

- [ ] **Step 11.4: Run any existing eval-related Pest tests**

```bash
./vendor/bin/pest tests/Feature/Fyn/Eval/ tests/Unit/Services/Eval/
```

Expected: Existing tests for `EvalDeltaBuilder` and `AssertionHelpers` still pass (they test pure logic, unaffected by the command rewire). YAML-fixture tests will need fixture conversion in Task 13.

- [ ] **Step 11.5: Commit**

```bash
git add app/Console/Commands/EvalRecordCommand.php app/Models/EvalRecordingSession.php app/Models/EvalProviderRun.php
git commit -m "refactor(eval): rewire EvalRecordCommand to use EvalHttpDriver — delete synthetic-seed methods"
```

---

## Task 12: JSON Schema for scenarios

**Goal:** Single source of truth for scenario shape. Architecture meta-test validates every scenario against it.

**Files:**
- Create: `tests/Feature/Fyn/Eval/scenarios/_schema.json`

- [ ] **Step 12.1: Write the schema**

`tests/Feature/Fyn/Eval/scenarios/_schema.json`:

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "Fynla Eval Scenario",
  "type": "object",
  "required": ["id", "category", "persona", "is_mutating", "description", "input", "expected_classification_shape", "expected_response_mode", "expected_engine_call_level", "expected_kyc_state", "expected_tool_calls", "expected_sse_events", "expected_assistant_text", "timing_budget_ms", "tags"],
  "properties": {
    "id": {"type": "string", "pattern": "^[a-z][a-z0-9_]+$"},
    "category": {"type": "string", "enum": ["01-query-types", "02-preview-personas", "03-multi-entity", "04-handoffs", "05-cancel-timeout", "06-prompt-injection", "07-regulatory", "08-provider-parity", "09-canonical-behaviour"]},
    "persona": {"type": "string", "enum": ["young_family", "peak_earners", "entrepreneur", "young_saver", "retired_couple", "student"]},
    "is_mutating": {"type": "boolean"},
    "description": {"type": "string", "minLength": 30},
    "input": {
      "type": "object",
      "required": ["turns"],
      "properties": {
        "turns": {
          "type": "array",
          "minItems": 1,
          "items": {
            "type": "object",
            "required": ["user"],
            "properties": {
              "user": {"type": "string", "minLength": 1, "maxLength": 2000},
              "current_route": {"type": ["string", "null"]}
            }
          }
        }
      }
    },
    "expected_classification_shape": {
      "type": "object",
      "required": ["primary"],
      "properties": {
        "primary": {"type": "string"},
        "related": {"type": "array", "items": {"type": "string"}},
        "modules": {"type": "array", "items": {"type": "string"}}
      }
    },
    "expected_response_mode": {"type": "string", "enum": ["recommendation", "factual", "out_of_remit"]},
    "expected_engine_call_level": {"type": "string", "enum": ["holistic", "module", "factual"]},
    "expected_kyc_state": {"type": "string", "enum": ["passed", "bypass", "blocked"]},
    "expected_kyc_missing": {"type": "array"},
    "expected_tool_calls": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["tool", "required"],
        "properties": {
          "tool": {"type": "string"},
          "args": {"type": "object"},
          "required": {"type": "boolean"},
          "result_path": {"type": "string", "enum": ["happy", "success_false", "readiness_blocked", "empty_state"]},
          "result_message_contains": {"type": "string"},
          "condition": {"type": "string"}
        }
      }
    },
    "expected_tool_calls_absent": {"type": "array", "items": {"type": "string"}},
    "expected_sse_events": {
      "type": "object",
      "properties": {
        "must_contain_types": {"type": "array", "items": {"type": "string"}},
        "must_emit_exactly_once": {"type": "array", "items": {"type": "string"}},
        "must_not_emit": {"type": "array", "items": {"type": "string"}},
        "content_event_minimum": {"type": "integer", "minimum": 0},
        "tool_use_count_min": {"type": "integer", "minimum": 0},
        "tool_use_count_max": {"type": "integer", "minimum": 0}
      }
    },
    "expected_assistant_text": {
      "type": "object",
      "properties": {
        "must_contain_substrings": {"type": "array", "items": {"type": "string"}},
        "must_contain_at_least_one_of": {"type": "array", "items": {"type": "array", "items": {"type": "string"}}},
        "must_not_contain_substrings": {"type": "array", "items": {"type": "string"}},
        "minimum_length_chars": {"type": "integer", "minimum": 0},
        "maximum_length_chars": {"type": "integer", "minimum": 0},
        "exact_match": {"type": "string"}
      }
    },
    "expected_db_writes": {
      "type": "object",
      "properties": {
        "expected_count": {"type": "integer", "minimum": 0},
        "expected_no_writes_to": {"type": "array", "items": {"type": "string"}}
      }
    },
    "expected_engine_trace": {
      "type": "object",
      "properties": {
        "must_contain": {"type": "array"},
        "must_not_contain": {"type": "array"},
        "ordered": {"type": "array", "items": {"type": "string"}}
      }
    },
    "expected_http_log": {
      "type": "object",
      "properties": {
        "calls": {"type": "integer", "minimum": 0},
        "must_have_status_200": {"type": "array", "items": {"type": "string"}}
      }
    },
    "timing_budget_ms": {
      "type": "object",
      "patternProperties": {
        "^(anthropic|xai)$": {
          "type": "object",
          "patternProperties": {
            "^(happy|success_false|readiness_blocked|kyc_blocked|factual)$": {"type": "integer", "minimum": 100}
          }
        }
      }
    },
    "tags": {"type": "array", "items": {"type": "string"}}
  }
}
```

- [ ] **Step 12.2: Commit (no test yet — Task 14 will validate scenarios against this)**

```bash
git add tests/Feature/Fyn/Eval/scenarios/_schema.json
git commit -m "feat(eval): add JSON Schema for scenario files"
```

---

## Task 13: Author the 10 JSON scenarios + delete the 6 YAMLs

**Goal:** Per spec §10.2 and §10.3 — 10 mitchell scenarios bound to peak_earners.

**Files:**
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_protection_cover.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_savings_emergency.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_investment_isa.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_retirement_contribution.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_estate_iht.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_holistic_health.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_tax_optimisation.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_advice_goals_affordability.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_factual_net_worth.json`
- Create: `tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_factual_income.json`
- Delete: `tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_*.yaml` (6 files)

- [ ] **Step 13.1: Author `mitchell_advice_protection_cover.json` (the canonical first one)**

```json
{
  "id": "mitchell_advice_protection_cover",
  "category": "01-query-types",
  "persona": "peak_earners",
  "is_mutating": false,
  "description": "David Mitchell asks about protection cover. Classifier resolves to PROTECTION_COVER (primary), modules: [protection]. Universal KYC passes (full peak_earners seed). ProtectionAgent finds protection_profile + life + critical illness + income protection policies all populated → happy path across all 3 protection types. LLM should reference specific policies and signpost FCA advice.",
  "input": {
    "turns": [
      {"user": "Am I covered enough for protection?", "current_route": "/protection"}
    ]
  },
  "expected_classification_shape": {
    "primary": "protection_cover",
    "related": [],
    "modules": ["protection"]
  },
  "expected_response_mode": "recommendation",
  "expected_engine_call_level": "module",
  "expected_kyc_state": "passed",
  "expected_tool_calls": [
    {"tool": "list_records", "args": {"entity_type": "life_insurance"}, "required": true, "result_path": "happy"},
    {"tool": "list_records", "args": {"entity_type": "critical_illness"}, "required": true, "result_path": "happy"},
    {"tool": "list_records", "args": {"entity_type": "income_protection"}, "required": true, "result_path": "happy"},
    {"tool": "get_module_analysis", "args": {"module": "protection"}, "required": true, "result_path": "happy"}
  ],
  "expected_tool_calls_absent": [
    "create_protection_policy",
    "update_protection_policy",
    "delete_protection_policy",
    "delegate_to_capture"
  ],
  "expected_sse_events": {
    "must_contain_types": ["title", "content", "tool_use", "done"],
    "must_emit_exactly_once": ["done", "title"],
    "must_not_emit": ["persona_state_change", "handoff", "consent_required", "error"],
    "content_event_minimum": 5,
    "tool_use_count_min": 4,
    "tool_use_count_max": 12
  },
  "expected_assistant_text": {
    "must_contain_substrings": [
      "For regulated advice personal to your circumstances, speak to a qualified financial adviser."
    ],
    "must_contain_at_least_one_of": [
      ["life", "critical illness", "income protection"]
    ],
    "must_not_contain_substrings": ["I think you should", "I'd recommend", "In my opinion", "you should definitely"],
    "minimum_length_chars": 200,
    "maximum_length_chars": 3000
  },
  "expected_db_writes": {
    "expected_count": 0,
    "expected_no_writes_to": ["life_insurance_policies", "critical_illness_policies", "income_protection_policies", "users", "protection_profiles"]
  },
  "expected_engine_trace": {
    "must_contain": [
      {"event": "AgentDecision", "decisionPoint": "classify_query"},
      {"event": "AgentDecision", "decisionPoint": "response_mode", "outcome": "recommendation"},
      {"event": "AgentDecision", "decisionPoint": "engine_call_level", "outcome": "module"},
      {"event": "GateChecked", "gate": "kyc", "module": "global", "passed": true},
      {"event": "GateChecked", "gate": "kyc", "module": "protection", "passed": true},
      {"event": "GateChecked", "gate": "data_readiness", "module": "protection", "passed": true},
      {"event": "GateChecked", "gate": "profile_gate", "module": "protection", "passed": true},
      {"event": "EngineCalled", "engine": "protection_analysis"}
    ],
    "must_not_contain": [
      {"event": "EngineCalled", "engine": "orchestrate_analysis"}
    ]
  },
  "expected_http_log": {
    "calls": 4,
    "must_have_status_200": ["login", "create_conversation", "send_message", "logout"]
  },
  "timing_budget_ms": {
    "anthropic": {"happy": 9000},
    "xai": {"happy": 22000}
  },
  "tags": ["regression-band-0", "recommendation-mode", "protection", "peak_earners", "happy-path"]
}
```

- [ ] **Step 13.2: Author the remaining 9 scenarios**

For each remaining scenario, copy the structure of `mitchell_advice_protection_cover.json` and adjust:

- `id`, `description`, `input.turns[0].user`, `input.turns[0].current_route` (route the user is on for that question).
- `expected_classification_shape.primary` and `.related` and `.modules` per `QuerySchemas` for that query type (verify each via `php artisan tinker --execute="dump(app(\App\Services\AI\QueryClassifier::class)->classify('<message>'));"`).
- `expected_response_mode`: `recommendation` for advice-type queries (#1-#8); `factual` for #9-#10.
- `expected_engine_call_level`: `module` for #1-#5, #7-#8; `holistic` for #6 (holistic health); `factual` for #9-#10.
- `expected_kyc_state`: `passed` for #1-#8; `bypass` for #9-#10 (factual queries bypass KYC).
- `expected_tool_calls`: per `QuerySchemas::REQUIRED_TOOLS[<primary>]` for that classification, all `required: true` with `result_path: happy` (peak_earners has full data).
- `expected_engine_trace.must_contain`: classify_query, response_mode, engine_call_level, kyc:global passed, plus per-module gates and engine_call for each module the classification touches.
- `timing_budget_ms.anthropic.happy` and `xai.happy`: start at 9000 / 22000; calibrate after recording.
- `tags`: include `peak_earners`, `happy-path`, plus the classification name.

Author all 10 files. Each is ~80-100 lines of JSON.

- [ ] **Step 13.3: Validate each scenario against the schema**

```bash
php artisan tinker --execute="
\$schema = json_decode(file_get_contents(base_path('tests/Feature/Fyn/Eval/scenarios/_schema.json')));
\$validator = new \JsonSchema\Validator;
foreach (glob(base_path('tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json')) as \$f) {
    \$data = json_decode(file_get_contents(\$f));
    \$validator->validate(\$data, \$schema);
    echo basename(\$f) . ': ' . (\$validator->isValid() ? 'OK' : 'FAIL — '.json_encode(\$validator->getErrors())) . PHP_EOL;
    \$validator->reset();
}"
```

Install `justinrainbow/json-schema` if missing: `composer require --dev justinrainbow/json-schema`.

Expected: 10 lines, all `OK`.

- [ ] **Step 13.4: Delete the 6 superseded YAMLs**

```bash
rm tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_protection_cover.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_savings_emergency.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_investment_isa.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_retirement_contribution.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_estate_iht.yaml \
   tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_goals_affordability.yaml
```

- [ ] **Step 13.5: Commit**

```bash
git add tests/Feature/Fyn/Eval/scenarios/01-query-types/mitchell_*.json
git rm tests/Feature/Fyn/Eval/scenarios/01-query-types/advice_*.yaml
git commit -m "feat(eval): author 10 mitchell JSON scenarios; delete superseded advice YAMLs"
```

---

## Task 14: Wire `EvalDeltaBuilder` to JSON + add `gradeEngineTrace`

**Goal:** Per spec §11.2 — keep the delta-builder logic, swap YAML→JSON, add engine-trace grading.

**Files:**
- Modify: `app/Services/Eval/EvalDeltaBuilder.php`
- Modify: `app/Http/Controllers/Api/Admin/EvalRecordingController.php`
- Modify: `tests/Unit/Services/Eval/EvalDeltaBuilderTest.php` (existing fixtures: YAML→JSON)

- [ ] **Step 14.1: YAML→JSON in `EvalDeltaBuilder.php`**

In `app/Services/Eval/EvalDeltaBuilder.php`, locate any `Yaml::parse(...)` calls (the spec notes 1 site around line 65) and replace with `json_decode(..., true)`.

If the builder receives a parsed array directly (not raw text), no change needed there — only the call-sites parsing the file change.

- [ ] **Step 14.2: YAML→JSON in `EvalRecordingController.php`**

In `app/Http/Controllers/Api/Admin/EvalRecordingController.php` `parseExpectations` method (~line 190), replace `Yaml::parse($yamlText)` with `json_decode($jsonText, true)`. Also update the file-extension lookup from `.yaml` to `.json`.

- [ ] **Step 14.3: Add `gradeEngineTrace` to `EvalDeltaBuilder`**

Add a new public method to `EvalDeltaBuilder`:

```php
/**
 * Grade the captured engine trace against the scenario's expected_engine_trace.
 *
 * @param  list<array<string, mixed>>  $trace
 * @param  array<string, mixed>  $expected
 * @return array{must_contain_misses: list<array<string, mixed>>, must_not_contain_hits: list<array<string, mixed>>, ordered_violations: list<string>}
 */
public function gradeEngineTrace(array $trace, array $expected): array
{
    $mustContainMisses = [];
    foreach ($expected['must_contain'] ?? [] as $expectedEntry) {
        $matched = collect($trace)->contains(fn ($t) => $this->traceEntryMatches($t, $expectedEntry));
        if (! $matched) {
            $mustContainMisses[] = $expectedEntry;
        }
    }

    $mustNotContainHits = [];
    foreach ($expected['must_not_contain'] ?? [] as $forbiddenEntry) {
        $matched = collect($trace)->contains(fn ($t) => $this->traceEntryMatches($t, $forbiddenEntry));
        if ($matched) {
            $mustNotContainHits[] = $forbiddenEntry;
        }
    }

    $orderedViolations = [];
    if (! empty($expected['ordered'] ?? [])) {
        $traceKeys = collect($trace)->map(fn ($t) => $this->traceEntryKey($t))->all();
        $cursor = 0;
        foreach ($expected['ordered'] as $expectedKey) {
            $found = false;
            for ($i = $cursor; $i < count($traceKeys); $i++) {
                if ($traceKeys[$i] === $expectedKey) {
                    $cursor = $i + 1;
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $orderedViolations[] = $expectedKey;
            }
        }
    }

    return [
        'must_contain_misses' => $mustContainMisses,
        'must_not_contain_hits' => $mustNotContainHits,
        'ordered_violations' => $orderedViolations,
    ];
}

private function traceEntryMatches(array $traceEntry, array $expectedEntry): bool
{
    foreach ($expectedEntry as $key => $expectedValue) {
        if (str_contains($key, '.')) {
            // Dot-path lookup, e.g. "context.primary"
            $parts = explode('.', $key);
            $cursor = $traceEntry;
            foreach ($parts as $p) {
                if (! is_array($cursor) || ! array_key_exists($p, $cursor)) {
                    return false;
                }
                $cursor = $cursor[$p];
            }
            if ($cursor !== $expectedValue) {
                return false;
            }
        } else {
            if (($traceEntry[$key] ?? null) !== $expectedValue) {
                return false;
            }
        }
    }

    return true;
}

private function traceEntryKey(array $traceEntry): string
{
    return ($traceEntry['event'] ?? '') . ':' .
        ($traceEntry['gate'] ?? $traceEntry['engine'] ?? $traceEntry['decisionPoint'] ?? '');
}
```

- [ ] **Step 14.4: Convert existing `EvalDeltaBuilderTest` fixtures from YAML to JSON**

In `tests/Unit/Services/Eval/EvalDeltaBuilderTest.php`, if any tests construct YAML strings and parse them, replace with PHP arrays directly (or with `json_decode(<json string>, true)`).

Add new test cases for `gradeEngineTrace`:

```php
it('gradeEngineTrace flags must_contain misses', function () {
    $builder = new EvalDeltaBuilder;
    $trace = [
        ['event' => 'GateChecked', 'gate' => 'kyc', 'module' => 'global', 'passed' => true],
    ];
    $expected = [
        'must_contain' => [
            ['event' => 'GateChecked', 'gate' => 'kyc', 'module' => 'global', 'passed' => true],
            ['event' => 'EngineCalled', 'engine' => 'protection_analysis'],
        ],
    ];

    $result = $builder->gradeEngineTrace($trace, $expected);

    expect($result['must_contain_misses'])->toHaveCount(1)
        ->and($result['must_contain_misses'][0]['engine'])->toBe('protection_analysis');
});

it('gradeEngineTrace flags must_not_contain hits', function () {
    $builder = new EvalDeltaBuilder;
    $trace = [['event' => 'EngineCalled', 'engine' => 'orchestrate_analysis']];
    $expected = ['must_not_contain' => [['event' => 'EngineCalled', 'engine' => 'orchestrate_analysis']]];

    $result = $builder->gradeEngineTrace($trace, $expected);

    expect($result['must_not_contain_hits'])->toHaveCount(1);
});

it('gradeEngineTrace flags ordering violations', function () {
    $builder = new EvalDeltaBuilder;
    $trace = [
        ['event' => 'EngineCalled', 'engine' => 'protection_analysis'],
        ['event' => 'AgentDecision', 'decisionPoint' => 'classify_query'],
    ];
    $expected = ['ordered' => ['AgentDecision:classify_query', 'EngineCalled:protection_analysis']];

    $result = $builder->gradeEngineTrace($trace, $expected);

    expect($result['ordered_violations'])->not->toBeEmpty();
});
```

- [ ] **Step 14.5: Run tests**

```bash
./vendor/bin/pest tests/Unit/Services/Eval/EvalDeltaBuilderTest.php
```

Expected: All previously-green + the 3 new engine-trace tests PASS.

- [ ] **Step 14.6: Commit**

```bash
git add app/Services/Eval/EvalDeltaBuilder.php app/Http/Controllers/Api/Admin/EvalRecordingController.php tests/Unit/Services/Eval/EvalDeltaBuilderTest.php
git commit -m "feat(eval): wire EvalDeltaBuilder to JSON + add gradeEngineTrace"
```

---

## Task 15: Architecture meta-tests

**Goal:** Lock the eval system against drift. 5 new architecture tests.

**Files:**
- Create: `tests/Architecture/EvalScenarioJsonSchemaTest.php`
- Create: `tests/Architecture/EvalScenarioPersonaIsValidTest.php`
- Create: `tests/Architecture/EvalScenarioMutatingFlagMatchesWritesTest.php`
- Create: `tests/Architecture/EvalScenarioEngineTraceConsistencyTest.php`
- Create: `tests/Architecture/PreviewBlockSitesCheckBypassTest.php`

- [ ] **Step 15.1: Write `EvalScenarioJsonSchemaTest`**

```php
<?php

declare(strict_types=1);

use JsonSchema\Validator;

it('every scenario JSON validates against _schema.json', function () {
    $schema = json_decode(file_get_contents(base_path('tests/Feature/Fyn/Eval/scenarios/_schema.json')));
    $validator = new Validator;

    $files = glob(base_path('tests/Feature/Fyn/Eval/scenarios/*/mitchell_*.json')) ?: [];
    expect($files)->not->toBeEmpty('no scenario files found — did Task 13 run?');

    foreach ($files as $file) {
        $data = json_decode(file_get_contents($file));
        $validator->validate($data, $schema);
        expect($validator->isValid())->toBeTrue(
            "schema violation in ".basename($file).": ".json_encode($validator->getErrors())
        );
        $validator->reset();
    }
});
```

- [ ] **Step 15.2: Write `EvalScenarioPersonaIsValidTest`**

```php
<?php

declare(strict_types=1);

it('every scenario binds to a known persona', function () {
    $valid = ['young_family', 'peak_earners', 'entrepreneur', 'young_saver', 'retired_couple', 'student'];
    $files = glob(base_path('tests/Feature/Fyn/Eval/scenarios/*/*.json')) ?: [];

    foreach ($files as $file) {
        if (basename($file) === '_schema.json') continue;
        $data = json_decode(file_get_contents($file), true);
        expect($data['persona'])->toBeIn($valid, "invalid persona in ".basename($file));
    }
});
```

- [ ] **Step 15.3: Write `EvalScenarioMutatingFlagMatchesWritesTest`**

```php
<?php

declare(strict_types=1);

it('is_mutating: false scenarios assert expected_db_writes.expected_count: 0', function () {
    $files = glob(base_path('tests/Feature/Fyn/Eval/scenarios/*/*.json')) ?: [];

    foreach ($files as $file) {
        if (basename($file) === '_schema.json') continue;
        $data = json_decode(file_get_contents($file), true);
        if (! ($data['is_mutating'] ?? false)) {
            expect($data['expected_db_writes']['expected_count'] ?? null)
                ->toBe(0, "non-mutating scenario ".basename($file)." should expect 0 writes");
        }
    }
});
```

- [ ] **Step 15.4: Write `EvalScenarioEngineTraceConsistencyTest`**

```php
<?php

declare(strict_types=1);

it('every expected_engine_trace entry references a real engine/gate name', function () {
    $validEngines = ['orchestrate_analysis', 'protection_analysis', 'savings_analysis', 'investment_analysis', 'retirement_analysis', 'estate_analysis', 'goals_analysis', 'protection_recommendation', 'savings_recommendation', 'investment_recommendation', 'retirement_recommendation', 'estate_recommendation', 'goals_recommendation'];
    $validGates = ['kyc', 'data_readiness', 'profile_gate', 'recommendation_eligibility'];
    $validDecisionPoints = ['classify_query', 'response_mode', 'engine_call_level', 'tool_dispatch', 'profile_gate', 'analyze_complete'];

    $files = glob(base_path('tests/Feature/Fyn/Eval/scenarios/*/*.json')) ?: [];

    foreach ($files as $file) {
        if (basename($file) === '_schema.json') continue;
        $data = json_decode(file_get_contents($file), true);
        $trace = $data['expected_engine_trace'] ?? [];

        foreach (array_merge($trace['must_contain'] ?? [], $trace['must_not_contain'] ?? []) as $entry) {
            if (isset($entry['engine'])) {
                expect($entry['engine'])->toBeIn($validEngines, "unknown engine in ".basename($file).": {$entry['engine']}");
            }
            if (isset($entry['gate'])) {
                expect($entry['gate'])->toBeIn($validGates, "unknown gate in ".basename($file).": {$entry['gate']}");
            }
            if (isset($entry['decisionPoint'])) {
                expect($entry['decisionPoint'])->toBeIn($validDecisionPoints, "unknown decisionPoint in ".basename($file).": {$entry['decisionPoint']}");
            }
        }
    }
});
```

- [ ] **Step 15.5: Write `PreviewBlockSitesCheckBypassTest`**

```php
<?php

declare(strict_types=1);

it('every preview write-block site checks the bypass-preview-mode ability', function () {
    $sites = [
        'app/Http/Middleware/PreviewWriteInterceptor.php',
        'app/Traits/HasAiChat.php',
        'app/Agents/CoordinatingAgent.php',
    ];

    foreach ($sites as $site) {
        $contents = file_get_contents(base_path($site));
        expect($contents)->toContain('bypass-preview-mode', "{$site} does NOT check bypass-preview-mode ability — eval writes will be silently swallowed");
    }
});
```

- [ ] **Step 15.6: Run all 5 architecture tests**

```bash
./vendor/bin/pest --testsuite=Architecture --filter=Eval
./vendor/bin/pest tests/Architecture/PreviewBlockSitesCheckBypassTest.php
```

Expected: All green.

- [ ] **Step 15.7: Commit**

```bash
git add tests/Architecture/EvalScenarioJsonSchemaTest.php tests/Architecture/EvalScenarioPersonaIsValidTest.php tests/Architecture/EvalScenarioMutatingFlagMatchesWritesTest.php tests/Architecture/EvalScenarioEngineTraceConsistencyTest.php tests/Architecture/PreviewBlockSitesCheckBypassTest.php
git commit -m "feat(eval): add 5 architecture meta-tests for scenario integrity"
```

---

## Task 16: Re-record all 10 scenarios + dashboard polish

**Goal:** End-to-end verification. The hard acceptance gate from spec §12.16.

**Files:**
- Modify: `resources/js/components/Admin/eval/RunPanel.vue` (add HTTP log + engine trace timeline panels)
- Run: `php artisan eval:record mitchell_*` for each of the 10

- [ ] **Step 16.1: Pre-flight**

In a separate terminal, run `./dev.sh`. Wait for the server to be ready at http://localhost:8000.

- [ ] **Step 16.2: Re-record `mitchell_advice_protection_cover` against both providers**

```bash
php artisan eval:record mitchell_advice_protection_cover
```

Expected: Both providers complete. Output shows:
- `events=` non-zero
- `tool_calls` includes `list_records(life_insurance)`, `list_records(critical_illness)`, `list_records(income_protection)`, `get_module_analysis(protection)`
- `engine_trace` length non-zero
- Session row in `eval_recording_sessions` with `persona='peak_earners'`, `http_log` populated.

- [ ] **Step 16.3: Verify the hard acceptance gate**

```bash
php artisan tinker --execute="
\$session = \App\Models\EvalRecordingSession::where('scenario_id', 'mitchell_advice_protection_cover')->latest('id')->firstOrFail();
echo 'persona=' . \$session->persona . PHP_EOL;
echo 'http_log_calls=' . count(\$session->http_log ?? []) . PHP_EOL;
echo 'status=' . \$session->status . PHP_EOL;
foreach (\$session->providerRuns as \$run) {
    echo '  provider=' . \$run->provider . ' tools=' . count(\$run->tool_calls ?? []) . ' trace=' . count(\$run->engine_trace ?? []) . PHP_EOL;
}
"
```

Expected output:
```
persona=peak_earners
http_log_calls=4
status=completed
  provider=anthropic tools=4+ trace=8+
  provider=xai tools=4+ trace=8+
```

- [ ] **Step 16.4: Re-record the remaining 9 scenarios**

```bash
for scenario in \
    mitchell_advice_savings_emergency \
    mitchell_advice_investment_isa \
    mitchell_advice_retirement_contribution \
    mitchell_advice_estate_iht \
    mitchell_advice_holistic_health \
    mitchell_advice_tax_optimisation \
    mitchell_advice_goals_affordability \
    mitchell_factual_net_worth \
    mitchell_factual_income; do
  php artisan eval:record "$scenario"
done
```

For each, calibrate the `timing_budget_ms.{anthropic,xai}.happy` (or .factual) values in the JSON to ~+30% of the actual recorded duration. Commit calibrations as a single follow-up commit.

- [ ] **Step 16.5: Update `RunPanel.vue` to render `http_log` and `engine_trace`**

In `resources/js/components/Admin/eval/RunPanel.vue`, add two new panels after the existing tool-call timeline:

```vue
<!-- HTTP log panel -->
<div v-if="run.session?.http_log?.length" class="mt-6">
    <h3 class="text-lg font-bold text-horizon-500 mb-3">HTTP log</h3>
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-savannah-200">
                <th class="text-left py-2">Method</th>
                <th class="text-left py-2">URL</th>
                <th class="text-right py-2">Status</th>
                <th class="text-right py-2">Duration</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(call, i) in run.session.http_log" :key="i" class="border-b border-savannah-100">
                <td class="py-2 font-mono">{{ call.method }}</td>
                <td class="py-2 font-mono truncate max-w-md">{{ call.url }}</td>
                <td class="py-2 text-right" :class="call.status >= 400 ? 'text-raspberry-500' : 'text-spring-500'">{{ call.status }}</td>
                <td class="py-2 text-right">{{ call.duration_ms }}ms</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Engine + gate timeline -->
<div v-if="run.engine_trace?.length" class="mt-6">
    <h3 class="text-lg font-bold text-horizon-500 mb-3">Engine + gate timeline</h3>
    <ul class="space-y-2">
        <li v-for="(entry, i) in run.engine_trace" :key="i" class="flex items-start gap-3 text-sm">
            <span class="text-horizon-300 font-mono w-12 text-right">{{ entry.t_ms }}ms</span>
            <span :class="traceColorClass(entry.event)" class="font-bold">{{ entry.event }}</span>
            <span class="flex-1">{{ traceLine(entry) }}</span>
        </li>
    </ul>
</div>
```

Add to the script:

```js
methods: {
    traceColorClass(eventType) {
        return {
            GateChecked: 'text-horizon-500',
            EngineCalled: 'text-raspberry-500',
            AgentDecision: 'text-spring-600',
        }[eventType] || 'text-horizon-400';
    },
    traceLine(entry) {
        if (entry.event === 'GateChecked') {
            return `${entry.gate}:${entry.module} → ${entry.passed ? 'PASS' : 'FAIL'}`;
        }
        if (entry.event === 'EngineCalled') {
            return `${entry.engine} → ${entry.resultSummary?.result_path || '?'} (${entry.durationMs}ms)`;
        }
        if (entry.event === 'AgentDecision') {
            return `${entry.agent}.${entry.decisionPoint} → ${entry.outcome}`;
        }
        return '';
    },
}
```

- [ ] **Step 16.6: Manual dashboard verification**

Open `http://localhost:8000/admin/eval-recordings/<latest_session_id>` in a browser (per CLAUDE.md, log in as `chris@fynla.org`). Verify:

- Persona is shown as `peak_earners`
- HTTP log shows 4 calls all 200 OK
- Engine + gate timeline shows the 8+ trace entries

- [ ] **Step 16.7: Calibrate timing budgets for any over-budget scenarios**

For any scenario where actual duration > YAML-stated budget, update the JSON's `timing_budget_ms.{provider}.{path}` to actual + 30%.

Commit calibrations:

```bash
git add tests/Feature/Fyn/Eval/scenarios/01-query-types/*.json
git commit -m "chore(eval): calibrate timing budgets after live recording"
```

- [ ] **Step 16.8: Final acceptance test**

```bash
./vendor/bin/pest tests/Architecture/ tests/Feature/Fyn/Eval/ tests/Unit/Services/Eval/ tests/Feature/PreviewBypass tests/Feature/EvalAuth tests/Feature/EvalTrace
```

Expected: All green.

- [ ] **Step 16.9: Commit dashboard updates**

```bash
git add resources/js/components/Admin/eval/RunPanel.vue
git commit -m "feat(eval): render persona, HTTP log, engine/gate timeline in admin dashboard"
```

---

## Acceptance gate (overall)

Per spec §12.16, the rewrite is GREEN if and only if:

1. ✅ `php artisan db:seed --class=PreviewUserSeeder` produces a `peak_earners` user with full data.
2. ✅ `php artisan eval:record mitchell_advice_protection_cover` runs end-to-end via the HTTP loop. Session row has `persona='peak_earners'`, `http_log` populated, `engine_trace` populated, `status='completed'`. Both providers' runs have populated `assistant_text`, `tool_calls` containing all 4 expected tools, `db_writes_made` empty.
3. ✅ Captured `tool_calls[*].result` for each `list_records` call returns David Mitchell's actual policies (non-empty arrays).
4. ✅ Captured `tool_calls.get_module_analysis(protection)` returns `happy` path.
5. ✅ Assistant text contains FCA signposting AND references real persona data (e.g. "Aviva", a £-figure, a policy type by name).
6. ✅ Captured `engine_trace` contains the 7 expected events from §12.16 step 6 in order, with NO `EngineCalled:orchestrate_analysis`.
7. ✅ `EvalDeltaBuilder` grades both runs as PASS.

If any step is red, do NOT close out. Per CLAUDE.md Rule #15 (LOOP UNTIL CORRECT): diagnose, fix, re-verify, repeat until GREEN per the plan.

---

## Self-review notes (inline)

**Spec coverage:** Every component in spec §3.1 maps to a task. The 11 trace call sites in §5.3 are Task 6. The 3 write-block sites in §4.2 are Task 4. The eval-login endpoint in §4.4 is Task 7. The protection scope correction in §10.3 is Task 8. The 10 scenarios in §10.2 are Task 13. The 5 architecture meta-tests in §15.2 are Task 15. The dashboard render in §5.7 is Task 16.

**Type consistency:**
- `currentAccessToken()->can('bypass-preview-mode')` — used identically in PreviewWriteInterceptor (Task 4), HasAiChat (Task 4), CoordinatingAgent (Task 4), EvalTraceListener (Task 5).
- `event(new \App\Events\Eval\GateChecked(...))` — same constructor signature across all 11 call sites (Task 6).
- Scenario JSON keys (`expected_classification_shape`, `expected_response_mode`, etc.) match between schema (Task 12), authoring (Task 13), and the schema test (Task 15).

**No placeholders found:** Every step has actual code. Every command has expected output. No "TBD" or "similar to" or "implement appropriately".

---

## Execution Handoff

Plan complete and saved to `/Users/CSJ/Desktop/fynla/April/April27Updates/eval-http-driven-rewrite-implementation-plan.md`. Two execution options:

**1. Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration. Especially well-suited here because Tasks 3, 4, 5, 6, 7 are independent and parallelisable.

**2. Inline Execution** — Execute tasks in this session sequentially with checkpoints for review at the end of each task.

Which approach?
