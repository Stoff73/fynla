<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\AiConversation;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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

    // Phase 10 additions — multi-job loop + profile-review pauses.
    public const STATE_BASE_EMPLOYMENT_MORE = 'base_employment_more';

    public const STATE_PROFILE_REVIEW_FAMILY = 'profile_review_family';

    public const STATE_PROFILE_REVIEW_EXPENDITURE = 'profile_review_expenditure';

    public const STATE_ASSET_CAPTURE = 'asset_capture';

    public const STATE_ADD_MORE = 'add_more';

    public const STATE_DONE = 'done';

    // SaveTax campaign — sections 4-6 (post-expenditure branch for path=campaign).
    // Hangs off STATE_PROFILE_REVIEW_EXPENDITURE; bypasses STATE_ASSET_CAPTURE.
    // STATE_CAMPAIGN_INTRO is the consent gate before the asset/liability capture
    // flow begins — explains why we're shifting topic and asks "Okay" / "Nope".
    public const STATE_CAMPAIGN_INTRO = 'campaign_intro';

    public const STATE_CAMPAIGN_OCCUPATIONAL_SCHEME = 'campaign_occupational_scheme';

    public const STATE_CAMPAIGN_ISA_HOLDINGS = 'campaign_isa_holdings';

    public const STATE_CAMPAIGN_BANK_ACCOUNTS = 'campaign_bank_accounts';

    public const STATE_CAMPAIGN_INVESTMENT_ACCOUNTS = 'campaign_investment_accounts';

    public const STATE_CAMPAIGN_PENSION_CONTRIBS = 'campaign_pension_contribs';

    public const STATE_CAMPAIGN_PENSION_HISTORY = 'campaign_pension_history';

    public const STATE_CAMPAIGN_CHARITABLE_GIVING = 'campaign_charitable_giving';

    public const STATE_CAMPAIGN_SPOUSE_WORK = 'campaign_spouse_work';

    public const STATE_CAMPAIGN_SPOUSE_HOUSEHOLD = 'campaign_spouse_household';

    public const STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS = 'campaign_spouse_non_working_assets';

    public const STATE_CAMPAIGN_TERMINAL = 'campaign_terminal';

    // Date of birth, captured inside the pensions section for the savetax
    // campaign (it's irrelevant to the income-tax advice the user came for, and
    // only matters once we reach pensions/retirement).
    public const STATE_CAMPAIGN_DOB = 'campaign_dob';

    // Per-section advice turns (turn_type 'advice'): auto-advancing read-only
    // messages where Fyn relays the relevant tax-engine recommendation for the
    // section just completed, before moving to the next section.
    public const STATE_CAMPAIGN_ADVICE_INCOME = 'campaign_advice_income';

    public const STATE_CAMPAIGN_ADVICE_SAVINGS = 'campaign_advice_savings';

    public const STATE_CAMPAIGN_ADVICE_INVESTMENTS = 'campaign_advice_investments';

    public const STATE_CAMPAIGN_ADVICE_PENSIONS = 'campaign_advice_pensions';

    public const STATE_CAMPAIGN_ADVICE_SPOUSE = 'campaign_advice_spouse';

    public const STATE_CAMPAIGN_SYNTHESIS = 'campaign_synthesis';

    // PensionCheck campaign entry states — turn types and prompt data added in
    // Task C3. The constants are defined here so the pensioncheck section arrays
    // can reference them now; inCodeStates() entries follow in C3.
    public const STATE_CAMPAIGN2_STATE_PENSION = 'campaign2_state_pension';

    public const STATE_CAMPAIGN2_RETIREMENT_GOALS = 'campaign2_retirement_goals';

    /**
     * Marker a prompt string can carry to render as multiple chat bubbles —
     * e.g. a "what we've heard" recap followed by the actual question. The
     * director (emitTurnForState) splits content prompts on this and emits an
     * onboarding_advance between the parts so the /m chat opens a fresh bubble.
     * Invisible control char so it never shows if a surface doesn't split.
     */
    public const BUBBLE_BREAK = "\x1E";

    /**
     * Per-campaign section walk orders — the single source of truth for each
     * campaign's question sequence. To reorder a journey, reorder its entry;
     * nothing else needs to change. Each section id maps to an entry state and
     * an optional whole-section skip predicate (see campaignSections()).
     *
     * savetax: section-led, income first (most relevant to the tax goal), DOB
     * deferred to the pensions section; whole sections skipped via funnel.
     *
     * pensioncheck: income first for context, then pensions + state-pension +
     * retirement-goals, spouse income for household, then expenditure for
     * retirement-gap modelling.
     */
    public const CAMPAIGN_SECTION_ORDERS = [
        'savetax' => ['income', 'savings', 'investments', 'pensions', 'spouse', 'expenditure'],
        'pensioncheck' => ['income', 'pensions', 'state_pension', 'retirement_goals', 'spouse', 'expenditure'],
    ];

    /**
     * Deprecated alias kept for backward compatibility — use sectionOrderFor().
     * Points to the savetax walk, which is unchanged.
     *
     * @deprecated use self::sectionOrderFor('savetax')
     */
    public const CAMPAIGN_SECTION_ORDER = self::CAMPAIGN_SECTION_ORDERS['savetax'];

    /**
     * Return the ordered section list for the given campaign selection.
     * Falls back to 'savetax' for any unrecognised selection.
     *
     * @return list<string>
     */
    public static function sectionOrderFor(string $selection): array
    {
        return self::CAMPAIGN_SECTION_ORDERS[$selection] ?? self::CAMPAIGN_SECTION_ORDERS['savetax'];
    }

    /**
     * Section id → entry state + optional whole-section skip predicate.
     * The resolver (nextCampaignSection) walks sectionOrderFor($selection) and
     * returns the entry state of the first non-skipped section.
     *
     * pensioncheck skips are data-presence guards added in Task C2; null
     * placeholders are in place until then.
     *
     * @return array<string, array{entry:string, skip:?callable}>
     */
    public static function campaignSections(string $selection = 'savetax'): array
    {
        $savetax = [
            'income' => ['entry' => self::STATE_BASE_EMPLOYMENT, 'skip' => null],
            'savings' => ['entry' => self::STATE_CAMPAIGN_ISA_HOLDINGS, 'skip' => [self::class, 'skipSectionIfNoCash']],
            'investments' => ['entry' => self::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS, 'skip' => [self::class, 'skipSectionIfNoInvestments']],
            'pensions' => ['entry' => self::STATE_CAMPAIGN_DOB, 'skip' => null],
            'spouse' => ['entry' => self::STATE_CAMPAIGN_SPOUSE_WORK, 'skip' => [self::class, 'skipIfNotMarried']],
            'expenditure' => ['entry' => self::STATE_BASE_EXPENDITURE, 'skip' => null],
        ];

        if ($selection !== 'pensioncheck') {
            return $savetax;
        }

        // pensioncheck section map (§5 of the pensioncheck spec).
        // state_pension / retirement_goals entry states land in Task C3.
        // Data-presence skips (skipSectionIfIncomeKnown etc.) land in Task C2.
        return [
            'income' => ['entry' => self::STATE_BASE_EMPLOYMENT,           'skip' => [self::class, 'skipSectionIfIncomeKnown']],
            'pensions' => ['entry' => self::STATE_CAMPAIGN_DOB,              'skip' => null],
            'state_pension' => ['entry' => self::STATE_CAMPAIGN2_STATE_PENSION,   'skip' => [self::class, 'skipSectionIfStatePensionKnown']],
            'retirement_goals' => ['entry' => self::STATE_CAMPAIGN2_RETIREMENT_GOALS, 'skip' => [self::class, 'skipSectionIfGoalsKnown']],
            'spouse' => ['entry' => self::STATE_CAMPAIGN_SPOUSE_WORK,      'skip' => [self::class, 'skipIfNotMarried']],
            'expenditure' => ['entry' => self::STATE_BASE_EXPENDITURE,          'skip' => [self::class, 'skipSectionIfExpenditureKnown']],
        ];
    }

    /**
     * Per-campaign verify sub-flow config: for each section, the /m screen to
     * navigate to for the "is this correct?" confirm (null = inline confirm, no
     * navigation — used for charitable giving) and the section's capture-entry
     * state to loop back to on "anything else to add?". The single source of truth
     * for the per-section verify behaviour; the three generic verify states read
     * the current section from users.onboarding_fyn_context['verify_section'].
     *
     * @return array<string, array{route:?string, entry:string}>
     */
    public static function campaignVerifyConfig(string $selection = 'savetax'): array
    {
        $savetax = [
            'income' => ['route' => '/income', 'entry' => self::STATE_BASE_EMPLOYMENT],
            'savings' => ['route' => '/savings', 'entry' => self::STATE_CAMPAIGN_ISA_HOLDINGS],
            'investments' => ['route' => '/investment', 'entry' => self::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS],
            'pensions' => ['route' => '/retirement', 'entry' => self::STATE_CAMPAIGN_DOB],
            'spouse' => ['route' => '/income', 'entry' => self::STATE_CAMPAIGN_SPOUSE_WORK],
            'expenditure' => ['route' => '/expenditure', 'entry' => self::STATE_BASE_EXPENDITURE],
        ];

        if ($selection !== 'pensioncheck') {
            return $savetax;
        }

        // pensioncheck verify routes: retirement module for pension-related
        // sections; income for spouse; expenditure for expenditure.
        return [
            'income' => ['route' => '/income', 'entry' => self::STATE_BASE_EMPLOYMENT],
            'pensions' => ['route' => '/retirement', 'entry' => self::STATE_CAMPAIGN_DOB],
            'state_pension' => ['route' => '/retirement', 'entry' => self::STATE_CAMPAIGN2_STATE_PENSION],
            'retirement_goals' => ['route' => '/retirement', 'entry' => self::STATE_CAMPAIGN2_RETIREMENT_GOALS],
            'spouse' => ['route' => '/income', 'entry' => self::STATE_CAMPAIGN_SPOUSE_WORK],
            'expenditure' => ['route' => '/expenditure', 'entry' => self::STATE_BASE_EXPENDITURE],
        ];
    }

    /**
     * Resolve the entry state of the next non-skipped campaign section after
     * the given section. Returns STATE_CAMPAIGN_SYNTHESIS when all sections are
     * exhausted, so the flow ends with a ranked consolidated plan before the
     * terminal navigation turn. This is what makes the sequence reorderable
     * from one array.
     *
     * The section order and section map are resolved from the user's
     * onboarding_fyn_selection so savetax and pensioncheck walk different paths.
     */
    public static function nextCampaignSection(string $afterSection, User $user): string
    {
        $selection = $user->onboarding_fyn_selection ?? 'savetax';
        $order = self::sectionOrderFor($selection);
        $sections = self::campaignSections($selection);
        $idx = array_search($afterSection, $order, true);
        if ($idx === false) {
            return self::STATE_CAMPAIGN_SYNTHESIS;
        }

        for ($i = $idx + 1; $i < count($order); $i++) {
            $section = $sections[$order[$i]] ?? null;
            if ($section === null) {
                continue;
            }
            $skip = $section['skip'];
            if (is_callable($skip) && $skip($user)) {
                continue;
            }

            // Honour per-state skip_if on the entry too (e.g. occupational
            // scheme skips when not employed), transitively.
            return self::applySkipRules($section['entry'], $user) ?? self::STATE_CAMPAIGN_SYNTHESIS;
        }

        return self::STATE_CAMPAIGN_SYNTHESIS;
    }

    /** Memoised effective transition table (corpus-merged or in-code fallback). */
    private static ?array $transitionTableCache = null;

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
                    // Labels and descriptions match the canonical life-stage
                    // names in resources/js/constants/lifeStageConfig.js and
                    // the /onboarding/welcome landing page so users see the
                    // same wording everywhere.
                    [
                        'id' => 'budgeting',
                        'label' => 'Starting Out',
                        'description' => 'Build smart money habits from day one.',
                    ],
                    [
                        'id' => 'goals',
                        'label' => 'Building Foundations',
                        'description' => 'Save for your first home and grow your career.',
                    ],
                    [
                        'id' => 'protection',
                        'label' => 'Protecting What Matters',
                        'description' => 'Secure your family and grow your wealth.',
                    ],
                    [
                        'id' => 'retirement',
                        'label' => 'Planning Your Future',
                        'description' => 'Maximise your wealth and prepare for retirement.',
                    ],
                    [
                        'id' => 'estate',
                        'label' => 'Enjoying Your Wealth',
                        'description' => 'Make your money last and leave a legacy.',
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
                    ['id' => 'estate', 'label' => 'Estate Planning'],
                    ['id' => 'goals', 'label' => 'Goals & Life Events'],
                    ['id' => 'budgeting', 'label' => 'Budgeting'],
                    ['id' => 'business', 'label' => 'Business'],
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
                'retry_text' => 'I need a first name, date of birth, and email address for your partner so I can create and link their account. Could you share those again?',
                'next' => self::STATE_BASE_DEPENDANTS,
                // Phase 10 — surface a raspberry-500 inline skip link alongside
                // the prompt. Frontend posts {action: 'skip'} to the action
                // endpoint; director's handleSkipAction advances to base_dependants.
                'skip_link' => [
                    'label' => 'Skip this for now',
                    'color' => 'raspberry',
                ],
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
                'next' => self::STATE_PROFILE_REVIEW_FAMILY,
            ],
            // Phase 10 — profile-review pause after family details. Frontend
            // shrinks the chat to w-[525px] and un-blurs the dashboard while
            // this state is active.
            self::STATE_PROFILE_REVIEW_FAMILY => [
                'turn_type' => 'bubbles',
                'prompt_text' => 'Does your family and personal information look right? Tap the bubble to confirm — or just tell me what needs changing.',
                'bubbles' => [
                    ['id' => 'looks_correct', 'label' => 'Looks correct'],
                ],
                'capture_field' => null,
                'layout' => 'standard',
                'next' => self::STATE_BASE_EMPLOYMENT,
            ],
            self::STATE_BASE_EMPLOYMENT => [
                'turn_type' => 'bubbles',
                'prompt_text' => "And what's your employment situation at the moment?",
                // Phase 10 — rename Employed → Full-time, drop Other (FR-M15).
                'bubbles' => [
                    ['id' => 'employed', 'label' => 'Full-time'],
                    ['id' => 'self_employed', 'label' => 'Self-employed'],
                    ['id' => 'part_time', 'label' => 'Part-time'],
                    ['id' => 'retired', 'label' => 'Retired'],
                    ['id' => 'unemployed', 'label' => 'Not working'],
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
                'retry_text' => 'I just need your gross annual income in GBP — could you share that?',
                'next' => self::STATE_BASE_EMPLOYMENT_MORE,
            ],
            // Phase 10 — multi-job loop. After the first job is captured,
            // ask if the user has another.  Yes loops back to base_employment;
            // No advances to expenditure.
            self::STATE_BASE_EMPLOYMENT_MORE => [
                'turn_type' => 'bubbles',
                'prompt_text' => 'Do you have any other roles or sources of earned income to add?',
                'bubbles' => [
                    ['id' => 'yes', 'label' => 'Yes, add another'],
                    ['id' => 'no', 'label' => "No, that's everything"],
                ],
                'capture_field' => null,
                'next' => self::class.'::nextFromEmploymentMore',
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
                // Path-aware: the savetax campaign runs expenditure near the end
                // of its reordered section flow, then heads to the holistic
                // terminal; the standard flow keeps the profile-review pause.
                'next' => fn (string $answer, User $user): string => $user->onboarding_fyn_path === 'campaign'
                    ? self::enterCampaignVerify($user, 'expenditure')
                    : self::STATE_PROFILE_REVIEW_EXPENDITURE,
                'skip_if' => [self::class, 'skipIfExpenditureSet'],
            ],
            // Phase 10 — profile-review pause after expenditure. Shows the
            // ProfileReviewPanel with expenditure alongside the earlier fields.
            //
            // Branches on users.onboarding_fyn_path:
            //   - 'campaign' → STATE_CAMPAIGN_INTRO (savetax consent gate)
            //   - 'journey' / 'focus' → STATE_ASSET_CAPTURE (existing behaviour)
            self::STATE_PROFILE_REVIEW_EXPENDITURE => [
                'turn_type' => 'bubbles',
                'prompt_text' => 'Your expenditure is noted. Confirm the full profile looks right — or tell me what to change.',
                'bubbles' => [
                    ['id' => 'looks_correct', 'label' => 'Looks correct'],
                ],
                'capture_field' => null,
                'layout' => 'standard',
                'next' => self::class.'::nextFromExpenditureReview',
            ],
            // ─── SaveTax campaign branch (sections 4-6) ─────────────────────
            // Consent gate before asset/liability capture begins. Explains the
            // topic shift and asks for explicit acknowledgement so the jump from
            // expenditure-review to "tell me about your workplace pension" no
            // longer feels jarring. Spouse phrasing is conditionally injected.
            //   - 'okay' → STATE_CAMPAIGN_OCCUPATIONAL_SCHEME (continue)
            //   - 'nope' → STATE_DONE (parks campaign, sets onboarding_completed)
            self::STATE_CAMPAIGN_INTRO => [
                'turn_type' => 'bubbles',
                'prompt_text' => self::class.'::buildCampaignIntroPrompt',
                'bubbles' => [
                    ['id' => 'okay', 'label' => 'Okay'],
                    ['id' => 'nope', 'label' => 'Nope'],
                ],
                'capture_field' => null,
                'next' => self::class.'::nextFromCampaignIntro',
            ],
            //
            // 9 states forming the post-expenditure tax-strategy capture flow.
            // Reachable only when onboarding_fyn_path === 'campaign'.
            // Each state uses grouped_extract or bubbles; tools whitelisted in
            // OnboardingChatDirector::captureToolSet().
            // Five LLM-delegated capture states — each lets Claude call multiple
            // existing create_* tools (create_savings_account, create_pension,
            // create_investment_account, capture_salary_sacrifice, etc.) to
            // populate the user's tax position from natural-language input.
            // ── Savings section (entry: ISA) ──────────────────────────────
            self::STATE_CAMPAIGN_ISA_HOLDINGS => [
                'turn_type' => 'delegated',
                'prompt_text' => "Let's look at your ISAs. **Do you have a Cash ISA or Stocks & Shares ISA? If so, what's the current balance and how much have you put in this tax year?**",
                'capture_field' => null,
                'next' => self::STATE_CAMPAIGN_BANK_ACCOUNTS,
                // Only ask about ISAs if the user ticked "ISA" on the funnel.
                'skip_if' => [self::class, 'skipIfNoIsa'],
            ],
            self::STATE_CAMPAIGN_BANK_ACCOUNTS => [
                'turn_type' => 'delegated',
                'prompt_text' => self::class.'::buildCampaignBankAccountsPrompt',
                'capture_field' => null,
                'next' => fn (string $answer, User $user): string => self::enterCampaignVerify($user, 'savings'),
                // Only ask about bank/savings if the user ticked bank or savings.
                'skip_if' => [self::class, 'skipIfNoBankOrSavings'],
            ],
            // ── Investments section ───────────────────────────────────────
            self::STATE_CAMPAIGN_INVESTMENT_ACCOUNTS => [
                'turn_type' => 'delegated',
                'prompt_text' => 'Any investment accounts — General Investment Accounts, share trading platforms? If so, current value, your purchase cost, and any annual dividend income.',
                'capture_field' => null,
                'next' => fn (string $answer, User $user): string => self::enterCampaignVerify($user, 'investments'),
            ],
            // ── Pensions section (entry: DOB — only now is it relevant) ────
            self::STATE_CAMPAIGN_DOB => [
                'turn_type' => 'grouped_extract',
                'prompt_text' => self::class.'::buildCampaignDobPrompt',
                'extraction_tool' => 'capture_personal_details',
                'retry_text' => 'Could you give me your date of birth — for example 12 January 1985 or 12/01/85?',
                // Pension questions only if the user ticked "pension"; otherwise
                // DOB is captured and we skip straight to the next section.
                'next' => self::class.'::nextFromCampaignDob',
                'skip_if' => [self::class, 'skipIfDobSet'],
            ],
            self::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME => [
                'turn_type' => 'delegated',
                'prompt_text' => "Tell me about your workplace pension. **What percentage of your salary do you contribute, does your employer match it, and is it via salary sacrifice?** If you don't have a workplace pension, just say so and we'll move on.",
                'capture_field' => null,
                'next' => self::STATE_CAMPAIGN_PENSION_CONTRIBS,
                'skip_if' => [self::class, 'skipIfNotEmployed'],
                // Linear scripted step that writes no entity record (workplace
                // pension details inform retirement advice, not a create_* row).
                // When the user both answers the scripted question and asks a
                // side-question ("3% and matched. What's salary sacrifice?"),
                // the delegated turn captures no tool, so the default A1 gate
                // would re-prompt and stall the walk. Opt in to advancing once
                // the answer is substantive — the side-question is answered in
                // the same turn and the script moves on.
                'advance_on_answered_question' => true,
            ],
            self::STATE_CAMPAIGN_PENSION_CONTRIBS => [
                'turn_type' => 'delegated',
                'prompt_text' => 'Beyond the workplace pension we covered, **do you make any personal pension or Self-Invested Personal Pension contributions? If so, how much per year (gross)?**',
                'capture_field' => null,
                // Carry-forward question removed (CSJ — Fyn does not need to ask
                // about prior-years' pension contributions): the pensions section
                // ends at personal contributions and goes straight to the verify
                // gate. The now-unreachable STATE_CAMPAIGN_PENSION_HISTORY state
                // was deleted.
                'next' => fn (string $answer, User $user): string => self::enterCampaignVerify($user, 'pensions'),
                'advance_on_answered_question' => true,
            ],
            self::STATE_CAMPAIGN_SPOUSE_WORK => [
                'turn_type' => 'bubbles',
                'prompt_text' => 'Does your spouse work?',
                'bubbles' => [
                    ['id' => 'yes', 'label' => 'Yes, they work'],
                    ['id' => 'no', 'label' => "No, they don't currently work"],
                ],
                'capture_field' => null,
                // Bubble click dispatches capture_spouse_work_status synchronously
                // before nextFromSpouseWork reads household_calculation_mode.
                // Without this the column stays NULL and routing falls to TERMINAL.
                'bubble_capture' => [
                    'tool' => 'capture_spouse_work_status',
                    'input_for_bubble' => [
                        'yes' => ['spouse_works' => true],
                        'no' => ['spouse_works' => false],
                    ],
                ],
                'next' => self::class.'::nextFromSpouseWork',
                'skip_if' => [self::class, 'skipSpouseWorkIfModeKnown'],
            ],
            // Two structured grouped_extract states — each uses ONE bespoke
            // composite tool that captures multiple fields in a single call,
            // mirroring the capture_personal_details / capture_dependants pattern.
            self::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD => [
                'turn_type' => 'grouped_extract',
                'prompt_text' => 'Great. **How much does your spouse earn annually, and do they have ISAs, investments, or pension contributions of their own?**',
                'capture_field' => null,
                'extraction_tool' => 'capture_spouse_household_data',
                'retry_text' => 'I need their annual income and whatever you know about their ISA / investment / pension balances. Could you share what you have?',
                'next' => fn (string $answer, User $user): string => self::enterCampaignVerify($user, 'spouse'),
                'skip_if' => [self::class, 'skipIfNotDualEarner'],
            ],
            self::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS => [
                'turn_type' => 'grouped_extract',
                'prompt_text' => "Got it — your spouse doesn't currently earn an income. That's actually useful for your tax strategy, because they have around £40,000 of unused tax allowances we can put to work. **Do they have any savings, ISAs, or investment accounts in their own name today, or is it all in yours?**",
                'capture_field' => null,
                'extraction_tool' => 'capture_spouse_non_working_assets',
                'retry_text' => 'Just give me rough numbers — savings balance, ISA balance, investment balance. If they have nothing in their own name, just say "nothing".',
                'next' => fn (string $answer, User $user): string => self::enterCampaignVerify($user, 'spouse'),
                'skip_if' => [self::class, 'skipIfNotSingleEarnerCouple'],
            ],
            // Terminal state for the campaign branch. turn_type=terminal mirrors
            // STATE_DONE; the OnboardingChatDirector reads `navigate_to` and emits
            // a `navigate` SSE event when this state is reached.
            self::STATE_CAMPAIGN_TERMINAL => [
                'turn_type' => 'terminal',
                'prompt_text' => "We've created your personal tax strategy, {first_name}.",
                'capture_field' => null,
                'navigate_to' => '/tax-strategy',
                'next' => self::STATE_DONE,
            ],
            // ── Per-section advice (auto-advancing) ───────────────────────
            // Each fires after its section's capture, relays the relevant
            // tax-engine recommendation, then continues to the next section.
            // Advice now fires AFTER the user has confirmed the section's screen
            // (verify_navigate "yes" → this advice → next section). Each relays
            // its tax-engine recommendation, then continues to the next section.
            self::STATE_CAMPAIGN_ADVICE_INCOME => [
                'turn_type' => 'advice',
                'advice_section' => 'income',
                'capture_field' => null,
                'next' => fn (string $answer, User $user): string => self::nextCampaignSection('income', $user),
            ],
            self::STATE_CAMPAIGN_ADVICE_SAVINGS => [
                'turn_type' => 'advice',
                'advice_section' => 'savings',
                'capture_field' => null,
                'next' => fn (string $answer, User $user): string => self::nextCampaignSection('savings', $user),
            ],
            self::STATE_CAMPAIGN_ADVICE_INVESTMENTS => [
                'turn_type' => 'advice',
                'advice_section' => 'investments',
                'capture_field' => null,
                'next' => fn (string $answer, User $user): string => self::nextCampaignSection('investments', $user),
            ],
            self::STATE_CAMPAIGN_ADVICE_PENSIONS => [
                'turn_type' => 'advice',
                'advice_section' => 'pensions',
                'capture_field' => null,
                'next' => fn (string $answer, User $user): string => self::nextCampaignSection('pensions', $user),
            ],
            self::STATE_CAMPAIGN_ADVICE_SPOUSE => [
                'turn_type' => 'advice',
                'advice_section' => 'spouse',
                'capture_field' => null,
                // Last section's advice → nextCampaignSection('spouse') returns
                // STATE_CAMPAIGN_TERMINAL once the sections are exhausted. This
                // MUST NOT point back at itself.
                'next' => fn (string $answer, User $user): string => self::nextCampaignSection('spouse', $user),
            ],
            self::STATE_CAMPAIGN_SYNTHESIS => [
                'turn_type' => 'advice',
                'advice_section' => 'synthesis',
                'capture_field' => null,
                // Plain constant next — NEVER a closure resolving to itself; advice turns
                // auto-advance and a self-edge recurses unbounded (the 2026-06-07
                // campaign_advice_spouse incident, PR #504).
                'next' => self::STATE_CAMPAIGN_TERMINAL,
            ],
            // ── SaveTax verify sub-flow (generic; section in context) ──────
            // Entered via enterCampaignVerify() which stamps verify_section.
            'campaign_verify_more' => [
                'turn_type' => 'bubbles',
                'prompt_text' => self::class.'::verifyPromptMore',
                'bubbles' => [
                    ['id' => 'yes', 'label' => 'Yes, add more'],
                    ['id' => 'no', 'label' => "No, that's everything"],
                ],
                'capture_field' => null,
                'next' => self::class.'::nextFromVerifyMore',
            ],
            // Announce-before-navigate: Fyn says it's taking the user to the
            // section's page and waits for an explicit "Okay" tap BEFORE the
            // navigation fires. Without this gate the navigate event fired in the
            // same turn as the message, so the chat minimised and the user never
            // saw/acknowledged the handoff — it "just transitioned". No navigate_to
            // here on purpose; the Okay tap advances to campaign_verify_navigate,
            // which owns the actual navigation.
            'campaign_verify_announce' => [
                'turn_type' => 'bubbles',
                'prompt_text' => self::class.'::verifyPromptAnnounce',
                'bubbles' => [
                    ['id' => 'okay', 'label' => 'Okay'],
                ],
                'capture_field' => null,
                'next' => 'campaign_verify_navigate',
            ],
            // Bubbles state that ALSO emits a navigation event when navigate_to
            // resolves to a route (director extension): the chat minimises + routes,
            // and the "is this correct?" bubbles wait for the user to reopen.
            // navigate_to is a code-only closure (null for charitable giving =
            // inline confirm, no navigation). Reached only AFTER the user taps
            // Okay on campaign_verify_announce.
            'campaign_verify_navigate' => [
                'turn_type' => 'bubbles',
                'prompt_text' => self::class.'::verifyPromptNavigate',
                'navigate_to' => fn (User $user): ?string => self::verifyNavigateRoute($user),
                'bubbles' => [
                    ['id' => 'yes', 'label' => "Yes, that's right"],
                    ['id' => 'no', 'label' => 'No, change something'],
                ],
                'capture_field' => null,
                'next' => self::class.'::nextFromVerifyNavigate',
            ],
            'campaign_verify_edit' => [
                'turn_type' => 'delegated',
                'prompt_text' => 'No problem — what needs changing?',
                'capture_field' => null,
                // After the edit is applied, re-show the screen + re-ask "correct?".
                'next' => 'campaign_verify_navigate',
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
                'prompt_text' => 'All set, {first_name}. Your {selection} module is ready to explore.',
                'capture_field' => null,
                'next' => null,
            ],
        ];
    }

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
            $procedure = $corpus->active('onboarding.workflow.fyn-onboarding', asOf: Carbon::now());
            if ($procedure === null) {
                return self::$transitionTableCache = $base;
            }

            $data = OnboardingWorkflowTable::fromProcedure($procedure);
            if ($data === null) {
                return self::$transitionTableCache = $base;
            }

            // State-id set + order MUST match the in-code table, else fall back.
            if (array_keys($data) !== array_keys($base)) {
                Log::notice('[OnboardingStateMachine] Corpus workflow ignored: state-id set mismatch with in-code table — falling back to in-code states.', [
                    'corpus_states' => count($data),
                    'in_code_states' => count($base),
                ]);

                return self::$transitionTableCache = $base;
            }

            return self::$transitionTableCache = self::mergeTable($base, $data);
        } catch (\Throwable $e) {
            report($e);

            return self::$transitionTableCache = $base;
        }
    }

    /**
     * Test/deploy hook — clear the memoised transition table so the next
     * states() / transitionTable() call re-reads the corpus. Used by the 4e
     * stamping tests (each drives its own temp corpus) and after a corpus
     * hot-reload. Idempotent and side-effect-free in production.
     */
    public static function flushTransitionTableCache(): void
    {
        self::$transitionTableCache = null;
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

        // No dependants — skip _detail and go straight to profile review.
        return self::STATE_PROFILE_REVIEW_FAMILY;
    }

    /**
     * Normalise a yes/no answer (bubble id or free text) to 'yes' or 'no'.
     * Bubble clicks send the literal bubble id ('yes' / 'no'); free text is
     * matched on a leading 'yes'. Anything else is treated as 'no' so the flow
     * advances rather than looping (the verify bubbles are explicit Yes/No).
     */
    private static function normaliseYesNo(string $answer): string
    {
        return str_starts_with(mb_strtolower(trim($answer)), 'yes') ? 'yes' : 'no';
    }

    /** Stamp the section into context and enter the verify sub-flow. */
    public static function enterCampaignVerify(User $user, string $section): string
    {
        $context = is_array($user->onboarding_fyn_context) ? $user->onboarding_fyn_context : [];
        $context['verify_section'] = $section;
        $user->onboarding_fyn_context = $context;
        $user->save();

        // Announce first: Fyn states it's taking the user to the section page and
        // waits for an "Okay" tap before navigating (campaign_verify_announce →
        // campaign_verify_navigate). The section's own capture "anything else?"
        // gate already covered "more"; we never ask it again.
        return 'campaign_verify_announce';
    }

    /**
     * The per-section advice state shown AFTER the user confirms the screen
     * (verify_navigate "yes"), or null for sections with no advice turn
     * (giving, expenditure). The advice used to precede the verify; it now
     * follows the confirmation.
     */
    private static function campaignSectionAdvice(string $section): ?string
    {
        return [
            'income' => self::STATE_CAMPAIGN_ADVICE_INCOME,
            'savings' => self::STATE_CAMPAIGN_ADVICE_SAVINGS,
            'investments' => self::STATE_CAMPAIGN_ADVICE_INVESTMENTS,
            'pensions' => self::STATE_CAMPAIGN_ADVICE_PENSIONS,
            'spouse' => self::STATE_CAMPAIGN_ADVICE_SPOUSE,
        ][$section] ?? null;
    }

    /** The section currently being verified (from context). */
    private static function verifySection(User $user): string
    {
        return (string) (($user->onboarding_fyn_context['verify_section'] ?? '') ?: '');
    }

    /** verify_more: "yes" loops back to the section's capture entry; "no" → navigate. */
    public static function nextFromVerifyMore(string $answer, User $user): string
    {
        if (self::normaliseYesNo($answer) === 'yes') {
            $section = self::verifySection($user);
            $selection = $user->onboarding_fyn_selection ?? 'savetax';

            return self::campaignVerifyConfig($selection)[$section]['entry'] ?? self::STATE_CAMPAIGN_SYNTHESIS;
        }

        return 'campaign_verify_navigate';
    }

    /** verify_navigate: "no" → edit; "yes" → section advice (then next section). */
    public static function nextFromVerifyNavigate(string $answer, User $user): string
    {
        if (self::normaliseYesNo($answer) === 'no') {
            return 'campaign_verify_edit';
        }

        // Confirmed — show this section's tax advice now (after the confirm),
        // then advance. Sections with no advice (expenditure) go straight to
        // the next section.
        $section = self::verifySection($user);

        return self::campaignSectionAdvice($section)
            ?? self::nextCampaignSection($section, $user->refresh());
    }

    /** Resolved navigate_to for the verify_navigate state (null = inline confirm). */
    public static function verifyNavigateRoute(User $user): ?string
    {
        $section = self::verifySection($user);
        $selection = $user->onboarding_fyn_selection ?? 'savetax';

        return self::campaignVerifyConfig($selection)[$section]['route'] ?? null;
    }

    /**
     * Prompt for verify_more, section-aware. Signature mirrors the other
     * callable prompt builders (buildPersonalPrompt): invoked by
     * resolvePromptText/invokeCallableString as ($answer, $user).
     */
    public static function verifyPromptMore(string $answer, User $user): string
    {
        $label = self::sectionLabel(self::verifySection($user), $user);

        return "Anything else to add to your {$label}?";
    }

    /**
     * Prompt for verify_announce, section-aware. Fyn states the upcoming
     * transition and waits for an Okay tap before the navigation fires.
     */
    public static function verifyPromptAnnounce(string $answer, User $user): string
    {
        if (self::verifyNavigateRoute($user) === null) {
            // No screen to navigate to — confirm inline, no announce needed.
            return "I've recorded that. **Does it look right?**";
        }

        $section = self::sectionLabel(self::verifySection($user), $user);

        return "I've saved your {$section}. Next I'll take you to your {$section} page so you can check everything's correct — **tap Okay when you're ready.**";
    }

    /** Prompt for verify_navigate, section-aware (navigation vs inline confirm). */
    public static function verifyPromptNavigate(string $answer, User $user): string
    {
        if (self::verifyNavigateRoute($user) === null) {
            // Inline confirm: no screen to navigate to.
            return "I've recorded that. **Does it look right?**";
        }

        $section = self::sectionLabel(self::verifySection($user), $user);

        return "Here's your {$section} page — take a look and check everything's correct, then tell me: **does it look right?**";
    }

    /**
     * Human label for a campaign section, for verify prompts. The savings
     * section spans bank accounts AND ISAs (a Cash ISA is cash held in an ISA
     * wrapper), so its label reflects what the user actually holds — it never
     * calls an ISA a "bank account" (CSJ).
     */
    private static function sectionLabel(string $section, ?User $user = null): string
    {
        if ($section === 'savings' && $user !== null) {
            $hasIsa = self::funnelHasAnyAsset($user, ['isa']);
            $hasBank = self::funnelHasAnyAsset($user, ['bank', 'savings']);

            return match (true) {
                $hasIsa && $hasBank => 'savings and ISA accounts',
                $hasIsa => 'ISA accounts',
                default => 'bank accounts',
            };
        }

        return [
            'income' => 'income', 'savings' => 'bank accounts', 'investments' => 'investments',
            'pensions' => 'pensions', 'spouse' => 'spouse details',
            'expenditure' => 'expenditure',
        ][$section] ?? 'details';
    }

    public static function nextFromEmploymentMore(string $answer, User $user): string
    {
        $normalised = mb_strtolower(trim($answer));
        if (str_starts_with($normalised, 'yes')) {
            return self::STATE_BASE_EMPLOYMENT;
        }

        // End of the income section. The savetax campaign enters the verify
        // flow (navigate to /income → confirm → income advice → next section);
        // the standard flow goes to expenditure.
        if ($user->onboarding_fyn_path === 'campaign') {
            return self::enterCampaignVerify($user, 'income');
        }

        return self::STATE_BASE_EXPENDITURE;
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

        // Funnel arrivals: greet + recap what they told us in the /savetax
        // funnel, acknowledge the profile we've pre-filled from it, then ask for
        // the first still-missing field (date of birth). Fires only on the first
        // base_personal turn (DOB unset); once DOB is captured the standard
        // branches below take over. This is what makes Fyn open with "here's what
        // you told us" instead of asking everything cold.
        $funnel = is_array($user->funnel_answers ?? null) ? $user->funnel_answers : [];
        if (($user->onboarding_fyn_path ?? '') === 'campaign' && $funnel !== [] && ! $hasDob) {
            $firstName = trim((string) ($user->first_name ?? '')) !== ''
                ? trim((string) $user->first_name)
                : 'there';

            return self::buildFunnelRecapPrompt($firstName, $funnel);
        }

        // Campaign welcome — fires only on the very first base_personal turn
        // for users who arrived via config('onboarding.campaign_map'). The
        // welcome is prepended to the existing grouped DOB+marital question
        // so it lands as a single bubble. Resume branches below take
        // precedence once DOB or marital is set.
        $isCampaign = ($user->onboarding_fyn_path ?? '') === 'campaign';
        if ($isCampaign && ! $hasDob && ! $hasMarital) {
            $welcome = self::campaignWelcomeFor((string) ($user->onboarding_fyn_selection ?? ''));
            $firstName = trim((string) ($user->first_name ?? '')) !== ''
                ? trim((string) $user->first_name)
                : 'there';

            return "Hi {$firstName}, {$welcome} Let's start with the basics: what's your date of birth, and are you single, married, in a civil partnership, divorced, or widowed?";
        }

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
     * First-turn greeting for /savetax funnel arrivals: recap the answers they
     * gave in the funnel, acknowledge that we've started their profile from
     * those answers, and ask for the first still-missing field (date of birth).
     * The exact income, expenditure and holdings are gathered by the base/
     * campaign states that follow. Plain text only (Rule #16).
     */
    private static function buildFunnelRecapPrompt(string $firstName, array $funnel): string
    {
        $points = [];

        $employmentLabel = [
            'full-time' => 'working full-time',
            'part-time' => 'working part-time',
            'self-employed' => 'self-employed',
            'retired' => 'retired',
            'not-employed' => 'not currently employed',
        ][$funnel['employment'] ?? ''] ?? null;
        if ($employmentLabel) {
            $points[] = ucfirst($employmentLabel);
        }

        $incomeLabel = [
            'upto_50270' => 'earning up to £50,270',
            '50271_100000' => 'earning £50,271 to £100,000',
            '100001_125140' => 'earning £100,001 to £125,140',
            'over_125140' => 'earning above £125,140',
        ][$funnel['income'] ?? ''] ?? null;
        if ($incomeLabel) {
            $points[] = ucfirst($incomeLabel);
        }

        if (($funnel['spouse'] ?? '') === 'yes') {
            // Spouse income is only collected on the funnel when the spouse has
            // income (the "No income"/zero option and the no-spouse path leave it
            // unset), so the band→phrase map omits zero — an unset/zero band falls
            // through to '' and we recap just the spouse, no income line.
            $spouseIncomeSuffix = [
                'upto_50270' => ' earning up to £50,270',
                '50271_100000' => ' earning £50,271 to £100,000',
                '100001_125140' => ' earning £100,001 to £125,140',
                'over_125140' => ' earning above £125,140',
            ][$funnel['spouseIncome'] ?? ''] ?? '';
            $points[] = 'You have a spouse'.$spouseIncomeSuffix;
        }

        $assetMap = [
            'bank' => 'bank accounts', 'savings' => 'savings', 'pension' => 'a pension',
            'property' => 'property', 'isa' => 'an ISA', 'investments' => 'investments',
        ];
        $assets = array_values(array_filter(array_map(
            fn ($a) => $assetMap[$a] ?? null,
            is_array($funnel['assets'] ?? null) ? $funnel['assets'] : []
        )));
        if ($assets !== []) {
            $points[] = 'You have '.self::joinWithAnd($assets);
        }

        // Time estimate: the detailed onboarding averages 3-5 minutes, plus a
        // minute for every asset the user picked beyond the first on the funnel's
        // final (multi-select) screen — each extra asset is one more section Fyn
        // walks through. The range grows with the selection (1 asset → 3-5,
        // 3 assets → 5-7) so the expectation Fyn sets matches the work ahead.
        $assetChoices = is_array($funnel['assets'] ?? null) ? $funnel['assets'] : [];
        $extraMinutes = max(0, count($assetChoices) - 1);
        $estimateLow = 3 + $extraMinutes;

        // First bubble: greet, list the funnel answers as bullet points (one per
        // line so they read clearly), then the profile/time line. Markdown "- "
        // bullets render as a list on both surfaces (web AiMessageContent +
        // /m renderFynText). BUBBLE_BREAK then splits the bold income question
        // off as its own bubble in the dock.
        $intro = "Hi {$firstName}, I'm Fyn — thanks for those answers.";
        if ($points !== []) {
            $bullets = implode("\n", array_map(static fn ($p) => "- {$p}", $points));
            $intro .= " Here's what you've told me:\n\n{$bullets}";
        }

        return $intro
            ."\n\nI've started your profile from what you told us, and to build your personalised tax plan I just need a few more details — this usually takes about {$estimateLow} minutes."
            .self::BUBBLE_BREAK
            ."**Let's start with your income.** Tell me your gross annual income (this includes bonuses and commissions).";
    }

    /** Join a list into "a, b and c". */
    private static function joinWithAnd(array $items): string
    {
        if (count($items) <= 1) {
            return (string) ($items[0] ?? '');
        }
        $last = array_pop($items);

        return implode(', ', $items).' and '.$last;
    }

    /**
     * Builds a personalised spouse prompt that uses "partner" for civil
     * partnership and "spouse" for married. Called at runtime from the
     * grouped_extract turn config.
     *
     * When `$conversation` carries a parked `spouse.first_name` (typically
     * extracted upstream by `OnboardingFactExtractor` from a phrase like
     * "married to Angela" at base_personal), the prompt acknowledges the
     * known name and only asks for the remaining fields — DOB and email.
     * Without this, the hardcoded fallback re-asks for the first name even
     * though the user has already volunteered it, breaking the canonical
     * "no repeat-asks" contract (INV-2.2.3 + INV-2.11.1).
     */
    public static function buildSpousePrompt(string $answer, User $user, ?AiConversation $conversation = null): string
    {
        $isCivilPartnership = ($user->marital_status ?? '') === 'civil_partnership';
        $word = $isCivilPartnership ? 'partner' : 'spouse';

        $parkedFirstName = null;
        if ($conversation !== null) {
            $parked = (array) ($conversation->onboarding_parked_facts ?? []);
            $parkedSpouse = is_array($parked['spouse'] ?? null) ? $parked['spouse'] : [];
            $candidate = trim((string) ($parkedSpouse['first_name'] ?? ''));
            if ($candidate !== '') {
                $parkedFirstName = $candidate;
            }
        }

        if ($parkedFirstName !== null) {
            return "Great — got {$parkedFirstName} noted. Could you share their date of birth and email address? I'll create an account and link the two of you so you can plan together.";
        }

        return "Great — let's add your {$word}'s details. Can you share their first name, date of birth, and email address? I'll create an account and link the two of you so you can plan together.";
    }

    /**
     * Builds a personalised work prompt that matches the user's chosen
     * employment_status (self-employed users get "trade name" wording).
     */
    public static function buildWorkPrompt(string $answer, User $user, ?AiConversation $conversation = null): string
    {
        $status = $user->employment_status ?? 'employed';

        // SaveTax campaign funnel arrivals open here (income-first). On the first
        // work turn — before any income is captured — greet with the funnel recap
        // (which leads straight into the income question). Employment is already
        // known from the funnel, so we never re-ask it. Delivered ONCE per
        // conversation: the welcome-back resume ('continue') re-emits this
        // state's turn, and without the delivered-check the full recap +
        // question would repeat as duplicate transcript rows.
        $funnel = is_array($user->funnel_answers ?? null) ? $user->funnel_answers : [];
        $noIncomeYet = empty($user->annual_employment_income) && empty($user->annual_self_employment_income);
        if (($user->onboarding_fyn_path ?? '') === 'campaign' && $funnel !== [] && $noIncomeYet
            && ! self::stateTurnAlreadyDelivered($conversation, self::STATE_BASE_WORK)) {
            $firstName = trim((string) ($user->first_name ?? '')) !== ''
                ? trim((string) $user->first_name)
                : 'there';

            return self::buildFunnelRecapPrompt($firstName, $funnel);
        }

        if ($status === 'self_employed') {
            return "Brilliant. What's your gross annual self-employment income? This includes bonuses and commissions.";
        }

        if ($status === 'part_time') {
            return "Lovely. What's your gross annual income from that role? This includes bonuses and commissions.";
        }

        return "Brilliant. What's your gross annual income? This includes bonuses and commissions.";
    }

    /**
     * Has a turn for the given state already been persisted in this
     * conversation? Every emitTurnForState save stamps metadata.onboarding_step,
     * so this survives across surfaces (desktop start → /m dock resume). Used
     * to deliver one-off content (the funnel recap) exactly once.
     */
    private static function stateTurnAlreadyDelivered(?AiConversation $conversation, string $stateId): bool
    {
        if ($conversation === null) {
            return false;
        }

        try {
            return $conversation->messages()
                ->where('role', 'assistant')
                ->where('metadata->onboarding_step', $stateId)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
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
        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $nameParts = explode(' ', (string) $user->name);
            $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';
        }

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

    /** Skip the campaign DOB capture when we already have a date of birth. */
    public static function skipIfDobSet(User $user): bool
    {
        return ! empty($user->date_of_birth);
    }

    /**
     * Whole-section skip helpers for the savetax campaign, keyed off the funnel
     * answers (assets the user told us they hold). If they said they have no
     * cash/savings/ISA, skip the savings section entirely, etc.
     */
    public static function skipSectionIfNoCash(User $user): bool
    {
        return ! self::funnelHasAnyAsset($user, ['savings', 'bank', 'isa']);
    }

    // ─── Pensioncheck data-presence skip predicates (Task C2) ────────────────
    //
    // These are section-level skips for the pensioncheck campaign.  A returning
    // user who already completed the relevant section of the SaveTax onboarding
    // should not be asked the same questions again.
    //
    // Note: income is stored directly on the users table — there is no separate
    // job_employment relation in this codebase.  employment_status being non-null
    // is the reliable signal that the income section has been completed.

    /**
     * Skip the income section when actual captured income is already recorded.
     *
     * Income data lives on the users table (annual_employment_income /
     * annual_self_employment_income).  employment_status MUST NOT be used here:
     * FunnelAnswersMapper sets it at registration for every funnel user, so
     * keying on it would skip the income section for every new pensioncheck
     * registrant before their income is ever captured.
     */
    public static function skipSectionIfIncomeKnown(User $user): bool
    {
        return (float) ($user->annual_employment_income ?? 0) > 0
            || (float) ($user->annual_self_employment_income ?? 0) > 0;
    }

    /**
     * Skip the state_pension section when a state_pensions row already exists.
     */
    public static function skipSectionIfStatePensionKnown(User $user): bool
    {
        return $user->statePension()->exists();
    }

    /**
     * Skip the retirement_goals section when the retirement_profiles row exists
     * AND both target_retirement_age and target_retirement_income are populated.
     *
     * target_retirement_age is NOT NULL in the schema, so the meaningful guard is
     * target_retirement_income (which is nullable) — both are checked explicitly
     * to future-proof against schema changes.
     */
    public static function skipSectionIfGoalsKnown(User $user): bool
    {
        $profile = $user->retirementProfile;

        return $profile !== null
            && $profile->target_retirement_age !== null
            && $profile->target_retirement_income !== null;
    }

    /**
     * Skip the expenditure section when monthly_expenditure is already recorded.
     *
     * Mirrors skipIfExpenditureSet: treats 0 as not-known (a stored zero is
     * indistinguishable from an uninitialised default in the legacy flow).
     */
    public static function skipSectionIfExpenditureKnown(User $user): bool
    {
        return (float) ($user->monthly_expenditure ?? 0) > 0
            || (float) ($user->annual_expenditure ?? 0) > 0;
    }

    public static function skipSectionIfNoInvestments(User $user): bool
    {
        if (self::funnelHasAnyAsset($user, ['investments'])) {
            return false;
        }

        // A Stocks & Shares ISA captured during the ISA-holdings step is an
        // investment — run the investments section so it surfaces on /investment
        // even when the user ticked only "ISA" (not "investments") on the funnel
        // (CSJ): cash ISA shows in the cash section, S&S ISA on the next screen.
        return ! InvestmentAccount::query()
            ->where('user_id', $user->id)
            ->where('account_type', 'isa')
            ->exists();
    }

    /**
     * Per-question gates inside the savings section. Fyn must only ask about the
     * specific products the user ticked on the funnel — the section-level
     * skipSectionIfNoCash runs the section if ANY cash-like asset is held, so
     * these stop the ISA question firing for a savings-only user (and vice versa).
     */
    public static function skipIfNoIsa(User $user): bool
    {
        return ! self::funnelHasAnyAsset($user, ['isa']);
    }

    public static function skipIfNoBankOrSavings(User $user): bool
    {
        return ! self::funnelHasAnyAsset($user, ['bank', 'savings']);
    }

    /**
     * Savings-section capture prompt, scoped to what the user ticked. Reached
     * only when bank or savings was selected (skipIfNoBankOrSavings), so it
     * names just the held cash type(s) — a user who ticked only "savings" is
     * never asked about bank accounts, and vice versa.
     */
    public static function buildCampaignBankAccountsPrompt(string $answer, User $user): string
    {
        $hasBank = self::funnelHasAnyAsset($user, ['bank']);
        $hasSavings = self::funnelHasAnyAsset($user, ['savings']);

        if ($hasBank && $hasSavings) {
            return "Now your savings — bank accounts and savings accounts. **For each, what's the balance and the interest rate?**";
        }

        if ($hasBank) {
            return "Now your bank accounts. **For each, what's the balance and the interest rate?**";
        }

        return "Now your savings accounts. **For each, what's the balance and the interest rate?**";
    }

    /**
     * DOB-capture prompt for the pensions section. DOB is always captured (it
     * feeds tax/retirement maths regardless), but only a user who ticked
     * "pension" on the funnel is told Fyn is now looking at pensions —
     * otherwise the question is framed neutrally so a user who did not pick a
     * pension is never asked about one they don't have. Pairs with
     * nextFromCampaignDob, which gates the pension questions that follow.
     */
    public static function buildCampaignDobPrompt(string $answer, User $user): string
    {
        if (self::funnelHasAnyAsset($user, ['pension'])) {
            return "Now let's look at pensions and retirement — for that **I need your date of birth.** Something like 12 January 1985 or 12/01/85.";
        }

        return "Next, **what's your date of birth?** Something like 12 January 1985 or 12/01/85.";
    }

    /**
     * Pensions-section entry routing after DOB. DOB is always captured (it's
     * needed for tax/retirement), but the pension questions + the pension verify
     * only fire when the user ticked "pension" on the funnel — otherwise we skip
     * straight to the next section, so Fyn never asks about a pension the user
     * doesn't have.
     */
    public static function nextFromCampaignDob(string $answer, User $user): string
    {
        return self::funnelHasAnyAsset($user, ['pension'])
            ? self::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME
            : self::nextCampaignSection('pensions', $user);
    }

    /** True if the user's funnel answers list at least one of the given assets. */
    private static function funnelHasAnyAsset(User $user, array $assets): bool
    {
        $held = (array) (($user->funnel_answers['assets'] ?? []));

        return (bool) array_intersect($assets, $held);
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

    // ─── SaveTax campaign branch helpers (sections 4-6) ─────────────────

    /**
     * Branch from STATE_PROFILE_REVIEW_EXPENDITURE based on onboarding_fyn_path.
     * path=campaign users hit the consent gate (STATE_CAMPAIGN_INTRO) before
     * the asset/liability capture; journey/focus users continue to the existing
     * asset_capture flow.
     */
    public static function nextFromExpenditureReview(string $answer, User $user): string
    {
        return $user->onboarding_fyn_path === 'campaign'
            ? self::STATE_CAMPAIGN_INTRO
            : self::STATE_ASSET_CAPTURE;
    }

    /**
     * Builds the campaign intro prompt that explains why the conversation is
     * shifting from personal/employment data to assets and liabilities. Spouse
     * phrasing is included when the user is married or in a civil partnership;
     * the spouse first name is sourced from the linked spouse User if present,
     * else from `conversation.onboarding_parked_facts.spouse.first_name`, else
     * falls back to "your spouse".
     */
    public static function buildCampaignIntroPrompt(string $answer, User $user, ?AiConversation $conversation = null): string
    {
        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $firstName = 'there';
        }

        $isMarried = in_array((string) $user->marital_status, ['married', 'civil_partnership'], true);
        $spousePhrase = '';

        if ($isMarried) {
            $spouseFirstName = null;

            if ($user->spouse_id !== null && $user->spouse !== null) {
                $candidate = trim((string) $user->spouse->first_name);
                if ($candidate !== '') {
                    $spouseFirstName = $candidate;
                }
            }

            if ($spouseFirstName === null && $conversation !== null) {
                $parked = (array) ($conversation->onboarding_parked_facts ?? []);
                $parkedSpouse = is_array($parked['spouse'] ?? null) ? $parked['spouse'] : [];
                $candidate = trim((string) ($parkedSpouse['first_name'] ?? ''));
                if ($candidate !== '') {
                    $spouseFirstName = $candidate;
                }
            }

            $spousePhrase = $spouseFirstName !== null
                ? ", including {$spouseFirstName}'s where it makes sense,"
                : ", including your spouse's where it makes sense,";
        }

        return "Thanks {$firstName} for that information. Now, in order to personalise your tax strategy and make sure I give you the right detail, I'd like to ask about your pensions, accounts and investments{$spousePhrase} is that okay?";
    }

    /**
     * Branch from STATE_CAMPAIGN_INTRO based on the user's bubble choice.
     * "okay" continues into the asset/liability capture flow; anything else
     * (including "nope") routes to STATE_DONE so onboarding_completed is set
     * and the user lands on /dashboard. They can revisit the campaign via the
     * Tax Strategy tile on /actions whenever they're ready.
     */
    public static function nextFromCampaignIntro(string $answer, User $user): string
    {
        $matched = self::matchBubble(self::STATE_CAMPAIGN_INTRO, $answer);

        return $matched === 'okay'
            ? self::STATE_CAMPAIGN_OCCUPATIONAL_SCHEME
            : self::STATE_DONE;
    }

    /**
     * Branch from STATE_CAMPAIGN_SPOUSE_WORK based on the household_calculation_mode
     * set by capture_spouse_work_status. Falls back to TERMINAL if mode is unset
     * (defensive — should never happen if the tool ran).
     */
    public static function nextFromSpouseWork(string $answer, User $user): string
    {
        return match ($user->household_calculation_mode) {
            'dual_earner' => self::STATE_CAMPAIGN_SPOUSE_HOUSEHOLD,
            'single_earner_couple' => self::STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS,
            default => self::STATE_CAMPAIGN_ADVICE_SPOUSE,
        };
    }

    /**
     * Skip the occupational-scheme state for users who aren't in employment.
     * Self-employed, retired, and unemployed users have no workplace pension
     * to capture.
     */
    public static function skipIfNotEmployed(User $user): bool
    {
        return ! in_array((string) $user->employment_status, ['full_time', 'part_time'], true);
    }

    /**
     * Skip all 3 spouse states for users who aren't married or in a civil
     * partnership.
     */
    public static function skipIfNotMarried(User $user): bool
    {
        return ! in_array((string) $user->marital_status, ['married', 'civil_partnership'], true);
    }

    /**
     * Skip the spouse-work question when the answer is already known —
     * either the user isn't married (whole section skips) or
     * household_calculation_mode was pre-set (mapped from the funnel's
     * spouse-income answer at registration, or captured on an earlier run).
     * applySkipRules then follows nextFromSpouseWork straight to the right
     * follow-up state, so Fyn never re-asks what the funnel already told us.
     */
    public static function skipSpouseWorkIfModeKnown(User $user): bool
    {
        return self::skipIfNotMarried($user) || $user->household_calculation_mode !== null;
    }

    /**
     * Defensive guard on STATE_CAMPAIGN_SPOUSE_HOUSEHOLD — only enter if
     * household_calculation_mode is 'dual_earner'. Keeps the state strictly
     * isolated to its path even if the routing callable misbehaves.
     */
    public static function skipIfNotDualEarner(User $user): bool
    {
        return (string) $user->household_calculation_mode !== 'dual_earner';
    }

    /**
     * Defensive guard on STATE_CAMPAIGN_SPOUSE_NON_WORKING_ASSETS — only
     * enter if household_calculation_mode is 'single_earner_couple'.
     */
    public static function skipIfNotSingleEarnerCouple(User $user): bool
    {
        return (string) $user->household_calculation_mode !== 'single_earner_couple';
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
        $firstName = trim((string) ($user->first_name ?? ''));
        if ($firstName === '') {
            $nameParts = explode(' ', (string) $user->name);
            $firstName = $nameParts[0] !== '' ? $nameParts[0] : 'there';
        }

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
    public static function resolvePromptText(array $state, User $user, string $answer = '', ?AiConversation $conversation = null): string
    {
        $promptText = $state['prompt_text'] ?? '';

        if (is_string($promptText) && self::isCallableReference($promptText)) {
            $result = self::invokeCallableString($promptText, $answer, $user, $conversation);

            return is_string($result) ? self::interpolate($result, $user) : '';
        }

        if (is_callable($promptText)) {
            $result = $promptText($answer, $user, $conversation);

            return is_string($result) ? self::interpolate($result, $user) : '';
        }

        return self::interpolate((string) $promptText, $user);
    }

    // ─── Internal helpers ────────────────────────────────────────────────

    private static function isCallableReference(string $value): bool
    {
        return str_contains($value, '::');
    }

    private static function invokeCallableString(string $reference, string $answer, User $user, ?AiConversation $conversation = null): mixed
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

        if ($paramCount === 2) {
            return $class::$method($answer, $user);
        }

        return $class::$method($answer, $user, $conversation);
    }

    /**
     * One-sentence campaign-specific opening prepended to the grouped
     * base_personal question. Keyed on users.onboarding_fyn_selection.
     * Falls back to a generic Fynla welcome for unknown campaign ids
     * — better than crashing or silently dropping the welcome.
     */
    private static function campaignWelcomeFor(string $campaignId): string
    {
        return match ($campaignId) {
            'savetax' => "welcome to Fynla — I'll help you build your tax-saving strategy.",
            default => "welcome to Fynla — let's get started.",
        };
    }
}
