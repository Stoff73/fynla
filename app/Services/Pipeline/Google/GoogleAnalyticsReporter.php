<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Google;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reads GA4 traffic for the pipeline's utm_source tags (instagram,
 * facebook, tiktok). Used by:
 *   - WeeklyOptimalTimeRecalculator: to find peak engagement windows and
 *     override the static best_times config.
 *   - WeeklySocialReport: to report sessions/engagement/conversions per
 *     platform to marketing@fynla.org.
 *
 * Uses the existing GoogleOAuthClient (analytics.readonly scope was
 * added; users must re-run pipeline:authorise-google to re-consent).
 * Direct HTTPS via Illuminate\Http — no google/apiclient dep.
 */
class GoogleAnalyticsReporter
{
    private const API_ROOT = 'https://analyticsdata.googleapis.com/v1beta';

    public function __construct(private readonly GoogleOAuthClient $oauth) {}

    /**
     * Aggregate metrics per pipeline utm_source over the given window.
     *
     * @return list<array{
     *   source:string,
     *   medium:string,
     *   campaign:string,
     *   content:string,
     *   sessions:int,
     *   engagedSessions:int,
     *   userEngagementDuration:float,
     *   conversions:int
     * }>
     */
    public function sessionsBySource(int $lookbackDays = 7): array
    {
        $data = $this->runReport([
            'dateRanges' => [[
                'startDate' => $lookbackDays.'daysAgo',
                'endDate' => 'yesterday',
            ]],
            'dimensions' => [
                ['name' => 'sessionSource'],
                ['name' => 'sessionMedium'],
                ['name' => 'sessionCampaignName'],
                ['name' => 'sessionManualAdContent'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'engagedSessions'],
                ['name' => 'userEngagementDuration'],
                ['name' => 'conversions'],
            ],
            'dimensionFilter' => $this->platformFilter(),
            'orderBys' => [
                ['metric' => ['metricName' => 'sessions'], 'desc' => true],
            ],
            'limit' => 500,
        ]);

        $rows = [];
        foreach ($data['rows'] ?? [] as $row) {
            $dims = array_column($row['dimensionValues'] ?? [], 'value');
            $mets = array_column($row['metricValues'] ?? [], 'value');

            $rows[] = [
                'source' => $dims[0] ?? '',
                'medium' => $dims[1] ?? '',
                'campaign' => $dims[2] ?? '',
                'content' => $dims[3] ?? '',
                'sessions' => (int) ($mets[0] ?? 0),
                'engagedSessions' => (int) ($mets[1] ?? 0),
                'userEngagementDuration' => (float) ($mets[2] ?? 0),
                'conversions' => (int) ($mets[3] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Peak engagement hour per platform. Used by the weekly optimal-time
     * recalculator to build a fresh `best_times` override.
     *
     * @return array<string,list<array{hour:int,day:string,sessions:int}>>
     */
    public function peakSlotsByPlatform(int $lookbackDays): array
    {
        $data = $this->runReport([
            'dateRanges' => [[
                'startDate' => $lookbackDays.'daysAgo',
                'endDate' => 'yesterday',
            ]],
            'dimensions' => [
                ['name' => 'sessionSource'],
                ['name' => 'dayOfWeekName'],
                ['name' => 'hour'],
            ],
            'metrics' => [
                ['name' => 'sessions'],
                ['name' => 'engagedSessions'],
            ],
            'dimensionFilter' => $this->platformFilter(),
            'orderBys' => [
                ['metric' => ['metricName' => 'engagedSessions'], 'desc' => true],
            ],
            'limit' => 2000,
        ]);

        $grouped = [];
        foreach ($data['rows'] ?? [] as $row) {
            $dims = array_column($row['dimensionValues'] ?? [], 'value');
            $mets = array_column($row['metricValues'] ?? [], 'value');

            $platform = strtolower((string) ($dims[0] ?? ''));
            if (! in_array($platform, ['instagram', 'facebook', 'tiktok'], true)) {
                continue;
            }

            $grouped[$platform][] = [
                'day' => (string) ($dims[1] ?? ''),
                'hour' => (int) ($dims[2] ?? 0),
                'sessions' => (int) ($mets[1] ?? 0),
            ];
        }

        return $grouped;
    }

    private function runReport(array $payload): array
    {
        $propertyId = $this->propertyId();
        $token = $this->oauth->accessToken();

        $response = Http::withToken($token)
            ->timeout(60)
            ->retry(2, 1000)
            ->post(self::API_ROOT.'/properties/'.$propertyId.':runReport', $payload);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('GA4 runReport failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
                'property_id' => $propertyId,
            ]);
            throw new RuntimeException('GA4 report failed: HTTP '.$response->status().' — '.$response->body());
        }

        return $response->json();
    }

    private function platformFilter(): array
    {
        return [
            'filter' => [
                'fieldName' => 'sessionSource',
                'inListFilter' => [
                    'values' => ['instagram', 'facebook', 'tiktok'],
                    'caseSensitive' => false,
                ],
            ],
        ];
    }

    private function propertyId(): string
    {
        $id = (string) config('pipeline.google_analytics.property_id', '');
        if ($id === '') {
            throw new RuntimeException('PIPELINE_GA_PROPERTY_ID is not set.');
        }

        return $id;
    }
}
