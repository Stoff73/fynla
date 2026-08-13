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

    public function __construct(private readonly GoogleServiceAccountClient $auth) {}

    /**
     * Apply the marketing tracker layout to an existing native Google Sheet.
     */
    public function initialiseTrackerSheet(string $spreadsheetId): void
    {
        $token = $this->auth->accessToken();

        $details = Http::withToken($token)
            ->timeout(30)
            ->get(self::API_ROOT.'/'.$spreadsheetId, [
                'fields' => 'sheets(properties(sheetId,title))',
            ]);

        if (! $details->successful()) {
            throw new RuntimeException('Sheets metadata lookup failed: HTTP '.$details->status());
        }

        $sheetId = $details->json('sheets.0.properties.sheetId');
        if (! is_int($sheetId)) {
            throw new RuntimeException('Google Sheets returned no worksheet ID.');
        }

        $this->applyFormatting($token, $spreadsheetId, $sheetId);
        $this->writeHeaders($token, $spreadsheetId);
    }

    /**
     * Append one row to the tracker sheet. Returns the appended row's 1-based
     * index (useful for later updates).
     *
     * @param  array<int,string|int|float|null>  $values  In HEADERS order.
     */
    public function appendRow(string $spreadsheetId, array $values): int
    {
        $token = $this->auth->accessToken();

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

    /**
     * Read spreadsheet metadata without changing its contents.
     *
     * @return array{spreadsheetId:string,title:string,sheets:list<array{id:int,title:string}>}
     */
    public function metadata(string $spreadsheetId): array
    {
        $response = Http::withToken($this->auth->accessToken())
            ->timeout(30)
            ->get(self::API_ROOT.'/'.$spreadsheetId, [
                'fields' => 'spreadsheetId,properties(title),sheets(properties(sheetId,title))',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets metadata failed: HTTP '.$response->status());
        }

        return [
            'spreadsheetId' => (string) $response->json('spreadsheetId'),
            'title' => (string) $response->json('properties.title'),
            'sheets' => array_map(static fn (array $sheet): array => [
                'id' => (int) data_get($sheet, 'properties.sheetId'),
                'title' => (string) data_get($sheet, 'properties.title'),
            ], $response->json('sheets', [])),
        ];
    }

    /**
     * Read the first row of a sheet without changing it.
     *
     * @return list<string>
     */
    public function firstRow(string $spreadsheetId, string $sheetTitle): array
    {
        $response = Http::withToken($this->auth->accessToken())
            ->timeout(30)
            ->get(self::API_ROOT.'/'.$spreadsheetId.'/values/'.rawurlencode($sheetTitle.'!1:1'));

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets header read failed: HTTP '.$response->status());
        }

        return array_map('strval', $response->json('values.0', []));
    }

    /**
     * Read the allowed values from the Status validation rule without changing the tracker.
     *
     * @return list<string>
     */
    public function statusOptions(string $spreadsheetId, string $sheetTitle): array
    {
        $response = Http::withToken($this->auth->accessToken())
            ->timeout(30)
            ->get(self::API_ROOT.'/'.$spreadsheetId, [
                'ranges' => $sheetTitle.'!E2',
                'includeGridData' => 'true',
                'fields' => 'sheets(data(rowData(values(dataValidation(condition(type,values))))))',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Google Sheets Status validation read failed: HTTP '.$response->status());
        }

        $condition = $response->json('sheets.0.data.0.rowData.0.values.0.dataValidation.condition', []);
        if (($condition['type'] ?? null) !== 'ONE_OF_LIST') {
            return [];
        }

        return array_values(array_map(
            static fn (array $value): string => (string) ($value['userEnteredValue'] ?? ''),
            $condition['values'] ?? [],
        ));
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
                'updateSheetProperties' => [
                    'properties' => [
                        'sheetId' => $sheetId,
                        'title' => 'Pipeline',
                        'gridProperties' => [
                            'frozenRowCount' => 1,
                            'columnCount' => count(self::HEADERS),
                        ],
                    ],
                    'fields' => 'title,gridProperties(frozenRowCount,columnCount)',
                ],
            ],
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
