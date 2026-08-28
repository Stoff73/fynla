---
name: test-failure-forensics
description: Why a Fynla test cannot fail, and why a green suite suddenly goes red for reasons unrelated to the code under test. Covers the five variants of a test that shares the code's misconception (Mock, Clamp, Fixture, Collision, Decoy), the subject-vs-arithmetic blind spot, and the four non-failures that look like failures (unbound Pest directory, a formatter-deleted import, PHP 8.5 DEPR marks, parallel-batch contention). Use when writing or reviewing Pest or Vitest tests, when a suite goes red unexpectedly, when a bug shipped past a green suite, or before trusting a suite over a method that branches on a collection.
---

# Test failure forensics

## Part 1 — five variants of "a test that shares the code's misconception cannot fail"

| Variant | The misconception lives in | Why it cannot fail |
|---|---|---|
| **Mock** | the value the test supplies | it asserts what the mock was told |
| **Clamp** | the value the code can return | the output cannot vary |
| **Fixture** | the data the test sets up | the branch is never entered |
| **Collision** | nothing — the test is fine | the right answer and the wrong answer are the same number |
| **Decoy** | the test's **name** | it never calls the code it is named after |

**In every variant the countermeasure is the same: assert that the answer MOVES when the real input moves**, rather than asserting the answer equals a value the test itself supplied.

### Clamp — a clamped value is not a probe

**Whenever a fix introduces a clamp, the clamped figure stops being a usable probe.** Every assertion against it passes for the wrong reason — not because the input was read, but because **the output cannot vary.**

Worked case: cash was projected without a floor and could reach −£1.8m. The fix floors it at zero. A test asserting "adding a pension moves projected cash" then passes trivially for a household modelled to outspend its means — it sits on the floor at zero whether the pension is read or not.

**Look for the quantity the clamp discards and assert on that instead.** Here it was the accumulated shortfall above the floor. Elsewhere: an overflow, a capped allowance, a truncated count, the residual of a `max()`.

### Fixture — the hardest to see

A mock and a clamp are visible in the test file; you can read them and ask what they hide. **A fixture's absence of a row is invisible** — nothing says *"and no liabilities exist here"*. Seven tests were written over one method in one sitting, all passed, and none entered the branch holding an undefined variable, because no fixture had a non-mortgage liability.

**The countermeasure is not more assertions — it is asking what the fixture does NOT contain.** Before trusting a suite over a method that branches on a collection, **list the shapes that collection can take and check the fixture produces more than one.**

**Corollary — a persona-derived fixture inherits the persona's blind spots.** `peak_earners` is rich in properties, mortgages and policies and holds **no liabilities, no business interests and no third-party chattels**, so every test built from it is silently strong in three areas and silently blind in three others. **When a persona is the fixture, its gaps are the suite's gaps.**

**Component specs: `setData` injects PAST the layer you are trying to prove.** Injecting a view-model into a component that loads its own data supplies **the object the mapping was supposed to build** — proving the template and skipping the mapping entirely. Seven green cases sat over a card that rendered wrong on the live page while a Feature test separately proved the endpoint published the field. **Neither covered the join.** It is the right tool for testing a template, but it needs a **sibling case driving the real lifecycle** with an endpoint-shaped payload.

### Collision — nothing in the file is wrong

**The assertion, the fixture and the data are all correct.** No amount of reading the test will reveal the problem. It fails to catch the bug because **the value it expects is also the value the bug produces.**

Worked case (W-0228): a property share memoised in a `static` declared on a **trait**. A static in a trait is **per using class**, so a dozen services each got a private cache with no single handle to clear any of them. The test set the property to 60%, cleared "the" cache, and read **40%** back — which was *both* the correct answer for the original state *and* the stale one. **The test could not distinguish the two hypotheses.**

**How to spot it:** ask *"if the mechanism I am testing did nothing at all, would this assertion still pass?"* If the expected value coincides with the pre-change value, the default, the fallback, or a symmetric case, the answer is yes.

**Symmetry is the commonest source.** A joint 50/50 split makes both owners' shares the same number, so a card that always shows the primary owner's share is **correct for both parties**. `LiabilityCard.vue` survived exactly this way and only broke into view at 40/60. **If every case in a suite uses 50/50, the suite cannot see a whole class of ownership bug.**

Two more Collision shapes:
- **`Model::fresh()` queries WITHOUT global scopes**, so it returns a soft-deleted row as happily as a live one. An assertion that a row "still exists" after a save passed **whether the row survived or was annihilated and rebuilt with the same id** — precisely the behaviour under test. **Query for a live row instead.** Found by mutation testing, not by reading the file.
- **Tighten a `toBeCloseTo` until the hypotheses part.** `toBeCloseTo(0.305, 3)` passed under both readings — weighting an allocation-derived £160,000 instead of the stored £160,018 gives 0.305 exactly. **A tolerance wide enough to span both answers is a Collision you wrote yourself.**

### Decoy — the test never calls what it is named after

`PropertyReadConsumerParityTest` carried a case titled *"IHTCalculationService projected properties does NOT double-count joint property across spouse pair"* which **never invoked `IHTCalculationService`.** It reproduced the query pattern inline and asserted arithmetic the test itself had written. It would have stayed green through the change that put **£177,000 of a stranger's money into an estate**.

