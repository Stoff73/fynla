<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Services\Pipeline\Google\GoogleDriveService;
use App\Services\Pipeline\Google\GoogleSheetsService;
use Illuminate\Console\Command;
use Throwable;

/**
 * One-off command that creates the tracker Google Sheet inside the Marketing
 * Automation folder. Prints the resulting spreadsheet ID for pasting into
 * PIPELINE_GOOGLE_TRACKER_SHEET_ID in .env.
 *
 * Idempotent: if PIPELINE_GOOGLE_TRACKER_SHEET_ID is already set, refuses to
 * run again unless --force is passed (avoids accidentally creating a
 * duplicate sheet with divergent state).
 */
class SetupTracker extends Command
{
    protected $signature = 'pipeline:setup-tracker
                            {--force : Create a new sheet even if one is already configured}
                            {--title= : Title for the sheet (default: "Fynla Marketing Pipeline Tracker")}';

    protected $description = 'Create the marketing pipeline tracker Google Sheet';

    public function handle(GoogleSheetsService $sheets, GoogleDriveService $drive): int
    {
        $existing = config('pipeline.google.tracker_sheet_id');
        if (is_string($existing) && $existing !== '' && ! $this->option('force')) {
            $this->warn('A tracker sheet is already configured:');
            $this->line('  Sheet ID: '.$existing);
            $this->line('  URL:      https://docs.google.com/spreadsheets/d/'.$existing.'/edit');
            $this->newLine();
            $this->line('Re-run with --force to create a new one.');

            return self::SUCCESS;
        }

        $folderId = config('pipeline.google.drive_folder_id');
        if (! is_string($folderId) || $folderId === '') {
            $this->error('PIPELINE_GOOGLE_DRIVE_FOLDER_ID is not set.');

            return self::FAILURE;
        }

        $title = (string) $this->option('title') ?: 'Fynla Marketing Pipeline Tracker';

        $this->info('Creating tracker sheet...');

        try {
            $spreadsheetId = $sheets->createTrackerSheet($title);
        } catch (Throwable $e) {
            $this->error('Sheet creation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('  Created sheet '.$spreadsheetId.'.');

        $this->info('Moving into the Marketing Automation folder...');

        try {
            $drive->moveToFolder($spreadsheetId, $folderId);
        } catch (Throwable $e) {
            $this->error('Move failed (sheet was created but is still in Drive root): '.$e->getMessage());
            $this->line('  Sheet ID: '.$spreadsheetId);

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✓ Tracker ready.');
        $this->line('  URL:      https://docs.google.com/spreadsheets/d/'.$spreadsheetId.'/edit');
        $this->newLine();
        $this->line('  Paste this into .env then run `php artisan config:clear`:');
        $this->newLine();
        $this->line('    PIPELINE_GOOGLE_TRACKER_SHEET_ID='.$spreadsheetId);

        return self::SUCCESS;
    }
}
