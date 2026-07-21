# Fyn Prompt Rework Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the 12-layer `AdvicePromptBuilder` and 4-layer `OnboardingPromptBuilder` with one static, fully-cached system prompt plus a dynamic user-turn context assembler, behind a feature flag, preserving every FCA invariant and the two-Fyn write-isolation guarantee.

**Architecture:** A new `FynSystemPrompt` returns a byte-identical static string (no interpolation). A new `FynContextAssembler` (driven by a 4-bucket `FynContextSelector`) builds the per-turn `<context>` block that gets prepended in-memory to the current user message. Both directors keep their dispatch and state machines; only the prompt-build call sites branch on `config('fyn.prompt_architecture')`. Old builders stay in-tree behind the flag.

**Tech Stack:** Laravel 10, PHP 8.2 (`declare(strict_types=1)`), Pest, existing Fyn eval harness (`tests/Feature/Fyn/Eval/EvalRunner`).

**Spec:** `docs/superpowers/specs/2026-05-16-fyn-prompt-rework-design.md`

---

## File Structure

| File | Responsibility | Action |
|------|----------------|--------|
| `config/fyn.php` | Houses `prompt_architecture` flag | Create |
| `app/Services/AI/Fyn/FynTurnContext.php` | Immutable VO describing one turn | Create |
| `app/Services/AI/Fyn/ContextBucket.php` | Enum: IDENTITY/POSITION/READINESS/CAPTURE | Create |
| `app/Services/AI/Fyn/FynSystemPrompt.php` | The single static system prompt string | Create |
| `app/Services/AI/Fyn/FynCaptureTurnInstructions.php` | Verbatim onboarding capture-turn rule block | Create |
| `app/Services/AI/Fyn/FynContextSelector.php` | Maps `FynTurnContext` → bucket set | Create |
| `app/Services/AI/Fyn/FynContextAssembler.php` | Builds `<context>…</context>` block | Create |
| `app/Traits/HasAiChat.php` | Advice seam: branch + in-memory context injection | Modify |
| `app/Services/Onboarding/OnboardingChatDirector.php` | Onboarding seam: branch at `:1727` | Modify |
| `tests/Architecture/ApplicationArchitectureTest.php` | Allowlist new namespace if needed | Modify (conditional) |
| `April/April24Updates/spec/00-canonical.md` | Rewritten canonical contract | Modify |
| `prompts/fyn-system-prompt.md` | Consolidated prompt doc | Create |
| `prompts/advice-system-prompt.md`, `prompts/onboarding-system-prompt.md` | Archived under `prompts/archive/` | Move |

**Naming contract (used across all tasks):**
- Config key: `fyn.prompt_architecture`, env `FYN_PROMPT_ARCH`, values `'legacy'` (default) | `'unified'`.
- Helper: `FynPromptMode::isUnified(): bool` — single source of truth for the flag check (Task 1).
- VO: `FynTurnContext` with named constructor `FynTurnContext::make(...)`.
- Assembler entry: `FynContextAssembler::build(FynTurnContext $ctx): string`.
- Selector entry: `FynContextSelector::buckets(FynTurnContext $ctx): array` (array of `ContextBucket`).
- System prompt entry: `FynSystemPrompt::text(): string`.

---

## Task 1: Feature flag + mode helper

**Files:**
- Create: `config/fyn.php`
- Create: `app/Services/AI/Fyn/FynPromptMode.php`
- Test: `tests/Unit/Services/AI/Fyn/FynPromptModeTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynPromptMode;

it('defaults to legacy', function (): void {
    config()->set('fyn.prompt_architecture', 'legacy');
    expect(FynPromptMode::isUnified())->toBeFalse();
});

it('detects unified', function (): void {
    config()->set('fyn.prompt_architecture', 'unified');
    expect(FynPromptMode::isUnified())->toBeTrue();
});

it('treats unknown values as legacy (fail-safe)', function (): void {
    config()->set('fyn.prompt_architecture', 'banana');
    expect(FynPromptMode::isUnified())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynPromptModeTest.php`
Expected: FAIL — `Class "App\Services\AI\Fyn\FynPromptMode" not found`.

- [ ] **Step 3: Create the config file**

`config/fyn.php`:

```php
<?php

declare(strict_types=1);

return [
    /*
     * Fyn prompt architecture.
     *
     * 'legacy'  — the 12-layer AdvicePromptBuilder / 4-layer
     *             OnboardingPromptBuilder (pre-2026-05-16).
     * 'unified' — single static FynSystemPrompt + dynamic
     *             FynContextAssembler.
     *
     * Defaults to legacy until the unified path proves >= legacy on
     * the Fyn eval suite. Fail-safe: any unrecognised value is legacy.
     */
    'prompt_architecture' => env('FYN_PROMPT_ARCH', 'legacy'),
];
```

- [ ] **Step 4: Create the mode helper**

`app/Services/AI/Fyn/FynPromptMode.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * Single source of truth for the Fyn prompt-architecture feature flag.
 * Fail-safe: only the exact string 'unified' enables the new path.
 */
final class FynPromptMode
{
    public static function isUnified(): bool
    {
        return config('fyn.prompt_architecture') === 'unified';
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynPromptModeTest.php`
Expected: PASS (3 passed).

- [ ] **Step 6: Commit**

```bash
git add config/fyn.php app/Services/AI/Fyn/FynPromptMode.php tests/Unit/Services/AI/Fyn/FynPromptModeTest.php
git commit -m "feat(fyn): add prompt-architecture feature flag + mode helper

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2: FynTurnContext value object + ContextBucket enum

**Files:**
- Create: `app/Services/AI/Fyn/ContextBucket.php`
- Create: `app/Services/AI/Fyn/FynTurnContext.php`
- Test: `tests/Unit/Services/AI/Fyn/FynTurnContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynTurnContext;

it('builds an advice-mode context', function (): void {
    $user = User::factory()->make(['first_name' => 'Chris']);
    $ctx = FynTurnContext::make(
        user: $user,
        message: 'How is my pension doing?',
        currentRoute: '/dashboard',
        mode: 'advice',
        onboardingFocus: null,
        isPreview: false,
        classification: ['primary' => 'RETIREMENT'],
    );

    expect($ctx->mode)->toBe('advice')
        ->and($ctx->onboardingFocus)->toBeNull()
        ->and($ctx->isOnboarding())->toBeFalse()
        ->and($ctx->classification['primary'])->toBe('RETIREMENT');
});

