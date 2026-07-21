<?php

declare(strict_types=1);

namespace App\Services\AI\Memory\Procedural;

use Illuminate\Support\Carbon;

/**
 * Immutable loaded procedural corpus + the typed read surface that Phase 4b–4d
 * consumers use. Pure data, no I/O.
 */
final class ProceduralCorpus
{
    /** @param list<Procedure> $procedures */
    public function __construct(private readonly array $procedures) {}

    /** @return list<Procedure> */
    public function all(): array
    {
        return array_values($this->procedures);
    }

    /** @return list<Procedure> */
    public function ofKind(string $kind): array
    {
        return array_values(array_filter($this->procedures, fn (Procedure $p): bool => $p->kind === $kind));
    }

    /** @return list<Procedure> all versions of one procedure, ascending by version */
    public function versions(string $procedureId): array
    {
        $matches = array_values(array_filter(
            $this->procedures,
            fn (Procedure $p): bool => $p->procedureId === $procedureId,
        ));
        usort($matches, fn (Procedure $a, Procedure $b): int => $a->version <=> $b->version);

        return $matches;
    }

    /** The active version effective on $asOf (default now) for $provider; highest version wins ties. */
    public function active(string $procedureId, string $provider = 'anthropic', ?Carbon $asOf = null): ?Procedure
    {
        $asOf ??= Carbon::now();
        $candidates = array_values(array_filter(
            $this->procedures,
            fn (Procedure $p): bool => $p->procedureId === $procedureId
                && $p->provider === $provider
                && $p->active
                && $p->effectiveOn($asOf),
        ));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, fn (Procedure $a, Procedure $b): int => $b->version <=> $a->version);

        return $candidates[0];
    }
}
