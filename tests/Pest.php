<?php

declare(strict_types=1);
use Anthropic\Client;
use App\Models\TaxConfiguration;
use App\Services\AI\XaiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fyn\ScriptedAnthropicClient;
use Tests\Support\Fyn\ScriptedXaiClient;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Feature');

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Unit/Services', 'Unit/Observers', 'Unit/Http', 'Unit/Database', 'Unit/Listeners');

// Agent tests that need database access (RefreshDatabase)
uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Unit/Agents/ProtectionAgentTest.php', 'Unit/Agents/SavingsAgentTest.php', 'Unit/Agents/GoalsAgentTest.php', 'Unit/Agents/SavingsAgentGoalsTest.php', 'Unit/Agents/ProtectionAgentGoalsTest.php', 'Unit/Agents/EstateAgentGoalsTest.php', 'Unit/Agents/RetirementAgentGoalsTest.php');

// BaseAgentTest is pure unit tests, no database needed
uses(TestCase::class)->in('Unit/Agents/BaseAgentTest.php');

// Mail render tests need Laravel app (config/url helpers) but no DB — factory()->make() only.
uses(TestCase::class)->in('Unit/Mail');

// Trait tests resolve the full agent graph via app(CoordinatingAgent::class) — they
// need the Laravel container (AppServiceProvider bindings, e.g. TierGate) but no DB.
uses(TestCase::class)->in('Unit/Traits');

uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Integration');

// Sprint 0 browser harness — every BS-NN scenario binds to the Browser
// TestCase so the markPendingInteractiveRun() helper is in scope. No
// RefreshDatabase: scenarios run against a seeded local DB driven by
// `./dev.sh`, not the test schema.
uses(Tests\Browser\TestCase::class)->in('Browser/scenarios');

// Sprint 1 eval harness arch tests need `config()` to read fyn_eval.php.
// No DB needed — these are pure config integrity checks.
uses(TestCase::class)->in(
    'Architecture/EvalScenarioCountTest.php',
    'Architecture/EvalFloorIntegrityTest.php',
);

// NOTE (global hooks, re-activated 2026-06-12): the two hooks below ARE live.
// They were originally written as bare `beforeEach(closure)->in(...)` — not a
// Pest scoping form, so they never executed — and were removed in PR #536.
// They are now registered in the supported `uses()->beforeEach($closure)
// ->in(...)` form (see vendor/pestphp/pest/src/PendingCalls/UsesCall.php),
// which chains the hook BEFORE any file-level beforeEach (Testable::setUp
// wraps them as ChainableClosure::bound($globalHook, $fileLevelBeforeEach)),
// so per-file seeding/bindings still override what the hooks set up.
// Liveness is pinned by tests/Feature/Fyn/PestHooksLivenessTest.php — if
// either hook stops executing, that test fails. Do not remove the hooks or
// the pin without updating both.

// Global TaxConfiguration safety net: every covered test gets an active tax
// configuration unless it already created one. The row is pinned to tax year
// 2019/20 — deliberately OUTSIDE TaxConfigurationFactory's random range
// (2021/22–2026/27) and TaxConfigurationSeeder's seeded years, so it can
// never collide with per-test factory creates or seeder upserts on the
// unique tax_configurations.tax_year index. Tests that exercise the
// missing-config error path must deactivate/delete rows in their arrange step.
uses()->beforeEach(function () {
    // Ensure active tax configuration exists for tests
    if (class_exists(TaxConfiguration::class)) {
        if (! TaxConfiguration::where('is_active', true)->exists()) {
            TaxConfiguration::factory()->forTaxYear('2019/20')->create(['is_active' => true]);
        }
    }
})->in('Feature', 'Unit/Services', 'Unit/Observers', 'Unit/Http', 'Unit/Agents/ProtectionAgentTest.php', 'Unit/Agents/SavingsAgentTest.php', 'Unit/Agents/GoalsAgentTest.php', 'Unit/Agents/SavingsAgentGoalsTest.php', 'Unit/Agents/ProtectionAgentGoalsTest.php', 'Unit/Agents/EstateAgentGoalsTest.php', 'Unit/Agents/RetirementAgentGoalsTest.php', 'Integration');

// CoALA Phase 5 item 5 — the planner (and reasoner) resolve a provider LLM
// client on every advice turn. The suite's default provider is xAI (env), so an
// advice-path test that doesn't script the LLM would otherwise make a real
// network call (slow, non-deterministic, CI-fragile). Bind empty scripted
// clients by default across the chat-path suites so an unmocked turn degrades to
// a fast no-op (the planner falls back to a default reason; the reasoner streams
// nothing). Tests that script the LLM (FynStreamHarness, ScriptedXaiClient) or
// mock CoordinatingAgent override these in their own setup, which runs after.
uses()->beforeEach(function () {
    app()->instance(Client::class, new ScriptedAnthropicClient([]));
    app()->instance(XaiClient::class, new ScriptedXaiClient([]));
})->in('Feature/Fyn', 'Feature/AI', 'Feature/Onboarding', 'Unit/Services/AI', 'Unit/Services/Onboarding');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function tierConfigFixture(string $tier): array
{
    return [
        'tier' => $tier,
        'display_name' => ucfirst($tier),
        'price_monthly_pence' => $tier === 'premium' ? 699 : 0,
        'price_annual_pence' => $tier === 'premium' ? 5999 : 0,
        'revolut_plan_variation_id' => null,
        'capability_matrix' => ['dashboard' => 'full'],
        'count_caps' => ['savings_account' => $tier === 'free' ? 2 : null],
        'document_upload_allowance' => $tier === 'free' ? 0 : null,
        'document_storage_gb' => $tier === 'premium' ? 1.00 : null,
        'fyn_weekly_token_budget' => $tier === 'premium' ? 500_000 : 100_000,
        'fyn_daily_hard_backstop' => $tier === 'premium' ? 2_000_000 : 500_000,
        'currency_display_mode' => $tier === 'premium' ? 'user_choice' : 'gbp_only',
        'snapshot_surfacing_window_days' => $tier === 'premium' ? null : 90,
        'open_api_affordance' => $tier === 'premium',
        'is_active' => true,
        'updated_by' => null,
    ];
}
