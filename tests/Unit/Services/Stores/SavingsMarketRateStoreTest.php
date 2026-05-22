<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\SavingsMarketRate;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsMarketRateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ReferenceDataUpdated::class]);
});

it('creates a row from an admin payload', function () {
    $store = new SavingsMarketRateStore;

    $id = $store->create([
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ], IngestSource::ADMIN, actorUserId: 1);

    $row = SavingsMarketRate::find($id);
    expect($row)->not->toBeNull()
        ->and($row->label)->toBe('Easy Access');
});

it('emits ReferenceDataUpdated on create', function () {
    $store = new SavingsMarketRateStore;

    $id = $store->create([
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ], IngestSource::ADMIN, actorUserId: 1);

    Event::assertDispatched(
        ReferenceDataUpdated::class,
        fn ($e) => $e->entityKey === 'savings_market_rate' && $e->entityId === $id
    );
});

it('updates a row through the store', function () {
    $store = new SavingsMarketRateStore;

    $id = $store->create([
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ], IngestSource::ADMIN, actorUserId: 1);

    $store->update($id, ['rate' => 0.050], IngestSource::ADMIN, actorUserId: 1);

    expect((float) SavingsMarketRate::find($id)->rate)->toEqualWithDelta(0.050, 0.0001);
});

it('refuses FORM / FYN_AI / UPLOAD ingest sources', function () {
    $store = new SavingsMarketRateStore;

    foreach ([IngestSource::FORM, IngestSource::FYN_AI, IngestSource::UPLOAD] as $bad) {
        expect(fn () => $store->create([
            'rate_key' => 'easy_access',
            'label' => 'Easy Access',
            'rate' => 0.045,
            'tax_year' => '2026/27',
            'effective_from' => '2026-04-06',
        ], $bad))->toThrow(StoreValidationException::class);
    }
});

it('memoises reads within a request', function () {
    $store = new SavingsMarketRateStore;

    $id = $store->create([
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ], IngestSource::ADMIN, actorUserId: 1);

    $r1 = $store->find($id);
    // mutate label out-of-band
    SavingsMarketRate::where('id', $id)->update(['label' => 'Mutated Out Of Band']);
    $r2 = $store->find($id);

    // memoised — original value survives the out-of-band mutation
    expect($r2['label'])->toBe('Easy Access');
});
