<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\User;

/**
 * Pure state config for the Fyn-driven onboarding flow.
 *
 * The backend owns the full state machine — Claude is only called for
 * asset_capture. Every other state is emitted deterministically from this
 * file by OnboardingChatDirector (see `turn_type` to decide).
 *
 * Plan: April/April15Updates/fynOnboardFix.md §5.
 *
 * NO ICONS RULE: Bubble entries are {id, label} ONLY. Per the NO ICONS
 * rule in CLAUDE.md §14 and fynlaDesignGuide.md v1.4.0, no emoji, SVG,
 * Unicode symbol, or glyph field may be added to bubbles, state prompts,
 * or any output emitted by this machine.
 *
 * State record shape:
 *   turn_type:    'bubbles' | 'free_text' | 'delegated' | 'terminal'
 *   prompt_text:  string | callable(User): string
 *                 (uses {first_name} / {selection} template tokens when string)
 *   bubbles:      array<{id, label}>  — only for turn_type='bubbles', NO ICONS
 *   capture_field: 'users.column_name' | null
 *                 (null for scratch-pad-only or FamilyMember-creating states)
 *   value_parser: OnboardingValueInterpreter method name | null
 *   next:         string (state id) | callable(string $answer, User $user): string
 *   skip_if:      callable(User): bool | null
 */
final class OnboardingStateMachine
{
    public const STATE_PATH_CHOICE = 'path_choice';

    public const STATE_JOURNEY_SELECTION = 'journey_selection';

    public const STATE_FOCUS_SELECTION = 'focus_selection';

    // base_personal replaces the old base_dob + base_marital pair — one
    // grouped_extract turn captures both at once via Claude.
    public const STATE_BASE_PERSONAL = 'base_personal';

    public const STATE_BASE_SPOUSE = 'base_spouse';

    public const STATE_BASE_DEPENDANTS = 'base_dependants';

    public const STATE_BASE_DEPENDANTS_DETAIL = 'base_dependants_detail';

    public const STATE_BASE_EMPLOYMENT = 'base_employment';

    // base_work replaces the old base_occupation + base_income pair —
    // one grouped_extract turn captures employer, occupation, and income
    // for employed / self-employed / part-time users.
    public const STATE_BASE_WORK = 'base_work';

    public const STATE_BASE_RETIREMENT_DATE = 'base_retirement_date';

    public const STATE_BASE_EXPENDITURE = 'base_expenditure';

    public const STATE_ASSET_CAPTURE = 'asset_capture';

    public const STATE_ADD_MORE = 'add_more';

    public const STATE_DONE = 'done';

