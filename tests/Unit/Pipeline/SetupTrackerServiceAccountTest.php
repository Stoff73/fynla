<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Cache::flush();
    putenv('RANDFILE='.sys_get_temp_dir().'/fynla-openssl-random-state');

    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);
    openssl_pkey_export($key, $privateKey);
    $this->credentialsPath = tempnam(sys_get_temp_dir(), 'fynla-tracker-google-');
    file_put_contents($this->credentialsPath, json_encode([
        'type' => 'service_account',
        'project_id' => 'fynla-marketing-test',
        'private_key_id' => 'test-key-id',
        'private_key' => $privateKey,
        'client_email' => 'pipeline@fynla-marketing-test.iam.gserviceaccount.com',
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_THROW_ON_ERROR));

    Config::set('pipeline.google.service_account_credentials', $this->credentialsPath);
    Config::set('pipeline.google.drive_folder_id', 'SHARED_DRIVE_FOLDER_ID');
    Config::set('pipeline.google.tracker_sheet_id');
});

afterEach(function () {
    @unlink($this->credentialsPath);
});

it('creates and initialises the native tracker directly inside the shared folder', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'service-account-access-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        'www.googleapis.com/drive/v3/files*' => Http::response([
            'id' => 'NATIVE_TRACKER_ID',
            'name' => 'Fynla Marketing Pipeline Tracker',
            'webViewLink' => 'https://docs.google.com/spreadsheets/d/NATIVE_TRACKER_ID/edit',
        ]),
        'sheets.googleapis.com/v4/spreadsheets/NATIVE_TRACKER_ID?fields=*' => Http::response([
            'sheets' => [[
                'properties' => ['sheetId' => 2468, 'title' => 'Sheet1'],
            ]],
        ]),
        'sheets.googleapis.com/v4/spreadsheets/NATIVE_TRACKER_ID:batchUpdate' => Http::response([]),
        'sheets.googleapis.com/v4/spreadsheets/NATIVE_TRACKER_ID/values/Pipeline!A1:H1*' => Http::response([
            'updatedRange' => 'Pipeline!A1:H1',
        ]),
        '*' => Http::response(['error' => 'unexpected request'], 500),
    ]);

    $exitCode = Artisan::call('pipeline:setup-tracker');

    expect($exitCode)->toBe(0)
        ->and(Artisan::output())->toContain('NATIVE_TRACKER_ID');

    Http::assertSent(function (Request $request): bool {
        return str_starts_with($request->url(), 'https://www.googleapis.com/drive/v3/files?')
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer service-account-access-token')
            && $request->data() === [
                'name' => 'Fynla Marketing Pipeline Tracker',
                'mimeType' => 'application/vnd.google-apps.spreadsheet',
                'parents' => ['SHARED_DRIVE_FOLDER_ID'],
            ];
    });

    Http::assertSent(function (Request $request): bool {
        if ($request->url() !== 'https://sheets.googleapis.com/v4/spreadsheets/NATIVE_TRACKER_ID:batchUpdate') {
            return false;
        }

        $requests = $request->data()['requests'] ?? [];

        return count($requests) === 3
            && $requests[0]['updateSheetProperties']['properties'] === [
                'sheetId' => 2468,
                'title' => 'Pipeline',
                'gridProperties' => [
                    'frozenRowCount' => 1,
                    'columnCount' => 8,
                ],
            ]
            && $requests[1]['repeatCell']['cell']['userEnteredFormat']['textFormat'] === ['bold' => true]
            && $requests[2]['setDataValidation']['rule']['condition'] === [
                'type' => 'ONE_OF_LIST',
                'values' => array_map(fn (string $status): array => ['userEnteredValue' => $status], [
                    'Script Ready',
                    'Video In Progress',
                    'Video Ready',
                    'Published',
                    'Rejected',
                ]),
            ];
    });

    Http::assertSent(function (Request $request): bool {
        return str_contains($request->url(), '/spreadsheets/NATIVE_TRACKER_ID/values/Pipeline!A1:H1')
            && $request->method() === 'PUT'
            && $request->data() === [
                'values' => [[
                    'Timestamp',
                    'Article slug',
                    'Article title',
                    'Script link',
                    'Status',
                    'Video link',
                    'Notes',
                    'Assignee',
                ]],
            ];
    });

    $sheetWriteOrder = Http::recorded()
        ->map(fn (array $pair): Request => $pair[0])
        ->filter(fn (Request $request): bool => str_contains($request->url(), 'sheets.googleapis.com'))
        ->map(fn (Request $request): string => $request->method().' '.$request->url())
        ->values()
        ->all();

    expect($sheetWriteOrder[1])->toBe('POST https://sheets.googleapis.com/v4/spreadsheets/NATIVE_TRACKER_ID:batchUpdate')
        ->and($sheetWriteOrder[2])->toStartWith('PUT https://sheets.googleapis.com/v4/spreadsheets/NATIVE_TRACKER_ID/values/Pipeline!A1:H1');
});
