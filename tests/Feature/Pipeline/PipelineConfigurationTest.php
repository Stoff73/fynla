<?php

declare(strict_types=1);

use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\VideosFolderLocator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

afterEach(fn () => Mockery::close());

it('exposes an empty runner name until a deployment assigns one', function () {
    expect(config('pipeline.runner_name'))->toBe('');
});

it('fails before querying Drive when the root folder is missing', function () {
    Cache::forget('pipeline.google.drive.videos_folder_id');
    Config::set('pipeline.google.drive_folder_id', null);

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldNotReceive('findSubfolder');

    expect(fn () => (new VideosFolderLocator($drive))->resolve())
        ->toThrow(RuntimeException::class, 'PIPELINE_GOOGLE_DRIVE_FOLDER_ID is not set.');
});

it('queries Drive with the configured root folder', function () {
    Cache::forget('pipeline.google.drive.videos_folder_id');
    Config::set('pipeline.google.drive_folder_id', 'MARKETING_FOLDER');

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldReceive('findSubfolder')
        ->once()
        ->with('MARKETING_FOLDER', 'Videos')
        ->andReturn('VIDEOS_FOLDER');

    expect((new VideosFolderLocator($drive))->resolve())->toBe('VIDEOS_FOLDER');
});
