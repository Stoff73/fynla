<?php

declare(strict_types=1);

use App\Models\Pipeline\OAuthCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('pipeline.enabled', true);
    Config::set('pipeline.runner_name', 'production-marketing-runner');
    Config::set('pipeline.google.oauth_client_id', 'google-client-id');
    Config::set('pipeline.google.oauth_client_secret', 'google-client-secret');
    Config::set('pipeline.google.oauth_redirect_uri', 'https://app.fynla.org/pipeline/oauth/google/callback');
    Config::set('pipeline.google.drive_folder_id', 'ROOT_FOLDER');
    Config::set('pipeline.google.tracker_sheet_id', 'TRACKER_SHEET');
    Config::set('pipeline.notifications.script_ready_to', 'marketing@fynla.org');
    Config::set('pipeline.social.compose_after_render', true);
    Config::set('pipeline.social.dry_run', true);
    Config::set('pipeline.drive.webhook_url', null);
    Config::set('pipeline.drive.webhook_token', 'webhook-secret');

    OAuthCredential::create([
        'provider' => 'google',
        'account_email' => 'marketing@fynla.org',
        'access_token' => 'stored-access-token',
        'refresh_token' => 'stored-refresh-token',
        'expires_at' => now()->addHour(),
        'scopes' => ['https://www.googleapis.com/auth/drive'],
    ]);
});

function googlePreflightHttpFakes(string $trackerMimeType = 'application/vnd.google-apps.spreadsheet', array $folders = ['Articles', 'Scripts', 'Videos'], array $headers = [
    'Timestamp', 'Article slug', 'Article title', 'Script link', 'Status', 'Video link', 'Notes', 'Assignee',
]): void
{
    Http::fake(function (Request $request) use ($trackerMimeType, $folders, $headers) {
        $url = $request->url();

        if (str_contains($url, '/drive/v3/files/ROOT_FOLDER')) {
            return Http::response([
                'id' => 'ROOT_FOLDER',
                'name' => 'Marketing Automation',
                'mimeType' => 'application/vnd.google-apps.folder',
            ]);
        }

        if (str_contains($url, '/drive/v3/files/TRACKER_SHEET')) {
            return Http::response([
                'id' => 'TRACKER_SHEET',
                'name' => 'Fynla Marketing Pipeline Tracker',
                'mimeType' => $trackerMimeType,
            ]);
        }

        if (str_contains($url, '/drive/v3/files?')) {
            return Http::response([
                'files' => array_map(fn (string $name) => ['id' => strtoupper($name).'_FOLDER', 'name' => $name], $folders),
            ]);
        }

        if (str_contains($url, '/v4/spreadsheets/TRACKER_SHEET/values/')) {
            return Http::response(['values' => [$headers]]);
        }

        if (str_contains($url, '/v4/spreadsheets/TRACKER_SHEET')) {
            return Http::response([
                'spreadsheetId' => 'TRACKER_SHEET',
                'properties' => ['title' => 'Fynla Marketing Pipeline Tracker'],
                'sheets' => [['properties' => ['sheetId' => 0, 'title' => 'Pipeline']]],
            ]);
        }

        return Http::response([], 404);
    });
}

it('reports a fully configured native tracker without leaking credentials or mutating Google', function () {
    googlePreflightHttpFakes();
    Log::spy();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('PASS Google client settings are configured.')
        ->expectsOutputToContain('PASS Google connection is available.')
        ->expectsOutputToContain('PASS Runner: production-marketing-runner; pipeline is enabled.')
        ->expectsOutputToContain('PASS Root folder: Marketing Automation is accessible.')
        ->expectsOutputToContain('PASS Required Drive folders found: Articles, Scripts, Videos.')
        ->expectsOutputToContain('PASS Tracker: Fynla Marketing Pipeline Tracker is a native Google spreadsheet.')
        ->expectsOutputToContain('PASS Tracker sheet: Pipeline exists.')
        ->expectsOutputToContain('PASS Tracker headers are in the required order.')
        ->expectsOutputToContain('SAFE Notifications will go to marketing@fynla.org.')
        ->expectsOutputToContain('SAFE Social publishing waits for rendered video.')
        ->expectsOutputToContain('SAFE Social publishing dry-run is enabled.')
        ->expectsOutputToContain('SAFE Drive webhook is not configured; polling remains available.')
        ->assertExitCode(0);

    Http::assertSentCount(5);
    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/drive/v3/files/ROOT_FOLDER')
            && str_contains($request->url(), 'supportsAllDrives=true');
    });
    Http::assertSent(function (Request $request) {
        return $request->method() === 'GET'
            && str_contains($request->url(), '/drive/v3/files?')
            && str_contains($request->url(), 'supportsAllDrives=true')
            && str_contains($request->url(), 'includeItemsFromAllDrives=true');
    });
    Http::assertNotSent(fn (Request $request) => $request->method() !== 'GET');
    Log::shouldNotHaveReceived('error');
});

