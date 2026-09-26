<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Constants\GateRoutes;
use App\Models\FamilyMember;
use App\Models\SpousePermission;
use App\Models\User;
use App\Services\AI\ContextualConversation\ContextualResourceResolver;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Coordination\HouseholdFinancialContext;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\PrerequisiteGateService;

/**
 * Builds the single ranked next-actions list (max 4) shown on the /m dashboard
 * wheel box AND the recommendations list below — they are the same list. Items
 * are either real recommendations (from the KYC-gated aggregator) or KYC
 * "unlock" prompts for high-value gated modules.
 */
class NextActionsService
{
    private const MAX_ITEMS = 4;

    /** Modules that can produce an unlock prompt, in surfacing priority order. */
    private const UNLOCK_MODULES = ['retirement', 'protection', 'savings', 'investment', 'estate', 'goals'];

    /** Max strategy-level unlock cards to surface — keeps the 4-slot list from being crowded. */
    private const MAX_STRATEGY_UNLOCKS = 2;

    /**
     * WP-6 — campaign-to-module affinity map. Campaign arrivals see their
     * campaign's primary module surfaced ahead of generic cross-module items.
     * Unknown or absent campaign tokens receive no affinity boost.
     */
    private const CAMPAIGN_AFFINITY = [
        'savetax' => 'tax',
        'pensioncheck' => 'retirement',
    ];