**This is worse than having no test at all** — no test does not tell you the service is guarded; a green case with that title does.

**It gets written in good faith.** This one was born in a refactor locking a query *pattern* across five consumers, which was reasonable. **The defect is the name.** Once the name existed, nobody re-read the body.

**The check:** for every test named after a class or method, confirm the body **resolves and calls it**. `grep` the test file for the class name — if it appears only in the title, the case proves nothing about it.

**One layer down, the same signature in code:** a missing attribute read off a **collection** returns `null` silently, while a **query-builder** read of the same name throws. The identical defect is invisible in the idiomatic half and fatal in the other. `db_pensions.transfer_value` (twice) and `mortgages.end_date` (which is `maturity_date`) were three instances in one cycle. **When a column name proves not to exist, grep every reader and check which kind each one is** — the throwing ones are already known, the silent ones are the backlog.

## Part 2 — internal consistency is not correctness

**A reconciliation check verifies the NUMBERS and is blind to the SUBJECT of the sentence.** An agent built a check that parsed a rendered decision trace and proved four properties: each rate against its own printed base, the subtraction against the printed saving, and the second base against the first less the printed shortfall. **All four true. The sentence was about the wrong person** — it reported one spouse's charitable position under the other's name, with an instruction to amend the wrong will.

Its own account: *"I read that sentence four times, checked its arithmetic to the penny, and **never asked whose will it was about.**"*

**Rendering from the right session is necessary and not sufficient, and the agent proved it on itself.** It had already read both accounts on its first pass. The defect was fully visible in the screenshot it filed as evidence of the fix — *"If **David** increases charitable bequests…"* printed over the other spouse's figures. **It looked straight at the defect and did not see it, because the check's scope excluded the axis.** A wider sample would not have helped; only asking a different question would have.

**The guard that works asserts the subject explicitly** — that the right name appears, **that the wrong name does not**, and that the prescribed action names the right person's instrument — rendered from the session where those differ. Then mutate the attribution and confirm exactly that case reddens.

**An asymmetric fixture is only asymmetric along the AXIS YOU VARIED.** A fixture varied the two spouses' charitable legacies (£30,000 against £5,000) — genuinely asymmetric — but **always read from the survivor's session**, so it could not express a defect about *whose* will is read. **Name the axis the defect lives on and check you varied that one.**

## Part 3 — four non-failures that look like failures

Check these before diagnosing the code under test.

### 1. A new test file in an unbound directory — "A facade root has not been set"

`Pest.php` binds `TestCase` (and `RefreshDatabase`) **per directory, by name**. `Unit/Services`, `Unit/Observers`, `Unit/Http`, `Unit/Database` and `Unit/Listeners` are bound; a sibling like `Unit/Constants` is **not**, so the Laravel app never boots and the first facade call throws `RuntimeException: A facade root has not been set` — for every test in the file, with 0 assertions.

**Put the file in an already-bound directory** rather than binding a new one: schema-conformance and constant-vs-column tests belong in `Unit/Database`. **Adding a binding means editing `Pest.php`, which is shared config — while parallel batches are running, that is a collision, not a fix.**

### 2. The formatter silently deleted your `use` statement

Pint (and the PostToolUse formatter hook) removes an import unreferenced **at the moment it runs**. Add `use App\Constants\Foo;` in one edit and the `Foo::` reference in the next, and the import is gone before the second edit lands. Nothing warns you: the file is valid PHP, `php -l` passes, and the class resolves as a same-namespace name that does not exist — so every request through it 500s and a dozen unrelated tests go red at once.

**Add the import and its first reference in the same edit.** After any formatter run on a file you just added an import to: `grep -n '^use ' path/to/File.php`.

### 3. `!` and `DEPR` are not failures — read the summary line, not the marks

PHP 8.5 deprecates `ReflectionMethod::setAccessible()`, which Pest calls internally (`vendor/pestphp/pest/src/Support/Reflection.php:36`). Any file that trips it prints **`!` against every test and the file marked `DEPR`** rather than `✓`. At a glance that reads as "not passing". It is not.

```
Tests:    14 deprecated (50 assertions)      <- GREEN. No `failed` count.
Tests:    4 failed, 30 skipped, 7156 passed  <- red.
```

**If there is no `failed` count, nothing failed.** On 2026-08-21 this cost one agent a diagnosis and nearly cost another a wasted fix on a file corrected an hour earlier. A file genuinely can be both deprecated *and* failing — the marks tell you nothing either way; only the summary does.

### 4. Contention between parallel batches — discard, do not diagnose

| Symptom | Cause | Response |
|---|---|---|
| `SQLSTATE[40001] 1213 Deadlock` or `Unknown table` during migration; failures with **0 assertions** | Batches sharing one test database | Run with the batch's own database: `DB_DATABASE=laravel_testing_c ./vendor/bin/pest <paths>`. `phpunit.xml`'s `<env>` has no `force="true"`, so the shell wins. |
| Vitest `Test timed out in 5000ms`, whole-suite duration several times normal (1008s vs 168s) | CPU saturation from parallel agents | Re-run the failing files **in isolation** before believing them. Do not raise the global timeout. |

**The test that distinguishes both from a real failure: re-run the file on its own.** A real failure reproduces; contention does not.