    /**
     * Complete state table. See fynOnboardFix.md §5.2 for the reference
     * table this was built from.
     */
    public static function states(): array
    {
        return [
            self::STATE_PATH_CHOICE => [
                'turn_type' => 'bubbles',
                'prompt_text' => "Hi {first_name}, I'm Fyn — welcome to Fynla. I'll help you set up your financial plan. To start, do you want to follow a life-stage journey or pick a single module focus?",
                'bubbles' => [
                    ['id' => 'journey', 'label' => 'Follow a journey'],
                    ['id' => 'focus', 'label' => 'Pick a focus'],
                ],
                'capture_field' => 'onboarding_fyn_path',
                'next' => self::class.'::nextFromPathChoice',
            ],
            self::STATE_JOURNEY_SELECTION => [
                'turn_type' => 'bubbles',
                'prompt_text' => 'Which journey fits your situation best?',
                'bubbles' => [
                    [
                        'id' => 'budgeting',
                        'label' => 'Starting out',
                        'description' => 'Build strong financial habits for your future.',
                    ],
                    [
                        'id' => 'goals',
                        'label' => 'Building foundations',
                        'description' => 'Save, invest, and grow your wealth with confidence.',
                    ],
                    [
                        'id' => 'protection',
                        'label' => 'Protecting and growing',
                        'description' => 'Ensure your family and assets are fully covered.',
                    ],
                    [
                        'id' => 'retirement',
                        'label' => 'Planning your future',
                        'description' => 'Retirement projections, pension tracking, and estate planning.',
                    ],
                    [
                        'id' => 'estate',
                        'label' => 'Enjoying your wealth',
                        'description' => 'Make the most of your financial success.',
                    ],
                ],
                'capture_field' => 'onboarding_fyn_selection',
                'next' => self::STATE_BASE_PERSONAL,
            ],
            self::STATE_FOCUS_SELECTION => [
                'turn_type' => 'bubbles',
                'prompt_text' => 'Which area would you like me to focus on first?',
                'bubbles' => [
                    ['id' => 'savings', 'label' => 'Savings'],
                    ['id' => 'investment', 'label' => 'Investment'],
                    ['id' => 'retirement', 'label' => 'Retirement'],
                    ['id' => 'protection', 'label' => 'Protection'],
                ],
                'capture_field' => 'onboarding_fyn_selection',
                'next' => self::STATE_BASE_PERSONAL,
            ],
            self::STATE_BASE_PERSONAL => [
                'turn_type' => 'grouped_extract',
                'prompt_text' => self::class.'::buildPersonalPrompt',
                'extraction_tool' => 'capture_personal_details',
                'retry_text' => "Sorry, I didn't catch both pieces. Could you tell me your date of birth (something like 12 January 1985) and your marital status?",
                'next' => self::class.'::nextFromPersonal',
                'skip_if' => [self::class, 'skipIfPersonalComplete'],
            ],
            self::STATE_BASE_SPOUSE => [
                'turn_type' => 'grouped_extract',
                'prompt_text' => self::class.'::buildSpousePrompt',
                'extraction_tool' => 'capture_spouse_details',
                'retry_text' => "I need a first name, date of birth, and email address for your partner so I can create and link their account. Could you share those again?",
                'next' => self::STATE_BASE_DEPENDANTS,
            ],
            self::STATE_BASE_DEPENDANTS => [
                'turn_type' => 'bubbles',
                'prompt_text' => 'Any children or dependants to add?',
                'bubbles' => [
                    ['id' => 'yes', 'label' => 'Yes'],
                    ['id' => 'no', 'label' => 'No'],
                ],
                'capture_field' => null, // written to onboarding_fyn_context.has_dependants
                'next' => self::class.'::nextFromDependants',
            ],
            self::STATE_BASE_DEPENDANTS_DETAIL => [
                'turn_type' => 'grouped_extract',
                'prompt_text' => 'Lovely. Tell me their first names, ages, and how they are related to you (child, parent, or other dependant). You can list several in one go.',
                'extraction_tool' => 'capture_dependants',
                'retry_text' => 'Could you list them again with ages and how they are related? Something like "Alice 7 child, Bob 4 child".',
                'next' => self::STATE_BASE_EMPLOYMENT,
            ],
            self::STATE_BASE_EMPLOYMENT => [
                'turn_type' => 'bubbles',
                'prompt_text' => "And what's your employment situation at the moment?",
                'bubbles' => [
                    ['id' => 'employed', 'label' => 'Employed'],
                    ['id' => 'self_employed', 'label' => 'Self-employed'],
                    ['id' => 'part_time', 'label' => 'Part-time'],
                    ['id' => 'retired', 'label' => 'Retired'],
                    ['id' => 'unemployed', 'label' => 'Not working'],
                    ['id' => 'other', 'label' => 'Other'],
                ],
                'capture_field' => 'employment_status',
                'value_parser' => 'parseEmploymentFromText',
                'next' => self::class.'::nextFromEmployment',
                'skip_if' => [self::class, 'skipIfEmploymentSet'],
            ],
            self::STATE_BASE_WORK => [
                'turn_type' => 'grouped_extract',
                'prompt_text' => self::class.'::buildWorkPrompt',
                'extraction_tool' => 'capture_work_details',
                'retry_text' => 'I need three things: the company or trade name, your position, and your gross annual income in GBP. Could you share all three?',
                'next' => self::STATE_BASE_EXPENDITURE,
            ],
            self::STATE_BASE_RETIREMENT_DATE => [
                'turn_type' => 'free_text',
                'prompt_text' => 'When did you retire? A year is fine — something like "2020".',
                'capture_field' => 'retirement_date',
                'value_parser' => 'parseRetirementDate',
                'next' => self::STATE_BASE_EXPENDITURE,
            ],
            self::STATE_BASE_EXPENDITURE => [
                'turn_type' => 'free_text',
                'prompt_text' => JourneyFieldResolver::getFynPrompt('monthly_expenditure'),
                'capture_field' => 'monthly_expenditure',
                'value_parser' => 'parseExpenditureAmount',
                'next' => self::STATE_ASSET_CAPTURE,
                'skip_if' => [self::class, 'skipIfExpenditureSet'],
            ],
            self::STATE_ASSET_CAPTURE => [
                'turn_type' => 'delegated',
                // Prompt is built at runtime by OnboardingPromptBuilder based
                // on the selected focus. The string below is only used as a
                // Fyn intro message rendered BEFORE the Claude delegation.
                'prompt_text' => self::class.'::buildAssetCaptureIntro',
                'capture_field' => null,
                'next' => self::STATE_ADD_MORE,
            ],
            self::STATE_ADD_MORE => [
                'turn_type' => 'bubbles',
                'prompt_text' => "Anything else you'd like to cover?",
                'bubbles' => [
                    // Dynamic per-user — director strips already-visited focuses
                    // and always appends the "I'm done" bubble. Static config here
                    // lists the full option set for reference.
                    ['id' => 'savings', 'label' => 'Savings'],
                    ['id' => 'investment', 'label' => 'Investment'],
                    ['id' => 'retirement', 'label' => 'Retirement'],
                    ['id' => 'protection', 'label' => 'Protection'],
                    ['id' => 'done', 'label' => "I'm done"],
                ],
                'capture_field' => null,
                'next' => self::class.'::nextFromAddMore',
            ],
            self::STATE_DONE => [
                'turn_type' => 'terminal',
                'prompt_text' => "All set, {first_name}. Your {selection} module is ready to explore.",
                'capture_field' => null,
                'next' => null,
            ],
        ];
    }