    public function __construct(
        private readonly RecommendationsAggregatorService $recommendations,
        private readonly PrerequisiteGateService $gate,
        private readonly ComposedTaxPlanService $taxPlan,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function build(int $userId): array
    {
        $user = User::findOrFail($userId);

        return array_slice($this->applyCampaignAffinity($user, $this->rankAll($user, $userId)), 0, self::MAX_ITEMS);
    }

    /**
     * WP-2 (one actions model) — the FULL ranked open-actions list, uncapped.
     * Same items, same ranking, same stable ids as build(); the 4-slot cap is
     * a dashboard presentation concern, not a property of the list. Feeds the
     * "all my actions" surfaces (desktop /actions, /m Done/all views).
     *
     * @return array<int,array<string,mixed>>
     */
    public function buildAll(int $userId): array
    {
        $user = User::findOrFail($userId);

        return $this->applyCampaignAffinity($user, $this->rankAll($user, $userId));
    }

    /**
     * The merged, value-ranked open list shared by build()/buildAll().
     *
     * @return array<int,array<string,mixed>>
     */
    /**
     * Mid-walk (CSJ 2026-07-23): while the director owns the next turn,
     * unlock prompts in the UNIFIED action lists are noise competing with
     * onboarding — Fyn is about to ask for that data in the walk, and the
     * dashboard's finish-your-plan nudge is the one call to action. The
     * per-module tab cards keep their true gate state (they are the level
     * map, not a competing call to action).
     */
    private function midWalk(User $user): bool
    {
        if ($user->onboarding_fyn_step !== null) {
            return true;
        }

        // A fresh campaign registrant's first dashboard fetch races the chat
        // turn that stamps the step (live 2026-07-23, user 292): a funnel
        // registrant who has not begun the walk (onboarding_started_at null)
        // is walking by construction. A paused walker (started_at set, step
        // nulled) keeps the unlock prompts — they are the re-engagement hook.
        return ! $user->onboarding_completed
            && $user->onboarding_started_at === null
            && ($user->funnel_answers['campaign'] ?? null) !== null;
    }

    /** @return array<int,array<string,mixed>> */
    private function unlockFamilyItems(User $user): array
    {
        return array_merge($this->unlockItems($user), $this->strategyUnlockItems($user));
    }

    /**
     * Every open action for the user, unranked — the ONE merge both the
     * unified list (build/buildAll) and the `/m` focus carousel read. They
     * each merged their own set before, so an item added to one was missing
     * from the other (the spouse-link action, live on csjones 2026-09-16).
     *
     * @return array<int,array<string,mixed>>
     */
    private function openItems(User $user, int $userId): array
    {
        $midWalk = $this->midWalk($user);

        $items = array_merge(
            $this->recommendationItems($userId),
            $midWalk ? [] : $this->unlockFamilyItems($user),
            $midWalk ? [] : $this->spouseLinkItems($user),
        );

        // One module vocabulary, stamped once, for every client.
        return array_map(function (array $item): array {
            $item['module_label'] = self::moduleDisplayLabel((string) ($item['module'] ?? ''));

            return $item;
        }, $items);
    }

    private function rankAll(User $user, int $userId): array
    {
        $items = $this->openItems($user, $userId);

        usort($items, static function (array $a, array $b): int {
            return [$b['value'], $a['module']] <=> [$a['value'], $b['module']];
        });

        return $items;
    }

    /**
     * Per-area focus cards for the /m carousel: a "Top actions" card (the unified
     * <=4 across all areas) followed by one card per module — real recommendations
     * when the module's KYC gate is open, or a locked "unlock" card when it is
     * gated. Selecting a card drives the actions list shown below it. Computed
     * from a single recommendation aggregation.
     *
     * @return array<int,array<string,mixed>>
     */
    public function focusAreas(int $userId): array
    {
        $user = User::findOrFail($userId);

        $recItems = $this->recommendationItems($userId);
        $unlocks = $this->unlockFamilyItems($user);

        // Top card = the unified <=4 (recs + unlocks), same ranking as build()
        // including the WP-6 campaign affinity (tax first for SaveTax users).
        // Affinity runs BEFORE the 4-slot cut so a lower-value tax item can
        // still be lifted into the card.
        $merged = $this->openItems($user, $userId);
        usort($merged, static function (array $a, array $b): int {
            return [$b['value'], $a['module']] <=> [$a['value'], $b['module']];
        });
        $top = array_slice($this->applyCampaignAffinity($user, $merged), 0, self::MAX_ITEMS);

        return array_merge([[
            'key' => 'top',
            'label' => 'Top actions',
            'locked' => false,
            'stat' => count($top).' action'.(count($top) === 1 ? '' : 's'),
            'actions' => $top,
        ]], $this->moduleCards($recItems, $unlocks));
    }

    /**
     * One card per module, in UNLOCK_MODULES order: the module's own
     * recommendations when its KYC gate is open, the unlock prompt when it is
     * closed, and the data-needed prompt when the gate is open but there is
     * not yet enough to advise on. NEVER an "On track"/empty placeholder.
     *
     * @param  array<int,array<string,mixed>>  $recItems
     * @param  array<int,array<string,mixed>>  $unlocks
     * @return array<int,array<string,mixed>>
     */
    private function moduleCards(array $recItems, array $unlocks): array
    {
        $byModule = [];
        foreach ($recItems as $item) {
            $byModule[$item['module']][] = $item;
        }
        foreach ($byModule as &$list) {
            usort($list, static fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        }
        unset($list);

        $unlockByModule = [];
        foreach ($unlocks as $unlock) {
            $unlockByModule[$unlock['module']] = $unlock;
        }

        $cards = [];

        foreach (self::UNLOCK_MODULES as $module) {
            $label = self::moduleDisplayLabel($module);

            if (isset($unlockByModule[$module])) {
                $unlock = $unlockByModule[$module];
                $cards[] = [
                    'key' => $module,
                    'label' => $label,
                    'locked' => true,
                    'stat' => (string) $unlock['meta'],
                    'actions' => [$unlock],
                ];

                continue;
            }

            // KYC gate open → this module's real recommendations.
            $items = array_slice($byModule[$module] ?? [], 0, self::MAX_ITEMS);

            if ($items === []) {
                // Gate open, nothing to recommend: not locked (Laura,
                // 2026-09-18, read "Locked" on a module she had completed).
                $needed = $this->dataNeededItem($module);
                $cards[] = [
                    'key' => $module,
                    'label' => $label,
                    'locked' => false,
                    'stat' => 'Nothing to action right now',
                    'actions' => [$needed],
                ];

                continue;
            }

            $cards[] = [
                'key' => $module,
                'label' => $label,
                'locked' => false,
                'stat' => (string) ($items[0]['meta'] ?? (count($items).' actions')),
                'actions' => $items,
            ];
        }

        return $cards;
    }

    /**
     * KYC prompt for a module whose gate is open but which can't yet produce
     * recommendations (not enough data). Same shape as a gate-closed unlock.
     *
     * @return array<string,mixed>
     */
    private function dataNeededItem(string $module): array
    {
        $label = $this->moduleLabel($module);

        return [
            'id' => 'unlock:'.$module,
            'type' => 'unlock',
            'module' => $module,
            'title' => 'Complete your '.$label.' details',
            'meta' => 'A few more details so we can give you '.$label.' recommendations',
            'value' => 0.0,
            'done' => false,
            'action' => ['kind' => 'fyn_capture', 'payload' => $module, 'prompt' => RecommendationRouting::unlockPrompt($module)],
        ];
    }

    /**
     * Rank by value descending (module name tie-break) and cap at MAX_ITEMS.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    private function rank(array $items): array
    {
        usort($items, static function (array $a, array $b): int {
            return [$b['value'], $a['module']] <=> [$a['value'], $b['module']];
        });

        return array_slice($items, 0, self::MAX_ITEMS);
    }

    /**
     * WP-6 — campaign affinity. Campaign arrivals see their campaign's primary
     * module sorted ahead of generic cross-module items; within each tier the
     * normal value ranking holds. The module is keyed off CAMPAIGN_AFFINITY via
     * the user's onboarding_fyn_selection (set at campaign start) with a
     * fallback to funnel_answers['campaign'] (raw funnel data). Unknown or
     * absent tokens receive no affinity boost.
     *
     * @param  array<int,array<string,mixed>>  $items
     * @return array<int,array<string,mixed>>
     */
    private function applyCampaignAffinity(User $user, array $items): array
    {
        // Resolution order mirrors A6's controller fallback:
        // 1. onboarding_fyn_selection (set at campaign start — most reliable)
        // 2. funnel_answers['campaign'] (stamped by A6 for new arrivals)
        // 3. any non-empty funnel_answers without a campaign key → legacy pre-A6
        //    rows that arrived via the savetax funnel before the stamp migration
        $raw = $user->onboarding_fyn_selection
            ?? ($user->funnel_answers['campaign']
                ?? (! empty($user->funnel_answers) ? 'savetax' : null));

        $campaign = is_string($raw) ? $raw : null;
        $affinityModule = self::CAMPAIGN_AFFINITY[$campaign] ?? null;

        if ($affinityModule === null) {
            return $items;
        }

        usort($items, static function (array $a, array $b) use ($affinityModule): int {
            $aMatch = ($a['module'] ?? '') === $affinityModule ? 1 : 0;
            $bMatch = ($b['module'] ?? '') === $affinityModule ? 1 : 0;

            return [$bMatch, $b['value'], $a['module']] <=> [$aMatch, $a['value'], $b['module']];
        });

        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function recommendationItems(int $userId): array
    {
        $all = $this->recommendations->aggregateRecommendations($userId);

        // Drop blank recs (a blank renders as an empty row / "How do I ''?")
        // AND completed recs: a completed action is banked toward the wheel
        // count and replaced by the next-best, so it leaves the actionable
        // list rather than sitting there ticked (CSJ 4.4 — replace done with a
        // new one; the running tally is counted in MobileLevelService). The
        // aggregator merges recommendation_tracking status onto each rec (F18).
        $all = array_filter($all, static function (array $rec): bool {
            if (trim((string) ($rec['recommendation_text'] ?? '')) === '') {
                return false;
            }

            return ($rec['status'] ?? 'pending') !== 'completed';
        });

        return array_map(function (array $rec): array {
            $benefit = is_numeric($rec['potential_benefit'] ?? null) ? (float) $rec['potential_benefit'] : null;
            $id = (string) ($rec['recommendation_id'] ?? uniqid('rec_'));
            // Routing keys on the rule; the id also carries the record scope.
            $ruleKey = (string) ($rec['rule_key'] ?? $id);
            $module = (string) ($rec['module'] ?? 'general');
            [$title, $detail] = self::splitHeadline((string) ($rec['recommendation_text'] ?? ''));

            return [
                'id' => $id,
                'type' => 'recommendation',
                'module' => (string) ($rec['module'] ?? 'general'),
                'title' => $title,
                // Everything after the headline's dash — the explanation Fyn
                // carries when the row opens a capture; the row itself never
                // shows it (CSJ 2026-09-09: the full sentence cluttered the screen).
                'detail' => $detail,
                'meta' => $benefit !== null
                    ? 'You could save £'.number_format($benefit)
                    : $this->categoryLabel((string) ($rec['category'] ?? 'Recommended')),
                // The one ranking (PriorityRanker via the aggregator, Batch B): a pound
                // benefit is copy for the meta line, never the sort key.
                'value' => (float) ($rec['priority_score'] ?? 50),
                // Open only — completed recs are excluded above and replaced by
                // the next-best, so every shown recommendation is actionable.
                'done' => false,
                // Tapping a recommendation: one that asks the user to record or
                // update information opens Fyn in a contextual capture (the
                // clients post `contextual` to /api/ai-chat/contextual-
                // conversations); everything else deep-links to the module
                // screen where the user actions it (RecommendationRouting).
                'action' => ($contextual = RecommendationRouting::contextualFor($ruleKey)) !== null
                    ? [
                        'kind' => 'fyn_capture',
                        'payload' => $module,
                        // The complete POST /api/ai-chat/contextual-conversations
                        // body — clients send it verbatim, composing nothing.
                        'contextual' => [
                            'action' => $contextual['action'],
                            'resource_type' => $contextual['resource_type'],
                            'resource_id' => null,
                            'current_destination' => GateRoutes::destination(
                                app(ContextualResourceResolver::class)->overviewScreenFor($contextual['resource_type']),
                            ),
                            'origin' => ['kind' => 'recommendation', 'recommendation_id' => $id],
                        ],
                    ]
                    : ['kind' => 'navigate', ...RecommendationRouting::pageFor($ruleKey, $module, $rec)],
                // What the action's own card needs and the row does not show
                // (ActionCardService, design C, CSJ 2026-09-26).
                'card' => [
                    'category' => $rec['category'] ?? null,
                    'timeline' => $rec['timeline'] ?? null,
                    'personalised_context' => array_values(array_filter((array) ($rec['personalised_context'] ?? []), 'is_string')),
                    'conflict_note' => $rec['conflict_note'] ?? null,
                    'potential_benefit' => $benefit,
                    'requires_advice' => (bool) ($rec['requires_advice'] ?? false),
                ],
            ];
        }, $all);
    }

    /**
     * The dashboard row shows the headline only. Every engine phrases a
     * recommendation as "Headline — explanation" (the aggregator joins title
     * and description with an em dash; tax strategy titles carry a second dash
     * for their tagline), so the row keeps the words before the FIRST dash and
     * the rest travels as `detail`. Only em/en dashes split — a hyphen inside a
     * name ("Chen Tech Consulting - Business Reserve") is part of the headline.
     *
     * @return array{0: string, 1: string|null}
     */
    public static function splitHeadline(string $text): array
    {
        $parts = preg_split('/\s+[\x{2014}\x{2013}]\s+/u', $text, 2) ?: [$text];
        $title = trim($parts[0]);
        $detail = isset($parts[1]) ? trim($parts[1]) : '';

        return [$title !== '' ? $title : $text, $detail !== '' ? $detail : null];
    }

    /**
     * Human-readable category label, preserving "ISA" casing (Rule #9).
     */
    public function categoryLabel(string $category): string
    {
        $label = ucwords(str_replace('_', ' ', $category));

        return preg_replace('/\bIsa\b/', 'ISA', $label) ?? $label;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    /**
     * CSJ 2026-09-16: an action while the two accounts are NOT linked. Until
     * they are, the plan runs on the figures the user typed about their
     * spouse rather than the spouse's real allowances and tax position, and
     * nothing else on the dashboard says so. One item at a time, in the one
     * actions model, so it reaches web and `/m` alike:
     *   - a request waiting on THIS user to accept (they were invited),
     *   - a spouse on file whose account has not linked yet (they invited),
     *   - married with no spouse on file at all.
     * It disappears the moment the link exists; there is nothing to mark done.
     *
     * @return array<int,array<string,mixed>>
     */
    private function spouseLinkItems(User $user): array
    {
        if ($user->liveSpouseId() !== null) {
            return [];
        }

        $isCoupled = in_array($user->marital_status, ['married', 'civil_partnership'], true);
        $card = FamilyMember::where('user_id', $user->id)->where('relationship', 'spouse')->latest('id')->first();
        if (! $isCoupled && $card === null) {
            return [];
        }

        $partnerWord = $user->marital_status === 'civil_partnership' ? 'partner' : 'spouse';
        $name = trim((string) ($card->first_name ?? ''));
        $named = $name !== '' ? $name : 'your '.$partnerWord;

        $pending = SpousePermission::where('spouse_id', $user->id)->where('status', 'pending')->latest('id')->first();
        if ($pending !== null) {
            $requester = User::find($pending->user_id);
            $requesterName = trim((string) ($requester->first_name ?? ''));
            $title = 'Accept '.($requesterName !== '' ? $requesterName."'s" : "your {$partnerWord}'s").' request to link your accounts';
            $meta = "You'll each see the other's assets, income and allowances, and your plan uses their real figures.";
        } elseif ($card !== null) {
            $title = 'Link '.$named."'s Fynla account";
            $meta = 'Until it is linked your plan uses the figures you gave us, not their real allowances and tax position.';
        } else {
            $title = 'Add your '.$partnerWord;
            $meta = 'Joint planning needs their details — allowance transfers and the joint tax picture depend on it.';
        }

        return [[
            'id' => 'household:spouse_link',
            'type' => 'unlock',
            'module' => 'household',
            'title' => $title,
            'meta' => $meta,
            'value' => (float) config('gamification.spouse_link_action_weight', 70),
            'done' => false,
            'action' => [
                'kind' => 'navigate',
                // `/m` carries the sharing panel at its own path; the web
                // destination resolves to /settings/family.
                'payload' => '/spouse-sharing',
                'destination' => GateRoutes::destination(GateRoutes::SPOUSE_SHARING),
            ],
        ]];
    }

    private function unlockItems(User $user): array
    {
        $weight = (float) config('gamification.unlock_action_weight', 65);
        $items = [];

        foreach (self::UNLOCK_MODULES as $module) {
            $gate = $this->gate->enforce($module, $user);
            if ($gate['can_proceed'] === true) {
                continue;
            }

            $action = $gate['required_actions'][0] ?? ['label' => 'Add your details', 'route' => '/dashboard'];

            $items[] = [
                'id' => 'unlock:'.$module,
                'type' => 'unlock',
                'module' => $module,
                'title' => 'Unlock '.$this->moduleLabel($module).' advice',
                'meta' => $action['label'] ?? 'A few quick questions',
                'value' => $weight,
                'done' => false,
                'action' => ['kind' => 'fyn_capture', 'payload' => $module, 'prompt' => RecommendationRouting::unlockPrompt($module)],
            ];
        }

        return $items;
    }

    /**
     * Strategy-level unlock cards: tax gate open but individual strategies are
     * locked by a single missing data point each. Teases the unlock to the user
     * and routes to tax strategy capture. Uses type 'unlock' so the frontend
     * carousel styles and tap-handler (openFynForCapture) work without changes;
     * the id prefix 'strategy_unlock:' distinguishes them from module unlocks.
     * At most MAX_STRATEGY_UNLOCKS items so they never crowd the 4-slot list.
     *
     * These carry a slightly higher weight than module-level unlocks (+5) because
     * they are more specific: we know the exact missing data point, which makes
     * them more actionable than a generic "unlock module" prompt.
     *
     * @return array<int,array<string,mixed>>
     */
    private function strategyUnlockItems(User $user): array
    {
        if ($this->gate->enforce('tax_optimisation', $user)['can_proceed'] !== true) {
            return [];
        }

        $plan = $this->taxPlan->forUser($user);
        $weight = (float) config('gamification.unlock_action_weight', 65) + 5.0;
        $items = [];

        foreach (array_slice($plan['locked'], 0, self::MAX_STRATEGY_UNLOCKS) as $locked) {
            $noun = $this->unlockNounFor((string) ($locked['missing'][0] ?? ''));

            $items[] = [
                'id' => 'strategy_unlock:'.$locked['strategy_type'],
                'type' => 'unlock',
                'module' => 'tax',
                // Per-item label (CSJ 4.2): name the specific missing detail
                // ("Unlock pension info" / "Enter your pension details") rather
                // than a generic "Unlock a tax strategy" — the user has already
                // seen a strategy for what they have; this is about adding more.
                'title' => 'Unlock '.$noun.' info',
                'meta' => 'Enter your '.$noun.' details',
                'value' => $weight,
                'done' => false,
                'action' => ['kind' => 'fyn_capture', 'payload' => 'tax', 'prompt' => RecommendationRouting::unlockPrompt('tax')],
            ];
        }

        return $items;
    }

    /**
     * Short noun for an unlock card title, derived from the missing data point
     * (CSJ 4.2). Keeps the card item-specific ("pension", "ISA") rather than the
     * verbose data-point label; falls back to the household-context label.
     */
    private function unlockNounFor(string $missingKey): string
    {
        return match ($missingKey) {
            'pension_contributions', 'workplace_pension', 'pension_input_history' => 'pension',
            'isa_subscriptions_ytd' => 'ISA',
            'gia_holdings' => 'investment',
            'dividend_income' => 'dividend',
            'savings_balances' => 'savings',
            'annual_income' => 'income',
            'spouse_income' => "spouse's income",
            default => HouseholdFinancialContext::labelFor($missingKey),
        };
    }

    /**
     * The in-sentence form, for copy that reads "Unlock estate planning
     * advice". For anything that NAMES the module as a label, use
     * moduleDisplayLabel() — the one vocabulary the clients render.
     */
    private function moduleLabel(string $module): string
    {
        return match ($module) {
            'protection' => 'protection',
            'savings' => 'savings',
            'investment' => 'investment',
            'retirement' => 'retirement',
            'estate' => 'estate planning',
            'goals' => 'goals',
            default => $module,
        };
    }

    /**
     * The canonical module label, matching the nav vocabulary
     * (subNavConfig/SideMenu/moduleConfigs). It used to live in three
     * places — here, ActionsDashboard.vue and the `/m` Actions.vue — and had
     * already drifted ("Estate Planning" vs "Estate planning"). The server
     * now sends it on every item as `module_label` and both clients render
     * what they are given (CSJ 2026-09-17).
     */
    public static function moduleDisplayLabel(string $module): string
    {
        return match ($module) {
            'protection' => 'Protection',
            'savings' => 'Savings',
            'investment' => 'Investment',
            'retirement' => 'Retirement',
            'estate' => 'Estate Planning',
            'goals' => 'Goals',
            'tax' => 'Tax Strategy',
            'household' => 'Household',
            'general' => 'General',
            default => ucfirst($module),
        };
    }
}
