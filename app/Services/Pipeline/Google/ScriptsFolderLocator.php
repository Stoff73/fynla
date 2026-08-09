<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Resolves the Google Drive folder ID for the "Scripts" subfolder of the
 * Marketing Automation folder, so generated scripts land there instead of
 * cluttering the root alongside Articles/Videos.
 *
 * Unlike Articles/Videos (which the human fills), Scripts is an OUTPUT folder,
 * so it is created automatically if missing. Cached for an hour.
 */
class ScriptsFolderLocator
{
    private const CACHE_KEY = 'pipeline.google.drive.scripts_folder_id';

    private const CACHE_TTL_SECONDS = 3600;

    private const SUBFOLDER_NAME = 'Scripts';

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

        $id = $this->drive->findOrCreateSubfolder($parent, self::SUBFOLDER_NAME);

        Cache::put(self::CACHE_KEY, $id, self::CACHE_TTL_SECONDS);

        return $id;
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
