<?php

declare(strict_types=1);
use Illuminate\Foundation\Testing\RefreshDatabase;
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

// NOTE (test-isolation fix, 2026-06-12): this file used to carry two
// `beforeEach(closure)->in(...)` blocks (TaxConfiguration auto-create and
// scripted AI-client binding). That chain is NOT a Pest scoping form — a bare
// beforeEach() registers for tests in the registering file only (Pest.php has
// none) and `->in()` is swallowed by BeforeEachCall::__call as a runtime
// proxy — so neither hook ever executed. They were removed rather than
// activated: every test that needs TaxConfiguration seeds it explicitly
// (`$this->seed(TaxConfigurationSeeder::class)`), and chat-path tests bind
// their own scripted clients. If suite-wide hooks are ever wanted, the
// supported form is uses()->beforeEach($closure)->in(...).


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
        'price_monthly_pence' => 0,
        'price_annual_pence' => 0,
        'revolut_plan_variation_id' => null,
        'capability_matrix' => ['dashboard' => 'full'],
        'count_caps' => ['savings_account' => $tier === 'free' ? 3 : null],
        'document_upload_allowance' => 3,
        'document_storage_gb' => null,
        'fyn_weekly_token_budget' => 100_000,
        'fyn_daily_hard_backstop' => 500_000,
        'currency_display_mode' => 'gbp_only',
        'snapshot_surfacing_window_days' => 90,
        'open_api_affordance' => false,
        'is_active' => true,
        'updated_by' => null,
    ];
}
