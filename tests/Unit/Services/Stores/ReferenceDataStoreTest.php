<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\ReferenceData\ReferenceDataStore;
use Illuminate\Support\Facades\Event;

/**
 * Minimal in-memory fixture concrete subclass to exercise the base.
 * Defined inline because it's only used by these tests.
 */
class FakeRefStore extends ReferenceDataStore
{
    public array $rows = [];

    protected function entityKey(): string
    {
        return 'fake_ref';
    }

    protected function persist(array $canonical, ?int $id = null): int
    {
        if ($id === null) {
            $id = count($this->rows) + 1;
            $this->rows[$id] = $canonical;
        } else {
            $this->rows[$id] = array_merge($this->rows[$id] ?? [], $canonical);
        }

        return $id;
    }

    protected function read(int $id): array
    {
        return $this->rows[$id] ?? [];
    }

    protected function delete_(int $id): void
    {
        unset($this->rows[$id]);
    }
}

beforeEach(function () {
    Event::fake([ReferenceDataUpdated::class]);
});

it('rejects writes from non-admin / non-seeder sources', function () {
    $store = new FakeRefStore;

    expect(fn () => $store->create(['k' => 'v'], IngestSource::FORM))
        ->toThrow(StoreValidationException::class);
    expect(fn () => $store->create(['k' => 'v'], IngestSource::FYN_AI))
        ->toThrow(StoreValidationException::class);
    expect(fn () => $store->create(['k' => 'v'], IngestSource::UPLOAD))
        ->toThrow(StoreValidationException::class);
});

it('accepts ADMIN and SEEDER writes', function () {
    $store = new FakeRefStore;

    $id1 = $store->create(['k' => 'v1'], IngestSource::ADMIN, actorUserId: 1);
    $id2 = $store->create(['k' => 'v2'], IngestSource::SEEDER);

    expect($id1)->toBe(1);
    expect($id2)->toBe(2);
});

it('emits ReferenceDataUpdated on create', function () {
    $store = new FakeRefStore;
    $id = $store->create(['k' => 'v'], IngestSource::ADMIN, actorUserId: 9);

    Event::assertDispatched(ReferenceDataUpdated::class, function ($e) use ($id) {
        return $e->entityKey === 'fake_ref'
            && $e->entityId === $id
            && $e->changedKeys === ['k']
            && $e->actorUserId === 9;
    });
});

it('memoises reads within the same request', function () {
    $store = new FakeRefStore;
    $id = $store->create(['k' => 'v'], IngestSource::ADMIN, actorUserId: 1);

    $first = $store->find($id);
    $store->rows[$id] = ['k' => 'modified-out-of-band'];
    $second = $store->find($id);

    // Memoised: second call returns the cached first read, not the out-of-band mutation.
    expect($second)->toBe($first);
});

it('invalidates the cache after a write', function () {
    $store = new FakeRefStore;
    $id = $store->create(['k' => 'v1'], IngestSource::ADMIN, actorUserId: 1);

    $store->find($id);                          // primes cache
    $store->update($id, ['k' => 'v2'], IngestSource::ADMIN, actorUserId: 1);

    expect($store->find($id))->toBe(['k' => 'v2']);
});
