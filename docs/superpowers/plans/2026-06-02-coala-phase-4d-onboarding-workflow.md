# CoALA Phase 4d — Onboarding Workflow Transition Table → `.md` (Implementation Plan)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Externalise the **pure-data subset** of the Fyn onboarding state-machine transition table out of `OnboardingStateMachine::states()` into a git-tracked `workflow`-kind procedure `.md`, consumed via the 4a `ProceduralCorpusLoader`. The PHP code (branching `next` callables, `prompt_text` builders, `skip_if` predicates, all helper bodies) stays byte-identical in PHP and is re-attached by state id. The merged table MUST deep-equal the in-code table — proven by a golden-master test that is the phase's hard gate. When the corpus procedure is absent/malformed, the machine falls back to the in-code table. Zero behaviour change in both modes.

**Architecture:** Mirrors the 4b consumption pattern (`AiToolDefinitions::toolFromCorpus` strips a fenced block from `Procedure->body` and decodes it) and the 4a degrade discipline (`ProceduralCorpusLoader::load()` never throws). A new pure reader `OnboardingWorkflowTable::fromProcedure(Procedure): ?array` parses a single fenced ```` ```yaml ```` block into the extracted-subset table, returning `null` on any error. A new static `OnboardingStateMachine::transitionTable(): array` resolves the active `onboarding.workflow.fyn-onboarding` procedure, parses it, and **merges** the corpus data over the in-code base (corpus wins on DATA fields; PHP-only fields always from code), falling back to the in-code table on any fault. `states()` / `getState()` route through `transitionTable()`; no consumer signature changes.

**Tech Stack:** PHP 8.2, Laravel 10, Symfony YAML, Pest. No new dependencies. 4a substrate already on branch.

**Branch:** `feat/coala-4d-onboarding-workflow`

**Spec:** `docs/superpowers/specs/2026-06-02-coala-phase-4d-onboarding-workflow-design.md`

---

## Extraction boundary (authoritative for this plan)

Per state, exactly these keys are **PHP-only** and are NEVER read from the corpus — they are always re-attached from the in-code table by state id:

- `next` — **only when its value is a callable reference** (a string containing `::`, e.g. `App\Services\Onboarding\OnboardingStateMachine::nextFromPathChoice`). When `next` is a **static string state id** (e.g. `'base_personal'`) it is a DATA field and moves to the `.md`.
- `prompt_text` — **only when its value is a callable reference** (string containing `::`). When `prompt_text` is a **plain literal string** it is a DATA field and moves to the `.md` (verbatim, including the runtime-resolved `JourneyFieldResolver::getFynPrompt('monthly_expenditure')` string for `base_expenditure`, which evaluates to a literal at array-build time).
- `skip_if` — always an array callable `[self::class, 'method']`; never represented in the `.md`. Re-attached from code.

Every other key is DATA and moves to the `.md`: `turn_type`, `bubbles` (array of `{id,label}` + optional `description`), `capture_field` (string or `null`), `value_parser`, `extraction_tool`, `retry_text`, `layout`, `navigate_to`, `skip_link`, `bubble_capture`, and the static-string forms of `next` / `prompt_text`.

**The descriptive markers.** For PHP-only `next` / `prompt_text` the `.md` carries a human-readable marker so a reader can see which branch/builder applies, but the merge ignores the marker entirely and re-attaches the real callable from code:

- callable `next`  → `.md` records `next: { branch: <method-name> }` (e.g. `{branch: nextFromPathChoice}`)
- callable `prompt_text` → `.md` records `prompt_text: { builder: <method-name> }` (e.g. `{builder: buildPersonalPrompt}`)

The reader returns these markers as the parsed values; the merge in `transitionTable()` discards any `next`/`prompt_text` that is a marker (array) OR that names a callable in the in-code base, and keeps the in-code callable. This keeps PHP namespaces out of the data file.

**The merge rule.** For each state id in `inCodeStates()`:
1. `base = inCodeStates()[id]` (authoritative for PHP-only fields + the fallback).
2. If the corpus table has this id, for each key in the corpus state: if the key is `skip_if` → ignore; if the key is `next`/`prompt_text` AND the in-code base value for that key is a callable reference (string with `::`) → ignore the corpus value (keep code); else overwrite `base[key]` with the corpus value, **preserving the in-code key order** (corpus values replace in place; corpus introduces no new keys because v1 is a faithful transcription).
3. `effective[id] = base`.

State-id set + order MUST match between corpus and code, else the reader returns `null` (fallback) and the golden master fails.

---

## File Structure

**Create:**
- `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` — frontmatter + the transition table (extracted subset) in one fenced ```` ```yaml ```` block.
- `app/Services/Onboarding/OnboardingWorkflowTable.php` — pure reader: `fromProcedure(Procedure $p): ?array`.
- `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php` — the 4d hard gate.
- `tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php` — parser degrade + merge re-attach + fallback.

**Modify:**
- `app/Services/Onboarding/OnboardingStateMachine.php` — add `inCodeStates()` (verbatim move of the current `states()` body), `transitionTable()` (load + merge + fallback), route `states()` / `getState()` through `transitionTable()`.

**Consumed unchanged (4a/4b substrate):**
- `ProceduralCorpusLoader::load()` (singleton, never throws), `ProceduralCorpus::active()`, `Procedure` VO.

---

## Task 1: Establish the golden-master — author the `.md`, capture current `states()`, assert merged deep-equals

This is the phase's hard gate and MUST come first. We capture the CURRENT `states()` output as the authority, ship the `.md` as a faithful transcription, and write the golden-master test against the not-yet-built `transitionTable()` so it fails red until the implementation lands.

