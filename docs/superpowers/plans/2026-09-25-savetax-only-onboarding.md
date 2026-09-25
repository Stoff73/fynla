# Save Tax–Only Onboarding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (CSJ ruling 2026-09-24: plans run inline, no implementation subagents). Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Every new user, on every surface, onboards through the Save Tax campaign. A user who has not answered the Save Tax funnel questions is greeted by Fyn and asked them in chat first. Every other onboarding route stays in the code but cannot be reached.

**Architecture:** One config switch (`onboarding.forced_campaign`, default `savetax`) makes `AiChatController::startOnboarding` resolve the Save Tax campaign regardless of `from`, funnel campaign or journey. Four new bubble states in `OnboardingStateMachine` (`campaign_funnel_employment`, `campaign_funnel_income`, `campaign_funnel_spouse`, `campaign_funnel_spouse_income`, `campaign_funnel_assets`) ask the funnel questions, write `users.funnel_answers` in the exact funnel vocabulary, then run the existing `FunnelAnswersMapper::mapToProfile` and hand off to the normal Save Tax entry. This is all server-side, so web, `/m` and native get it from one place (Rule 20). The web-only legacy wizard is closed by a router redirect and the Register.vue fallback.

**Amendment (CSJ 2026-09-25, plan approved):** the income-band question is DROPPED. `STATE_CAMPAIGN_FUNNEL_INCOME` is not built, `FUNNEL_STATES` has no `income` key, and `firstMissingFunnelState` never requires `income`. Wherever a task below taps an income bubble or expects `campaign_funnel_income`, skip that tap or expect the next state (`campaign_funnel_spouse`). The spouse-income bubbles are still built from `TaxConfigService`.

**Tech Stack:** Laravel 10, Pest, Vue 3 router, the `fyn-memory` onboarding corpus.

**Spec:** CSJ's instruction of 2026-09-25 (item 5), with the answer "Greet, then ask funnel Qs". Context: `September/September25Updates/savetax-outcomes-by-household-2026-09-25.md`.

## Global Constraints

- Do not delete, remove or amend the other onboarding routes (path_choice, journeys, focus, pensioncheck, the web wizard). Make them unreachable only. Setting `onboarding.forced_campaign` to `null` must restore today's behaviour exactly.
- Rule 20: one change in one place for all surfaces. No client-side copy of the new questions.
- Rule 15: no emoji or Unicode glyphs in any prompt or bubble label.
- Rule 9: no cold acronyms. Rule 2: no hardcoded tax values in prompts. The income-band labels in the funnel are figures, so build them from `TaxConfigService`, not literals.
- British user-facing spelling.
- Preview users stay excluded (403 at `startOnboarding`).
- Users already mid-flow (`onboarding_fyn_step` set) keep resuming where they are, including anyone mid-Pension Check.

## Review Focus

1. **A user with a partial funnel** (for example employment set but no assets) should be asked only the missing questions. Pinned in Task 3.
2. **"Start over" on the welcome-back bubble** must land on Save Tax, not the journey chooser. Pinned in Task 4.
3. **A resumed conversation** must not repeat the greeting (the `stateTurnAlreadyDelivered` guard). Pinned in Task 3.
4. **A completed user with `from=pensioncheck`** must get 409, not a Pension Check re-entry. Pinned in Task 1.
5. **Tapping "That's everything" on assets with nothing picked** writes `assets: []`, and the walk still proceeds to work/income. Pinned in Task 3.

---

### Task 1: Force the Save Tax campaign at the start endpoint

**Files:**
- Modify: `config/onboarding.php` (add `forced_campaign`)
- Modify: `app/Http/Controllers/Api/AiChatController.php:641-827`
- Test: `tests/Feature/AI/ForcedSavetaxCampaignTest.php` (new)
- Modify: `tests/Feature/AI/OnboardingStartCampaignMapTest.php`, `tests/Feature/Onboarding/EntrySourceCampaignMapTest.php`, `EntrySourceJourneyMapTest.php`, `PathChoiceHasAWayOutTest.php`, `StartOnboardingEndpointTest.php`, and any other suite asserting journey, path_choice or pensioncheck routing: add `config()->set('onboarding.forced_campaign', null);` to their `beforeEach` so they keep pinning the dormant routes.

