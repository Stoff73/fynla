<?php

declare(strict_types=1);

use App\Models\DataExport;
use App\Models\User;
use App\Services\GDPR\DataExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(DataExportService::class);
    $this->user = User::factory()->create();
});

describe('requestExport', function () {
    it('creates an export request', function () {
        $export = $this->service->requestExport($this->user);

        expect($export)->toBeInstanceOf(DataExport::class);
        expect($export->user_id)->toBe($this->user->id);
        expect($export->status)->toBe(DataExport::STATUS_PENDING);
    });

    it('returns existing pending export if one exists', function () {
        $first = $this->service->requestExport($this->user);
        $second = $this->service->requestExport($this->user);

        expect($first->id)->toBe($second->id);
    });

    it('creates new export if previous one is completed', function () {
        $first = $this->service->requestExport($this->user);
        $first->status = DataExport::STATUS_COMPLETED;
        $first->save();

        $second = $this->service->requestExport($this->user);

        expect($second->id)->not->toBe($first->id);
    });

    it('defaults to JSON format', function () {
        $export = $this->service->requestExport($this->user);

        expect($export->format)->toBe(DataExport::FORMAT_JSON);
    });

    it('accepts CSV format', function () {
        $export = $this->service->requestExport($this->user, DataExport::FORMAT_CSV);

        expect($export->format)->toBe(DataExport::FORMAT_CSV);
    });
});

describe('processExport', function () {
    it('processes a pending export', function () {
        $export = $this->service->requestExport($this->user);

        $this->service->processExport($export);

        $export->refresh();
        expect($export->status)->toBe(DataExport::STATUS_COMPLETED);
        expect($export->file_path)->not->toBeNull();
        expect($export->file_size)->toBeGreaterThan(0);
        expect($export->completed_at)->not->toBeNull();
    });

    it('creates JSON file for JSON format', function () {
        $export = $this->service->requestExport($this->user, DataExport::FORMAT_JSON);

        $this->service->processExport($export);

        $export->refresh();
        expect($export->file_path)->toEndWith('.json');
    });

    it('creates CSV file for CSV format', function () {
        $export = $this->service->requestExport($this->user, DataExport::FORMAT_CSV);

        $this->service->processExport($export);

        $export->refresh();
        expect($export->file_path)->toEndWith('.csv');
    });
});

describe('getExportFile', function () {
    it('returns null for non-completed export', function () {
        $export = $this->service->requestExport($this->user);

        expect($this->service->getExportFile($export))->toBeNull();
    });

    it('returns file path for completed export', function () {
        $export = $this->service->requestExport($this->user);
        $this->service->processExport($export);
        $export->refresh();

        $path = $this->service->getExportFile($export);

        expect($path)->not->toBeNull();
        expect($path)->toContain('exports');
    });

    it('marks export as downloaded', function () {
        $export = $this->service->requestExport($this->user);
        $this->service->processExport($export);
        $export->refresh();

        $this->service->getExportFile($export);

        $export->refresh();
        expect($export->downloaded_at)->not->toBeNull();
    });
});