**Files:**
- Create: `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`
- Create: `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`

- [ ] **Step 1: Author the corpus `.md` — faithful transcription of the extracted data subset**

Create `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md`. Every DATA field is transcribed verbatim from `states()`; PHP-only `next`/`prompt_text` are written as `{branch:…}` / `{builder:…}` markers; `skip_if` is omitted entirely. State order and bubble order match the in-code table exactly. NO ICONS (bubbles are `{id,label}` + optional `description` only). The `base_expenditure` prompt is the literal string `JourneyFieldResolver::getFynPrompt('monthly_expenditure')` resolves to (verify with the one-liner in Step 2).

````markdown
---
procedure_id: 'onboarding.workflow.fyn-onboarding'
kind: workflow
module: onboarding
version: 1
active: true
effective_from: 2026-06-02
---

Fyn onboarding state-machine transition table — DATA subset only.

PHP-only fields (callable `next`, callable `prompt_text`, `skip_if`) stay in
`OnboardingStateMachine` and are re-attached by state id at merge time; the
`{branch: …}` / `{builder: …}` markers below are descriptive only and are
NEVER read as the actual transition target / prompt. See
`docs/superpowers/plans/2026-06-02-coala-phase-4d-onboarding-workflow.md`.

NO ICONS: bubbles are `{id, label}` (+ optional `description`) only.