it('builds an onboarding-mode context', function (): void {
    $user = User::factory()->make(['first_name' => 'Chris']);
    $ctx = FynTurnContext::make(
        user: $user,
        message: 'Halifax ISA £10k',
        currentRoute: null,
        mode: 'onboarding',
        onboardingFocus: 'savings',
        isPreview: false,
        classification: null,
    );

    expect($ctx->isOnboarding())->toBeTrue()
        ->and($ctx->onboardingFocus)->toBe('savings');
});

it('rejects an invalid mode', function (): void {
    $user = User::factory()->make();
    FynTurnContext::make(
        user: $user, message: 'x', currentRoute: null,
        mode: 'banana', onboardingFocus: null, isPreview: false, classification: null,
    );
})->throws(InvalidArgumentException::class);
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynTurnContextTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create the enum**

`app/Services/AI/Fyn/ContextBucket.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * The four context buckets that replace the legacy 12-layer assembly.
 *
 * IDENTITY  — always: profile narrative + current-page context.
 * POSITION  — financial snapshot + existing records + ranked recommendations.
 * READINESS — data completeness + KYC gate + review-due.
 * CAPTURE   — onboarding focus header + capture-turn instruction block.
 */
enum ContextBucket
{
    case IDENTITY;
    case POSITION;
    case READINESS;
    case CAPTURE;
}
```

- [ ] **Step 4: Create the value object**

