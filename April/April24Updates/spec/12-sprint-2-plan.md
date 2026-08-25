# Fyn v2 — Canonical Two-Fyn Contract

> **BRANCH: `feature/fyn-persona-split`.** All implementation builds on this branch.

This statement is the source of truth for every doc, spec, plan, PRD, and task list in this workstream. It appears verbatim at the top of every artefact.

---

**FYN HAS TWO STATES.**

**ONBOARDING FYN** takes a user through the onboarding flow using bubbles for the user to choose the path, and guides them through the flow they choose. It accepts multi-line information and **SAVES AND WRITES** it to the database so user information is persisted. It has memory: any additional information already entered is not asked about again, but is resurfaced to the user at the right time to give a view of intelligence. If a user leaves at any point in the conversation, the next time they log in Onboarding Fyn picks up from where they left off (example only, not the whole scope: *"Good afternoon CSJ — last time we were busy entering your family details, you told me about X. Do you want to continue from where we left off?"* Yes / No bubble). Journeys are mapped according to what the user wants and where they enter onboarding from. Onboarding Fyn also receives handovers from Advice Fyn for any outstanding information needed to produce guidance. **Onboarding Fyn is the ONLY state that enters or edits information.**

**ADVICE FYN** takes a user request, fetches the user's information, and answers that request using the recommendation engine, the risk module, and every other module or system in the app as needed. Examples only, not the whole scope:

- *"Where's my invoice?"* → Advice Fyn checks subscription status and navigates to the subscription page, confirming the subscription.
- *"Should I contribute more to my ISA?"* → Advice Fyn uses the recommendation engine to surface the guidance the engine produces and navigates to the portfolio page.

Advice Fyn covers tax optimisation (income tax, asset splitting between spouses, etc.), and all other guidance across every module as per the financial planning remit, classification system, recommendation engine, and all the investment, retirement, protection, estate engines and modules. **The ONLY thing Advice Fyn does NOT do is enter or edit information** — that is Onboarding Fyn's job.

**THE USER NEVER SEES THE HANDOFF, OR FEELS THE SWITCH**, between the two states.

---

## What this means for code

- One dispatch decision in `AiChatController::sendMessage`: onboarding or advice, based on `users.onboarding_completed`.
- Onboarding Fyn = the existing `OnboardingChatDirector` (promoted) with a new `handleInlineCapture` entry point for post-onboarding captures.
- Advice Fyn = a new `AdviceFyn` class wrapping the advice-side prompt + chat loop + read-only tool list.
- No `FynPersonaOrchestrator`, no `FynPersonaInvoker`, no `FynPersonaRegistry`, no `DataCapturePromptBuilder`.
- `HandoffContract` constants and `CaptureContext` VO are kept.
- Zero SSE events visible to the frontend that distinguish the two states. No `persona_state_change` event. No capturing pill. Input placeholder invariant.

## What this means for the user

- Onboarding feels like a friendly guided flow with clickable choices and open-text questions.
- Advice feels like a conversational assistant that knows their situation, answers with real data + engine-generated guidance, and navigates them to the right module page.
- When Advice Fyn needs more information to answer something, the request for that information arrives as a natural continuation of the conversation — no "switching to capture mode" preamble, no sudden bubbles.
- Resuming on a new device / session / after a disconnect picks up exactly where the user left off.

## What this means for evaluation

- `01-invariants.md` breaks this contract into ~35 falsifiable invariants. Each invariant has a specific test.
- `fyn-rubrics.md §B` contains 75 golden conversations that exercise the contract end-to-end.
- Scenario category `09-canonical-behaviour` (10 scenarios) is the core canonical-contract test set. Any regression in that category blocks merge.

---

*Source of truth. Do not paraphrase when copying into other docs — paste verbatim.*

---

# Sprint 2 — Batch-shaped Capture Tools + 18-Entity Multi-Entity Coverage

> **BRANCH: `feature/fyn-persona-split`.** Sprint 2 starts only when Sprint 1 is merged. Commits on `feature/fyn-persona-split` or feature branches `feature/csj/sprint2-<subtask>`.
>
> **REQUIRED SUB-SKILL:** `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans`.

