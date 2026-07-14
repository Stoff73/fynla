<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin wrapper around Google Sheets REST v4. Creates the marketing-pipeline
 * tracker (headers + dropdown + frozen top row) and appends one row per
 * generated script.
 */
class GoogleSheetsService
{
    private const API_ROOT = 'https://sheets.googleapis.com/v4/spreadsheets';

    /** @var list<string> */
    public const HEADERS = [
        'Timestamp',
        'Article slug',
        'Article title',
        'Script link',
        'Status',
        'Video link',
        'Notes',
        'Assignee',
    ];

    /** @var list<string> Options for the "Status" dropdown in column E. */
    public const STATUS_OPTIONS = [
        'Script Ready',
        'Video In Progress',
        'Video Ready',
        'Published',
        'Rejected',
    ];

    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    /**
     * Create a spreadsheet with the marketing pipeline tracker layout. Returns
     * the new spreadsheet's ID. Caller should move it into the target folder
     * using GoogleDriveService::moveToFolder().
     */
    public function createTrackerSheet(string $title): string
    {
        $token = $this->oauth->accessToken();

        $create = Http::withToken($token)
            ->timeout(30)
            ->post(self::API_ROOT, [
                'properties' => ['title' => $title],
                'sheets' => [[
                    'properties' => [
                        'title' => 'Pipeline',
                        'gridProperties' => [
                            'frozenRowCount' => 1,
                            'columnCount' => count(self::HEADERS),
                        ],
                    ],
                ]],
            ]);

        if (! $create->successful()) {
            Log::channel('pipeline')->error('Sheets create failed.', [
                'status' => $create->status(),
                'body' => $create->body(),
            ]);
            throw new RuntimeException('Google Sheets create failed: HTTP '.$create->status());
        }

        $spreadsheetId = $create->json('spreadsheetId');
        $sheetId = $create->json('sheets.0.properties.sheetId');

        $this->writeHeaders($token, $spreadsheetId);
        $this->applyFormatting($token, $spreadsheetId, (int) $sheetId);

        return $spreadsheetId;
    }

    /**
     * Append one row to the tracker sheet. Returns the appended row's 1-based
     * index (useful for later updates).
     *
     * @param  array<int,string|int|float|null>  $values  In HEADERS order.
     */
    public function appendRow(string $spreadsheetId, array $values): int
    {
        $token = $this->oauth->accessToken();

        $response = Http::withToken($token)
            ->timeout(30)
            ->post(self::API_ROOT.'/'.$spreadsheetId.'/values/Pipeline!A:H:append?'.http_build_query([
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ]), [
                'values' => [$values],
            ]);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Sheets append failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'spreadsheet_id' => $spreadsheetId,
            ]);
            throw new RuntimeException('Google Sheets append failed: HTTP '.$response->status());
        }

        $updatedRange = $response->json('updates.updatedRange', 'Pipeline!A2:H2');
        preg_match('/[A-Z]+(\d+):/', $updatedRange, $m);

        return (int) ($m[1] ?? 0);
    }

    private function writeHeaders(string $token, string $spreadsheetId): void
    {
        $response = Http::withToken($token)
            ->timeout(30)
            ->put(self::API_ROOT.'/'.$spreadsheetId.'/values/Pipeline!A1:H1?valueInputOption=RAW', [
                'values' => [self::HEADERS],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Sheets writeHeaders failed: HTTP '.$response->status());
        }
    }

    private function applyFormatting(string $token, string $spreadsheetId, int $sheetId): void
    {
        $requests = [
            [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => ['bold' => true],
                            'backgroundColor' => ['red' => 0.95, 'green' => 0.95, 'blue' => 0.95],
                        ],
                    ],
                    'fields' => 'userEnteredFormat(textFormat,backgroundColor)',
                ],
            ],
            [
                'setDataValidation' => [
                    'range' => [
                        'sheetId' => $sheetId,
                        'startRowIndex' => 1,
                        'endRowIndex' => 1000,
                        'startColumnIndex' => 4,
                        'endColumnIndex' => 5,
                    ],
                    'rule' => [
                        'condition' => [
                            'type' => 'ONE_OF_LIST',
                            'values' => array_map(fn ($v) => ['userEnteredValue' => $v], self::STATUS_OPTIONS),
                        ],
                        'showCustomUi' => true,
                        'strict' => false,
                    ],
                ],
            ],
        ];

        $response = Http::withToken($token)
            ->timeout(30)
            ->post(self::API_ROOT.'/'.$spreadsheetId.':batchUpdate', [
                'requests' => $requests,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Sheets applyFormatting failed: HTTP '.$response->status());
        }
    }
}
