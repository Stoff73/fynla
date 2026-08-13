<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\GoogleOAuthClient;
use App\Services\Pipeline\Google\GoogleSheetsService;
use Illuminate\Console\Command;
use Throwable;

class GooglePreflight extends Command
{
    protected $signature = 'pipeline:google-preflight';

    protected $description = 'Check Google Drive and tracker readiness without making changes';

    /** @var list<string> */
    private const REQUIRED_FOLDERS = ['Articles', 'Scripts', 'Videos'];

    public function handle(GoogleOAuthClient $oauth, GoogleDriveService $drive, GoogleSheetsService $sheets): int
    {
        if (! $this->configured('pipeline.google.oauth_client_id')) {
            return $this->fail('Google OAuth client ID is not configured.');
        }
        if (! $this->configured('pipeline.google.oauth_client_secret')) {
            return $this->fail('Google OAuth client secret is not configured.');
        }
        $this->pass('Google client settings are configured.');

        if (! $this->configured('pipeline.google.drive_folder_id')) {
            return $this->fail('Marketing Automation root folder ID is not configured.');
        }
        if (! $this->configured('pipeline.google.tracker_sheet_id')) {
            return $this->fail('Tracker spreadsheet ID is not configured.');
        }

        try {
            $oauth->credential();
            $oauth->accessToken();
            $this->pass('Google connection is available.');
        } catch (Throwable) {
            return $this->fail('Google connection is missing. Run `php artisan pipeline:authorise-google` first.');
        }

        $runner = (string) config('pipeline.runner_name');
        $this->pass('Runner: '.($runner !== '' ? $runner : 'not named').'; pipeline is '.(config('pipeline.enabled') ? 'enabled' : 'disabled').'.');

        $rootId = (string) config('pipeline.google.drive_folder_id');
        try {
            $root = $drive->metadata($rootId);
            if ($root['mimeType'] !== 'application/vnd.google-apps.folder') {
                return $this->fail('Root folder is not a Google Drive folder.');
            }
            $this->pass('Root folder: '.$root['name'].' is accessible.');

            $children = $drive->listChildFolders($rootId);
            $names = array_flip(array_column($children, 'name'));
            foreach (self::REQUIRED_FOLDERS as $folder) {
                if (! isset($names[$folder])) {
                    return $this->fail('Required Drive folder is missing: '.$folder.'.');
                }
            }
            $this->pass('Required Drive folders found: '.implode(', ', self::REQUIRED_FOLDERS).'.');
        } catch (Throwable) {
            return $this->fail('Root folder could not be accessed. Check the configured ID and Google Drive permissions.');
        }

        $trackerId = (string) config('pipeline.google.tracker_sheet_id');
        try {
            $tracker = $drive->metadata($trackerId);
        } catch (Throwable) {
            return $this->fail('Tracker spreadsheet could not be accessed. Check the configured ID and Google Drive permissions.');
        }

        if ($tracker['mimeType'] !== 'application/vnd.google-apps.spreadsheet') {
            if ($tracker['mimeType'] === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                return $this->fail('Tracker is an Excel workbook, not a native Google spreadsheet.', 'Archive the Excel workbook, then run `php artisan pipeline:setup-tracker` to create the required native spreadsheet.');
            }

            return $this->fail('Tracker is not a native Google spreadsheet.');
        }
        $this->pass('Tracker: '.$tracker['name'].' is a native Google spreadsheet.');

        try {
            $metadata = $sheets->metadata($trackerId);
        } catch (Throwable) {
            return $this->fail('Tracker spreadsheet metadata could not be read.');
        }

        $sheetNames = array_column($metadata['sheets'], 'title');
        if (! in_array('Pipeline', $sheetNames, true)) {
            return $this->fail('Tracker sheet "Pipeline" is missing.');
        }
        $this->pass('Tracker sheet: Pipeline exists.');

        try {
            $headers = $sheets->firstRow($trackerId, 'Pipeline');
        } catch (Throwable) {
            return $this->fail('Tracker headers could not be read.');
        }
        if ($headers !== GoogleSheetsService::HEADERS) {
            return $this->fail('Tracker headers must be: '.implode(', ', GoogleSheetsService::HEADERS).'.');
        }
        $this->pass('Tracker headers are in the required order.');

        $recipient = (string) config('pipeline.notifications.script_ready_to');
        $this->safe('Notifications will go to '.($recipient !== '' ? $recipient : 'no configured address').'.');
        $this->safe('Social publishing '.(config('pipeline.social.compose_after_render') ? 'waits for rendered video.' : 'can start before rendered video.'));
        $this->safe('Social publishing dry-run is '.(config('pipeline.social.dry_run') ? 'enabled.' : 'disabled.'));
        $this->safe($this->configured('pipeline.drive.webhook_url') ? 'Drive webhook is configured.' : 'Drive webhook is not configured; polling remains available.');

        return self::SUCCESS;
    }

    private function configured(string $key): bool
    {
        $value = config($key);

        return is_string($value) && $value !== '';
    }

    private function pass(string $message): void
    {
        $this->line('PASS '.$message);
    }

    private function safe(string $message): void
    {
        $this->line('SAFE '.$message);
    }

    private function fail(string $message, ?string $guidance = null): int
    {
        $this->line('FAIL '.$message);
        if ($guidance !== null) {
            $this->line($guidance);
        }

        return self::FAILURE;
    }
}
