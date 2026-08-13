<?php

declare(strict_types=1);

use App\Services\Pipeline\Google\ArticlesFolderLocator;
use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\ScriptsFolderLocator;
use App\Services\Pipeline\Google\VideosFolderLocator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

afterEach(fn () => Mockery::close());

it('exposes an empty runner name until a deployment assigns one', function () {
    expect(config('pipeline.runner_name'))->toBe('');
});

it('keeps the real root-folder configuration unset and rejects a cached folder', function () {
    expect(config('pipeline.google.drive_folder_id'))->toBeNull();

    Cache::put('pipeline.google.drive.videos_folder_id', 'STALE_VIDEOS_FOLDER', 3600);

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldNotReceive('findSubfolder');

    expect(fn () => (new VideosFolderLocator($drive))->resolve())
        ->toThrow(RuntimeException::class, 'PIPELINE_GOOGLE_DRIVE_FOLDER_ID is not set.');
});

it('rejects cached child folders when the root folder is missing', function (string $locator, string $cacheKey, string $driveMethod) {
    Cache::forget('pipeline.google.drive.videos_folder_id');
    Config::set('pipeline.google.drive_folder_id', null);
    Cache::put($cacheKey, 'STALE_CHILD_FOLDER', 3600);

    $drive = Mockery::mock(GoogleDriveService::class);
    $drive->shouldNotReceive($driveMethod);

    expect(fn () => (new $locator($drive))->resolve())
        ->toThrow(RuntimeException::class, 'PIPELINE_GOOGLE_DRIVE_FOLDER_ID is not set.');
})->with([
    'articles' => [ArticlesFolderLocator::class, 'pipeline.google.drive.articles_folder_id', 'findSubfolder'],
    'scripts' => [ScriptsFolderLocator::class, 'pipeline.google.drive.scripts_folder_id', 'findOrCreateSubfolder'],
    'videos' => [VideosFolderLocator::class, 'pipeline.google.drive.videos_folder_id', 'findSubfolder'],
]);

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
