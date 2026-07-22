# SaveTax Funnel Income Cross-Check & Challenge — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When the income captured in the Fyn onboarding chat falls outside the band the user picked on the SaveTax funnel, Fyn challenges it with Continue/Change bubbles before advancing — for both the user's income and their spouse's income.

**Architecture:** A pure `FunnelIncomeBand` helper owns the band→range map. `OnboardingChatDirector` runs a deterministic mismatch check right after income is captured (`base_work` for the user, `base_spouse` for the spouse — both flow through `handleGroupedExtractTurn`). On mismatch it parks a `pending_income_challenge` flag in `users.onboarding_fyn_context`, emits a `quick_replies` challenge, and does not advance. The next turn's flag-first handler in `handleUserMessage` resolves Continue (advance) / Change (re-ask). All backend, so web and `/m` get it through the shared chat endpoint.

**Tech Stack:** Laravel 10, PHP 8.2 (`declare(strict_types=1)`), Pest. Existing onboarding flow in `app/Services/Onboarding/OnboardingChatDirector.php` + `OnboardingStateMachine.php`; capture handlers in `app/Agents/CoordinatingAgent.php`.

## Global Constraints

- PHP files start with `declare(strict_types=1);`; PSR-12; run `./vendor/bin/pint` before each commit.
- User-facing copy: plain text only — NO icons/emoji/Unicode glyphs (Rule #15). British spelling (Rule: "Optimise"). Spell out acronyms except ISA (Rule #9).
- No hardcoded tax *calculations* (Rule #2) — the band ranges here mirror the funnel's own static band keys (`upto_50270` etc.), they are funnel-data interpretation, not a tax computation, so `TaxConfigService` is not the source.
- Backend-only change; no frontend edits (Rule #19 parity is satisfied by the shared endpoint + existing quick-replies rendering).
- Branch: `savetax-income-only` (off `dev`). Work in the worktree `fynla-m-funnel` (real vendor).
- Tests: `./vendor/bin/pest <path>`. Funnel band keys (live funnel `public/pages/savetax.php`): `upto_50270`, `50271_100000`, `100001_125140`, `over_125140`; spouse adds `zero`.

---

### Task 1: `FunnelIncomeBand` band→range helper

**Files:**
- Create: `app/Services/Onboarding/FunnelIncomeBand.php`
- Test: `tests/Unit/Services/Onboarding/FunnelIncomeBandTest.php`

**Interfaces:**
- Produces:
  - `FunnelIncomeBand::isKnown(string $key): bool`
  - `FunnelIncomeBand::inBand(string $key, float $figure): bool` — true if `$figure` is within the band's inclusive range. Unknown key → `false`.
  - `FunnelIncomeBand::label(string $key): string` — human label for the challenge copy; unknown key → `''`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\Onboarding\FunnelIncomeBand;

it('knows the funnel band keys', function () {
    expect(FunnelIncomeBand::isKnown('upto_50270'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('50271_100000'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('100001_125140'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('over_125140'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('zero'))->toBeTrue()
        ->and(FunnelIncomeBand::isKnown('nonsense'))->toBeFalse();
});

it('treats a figure inside the band as in-band', function () {
    expect(FunnelIncomeBand::inBand('50271_100000', 80000))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('100001_125140', 110000))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('upto_50270', 0))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('over_125140', 200000))->toBeTrue();
});

it('treats a figure outside the band as out-of-band', function () {
    // CSJ's example: picked £100,001-£125,140, typed £50,000.
    expect(FunnelIncomeBand::inBand('100001_125140', 50000))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('upto_50270', 60000))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('over_125140', 90000))->toBeFalse();
});

it('honours inclusive band boundaries', function () {
    expect(FunnelIncomeBand::inBand('upto_50270', 50270))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('upto_50270', 50271))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('50271_100000', 50271))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('100001_125140', 125140))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('over_125140', 125141))->toBeTrue();
});

it('treats any positive figure as out-of-band for the zero spouse band', function () {
    expect(FunnelIncomeBand::inBand('zero', 0))->toBeTrue()
        ->and(FunnelIncomeBand::inBand('zero', 1))->toBeFalse()
        ->and(FunnelIncomeBand::inBand('zero', 40000))->toBeFalse();
});

