<?php

declare(strict_types=1);

use App\Events\ReferenceData\ReferenceDataUpdated;
use App\Models\TaxConfiguration;
use App\Models\TaxConfigurationAudit;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TaxConfigStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([ReferenceDataUpdated::class]);
    $this->actorId = User::factory()->create()->id;
});

function validTaxConfigPayload(array $overrides = []): array
{
    return array_merge([
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
        'effective_to' => '2027-04-05',
        'config_data' => [
            'income_tax' => ['personal_allowance' => 12570],
            'isa' => ['annual_allowance' => 20000],
        ],
        'is_active' => false,
        'notes' => 'Test tax year 2026/27',
    ], $overrides);
}

describe('TaxConfigStore::create', function () {
    it('creates a row from an admin payload', function () {
        $store = new TaxConfigStore;

        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN, actorUserId: $this->actorId);

        $row = TaxConfiguration::find($id);
        expect($row)->not->toBeNull()
            ->and($row->tax_year)->toBe('2026/27')
            ->and($row->is_active)->toBeFalse()
            ->and($row->config_data)->toMatchArray(['income_tax' => ['personal_allowance' => 12570]]);
    });

    it('writes a TaxConfigurationAudit row on create', function () {
        $store = new TaxConfigStore;

        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN, actorUserId: $this->actorId, rationale: 'Initial seed', ipAddress: '127.0.0.1');

        $audit = TaxConfigurationAudit::where('tax_configuration_id', $id)->first();
        expect($audit)->not->toBeNull()
            ->and($audit->change_type)->toBe('created')
            ->and($audit->changed_by_user_id)->toBe($this->actorId)
            ->and($audit->rationale)->toBe('Initial seed')
            ->and($audit->ip_address)->toBe('127.0.0.1');
    });

    it('emits ReferenceDataUpdated on create', function () {
        $store = new TaxConfigStore;

        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN, actorUserId: $this->actorId);

        Event::assertDispatched(
            ReferenceDataUpdated::class,
            fn ($e) => $e->entityKey === 'tax_configuration' && $e->entityId === $id
        );
    });

    it('refuses FORM / FYN_AI / UPLOAD ingest sources', function () {
        $store = new TaxConfigStore;

        foreach ([IngestSource::FORM, IngestSource::FYN_AI, IngestSource::UPLOAD] as $bad) {
            expect(fn () => $store->create(validTaxConfigPayload(), $bad))
                ->toThrow(StoreValidationException::class);
        }
    });

    it('rejects invalid tax_year format', function () {
        $store = new TaxConfigStore;

        expect(fn () => $store->create(
            validTaxConfigPayload(['tax_year' => '2026-27']),
            IngestSource::ADMIN
        ))->toThrow(StoreValidationException::class);
    });
});

describe('TaxConfigStore::update', function () {
    it('partially updates a row via merge', function () {
        $store = new TaxConfigStore;
        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN, actorUserId: $this->actorId);

        $store->update($id, ['notes' => 'Updated notes'], IngestSource::ADMIN, actorUserId: $this->actorId);

        expect(TaxConfiguration::find($id)->notes)->toBe('Updated notes');
        // tax_year preserved from existing row
        expect(TaxConfiguration::find($id)->tax_year)->toBe('2026/27');
    });

    it('writes an updated audit row with before_state', function () {
        $store = new TaxConfigStore;
        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN, actorUserId: $this->actorId);

        $store->update(
            $id,
            ['config_data' => ['income_tax' => ['personal_allowance' => 13000]]],
            IngestSource::ADMIN,
            actorUserId: $this->actorId,
            rationale: 'PA uplift'
        );

        $audit = TaxConfigurationAudit::where('tax_configuration_id', $id)
            ->where('change_type', 'updated')
            ->first();

        expect($audit)->not->toBeNull()
            ->and($audit->rationale)->toBe('PA uplift')
            ->and($audit->before_state['income_tax']['personal_allowance'])->toBe(12570)
            ->and($audit->after_state['income_tax']['personal_allowance'])->toBe(13000);
    });

    it('throws when updating a non-existent row', function () {
        $store = new TaxConfigStore;

        expect(fn () => $store->update(99999, ['notes' => 'x'], IngestSource::ADMIN))
            ->toThrow(StoreValidationException::class);
    });
});

