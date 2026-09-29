<?php

declare(strict_types=1);

namespace App\Services\Actions;

use App\Models\EstateActionDefinition;
use App\Models\InvestmentActionDefinition;
use App\Models\PlanActionFundingSelection;
use App\Models\ProtectionActionDefinition;
use App\Models\RecommendationTracking;
use App\Models\RetirementActionDefinition;
use App\Models\SavingsActionDefinition;
use App\Models\TaxActionDefinition;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Mobile\NextActionsService;
use App\Services\Plans\FundingAccounts;
use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * One action's detail card (design C, "Fynla Actions Layouts" canvas; CSJ
 * 2026-09-26) — the ONE place a card's fields are decided. It reads the same
 * list the actions page shows (NextActionsService::buildAll), so the card's id,
 * title and routing are the row's; web, /m and iOS render it as sent.
 */
final class ActionCardService
{
    public const DISCLAIMER = 'Fynla does not recommend products. This is guidance based on the figures you have entered.';

    /**
     * What "Ask Fyn about this" sends, before the card's title. Fyn recognises a
     * card's question by it (forAskFynMessage), so a card is grounded however the
     * question is classified.
     */
    public const ASK_FYN_PREFIX = 'Tell me more about: ';

    /** What an unlock item is holding back, per module (canvas "What this changes"). */
    private const UNLOCK_CONSEQUENCES = [
        'protection' => 'We cannot check your cover against what your family would need until this is in.',
        'savings' => 'Your emergency fund and savings allowances cannot be worked out until this is in.',
        'investment' => 'Your investment allowances and fees cannot be checked until this is in.',
        'retirement' => 'Your retirement projection is understated until this is in.',
        'estate' => 'Your Inheritance Tax estimate is incomplete until this is in.',
        'goals' => 'Your goals cannot be tracked until one is set.',
        'tax' => 'Your tax plan leaves out the saving this would unlock until it is in.',
        'household' => 'Your plan runs on what you told us about your spouse, not their own figures, until your accounts are linked.',
    ];

    public function __construct(
        private readonly NextActionsService $actions,
        private readonly ComposedTaxPlanService $taxPlan,
        private readonly TaxConfigService $taxConfig,
    ) {}

    /** Engine categories that describe severity or nothing, not a topic. */
    private const NOT_A_TOPIC = ['warning', 'general', 'recommended'];

    /**
     * The card's topic from the engine category ("Income Band", "ISA
     * Allowance"), or null for a category that is not a topic ("Warning").
     */
    public static function topicFor(?string $category): ?string
    {
        if ($category === null || $category === '' || in_array(strtolower($category), self::NOT_A_TOPIC, true)) {
            return null;
        }
        $label = ucwords(str_replace('_', ' ', $category));

        return preg_replace('/\bIsa\b/', 'ISA', $label) ?? $label;
    }

    /** @return array<string, mixed>|null null when the id is not this user's */
    public function for(User $user, string $id): ?array
    {
        $item = collect($this->actions->buildAll($user->id))->firstWhere('id', $id);
        if (is_array($item)) {
            return $this->open($user, $item);
        }

        $done = RecommendationTracking::where('user_id', $user->id)
            ->where('recommendation_id', $id)
            ->completed()
            ->latest('completed_at')
            ->first();

        return $done === null ? null : $this->completed($user, $done);
    }

    /**
     * The card a message asked about through "Ask Fyn about this": the message is
     * ASK_FYN_PREFIX and the exact title of one of this user's actions, open or
     * done. Null for any other message.
     *
     * @return array<string, mixed>|null
     */
    public function forAskFynMessage(User $user, string $message): ?array
    {
        if (! str_starts_with($message, self::ASK_FYN_PREFIX)) {
            return null;
        }
        $title = trim(substr($message, strlen(self::ASK_FYN_PREFIX)));

        $item = collect($this->actions->buildAll($user->id))->first(fn (array $i): bool => ($i['title'] ?? null) === $title);
        if (is_array($item)) {
            return $this->open($user, $item);
        }

        $done = RecommendationTracking::where('user_id', $user->id)
            ->completed()
            ->latest('completed_at')
            ->get()
            ->first(fn (RecommendationTracking $row): bool => NextActionsService::splitHeadline((string) $row->recommendation_text)[0] === $title);

        return $done === null ? null : $this->completed($user, $done);
    }

