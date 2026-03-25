# Testing Conventions

This file supplements the root `CLAUDE.md` with testing-specific patterns.

## Structure

```
tests/
  Unit/           102 tests - Isolated service/agent/model tests
    Agents/       Agent orchestration tests
    Models/       Model domain logic
    Services/     Service calculations (organised by module)
  Feature/        63 tests - API endpoint integration tests
    Api/          General API tests
    Auth/         Authentication flow
    Estate/       Estate module endpoints
    Protection/   Protection module endpoints
    Savings/      Savings module endpoints
    Security/     Security-specific tests
  Architecture/   7 tests - Code standards enforcement
  Integration/    3 tests - Multi-step workflow tests
```

## Pest Syntax (Preferred)

```php
<?php
declare(strict_types=1);  // Required in all test files

describe('FeatureName', function () {
    it('does something specific', function () {
        // Arrange → Act → Assert
        expect($result)->toBe($expected);
    });
});
```

Use `it()` / `describe()` syntax (not `test_` PHPUnit methods).

## Unit Tests

**Simple service test (no mocking):**
```php
it('calculates runway correctly', function () {
    $calculator = new EmergencyFundCalculator;
    expect($calculator->calculateRunway(12000, 2000))->toBe(6.0);
});
```

**Service test with mocking:**
```php
beforeEach(function () {
    $this->taxConfig = Mockery::mock(TaxConfigService::class);
    $this->taxConfig->shouldReceive('getInheritanceTax')->andReturn([...]);
    $this->calculator = new IHTCalculator($this->taxConfig);
});

afterEach(function () {
    Mockery::close();  // Always clean up Mockery
});
```

## Feature Tests (API)

```php
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('returns savings data for authenticated user', function () {
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson('/api/savings');

    $response->assertOk()
        ->assertJsonStructure(['success', 'data' => ['accounts', 'goals']]);
});

it('prevents access to other users data', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = SavingsAccount::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)->deleteJson("/api/savings/accounts/{$account->id}")
        ->assertNotFound();
});
```

**HTTP methods:** `getJson()`, `postJson()`, `putJson()`, `deleteJson()`

**Auth:** `$this->actingAs($user)` or `Sanctum::actingAs($user)`

**Assertions:** `assertOk()`, `assertCreated()`, `assertNotFound()`, `assertUnauthorized()`, `assertJsonStructure()`, `assertJson()`, `assertDatabaseHas()`

## Architecture Tests

```php
arch('all agents extend BaseAgent')
    ->expect('App\Agents')->classes()
    ->toExtend('App\Agents\BaseAgent')
    ->ignoring('App\Agents\BaseAgent');

arch('all services use strict types')
    ->expect('App\Services')->toUseStrictTypes();

arch('controllers do not use DB facade directly')
    ->expect('App\Http\Controllers')->not->toUse('Illuminate\Support\Facades\DB')
    ->ignoring([/* specific exceptions */]);
```

## Factories

55 factories in `database/factories/` with state methods:
```php
// Basic usage
$user = User::factory()->create();
$account = SavingsAccount::factory()->create(['user_id' => $user->id]);

// With state
$asset = Asset::factory()->mainResidence()->create();
$asset = Asset::factory()->ihtExempt()->joint()->create();  // Chain states

// Multiple
$accounts = SavingsAccount::factory(5)->create(['user_id' => $user->id]);
```

## Key Conventions

| Convention | Pattern |
|-----------|---------|
| Strict types | `declare(strict_types=1);` in every test file |
| Database | `RefreshDatabase` trait resets between tests |
| Tax config | Auto-seeded in `Pest.php` `beforeEach()` for services/features/agents |
| Naming | `{Feature}Test.php`, lowercase `it('does something')` |
| Assertions | Fluent `expect($x)->toBe(5)->and($y)->toContain('text')` |
| Mocking | Mockery with `shouldReceive()` + `Mockery::close()` in afterEach |
| Isolation | Always test that users cannot access other users' data |

## Running Tests

```bash
./vendor/bin/pest                              # All tests
./vendor/bin/pest tests/Unit/Services/Estate/  # By directory
./vendor/bin/pest --testsuite=Architecture     # By suite
./vendor/bin/pest --filter="calculateIHT"      # By name
./vendor/bin/pest --coverage                   # With coverage
```