**Interfaces:**
- Produces: `config('onboarding.forced_campaign')`, a `?string` campaign-map key. Also `OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT` (defined in Task 2 and referenced here as the entry for a user without funnel answers).

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserConsent;
use App\Services\GDPR\ConsentService;
use App\Services\Onboarding\OnboardingStateMachine;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    OnboardingStateMachine::flushTransitionTableCache();
});

function startAs(User $user, array $body = [])
{
    app(ConsentService::class)->recordConsent($user, UserConsent::TYPE_AI_CHAT, true);

    return test()->withToken($user->createToken('t')->plainTextToken)
        ->postJson('/api/ai-chat/onboarding/start', $body);
}

function freshUser(array $attrs = []): User
{
    return User::factory()->create(array_merge([
        'is_preview_user' => false, 'onboarding_completed' => false,
        'onboarding_fyn_step' => null, 'onboarding_fyn_path' => null,
        'onboarding_fyn_selection' => null, 'funnel_answers' => null,
    ], $attrs));
}

it('sends a user with no from and no funnel answers into the Save Tax funnel questions', function () {
    $user = freshUser();
    startAs($user)->assertOk();
    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign')
        ->and($user->onboarding_fyn_selection)->toBe('savetax')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT);
});

it('ignores a journey from value', function () {
    $user = freshUser();
    startAs($user, ['from' => 'retirement'])->assertOk();
    expect($user->refresh()->onboarding_fyn_selection)->toBe('savetax');
});

it('sends a pensioncheck funnel user into Save Tax', function () {
    $user = freshUser(['funnel_answers' => ['campaign' => 'pensioncheck', 'employment' => 'full-time']]);
    startAs($user)->assertOk();
    $user->refresh();
    expect($user->onboarding_fyn_selection)->toBe('savetax')
        ->and($user->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_INCOME);
});

it('keeps a fully funnelled savetax user on the existing base_work entry', function () {
    $user = freshUser(['funnel_answers' => [
        'campaign' => 'savetax', 'employment' => 'full-time', 'income' => '50271_100000',
        'spouse' => 'no', 'assets' => ['bank'],
    ], 'employment_status' => 'full_time']);
    startAs($user)->assertOk();
    expect($user->refresh()->onboarding_fyn_step)->toBe('base_work');
});

it('refuses Pension Check re-entry for a completed user', function () {
    $user = freshUser(['onboarding_completed' => true]);
    startAs($user, ['from' => 'pensioncheck'])->assertStatus(409);
});

it('restores the old routing when forced_campaign is null', function () {
    config()->set('onboarding.forced_campaign', null);
    $user = freshUser();
    startAs($user)->assertOk();
    expect($user->refresh()->onboarding_fyn_step)->toBe(OnboardingStateMachine::STATE_PATH_CHOICE);
});
```

- [ ] **Step 2: Run it and check it fails**

Run: `./vendor/bin/pest tests/Feature/AI/ForcedSavetaxCampaignTest.php`
Expected: FAIL, because `STATE_CAMPAIGN_FUNNEL_EMPLOYMENT` is undefined. Task 2 defines it. Do Task 2 Step 3 (the constants) first if running strictly in order.

- [ ] **Step 3: Implement**

In `config/onboarding.php`, add this beside `campaign_map`:

```php
    // CSJ 2026-09-25: every onboarding goes through Save Tax. The other
    // entry routes (path_choice, journeys, focus, pensioncheck, the web
    // wizard) stay in the code but are unreachable while this is set.
    // null restores the from= / funnel / journey routing exactly.
    'forced_campaign' => env('ONBOARDING_FORCED_CAMPAIGN', 'savetax'),
