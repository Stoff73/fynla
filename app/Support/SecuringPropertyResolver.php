<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Stores\PropertyStore;
use Illuminate\Database\Eloquent\Model;

/**
 * Which property secures a mortgage — resolved once per request, for everybody.
 *
 * **A debt is shared exactly as the asset securing it is shared** (CSJ ruling,
 * W-0228), so every consumer that asks what a user owes on a mortgage has to
 * read the property behind it. Around twenty services do, several of them in the
 * same request.
 *
 * **This is a class rather than a static on the trait, and that is not a style
 * preference.** A `static` property declared in a trait is *per using class*:
 * `CalculatesOwnershipShare` is used by a dozen services, so a static memo there
 * is a dozen separate caches with no single way to clear them — which is exactly
 * what a test proved when it changed a property's ownership, cleared "the" cache,
 * and read the old share back. A container singleton is one cache, one clear, and
 * the same lifetime as the request that needs it.
 *
 * **Resolved without lazy loading.** `Model::preventLazyLoading()` is on
 * everywhere but production (`AppServiceProvider:208`), so touching
 * `$mortgage->property` on an unloaded relation throws. An eager-loaded relation
 * is preferred; otherwise the property is fetched by key.
 */
class SecuringPropertyResolver
{
    /**
     * Keyed by property id; null where the id resolves to nothing.
     *
     * Typed as `object` rather than `Property` on purpose. Naming the model here
     * would make this class a direct model consumer and trip
     * PropertyStoreBoundaryTest — rightly, because the READ belongs to
     * PropertyStore and this class only remembers its answer. `object` is also
     * the more truthful type: `for()` returns the mortgage itself when nothing
     * secures it.
     *
     * @var array<int, object|null>
     */
    private array $byPropertyId = [];

    public function __construct(
        private readonly PropertyStore $propertyStore
    ) {}

    /**
     * The record whose ownership a mortgage inherits.
     *
     * Returns the mortgage itself where nothing secures it — its own columns are
     * then the only information that exists. Stated so the fallback is not
     * mistaken for the rule.
     */
    public function for(object $mortgage): object
    {
        $propertyId = $mortgage->property_id ?? null;

        if ($propertyId === null) {
            return $mortgage;
        }

        if ($mortgage instanceof Model && $mortgage->relationLoaded('property')) {
            return $mortgage->getRelation('property') ?? $mortgage;
        }

        if (! array_key_exists($propertyId, $this->byPropertyId)) {
            $this->byPropertyId[$propertyId] = $this->propertyStore->findOwnershipBasis($propertyId);
        }

        return $this->byPropertyId[$propertyId] ?? $mortgage;
    }

    /**
     * Forget what has been resolved.
     *
     * A request is short enough that a property's ownership cannot change inside
     * one, so only tests need this — and they need it badly: a test that changes
     * a property and re-reads a mortgage share in the same process measures the
     * ownership it started with, and passes while proving nothing.
     */
    public function forget(): void
    {
        $this->byPropertyId = [];
    }
}
