<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\GoogleServiceAccountClient;
use App\Services\Pipeline\Google\GoogleSheetsService;
use Illuminate\Console\Command;
use Throwable;

class GooglePreflight extends Command
{
    protected $signature = 'pipeline:google-preflight';

    protected $description = 'Check Google Drive and tracker readiness without making changes';

    /** @var list<string> */
    private const REQUIRED_FOLDERS = ['Articles', 'Scripts', 'Videos'];

    private const COMMISSIONING_RUNNER = 'csjones-development';

    public function handle(GoogleServiceAccountClient $auth, GoogleDriveService $drive, GoogleSheetsService $sheets): int
    {
        if (! $this->configured('pipeline.google.drive_folder_id')) {
            return $this->fail('Marketing Automation root folder ID is not configured.');
        }
        if (! $this->configured('pipeline.google.tracker_sheet_id')) {
            return $this->fail('Tracker spreadsheet ID is not configured.');
        }

        if (config('pipeline.runner_name') !== self::COMMISSIONING_RUNNER) {
            return $this->fail('Runner must be csjones-development for initial commissioning.');
        }
        if (config('pipeline.social.compose_after_render')) {
            return $this->fail('Automatic social composition after render must be disabled for commissioning.');
        }
        if (! config('pipeline.social.dry_run')) {
            return $this->fail('Social publishing dry-run must be enabled for commissioning.');
        }

        try {
            $auth->accessToken();
            $this->pass('Google service-account credentials are configured.');
            $this->pass('Google service-account authentication is available.');
        } catch (Throwable) {
            return $this->fail('Google service-account authentication failed. Check GOOGLE_SERVICE_ACCOUNT_CREDENTIALS and the service-account key.');
        }

        $this->pass('Runner: '.self::COMMISSIONING_RUNNER.'; pipeline is '.(config('pipeline.enabled') ? 'enabled' : 'disabled').'.');

        $rootId = (string) config('pipeline.google.drive_folder_id');
        try {
            $root = $drive->metadata($rootId);
            if ($root['mimeType'] !== 'application/vnd.google-apps.folder') {
                return $this->fail('Root folder is not a Google Drive folder.');
            }
            if ($root['driveId'] === null || $root['id'] !== $root['driveId'] || $root['parents'] !== []) {
                return $this->fail('Configured Marketing Automation folder must be the Shared Drive root, not an ordinary or nested folder.');
            }
            $this->pass('Shared Drive root: '.$root['name'].' is accessible.');

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

        try {
            $statusOptions = $sheets->statusOptions($trackerId, 'Pipeline');
        } catch (Throwable) {
            return $this->fail('Tracker Status dropdown could not be read.');
        }
        if ($statusOptions !== GoogleSheetsService::STATUS_OPTIONS) {
            return $this->fail('Tracker Status dropdown must contain: '.implode(', ', GoogleSheetsService::STATUS_OPTIONS).'.');
        }
        $this->pass('Tracker Status dropdown contains the supported options.');

        $recipient = (string) config('pipeline.notifications.script_ready_to');
        $this->safe('Notifications will go to '.($recipient !== '' ? $recipient : 'no configured address').'.');
        $this->safe('Automatic social composition after render is disabled.');
        $this->safe('Social publishing dry-run is enabled.');
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