    public static function getState(string $stateId): ?array
    {
        return self::states()[$stateId] ?? null;
    }

    /**
     * Compute the next state id given the current state, the interpreted
     * answer, and the user (used to evaluate skip_if on target states).
     *
     * Returns null if the state has no `next` (terminal).
     */
    public static function getNextStateId(string $currentStateId, string $answer, User $user): ?string
    {
        $state = self::getState($currentStateId);
        if ($state === null) {
            return null;
        }

        $next = $state['next'] ?? null;
        if ($next === null) {
            return null;
        }

        // Static string → follow directly
        if (is_string($next) && ! self::isCallableReference($next)) {
            return self::applySkipRules($next, $user);
        }

        // Callable reference (Class::method or [class, method])
        if (is_string($next) && self::isCallableReference($next)) {
            $target = self::invokeCallableString($next, $answer, $user);

            return $target === null ? null : self::applySkipRules($target, $user);
        }

        if (is_callable($next)) {
            $target = $next($answer, $user);

            return is_string($target) ? self::applySkipRules($target, $user) : null;
        }

        return null;
    }

    /**
     * Follow skip_if rules transitively — if the target state's skip_if
     * returns true, advance to its own next state without user interaction.
     * Handles both static next state ids and callable next references
     * (e.g. nextFromPersonal which branches on marital_status). Bounded
     * to avoid infinite loops with bad config.
     */
    public static function applySkipRules(string $targetStateId, User $user, int $depth = 0): ?string
    {
        if ($depth > 20) {
            return $targetStateId; // guard against config cycles
        }

        $state = self::getState($targetStateId);
        if ($state === null) {
            return $targetStateId;
        }

        $skipIf = $state['skip_if'] ?? null;
        if ($skipIf === null || ! is_callable($skipIf)) {
            return $targetStateId;
        }

        if (! $skipIf($user)) {
            return $targetStateId;
        }

        // Skip this state — evaluate its 'next' without a user answer.
        // For callable next references, pass an empty answer string — the
        // branching helpers in this file use `$user` state (marital_status,
        // employment_status, etc.) to pick the target, so the empty answer
        // is safe.
        $next = $state['next'] ?? null;
        if ($next === null) {
            return null;
        }

        if (is_string($next) && ! self::isCallableReference($next)) {
            return self::applySkipRules($next, $user, $depth + 1);
        }

        if (is_string($next) && self::isCallableReference($next)) {
            $target = self::invokeCallableString($next, '', $user);
            if (is_string($target)) {
                return self::applySkipRules($target, $user, $depth + 1);
            }
        }

        if (is_callable($next)) {
            $target = $next('', $user);
            if (is_string($target)) {
                return self::applySkipRules($target, $user, $depth + 1);
            }
        }

        return $targetStateId;
    }

