<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\StrategyCategory;
use App\Enums\StrategyPriority;

/**
 * One recommended tax-optimisation action surfaced on the SaveTax dashboard.
 *
 * Each recommendation is emitted by TaxStrategyCalculator from pure rules + DB
 * + TaxConfigService — no LLM language generation. The dashboard renders the
 * full list (`recommendations` on TaxStrategyOutputDTO) sorted by `priority`
 * within `category`.
 *
 * `category` and `priority` accept either a backed-enum case or its string
 * value; both are validated against StrategyCategory / StrategyPriority at
 * construction. The public properties remain string-typed so existing
 * array-shape consumers (frontend, tests) keep working unchanged.
 *
 * `extra` carries strategy-specific fields (e.g. `suggested_transfer_amount`,
 * `available_allowance`, `amount_transferred`) and is merged into the
 * top-level array on serialisation, so frontend components and tests can read
 * them without nested traversal — preserves backward compatibility with the
 * pre-Phase-1 array shape.
 */
final class StrategyRecommendation
{
    public readonly string $category;

    public readonly string $priority;

    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $type,
        StrategyCategory|string $category,
        StrategyPriority|string $priority,
        public readonly string $title,
        public readonly string $description,
        public readonly ?float $estimatedAnnualTaxSaved = null,
        public readonly bool $requiresAdvice = false,
        public readonly array $extra = [],
        public readonly ?float $requiredMonthlyCost = null,
        public readonly ?float $requiredLumpSum = null,
    ) {
        $this->category = $category instanceof StrategyCategory
            ? $category->value
            : StrategyCategory::from($category)->value;

        $this->priority = $priority instanceof StrategyPriority
            ? $priority->value
            : StrategyPriority::from($priority)->value;
    }

    public function categoryEnum(): StrategyCategory
    {
        return StrategyCategory::from($this->category);
    }

    public function priorityEnum(): StrategyPriority
    {
        return StrategyPriority::from($this->priority);
    }

    /**
     * Wrap a legacy suggestion array (as built by the pre-Phase-1 calculator
     * helpers) in the typed DTO. Keys recognised at the top level become typed
     * fields; everything else flows into `extra` so it round-trips on
     * serialisation.
     *
     * @param  array<string, mixed>  $arr
     */
    public static function fromArray(StrategyCategory|string $category, array $arr): self
    {
        $reservedKeys = [
            'type', 'category', 'priority', 'title', 'description',
            'estimated_annual_tax_saved', 'requires_advice',
            'required_monthly_cost', 'required_lump_sum',
        ];

        return new self(
            type: (string) ($arr['type'] ?? ''),
            category: $category,
            priority: (string) ($arr['priority'] ?? 'medium'),
            title: (string) ($arr['title'] ?? ''),
            description: (string) ($arr['description'] ?? ''),
            estimatedAnnualTaxSaved: isset($arr['estimated_annual_tax_saved'])
                ? (float) $arr['estimated_annual_tax_saved']
                : null,
            requiresAdvice: (bool) ($arr['requires_advice'] ?? false),
            extra: array_diff_key($arr, array_flip($reservedKeys)),
            requiredMonthlyCost: isset($arr['required_monthly_cost'])
                ? (float) $arr['required_monthly_cost']
                : null,
            requiredLumpSum: isset($arr['required_lump_sum'])
                ? (float) $arr['required_lump_sum']
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $base = [
            'type' => $this->type,
            'category' => $this->category,
            'priority' => $this->priority,
            'title' => $this->title,
            'description' => $this->description,
            'estimated_annual_tax_saved' => $this->estimatedAnnualTaxSaved,
            'requires_advice' => $this->requiresAdvice,
        ];

        // Cost fields are first-class for affordability ranking, but omitted
        // when null so tax plans (which never set them) serialise byte-identically
        // to the pre-cost shape — preserving planDigest parity.
        if ($this->requiredMonthlyCost !== null) {
            $base['required_monthly_cost'] = $this->requiredMonthlyCost;
        }
        if ($this->requiredLumpSum !== null) {
            $base['required_lump_sum'] = $this->requiredLumpSum;
        }

        return array_merge($base, $this->extra);
    }
}