```

In `AiChatController::startOnboarding`:
- After `$campaignMap` is read (line 642), add `$forced = config('onboarding.forced_campaign');` and `$forcedEntry = is_string($forced) ? ($campaignMap[$forced] ?? null) : null;`.
- Change the `$reentryCampaign` derivation (643-646) so that when `$forcedEntry !== null`, only `$forcedEntry` can be a re-entry campaign. Savetax has `reentry: false`, so a completed user always gets 409.
- Apply the same guard to the paused-campaign block (668-670) and the active_campaign block (676-682): skip them when `$forcedEntry !== null` and the campaign differs from `$forced`.
- Keep the mid-flow resume (712-735) untouched.
- Replace lines 757-780 with:

```php
        if ($forcedEntry !== null) {
            $campaignEntry = $forcedEntry;
            $matchedCampaign = $forcedEntry['selection'];
            $matchedJourney = null;
        } else {
            // existing lines 757-780 unchanged, indented one level
        }
```

- Inside `if ($matchedCampaign !== null)`, after the pause-resume block and before the retired diversion, add:

```php
            // A user who has not answered the Save Tax funnel questions is
            // greeted and asked them first (CSJ 2026-09-25).
            $missing = OnboardingStateMachine::firstMissingFunnelState($user);
            if ($forcedEntry !== null && $missing !== null && $stepId === $campaignEntry['entry']) {
                $stepId = $missing;
            }
```

- [ ] **Step 4: Update the characterisation suites.** Add `config()->set('onboarding.forced_campaign', null);` to the `beforeEach` of every suite listed under Files. To find them, run `./vendor/bin/pest tests/Feature/AI tests/Feature/Onboarding` after Task 2 and read each red test: a red from path_choice, journey or pensioncheck expectations gets the config line, and anything else is a real regression to fix.

- [ ] **Step 5: Run the tests**

Run: `./vendor/bin/pest tests/Feature/AI/ForcedSavetaxCampaignTest.php tests/Feature/AI tests/Feature/Onboarding`
Expected: PASS.

- [ ] **Step 6: Commit** with `feat(onboarding): force the Save Tax campaign for every new user`.

---

### Task 2: Funnel-question states (greeting plus the four questions)

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` (constants near :120; `inCodeStates()` entries; new `next`, `prompt` and skip helpers; `firstMissingFunnelState`)
- Modify: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` (the data subset for the five states)
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php` (`persistCapture`: write funnel answers; `filterBubbles`: hide already-picked assets)
- Test: `tests/Feature/Onboarding/SavetaxFunnelQuestionsTest.php` (new)

**Interfaces:**
- Produces:
  - `OnboardingStateMachine::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT = 'campaign_funnel_employment'`, and likewise `_INCOME`, `_SPOUSE`, `_SPOUSE_INCOME` and `_ASSETS` (values in snake_case).
  - `OnboardingStateMachine::firstMissingFunnelState(User $user): ?string` returns the first unanswered state in the order employment, income, spouse, spouse_income (only when spouse is `yes`), assets. It returns null when everything is answered.
  - `OnboardingStateMachine::FUNNEL_STATES`, a `const array<string,string>` mapping state id to funnel key (`employment`, `income`, `spouse`, `spouseIncome`, `assets`).

- [ ] **Step 1: Write the failing test.** Drive the director through `POST /api/ai-chat/conversations/{id}/actions` with bubble ids, following the pattern in `tests/Feature/Onboarding/StateMachineWalkthroughTest.php`.