**Goal:** Introduce batch-shaped capture tools so every one of the 18+ entity types in Fyn accepts multi-entity inputs. Retire the regex-based `AssetCaptureEntityExtractor` when sustained gap-fill fire rate drops below 2%. Expand Rubric B to 65+ scenarios. End state: full multi-entity coverage; Rubric-A ~22/40; Rubric-B Mode 2 weekly cron running on real providers.

**Architecture:** 14 new capture tools (`capture_<entity>_batch`), each with strict JSON schema + single `DB::transaction` that persists all items or none. They coexist with the existing singular tools (`create_savings_account` etc.) — LLM chooses singular for one-at-a-time conversational inputs and batch for list-style inputs. When Rubric-B eval shows gap-fill synthesis <2% fire rate for 2 consecutive weeks, deletion of `AssetCaptureEntityExtractor` is a separate commit.

**Tech Stack:** Laravel 10, Pest 3, Anthropic + xAI strict tools.

**Spec reference:** INV-2.7.1, INV-2.7.4, INV-2.8.1, INV-2.8.2, INV-2.8.3, INV-2.13.1 in [`01-invariants.md`](01-invariants.md).

---

## Pre-flight

- Sprint 1 merged to `feature/fyn-persona-split`.
- Rubric-A ≥17/40.
- Rubric-B Mode 1 green at 30 scenarios.

---

## Tool catalogue expansion

The 14 batch tools to add (both providers, parity enforced):

| Tool | Maps to | Handler writes |
|---|---|---|
| `capture_protection_policies` | array of `{policy_type, provider, sum_assured, monthly_premium, end_date, ...}` | `LifeInsurancePolicy` / `CriticalIllnessPolicy` / `IncomeProtectionPolicy` per `policy_type` |
| `capture_savings_accounts` | array of `{account_name, provider, account_type, balance, interest_rate}` | `SavingsAccount` |
| `capture_pensions` | array of `{pension_type, provider, pot_value, monthly_contribution, ...}` | `DCPension` / `DBPension` per `pension_type` |
| `capture_investment_accounts` | array of investment-account fields | `InvestmentAccount` |
| `capture_properties_mortgages` | array of `{property: {...}, mortgage: {...}}` | `Property` + `Mortgage` paired |
| `capture_trusts` | array of trust fields | `Trust` |
| `capture_family_members` | array of family-member fields | `FamilyMember` (via `SpouseLinkingService` for spouse branch; direct for dependants) |
| `capture_goals` | array of goal fields | `Goal` |
| `capture_life_events` | array of life-event fields | `LifeEvent` |
| `capture_chattels` | array of chattel fields | `Chattel` |
| `capture_business_interests` | array of business-interest fields | `BusinessInterest` |
| `capture_liabilities` | array of liability fields | `App\Models\Estate\Liability` |
| `capture_estate_gifts` | array of gift fields | `App\Models\Estate\Gift` |
| `capture_holdings` | array of holding fields keyed by investment account | `Holding` |

Total catalogue: 40 (post-Sprint-0) + 14 = **54 Anthropic / 54 xAI** post-Sprint-2.

---

## Task 2.1 — `capture_protection_policies` (prototype for the other 13)

**Invariants:** INV-2.8.2, INV-2.7.1.

**Files:**
- Modify: `app/Services/AI/AiToolDefinitions.php` (+1 tool)
- Modify: `app/Services/AI/XaiToolDefinitions.php` (+1 tool)
- Modify: `app/Agents/CoordinatingAgent.php` (+1 handler method)
- Create: `tests/Feature/AI/BatchCapture/ProtectionPoliciesTest.php`
- Create scenario: `tests/Feature/Fyn/Eval/scenarios/03-multi-entity/protection_batch_3x.yaml`

- [ ] **Step 1 — Failing test**