```yaml
path_choice:
  turn_type: bubbles
  prompt_text: "Hi {first_name}, I'm Fyn — welcome to Fynla. I'll help you set up your financial plan. To start, do you want to follow a life-stage journey or pick a single module focus?"
  bubbles:
    - { id: journey, label: 'Follow a journey' }
    - { id: focus, label: 'Pick a focus' }
  capture_field: onboarding_fyn_path
  next: { branch: nextFromPathChoice }

journey_selection:
  turn_type: bubbles
  prompt_text: 'Which journey fits your situation best?'
  bubbles:
    - { id: budgeting, label: 'Starting Out', description: 'Build smart money habits from day one.' }
    - { id: goals, label: 'Building Foundations', description: 'Save for your first home and grow your career.' }
    - { id: protection, label: 'Protecting What Matters', description: 'Secure your family and grow your wealth.' }
    - { id: retirement, label: 'Planning Your Future', description: 'Maximise your wealth and prepare for retirement.' }
    - { id: estate, label: 'Enjoying Your Wealth', description: 'Make your money last and leave a legacy.' }
  capture_field: onboarding_fyn_selection
  next: base_personal

focus_selection:
  turn_type: bubbles
  prompt_text: 'Which area would you like me to focus on first?'
  bubbles:
    - { id: savings, label: 'Savings' }
    - { id: investment, label: 'Investment' }
    - { id: retirement, label: 'Retirement' }
    - { id: protection, label: 'Protection' }
    - { id: estate, label: 'Estate Planning' }
    - { id: goals, label: 'Goals & Life Events' }
    - { id: budgeting, label: 'Budgeting' }
    - { id: business, label: 'Business' }
  capture_field: onboarding_fyn_selection
  next: base_personal

base_personal:
  turn_type: grouped_extract
  prompt_text: { builder: buildPersonalPrompt }
  extraction_tool: capture_personal_details
  retry_text: "Sorry, I didn't catch both pieces. Could you tell me your date of birth (something like 12 January 1985) and your marital status?"
  next: { branch: nextFromPersonal }

base_spouse:
  turn_type: grouped_extract
  prompt_text: { builder: buildSpousePrompt }
  extraction_tool: capture_spouse_details
  retry_text: 'I need a first name, date of birth, and email address for your partner so I can create and link their account. Could you share those again?'
  next: base_dependants
  skip_link: { label: 'Skip this for now', color: raspberry }

base_dependants:
  turn_type: bubbles
  prompt_text: 'Any children or dependants to add?'
  bubbles:
    - { id: yes, label: 'Yes' }
    - { id: 'no', label: 'No' }
  capture_field: null
  next: { branch: nextFromDependants }

base_dependants_detail:
  turn_type: grouped_extract
  prompt_text: 'Lovely. Tell me their first names, ages, and how they are related to you (child, parent, or other dependant). You can list several in one go.'
  extraction_tool: capture_dependants
  retry_text: 'Could you list them again with ages and how they are related? Something like "Alice 7 child, Bob 4 child".'
  next: profile_review_family

profile_review_family:
  turn_type: bubbles
  prompt_text: 'Does your family and personal information look right? Tap the bubble to confirm — or just tell me what needs changing.'
  bubbles:
    - { id: looks_correct, label: 'Looks correct' }
  capture_field: null
  layout: standard
  next: base_employment

base_employment:
  turn_type: bubbles
  prompt_text: "And what's your employment situation at the moment?"
  bubbles:
    - { id: employed, label: 'Full-time' }
    - { id: self_employed, label: 'Self-employed' }
    - { id: part_time, label: 'Part-time' }
    - { id: retired, label: 'Retired' }
    - { id: unemployed, label: 'Not working' }
  capture_field: employment_status
  value_parser: parseEmploymentFromText
  next: { branch: nextFromEmployment }

base_work:
  turn_type: grouped_extract
  prompt_text: { builder: buildWorkPrompt }
  extraction_tool: capture_work_details
  retry_text: 'I need three things: the company or trade name, your position, and your gross annual income in GBP. Could you share all three?'
  next: base_employment_more

base_employment_more:
  turn_type: bubbles
  prompt_text: 'Do you have any other roles or sources of earned income to add?'
  bubbles:
    - { id: yes, label: 'Yes, add another' }
    - { id: 'no', label: "No, that's everything" }
  capture_field: null
  next: { branch: nextFromEmploymentMore }

base_retirement_date:
  turn_type: free_text
  prompt_text: 'When did you retire? A year is fine — something like "2020".'
  capture_field: retirement_date
  value_parser: parseRetirementDate
  next: base_expenditure

base_expenditure:
  turn_type: free_text
  prompt_text: "And roughly how much goes out each month — rent or mortgage, bills, food, transport, the lot? A ballpark figure is fine. I'll use it to work out your savings capacity, emergency fund target, and how much income you'll need in retirement."
  capture_field: monthly_expenditure
  value_parser: parseExpenditureAmount
  next: profile_review_expenditure

profile_review_expenditure:
  turn_type: bubbles
  prompt_text: 'Your expenditure is noted. Confirm the full profile looks right — or tell me what to change.'
  bubbles:
    - { id: looks_correct, label: 'Looks correct' }
  capture_field: null
  layout: standard
  next: { branch: nextFromExpenditureReview }

campaign_intro:
  turn_type: bubbles
  prompt_text: { builder: buildCampaignIntroPrompt }
  bubbles:
    - { id: okay, label: 'Okay' }
    - { id: nope, label: 'Nope' }
  capture_field: null
  next: { branch: nextFromCampaignIntro }

campaign_occupational_scheme:
  turn_type: delegated
  prompt_text: "Tell me about your workplace pension. What percentage of your salary do you contribute, does your employer match it, and is it via salary sacrifice? If you don't have a workplace pension, just say so and we'll move on."
  capture_field: null
  next: campaign_isa_holdings

campaign_isa_holdings:
  turn_type: delegated
  prompt_text: "Let's look at your ISAs. Do you have a Cash ISA or Stocks & Shares ISA? If so, what's the current balance and how much have you put in this tax year?"
  capture_field: null
  next: campaign_bank_accounts

campaign_bank_accounts:
  turn_type: delegated
  prompt_text: "Now your savings outside an ISA — bank accounts, savings accounts, premium bonds. For each, what's the balance and the interest rate?"
  capture_field: null
  next: campaign_investment_accounts

campaign_investment_accounts:
  turn_type: delegated
  prompt_text: 'Any investment accounts outside an ISA — General Investment Accounts, share trading platforms? If so, current value, your purchase cost, and any annual dividend income.'
  capture_field: null
  next: campaign_pension_contribs

campaign_pension_contribs:
  turn_type: delegated
  prompt_text: 'Beyond the workplace pension we covered, do you make any personal pension or Self-Invested Personal Pension contributions? If so, how much per year (gross)?'
  capture_field: null
  next: campaign_pension_history

campaign_pension_history:
  turn_type: grouped_extract
  prompt_text: "Quick one — to check if you have any unused pension allowance to top up, what did you contribute (gross) in each of the last 3 tax years? If you don't know exact numbers, rough figures are fine, and \"zero\" is a valid answer."
  capture_field: null
  extraction_tool: capture_pension_history
  retry_text: 'I just need a rough gross figure for each of the last three tax years (2024/25, 2023/24, 2022/23). Even "I think it was about 5,000 each year" works.'
  next: campaign_charitable_giving

campaign_charitable_giving:
  turn_type: grouped_extract
  prompt_text: "One more — do you make any charitable donations through Gift Aid? If you donate at the higher or additional rate, there's extra relief you can reclaim. Roughly how much per year? Say \"none\" if you don't donate."
  capture_field: null
  extraction_tool: capture_charitable_giving
  retry_text: 'Just an annual figure works — e.g. "about £500" or "none".'
  next: campaign_spouse_work

campaign_spouse_work:
  turn_type: bubbles
  prompt_text: 'Does your spouse work?'
  bubbles:
    - { id: yes, label: 'Yes, they work' }
    - { id: 'no', label: "No, they don't currently work" }
  capture_field: null
  bubble_capture:
    tool: capture_spouse_work_status
    input_for_bubble:
      yes: { spouse_works: true }
      'no': { spouse_works: false }
  next: { branch: nextFromSpouseWork }

campaign_spouse_household:
  turn_type: grouped_extract
  prompt_text: 'Great. How much does your spouse earn annually, and do they have ISAs, investments, or pension contributions of their own?'
  capture_field: null
  extraction_tool: capture_spouse_household_data
  retry_text: 'I need their annual income and whatever you know about their ISA / investment / pension balances. Could you share what you have?'
  next: campaign_terminal

campaign_spouse_non_working_assets:
  turn_type: grouped_extract
  prompt_text: "Got it — your spouse doesn't currently earn an income. That's actually useful for your tax strategy, because they have around £40,000 of unused tax allowances we can put to work. Do they have any savings, ISAs, or investment accounts in their own name today, or is it all in yours?"
  capture_field: null
  extraction_tool: capture_spouse_non_working_assets
  retry_text: 'Just give me rough numbers — savings balance, ISA balance, investment balance. If they have nothing in their own name, just say "nothing".'
  next: campaign_terminal

campaign_terminal:
  turn_type: terminal
  prompt_text: 'All set, {first_name} — let me show you your tax position.'
  capture_field: null
  navigate_to: /tax-strategy
  next: done

asset_capture:
  turn_type: delegated
  prompt_text: { builder: buildAssetCaptureIntro }
  capture_field: null
  next: add_more

add_more:
  turn_type: bubbles
  prompt_text: "Anything else you'd like to cover?"
  bubbles:
    - { id: savings, label: 'Savings' }
    - { id: investment, label: 'Investment' }
    - { id: retirement, label: 'Retirement' }
    - { id: protection, label: 'Protection' }
    - { id: done, label: "I'm done" }
  capture_field: null
  next: { branch: nextFromAddMore }

done:
  turn_type: terminal
  prompt_text: 'All set, {first_name}. Your {selection} module is ready to explore.'
  capture_field: null
  next: null
```
````