it('explains how to replace an Excel tracker without leaking secrets', function () {
    googlePreflightHttpFakes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    Log::spy();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Tracker is an Excel workbook, not a native Google spreadsheet.')
        ->expectsOutputToContain('Archive the Excel workbook, then run `php artisan pipeline:setup-tracker` to create the required native spreadsheet.')
        ->doesntExpectOutputToContain('google-client-secret')
        ->doesntExpectOutputToContain('stored-access-token')
        ->doesntExpectOutputToContain('stored-refresh-token')
        ->doesntExpectOutputToContain('webhook-secret')
        ->assertExitCode(1);

    Log::shouldNotHaveReceived('error');
});

it('fails with actionable configuration guidance before making Google requests', function (string $key, string $expected) {
    Config::set($key, null);
    Http::fake();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain($expected)
        ->assertExitCode(1);

    Http::assertNothingSent();
})->with([
    'client id' => ['pipeline.google.oauth_client_id', 'FAIL Google OAuth client ID is not configured.'],
    'client secret' => ['pipeline.google.oauth_client_secret', 'FAIL Google OAuth client secret is not configured.'],
    'root folder id' => ['pipeline.google.drive_folder_id', 'FAIL Marketing Automation root folder ID is not configured.'],
    'tracker id' => ['pipeline.google.tracker_sheet_id', 'FAIL Tracker spreadsheet ID is not configured.'],
]);

it('fails with authorisation guidance when no encrypted Google connection exists', function () {
    OAuthCredential::query()->delete();
    Http::fake();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Google connection is missing. Run `php artisan pipeline:authorise-google` first.')
        ->assertExitCode(1);

    Http::assertNothingSent();
});

it('fails when the configured root folder cannot be read', function () {
    Http::fake([
        'googleapis.com/drive/v3/files/ROOT_FOLDER*' => Http::response([], 404),
    ]);

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Root folder could not be accessed.')
        ->assertExitCode(1);
});

it('fails when a required Drive child folder is absent', function () {
    googlePreflightHttpFakes(folders: ['Articles', 'Videos']);

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Required Drive folder is missing: Scripts.')
        ->assertExitCode(1);
});

it('fails when the Pipeline sheet or its required header order is absent', function (array $sheets, array $headers, string $expected) {
    Http::fake(function (Request $request) use ($sheets, $headers) {
        if (str_contains($request->url(), '/drive/v3/files/ROOT_FOLDER')) {
            return Http::response(['id' => 'ROOT_FOLDER', 'name' => 'Marketing Automation', 'mimeType' => 'application/vnd.google-apps.folder']);
        }
        if (str_contains($request->url(), '/drive/v3/files/TRACKER_SHEET')) {
            return Http::response(['id' => 'TRACKER_SHEET', 'name' => 'Tracker', 'mimeType' => 'application/vnd.google-apps.spreadsheet']);
        }
        if (str_contains($request->url(), '/drive/v3/files?')) {
            return Http::response(['files' => [
                ['id' => 'ARTICLES', 'name' => 'Articles'], ['id' => 'SCRIPTS', 'name' => 'Scripts'], ['id' => 'VIDEOS', 'name' => 'Videos'],
            ]]);
        }
        if (str_contains($request->url(), '/values/')) {
            return Http::response(['values' => [$headers]]);
        }

        return Http::response(['spreadsheetId' => 'TRACKER_SHEET', 'sheets' => $sheets]);
    });

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain($expected)
        ->assertExitCode(1);
})->with([
    'missing sheet' => [[], [], 'FAIL Tracker sheet "Pipeline" is missing.'],
    'wrong header order' => [[['properties' => ['sheetId' => 0, 'title' => 'Pipeline']]], ['Article slug', 'Timestamp', 'Article title', 'Script link', 'Status', 'Video link', 'Notes', 'Assignee'], 'FAIL Tracker headers must be: Timestamp, Article slug, Article title, Script link, Status, Video link, Notes, Assignee.'],
]);