```php
<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\LifeInsurancePolicy;
use App\Models\User;

it('capture_protection_policies persists all items in one transaction', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('capture_protection_policies', [
        'policies' => [
            ['policy_type' => 'life', 'provider' => 'Aviva', 'sum_assured' => 300000, 'monthly_premium' => 45],
            ['policy_type' => 'life', 'provider' => 'Vitality', 'sum_assured' => 100000, 'monthly_premium' => 25],
        ],
    ], $user);

    expect($result['success'])->toBeTrue();
    expect($result['entity_ids'])->toHaveCount(2);
    expect(LifeInsurancePolicy::where('user_id', $user->id)->count())->toBe(2);
});

it('capture_protection_policies rolls back on mid-batch failure', function (): void {
    $user = User::factory()->create();

    $result = app(CoordinatingAgent::class)->executeTool('capture_protection_policies', [
        'policies' => [
            ['policy_type' => 'life', 'provider' => 'Aviva', 'sum_assured' => 300000, 'monthly_premium' => 45],
            ['policy_type' => 'invalid_type', 'provider' => 'X', 'sum_assured' => 100000, 'monthly_premium' => 25],
        ],
    ], $user);

    expect($result)->toHaveKey('error');
    expect(LifeInsurancePolicy::where('user_id', $user->id)->count())->toBe(0);
});
```

- [ ] **Step 2 — Register tool (Anthropic)**

```php
[
    'name' => 'capture_protection_policies',
    'description' => "Persist multiple protection policies in one call. Use when the user mentions multiple policies at once. All or none persist.",
    'parameters' => [
        'type' => 'object',
        'properties' => [
            'policies' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'policy_type' => ['type' => 'string', 'enum' => ['life', 'critical_illness', 'income_protection']],
                        'provider' => ['type' => 'string'],
                        'sum_assured' => ['type' => 'number'],
                        'monthly_premium' => ['type' => 'number'],
                        'end_date' => ['type' => ['string', 'null']],
                    ],
                    'required' => ['policy_type', 'provider'],
                    'additionalProperties' => false,
                ],
                'minItems' => 1,
            ],
        ],
        'required' => ['policies'],
        'additionalProperties' => false,
    ],
],
```

- [ ] **Step 3 — xAI equivalent** via `wrapTool` with `strict: true`.

- [ ] **Step 4 — Handler**

```php
private function handleCaptureProtectionPolicies(array $input, User $user, bool $isPreview): array
{
    if ($isPreview) return ['blocked' => true];

    return \DB::transaction(function () use ($input, $user) {
        $ids = [];
        foreach ($input['policies'] as $policyData) {
            $policy = match ($policyData['policy_type']) {
                'life' => \App\Models\LifeInsurancePolicy::create([
                    'user_id' => $user->id,
                    'provider' => $policyData['provider'],
                    'sum_assured' => $policyData['sum_assured'] ?? null,
                    'monthly_premium' => $policyData['monthly_premium'] ?? null,
                    'end_date' => $policyData['end_date'] ?? null,
                ]),
                'critical_illness' => \App\Models\CriticalIllnessPolicy::create([...]),
                'income_protection' => \App\Models\IncomeProtectionPolicy::create([...]),
                default => throw new \InvalidArgumentException("Unknown policy_type: {$policyData['policy_type']}"),
            };
            $ids[] = ['type' => $policyData['policy_type'], 'id' => $policy->id];
        }
        return ['success' => true, 'entity_ids' => $ids];
    });
}
```

- [ ] **Step 5 — Eval scenario** `protection_batch_3x.yaml` — user message with 3 policies → one `capture_protection_policies` call → 3 rows persisted.

- [ ] **Step 6 — Run tests + parity test**. PASS.

- [ ] **Step 7 — Commit**
  ```
  git commit -am "feat(fyn): capture_protection_policies batch tool"
  ```

---

## Task 2.2–2.14 — remaining 13 batch tools

Same pattern as 2.1 — TDD cycle with fail + register on both providers + handler + scenario + commit. Each is a mechanical variant over the target entity types:

- [ ] 2.2 `capture_savings_accounts`
- [ ] 2.3 `capture_pensions`
- [ ] 2.4 `capture_investment_accounts`
- [ ] 2.5 `capture_properties_mortgages` (paired — property + its mortgage in one item)
- [ ] 2.6 `capture_trusts`
- [ ] 2.7 `capture_family_members` (spouse branch delegates to `SpouseLinkingService`)
- [ ] 2.8 `capture_goals`
- [ ] 2.9 `capture_life_events`
- [ ] 2.10 `capture_chattels`
- [ ] 2.11 `capture_business_interests`
- [ ] 2.12 `capture_liabilities`
- [ ] 2.13 `capture_estate_gifts`
- [ ] 2.14 `capture_holdings` (arrays keyed by `account_name`)

Each commit message: `feat(fyn): capture_<entity>_batch tool`.

---

## Task 2.15 — Tool-description multi-entity affordance

**Invariants:** part of INV-2.8.1, INV-2.8.2 — ACI improvement.

- [ ] **Step 1 — Update every singular `create_*` tool description** in both `AiToolDefinitions` and `XaiToolDefinitions`: append `" Prefer capture_<entity>_batch when the user mentions multiple items in one message."`.

- [ ] **Step 2 — Pest**. Architecture parity test still passes (40→54 count change).

- [ ] **Step 3 — Commit**.

---

## Task 2.16 — Eval harness expansion to 65+ scenarios

**Invariant:** INV-2.13.1.

- [ ] **Step 1 — Author remaining 8 prompt-injection scenarios** (category 06).
- [ ] **Step 2 — Author 5 regulatory-hedging scenarios** (category 07).
- [ ] **Step 3 — Author 4 provider-parity scenarios** (category 08) — same input run on Anthropic + xAI, assert identical tool calls + SSE event shapes.
- [ ] **Step 4 — Author remaining 6 preview-persona scenarios** (category 02).
- [ ] **Step 5 — Author 8 more canonical-behaviour scenarios** (category 09 — there are 10 in total per Sprint-1 + Sprint-2).
- [ ] **Step 6 — Record real-provider fixtures** for each.
- [ ] **Step 7 — Mode 1 green at 65+ scenarios**.
- [ ] **Step 8 — Enable Mode 2 weekly cron** via `app/Console/Kernel.php` scheduling a `php artisan fyn:eval:run --mode=real` command.

---

## Task 2.17 — Ratchet Rubric-B floors per CSJ decision 3

**Invariant:** INV-2.8.3; CSJ decision 3 in [`../audit-synthesis.md §8`](../audit-synthesis.md).

- [ ] **Step 1 — Edit `config/fyn_eval.php`** with ratcheted floors:
  ```php
  'recall_floor' => [
      'default' => 95,
      'protection' => 98,
      'savings' => 98,
      'mortgage' => 100,
      'retirement' => 95,
      'investment' => 95,
  ],
  'precision_floor' => [
      'default' => 95,
      'mortgage' => 100,
  ],
  ```

- [ ] **Step 2 — Per-commit message tag** `EVAL_FLOOR_RAISE: protection 95→98 — reason: Sprint 2 batch tools ready`.

- [ ] **Step 3 — Rubric-B eval must stay green** at the raised floors. If red, fix the extractor/tool shape first.

- [ ] **Step 4 — Commit**
  ```
  git commit -am "chore(eval): raise recall/precision floors per CSJ decision 3"
  ```

---

## Task 2.18 — Retire `AssetCaptureEntityExtractor` (conditional)

**Invariant:** INV-2.8.2 — trailing condition "when gap-fill fire rate sustained <2% over a 2-week eval window".

- [ ] **Step 1 — Measure gap-fill fire rate** over 2 weeks of Mode 2 runs. If sustained <2%, proceed. Otherwise, defer.

- [ ] **Step 2 — Delete extractor + wiring**
  ```
  git rm app/Services/Onboarding/AssetCaptureEntityExtractor.php
  git rm tests/Unit/Services/Onboarding/AssetCaptureEntityExtractorTest.php
  ```
  Remove `emitGapFillFromCaptureContext` method body from `OnboardingChatDirector` (keep an empty generator yield, or delete the call site entirely).

- [ ] **Step 3 — Eval must stay green** at the post-retirement state.