```php
it('greets once, then asks employment, income, spouse, spouse income and assets, then maps and enters base_work', function () {
    $user = freshFunnelUser(); // helper: forced campaign, consent recorded, no funnel_answers
    $first = startAndCollect($user); // helper: returns the SSE text of the first turn
    expect($first)->toContain("I'm Fyn")->toContain('employment');

    tapBubble($user, 'full-time');
    expect($user->refresh()->onboarding_fyn_step)->toBe('campaign_funnel_income');
    tapBubble($user, '50271_100000');
    tapBubble($user, 'yes');
    expect($user->refresh()->onboarding_fyn_step)->toBe('campaign_funnel_spouse_income');
    tapBubble($user, 'zero');
    tapBubble($user, 'bank');
    tapBubble($user, 'pension');
    tapBubble($user, 'done');

    $user->refresh();
    expect($user->funnel_answers)->toMatchArray([
        'campaign' => 'savetax', 'employment' => 'full-time', 'income' => '50271_100000',
        'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => ['bank', 'pension'],
    ])
        ->and($user->employment_status)->toBe('full_time')
        ->and($user->marital_status)->toBe('married')
        ->and($user->household_calculation_mode)->toBe('single_earner_couple')
        ->and($user->onboarding_fyn_step)->toBe('base_work');
});

it('skips spouse income when there is no spouse', function () {
    $user = freshFunnelUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'income' => 'upto_50270']]);
    startAndCollect($user);
    tapBubble($user, 'no');
    expect($user->refresh()->onboarding_fyn_step)->toBe('campaign_funnel_assets');
});

it('sends a retired user past work to the retirement-date step after the questions', function () {
    $user = freshFunnelUser();
    startAndCollect($user);
    foreach (['retired', 'upto_50270', 'no', 'done'] as $b) { tapBubble($user, $b); }
    expect($user->refresh()->onboarding_fyn_step)
        ->toBe(OnboardingStateMachine::nextFromEmployment('', $user));
});

it('writes an empty asset list when done is tapped first', function () {
    $user = freshFunnelUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'income' => 'upto_50270', 'spouse' => 'no']]);
    startAndCollect($user);
    tapBubble($user, 'done');
    expect($user->refresh()->funnel_answers['assets'])->toBe([])
        ->and($user->onboarding_fyn_step)->toBe('base_work');
});

it('does not repeat the greeting on resume', function () {
    $user = freshFunnelUser();
    startAndCollect($user);
    $again = resumeAndCollect($user); // helper: posts the welcome-back 'continue' action
    expect($again)->not->toContain("I'm Fyn");
});

it('hides assets already picked', function () {
    $user = freshFunnelUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'income' => 'upto_50270', 'spouse' => 'no']]);
    startAndCollect($user);
    $turn = tapBubbleAndCollect($user, 'isa');
    expect($turn['bubbles'])->not->toContain('isa')->toContain('done');
});
```

Write the four helpers (`freshFunnelUser`, `startAndCollect`, `tapBubble`/`tapBubbleAndCollect`, `resumeAndCollect`) at the top of the file. Copy the SSE-parsing helper already used in `StateMachineWalkthroughTest.php`; don't invent a new one.

- [ ] **Step 2: Run it and check it fails.** Run `./vendor/bin/pest tests/Feature/Onboarding/SavetaxFunnelQuestionsTest.php`. Expected: FAIL, unknown state.

- [ ] **Step 3: Implement the states.** Add the constants and `FUNNEL_STATES`. Add the in-code entries:

```php
            self::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT => [
                'prompt_text' => self::class.'::buildFunnelEmploymentPrompt',
                'reprompt_text' => "What's your employment situation at the moment?",
                'next' => self::class.'::nextFromFunnelQuestion',
            ],
            self::STATE_CAMPAIGN_FUNNEL_INCOME => [
                'prompt_text' => self::class.'::buildFunnelIncomePrompt',
                'next' => self::class.'::nextFromFunnelQuestion',
            ],
            self::STATE_CAMPAIGN_FUNNEL_SPOUSE => ['next' => self::class.'::nextFromFunnelQuestion'],
            self::STATE_CAMPAIGN_FUNNEL_SPOUSE_INCOME => [
                'prompt_text' => self::class.'::buildFunnelSpouseIncomePrompt',
                'next' => self::class.'::nextFromFunnelQuestion',
            ],
            self::STATE_CAMPAIGN_FUNNEL_ASSETS => ['next' => self::class.'::nextFromFunnelAssets'],
```

Add the helpers:

```php
    public const FUNNEL_STATES = [
        self::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT => 'employment',
        self::STATE_CAMPAIGN_FUNNEL_INCOME => 'income',
        self::STATE_CAMPAIGN_FUNNEL_SPOUSE => 'spouse',
        self::STATE_CAMPAIGN_FUNNEL_SPOUSE_INCOME => 'spouseIncome',
        self::STATE_CAMPAIGN_FUNNEL_ASSETS => 'assets',
    ];

    public static function firstMissingFunnelState(User $user): ?string
    {
        $funnel = is_array($user->funnel_answers) ? $user->funnel_answers : [];
        foreach (self::FUNNEL_STATES as $state => $key) {
            if ($key === 'spouseIncome' && ($funnel['spouse'] ?? null) !== 'yes') {
                continue;
            }
            if (! array_key_exists($key, $funnel)) {
                return $state;
            }
        }

        return null;
    }

    public static function nextFromFunnelQuestion(string $answer, User $user): string
    {
        return self::firstMissingFunnelState($user) ?? self::leaveFunnelQuestions($user);
    }

    public static function nextFromFunnelAssets(string $answer, User $user): string
    {
        return $answer === 'done'
            ? self::leaveFunnelQuestions($user)
            : self::STATE_CAMPAIGN_FUNNEL_ASSETS;
    }

    /** Map the answers onto the profile exactly as registration does, then enter Save Tax. */
    private static function leaveFunnelQuestions(User $user): string
    {
        app(\App\Services\Auth\FunnelAnswersMapper::class)->mapToProfile($user);
        $user->refresh();
        $entry = (string) config('onboarding.campaign_map.savetax.entry');
        if ($entry === self::STATE_BASE_WORK && ! empty($user->employment_status)
            && ! in_array($user->employment_status, [...self::WORKPLACE_PENSION_STATUSES, 'self_employed'], true)) {
            return self::nextFromEmployment('', $user);
        }

        return $entry;
    }
```

Note: the retired diversion rule is now in two places (the controller and `leaveFunnelQuestions`). Consolidate it into one `public static function campaignEntryFor(User $user, string $entry): string` in the state machine and call it from both (Rule 20).

The prompt builders:
- `buildFunnelEmploymentPrompt` returns `"Hi {$firstName}, I'm Fyn. I'll help you find where you could be saving tax. First, a few quick questions. **What's your employment situation at the moment?**"` unless `stateTurnAlreadyDelivered($conversation, self::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT)`, in which case it returns the reprompt.
- `buildFunnelIncomePrompt` returns `'**Roughly what do you earn a year before tax?**'`.
- `buildFunnelSpouseIncomePrompt` returns `'**And roughly what does your spouse earn a year?**'`.

Band labels are dynamic, so the bubbles for income and spouse income come from a builder that reads `TaxConfigService` (higher-rate threshold, Personal Allowance taper threshold, additional-rate threshold). Generate them in `OnboardingChatDirector::filterBubbles` for these two states, in the same order and with the same ids as the funnel: `upto_50270`, `50271_100000`, `100001_125140`, `over_125140`, and `zero` first for the spouse. Label them like "Up to £50,270", with each figure formatted from config.

- [ ] **Step 4: Add the corpus data** to `fyn-onboarding.v1.md`:

```yaml
campaign_funnel_employment:
  turn_type: bubbles
  prompt_text: { builder: buildFunnelEmploymentPrompt }
  bubbles:
    - { id: full-time, label: Full-time }
    - { id: part-time, label: Part-time }
    - { id: self-employed, label: Self-employed }
    - { id: retired, label: Retired }
    - { id: not-employed, label: 'Not working' }
  capture_field: null
  next: { branch: nextFromFunnelQuestion }

campaign_funnel_income:
  turn_type: bubbles
  prompt_text: { builder: buildFunnelIncomePrompt }
  bubbles: []   # built from TaxConfigService in OnboardingChatDirector::filterBubbles
  capture_field: null
  next: { branch: nextFromFunnelQuestion }

campaign_funnel_spouse:
  turn_type: bubbles
  prompt_text: '**Do you have a spouse or civil partner?**'
  bubbles:
    - { id: 'yes', label: 'Yes' }
    - { id: 'no', label: 'No' }
  capture_field: null
  next: { branch: nextFromFunnelQuestion }

campaign_funnel_spouse_income:
  turn_type: bubbles
  prompt_text: { builder: buildFunnelSpouseIncomePrompt }
  bubbles: []   # built from TaxConfigService, 'zero' first
  capture_field: null
  next: { branch: nextFromFunnelQuestion }

campaign_funnel_assets:
  turn_type: bubbles
  prompt_text: '**Which of these do you have?** Tap each one, then "That''s everything".'
  bubbles:
    - { id: bank, label: 'Bank account' }
    - { id: savings, label: 'Savings account' }
    - { id: isa, label: ISA }
    - { id: pension, label: Pension }
    - { id: investments, label: Investments }
    - { id: property, label: Property }
    - { id: done, label: "That's everything" }
  capture_field: null
  next: { branch: nextFromFunnelAssets }
```

