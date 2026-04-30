<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * One recommended tax-optimisation action surfaced on the SaveTax dashboard.
 *
 * Each recommendation is emitted by TaxStrategyCalculator from pure rules + DB
 * + TaxConfigService — no LLM language generation. The dashboard renders the
 * full list (`recommendations` on TaxStrategyOutputDTO) sorted by `priority`
 * within `category`.
 *
 * Categories:
 *   - 'income_band' — strategies driven by user's tax band (taper rescue, additional-rate avoidance)
 *   - 'allowance'   — under-used allowances harvested at year end (ISA top-up, dividend allowance)
 *   - 'household'   — spouse / joint asset coordination
 *   - 'lifecycle'   — life-stage strategies (Lifetime ISA under 40, Junior ISA, Junior Pension)
 *   - 'warning'     — tapered Annual Allowance, MPAA gates — surfaces a downside risk, not a saving
 *
 * Priorities: 'high' | 'medium' | 'low'.
 *
 * `extra` carries strategy-specific fields (e.g. `suggested_transfer_amount`,
 * `available_allowance`, `amount_transferred`) and is merged into the
 * top-level array on serialisation, so frontend components and tests can read
 * them without nested traversal — preserves backward compatibility with the
 * pre-Phase-1 array shape.
 */
final class StrategyRecommendation
{
    /**
     * @param  array<string, mixed>  $extra
     */
    public function __construct(
        public readonly string $type,
        public readonly string $category,
        public readonly string $priority,
        public readonly string $title,
        public readonly string $description,
        public readonly ?float $estimatedAnnualTaxSaved = null,
        public readonly bool $requiresAdvice = false,
        public readonly array $extra = [],
    ) {}

    /**
     * Wrap a legacy suggestion array (as built by the pre-Phase-1 calculator
     * helpers) in the typed DTO. Keys recognised at the top level become typed
     * fields; everything else flows into `extra` so it round-trips on
     * serialisation.
     *
     * @param  array<string, mixed>  $arr
     */
    public static function fromArray(string $category, array $arr): self
    {
        $reservedKeys = [
            'type', 'category', 'priority', 'title', 'description',
            'estimated_annual_tax_saved', 'requires_advice',
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

        return array_merge($base, $this->extra);
    }
}