> NOTE ON YAML SCALARS: the bubble id `no` and the key `no:` are quoted (`'no'`) so YAML does not coerce them to boolean `false`. `parseEmploymentFromText`, route strings, and the `value_parser` tokens are unquoted plain scalars (no special chars). Booleans inside `input_for_bubble` (`true`/`false`) are intentional real booleans matching the in-code array. The reader (Task 2) re-normalises `null` strings → PHP `null` only where YAML already yields `null` (Symfony YAML maps bare `null` and `~` to PHP `null`; `capture_field: null` parses to PHP `null` directly).

- [ ] **Step 2: Verify the `base_expenditure` literal is exact, and validate the corpus**

Confirm the transcribed `base_expenditure` prompt matches the runtime-resolved value byte-for-byte:

```bash
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Services\Onboarding\JourneyFieldResolver::getFynPrompt("monthly_expenditure");'
```

If the printed string differs from the `.md`, fix the `.md` to match the PHP exactly (the in-code table is authority). Then run the 4a deploy gate to prove the frontmatter + path agreement is valid:

```bash
php artisan fyn:procedural:validate
```
Expected: exit 0, and the summary lists `active: onboarding.workflow.fyn-onboarding v1 (workflow/onboarding)`.

- [ ] **Step 3: Write the failing golden-master test**

Create `tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\Onboarding\OnboardingStateMachine;
use App\Services\Onboarding\OnboardingWorkflowTable;
use Illuminate\Support\Carbon;

/**
 * Phase 4d HARD GATE.
 *
 * Proves the .md-backed + merged transition table is value-identical to the
 * in-code states() table for the DATA subset (incl. state order + bubble
 * order), and that the PHP-only fields (callable next, callable prompt_text,
 * skip_if array callable) are object-identical between merged and in-code.
 *
 * A drifted .md (state added/removed/renamed, or a DATA value changed) fails
 * here. PHP-only fields are never in the .md and are asserted unchanged from
 * the in-code side.
 */

// Keys that are NEVER read from the corpus — always re-attached from code.
const PHP_ONLY_KEYS = ['skip_if'];

/** A value is a PHP callable reference iff it is a string containing '::'. */
function isCallableRef(mixed $v): bool
{
    return is_string($v) && str_contains($v, '::');
}

/** Strip the PHP-only / callable fields so two states can be compared as DATA. */
function dataSubset(array $state): array
{
    $out = [];
    foreach ($state as $k => $v) {
        if (in_array($k, PHP_ONLY_KEYS, true)) {
            continue;
        }
        if (($k === 'next' || $k === 'prompt_text') && isCallableRef($v)) {
            continue; // callable form — compared separately as a PHP-only field
        }
        $out[$k] = $v;
    }

    return $out;
}

/** Reflectively read the private inCodeStates() so the test sees the authority. */
function inCodeStates(): array
{
    $m = new ReflectionMethod(OnboardingStateMachine::class, 'inCodeStates');
    $m->setAccessible(true);

    return $m->invoke(null);
}

it('merged corpus-backed table deep-equals the in-code states() table', function (): void {
    $inCode = inCodeStates();
    $merged = OnboardingStateMachine::transitionTable();

    // State-id set + ORDER identical.
    expect(array_keys($merged))->toBe(array_keys($inCode));

    foreach ($inCode as $id => $codeState) {
        // DATA subset value-identical, incl. nested ordering (bubbles etc.).
        expect(dataSubset($merged[$id]))->toEqual(dataSubset($codeState))
            ->and(json_encode(dataSubset($merged[$id])))
            ->toBe(json_encode(dataSubset($codeState)))   // strict ordering
            ->and(array_keys($merged[$id]))->toBe(array_keys($codeState)); // key order per state

        // PHP-only / callable fields: object-identical (kept from code).
        foreach (['next', 'prompt_text'] as $k) {
            if (array_key_exists($k, $codeState) && isCallableRef($codeState[$k])) {
                expect($merged[$id][$k] ?? null)->toBe($codeState[$k]);
            }
        }
        if (array_key_exists('skip_if', $codeState)) {
            expect($merged[$id]['skip_if'] ?? null)->toBe($codeState['skip_if']);
        }
    }
});

it('the corpus state-id set exactly equals the in-code state-id set', function (): void {
    $corpus = app(ProceduralCorpusLoader::class)->load();
    $proc = $corpus->active('onboarding.workflow.fyn-onboarding', Carbon::now());
    expect($proc)->not->toBeNull();

    $parsed = OnboardingWorkflowTable::fromProcedure($proc);
    expect($parsed)->not->toBeNull()
        ->and(array_keys($parsed))->toBe(array_keys(inCodeStates()));
});
```

- [ ] **Step 4: Run the golden-master test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`
Expected: FAIL — `Class "App\Services\Onboarding\OnboardingWorkflowTable" not found` and `transitionTable()` / `inCodeStates()` undefined. This proves the gate is wired against the real `.md` and the not-yet-built implementation.

- [ ] **Step 5: Commit the fixture + failing gate**

```bash
./vendor/bin/pint fyn-memory tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php 2>/dev/null; true
git add fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php
git commit -m "test(coala-4d): golden master — corpus workflow .md + deep-equal gate (red)

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: `OnboardingWorkflowTable` reader — parse fenced YAML, degrade to null

