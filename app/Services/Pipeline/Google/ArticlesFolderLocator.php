<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Resolves the Google Drive folder ID for the "Articles" subfolder of
 * Marketing Automation. Cached for an hour. Same pattern as
 * VideosFolderLocator (Stage 3).
 */
class ArticlesFolderLocator
{
    private const CACHE_KEY = 'pipeline.google.drive.articles_folder_id';

    private const CACHE_TTL_SECONDS = 3600;

    private const SUBFOLDER_NAME = 'Articles';

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
                'Could not find an "Articles" subfolder under the Marketing Automation folder. '
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