describe('TaxConfigStore::setActive', function () {
    it('activates the chosen row and deactivates the prior active one', function () {
        $store = new TaxConfigStore;
        $oldId = $store->create(validTaxConfigPayload(['tax_year' => '2025/26', 'effective_from' => '2025-04-06', 'effective_to' => '2026-04-05', 'is_active' => true]), IngestSource::SEEDER);
        $newId = $store->create(validTaxConfigPayload(['tax_year' => '2026/27', 'is_active' => false]), IngestSource::ADMIN);

        $store->setActive($newId, IngestSource::ADMIN, actorUserId: $this->actorId);

        expect(TaxConfiguration::find($oldId)->is_active)->toBeFalse();
        expect(TaxConfiguration::find($newId)->is_active)->toBeTrue();
    });

    it('writes both deactivated and activated audit rows', function () {
        $store = new TaxConfigStore;
        $oldId = $store->create(validTaxConfigPayload(['tax_year' => '2025/26', 'effective_from' => '2025-04-06', 'effective_to' => '2026-04-05', 'is_active' => true]), IngestSource::SEEDER);
        $newId = $store->create(validTaxConfigPayload(['tax_year' => '2026/27']), IngestSource::ADMIN);

        $store->setActive($newId, IngestSource::ADMIN, actorUserId: $this->actorId, rationale: 'Promote 2026/27');

        expect(TaxConfigurationAudit::where('tax_configuration_id', $oldId)->where('change_type', 'deactivated')->count())->toBe(1);
        $activated = TaxConfigurationAudit::where('tax_configuration_id', $newId)->where('change_type', 'activated')->first();
        expect($activated)->not->toBeNull()
            ->and($activated->rationale)->toBe('Promote 2026/27');
    });

    it('throws when the target row does not exist', function () {
        $store = new TaxConfigStore;
        expect(fn () => $store->setActive(99999, IngestSource::ADMIN))
            ->toThrow(StoreValidationException::class);
    });
});

describe('TaxConfigStore::duplicate', function () {
    it('copies config_data into a new inactive row with new tax_year and dates', function () {
        $store = new TaxConfigStore;
        $sourceId = $store->create(validTaxConfigPayload(['tax_year' => '2026/27', 'is_active' => true]), IngestSource::ADMIN);

        $newId = $store->duplicate(
            $sourceId,
            '2027/28',
            '2027-04-06',
            '2028-04-05',
            IngestSource::ADMIN,
            actorUserId: $this->actorId
        );

        $new = TaxConfiguration::find($newId);
        expect($new)->not->toBeNull()
            ->and($new->tax_year)->toBe('2027/28')
            ->and($new->is_active)->toBeFalse()
            ->and($new->config_data['income_tax']['personal_allowance'])->toBe(12570);
    });

    it('writes a created audit row referencing the source tax_year', function () {
        $store = new TaxConfigStore;
        $sourceId = $store->create(validTaxConfigPayload(['tax_year' => '2026/27']), IngestSource::ADMIN);

        $newId = $store->duplicate($sourceId, '2027/28', '2027-04-06', '2028-04-05', IngestSource::ADMIN, actorUserId: $this->actorId);

        $audit = TaxConfigurationAudit::where('tax_configuration_id', $newId)->first();
        expect($audit)->not->toBeNull()
            ->and($audit->change_type)->toBe('created')
            ->and($audit->rationale)->toContain('2026/27');
    });

    it('throws when the source row does not exist', function () {
        $store = new TaxConfigStore;
        expect(fn () => $store->duplicate(99999, '2027/28', '2027-04-06', '2028-04-05', IngestSource::ADMIN))
            ->toThrow(StoreValidationException::class);
    });
});

describe('TaxConfigStore::delete', function () {
    it('deletes a row and emits ReferenceDataUpdated', function () {
        $store = new TaxConfigStore;
        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN, actorUserId: $this->actorId);

        $store->delete($id, IngestSource::ADMIN, actorUserId: $this->actorId);

        expect(TaxConfiguration::find($id))->toBeNull();
        Event::assertDispatched(
            ReferenceDataUpdated::class,
            fn ($e) => $e->entityKey === 'tax_configuration' && $e->entityId === $id && $e->changedKeys === ['__deleted']
        );
    });

    it('is idempotent for missing rows', function () {
        $store = new TaxConfigStore;

        expect(fn () => $store->delete(99999, IngestSource::ADMIN))->not->toThrow(Throwable::class);
    });
});

describe('TaxConfigStore::find / findByTaxYear / findEloquent / all', function () {
    it('find returns canonicalised array', function () {
        $store = new TaxConfigStore;
        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN);

        $row = $store->find($id);

        expect($row['tax_year'])->toBe('2026/27')
            ->and($row['effective_from'])->toBe('2026-04-06')
            ->and($row['effective_to'])->toBe('2027-04-05');
    });

    it('findByTaxYear locates by tax_year string', function () {
        $store = new TaxConfigStore;
        $store->create(validTaxConfigPayload(['tax_year' => '2026/27']), IngestSource::SEEDER);

        $row = $store->findByTaxYear('2026/27');
        expect($row)->not->toBeNull()
            ->and($row->tax_year)->toBe('2026/27');

        expect($store->findByTaxYear('1999/00'))->toBeNull();
    });

    it('findEloquent returns the model or null', function () {
        $store = new TaxConfigStore;
        $id = $store->create(validTaxConfigPayload(), IngestSource::ADMIN);

        expect($store->findEloquent($id))->toBeInstanceOf(TaxConfiguration::class);
        expect($store->findEloquent(99999))->toBeNull();
    });

    it('all returns rows ordered by effective_from desc', function () {
        $store = new TaxConfigStore;
        $store->create(validTaxConfigPayload(['tax_year' => '2025/26', 'effective_from' => '2025-04-06', 'effective_to' => '2026-04-05']), IngestSource::SEEDER);
        $store->create(validTaxConfigPayload(['tax_year' => '2026/27']), IngestSource::SEEDER);

        $rows = $store->all();
        expect($rows->first()->tax_year)->toBe('2026/27');
    });
});
