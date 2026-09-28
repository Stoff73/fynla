<?php

declare(strict_types=1);

namespace App\Services\Actions;

use App\Services\TaxConfigService;
use Carbon\Carbon;

/**
 * Which lane an open action sits in on the actions page — the approved design
 * B, "deadline lanes" ("Fynla Actions Layouts" canvas, CSJ 2026-09-17): the
 * ONE place the grouping is decided, so web, /m and the card's deadline label
 * never disagree.
 *
 *   before_tax_year_end — an allowance set per tax year, lost on 5 April
 *   soon                — worth doing, no hard deadline
 *   waiting             — not an action: data we need first (unlocks, links)
 */
final class ActionLanes
{
    public const BEFORE_TAX_YEAR_END = 'before_tax_year_end';

    public const SOON = 'soon';

    public const WAITING = 'waiting';

    /**
     * Other modules' actions on an allowance set per tax year: the ISA and
     * Junior ISA allowances do not carry over
     * (https://www.gov.uk/individual-savings-accounts/how-isas-work,
     * https://www.gov.uk/junior-individual-savings-accounts).
     */
    private const TAX_YEAR_DEFINITION_KEYS = ['excess_cash_isa_available', 'child_no_jisa'];

    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /** @param  array<string, mixed>  $item  an open action from NextActionsService */
    public static function laneFor(array $item): string
    {
        if (($item['type'] ?? '') !== 'recommendation') {
            return self::WAITING;
        }
        $id = (string) ($item['id'] ?? '');
        $taxYearBound = str_starts_with($id, 'tax_')
            ? self::closesAtTaxYearEnd(substr($id, 4))
            : in_array($item['card']['definition_key'] ?? null, self::TAX_YEAR_DEFINITION_KEYS, true);

        return $taxYearBound ? self::BEFORE_TAX_YEAR_END : self::SOON;
    }

    public static function closesAtTaxYearEnd(?string $strategyType): bool
    {
        return in_array($strategyType, ActionCardFigures::ANNUAL_ALLOWANCE_TYPES, true);
    }

    /** "5 April", from the active tax year's end in tax config; null without one. */
    public function taxYearEnd(): ?Carbon
    {
        $end = $this->taxConfig->getEffectiveTo();

        return $end === '' ? null : Carbon::parse($end);
    }

    /**
     * Each item stamped with its lane (and "Closes 5 April" where it has
     * one), plus the lane headings in page order, empty lanes left out.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{items: list<array<string, mixed>>, lanes: list<array{key: string, title: string, sub: string, count: int}>}
     */
    public function group(array $items): array
    {
        $end = $this->taxYearEnd();
        $items = array_map(function (array $item) use ($end): array {
            $item['lane'] = self::laneFor($item);
            $item['closes'] = $item['lane'] === self::BEFORE_TAX_YEAR_END && $end !== null
                ? 'Closes '.$end->format('j F')
                : null;

            return $item;
        }, $items);

        $count = static fn (string $lane): int => count(array_filter($items, fn (array $i): bool => $i['lane'] === $lane));
        $days = $end !== null ? max(0, (int) Carbon::today()->diffInDays($end, false)) : null;

        $lanes = array_values(array_filter([
            [
                'key' => self::BEFORE_TAX_YEAR_END,
                'title' => $end !== null ? 'Before '.$end->format('j F') : 'Before the tax year ends',
                'sub' => $days !== null ? sprintf('%d %s — these do not carry over', $days, $days === 1 ? 'day' : 'days') : 'These do not carry over',
                'count' => $count(self::BEFORE_TAX_YEAR_END),
            ],
            [
                'key' => self::SOON,
                'title' => 'Worth doing soon',
                'sub' => 'No deadline, but the longer they sit the more they cost',
                'count' => $count(self::SOON),
            ],
            [
                'key' => self::WAITING,
                'title' => 'Waiting on you',
                'sub' => 'Not things to do — things we need before the figures above can be right.',
                'count' => $count(self::WAITING),
            ],
        ], fn (array $lane): bool => $lane['count'] > 0));

        return ['items' => $items, 'lanes' => $lanes];
    }
}