    // ─── Branching helpers (referenced by state next values) ─────────────

    public static function nextFromPathChoice(string $answer): string
    {
        $normalised = mb_strtolower(trim($answer));
        if (str_contains($normalised, 'journey')) {
            return self::STATE_JOURNEY_SELECTION;
        }

        return self::STATE_FOCUS_SELECTION;
    }

    /**
     * After base_personal (grouped DOB + marital_status), branch:
     *  - partial capture (either field still null) → stay on base_personal
     *    so buildPersonalPrompt (FR-M10) can pre-confirm the captured
     *    field and ask for the missing one
     *  - married or civil_partnership → base_spouse
     *  - single / divorced / widowed → base_dependants
     */
    public static function nextFromPersonal(string $answer, User $user): string
    {
        // FR-M10 — stay on base_personal until both fields are captured.
        // Needed because capture_personal_details now accepts partial
        // payloads; without this guard a DOB-only reply would branch
        // straight to base_dependants with marital_status still null.
        if (empty($user->date_of_birth) || empty($user->marital_status)) {
            return self::STATE_BASE_PERSONAL;
        }

        $marital = $user->marital_status ?? '';
        if (in_array($marital, ['married', 'civil_partnership'], true)) {
            return self::STATE_BASE_SPOUSE;
        }

        return self::STATE_BASE_DEPENDANTS;
    }

    public static function nextFromDependants(string $answer): string
    {
        $normalised = mb_strtolower(trim($answer));
        if ($normalised === 'yes' || str_starts_with($normalised, 'yes')) {
            return self::STATE_BASE_DEPENDANTS_DETAIL;
        }

        return self::STATE_BASE_EMPLOYMENT;
    }

    /**
     * After base_employment bubble pick:
     *  - employed / full_time / self_employed / part_time → base_work
     *    (grouped employer + occupation + income)
     *  - retired → base_retirement_date
     *  - unemployed / other → straight to base_expenditure (no income to ask)
     */
    public static function nextFromEmployment(string $answer, User $user): string
    {
        $status = $user->employment_status ?? '';
        if (in_array($status, ['employed', 'full_time', 'part_time', 'self_employed'], true)) {
            return self::STATE_BASE_WORK;
        }

        if ($status === 'retired') {
            return self::STATE_BASE_RETIREMENT_DATE;
        }

        return self::STATE_BASE_EXPENDITURE;
    }

    /**
     * FR-M10 — hybrid base_personal prompt. When neither DOB nor marital
     * is set, falls back to the full grouped prompt. When exactly one is
     * already captured, pre-confirms it and asks only for the missing
     * field. The both-set case is handled upstream by skip_if.
     */
    public static function buildPersonalPrompt(string $answer, User $user): string
    {
        $hasDob = ! empty($user->date_of_birth);
        $hasMarital = ! empty($user->marital_status);

        if (! $hasDob && ! $hasMarital) {
            return "Let me grab a few basics first, {first_name}. What's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?";
        }

        if ($hasDob && ! $hasMarital) {
            $dob = $user->date_of_birth instanceof \DateTimeInterface
                ? $user->date_of_birth
                : new \DateTimeImmutable((string) $user->date_of_birth);
            $formatted = $dob->format('j F Y');

            return "Got it — I have you down as born on {$formatted}. Are you single, married, in a civil partnership, or have you been through a separation as a widow or widower?";
        }

        // Marital set, DOB missing.
        $maritalWord = match ($user->marital_status) {
            'married' => 'married',
            'civil_partnership' => 'in a civil partnership',
            'single' => 'single',
            'divorced' => 'divorced',
            'widowed' => 'widowed',
            default => (string) $user->marital_status,
        };

        return "Thanks — I have you noted as {$maritalWord}. Could you share your date of birth? Something like 12 January 1985 is fine.";
    }