**Files:**
- Create: `app/Services/Onboarding/OnboardingWorkflowTable.php`
- Test: `tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php`

- [ ] **Step 1: Write the failing reader test**

Create `tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php`:

```php
<?php

declare(strict_types=1);

use App\Services\AI\Memory\Procedural\Procedure;
use App\Services\Onboarding\OnboardingWorkflowTable;
use Illuminate\Support\Carbon;

function workflowProc(string $body): Procedure
{
    return new Procedure(
        procedureId: 'onboarding.workflow.fyn-onboarding',
        kind: 'workflow',
        module: 'onboarding',
        version: 1,
        active: true,
        effectiveFrom: Carbon::parse('2026-06-02'),
        effectiveTo: null,
        body: $body,
    );
}

it('parses a valid fenced yaml table into the extracted-subset array', function (): void {
    $body = <<<'MD'
Some descriptive prose.

```yaml
path_choice:
  turn_type: bubbles
  prompt_text: 'Hello {first_name}.'
  bubbles:
    - { id: journey, label: 'Follow a journey' }
    - { id: focus, label: 'Pick a focus' }
  capture_field: onboarding_fyn_path
  next: { branch: nextFromPathChoice }
journey_selection:
  turn_type: bubbles
  prompt_text: 'Which journey?'
  capture_field: onboarding_fyn_selection
  next: base_personal
```
MD;

    $table = OnboardingWorkflowTable::fromProcedure(workflowProc($body));

    expect($table)->toBeArray()
        ->and(array_keys($table))->toBe(['path_choice', 'journey_selection'])
        ->and($table['path_choice']['turn_type'])->toBe('bubbles')
        ->and($table['path_choice']['bubbles'][0])->toBe(['id' => 'journey', 'label' => 'Follow a journey'])
        ->and($table['path_choice']['capture_field'])->toBe('onboarding_fyn_path')
        // callable-next marker round-trips as an array — the merge ignores it.
        ->and($table['path_choice']['next'])->toBe(['branch' => 'nextFromPathChoice'])
        // static-string next is preserved as a data string.
        ->and($table['journey_selection']['next'])->toBe('base_personal');
});

it('preserves capture_field: null as PHP null', function (): void {
    $body = "```yaml\nbase_dependants:\n  turn_type: bubbles\n  capture_field: null\n  next: { branch: nextFromDependants }\n```";
    $table = OnboardingWorkflowTable::fromProcedure(workflowProc($body));
    expect($table['base_dependants'])->toHaveKey('capture_field')
        ->and($table['base_dependants']['capture_field'])->toBeNull();
});

it('returns null when the fenced yaml block is missing', function (): void {
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc('no fence here')))->toBeNull();
});

it('returns null on malformed yaml', function (): void {
    $body = "```yaml\n  : : : not valid\n   indentation broken\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});