it('returns false for an unknown key', function () {
    expect(FunnelIncomeBand::inBand('nonsense', 50000))->toBeFalse();
});

it('returns a human label for each band and empty for unknown', function () {
    expect(FunnelIncomeBand::label('100001_125140'))->toBe('£100,001–£125,140')
        ->and(FunnelIncomeBand::label('zero'))->toBe('no income')
        ->and(FunnelIncomeBand::label('over_125140'))->toBe('over £125,140')
        ->and(FunnelIncomeBand::label('nonsense'))->toBe('');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/FunnelIncomeBandTest.php`
Expected: FAIL — `Class "App\Services\Onboarding\FunnelIncomeBand" not found`.

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

/**
 * Maps the SaveTax funnel income band keys (public/pages/savetax.php) to their
 * numeric ranges so the onboarding director can tell when a captured income
 * figure contradicts the band the user picked on the website. Funnel-data
 * interpretation only — not a tax calculation (Rule #2 does not apply).
 */
final class FunnelIncomeBand
{
    /** key => [min, max] inclusive; null max = open-ended. 'zero' = exactly 0. */
    private const RANGES = [
        'zero' => [0.0, 0.0],
        'upto_50270' => [0.0, 50270.0],
        '50271_100000' => [50271.0, 100000.0],
        '100001_125140' => [100001.0, 125140.0],
        'over_125140' => [125141.0, null],
    ];

    private const LABELS = [
        'zero' => 'no income',
        'upto_50270' => 'up to £50,270',
        '50271_100000' => '£50,271–£100,000',
        '100001_125140' => '£100,001–£125,140',
        'over_125140' => 'over £125,140',
    ];

    public static function isKnown(string $key): bool
    {
        return array_key_exists($key, self::RANGES);
    }

    public static function inBand(string $key, float $figure): bool
    {
        if (! self::isKnown($key)) {
            return false;
        }

        [$min, $max] = self::RANGES[$key];

        return $figure >= $min && ($max === null || $figure <= $max);
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? '';
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/FunnelIncomeBandTest.php`
Expected: PASS (7 passed).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/FunnelIncomeBand.php tests/Unit/Services/Onboarding/FunnelIncomeBandTest.php
git add app/Services/Onboarding/FunnelIncomeBand.php tests/Unit/Services/Onboarding/FunnelIncomeBandTest.php
git commit -m "feat(onboarding): FunnelIncomeBand band->range helper"
```

---

### Task 2: Surface the entered spouse income in the capture result

`handleCaptureSpouseDetails` already computes the entered `annual_income` (passed to `SpouseLinkingService`) but does not return it. The director's mismatch check reads the entered figure from the capture result uniformly (same as `handleCaptureWorkDetails`, which already returns `details.annual_income`), so add it here.

**Files:**
- Modify: `app/Agents/CoordinatingAgent.php` (the `return` block of `handleCaptureSpouseDetails`, ~line 1370-1384)
- Test: `tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php` (add a case)

**Interfaces:**
- Produces: `handleCaptureSpouseDetails(...)['details']['annual_income']` — `float|null` (the entered spouse income, null when not provided).

- [ ] **Step 1: Write the failing test** (append to the existing file)

```php
it('returns the entered spouse annual_income in the capture details', function () {
    $user = User::factory()->create(['marital_status' => 'married']);
    $agent = app(\App\Agents\CoordinatingAgent::class);
    $m = new ReflectionMethod($agent, 'handleCaptureSpouseDetails');
    $m->setAccessible(true);

    $result = $m->invoke($agent, [
        'first_name' => 'Sam',
        'last_name' => 'Carter',
        'date_of_birth' => '1985-01-12',
        'email' => 'sam.spouse.' . uniqid() . '@example.com',
        'annual_income' => 0,
    ], $user);

    expect($result)->toHaveKey('details')
        ->and($result['details'])->toHaveKey('annual_income')
        ->and($result['details']['annual_income'])->toBe(0.0);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php --filter="entered spouse annual_income"`
Expected: FAIL — `Failed asserting that array has the key 'annual_income'`.

- [ ] **Step 3: Write minimal implementation**

In `handleCaptureSpouseDetails`, add `annual_income` to the returned `details` array:

```php
            'details' => [
                'family_member_id' => $result['family_member']->id,
                'spouse_user_id' => $result['spouse_user']->id,
                'created_new_user' => $result['created_new_user'],
                'already_linked' => $result['already_linked'],
                'email_sent' => $result['email_sent'],
                'first_name' => $firstName,
                'annual_income' => isset($input['annual_income']) ? (float) $input['annual_income'] : null,
            ],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php`
Expected: PASS (all in file).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Agents/CoordinatingAgent.php tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php
git add app/Agents/CoordinatingAgent.php tests/Unit/Services/Onboarding/OnboardingChatDirectorFixesTest.php
git commit -m "feat(onboarding): return entered spouse annual_income in capture details"
```

---

### Task 3: Director mismatch detection + challenge-copy builder

Add two private methods to `OnboardingChatDirector`. No flow wiring yet — tested directly via reflection.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (new private methods; add `use App\Services\Onboarding\FunnelIncomeBand;` — same namespace, so no `use` needed)
- Test: `tests/Unit/Services/Onboarding/IncomeFunnelChallengeTest.php`

**Interfaces:**
- Consumes: `FunnelIncomeBand` (Task 1); `details.annual_income` from work + spouse capture results (Task 2).
- Produces:
  - `detectIncomeFunnelMismatch(User $user, string $stateId, array $captureDetails): ?array` — returns `['field' => 'self'|'spouse', 'band' => string, 'entered' => float]` on a real mismatch, else `null`.
  - `buildIncomeChallenge(array $mismatch, User $user): string` — the challenge sentence.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;

function invokeDetect(User $user, string $stateId, array $captureDetails)
{
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'detectIncomeFunnelMismatch');
    $m->setAccessible(true);

    return $m->invoke($director, $user, $stateId, $captureDetails);
}

it('flags a user income that falls outside the funnel band', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['income' => '100001_125140'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_WORK, [
        'details' => ['annual_income' => 50000.0],
    ]);
    expect($result)->toMatchArray(['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0]);
});

it('does not flag a user income inside the funnel band', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['income' => '50271_100000'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_WORK, [
        'details' => ['annual_income' => 80000.0],
    ]);
    expect($result)->toBeNull();
});

it('never flags when the user has no funnel answers', function () {
    $user = User::factory()->create(['funnel_answers' => null]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_WORK, [
        'details' => ['annual_income' => 50000.0],
    ]);
    expect($result)->toBeNull();
});

it('flags a spouse income that contradicts the funnel spouse band', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['spouseIncome' => 'zero'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_SPOUSE, [
        'details' => ['annual_income' => 40000.0],
    ]);
    expect($result)->toMatchArray(['field' => 'spouse', 'band' => 'zero', 'entered' => 40000.0]);
});

it('skips the spouse check when no spouse income was captured', function () {
    $user = User::factory()->create([
        'funnel_answers' => ['spouseIncome' => 'zero'],
    ]);
    $result = invokeDetect($user, OnboardingStateMachine::STATE_BASE_SPOUSE, [
        'details' => ['annual_income' => null],
    ]);
    expect($result)->toBeNull();
});

it('builds a challenge sentence naming the band and the entered figure', function () {
    $user = User::factory()->create();
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'buildIncomeChallenge');
    $m->setAccessible(true);
    $text = $m->invoke($director, ['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0], $user);
    expect($text)->toContain('£100,001–£125,140')
        ->and($text)->toContain('£50,000')
        ->and($text)->toContain('tax-saving');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/IncomeFunnelChallengeTest.php`
Expected: FAIL — `detectIncomeFunnelMismatch` does not exist.

- [ ] **Step 3: Write minimal implementation** (add these private methods to `OnboardingChatDirector`)

```php
    /**
     * Cross-check a just-captured income figure against the band the user
     * picked on the SaveTax funnel. Returns the mismatch payload to challenge,
     * or null when there is nothing to challenge (no funnel band, unknown band,
     * no figure captured, or the figure is in-band).
     *
     * @return array{field: string, band: string, entered: float}|null
     */
    private function detectIncomeFunnelMismatch(User $user, string $stateId, array $captureDetails): ?array
    {
        $funnel = is_array($user->funnel_answers ?? null) ? $user->funnel_answers : [];
        if ($funnel === []) {
            return null;
        }

        if ($stateId === OnboardingStateMachine::STATE_BASE_WORK) {
            $field = 'self';
            $band = (string) ($funnel['income'] ?? '');
        } elseif ($stateId === OnboardingStateMachine::STATE_BASE_SPOUSE) {
            $field = 'spouse';
            $band = (string) ($funnel['spouseIncome'] ?? '');
        } else {
            return null;
        }

        if (! FunnelIncomeBand::isKnown($band)) {
            return null;
        }

        $enteredRaw = $captureDetails['details']['annual_income'] ?? null;
        if ($enteredRaw === null) {
            return null; // spouse income optional; user income absence handled by the income-required retry
        }
        $entered = (float) $enteredRaw;

        if (FunnelIncomeBand::inBand($band, $entered)) {
            return null;
        }

        return ['field' => $field, 'band' => $band, 'entered' => $entered];
    }

    /**
     * Plain-text challenge naming what the user told the funnel and what they
     * just entered. No icons (Rule #15); British spelling.
     */
    private function buildIncomeChallenge(array $mismatch, User $user): string
    {
        $bandLabel = FunnelIncomeBand::label($mismatch['band']);
        $entered = '£' . number_format($mismatch['entered']);

        if ($mismatch['field'] === 'spouse') {
            $whose = "your spouse's income was {$bandLabel}";
            $question = "is {$entered} right for them?";
        } else {
            $whose = "your income was {$bandLabel}";
            $question = "is {$entered} right?";
        }

        return "Earlier you told us {$whose}, but you've entered {$entered}. "
            ."That changes your tax-saving calculation — {$question}";
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/IncomeFunnelChallengeTest.php`
Expected: PASS (6 passed).

- [ ] **Step 5: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Unit/Services/Onboarding/IncomeFunnelChallengeTest.php
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Unit/Services/Onboarding/IncomeFunnelChallengeTest.php
git commit -m "feat(onboarding): income/funnel mismatch detection + challenge copy"
```

---

### Task 4: Extract `advanceFromState` (refactor — no behaviour change)

The post-capture advance tail of `handleGroupedExtractTurn` (currently lines ~1856-1911) must be reusable so the Continue branch (Task 6) can resume it. Extract it verbatim into a private method and call it. Pure refactor.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php`
- Test: existing `tests/Unit/Services/Onboarding/` + `tests/Feature/Onboarding/` (must stay green)

**Interfaces:**
- Produces: `advanceFromState(User $user, AiConversation $conversation, string $currentStateId, string $message): \Generator` — runs `getNextStateId` → save → done/terminal/normal-prompt emission. Same logic that previously lived inline.

- [ ] **Step 1: Add the new method** (paste the existing tail verbatim, parameterised)

```php
    /**
     * Advance from a just-completed capture state to the next state and emit
     * its turn. Extracted from handleGroupedExtractTurn so the income-challenge
     * Continue branch can resume the advance after the user confirms.
     */
    private function advanceFromState(User $user, AiConversation $conversation, string $currentStateId, string $message): \Generator
    {
        $nextStateId = OnboardingStateMachine::getNextStateId($currentStateId, $message, $user);

        if ($nextStateId === null) {
            yield $this->errorEvent('Onboarding state machine reached a dead end after grouped capture.');

            return;
        }

        $user->onboarding_fyn_step = $nextStateId;

        if ($nextStateId === OnboardingStateMachine::STATE_DONE) {
            yield from $this->emitDoneTurn($user, $conversation);

            return;
        }

        $user->save();

        $nextState = OnboardingStateMachine::getState($nextStateId);
        if ($nextState === null) {
            yield $this->errorEvent('Unknown next state: '.$nextStateId);

            return;
        }

        $ack = $this->buildCaptureAck($user, $currentStateId, []);
        if ($ack !== null) {
            yield ['type' => 'content', 'text' => $ack];
        }

        yield [
            'type' => 'onboarding_advance',
            'from_step' => $currentStateId,
            'to_step' => $nextStateId,
        ];

        if (($nextState['turn_type'] ?? '') === 'terminal' && ! empty($nextState['navigate_to'])) {
            yield from $this->emitTerminalNavigationTurn($user, $conversation, $nextStateId, $nextState);

            return;
        }

        yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);
    }
```

- [ ] **Step 2: Replace the inline tail in `handleGroupedExtractTurn`**

Replace the block from `$nextStateId = OnboardingStateMachine::getNextStateId(` through the final `yield from $this->emitTurnForState($user, $conversation, $nextStateId, $nextState);` (the lines after `$user->refresh();`) with:

```php
        yield from $this->advanceFromState($user, $conversation, $currentStateId, $message);
```

(Keep the preceding `recordProgress`, `flushParkedFactsForState`, and `$user->refresh();` calls in place.)

- [ ] **Step 3: Run the onboarding suites to verify no behaviour change**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding tests/Feature/Onboarding`
Expected: PASS (same counts as before the refactor; e.g. all green).

- [ ] **Step 4: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php
git add app/Services/Onboarding/OnboardingChatDirector.php
git commit -m "refactor(onboarding): extract advanceFromState from handleGroupedExtractTurn"
```

---

### Task 5: Emit the challenge on mismatch (park flag, hold the state)

Wire the detection into `handleGroupedExtractTurn`: after capture + `$user->refresh()`, on mismatch park the flag, emit the challenge `quick_replies`, and return instead of advancing.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (in `handleGroupedExtractTurn`, right before the `advanceFromState` call from Task 4)
- Test: `tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php`

**Interfaces:**
- Consumes: `detectIncomeFunnelMismatch`, `buildIncomeChallenge` (Task 3); `advanceFromState` (Task 4).
- Produces: `users.onboarding_fyn_context['pending_income_challenge'] = ['field' => string, 'band' => string, 'entered' => float]` parked on mismatch; a `quick_replies` event with bubbles `continue` / `change`.

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Onboarding\OnboardingChatDirector;
use App\Services\Onboarding\OnboardingStateMachine;

/** Drain a director generator into a list of events. */
function drain(\Generator $gen): array
{
    $events = [];
    foreach ($gen as $e) {
        $events[] = $e;
    }
    return $events;
}

it('challenges and holds base_work when the income contradicts the funnel band', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '100001_125140'],
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'annual_employment_income' => 50000,
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    // Simulate the post-capture call site directly.
    $m = new ReflectionMethod($director, 'maybeChallengeIncome');
    $m->setAccessible(true);
    $events = drain($m->invoke(
        $director, $user, $conversation,
        OnboardingStateMachine::STATE_BASE_WORK,
        ['details' => ['annual_income' => 50000.0]]
    ));

    $types = array_column($events, 'type');
    expect($types)->toContain('quick_replies');

    $qr = collect($events)->firstWhere('type', 'quick_replies');
    expect(collect($qr['bubbles'])->pluck('id')->all())->toBe(['continue', 'change']);

    $user->refresh();
    expect($user->onboarding_fyn_context['pending_income_challenge']['band'])->toBe('100001_125140')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK); // held
});

it('does not challenge when the income is in-band', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '50271_100000'],
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);
    $m = new ReflectionMethod($director, 'maybeChallengeIncome');
    $m->setAccessible(true);
    $events = drain($m->invoke(
        $director, $user, $conversation,
        OnboardingStateMachine::STATE_BASE_WORK,
        ['details' => ['annual_income' => 80000.0]]
    ));
    expect($events)->toBe([]);
    $user->refresh();
    expect($user->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php`
Expected: FAIL — `maybeChallengeIncome` does not exist.

- [ ] **Step 3: Write minimal implementation**

Add the helper that detects + emits + parks (returns true when it challenged, so the caller knows to stop):

```php
    /**
     * If the just-captured income contradicts the funnel band, park a
     * pending_income_challenge flag, emit the challenge + Continue/Change
     * bubbles, and yield nothing further. Returns true when it challenged
     * (caller must NOT advance), false otherwise.
     */
    private function maybeChallengeIncome(User $user, AiConversation $conversation, string $stateId, array $captureDetails): \Generator
    {
        $mismatch = $this->detectIncomeFunnelMismatch($user, $stateId, $captureDetails);
        if ($mismatch === null) {
            return false;
        }

        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $context['pending_income_challenge'] = $mismatch;
        $user->onboarding_fyn_context = $context;
        $user->save();

        $text = $this->buildIncomeChallenge($mismatch, $user);
        $this->saveMessage($conversation, 'assistant', $text, [
            'metadata' => ['onboarding_step' => $stateId, 'income_challenge' => true],
        ]);

        yield [
            'type' => 'quick_replies',
            'prompt_text' => $text,
            'bubbles' => [
                ['id' => 'continue', 'label' => 'Continue'],
                ['id' => 'change', 'label' => 'Change'],
            ],
        ];

        return true;
    }
```

Then, in `handleGroupedExtractTurn`, after `$user->refresh();` and before the `advanceFromState` call (Task 4), guard the advance:

```php
        $challenged = yield from $this->maybeChallengeIncome($user, $conversation, $currentStateId, $captureDetails);
        if ($challenged) {
            return;
        }

        yield from $this->advanceFromState($user, $conversation, $currentStateId, $message);
```

(`yield from` on a generator returns the generator's `return` value, so `$challenged` receives the boolean.)

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php`
Expected: PASS (2 passed).

- [ ] **Step 5: Run the onboarding suites for regressions**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding tests/Feature/Onboarding`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php
git commit -m "feat(onboarding): challenge income that contradicts the funnel band"
```

---

### Task 6: Flag-first Continue/Change handler in `handleUserMessage`

When a `pending_income_challenge` is parked, the next user turn answers it. Handle it before any normal turn routing.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (in `handleUserMessage`, after `$state` is resolved — after the `if ($state === null)` block (~line 161) and before the `campaign_verify_edit` branch (~line 177))
- Test: `tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php` (extend)

**Interfaces:**
- Consumes: `pending_income_challenge` flag (Task 5); `advanceFromState` (Task 4); `emitTurnForState` (existing).
- Produces: clears the flag on every branch; Continue → advance; Change → re-emit the current state's prompt; free-typed figure → falls through to normal capture.

- [ ] **Step 1: Write the failing tests** (append)

```php
it('advances when the user taps Continue on the income challenge', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '100001_125140'],
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'annual_employment_income' => 50000,
        'onboarding_fyn_context' => ['pending_income_challenge' => ['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0]],
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $events = drain($director->handleUserMessage($user, $conversation, 'Continue'));
    $user->refresh();

    expect($user->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull()
        ->and($user->onboarding_fyn_step)->not->toBe(OnboardingStateMachine::STATE_BASE_WORK); // advanced
});

it('re-asks the income question when the user taps Change', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'funnel_answers' => ['income' => '100001_125140'],
        'onboarding_completed' => false,
        'onboarding_fyn_step' => OnboardingStateMachine::STATE_BASE_WORK,
        'annual_employment_income' => 50000,
        'onboarding_fyn_context' => ['pending_income_challenge' => ['field' => 'self', 'band' => '100001_125140', 'entered' => 50000.0]],
    ]);
    $conversation = AiConversation::factory()->create(['user_id' => $user->id]);
    $director = app(OnboardingChatDirector::class);

    $events = drain($director->handleUserMessage($user, $conversation, 'Change'));
    $user->refresh();

    $content = collect($events)->where('type', 'content')->pluck('text')->implode(' ');
    expect($user->onboarding_fyn_context['pending_income_challenge'] ?? null)->toBeNull()
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_BASE_WORK) // held for re-ask
        ->and(strtolower($content))->toContain('income');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/pest tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php`
Expected: FAIL — Continue advances incorrectly / flag not cleared (no handler yet).

- [ ] **Step 3: Write minimal implementation**

In `handleUserMessage`, immediately after the `$state === null` guard (~line 161), insert:

```php
        // Income-challenge resolution (pending_income_challenge parked by
        // maybeChallengeIncome). The user is answering "is X right?" — handle
        // it before any normal turn routing.
        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        if (isset($context['pending_income_challenge'])) {
            $reply = mb_strtolower(trim($message));

            // Clear the flag on every branch — Continue always ends the loop.
            unset($context['pending_income_challenge']);
            $user->onboarding_fyn_context = $context;
            $user->save();

            if ($reply === 'continue') {
                yield from $this->advanceFromState($user, $conversation, $currentStateId, $message);

                return;
            }

            if ($reply === 'change') {
                yield from $this->emitTurnForState($user, $conversation, $currentStateId, $state);

                return;
            }
            // Anything else: the user typed a new figure instead of tapping —
            // fall through to the normal capture path below, which re-captures
            // and re-runs the challenge check.
        }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/pest tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php`
Expected: PASS (4 passed).

- [ ] **Step 5: Full onboarding + agents + Fyn regression run**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding tests/Feature/Onboarding tests/Unit/Agents tests/Feature/Fyn`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Onboarding/IncomeFunnelChallengeFlowTest.php
git commit -m "feat(onboarding): resolve income challenge — Continue advances, Change re-asks"
```

---

### Task 7: Browser verification on csjones (web + /m)

No code — live end-to-end verification per the project's browser-testing law. Deploy the branch to csjones first (feature-branch deploy gate).

**Files:** none.

- [ ] **Step 1: Deploy the branch to csjones**

```bash
cd /Users/CSJ/Desktop/fynla-m-funnel
git push origin savetax-income-only
ssh -i ~/.ssh/fynlaDev -p 18765 u163-ptanegf9edny@ssh.csjones.co 'cd ~/www/csjones.co/fynla-app && git fetch origin -q && git checkout savetax-income-only && git pull origin savetax-income-only -q'
# No frontend change in Tasks 1-6, but rebuild+upload is harmless if unsure:
./deploy/csjones-fynla/build.sh
rsync -az -e "ssh -i ~/.ssh/fynlaDev -p 18765" public/build/ u163-ptanegf9edny@ssh.csjones.co:www/csjones.co/fynla-app/public/build/
rsync -az -e "ssh -i ~/.ssh/fynlaDev -p 18765" public/m-build/ u163-ptanegf9edny@ssh.csjones.co:www/csjones.co/fynla-app/public/m-build/
ssh -i ~/.ssh/fynlaDev -p 18765 u163-ptanegf9edny@ssh.csjones.co 'cd ~/www/csjones.co/fynla-app && php artisan cache:clear && php artisan route:clear && php artisan config:clear && php artisan view:clear && php artisan config:cache'
```

- [ ] **Step 2: Verify the USER income challenge (web)**

Register a fresh SaveTax campaign user via the funnel (`https://csjones.co/fynla/savetax`) picking income band **£100,001–£125,140**. Complete MFA (fetch the code via SSH tinker on `EmailVerificationCode`). In the Fyn dock, at the income question type **50000**.
Expected: Fyn replies with a challenge naming "£100,001–£125,140" and "£50,000" + **Continue** / **Change** bubbles; the flow does NOT advance.

- [ ] **Step 3: Verify Change then Continue**

Tap **Change** → Fyn re-asks for income. Type **50000** again → challenge re-appears. Tap **Continue** → flow advances (next onboarding question). Confirm in DB: `users.annual_employment_income = 50000`, `onboarding_fyn_context` no longer has `pending_income_challenge`.

- [ ] **Step 4: Verify no false challenge**

With a second fresh funnel user (band **£50,271–£100,000**), type **80000** → NO challenge, flow advances normally.

- [ ] **Step 5: Verify on /m**

Repeat Step 2 on `/m`: log in at `/fynla/m/app/login` + MFA, drive the dock to the income question, type a contradicting figure → challenge + Continue/Change bubbles render in the dock.

- [ ] **Step 6: Verify the SPOUSE income challenge**

Use a married funnel user whose funnel `spouseIncome = zero` (No income). At the spouse step, enter a spouse with a positive income (e.g. £40,000) → spouse challenge fires. Confirm Continue/Change both work.

- [ ] **Step 7: Record results**

Note pass/fail with screenshots in the session handover (`June/June29Updates/`). If any step is RED, return to the relevant task, fix, re-deploy, re-verify (Rule #14 — loop until green).

---

## Self-Review

- **Spec coverage:** band map (Task 1), user income trigger at `base_work` (Tasks 3/5), spouse income trigger at `base_spouse` (Tasks 2/3/5), skip conditions (Task 3), challenge + Continue/Change (Tasks 5/6), no-loop via always-clear flag (Task 6), surfaces web + `/m` (Task 7), testing at every task. ✓
- **Placeholder scan:** none — every step has concrete code/commands.
- **Type consistency:** `detectIncomeFunnelMismatch` returns `['field','band','entered']` used by `buildIncomeChallenge`, `maybeChallengeIncome`, and the flag; `advanceFromState(User, AiConversation, string, string)` signature consistent across Tasks 4/5/6; bubble ids `continue`/`change` consistent in Tasks 5/6. ✓
- **Note for implementer:** the exact line numbers (1856-1911, ~161, ~177) drift as edits land — match on the surrounding code shown, not the numbers.