    /**
     * Builds a personalised spouse prompt that uses "partner" for civil
     * partnership and "spouse" for married. Called at runtime from the
     * grouped_extract turn config.
     */
    public static function buildSpousePrompt(string $answer, User $user): string
    {
        $isCivilPartnership = ($user->marital_status ?? '') === 'civil_partnership';
        $word = $isCivilPartnership ? 'partner' : 'spouse';

        return "Great — let's add your {$word}'s details. Can you share their first name, date of birth, and email address? I'll create an account and link the two of you so you can plan together.";
    }

    /**
     * Builds a personalised work prompt that matches the user's chosen
     * employment_status (self-employed users get "trade name" wording).
     */
    public static function buildWorkPrompt(string $answer, User $user): string
    {
        $status = $user->employment_status ?? 'employed';

        if ($status === 'self_employed') {
            return "Brilliant. Let me know your trade or business name, your main role, and your gross annual self-employment income — all in one go is fine.";
        }

        if ($status === 'part_time') {
            return "Lovely. Share the company you work for part-time, your position, and your gross annual income from that role.";
        }

        return "Brilliant. Share the company you work for, your position, and your gross annual income — all in one go is fine.";
    }

    public static function nextFromAddMore(string $answer, User $user): string
    {
        $normalised = mb_strtolower(trim($answer));
        if (str_starts_with($normalised, "i'm done")
            || str_starts_with($normalised, 'im done')
            || $normalised === 'done') {
            return self::STATE_DONE;
        }

        // Anything else advances back into asset_capture for the new
        // selection. The director updates user.onboarding_fyn_selection
        // before calling this helper, so subsequent state evaluation picks
        // up the new focus.
        return self::STATE_ASSET_CAPTURE;
    }

    public static function buildAssetCaptureIntro(string $answer, User $user): string
    {
        $selection = $user->onboarding_fyn_selection ?? 'savings';
        $nameParts = explode(' ', (string) $user->name);
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';

        $intros = [
            'savings' => "Right {$firstName}, let's get your savings mapped. Tell me about any cash accounts, ISAs, or savings pots — the provider, the balance, and whether it's an ISA or not. You can list several in one message and I'll add them all at once.",
            'investment' => "Now let's capture your investments, {$firstName}. Tell me about any Stocks & Shares ISAs, General Investment Accounts, or platforms you hold — provider, current value, and account type is plenty to start. List as many as you like in one go.",
            'retirement' => "Time to map your pensions, {$firstName}. Walk me through any you have — workplace pensions, personal pensions, Self-Invested Personal Pensions, or Defined Benefit schemes. For each one I need the provider or scheme name, and either the current fund value (for Defined Contribution) or the projected annual income (for Defined Benefit).",
            'protection' => "Let's look at your existing protection cover, {$firstName}. Tell me about any life insurance, critical illness cover, or income protection policies — the type, the provider, and the cover amount. If you don't have any yet, just say so and we'll come back to this once we've looked at the gaps.",
            'estate' => "Right, let's build up your estate picture, {$firstName}. Start with any property you own — address, type (main residence, second home, or buy-to-let), and rough current value. You can also mention valuables, gifts you've made in the last seven years, or business interests in the same message.",
            'business' => "Tell me about your business interests, {$firstName}. For each one I need the trading name, the entity type (sole trader, partnership, or limited company), your ownership percentage, and a rough current valuation. List as many as you own.",
            'goals' => "What are you working towards, {$firstName}? A house deposit, early retirement, school fees, a dream holiday — tell me about your financial goals. For each one: a short name, a rough target amount, and when you'd like to hit it by.",
            'budgeting' => "Let's look at your monthly budget, {$firstName}. Share the headline spending categories — housing, bills, food, transport, entertainment — with rough monthly figures. I'll use them to work out your realistic savings capacity.",
        ];

        return $intros[$selection] ?? $intros['savings'];
    }

