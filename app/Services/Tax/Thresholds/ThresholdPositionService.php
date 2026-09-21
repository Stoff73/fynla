<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;

/**
 * Runs every catalogue line against one user and orders what applies by how
 * near it is. Money lines first, by absolute distance; date lines after, by
 * days. The strip is the nearest line the user can still act on (has a lever);
 * a line with no lever never leads. Empty output means the strip does not render.
 */
final class ThresholdPositionService
{
    /** @param iterable<ThresholdLine> $lines */
    public function __construct(
        private readonly IncomeDefinitionsService $definitions,
        private readonly iterable $lines,
    ) {}

    /** @return array{strip: ?string, suppressed: int, lines: list<array<string, mixed>>} */
    public function evaluate(User $user): array
    {
        $context = new ThresholdContext($user, $this->definitions->calculate($user->id));

        $results = [];
        $catalogue = 0;
        foreach ($this->lines as $line) {
            $catalogue++;
            $result = $line->evaluate($context);
            if ($result !== null) {
                $results[] = $result;
            }
        }

        usort($results, function (ThresholdResult $a, ThresholdResult $b): int {
            if ($a->isDateLine() !== $b->isDateLine()) {
                return $a->isDateLine() ? 1 : -1;
            }

            return abs($a->position['distance']) <=> abs($b->position['distance']);
        });

        $strip = null;
        foreach ($results as $result) {
            if ($result->lever !== null) {
                $strip = $result->key;
                break;
            }
        }

        // How many catalogue lines the user is nowhere near, so the expanded view can
        // say so instead of listing a line they cannot cross.
        $suppressed = $catalogue - count($results);

        return ['strip' => $strip, 'suppressed' => $suppressed, 'lines' => array_map(fn (ThresholdResult $r): array => $r->toArray(), $results)];
    }
}
