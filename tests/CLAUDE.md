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

**Five shapes of one family — *a test that shares the code's misconception cannot fail*:**

| Variant | The misconception lives in | Why it cannot fail |
|---|---|---|
| **Mock** | the value the test supplies | it asserts what the mock was told |
| **Clamp** | the value the code can return | the output cannot vary |
| **Fixture** | **the data the test sets up** | **the branch is never entered** |
| **Collision** | **nothing — the test is fine** | **the right answer and the wrong answer are the same number** |
| **Decoy** | **the test's NAME** | **it never calls the code it is named after** |

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

**The fourth variant — Collision — added 2026-08-22, from the W-0228 mortgage-share fix.**

**This one is unlike the other three: the assertion, the fixture and the data are all
correct.** Nothing in the test file is wrong, so no amount of reading it will reveal the
problem. It fails to catch the bug because **the value it expects is also the value the
bug produces.**

The worked case: a property share was memoised in a `static` declared on a **trait**. A
static in a trait is **per using class**, so a dozen services each got their own private
cache with no single handle to clear any of them. The test changed the property to 60%,
cleared "the" cache, and read **40%** back — and 40% was *both* the correct answer for
the original state *and* the stale one. **The test could not distinguish the two
hypotheses**, so it passed while the memo was unclearable.

**How to spot it before it bites:** ask *"if the mechanism I am testing did nothing at
all, would this assertion still pass?"* If the expected value coincides with the
pre-change value, the default, the fallback, or a symmetric case, the answer is yes —
and the test proves nothing.

**Symmetry is the commonest source.** A joint 50/50 split makes the primary owner's
share and the co-owner's share the same number, so a card that always shows the primary
owner's share is **correct for both parties** and no test built on that persona can fail.
`LiabilityCard.vue` survived exactly this way, and only broke into view at 40/60 — see
the corollary above about a persona's gaps being the suite's gaps.

**The countermeasure is to move the input to a value where the two hypotheses diverge**
— an asymmetric split, a non-default rate, a second write after the first. If every case
in a suite uses 50/50, the suite cannot see a whole class of ownership bug.

**The fifth variant — Decoy — added 2026-08-23, from the estate-projection fix.**

**The test never calls the code it is named after.** `PropertyReadConsumerParityTest`
carried a case titled *"IHTCalculationService projected properties does NOT double-count
joint property across spouse pair"* which **never invoked `IHTCalculationService`.** It
reproduced the query pattern inline and asserted arithmetic the test itself had written.
It would have stayed green through the change that put **£177,000 of a stranger's money
into an estate**.

**This is worse than the other four, and worse than having no test at all** — because no
test does not tell you the service is guarded. A green case with that title does.

**It gets written in good faith.** This one was born in a refactor that changed a query
*pattern* across five consumers, and was written to lock the pattern — which was
reasonable. **The defect is the name.** Once the name existed, nobody re-read the body.

**The check:** for every test named after a class or method, confirm the body **resolves
and calls it**. `grep` the test file for the class name — if it appears only in the title,
the case proves nothing about it.

**Related, one layer down — the same signature in code rather than in tests.** A missing
attribute read off a **collection** returns `null` silently, while a **query-builder** read
of the same name throws. So the identical defect is invisible in the idiomatic half and
fatal in the other, and it survives precisely where the code reads well: `db_pensions.
transfer_value` (twice) and `mortgages.end_date` (which is `maturity_date`) were three
instances in one cycle. **When a column name proves not to exist, grep for every reader and
check which kind each one is** — the throwing ones are already known, the silent ones are
the backlog.

**A reconciliation check verifies the NUMBERS and is blind to the SUBJECT of the sentence.**
Added 2026-08-23. An agent built a check that parsed a rendered decision trace and proved
four properties: each rate against its own printed base, the subtraction against the printed
saving, and the second base against the first less the printed shortfall. **All four true.
The sentence was about the wrong person** — it reported one spouse's charitable position
under the other's name, with an instruction to amend the wrong will.

Its own account: *"I read that sentence four times, checked its arithmetic to the penny, and
**never asked whose will it was about.** The arithmetic check I built is blind to the subject
of the sentence."*

**Internal consistency is not correctness.** A self-consistent statement about the wrong
entity passes every reconciliation you can write, and the screenshot proving the arithmetic
also photographs the defect. **When a figure belongs to a person, an account or a household,
assert WHOSE it is as a separate property from what it equals** — and render it from the
session where the two differ.

**Rendering from the right session is NECESSARY AND NOT SUFFICIENT, and the agent proved it
on itself.** It had already read **both** accounts on its first pass. The defect was fully
visible in the screenshot it filed as evidence of the fix — *"If **David** increases
charitable bequests…"* printed over the other spouse's figures. **It looked straight at the
defect and did not see it, because the check's scope excluded the axis.** A wider sample
would not have helped; **only asking a different question would have.**

**The guard that works asserts the subject explicitly** — that the right name appears, **that
the wrong name does not**, and that the prescribed action names the right person's
instrument — **rendered from the session where those differ.** Then mutate the attribution
and confirm exactly that case reddens.

**An asymmetric fixture is only asymmetric along the AXIS YOU VARIED.** Added 2026-08-23.
A fixture varied the two spouses' charitable legacies (£30,000 against £5,000) — genuinely
asymmetric — but **always read from the survivor's session**, so it could not express a
defect about *whose* will is read. **The variation and the hypothesis were on different
axes.** Before trusting an asymmetric fixture, name the axis the defect lives on and check
you varied *that* one.

**`Model::fresh()` queries WITHOUT global scopes — so it returns a soft-deleted row as
happily as a live one.** Added 2026-08-23. An assertion that a row "still exists" after a
save passed **whether the row survived or was annihilated and rebuilt with the same id** —
which was precisely the behaviour under test. **Query for a live row instead.** Found by
mutation testing, not by reading the file: it is a Collision (the right answer and the wrong
answer are the same object) and nothing in the test looks wrong.

**And tighten a `toBeCloseTo` until the hypotheses part.** In the same batch,
`toBeCloseTo(0.305, 3)` passed under both readings — weighting an allocation-derived
£160,000 instead of the stored £160,018 gives 0.305 exactly. **A tolerance wide enough to
span both answers is a Collision you wrote yourself.**

**Component specs: `setData` injects PAST the layer you are trying to prove.**
Added 2026-08-23. Injecting a view-model into a component that loads its own data supplies
**the object the mapping was supposed to build** — so the case proves the template and
**skips the mapping entirely.** Seven green cases sat over a card that rendered wrong on the
live page; a Feature test separately proved the endpoint published the field. **Neither
covered the join.**

**It is not wrong — it is the right tool for testing a template.** But it needs a **sibling
case that drives the real lifecycle** with an endpoint-shaped payload, or the mapping
between endpoint and template is untested by construction.

**This is the Fixture variant on an integration seam, and it is the hard one for the reason
above: nothing in the file says "and no mapping runs here."** A mock or a clamp is visible
when you read the test. An injection point that bypasses a layer looks exactly like sensible
setup. The agent that wrote those cases put it plainly: *"I would not have found that by
reading my own test file; the browser found it."*

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
