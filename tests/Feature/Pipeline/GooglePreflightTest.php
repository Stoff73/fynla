<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();
    putenv('RANDFILE='.sys_get_temp_dir().'/fynla-openssl-random-state');

    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($key, $this->privateKey);
    $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fynla-google-preflight-');
    file_put_contents($this->credentialsPath, json_encode([
        'type' => 'service_account',
        'private_key_id' => 'preflight-test-key-id',
        'private_key' => $this->privateKey,
        'client_email' => 'pipeline@fynla-marketing-test.iam.gserviceaccount.com',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_THROW_ON_ERROR));

    Config::set('pipeline.enabled', true);
    Config::set('pipeline.runner_name', 'production-marketing-runner');
    Config::set('pipeline.google.service_account_credentials', $this->credentialsPath);
    Config::set('pipeline.google.drive_folder_id', 'ROOT_FOLDER');
    Config::set('pipeline.google.tracker_sheet_id', 'TRACKER_SHEET');
    Config::set('pipeline.notifications.script_ready_to', 'marketing@fynla.org');
    Config::set('pipeline.social.compose_after_render', true);
    Config::set('pipeline.social.dry_run', true);
    Config::set('pipeline.drive.webhook_url', null);
    Config::set('pipeline.drive.webhook_token', 'webhook-secret');
});

afterEach(function () {
    @unlink($this->credentialsPath);
});

function googlePreflightHttpFakes(string $trackerMimeType = 'application/vnd.google-apps.spreadsheet', array $folders = ['Articles', 'Scripts', 'Videos'], array $headers = [
    'Timestamp', 'Article slug', 'Article title', 'Script link', 'Status', 'Video link', 'Notes', 'Assignee',
]): void
{
    Http::fake(function (Request $request) use ($trackerMimeType, $folders, $headers) {
        $url = $request->url();

        if ($url === 'https://oauth2.googleapis.com/token') {
            return Http::response([
                'access_token' => 'preflight-service-account-access-token',
                'expires_in' => 3600,
            ]);
        }

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

it('reports a fully configured native tracker without leaking credentials or mutating Drive or Sheets', function () {
    googlePreflightHttpFakes();
    Log::spy();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('PASS Google service-account credentials are configured.')
        ->expectsOutputToContain('PASS Google service-account authentication is available.')
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

    Http::assertSentCount(6);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://oauth2.googleapis.com/token');
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
    Http::assertNotSent(fn (Request $request) => $request->url() !== 'https://oauth2.googleapis.com/token'
        && $request->method() !== 'GET');
    Log::shouldNotHaveReceived('error');
});

it('explains how to replace an Excel tracker without leaking secrets', function () {
    googlePreflightHttpFakes('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    Log::spy();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Tracker is an Excel workbook, not a native Google spreadsheet.')
        ->expectsOutputToContain('Archive the Excel workbook, then run `php artisan pipeline:setup-tracker` to create the required native spreadsheet.')
        ->doesntExpectOutputToContain('google-client-secret')
        ->doesntExpectOutputToContain($this->privateKey)
        ->doesntExpectOutputToContain('preflight-service-account-access-token')
        ->doesntExpectOutputToContain('webhook-secret')
        ->assertExitCode(1);

    Log::shouldNotHaveReceived('error');
});

it('fails with actionable non-secret configuration guidance before making Google requests', function (string $key, string $expected) {
    Config::set($key, null);
    Http::fake();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain($expected)
        ->assertExitCode(1);

    Http::assertNothingSent();
})->with([
    'service-account credentials path' => ['pipeline.google.service_account_credentials', 'FAIL Google service-account authentication failed. Check GOOGLE_SERVICE_ACCOUNT_CREDENTIALS and the service-account key.'],
    'root folder id' => ['pipeline.google.drive_folder_id', 'FAIL Marketing Automation root folder ID is not configured.'],
    'tracker id' => ['pipeline.google.tracker_sheet_id', 'FAIL Tracker spreadsheet ID is not configured.'],
]);

it('fails safely when the service-account credentials path is unreadable', function () {
    $unreadablePath = '/private/not-for-console-output/google-service-account.json';
    Config::set('pipeline.google.service_account_credentials', $unreadablePath);
    Http::fake();
    Log::spy();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Google service-account authentication failed. Check GOOGLE_SERVICE_ACCOUNT_CREDENTIALS and the service-account key.')
        ->doesntExpectOutputToContain($unreadablePath)
        ->assertExitCode(1);

    Http::assertNothingSent();
    Log::shouldNotHaveReceived('error');
});

it('fails safely when the service-account credentials file is malformed', function () {
    $credentialJson = '{"private_key":"do-not-print-this-private-key"}';
    file_put_contents($this->credentialsPath, $credentialJson);
    Http::fake();
    Log::spy();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Google service-account authentication failed. Check GOOGLE_SERVICE_ACCOUNT_CREDENTIALS and the service-account key.')
        ->doesntExpectOutputToContain('do-not-print-this-private-key')
        ->doesntExpectOutputToContain($credentialJson)
        ->assertExitCode(1);

    Http::assertNothingSent();
    Log::shouldNotHaveReceived('error');
});

it('fails safely when the service account cannot obtain an access token', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 401),
    ]);
    Log::spy();

    $this->artisan('pipeline:google-preflight')
        ->expectsOutputToContain('FAIL Google service-account authentication failed. Check GOOGLE_SERVICE_ACCOUNT_CREDENTIALS and the service-account key.')
        ->doesntExpectOutputToContain($this->privateKey)
        ->doesntExpectOutputToContain('invalid_grant')
        ->assertExitCode(1);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && $request->url() === 'https://oauth2.googleapis.com/token');
    Log::shouldNotHaveReceived('error');
});

it('fails when the configured root folder cannot be read', function () {
    Http::fake(function (Request $request) {
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response([
                'access_token' => 'preflight-service-account-access-token',
                'expires_in' => 3600,
            ]);
        }

        if (str_contains($request->url(), '/drive/v3/files/ROOT_FOLDER')) {
            return Http::response([], 404);
        }

        return Http::response([], 404);
    });

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
        if ($request->url() === 'https://oauth2.googleapis.com/token') {
            return Http::response([
                'access_token' => 'preflight-service-account-access-token',
                'expires_in' => 3600,
            ]);
        }

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
