<?php

declare(strict_types=1);

use App\Services\Stores\IngestSource;

it('exposes the five canonical ingest source cases', function () {
    expect(IngestSource::cases())->toHaveCount(5);
    expect(IngestSource::FORM->value)->toBe('form');
    expect(IngestSource::FYN_AI->value)->toBe('fyn_ai');
    expect(IngestSource::UPLOAD->value)->toBe('upload');
    expect(IngestSource::SEEDER->value)->toBe('seeder');
    expect(IngestSource::ADMIN->value)->toBe('admin');
});