it('returns null when the yaml is not a mapping of states', function (): void {
    $body = "```yaml\n- just\n- a\n- list\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});

it('returns null when a state value is not a mapping', function (): void {
    $body = "```yaml\npath_choice: 'a string, not a state map'\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});

it('returns null when a state has no turn_type', function (): void {
    $body = "```yaml\npath_choice:\n  prompt_text: 'no turn_type here'\n```";
    expect(OnboardingWorkflowTable::fromProcedure(workflowProc($body)))->toBeNull();
});
```

- [ ] **Step 2: Run to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php`
Expected: FAIL — `Class "App\Services\Onboarding\OnboardingWorkflowTable" not found`.

- [ ] **Step 3: Write the reader**

Create `app/Services/Onboarding/OnboardingWorkflowTable.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Services\AI\Memory\Procedural\Procedure;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Pure reader for the onboarding workflow procedure body.
 *
 * Parses the single fenced ```yaml``` block in a `workflow`-kind procedure's
 * body into the extracted DATA-subset transition table (see Phase 4d plan §
 * extraction boundary). PHP-only fields (callable next / prompt_text, skip_if)
 * are NOT carried by the corpus — they are re-attached by OnboardingStateMachine
 * at merge time. Mirrors AiToolDefinitions::toolFromCorpus (4b).
 *
 * Returns null (never throws) on any error so OnboardingStateMachine falls back
 * to its in-code table — onboarding always has a working state machine.
 */
final class OnboardingWorkflowTable
{
    /**
     * @return array<string, array<string, mixed>>|null
     */
    public static function fromProcedure(Procedure $procedure): ?array
    {
        try {
            $yaml = self::extractYamlBlock($procedure->body);
            if ($yaml === null) {
                return null;
            }

            $parsed = Yaml::parse($yaml);
            if (! is_array($parsed) || $parsed === []) {
                return null;
            }

            $table = [];
            foreach ($parsed as $stateId => $state) {
                // Reject list-shaped YAML (numeric keys) and non-mapping states.
                if (! is_string($stateId) || ! is_array($state)) {
                    return null;
                }
                // Every state must declare a turn_type (cheap shape guard).
                if (! array_key_exists('turn_type', $state) || ! is_string($state['turn_type'])) {
                    return null;
                }
                $table[$stateId] = $state;
            }

            return $table;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Strip the leading ```yaml fence and trailing ``` fence; return inner YAML or null. */
    private static function extractYamlBlock(string $body): ?string
    {
        if (preg_match('/```yaml\s*\n(.*?)\n```/s', $body, $m) !== 1) {
            return null;
        }

        $inner = trim($m[1]);

        return $inner === '' ? null : $inner;
    }
}
```

- [ ] **Step 4: Run to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php`
Expected: PASS (7 passed).

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingWorkflowTable.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php
git add app/Services/Onboarding/OnboardingWorkflowTable.php tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php
git commit -m "feat(coala-4d): OnboardingWorkflowTable reader — parse fenced yaml, degrade to null

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: `OnboardingStateMachine::transitionTable()` + `inCodeStates()` + route `states()` through merge

This is the behaviour-moving task. Move the current `states()` body verbatim into a new private `inCodeStates()` (no value changes), then make `states()` return `transitionTable()`, which loads + merges + falls back. The golden master (Task 1) turns green here.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingStateMachine.php`

- [ ] **Step 1: Rename `states()` body to `inCodeStates()` (verbatim move)**

In `app/Services/Onboarding/OnboardingStateMachine.php`, change the current public `states()` signature line:

```php
    public static function states(): array
    {
        return [
```
to a new **private** method (the body is unchanged byte-for-byte — only the method name + visibility change):

```php
    /**
     * The authoritative in-code transition table. Source of truth for the
     * PHP-only fields (callable `next`, callable `prompt_text`, `skip_if`) and
     * the fallback when the corpus workflow procedure is absent / malformed.
     * Phase 4d moves the DATA subset of this table to
     * fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md; the merge
     * in transitionTable() overlays that data while keeping these PHP-only fields.
     */
    private static function inCodeStates(): array
    {
        return [
```

(Leave the entire array literal — every state from `STATE_PATH_CHOICE` to `STATE_DONE` — unchanged. Only the three lines above the array open are edited.)

- [ ] **Step 2: Add `states()` + `transitionTable()` immediately after `inCodeStates()`**

Insert these two methods directly after the closing `;` and `}` of `inCodeStates()` (i.e. before the existing `public static function getState(...)`):

```php
    /**
     * Public transition table. Routes through transitionTable() so every
     * consumer (OnboardingChatDirector, AiChatController) transparently gets the
     * corpus-backed DATA subset merged over the in-code base when the workflow
     * procedure is present, and the pure in-code table otherwise. No consumer
     * signature changes.
     */
    public static function states(): array
    {
        return self::transitionTable();
    }

    /**
     * Resolve the active onboarding workflow procedure, parse its DATA subset,
     * and merge it over the in-code base: the corpus is authoritative for DATA
     * fields; the in-code table is authoritative for PHP-only fields (callable
     * `next`, callable `prompt_text`, `skip_if`). Falls back to inCodeStates()
     * on absent / malformed corpus or any fault — never throws.
     *
     * Memoised per request (deterministic pure function of corpus + code).
     */
    public static function transitionTable(): array
    {
        if (self::$transitionTableCache !== null) {
            return self::$transitionTableCache;
        }

        $base = self::inCodeStates();

        try {
            $corpus = app(ProceduralCorpusLoader::class)->load();
            $procedure = $corpus->active('onboarding.workflow.fyn-onboarding', Carbon::now());
            if ($procedure === null) {
                return self::$transitionTableCache = $base;
            }

            $data = OnboardingWorkflowTable::fromProcedure($procedure);
            if ($data === null) {
                return self::$transitionTableCache = $base;
            }

            // State-id set + order MUST match the in-code table, else fall back.
            if (array_keys($data) !== array_keys($base)) {
                return self::$transitionTableCache = $base;
            }

            return self::$transitionTableCache = self::mergeTable($base, $data);
        } catch (\Throwable $e) {
            report($e);

            return self::$transitionTableCache = $base;
        }
    }

    /**
     * Overlay the corpus DATA fields onto the in-code base, in the in-code key
     * order. PHP-only fields are never overwritten:
     *   - `skip_if` is ignored entirely (not present in the corpus).
     *   - `next` / `prompt_text` are kept from code whenever the in-code value
     *     is a callable reference (a string containing '::') OR the corpus value
     *     is a descriptive marker array ({branch:…} / {builder:…}).
     *
     * @param  array<string, array<string, mixed>>  $base
     * @param  array<string, array<string, mixed>>  $data
     * @return array<string, array<string, mixed>>
     */
    private static function mergeTable(array $base, array $data): array
    {
        $merged = [];
        foreach ($base as $id => $codeState) {
            $corpusState = $data[$id] ?? [];
            $out = $codeState; // preserves in-code key order + PHP-only fields
            foreach ($corpusState as $key => $value) {
                if ($key === 'skip_if') {
                    continue;
                }
                if ($key === 'next' || $key === 'prompt_text') {
                    $codeValue = $codeState[$key] ?? null;
                    $codeIsCallable = is_string($codeValue) && str_contains($codeValue, '::');
                    $corpusIsMarker = is_array($value);
                    if ($codeIsCallable || $corpusIsMarker) {
                        continue; // keep the in-code callable
                    }
                }
                $out[$key] = $value; // corpus wins on DATA fields, in place
            }
            $merged[$id] = $out;
        }

        return $merged;
    }
```

- [ ] **Step 3: Add the memo field + imports**

At the top of the class body (right after the `STATE_*` consts, before `inCodeStates()`), add the request-scoped memo:

```php
    /** Memoised effective transition table (corpus-merged or in-code fallback). */
    private static ?array $transitionTableCache = null;
```

Add the two imports to the `use` block at the top of the file (next to the existing `use App\Models\AiConversation;` / `use App\Models\User;`), introducing them together with their first usage (Task 2's `OnboardingWorkflowTable`, the loader, and `Carbon`):

```php
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Support\Carbon;
```

(`OnboardingWorkflowTable` is in the same namespace `App\Services\Onboarding`, so it needs no import. `Carbon` and `ProceduralCorpusLoader` are used in `transitionTable()` added in Step 2 — add them in the same Write as the usage to dodge the Pint import-strip quirk.)

- [ ] **Step 4: Run the golden master — it must now turn GREEN**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php`
Expected: PASS (2 passed). The merged corpus-backed table deep-equals the in-code table; state-id sets match.

If RED: diagnose with file:line evidence (a mismatched DATA value means the `.md` transcription drifted from `states()`; fix the `.md` to match the in-code authority byte-for-byte and re-run — loop until GREEN, never weaken the assertion). A `next`/`prompt_text` mismatch means the marker handling in `mergeTable()` is wrong. A key-order mismatch means the corpus introduced/reordered a key — align the `.md` to the in-code key order.

- [ ] **Step 5: Pint + commit**

```bash
./vendor/bin/pint app/Services/Onboarding/OnboardingStateMachine.php
git add app/Services/Onboarding/OnboardingStateMachine.php
git commit -m "feat(coala-4d): route states() through corpus-merged transitionTable() with in-code fallback

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: Behavioural — merge re-attach + fallback path proven via the real machine

Prove (a) the merged table drives `getState`/`getNextStateId`/`matchBubble` identically, and (b) the fallback path returns the in-code table when the corpus procedure is absent — both via the public API, with `transitionTableCache` reset between cases.

**Files:**
- Modify: `tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php` (append merge + fallback cases)

- [ ] **Step 1: Append the merge + fallback tests**

Add these cases to `tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php`. They reset the memo via reflection, point the corpus at a temp dir to simulate "procedure absent", and re-assert behaviour. Add the imports at the top of the file in the same Write (they are used immediately below):

```php
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\Onboarding\OnboardingStateMachine;
use Illuminate\Support\Facades\File;

/** Clear the per-request memo so each case re-resolves the table. */
function resetTransitionMemo(): void
{
    $p = new ReflectionProperty(OnboardingStateMachine::class, 'transitionTableCache');
    $p->setAccessible(true);
    $p->setValue(null, null);
}
```

```php
it('re-attaches PHP-only callable fields from code in the merged table', function (): void {
    resetTransitionMemo();
    $merged = OnboardingStateMachine::transitionTable();

    // callable next kept from code (a Class::method string, not the {branch:…} marker)
    expect($merged['path_choice']['next'])
        ->toBe(OnboardingStateMachine::class.'::nextFromPathChoice')
        ->and(is_array($merged['path_choice']['next']))->toBeFalse();

    // callable prompt_text kept from code
    expect($merged['base_personal']['prompt_text'])
        ->toBe(OnboardingStateMachine::class.'::buildPersonalPrompt');

    // skip_if array callable kept from code (never in the .md)
    expect($merged['base_personal']['skip_if'])
        ->toBe([OnboardingStateMachine::class, 'skipIfPersonalComplete']);

    // static-string next is the corpus/data value (identical either way)
    expect($merged['journey_selection']['next'])->toBe('base_personal');
});

it('falls back to the in-code table when the corpus procedure is absent', function (): void {
    $empty = sys_get_temp_dir().'/proc-4d-'.uniqid();
    @mkdir($empty, 0777, true);
    config(['fyn.memory.procedural_path' => $empty]);
    config(['fyn.memory.procedural_reload_interval' => 0]); // force re-stat
    app()->forgetInstance(ProceduralCorpusLoader::class); // fresh loader, empty corpus
    resetTransitionMemo();

    try {
        $table = OnboardingStateMachine::transitionTable();

        // Same state set + order as code, and the campaign branch still routes.
        expect($table)->toHaveKeys(['path_choice', 'campaign_intro', 'done'])
            ->and($table['journey_selection']['next'])->toBe('base_personal')
            ->and($table['path_choice']['next'])
            ->toBe(OnboardingStateMachine::class.'::nextFromPathChoice');
    } finally {
        File::deleteDirectory($empty);
        config(['fyn.memory.procedural_path' => base_path('fyn-memory/procedural')]);
        app()->forgetInstance(ProceduralCorpusLoader::class);
        resetTransitionMemo();
    }
});

it('getNextStateId chains identically under the corpus-backed table', function (): void {
    resetTransitionMemo();
    $user = \App\Models\User::factory()->make([
        'marital_status' => 'single',
        'date_of_birth' => '1985-01-12',
    ]);

    // journey_selection → base_personal (static-string edge from the .md)
    expect(OnboardingStateMachine::getNextStateId('journey_selection', 'retirement', $user))
        ->toBe('base_personal');

    // path_choice 'journey' → journey_selection (callable next kept from code)
    expect(OnboardingStateMachine::getNextStateId('path_choice', 'Follow a journey', $user))
        ->toBe('journey_selection');
});
```

> The `getNextStateId` case uses `User::factory()->make()` (no DB write needed; the branching helpers read in-memory attributes). If the suite's `User` factory requires DB, switch to `RefreshDatabase` for this file — but `make()` should suffice as the helpers only read attributes.

- [ ] **Step 2: Run the reader + behavioural suite**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php`
Expected: PASS (10 passed — 7 from Task 2 + 3 new). The fallback test proves behaviour-identity with the corpus absent; the merge test proves PHP-only re-attachment.

- [ ] **Step 3: Pint + commit**

```bash
./vendor/bin/pint tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php
git add tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php
git commit -m "test(coala-4d): merge re-attach + corpus-absent fallback proven via the live machine

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: Full onboarding suite green (behaviour unchanged) + 4b/4c golden masters intact

No code changes — prove every existing consumer behaves identically with the corpus-backed table, and that 4d touched no tool catalogue or prompt prose. The `transitionTable()` memo persists within a test process; existing onboarding tests use `RefreshDatabase` and the real corpus dir, so they exercise the corpus-backed path.

**Files:** none (verification only)

- [ ] **Step 1: Run the full onboarding unit suite**

Run: `./vendor/bin/pest tests/Unit/Services/Onboarding/`
Expected: PASS — `OnboardingStateMachineTest`, `CampaignStateMachineBranchTest`, `CampaignBubbleCaptureTest`, `OnboardingChatDirectorFixesTest`, `OnboardingValueInterpreterTest`, `OnboardingFactExtractorTest`, `AssetCaptureOffScriptFilterTest`, `SpouseCollisionTest`, plus the two new 4d files all green.

If any RED: the extraction changed behaviour — diagnose with file:line evidence (a DATA value in the `.md` drifted from `states()`), fix the `.md` to match the in-code authority, re-run the golden master, then re-run this suite. Loop until GREEN; do NOT weaken any assertion.

- [ ] **Step 2: Run the onboarding feature suite**

Run: `./vendor/bin/pest tests/Feature/Onboarding/`
Expected: PASS — state-machine walkthrough, resume, multi-job, profile-review, spouse-skip, campaign-map entry, etc. all green with the corpus-backed table.

- [ ] **Step 3: Confirm 4b/4c golden masters + Two-Fyn contract untouched**

Run:
```bash
./vendor/bin/pest tests/Feature/Console/FynProceduralValidateTest.php \
  tests/Unit/Services/AI/ tests/Feature/AI/
```
Expected: PASS — the tool-schema golden master, prompt-overlay golden master, `FynSystemPromptTest`, and the procedural validate command all green. 4d added a `workflow/onboarding` file (validator handles it) and changed no tool/prompt.

- [ ] **Step 4: Run the procedural validate gate against the real corpus**

Run: `php artisan fyn:procedural:validate`
Expected: exit 0; summary includes `active: onboarding.workflow.fyn-onboarding v1 (workflow/onboarding)` alongside the 4b tool-schema procedures.

---

## Task 6: Full-suite regression + Pint

**Files:** none (verification only)

- [ ] **Step 1: Pint every changed/new file**

Run:
```bash
./vendor/bin/pint \
  app/Services/Onboarding/OnboardingWorkflowTable.php \
  app/Services/Onboarding/OnboardingStateMachine.php \
  tests/Unit/Services/Onboarding/OnboardingWorkflowTableTest.php \
  tests/Unit/Services/Onboarding/OnboardingWorkflowTableGoldenMasterTest.php
```
Expected: `PASS`. If Pint strips a freshly-added `use` import (the known repo quirk when an import lands before its first usage), re-add the import once the usage exists and re-run until `PASS`.

- [ ] **Step 2: Run the architecture suite**

Run: `./vendor/bin/pest --testsuite=Architecture`
Expected: PASS — `OnboardingWorkflowTable` is a `final` class with no interface, consistent with the services-are-classes rule.

- [ ] **Step 3: Full suite**

Run: `./vendor/bin/pest`
Expected: PASS (all suites green). If any test outside onboarding regressed, diagnose and fix the root cause; do not proceed until green.

- [ ] **Step 4: Final commit (only if Pint changed anything)**

```bash
git add -A
git commit -m "style(coala-4d): pint phase 4d onboarding workflow externalisation

Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>"
```

---

## Done-when checklist (verify against the spec §11)

- [ ] `fyn-memory/procedural/workflow/onboarding/fyn-onboarding.v1.md` exists, passes `fyn:procedural:validate`, faithful DATA-subset transcription, NO ICONS, no scores.
- [ ] `OnboardingWorkflowTable::fromProcedure()` parses the fenced ```yaml``` block; degrades to `null` on missing block / malformed YAML / non-mapping / shape error.
- [ ] `OnboardingStateMachine::transitionTable()` merges corpus DATA over in-code base (corpus wins on DATA; PHP-only `next`/`prompt_text`/`skip_if` always from code), falls back to `inCodeStates()` on absent/malformed/state-set-mismatch, never throws; memoised per request.
- [ ] `states()` / `getState()` route through `transitionTable()`; no consumer signature changes.
- [ ] `OnboardingWorkflowTableGoldenMasterTest` GREEN — merged table deep-equals in-code table (DATA value-identical incl. ordering; PHP-only fields object-identical; state-id set + order identical). **Hard gate.**
- [ ] `OnboardingWorkflowTableTest` GREEN — parser degrade, merge re-attach, corpus-absent fallback all proven.
- [ ] Full onboarding unit + feature suites GREEN with corpus present AND a representative chain re-asserted with the corpus absent.
- [ ] `FynProceduralValidateTest` GREEN; new `.md` passes the validator.
- [ ] 4b tool-schema golden master, 4c prompt-overlay golden master, `FynSystemPromptTest` GREEN; tool catalogue unchanged in both Fyn states; AdviceFyn read-only contract intact (no tool added/removed/reordered).
- [ ] Full Pest suite GREEN; `pint` `PASS` on every changed file; Architecture suite GREEN.
- [ ] No Vue touched (the 4d admin viewer is 4f, not 4d).
