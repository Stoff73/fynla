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

    /** @param  array<string, mixed>  $item */
    private function open(User $user, array $item): array
    {
        $id = (string) $item['id'];
        $module = (string) $item['module'];
        $card = (array) ($item['card'] ?? []);
        $taxItem = $this->taxItem($user, $id);
        $isRecommendation = ($item['type'] ?? '') === 'recommendation';

        return [
            'id' => $id,
            'type' => (string) $item['type'],
            'module' => $module,
            'module_label' => (string) ($item['module_label'] ?? NextActionsService::moduleDisplayLabel($module)),
            'topic' => self::topicFor(isset($card['category']) ? (string) $card['category'] : null),
            'deadline' => $isRecommendation ? $this->deadline($taxItem, $card) : null,
            'title' => (string) $item['title'],
            'description' => (string) ($taxItem['description'] ?? $item['detail'] ?? $item['meta'] ?? ''),
            'why' => $isRecommendation
                ? ($taxItem !== null ? ActionCardFigures::why($taxItem) : (array) ($card['personalised_context'] ?? []))
                : [],
            'what_this_changes' => $isRecommendation ? [] : [self::UNLOCK_CONSEQUENCES[$module] ?? self::UNLOCK_CONSEQUENCES['tax']],
            'key_figure' => $this->keyFigure($card['potential_benefit'] ?? null),
            'how_to' => $this->howTo($module, $taxItem['type'] ?? null, $card['definition_key'] ?? null),
            'conflict_note' => $card['conflict_note'] ?? null,
            'disclaimer' => ($card['requires_advice'] ?? false) || in_array($module, ['protection', 'investment'], true) ? self::DISCLAIMER : null,
            'ask_fyn' => isset($item['action']['contextual'])
                ? ['kind' => 'contextual', 'request' => $item['action']['contextual']]
                : ['kind' => 'prompt', 'prompt' => 'Tell me more about: '.$item['title']],
            'primary' => $isRecommendation
                ? ['kind' => 'mark_done', 'recommendation_id' => $id]
                : ($item['action']['kind'] === 'navigate'
                    ? ['kind' => 'navigate', 'destination' => $item['action']['destination'] ?? null, 'payload' => $item['action']['payload'] ?? null]
                    : ['kind' => 'capture', 'prompt' => (string) ($item['action']['prompt'] ?? '')]),
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
     * 2026-09-25). A tax action is found by its strategy type, any other by
     * the definition key its adapter carried.
     *
     * @return list<string>
     */
    private function howTo(string $module, ?string $strategyType, ?string $definitionKey): array
    {
        $model = self::DEFINITIONS[$module] ?? null;
        if ($model === null || ($strategyType === null && $definitionKey === null)) {
            return [];
        }
        $steps = $model::query()
            ->where('how_to_status', 'approved')
            ->where($strategyType !== null ? 'strategy_type' : 'key', $strategyType ?? $definitionKey)
            ->value('how_to_steps');
        $steps = is_string($steps) ? json_decode($steps, true) : $steps;

        return is_array($steps) ? array_values(array_filter($steps, 'is_string')) : [];
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
            'why' => $taxItem !== null ? ActionCardFigures::why($taxItem) : [],
            'what_this_changes' => [],
            'key_figure' => null,
            'how_to' => [],
            'conflict_note' => null,
            'disclaimer' => null,
            'ask_fyn' => ['kind' => 'prompt', 'prompt' => 'Tell me more about: '.$title],
            'primary' => null,
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
     * @param  array<string, mixed>|null  $taxItem
     * @param  array<string, mixed>  $card
     * @return array{label: string, closes_on: string|null}
     */
    private function deadline(?array $taxItem, array $card): array
    {
        $end = $this->taxConfig->getEffectiveTo();
        if ($taxItem !== null && in_array($taxItem['type'], ActionCardFigures::ANNUAL_ALLOWANCE_TYPES, true) && $end !== '') {
            $date = Carbon::parse($end);

            return ['label' => 'Closes '.$date->format('j F'), 'closes_on' => $date->toDateString()];
        }

        return ['label' => 'Worth reviewing', 'closes_on' => null];
    }

    /** @return array{label: string, value: string, sub: string|null}|null */
    private function keyFigure(mixed $benefit): ?array
    {
        return is_numeric($benefit) && (float) $benefit > 0
            ? ['label' => 'Saves about', 'value' => '£'.number_format((float) $benefit).' a year', 'sub' => null]
            : null;
    }
}