The spouse question wording "spouse or civil partner" is deliberate. It is the legal test for Marriage Allowance and spouse transfers (see bug B8 in the outcomes file). It changes only the chat question, not the public funnel page.

- [ ] **Step 5: Persist the answers.** In `OnboardingChatDirector::persistCapture`, before the `$captureField === null` return, add:

```php
        if (isset(OnboardingStateMachine::FUNNEL_STATES[$stateId]) && is_string($capturedValue) && $capturedValue !== '') {
            $key = OnboardingStateMachine::FUNNEL_STATES[$stateId];
            $funnel = is_array($user->funnel_answers) ? $user->funnel_answers : [];
            $funnel['campaign'] = 'savetax';
            if ($key === 'assets') {
                $assets = (array) ($funnel['assets'] ?? []);
                if ($capturedValue !== 'done' && ! in_array($capturedValue, $assets, true)) {
                    $assets[] = $capturedValue;
                }
                $funnel['assets'] = $assets;
            } else {
                $funnel[$key] = $capturedValue;
            }
            $user->funnel_answers = $funnel;
            $user->save();

            return;
        }
```

In `filterBubbles`, for `STATE_CAMPAIGN_FUNNEL_ASSETS`, drop ids already in `funnel_answers.assets`, keeping `done` last, the same shape as the `add_more` branch.

- [ ] **Step 6: Run the tests.** Run `./vendor/bin/pest tests/Feature/Onboarding/SavetaxFunnelQuestionsTest.php tests/Feature/AI/ForcedSavetaxCampaignTest.php`. Expected: PASS.

- [ ] **Step 7: Run the corpus and architecture guards.** Run `./vendor/bin/pest tests/Feature/Onboarding tests/Unit/Onboarding tests/Architecture`. Expected: PASS. The corpus-vs-code state parity test catches a state missing from either side.

- [ ] **Step 8: Commit** with `feat(onboarding): Fyn asks the Save Tax funnel questions when they are missing`.

---

### Task 3: Partial funnels and the base_work recap

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php` (`buildFunnelRecapPrompt` :1726, `workFunnelRecap` :1841)
- Test: `tests/Feature/Onboarding/SavetaxFunnelQuestionsTest.php` (add cases)

The chat has just asked these questions, so the base_work "thanks for those answers" recap should not greet a second time.

- [ ] **Step 1: Write the failing test**

```php
it('does not greet twice after the chat funnel questions', function () {
    $user = freshFunnelUser();
    startAndCollect($user);
    foreach (['full-time', 'upto_50270', 'no'] as $b) { tapBubble($user, $b); }
    $turn = tapBubbleAndCollect($user, 'done');
    expect($turn['text'])->not->toContain("I'm Fyn")->toContain('income');
});