- [ ] **Step 4 — Commit**
  ```
  git commit -am "chore(fyn): retire AssetCaptureEntityExtractor — batch tools cover all 18 entity types"
  ```

If the 2-week gate doesn't pass: defer this task to Sprint 3. Extractor coexists until it does.

---

## Task 2.19 — Sprint 2 Playwright matrix (batch-tool extension)

**Invariants:** INV-2.8.2 batch-shaped tools across all 18 entity types — per [`03-test-strategy.md`](03-test-strategy.md).

Sprint 2 extends BS-17 (multi-entity persist) with 13 extended variants — one per new batch tool introduced in Tasks 2.1–2.14. Full Sprint 2 matrix: **24 scenarios + 13 BS-17 variants = 37 total runs**, though most reuse the same underlying scenario script with different entity types.

**Files:**
- Modify: `tests/Browser/scenarios/BS-17-multi-entity-persist.php` — parameterise over the 14 batch-tool entity types (protection, savings, pensions, investments, properties+mortgages, trusts, family_members, goals, life_events, chattels, business_interests, liabilities, estate_gifts, holdings).

- [ ] **Step 1 — Parameterise BS-17** using Pest's dataset feature:

```php
it('BS-17 multi-entity persist', function (string $toolVariant, array $entities, string $modulePageRoute) {
    // Seed + login.
    // Type the multi-entity message matching $entities.
    // Wait for assistant ack.
    // Click through UI menu to reach $modulePageRoute.
    // Assert count and details of records rendered.
})->with([
    'protection' => ['protection', [/* 3 policies */], '/protection'],
    'savings' => ['savings', [/* 3 accounts */], '/net-worth/cash'],
    'pensions' => ['pensions', [/* 2 pensions */], '/net-worth/retirement'],
    'investments' => ['investments', [/* 2 accounts */], '/net-worth/investments'],
    'properties_mortgages' => ['properties_mortgages', [/* 1 property + mortgage */], '/net-worth/property'],
    'trusts' => ['trusts', [/* 2 trusts */], '/trusts'],
    'family_members' => ['family_members', [/* 2 dependants */], '/profile/family'],
    'goals' => ['goals', [/* 3 goals */], '/goals'],
    'life_events' => ['life_events', [/* 2 events */], '/goals?tab=events'],
    'chattels' => ['chattels', [/* 2 chattels */], '/net-worth/chattels'],
    'business_interests' => ['business_interests', [/* 1 interest */], '/net-worth/business'],
    'liabilities' => ['liabilities', [/* 2 liabilities */], '/net-worth/liabilities'],
    'estate_gifts' => ['estate_gifts', [/* 2 gifts */], '/estate'],
    'holdings' => ['holdings', [/* 3 holdings */], '/net-worth/investments'],
]);
```

- [ ] **Step 2 — Run**
  ```
  ./dev.sh &
  php artisan db:seed
  ./vendor/bin/pest --testsuite=Browser --filter=BS-
  ```
  Expected: 24 base scenarios + 14 BS-17 variants = 38 PASS.

- [ ] **Step 3 — Screenshots** → `docs/sprint-2-verification/BS-17-<variant>/`.

- [ ] **Step 4 — Commit**
  ```
  git commit -am "test(browser): Sprint 2 batch-tool matrix (14 BS-17 variants)"
  ```

---

## Sprint 2 verification

- [ ] **Full Pest** — all pass.
- [ ] **Architecture parity** — catalogue 54/54.
- [ ] **Rubric-B Mode 1** — 100% at 65+ scenarios.
- [ ] **Rubric-B Mode 2** — ≥97% first weekly run post-enable.
- [ ] **Browser matrix:** 38 runs (24 + 14 variants) — all PASS.
- [ ] **Rubric-A re-score** — target ~22/40.
- [ ] **Merge:** PR to `feature/fyn-persona-split`.

**Report-finished gate:** Sprint 2 is NOT done until 14 BS-17 variants are green AND evidence committed. Per [`03-test-strategy.md §Non-negotiables`](03-test-strategy.md).

Sprint 2 complete. [`13-sprint-3-plan.md`](13-sprint-3-plan.md) next.