`app/Services/AI/Fyn/FynTurnContext.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Models\AiConversation;
use App\Models\User;
use InvalidArgumentException;

/**
 * Immutable description of a single Fyn turn. Carries everything
 * FynContextSelector and FynContextAssembler need — nothing more.
 */
final class FynTurnContext
{
    private function __construct(
        public readonly User $user,
        public readonly string $message,
        public readonly ?string $currentRoute,
        public readonly string $mode,            // 'advice' | 'onboarding'
        public readonly ?string $onboardingFocus,
        public readonly bool $isPreview,
        public readonly ?array $classification,
        public readonly ?AiConversation $conversation,
    ) {}

    public static function make(
        User $user,
        string $message,
        ?string $currentRoute,
        string $mode,
        ?string $onboardingFocus,
        bool $isPreview,
        ?array $classification,
        ?AiConversation $conversation = null,
    ): self {
        if (! in_array($mode, ['advice', 'onboarding'], true)) {
            throw new InvalidArgumentException("Invalid Fyn turn mode: {$mode}");
        }

        return new self(
            $user, $message, $currentRoute, $mode,
            $onboardingFocus, $isPreview, $classification, $conversation,
        );
    }

    public function isOnboarding(): bool
    {
        return $this->mode === 'onboarding';
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynTurnContextTest.php`
Expected: PASS (3 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Fyn/ContextBucket.php app/Services/AI/Fyn/FynTurnContext.php tests/Unit/Services/AI/Fyn/FynTurnContextTest.php
git commit -m "feat(fyn): FynTurnContext VO + ContextBucket enum

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3: FynSystemPrompt (static, byte-stable)

**Source text (D4 — preserve wording):** assemble verbatim from the three existing prompt classes, applying ONLY the two generalisation deltas the spec mandates (§5):

1. `app/Services/AI/Prompts/CoreIdentity.php:18-67` — every `{$firstName}` occurrence is replaced with `the user`, EXCEPT the `<response_format>` informal-address bullet, whose final bullet becomes exactly:
   `- When referencing the user informally, you may occasionally use the user's first name (given to you in your turn context) to make the conversation feel personal — but do not overdo it`
2. `app/Services/AI/Prompts/ComplianceRules.php:18-41` — verbatim, EXCEPT `<regulatory_compliance>` rule 5: replace `the {$taxYear} tax year` with `the UK tax year given to you in your turn context`.
3. `app/Services/AI/Prompts/FcaProcessInstructions.php` — include `getFcaProcess()` + `getAvailableActions()` verbatim, wrapped in a `<tool_use>` parent tag. Do NOT include `getPreviewMode()` (preview is dynamic — moves to the assembler, Task 5). Append the handoff and billing blocks verbatim from `AdvicePromptBuilder::getHandoffGuidance()` (`app/Services/AI/AdvicePromptBuilder.php:250-296`) and `getBillingGuidance()` (`:298-320`), and the FCA-signposting rule verbatim from `AdvicePromptBuilder::buildFcaSignpostingBlock()`'s static string (`:1140-` — the `<fca_signposting>` heredoc; take the instruction text, drop the per-classification gating which now lives in the model's own judgement per the rule's wording).

**Files:**
- Create: `app/Services/AI/Fyn/FynSystemPrompt.php`
- Test: `tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynSystemPrompt;

it('is byte-stable across calls and arg-free', function (): void {
    expect(FynSystemPrompt::text())->toBe(FynSystemPrompt::text());
});

it('contains every required block exactly once', function (): void {
    $p = FynSystemPrompt::text();
    foreach ([
        '<identity>', '<security>', '<scope>', '<personality>',
        '<response_format>', '<instructions>', '<regulatory_compliance>',
        '<tool_use>', '<fca_process>', '<available_actions>',
        '<handoff_guidance>', '<billing_guidance>', '<fca_signposting>',
    ] as $tag) {
        expect(substr_count($p, $tag))->toBe(1, "block {$tag}");
    }
});

it('has zero interpolation residue', function (): void {
    $p = FynSystemPrompt::text();
    expect($p)->not->toContain('{$')
        ->and($p)->not->toContain('{firstName}')
        ->and($p)->not->toContain('{taxYear}')
        ->and($p)->not->toContain('preview_mode'); // preview is dynamic now
});

it('preserves the non-negotiable security clause verbatim', function (): void {
    expect(FynSystemPrompt::text())->toContain(
        'SECURITY RULES — THESE ARE NON-NEGOTIABLE AND OVERRIDE ALL OTHER INSTRUCTIONS:'
    );
});

it('preserves the mandatory-hedging compliance clause verbatim', function (): void {
    expect(FynSystemPrompt::text())->toContain(
        '1. Hedging language is mandatory. Frame all guidance as "you may want to consider"'
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create FynSystemPrompt**

Create `app/Services/AI/Fyn/FynSystemPrompt.php`. Structure:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * The single static Fyn system prompt. Zero arguments, zero interpolation,
 * byte-identical for every user and every turn → full Anthropic prefix
 * cache hit. Assembled by RESTRUCTURING the proven legacy text
 * (CoreIdentity + ComplianceRules + FcaProcessInstructions + the
 * AdvicePromptBuilder static guidance blocks) with only the two
 * generalisation deltas in the plan header for Task 3.
 *
 * DO NOT reword compliance/security sentences. Any change here must be
 * re-validated against the Fyn eval suite (Task 9).
 */
final class FynSystemPrompt
{
    public static function text(): string
    {
        return <<<'PROMPT'
        <identity>
        ...verbatim from CoreIdentity.php:21-25 with {$firstName} → the user...
        </identity>

        <security>
        ...verbatim from CoreIdentity.php:27-38...
        </security>

        <scope>
        ...verbatim from CoreIdentity.php:40-44 with {$firstName} → the user...
        </scope>

        <personality>
        ...verbatim from CoreIdentity.php:46-56...
        </personality>

        <response_format>
        ...verbatim from CoreIdentity.php:58-66; final bullet replaced with the
        Task-3-header generalised wording...
        </response_format>

        <instructions>
        ...verbatim from ComplianceRules.php:18-31...
        </instructions>

        <regulatory_compliance>
        ...verbatim from ComplianceRules.php:33-41; rule 5 tax-year phrase
        replaced per Task-3 header...
        </regulatory_compliance>

        <tool_use>
        ...verbatim getFcaProcess() (FcaProcessInstructions.php:39-55)...

        ...verbatim getAvailableActions() (FcaProcessInstructions.php:60-92)...

        ...verbatim handoff block (AdvicePromptBuilder::getHandoffGuidance, :250-296)...

        ...verbatim billing block (AdvicePromptBuilder::getBillingGuidance, :298-320)...

        ...verbatim fca_signposting instruction (AdvicePromptBuilder::buildFcaSignpostingBlock heredoc)...
        </tool_use>
        PROMPT;
    }
}
```

When filling the heredoc, open each cited source file and paste the exact lines. Use a nowdoc (`<<<'PROMPT'`) so `$` characters in the source text are not interpolated. Preserve every character of the cited ranges except the two documented deltas. The nested blocks (`<fca_process>`, `<available_actions>`, `<handoff_guidance>`, `<billing_guidance>`, `<fca_signposting>`) keep their own tags inside the `<tool_use>` parent so the byte-stability test's `substr_count(...)===1` per tag holds.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php`
Expected: PASS (5 passed). If a tag count is 0 or 2, fix the heredoc structure (missing block or duplicated tag).

- [ ] **Step 5: Snapshot the prompt for parity diffing**

Run: `php -r "require 'vendor/autoload.php'; \$a=require 'bootstrap/app.php'; \$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); file_put_contents('docs/superpowers/specs/fyn-system-prompt.snapshot.txt', App\Services\AI\Fyn\FynSystemPrompt::text());"`
Expected: writes the snapshot file (used as a reference artefact, not a test).

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Fyn/FynSystemPrompt.php tests/Unit/Services/AI/Fyn/FynSystemPromptTest.php docs/superpowers/specs/fyn-system-prompt.snapshot.txt
git commit -m "feat(fyn): static byte-stable FynSystemPrompt (restructured legacy text)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4: FynCaptureTurnInstructions (verbatim onboarding block)

**Source (D4 — preserve wording):** the `<asset_capture_turn>…</asset_capture_turn>` heredoc built by `OnboardingPromptBuilder::buildAssetCapturePrompt()` (`app/Services/Onboarding/OnboardingPromptBuilder.php` — the Layer 3 block; full text is mirrored in `prompts/onboarding-system-prompt.md:135-200`). Lift it verbatim into a constant with two named placeholders kept as `%s`: `{$focusLabel}` → `%1$s`, `{$toolList}` → `%2$s`.

**Files:**
- Create: `app/Services/AI/Fyn/FynCaptureTurnInstructions.php`
- Test: `tests/Unit/Services/AI/Fyn/FynCaptureTurnInstructionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Services\AI\Fyn\FynCaptureTurnInstructions;

it('renders focus label and tool list into the verbatim block', function (): void {
    $out = FynCaptureTurnInstructions::render('Cash & Savings', 'create_savings_account, update_profile, update_record');

    expect($out)->toContain('<asset_capture_turn>')
        ->and($out)->toContain('</asset_capture_turn>')
        ->and($out)->toContain('Cash & Savings')
        ->and($out)->toContain('create_savings_account, update_profile, update_record')
        ->and($out)->toContain('MULTI-ENTITY RULE (highest priority')
        ->and($out)->toContain('EXACTLY ONE')      // the ≤15-word guardrail
        ->and($out)->not->toContain('%1$s')
        ->and($out)->not->toContain('%2$s');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynCaptureTurnInstructionsTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create FynCaptureTurnInstructions**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

/**
 * The onboarding asset-capture turn rule block, lifted VERBATIM from
 * OnboardingPromptBuilder Layer 3 (D4 — preserve wording). Only the two
 * dynamic slots are parameterised: focus label and allowed tool list.
 */
final class FynCaptureTurnInstructions
{
    public static function render(string $focusLabel, string $toolList): string
    {
        $template = <<<'PROMPT'
        <asset_capture_turn>
        ...verbatim from prompts/onboarding-system-prompt.md:136-199 with
        {$focusLabel} → %1$s and {$toolList} → %2$s ...
        </asset_capture_turn>
        PROMPT;

        return sprintf($template, $focusLabel, $toolList);
    }
}
```

Fill the heredoc by pasting the exact `<asset_capture_turn>` block from `prompts/onboarding-system-prompt.md` lines 136-199 (which mirror `OnboardingPromptBuilder` Layer 3). Replace the two `{$focusLabel}` / `{$toolList}` slots with `%1$s` / `%2$s`. Nowdoc (`<<<'PROMPT'`) so `$` is literal. Change nothing else.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynCaptureTurnInstructionsTest.php`
Expected: PASS (1 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Fyn/FynCaptureTurnInstructions.php tests/Unit/Services/AI/Fyn/FynCaptureTurnInstructionsTest.php
git commit -m "feat(fyn): verbatim onboarding capture-turn instruction block

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5: FynContextSelector (4-bucket logic)

**Rules (spec §6):**
- `mode = onboarding` ⇒ exactly `{IDENTITY, CAPTURE}`.
- `mode = advice`, classification primary is factual (`BILLING`, `NAVIGATION`, `DATA_ENTRY`, `OUT_OF_REMIT`, `INCOME`, `GENERAL`) ⇒ `{IDENTITY}` only.
- `mode = advice`, otherwise ⇒ `{IDENTITY, POSITION, READINESS}`.
- Known-facts is mode-independent and is NOT a bucket (assembler always tries it).

The factual set is the existing `AdviceFyn::engineCallLevelFor($primary) === 'factual'` check used at `HasAiChat.php:149-150`. Reuse it — do not redefine the list.

**Files:**
- Create: `app/Services/AI/Fyn/FynContextSelector.php`
- Test: `tests/Unit/Services/AI/Fyn/FynContextSelectorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\ContextBucket;
use App\Services\AI\Fyn\FynContextSelector;
use App\Services\AI\Fyn\FynTurnContext;

function ctx(string $mode, ?array $classification, ?string $focus = null): FynTurnContext
{
    return FynTurnContext::make(
        user: User::factory()->make(),
        message: 'x',
        currentRoute: '/dashboard',
        mode: $mode,
        onboardingFocus: $focus,
        isPreview: false,
        classification: $classification,
    );
}

it('onboarding gets exactly IDENTITY + CAPTURE', function (): void {
    $b = (new FynContextSelector())->buckets(ctx('onboarding', null, 'savings'));
    expect($b)->toEqualCanonicalizing([ContextBucket::IDENTITY, ContextBucket::CAPTURE]);
});

it('advice factual gets IDENTITY only', function (): void {
    $b = (new FynContextSelector())->buckets(ctx('advice', ['primary' => 'BILLING']));
    expect($b)->toEqual([ContextBucket::IDENTITY]);
});

it('advice non-factual gets IDENTITY + POSITION + READINESS', function (): void {
    $b = (new FynContextSelector())->buckets(ctx('advice', ['primary' => 'RETIREMENT']));
    expect($b)->toEqualCanonicalizing([
        ContextBucket::IDENTITY, ContextBucket::POSITION, ContextBucket::READINESS,
    ]);
});

it('advice with null classification is treated as non-factual', function (): void {
    $b = (new FynContextSelector())->buckets(ctx('advice', null));
    expect($b)->toContain(ContextBucket::POSITION);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextSelectorTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Create FynContextSelector**

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Services\AI\AdviceFyn;

/**
 * Maps a FynTurnContext onto the 4 context buckets (spec §6).
 * Reuses AdviceFyn::engineCallLevelFor as the factual signal — does
 * not redefine the classification taxonomy.
 */
final class FynContextSelector
{
    /** @return list<ContextBucket> */
    public function buckets(FynTurnContext $ctx): array
    {
        if ($ctx->isOnboarding()) {
            return [ContextBucket::IDENTITY, ContextBucket::CAPTURE];
        }

        $primary = $ctx->classification['primary'] ?? null;
        $isFactual = $primary !== null
            && AdviceFyn::engineCallLevelFor($primary) === 'factual';

        if ($isFactual) {
            return [ContextBucket::IDENTITY];
        }

        return [
            ContextBucket::IDENTITY,
            ContextBucket::POSITION,
            ContextBucket::READINESS,
        ];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextSelectorTest.php`
Expected: PASS (4 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Fyn/FynContextSelector.php tests/Unit/Services/AI/Fyn/FynContextSelectorTest.php
git commit -m "feat(fyn): 4-bucket FynContextSelector reusing existing factual signal

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6: FynContextAssembler

Reuses the EXISTING public builders on `AdvicePromptBuilder` (do not rewrite their internals — they stay, the legacy path still uses them): `buildUserProfile(User)`, `buildFinancialContext(User, ?callable, ?array)`, `buildExistingRecordsSummary(User, ?array)`, `buildPrerequisiteStateContext(User)`, plus `MemoryRetrieverService::renderKnownFactsBlock(User, ?AiConversation)`. Module/page context: reuse `AdvicePromptBuilder`'s route map by calling its existing module-context behaviour via the same source it uses (the `getModuleContext` path); if that method is private, add a thin `public function moduleContextFor(?string $route): string` to `AdvicePromptBuilder` that returns the same string its private logic produces (no behaviour change — extract-and-expose).

**Files:**
- Create: `app/Services/AI/Fyn/FynContextAssembler.php`
- Modify: `app/Services/AI/AdvicePromptBuilder.php` (only if `getModuleContext` needs a public passthrough)
- Test: `tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynContextAssembler;
use App\Services\AI\Fyn\FynTurnContext;

beforeEach(function (): void {
    $this->user = User::factory()->create(['first_name' => 'Chris']);
});

it('always emits IDENTITY: profile + current page + name + tax year', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'How is my pension?', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: false,
        classification: ['primary' => 'BILLING'], // factual ⇒ IDENTITY only
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<context>')->and($out)->toContain('</context>')
        ->and($out)->toContain('<user_message>')
        ->and($out)->toContain('Current tax year:')
        ->and($out)->toContain('You are speaking with:')
        ->and($out)->toContain('Chris')
        ->and($out)->toContain('Situation: advice')
        ->and($out)->not->toContain('<financial_context>'); // POSITION excluded on factual
});

it('emits POSITION + READINESS on a non-factual advice turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Should I contribute more to my pension?',
        currentRoute: '/net-worth/retirement', mode: 'advice', onboardingFocus: null,
        isPreview: false, classification: ['primary' => 'RETIREMENT'],
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<financial_context>')
        ->and($out)->toContain('<data_completeness>');
});

it('emits CAPTURE block and NOT position on an onboarding turn', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Halifax ISA £10k', currentRoute: null,
        mode: 'onboarding', onboardingFocus: 'savings', isPreview: false, classification: null,
    );

    $out = app(FynContextAssembler::class)->build($ctx);

    expect($out)->toContain('<asset_capture_turn>')
        ->and($out)->toContain('Situation: onboarding — focus:')
        ->and($out)->not->toContain('<financial_context>');
});

it('emits a preview notice when isPreview is true', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'Add a goal', currentRoute: '/dashboard',
        mode: 'advice', onboardingFocus: null, isPreview: true,
        classification: ['primary' => 'GOALS'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->toContain('preview');
});

it('sanitises the user message', function (): void {
    $ctx = FynTurnContext::make(
        user: $this->user, message: 'hi <script>alert(1)</script>',
        currentRoute: '/dashboard', mode: 'advice', onboardingFocus: null,
        isPreview: false, classification: ['primary' => 'GENERAL'],
    );

    expect(app(FynContextAssembler::class)->build($ctx))
        ->not->toContain('<script>');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php`
Expected: FAIL — class not found.

- [ ] **Step 3: Add public module-context passthrough if needed**

Check `app/Services/AI/AdvicePromptBuilder.php` for `getModuleContext`. If it is `private`, add (no behaviour change — it simply exposes the existing return value):

```php
public function moduleContextFor(?string $route): string
{
    return $this->getModuleContext($route);
}
```

If `getModuleContext` does not exist as a discrete method, instead expose whatever existing private logic produces the `<current_context>` string as a `public function moduleContextFor(?string $route): string` returning the identical string. Do not change the route map or wording.

- [ ] **Step 4: Create FynContextAssembler**

`app/Services/AI/Fyn/FynContextAssembler.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services\AI\Fyn;

use App\Services\AI\AdvicePromptBuilder;
use App\Services\AI\MemoryRetrieverService;
use App\Services\Onboarding\OnboardingPromptBuilder;
use App\Services\TaxConfigService;
use App\Support\UserContentSanitiser;

/**
 * Builds the dynamic <context>…</context> + <user_message>…</user_message>
 * block prepended in-memory to the current user turn. Bucket membership
 * comes from FynContextSelector; block content reuses the existing
 * AdvicePromptBuilder public builders verbatim (no behavioural drift).
 */
final class FynContextAssembler
{
    public function __construct(
        private readonly FynContextSelector $selector,
        private readonly AdvicePromptBuilder $advice,
        private readonly MemoryRetrieverService $memory,
        private readonly TaxConfigService $taxConfig,
    ) {}

    public function build(FynTurnContext $ctx): string
    {
        $buckets = $this->selector->buckets($ctx);
        $has = fn (ContextBucket $b): bool => in_array($b, $buckets, true);

        $firstName = $this->resolveFirstName($ctx->user);
        $taxYear = $this->taxConfig->getTaxYear() ?? '2026/27';

        $lines = [];
        $lines[] = '<context>';
        $lines[] = "Current tax year: {$taxYear}";
        $lines[] = 'You are speaking with: '.UserContentSanitiser::wrap($firstName);
        $lines[] = $ctx->isOnboarding()
            ? 'Situation: onboarding — focus: '.$this->focusLabel($ctx->onboardingFocus)
            : 'Situation: advice';

        // IDENTITY (always present in every bucket set)
        $lines[] = '<user_profile>'."\n".$this->advice->buildUserProfile($ctx->user)."\n".'</user_profile>';
        $lines[] = '<current_context>'."\n".$this->advice->moduleContextFor($ctx->currentRoute)."\n".'</current_context>';

        // Known facts — mode-independent, included whenever non-empty
        $known = $this->memory->renderKnownFactsBlock($ctx->user, $ctx->conversation);
        if ($known !== '') {
            $lines[] = $known;
        }

        if ($has(ContextBucket::POSITION)) {
            $fin = $this->advice->buildFinancialContext($ctx->user, null, $ctx->classification);
            $lines[] = "<financial_context>\n{$fin}\n</financial_context>";
            $rec = $this->advice->buildExistingRecordsSummary($ctx->user, $ctx->classification);
            $lines[] = "<existing_records>\n{$rec}\n</existing_records>";
        }

        if ($has(ContextBucket::READINESS)) {
            $lines[] = $this->advice->buildPrerequisiteStateContext($ctx->user);
        }

        if ($has(ContextBucket::CAPTURE)) {
            $focus = (string) $ctx->onboardingFocus;
            $lines[] = FynCaptureTurnInstructions::render(
                $this->focusLabel($focus),
                implode(', ', OnboardingPromptBuilder::toolsForFocus($focus)),
            );
        }

        if ($ctx->isPreview) {
            $lines[] = '<preview_mode>'."\n"
                .'The user is previewing Fynla without a real account. You cannot create, '
                .'update, or delete any records. If they ask you to save anything, tell them '
                .'warmly it will be captured when they sign up, then continue helping with '
                .'analysis and questions.'."\n".'</preview_mode>';
        }

        $lines[] = '</context>';
        $lines[] = '<user_message>';
        $lines[] = UserContentSanitiser::clean($ctx->message);
        $lines[] = '</user_message>';

        return implode("\n", $lines);
    }

    private function resolveFirstName(\App\Models\User $user): string
    {
        $first = trim((string) ($user->first_name ?? ''));
        if ($first === '') {
            $parts = explode(' ', (string) $user->name);
            $first = $parts[0] !== '' ? $parts[0] : 'there';
        }

        return $first;
    }

    private function focusLabel(?string $focus): string
    {
        return match ($focus) {
            'savings', 'budgeting' => 'Cash & Savings',
            'investment' => 'Investments',
            'retirement' => 'Retirement',
            'protection' => 'Protection',
            'estate' => 'Estate Planning',
            'business' => 'Business',
            'goals' => 'Goals',
            'savetax' => 'SaveTax',
            default => (string) $focus,
        };
    }
}
```

Note: `UserContentSanitiser::clean`/`wrap` are the existing helpers used by both legacy builders — confirm the exact class path with `grep -rn "class UserContentSanitiser" app/` and match the namespace in the `use` statement. The preview-notice wording is the spec's condensed dynamic version (preview is no longer in the static prompt — Task 3 excluded `getPreviewMode()`); it is new dynamic copy, not a reworded compliance rule, so it is permitted under D4.

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php`
Expected: PASS (5 passed). If `moduleContextFor` is missing, complete Step 3.

- [ ] **Step 6: Run the full unit + architecture suites**

Run: `./vendor/bin/pest --testsuite=Unit,Architecture`
Expected: PASS. If `ApplicationArchitectureTest` flags the new `App\Services\AI\Fyn` namespace, add it to the same allowlist mechanism used for other AI services (search `tests/Architecture/ApplicationArchitectureTest.php` for `Services\AI` and mirror the existing entry).

- [ ] **Step 7: Commit**

```bash
git add app/Services/AI/Fyn/FynContextAssembler.php app/Services/AI/AdvicePromptBuilder.php tests/Unit/Services/AI/Fyn/FynContextAssemblerTest.php tests/Architecture/ApplicationArchitectureTest.php
git commit -m "feat(fyn): FynContextAssembler — dynamic user-turn context block

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7: Wire the flag into the advice seam (HasAiChat)

The advice path builds the system prompt at `HasAiChat::buildSystemPrompt()` (`app/Traits/HasAiChat.php:787-811`) and assembles `$messageHistory` at `:152`. In unified mode: system prompt = `FynSystemPrompt::text()`, and the assembled context is prepended in-memory to the last user message of `$messageHistory` (never persisted — `saveMessage` at `:122` already stored the raw message; we must not mutate the stored row).

**Files:**
- Modify: `app/Traits/HasAiChat.php`
- Test: `tests/Feature/Fyn/UnifiedPromptAdviceSeamTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\AiConversation;
use App\Models\User;
use App\Services\AI\Fyn\FynSystemPrompt;

it('legacy mode still uses AdvicePromptBuilder output', function (): void {
    config()->set('fyn.prompt_architecture', 'legacy');
    $user = User::factory()->create(['onboarding_completed' => true]);
    $agent = app(App\Agents\CoordinatingAgent::class);

    $prompt = (fn () => $this->buildSystemPrompt($user))->call($agent);

    expect($prompt)->toContain('<user_profile>'); // legacy interleaves profile into system
});

it('unified mode returns the static system prompt verbatim', function (): void {
    config()->set('fyn.prompt_architecture', 'unified');
    $user = User::factory()->create(['onboarding_completed' => true]);
    $agent = app(App\Agents\CoordinatingAgent::class);

    $prompt = (fn () => $this->buildSystemPrompt($user))->call($agent);

    expect($prompt)->toBe(FynSystemPrompt::text());
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/UnifiedPromptAdviceSeamTest.php`
Expected: FAIL — unified test returns legacy 12-layer string, not `FynSystemPrompt::text()`.

- [ ] **Step 3: Branch buildSystemPrompt**

In `app/Traits/HasAiChat.php`, at the top of `buildSystemPrompt()` (line 793, before `$builder = app(AdvicePromptBuilder::class);`), add:

```php
if (\App\Services\AI\Fyn\FynPromptMode::isUnified()) {
    return \App\Services\AI\Fyn\FynSystemPrompt::text();
}
```

Leave the entire legacy body below untouched.

- [ ] **Step 4: Inject context into the in-memory message history**

In `app/Traits/HasAiChat.php`, immediately after `$messageHistory = $this->buildMessageHistory($conversation);` (line 152), add:

```php
if (\App\Services\AI\Fyn\FynPromptMode::isUnified()) {
    $messageHistory = $this->injectUnifiedTurnContext(
        $messageHistory,
        $user,
        $message,
        $currentRoute,
        $classification,
        $conversation,
    );
}
```

Then add this private method to the trait (place it next to `buildSystemPrompt`):

```php
/**
 * Unified mode: replace the last user message's content with the
 * FynContextAssembler block (context + the message itself). In-memory
 * only — the persisted row keeps the raw message. The onboarding seam
 * (Task 8) sets $this->unifiedOnboardingFocus before delegating; when
 * it is null this is an advice turn.
 */
private function injectUnifiedTurnContext(
    array $messageHistory,
    \App\Models\User $user,
    string $message,
    ?string $currentRoute,
    ?array $classification,
    \App\Models\AiConversation $conversation,
): array {
    $focus = $this->unifiedOnboardingFocus;
    $ctx = \App\Services\AI\Fyn\FynTurnContext::make(
        user: $user,
        message: $message,
        currentRoute: $currentRoute,
        mode: $focus !== null ? 'onboarding' : 'advice',
        onboardingFocus: $focus,
        isPreview: (bool) $user->is_preview_user,
        classification: $classification,
        conversation: $conversation,
    );

    $block = app(\App\Services\AI\Fyn\FynContextAssembler::class)->build($ctx);

    for ($i = count($messageHistory) - 1; $i >= 0; $i--) {
        if (($messageHistory[$i]['role'] ?? null) === 'user') {
            $messageHistory[$i]['content'] = $block;
            break;
        }
    }

    return $messageHistory;
}
```

Add the backing property near the other override properties (`HasAiChat.php:79`, beside `private ?string $systemPromptOverride = null;`):

```php
private ?string $unifiedOnboardingFocus = null;

public function setUnifiedOnboardingFocus(?string $focus): void
{
    $this->unifiedOnboardingFocus = $focus;
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Fyn/UnifiedPromptAdviceSeamTest.php`
Expected: PASS (2 passed).

- [ ] **Step 6: Regression — legacy path unchanged**

Run: `./vendor/bin/pest --testsuite=Unit,Feature,Architecture`
Expected: PASS. Any failure here means the legacy branch was disturbed — revert and re-isolate the change to the two `if (FynPromptMode::isUnified())` guards only.

- [ ] **Step 7: Commit**

```bash
git add app/Traits/HasAiChat.php tests/Feature/Fyn/UnifiedPromptAdviceSeamTest.php
git commit -m "feat(fyn): wire prompt-arch flag into advice seam (HasAiChat)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 8: Wire the flag into the onboarding seam (OnboardingChatDirector)

`OnboardingChatDirector` builds the restricted prompt at `:1727` (`$restrictedPrompt = $this->promptBuilder->buildAssetCapturePrompt(...)`) then calls `coordinatingAgent->chatWithPromptOverride(...)`. In unified mode the system override becomes `FynSystemPrompt::text()` and the onboarding focus is handed to the agent so Task 7's `injectUnifiedTurnContext` builds the onboarding context.

**Files:**
- Modify: `app/Services/Onboarding/OnboardingChatDirector.php`
- Test: `tests/Feature/Fyn/UnifiedPromptOnboardingSeamTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Fyn\FynSystemPrompt;
use App\Services\Onboarding\OnboardingChatDirector;

it('unified onboarding turn uses the static prompt + sets focus on the agent', function (): void {
    config()->set('fyn.prompt_architecture', 'unified');
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'onboarding_fyn_selection' => 'savings',
    ]);

    // The director's restricted-prompt builder must return the static
    // prompt and pass the focus through. Assert via the seam helper the
    // director uses to compute the override (extracted in Step 3).
    $director = app(OnboardingChatDirector::class);
    [$prompt, $focus] = (fn () => $this->resolveUnifiedRestrictedPrompt($user, 'savings'))
        ->call($director);

    expect($prompt)->toBe(FynSystemPrompt::text())
        ->and($focus)->toBe('savings');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/pest tests/Feature/Fyn/UnifiedPromptOnboardingSeamTest.php`
Expected: FAIL — `resolveUnifiedRestrictedPrompt` does not exist.

- [ ] **Step 3: Extract the prompt resolution + branch it**

In `app/Services/Onboarding/OnboardingChatDirector.php`, add a private helper:

```php
/**
 * Unified mode: the system prompt is the static FynSystemPrompt and the
 * onboarding focus is carried separately so HasAiChat can build the
 * capture-turn context. Legacy mode: the verbatim asset-capture prompt.
 *
 * @return array{0:string,1:?string}  [systemPrompt, onboardingFocusOrNull]
 */
private function resolveUnifiedRestrictedPrompt(\App\Models\User $user, string $selection): array
{
    if (\App\Services\AI\Fyn\FynPromptMode::isUnified()) {
        return [\App\Services\AI\Fyn\FynSystemPrompt::text(), $selection];
    }

    return [$this->promptBuilder->buildAssetCapturePrompt($user, $selection), null];
}
```

Then replace the two lines at `:1727-1728`:

```php
$restrictedPrompt = $this->promptBuilder->buildAssetCapturePrompt($user, $selection, $conversation);
$allowedTools = OnboardingPromptBuilder::toolsForFocus($selection);
```

with:

```php
[$restrictedPrompt, $unifiedFocus] = $this->resolveUnifiedRestrictedPrompt($user, $selection);
$allowedTools = OnboardingPromptBuilder::toolsForFocus($selection);
if ($unifiedFocus !== null) {
    $this->coordinatingAgent->setUnifiedOnboardingFocus($unifiedFocus);
}
```

(`setUnifiedOnboardingFocus` was added to the agent via the `HasAiChat` trait in Task 7.) Keep `buildAssetCapturePrompt`'s third `$conversation` arg behaviour by passing `$conversation` through in the legacy branch of the helper — adjust the helper signature to accept and forward `?AiConversation $conversation = null` and pass it in the legacy `buildAssetCapturePrompt(...)` call so legacy behaviour is byte-identical.

- [ ] **Step 4: Reset the focus after the turn**

Find where the director finishes the delegated `chatWithPromptOverride` generator (the `try { ... }` block opened at `:1729`). After the generator is fully consumed (in a `finally` or immediately after the loop completes), add:

```php
$this->coordinatingAgent->setUnifiedOnboardingFocus(null);
```

so a subsequent advice turn on the same agent instance is not misclassified as onboarding.

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/pest tests/Feature/Fyn/UnifiedPromptOnboardingSeamTest.php`
Expected: PASS (1 passed).

- [ ] **Step 6: Regression — both onboarding paths**

Run: `./vendor/bin/pest --testsuite=Unit,Feature,Architecture --filter=Onboarding`
Expected: PASS. Then full: `./vendor/bin/pest --testsuite=Unit,Feature,Architecture` → PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/Onboarding/OnboardingChatDirector.php tests/Feature/Fyn/UnifiedPromptOnboardingSeamTest.php
git commit -m "feat(fyn): wire prompt-arch flag into onboarding seam

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 9: Eval parity gate (loop to green)

Acceptance per spec §10 + CLAUDE.md Rule #15: `FYN_PROMPT_ARCH=unified` must be **≥** the `legacy` baseline on the Fyn eval suite, with **zero regression** on scenario category `09-canonical-behaviour`. This task is a measure → diagnose → fix → re-measure loop, not a one-shot.

**Files:**
- Modify (only if a real defect is found): any Task 3–8 file
- Create: `April/May16Updates/fyn-prompt-rework-parity.md` (results log)

- [ ] **Step 1: Capture the legacy baseline**

Run the Fyn eval suite under legacy:

```bash
FYN_PROMPT_ARCH=legacy ./vendor/bin/pest tests/Feature/Fyn/Eval --testsuite=Feature 2>&1 | tee /tmp/fyn-eval-legacy.txt
```

(If the project exposes a dedicated eval entrypoint, use it; the suite lives at `tests/Feature/Fyn/Eval` and is driven by `tests/Feature/Fyn/Eval/EvalRunner::run()`. Confirm the invocation with `grep -rn "EvalRunner::run\|it(\|describe(" tests/Feature/Fyn/Eval/*.php | head`.)
Record pass counts per scenario category, especially `09-canonical-behaviour`, into `April/May16Updates/fyn-prompt-rework-parity.md` under a "Legacy baseline" heading.

- [ ] **Step 2: Capture the unified run**

```bash
FYN_PROMPT_ARCH=unified ./vendor/bin/pest tests/Feature/Fyn/Eval --testsuite=Feature 2>&1 | tee /tmp/fyn-eval-unified.txt
```

Record the same metrics under an "Unified run" heading.

- [ ] **Step 3: Diff and classify**

```bash
diff <(grep -E "✓|✗|PASS|FAIL" /tmp/fyn-eval-legacy.txt) <(grep -E "✓|✗|PASS|FAIL" /tmp/fyn-eval-unified.txt) || true
```

For every scenario where unified < legacy: use `superpowers:systematic-debugging` to root-cause with file:line evidence (which bucket dropped a block? a generalised wording delta changed model behaviour? onboarding focus not propagated?). Log each defect + root cause in the parity file.

- [ ] **Step 4: Fix root causes (loop)**

Fix in the Task 3–8 files. Common expected causes and their fix locus:
- Compliance regression from a generalisation delta → re-check Task 3 heredoc against the cited source ranges; the only permitted deltas are the two documented ones.
- Onboarding capture quality drop → `FynCaptureTurnInstructions` text fidelity (Task 4) or focus propagation (Task 8 Step 3/4).
- Missing financial data on a module query → `FynContextSelector` bucket logic (Task 5) or assembler block wiring (Task 6).
- Handoff/write-intent regression → `<tool_use>` block order/wording in Task 3 (handoff block must remain present and emphatic).

After each fix, re-run Step 2 + Step 3. Repeat until unified ≥ legacy on every metric and `09-canonical-behaviour` has zero regressions.

- [ ] **Step 5: Full regression**

Run: `./vendor/bin/pest` (full suite, default = legacy flag)
Expected: PASS (940+).
Run: `FYN_PROMPT_ARCH=unified ./vendor/bin/pest`
Expected: PASS — no non-eval test depends on prompt internals; any failure is a real coupling defect to fix.

- [ ] **Step 6: Browser verification (Rule #15, NON-NEGOTIABLE)**

With `FYN_PROMPT_ARCH=unified` set for the dev server, via Playwright on `http://localhost:8000` (local dev — fetch MFA code from DB per CLAUDE.md):
1. Log in as `john@example.com` / `password`.
2. Advice turn: ask "How is my pension doing?" → verify a personalised, hedged answer with the FCA signposting final line, no IDs/routes leaked.
3. Write-intent turn: "Add a Cash ISA with Nationwide, £5,000, 4.5%" → verify it persists (DB row) via the handoff, no fabricated success.
4. Onboarding: register a fresh user, pick a focus, give a multi-entity message ("Halifax ISA £10k and Nationwide saver £5k") → verify both rows created in one turn, ≤15-word acknowledgement.
Record outcomes in the parity file. If any journey fails, return to Step 3.

- [ ] **Step 7: Commit**

```bash
git add April/May16Updates/fyn-prompt-rework-parity.md
git add -A   # any root-cause fixes made during the loop
git commit -m "test(fyn): unified prompt parity gate green vs legacy baseline

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 10: Rewrite canonical contract + consolidate prompt docs + tag

**Files:**
- Modify: `April/April24Updates/spec/00-canonical.md`
- Create: `prompts/fyn-system-prompt.md`
- Move: `prompts/advice-system-prompt.md`, `prompts/onboarding-system-prompt.md` → `prompts/archive/`
- Tag: `fyn-two-prompt-pre-unify`

- [ ] **Step 1: Tag the pre-cutover state**

```bash
git tag fyn-two-prompt-pre-unify HEAD~1
git tag --list | grep fyn-two-prompt-pre-unify
```

(`HEAD~1` = the commit before this doc task, i.e. the last code/eval commit — the clean reference point per spec §8.)

- [ ] **Step 2: Replace the canonical contract body**

Overwrite the body of `April/April24Updates/spec/00-canonical.md` with the rewritten contract from the design spec §9 (`docs/superpowers/specs/2026-05-16-fyn-prompt-rework-design.md`), verbatim, keeping the file's existing top matter/banner lines. This is a paste of the approved §9 text — open the design spec, copy §9's blockquote, paste as the new contract body.

- [ ] **Step 3: Consolidate the prompt docs**

```bash
mkdir -p prompts/archive
git mv prompts/advice-system-prompt.md prompts/archive/advice-system-prompt.md
git mv prompts/onboarding-system-prompt.md prompts/archive/onboarding-system-prompt.md
git mv prompts/advice-system-prompt.pdf prompts/archive/advice-system-prompt.pdf
git mv prompts/onboarding-system-prompt.pdf prompts/archive/onboarding-system-prompt.pdf
```

Create `prompts/fyn-system-prompt.md` documenting the unified architecture: a short intro (one prompt, two write states enforced at dispatch+tool-gating), the block list from Task 3, the dynamic context shape from Task 6, and a pointer to `docs/superpowers/specs/fyn-system-prompt.snapshot.txt` as the canonical rendered text. Add a header line: `> Archived per-state docs (pre-2026-05-16 two-prompt architecture): prompts/archive/`.

- [ ] **Step 4: Update CLAUDE.md Fyn section pointer**

In `/Users/CSJ/Desktop/fynla/CLAUDE.md`, the "Fyn AI — Two-Fyn architecture" paragraph: replace the sentence describing two prompt builders with one sentence stating the prompt is now unified (`FynSystemPrompt` + `FynContextAssembler`, flag `FYN_PROMPT_ARCH`), the two write states remain enforced at dispatch + tool-gating, and the canonical source is unchanged (`April/April24Updates/spec/00-canonical.md`). Change only that paragraph — scope discipline.

- [ ] **Step 5: Commit**

```bash
git add April/April24Updates/spec/00-canonical.md prompts/ CLAUDE.md
git commit -m "docs(fyn): rewrite canonical contract for unified prompt; archive per-state docs

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Self-Review

**Spec coverage:**
- §3 D1 scope (prompt+turn only) → Tasks 7/8 branch only at the two seams; D2 selector → Task 5; D3 static → Task 3; D4 preserve wording → Tasks 3/4 cite exact source ranges + only two documented deltas; D5 flag → Task 1; D6 rewrite canonical → Task 10. ✓
- §4 new units → Tasks 2 (VO/enum), 3 (system), 4 (capture), 5 (selector), 6 (assembler). ✓
- §5 static prompt composition → Task 3 (cited ranges + deltas + byte-stability test). ✓
- §6 user-turn shape + 4-bucket selector + mode-independent known-facts → Tasks 5/6 (assembler always tries known-facts). ✓
- §7 onboarding integration (state machine unchanged, capture rules verbatim) → Tasks 4/8. ✓
- §8 flag/archival/tag → Tasks 1/10. ✓
- §10 eval parity gate + Rule #15 loop + browser → Task 9. ✓
- §11 out of scope respected (no CoordinatingAgent handler/audit/gate/tool changes). ✓
- §12 risks → Task 9 Step 4 enumerates each risk's fix locus. ✓

**Placeholder scan:** Large prompt blocks in Tasks 3/4 are specified as "paste verbatim from `<exact file:line>` with these two documented deltas" — this is an exact, executable instruction with pinned sources, not a TBD. All new code (config, VO, enum, selector, assembler, wiring) is shown in full.

**Type consistency:** `FynPromptMode::isUnified()` (T1) used in T7/T8. `FynTurnContext::make(...)` signature (T2) matches all call sites (T6 assembler, T7 injector). `FynContextSelector::buckets()` returns `list<ContextBucket>` (T5) consumed via `in_array(..., true)` (T6). `FynContextAssembler::build()` (T6) called by T7 injector. `setUnifiedOnboardingFocus()` added in T7, called in T8. `moduleContextFor()` added in T6 Step 3, used in T6 assembler. Consistent. ✓
