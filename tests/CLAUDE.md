# Testing Conventions

This file supplements the root `CLAUDE.md` with testing-specific patterns.

> **GOLDEN RULE #20 (CSJ, NEVER IGNORE):** every Fyn change — prompt, vocabulary, behaviour, rendering — is made ONCE, in ONE place, for ALL surfaces and paths. If more than one mechanism implements the behaviour, consolidating to one source is PART of the fix. Full text: root `CLAUDE.md` Rule 20.

## Structure

```
tests/
  Unit/           Isolated service/agent/model tests
    Agents/       Agent orchestration
    Models/       Model domain logic
    Services/     Service calculations (organised by module)
  Feature/        API endpoint integration tests
    Api/ Auth/ Estate/ Protection/ Savings/ Security/
  Architecture/   Code standards enforcement (Pest arch tests)
  Integration/    Multi-step workflow tests
  Browser/        Playwright end-to-end scenarios
    scenarios/    BS-NN-*.php — the Rule 14 acceptance contract
  Eval/           Fyn evaluation runs (own testsuite, not part of ./vendor/bin/pest)
```

Six testsuites are declared in `phpunit.xml`: Unit, Feature, Integration, Architecture, Browser, Eval.

**`tests/Browser/scenarios/BS-NN-*.php` is where Rule 14 lives.** The docblock at the top of each scenario IS the acceptance contract — every assertion in it must hold (DB row, SSE shape, audit chain, UI card, no fabricated success) before the work is done. Never treat a green unit suite as satisfying a BS-NN scenario.

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

Factories in `database/factories/` with state methods:
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

## When a green suite goes inexplicably red

Three causes that present as test failures with nothing to do with the test. Each
has cost real time; check them before diagnosing the code under test.

### 1. A new test file in an unbound directory — "A facade root has not been set"

`Pest.php` binds `TestCase` (and `RefreshDatabase`) **per directory**, by name.
`Unit/Services`, `Unit/Observers`, `Unit/Http`, `Unit/Database` and
`Unit/Listeners` are bound; a sibling like `Unit/Constants` is **not**, so the
Laravel app never boots and the first facade call throws
`RuntimeException: A facade root has not been set` — for every test in the file,
with 0 assertions.

Put the file in an already-bound directory rather than binding a new one:
schema-conformance and constant-vs-column tests belong in `Unit/Database`
alongside `UsersExpenditureColumnTypesTest` and `ProfileEnumColumnsTest`.
**Adding a binding means editing `Pest.php`, which is shared config — while
parallel batches are running, that is a collision, not a fix.**

### 2. The formatter silently deleted your `use` statement

Pint (and the PostToolUse formatter hook) removes an import that is unreferenced
**at the moment it runs**. Add `use App\Constants\Foo;` in one edit and the
reference to `Foo::` in the next, and the import is gone before the second edit
lands. Nothing warns you: the file is valid PHP, `php -l` passes, and the class
resolves as a same-namespace name that does not exist — so every request through
that class 500s and a dozen unrelated tests go red at once.

**Add the import and its first reference in the same edit**, and after any
formatter run on a file you just added an import to:
```bash
grep -n '^use ' path/to/File.php
```

### 3. `!` and `DEPR` are not failures — read the summary line, not the marks

**Added 2026-08-21** after two agents read the same file as red when it was green.

PHP 8.5 deprecates `ReflectionMethod::setAccessible()`, which Pest calls internally
(`vendor/pestphp/pest/src/Support/Reflection.php:36`). Any test file that trips it is
printed with **`!` against every test and the file marked `DEPR`** rather than `✓`.

At a glance that reads as "not passing". It is not. **The summary line is the truth:**

```
Tests:    14 deprecated (50 assertions)     <- GREEN. No `failed` count.
Tests:    4 failed, 30 skipped, 7156 passed <- red.
```

**If there is no `failed` count, nothing failed.** Check the summary before reporting a
file as red, and before anyone else re-runs it, bisects it, or attributes it to another
agent's in-flight change. On 2026-08-21 this cost one agent a diagnosis and nearly cost
another a wasted fix on a file that had already been corrected an hour earlier.

**Related and separate:** a file genuinely can be both — deprecated *and* failing. The
marks tell you nothing either way; only the summary does.

### 4. A clamped value is not a probe — assert on what the clamp discards

**Added 2026-08-22**, from the estate cash-flow fix.

**Whenever a fix introduces a clamp, the clamped figure stops being a usable probe.**
Every assertion written against it passes for the wrong reason — not because the input
was read, but because **the output cannot vary.**

The worked case: cash was projected without a floor and could reach −£1.8m. The fix
floors it at zero. A test asserting "adding a pension moves projected cash" then passes
trivially for a household modelled to outspend its means — it sits on the floor at zero
whether the pension is read or not.

**Look for the quantity the clamp discards and assert on that instead.** Here it was the
accumulated shortfall above the floor — the same fact, measured where it is still
visible. Elsewhere it will be an overflow, a capped allowance, a truncated count, or the
residual of a `max()`.

**A green test against a clamped value is the same class of defect as a green test
against a hardcoded literal: nothing in it can fail.**

**Three shapes of one family — *a test that shares the code's misconception cannot fail*:**

| Variant | The misconception lives in | Why it cannot fail |
|---|---|---|
| **Mock** | the value the test supplies | it asserts what the mock was told |
| **Clamp** | the value the code can return | the output cannot vary |
| **Fixture** | **the data the test sets up** | **the branch is never entered** |

**The fixture variant is the hardest to see.** A mock and a clamp are visible in the test
file — you can read them and ask what they hide. **A fixture's absence of a row is
invisible**: nothing in the file says *"and no liabilities exist here"*. Seven tests were
written over one method in one sitting, all passed, and none entered the branch holding
an undefined variable, because no fixture had a non-mortgage liability.

**The countermeasure is not more assertions — it is asking what the fixture does NOT
contain.** Before trusting a suite over a method that branches on a collection, **list
the shapes that collection can take and check the fixture produces more than one.**

**Corollary — a persona-derived fixture inherits the persona's blind spots.**
`peak_earners` is rich in properties, mortgages and policies and holds **no liabilities,
no business interests, and no third-party chattels** — so every test built from it is
silently strong in three areas and silently blind in three others. That is a fact about
the whole test strategy, not about one batch. **When a persona is the fixture, its gaps
are the suite's gaps.**

**In every variant the countermeasure is the same: assert that the answer MOVES when the
real input moves**, rather than asserting the answer equals a value the test itself
supplied.

### 5. Contention between parallel batches — discard, do not diagnose

Two fingerprints, neither a code failure:

| Symptom | Cause | Response |
|---|---|---|
| `SQLSTATE[40001] 1213 Deadlock` or `Unknown table` during migration; failures with **0 assertions** | Batches sharing one test database | Run with the batch's own database: `DB_DATABASE=laravel_testing_c ./vendor/bin/pest <paths>`. `phpunit.xml`'s `<env>` has no `force="true"`, so the shell wins. |
| Vitest failures with `Test timed out in 5000ms`, whole-suite duration several times normal (1008s vs 168s) | CPU saturation from parallel agents | Re-run the failing files **in isolation** before believing them. Do not raise the global timeout. |

The test that distinguishes both from a real failure: **re-run the file on its
own.** A real failure reproduces; contention does not.