    // ─── skip_if helpers ─────────────────────────────────────────────────

    /**
     * Skip base_personal only if BOTH date_of_birth AND marital_status
     * are already set. Either one missing means we still need to run
     * the grouped_extract turn.
     */
    public static function skipIfPersonalComplete(User $user): bool
    {
        return ! empty($user->date_of_birth) && ! empty($user->marital_status);
    }

    public static function skipIfEmploymentSet(User $user): bool
    {
        return ! empty($user->employment_status);
    }

    public static function skipIfExpenditureSet(User $user): bool
    {
        return (float) ($user->monthly_expenditure ?? 0) > 0
            || (float) ($user->annual_expenditure ?? 0) > 0;
    }

    /**
     * Match a bubble click (where the label becomes the next user message)
     * against the current state's bubble config. Case-insensitive, trimmed,
     * substring-tolerant. Returns the matched bubble id, or null.
     */
    public static function matchBubble(string $stateId, string $userAnswer): ?string
    {
        $state = self::getState($stateId);
        if ($state === null || ($state['turn_type'] ?? '') !== 'bubbles') {
            return null;
        }

        $normalised = mb_strtolower(trim($userAnswer));
        $bubbles = $state['bubbles'] ?? [];

        // Exact label match first (fastest, most precise)
        foreach ($bubbles as $bubble) {
            $label = mb_strtolower(trim((string) ($bubble['label'] ?? '')));
            if ($label !== '' && $label === $normalised) {
                return (string) $bubble['id'];
            }
        }

        // Exact id match (e.g. user typed the slug directly)
        foreach ($bubbles as $bubble) {
            $id = mb_strtolower(trim((string) ($bubble['id'] ?? '')));
            if ($id !== '' && $id === $normalised) {
                return (string) $bubble['id'];
            }
        }

        // Substring fallback (e.g. "savings account" matches "Savings")
        foreach ($bubbles as $bubble) {
            $label = mb_strtolower(trim((string) ($bubble['label'] ?? '')));
            if ($label !== '' && (str_contains($normalised, $label) || str_contains($label, $normalised))) {
                return (string) $bubble['id'];
            }
        }

        return null;
    }

    /**
     * Interpolate {first_name}, {selection}, etc. into a prompt string.
     */
    public static function interpolate(string $template, User $user): string
    {
        $nameParts = explode(' ', (string) $user->name);
        $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';

        return strtr($template, [
            '{first_name}' => $firstName,
            '{selection}' => (string) ($user->onboarding_fyn_selection ?? ''),
            '{path}' => (string) ($user->onboarding_fyn_path ?? ''),
        ]);
    }

    /**
     * Resolve a prompt_text that may be a plain string or a callable
     * reference. Strings are interpolated; callables receive (answer, user)
     * and return a fresh string.
     */
    public static function resolvePromptText(array $state, User $user, string $answer = ''): string
    {
        $promptText = $state['prompt_text'] ?? '';

        if (is_string($promptText) && self::isCallableReference($promptText)) {
            $result = self::invokeCallableString($promptText, $answer, $user);

            return is_string($result) ? self::interpolate($result, $user) : '';
        }

        if (is_callable($promptText)) {
            $result = $promptText($answer, $user);

            return is_string($result) ? self::interpolate($result, $user) : '';
        }

        return self::interpolate((string) $promptText, $user);
    }

    // ─── Internal helpers ────────────────────────────────────────────────

    private static function isCallableReference(string $value): bool
    {
        return str_contains($value, '::');
    }

    private static function invokeCallableString(string $reference, string $answer, User $user): mixed
    {
        [$class, $method] = explode('::', $reference, 2);
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }

        $reflection = new \ReflectionMethod($class, $method);
        $paramCount = $reflection->getNumberOfParameters();

        if ($paramCount === 0) {
            return $class::$method();
        }

        if ($paramCount === 1) {
            return $class::$method($answer);
        }

        return $class::$method($answer, $user);
    }
}
