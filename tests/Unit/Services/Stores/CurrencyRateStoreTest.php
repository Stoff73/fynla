<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\CurrencyRate;
use App\Services\Stores\CurrencyRateStore;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ReferenceDataUpdated::class]);
});

it('creates a row from an admin payload', function () {
    $store = new CurrencyRateStore;

    $id = $store->create([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
        'source' => 'manual',
    ], IngestSource::ADMIN, actorUserId: 1);

    $row = CurrencyRate::find($id);
    expect($row)->not->toBeNull()
        ->and($row->from_ccy)->toBe('GBP')
        ->and($row->to_ccy)->toBe('EUR')
        ->and((float) $row->rate)->toEqualWithDelta(1.17, 0.0001);
});

it('emits ReferenceDataUpdated on create', function () {
    $store = new CurrencyRateStore;

    $id = $store->create([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ], IngestSource::ADMIN, actorUserId: 1);

    Event::assertDispatched(
        ReferenceDataUpdated::class,
        fn ($e) => $e->entityKey === 'currency_rate' && $e->entityId === $id
    );
});

it('updates a row through the store', function () {
    $store = new CurrencyRateStore;

    $id = $store->create([
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ], IngestSource::ADMIN, actorUserId: 1);

    $store->update($id, ['rate' => 1.20], IngestSource::ADMIN, actorUserId: 1);

    expect((float) CurrencyRate::find($id)->rate)->toEqualWithDelta(1.20, 0.0001);
});

it('refuses FORM / FYN_AI / UPLOAD ingest sources', function () {
    $store = new CurrencyRateStore;

    foreach ([IngestSource::FORM, IngestSource::FYN_AI, IngestSource::UPLOAD] as $bad) {
        expect(fn () => $store->create([
            'from_ccy' => 'GBP',
            'to_ccy' => 'EUR',
            'rate' => 1.17,
            'effective_at' => '2026-04-06 00:00:00',
        ], $bad))->toThrow(StoreValidationException::class);
    }
});

it('latestFor returns the most-recent rate for a pair', function () {
    $store = new CurrencyRateStore;

    $store->create([
        'from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.15,
        'effective_at' => '2025-04-06 00:00:00',
    ], IngestSource::SEEDER);
    $store->create([
        'from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ], IngestSource::SEEDER);

    expect($store->latestFor('GBP', 'EUR'))->toEqualWithDelta(1.17, 0.0001);
    expect($store->latestFor('gbp', 'eur'))->toEqualWithDelta(1.17, 0.0001);
    expect($store->latestFor('GBP', 'GBP'))->toBe(1.0);
    expect($store->latestFor('GBP', 'JPY'))->toBeNull();
});

it('convert applies the latest rate', function () {
    $store = new CurrencyRateStore;

    $store->create([
        'from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ], IngestSource::SEEDER);

    expect($store->convert(100.0, 'GBP', 'EUR'))->toEqualWithDelta(117.0, 0.0001);
    expect($store->convert(100.0, 'GBP', 'GBP'))->toEqualWithDelta(100.0, 0.0001);
    expect($store->convert(100.0, 'GBP', 'JPY'))->toBeNull();
});

it('historical returns the rate effective on or before the given datetime', function () {
    $store = new CurrencyRateStore;

    $store->create([
        'from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.15,
        'effective_at' => '2025-04-06 00:00:00',
    ], IngestSource::SEEDER);
    $store->create([
        'from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
    ], IngestSource::SEEDER);

    expect($store->historical('GBP', 'EUR', Carbon::parse('2025-12-01')))
        ->toEqualWithDelta(1.15, 0.0001);
    expect($store->historical('GBP', 'EUR', Carbon::parse('2026-06-01')))
        ->toEqualWithDelta(1.17, 0.0001);
    expect($store->historical('GBP', 'EUR', Carbon::parse('2024-01-01')))
        ->toBeNull(); // before any rate
});
