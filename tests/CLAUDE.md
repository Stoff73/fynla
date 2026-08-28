# Testing Conventions

Supplements the root `CLAUDE.md`.

**Load the `test-failure-forensics` skill before writing a test, and always when a suite goes red.** It carries the five variants of a test that cannot fail (Mock, Clamp, Fixture, Collision, Decoy) and the four non-failures that look like failures — a green suite going red for reasons unrelated to the code under test has cost real time more than once.

## Structure

```
tests/
  Unit/           Agents/ Models/ Services/ (by module)
  Feature/        API endpoint integration
  Architecture/   Pest arch tests
  Integration/    Multi-step workflows
  Browser/        Playwright end-to-end
    scenarios/    BS-NN-*.php — the Rule 14 acceptance contract
  Eval/           Fyn evaluation runs (own testsuite, not part of ./vendor/bin/pest)
```

Six testsuites in `phpunit.xml`: Unit, Feature, Integration, Architecture, Browser, Eval.

**`tests/Browser/scenarios/BS-NN-*.php` is where Rule 14 lives.** The docblock at the top of each scenario **is** the acceptance contract — every assertion (DB row, SSE shape, audit chain, UI card, no fabricated success) must hold before the work is done. **Never treat a green unit suite as satisfying a BS-NN scenario.**

## Pest

`it()` / `describe()` (not `test_` methods), `declare(strict_types=1);` in every file.

```php
describe('FeatureName', function () {
    it('does something specific', function () {
        expect($result)->toBe($expected);
    });
});
```

**Mocking:** Mockery in `beforeEach`, always `Mockery::close()` in `afterEach`.

**Feature tests:** `getJson()` / `postJson()` / `putJson()` / `deleteJson()`, `$this->actingAs($user)` or `Sanctum::actingAs()`, `assertOk()` / `assertCreated()` / `assertNotFound()` / `assertJsonStructure()` / `assertDatabaseHas()`.

**Always test that a user cannot reach another user's data** — the isolation case is expected in every module suite:

```php
it('prevents access to other users data', function () {
    $this->actingAs($user)->deleteJson("/api/savings/accounts/{$otherUsersAccount->id}")
        ->assertNotFound();
});
```

**Architecture tests** enforce the standards the root file states:

```php
arch('all agents extend BaseAgent')->expect('App\Agents')->classes()
    ->toExtend('App\Agents\BaseAgent')->ignoring('App\Agents\BaseAgent');
arch('controllers do not use DB facade directly')
    ->expect('App\Http\Controllers')->not->toUse('Illuminate\Support\Facades\DB');
```

## Tax configuration

Auto-created by the `Pest.php` global hook (a 2019/20 safety-net row when no active config exists; liveness pinned by `tests/Feature/Fyn/PestHooksLivenessTest.php`). **Tests needing real seeded years must still seed explicitly:** `$this->seed(TaxConfigurationSeeder::class)`. Chat-path suites get empty scripted AI clients from the second global hook.

## Running

```bash
./vendor/bin/pest                              # all
./vendor/bin/pest tests/Unit/Services/Estate/  # by directory
./vendor/bin/pest --testsuite=Architecture     # by suite
./vendor/bin/pest --filter="calculateIHT"      # by name
```

`Pest.php` binds `TestCase` and `RefreshDatabase` **per directory, by name** — a new file in an unbound directory throws "A facade root has not been set" for every test with 0 assertions. Put it in an already-bound directory rather than editing `Pest.php`, which is shared config.
