<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Resolves the Google Drive folder ID for the "Videos" subfolder of the
 * Marketing Automation folder. Cached for an hour to avoid a Drive round-trip
 * on every video-processing job.
 *
 * The Marketing Automation folder ID is configured via
 * PIPELINE_GOOGLE_DRIVE_FOLDER_ID. The "Videos" subfolder is discovered by
 * name — the human creates it once in Drive and this class finds it.
 */
class VideosFolderLocator
{
    private const CACHE_KEY = 'pipeline.google.drive.videos_folder_id';

    private const CACHE_TTL_SECONDS = 3600;

    private const SUBFOLDER_NAME = 'Videos';

    public function __construct(private readonly GoogleDriveService $drive) {}

    public function resolve(): string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $parent = (string) config('pipeline.google.drive_folder_id');
        if ($parent === '') {
            throw new RuntimeException('PIPELINE_GOOGLE_DRIVE_FOLDER_ID is not set.');
        }

        $id = $this->drive->findSubfolder($parent, self::SUBFOLDER_NAME);
        if ($id === null) {
            throw new RuntimeException(
                'Could not find a "Videos" subfolder under the Marketing Automation folder. '
                .'Create it in Google Drive and re-run.'
            );
        }

        Cache::put(self::CACHE_KEY, $id, self::CACHE_TTL_SECONDS);

        return $id;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