it('asks only the missing question for a partial funnel', function () {
    $user = freshFunnelUser(['funnel_answers' => ['campaign' => 'savetax', 'employment' => 'full-time', 'income' => 'upto_50270', 'spouse' => 'no']]);
    startAndCollect($user);
    expect($user->refresh()->onboarding_fyn_step)->toBe('campaign_funnel_assets');
});
```

- [ ] **Step 2: Run it and check it fails.** Expected: the first case FAILs because the recap greets again.

- [ ] **Step 3: Implement.** In `workFunnelRecap` and the matching branch in `buildWorkPrompt`, return the short form lead-in when `stateTurnAlreadyDelivered($conversation, self::STATE_CAMPAIGN_FUNNEL_EMPLOYMENT)` is true. The user has already been greeted in this conversation.

- [ ] **Step 4: Run it and check it passes. Step 5: Commit** with `fix(onboarding): one greeting per Save Tax walk`.

---

### Task 4: Close the other doors

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php:847-866` (`handleRestartAction`) and `:166-178` (`emitFirstTurn` default)
- Modify: `resources/js/views/Auth/Register.vue:534-542`
- Modify: `resources/js/router/index.js:454-527`
- Test: `tests/Feature/Onboarding/OnboardingResumeTest.php` (add a case); `resources/js/router/__tests__/` or the nearest existing Vitest router test (add a case)

- [ ] **Step 1: Write the failing backend test**

```php
it('Start over lands on Save Tax, not the journey chooser', function () {
    $user = midWalkSavetaxUser(); // step = campaign_savings or any campaign step
    postAction($user, 'restart');
    $user->refresh();
    expect($user->onboarding_fyn_path)->toBe('campaign')
        ->and($user->onboarding_fyn_selection)->toBe('savetax')
        ->and($user->onboarding_fyn_step)->toBeIn(['base_work', 'campaign_funnel_employment']);
});
```

- [ ] **Step 2: Run it and check it fails.**

- [ ] **Step 3: Implement.** In `handleRestartAction`, when `config('onboarding.forced_campaign')` is set, reset to path `campaign`, the forced selection, and step `OnboardingStateMachine::firstMissingFunnelState($user) ?? campaignEntryFor($user, entry)` instead of `STATE_PATH_CHOICE`. Keep the null-config branch as it is today. In `emitFirstTurn`, when no state is passed and the config is set, default to the same resolution.

- [ ] **Step 4: Web.** In `Register.vue`, when `forced_campaign` is on (expose it as `onboarding_forced_campaign` on `UserResource`, next to `onboarding_campaign` at :59), always take the `Dashboard?openFyn=journey&from=<forced>` branch. That replaces the `?stage=` and `Onboarding?newUser=1` branches. In `router/index.js`, give the wizard routes (`/onboarding/welcome`, `/onboarding/journey/:journey`, `/onboarding/:step?`, `/onboarding/full`, and the module variants) a `beforeEnter` that redirects to `{ name: 'Dashboard', query: { openFyn: 'journey', from: 'savetax' } }` while the user's `onboarding_forced_campaign` is set. The routes and components stay.

- [ ] **Step 5: Run the tests.** Run `./vendor/bin/pest tests/Feature/Onboarding` and `npx vitest run resources/js/router`. Expected: PASS.

- [ ] **Step 6: Commit** with `feat(onboarding): route restart, first turn and the web wizard to Save Tax`.

---

### Task 5: Live verification (Rule 14, Rule 19)

- [ ] Run `php artisan db:seed` if any local data was lost, then `./dev.sh`.
- [ ] **Web:** register a new user at `localhost:8000/register` directly (no funnel). Fyn greets and asks employment. Answer all five and check the walk continues into income. Tap "Start over" on the welcome-back bubble after a reload, and check it returns to Save Tax. Visit `/onboarding` and check it redirects to the dashboard with Fyn open.
- [ ] **Web, funnel user:** complete `localhost:8000/savetax`, then register. Check there are no funnel questions and the walk opens on the income recap as today.
- [ ] **`/m`:** follow the `verify-m` skill. A fresh unfunnelled user gets the same greeting and questions in the `/m` dock.
- [ ] **iOS:** `I COULD NOT TEST THIS` unless the simulator is free. The change is server-side only, and the native client renders bubbles from the same SSE contract. If the simulator is available, use the `ios-simulator` skill against a local build.
- [ ] Record the evidence (screenshots and DB rows for `funnel_answers`) in `tests/Persona/savetax-only-onboarding/reports/2026-09-25.md`.
- [ ] Open a PR to `dev`.

---

## Resolved

1. Income-band question dropped (CSJ 2026-09-25). See the Amendment at the top.
