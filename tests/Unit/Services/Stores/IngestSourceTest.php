<?php

declare(strict_types=1);

use App\Services\Stores\IngestSource;

it('exposes the six canonical ingest source cases', function () {
    expect(IngestSource::cases())->toHaveCount(6);
    expect(IngestSource::FORM->value)->toBe('form');
    expect(IngestSource::FYN_AI->value)->toBe('fyn_ai');
    expect(IngestSource::UPLOAD->value)->toBe('upload');
    expect(IngestSource::SEEDER->value)->toBe('seeder');
    // F20: the quarterly MoneySavingExpert benchmark refresh writes reference data too.
    expect(IngestSource::SCRAPER->value)->toBe('scraper');
    expect(IngestSource::ADMIN->value)->toBe('admin');
});
