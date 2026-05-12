<?php

declare(strict_types=1);
use App\Models\TaxConfiguration;
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
)->in('Unit/Services', 'Unit/Observers', 'Unit/Http');

// Agent tests that need database access (RefreshDatabase)
uses(
    TestCase::class,
    RefreshDatabase::class,
)->in('Unit/Agents/ProtectionAgentTest.php', 'Unit/Agents/SavingsAgentTest.php', 'Unit/Agents/GoalsAgentTest.php', 'Unit/Agents/SavingsAgentGoalsTest.php', 'Unit/Agents/ProtectionAgentGoalsTest.php', 'Unit/Agents/EstateAgentGoalsTest.php', 'Unit/Agents/RetirementAgentGoalsTest.php');

// BaseAgentTest is pure unit tests, no database needed
uses(TestCase::class)->in('Unit/Agents/BaseAgentTest.php');

// Mail render tests need Laravel app (config/url helpers) but no DB — factory()->make() only.
uses(TestCase::class)->in('Unit/Mail');

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

// Global setup for all tests that need TaxConfiguration
beforeEach(function () {
    // Ensure active tax configuration exists for tests
    if (class_exists(TaxConfiguration::class)) {
        if (! TaxConfiguration::where('is_active', true)->exists()) {
            TaxConfiguration::factory()->create(['is_active' => true]);
        }
    }
})->in('Feature', 'Unit/Services', 'Unit/Observers', 'Unit/Http', 'Unit/Agents/ProtectionAgentTest.php', 'Unit/Agents/SavingsAgentTest.php', 'Unit/Agents/GoalsAgentTest.php', 'Unit/Agents/SavingsAgentGoalsTest.php', 'Unit/Agents/ProtectionAgentGoalsTest.php', 'Unit/Agents/EstateAgentGoalsTest.php', 'Unit/Agents/RetirementAgentGoalsTest.php', 'Integration');

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