    /** @param  array<string, mixed>  $item */
    private function open(User $user, array $item): array
    {
        $id = (string) $item['id'];
        $module = (string) $item['module'];
        $card = (array) ($item['card'] ?? []);
        $taxItem = $this->taxItem($user, $id);
        $isRecommendation = ($item['type'] ?? '') === 'recommendation';
        $howTo = $this->howTo($user, $module, $taxItem, $card['definition_key'] ?? null);

        return [
            'id' => $id,
            'type' => (string) $item['type'],
            'module' => $module,
            'module_label' => (string) ($item['module_label'] ?? NextActionsService::moduleDisplayLabel($module)),
            'topic' => self::topicFor(isset($card['category']) ? (string) $card['category'] : null),
            'deadline' => $isRecommendation ? $this->deadline($item, $card) : null,
            'title' => (string) $item['title'],
            'description' => (string) ($taxItem['description'] ?? $item['detail'] ?? $item['meta'] ?? ''),
            'why' => $isRecommendation
                ? ($taxItem !== null ? $howTo['why'] : (array) ($card['personalised_context'] ?? []))
                : [],
            'what_this_changes' => $isRecommendation
                ? $howTo['outcome']
                : [self::UNLOCK_CONSEQUENCES[$module] ?? self::UNLOCK_CONSEQUENCES['tax']],
            'key_figure' => self::keyFigureFor($module, $card['potential_benefit'] ?? null, $taxItem['type'] ?? null),
            'how_to' => $howTo['steps'],
            'learn_more' => $howTo['learn'],
            'conflict_note' => $card['conflict_note'] ?? null,
            'disclaimer' => ($card['requires_advice'] ?? false) || in_array($module, ['protection', 'investment'], true) ? self::DISCLAIMER : null,
            'ask_fyn' => isset($item['action']['contextual'])
                ? ['kind' => 'contextual', 'request' => $item['action']['contextual']]
                : ['kind' => 'prompt', 'prompt' => self::ASK_FYN_PREFIX.$item['title']],
            'primary' => $isRecommendation
                ? ['kind' => 'mark_done', 'recommendation_id' => $id]
                : ($item['action']['kind'] === 'navigate'
                    ? ['kind' => 'navigate', 'destination' => $item['action']['destination'] ?? null, 'payload' => $item['action']['payload'] ?? null]
                    : ['kind' => 'capture', 'prompt' => (string) ($item['action']['prompt'] ?? '')]),
            // Where a recommendation is actioned (the row's old destination),
            // shown as "Go to it" beside Mark as done (review I5).
            'go_to' => $isRecommendation && ($item['action']['kind'] ?? '') === 'navigate'
                ? ['destination' => $item['action']['destination'] ?? null, 'payload' => $item['action']['payload'] ?? null]
                : null,
            'funding' => $taxItem !== null && in_array($taxItem['type'], ActionCardFigures::FUNDED_TYPES, true)
                ? $this->funding($user, (string) $taxItem['type'])
                : null,
            'done' => false,
            'completed_at' => null,
        ];
    }

    /** The definition model per module, where its approved how-to steps live. */
    private const DEFINITIONS = [
        'tax' => TaxActionDefinition::class,
        'retirement' => RetirementActionDefinition::class,
        'investment' => InvestmentActionDefinition::class,
        'protection' => ProtectionActionDefinition::class,
        'savings' => SavingsActionDefinition::class,
        'estate' => EstateActionDefinition::class,
    ];

    /**
     * The action's how-to steps — only once CSJ has approved them (ruling
     * 2026-09-25) — as the branches the user's own records pick, filled with
     * their own figures (CSJ 2026-09-28). A tax action is found by its
     * strategy type, any other by the definition key its adapter carried.
     *
     * @param  array<string, mixed>|null  $taxItem
     * @return array{steps: list<string>, why: list<string>, outcome: list<string>, learn: list<array{label: string, url: string}>} the steps, why it matters to the user, what it changes, and where to read more
     */
    private function howTo(User $user, string $module, ?array $taxItem, ?string $definitionKey): array
    {
        $none = ['steps' => [], 'why' => [], 'outcome' => [], 'learn' => []];
        $model = self::DEFINITIONS[$module] ?? null;
        $strategyType = $taxItem['type'] ?? null;
        if ($model === null || ($strategyType === null && $definitionKey === null)) {
            return $none;
        }
        $steps = $model::query()
            ->where('how_to_status', 'approved')
            ->where($strategyType !== null ? 'strategy_type' : 'key', $strategyType ?? $definitionKey)
            ->value('how_to_steps');
        $steps = is_string($steps) ? json_decode($steps, true) : $steps;
        if (! is_array($steps) || $steps === []) {
            return $none;
        }
        ['facts' => $facts, 'text' => $text] = app(ActionHowToFacts::class)->for($user, $taxItem);

        return [
            'steps' => ActionHowTo::render($steps, $facts, $text),
            'why' => ActionHowTo::render($steps, $facts, $text, 'why'),
            'outcome' => ActionHowTo::render($steps, $facts, $text, 'outcome'),
            // "Label | /path": a page of Fynla's own help, never an outside site.
            'learn' => array_values(array_filter(array_map(static function (string $line): ?array {
                [$label, $url] = array_map('trim', explode('|', $line, 2) + [1 => '']);

                return $label !== '' && str_starts_with($url, '/') ? ['label' => $label, 'url' => $url] : null;
            }, ActionHowTo::render($steps, $facts, $text, 'learn')))),
        ];
    }

    /**
     * "Fund from": the user's eligible accounts (FundingAccounts, the one list)
     * and their saved pick for this action, or the recommended account.
     *
     * @return array{accounts: list<array<string, mixed>>, selected_id: int|null, selected_type: string|null}
     */
    private function funding(User $user, string $strategyType): array
    {
        $funding = app(FundingAccounts::class);
        $accounts = $funding->eligibleFor($user);
        $saved = PlanActionFundingSelection::getForUser($user->id, 'tax')->get($strategyType.'_0');
        $selected = $saved !== null
            ? collect($accounts)->first(fn ($a) => $a['id'] === (int) $saved->funding_source_id && $a['type'] === $saved->funding_source_type)
            : null;
        $selected ??= $funding->recommend($accounts);

        return [
            'accounts' => $accounts,
            'selected_id' => $selected['id'] ?? null,
            'selected_type' => $selected['type'] ?? null,
        ];
    }

    private function completed(User $user, RecommendationTracking $row): array
    {
        $id = (string) $row->recommendation_id;
        [$title, $detail] = NextActionsService::splitHeadline((string) $row->recommendation_text);
        $taxItem = $this->taxItem($user, $id);

        return [
            'id' => $id,
            'type' => 'recommendation',
            'module' => (string) $row->module,
            'module_label' => NextActionsService::moduleDisplayLabel((string) $row->module),
            'topic' => null,
            'deadline' => null,
            'title' => $title,
            'description' => (string) ($taxItem['description'] ?? $detail ?? ''),
            'why' => $taxItem !== null ? $this->howTo($user, 'tax', $taxItem, null)['why'] : [],
            'what_this_changes' => [],
            'key_figure' => null,
            'how_to' => [],
            'learn_more' => [],
            'conflict_note' => null,
            'disclaimer' => null,
            'ask_fyn' => ['kind' => 'prompt', 'prompt' => self::ASK_FYN_PREFIX.$title],
            'primary' => null,
            'go_to' => null,
            'funding' => null,
            'done' => true,
            'completed_at' => $row->completed_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed>|null the composed tax plan item behind a tax_ id */
    private function taxItem(User $user, string $id): ?array
    {
        if (! str_starts_with($id, 'tax_')) {
            return null;
        }
        $type = substr($id, 4);

        return collect($this->taxPlan->forUser($user)['items'])->firstWhere('type', $type);
    }

    /**
     * "Closes 5 April" for an annual allowance that does not carry over, dated
     * from the active tax year's end in tax config; "Worth reviewing" otherwise.
     *
     * @param  array<string, mixed>  $item  the open action
     * @param  array<string, mixed>  $card
     * @return array{label: string, closes_on: string|null}
     */
    private function deadline(array $item, array $card): array
    {
        $end = $this->taxConfig->getEffectiveTo();
        // The same rule that puts the action in the "Before 5 April" lane.
        if (ActionLanes::laneFor($item) === ActionLanes::BEFORE_TAX_YEAR_END && $end !== '') {
            $date = Carbon::parse($end);

            return ['label' => 'Closes '.$date->format('j F'), 'closes_on' => $date->toDateString()];
        }

        if (($card['timeline'] ?? null) === 'immediate') {
            return ['label' => 'Immediate action', 'closes_on' => null];
        }

        return ['label' => 'Worth reviewing', 'closes_on' => null];
    }

    /**
     * The key figure from the action's benefit. An estate benefit is a one-off
     * Inheritance Tax saving (EstateRecommendationAdapter), never "a year";
     * every other module's benefit is an annual tax saving.
     *
     * @return array{label: string, value: string, sub: string|null}|null
     */
    public static function keyFigureFor(string $module, mixed $benefit, ?string $type = null): ?array
    {
        if (! is_numeric($benefit) || (float) $benefit < 1) {
            return null;
        }
        // Down, never up: the figure never promises more than was worked out.
        $pounds = '£'.number_format(floor((float) $benefit));

        return match (true) {
            $module === 'estate' => ['label' => 'Could reduce Inheritance Tax by about', 'value' => $pounds, 'sub' => null],
            in_array($type, ActionCardFigures::ONE_OFF_TYPES, true) => ['label' => 'Saves about', 'value' => $pounds, 'sub' => null],
            default => ['label' => 'Saves about', 'value' => $pounds.' a year', 'sub' => null],
        };
    }
}
