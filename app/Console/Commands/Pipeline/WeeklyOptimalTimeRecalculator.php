<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Services\Pipeline\Google\GoogleAnalyticsReporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Weekly (Monday 06:00): pulls GA4 traffic per platform × day × hour for
 * the configured lookback window, picks the top 3 (day, hour) slots per
 * platform, and writes them to cache as an override to the static
 * best_times config in config/pipeline.php.
 *
 * If GA data is sparse (< MIN_SESSIONS_PER_PLATFORM), the static
 * defaults are kept — safer than shipping data-underinformed slots.
 */
class WeeklyOptimalTimeRecalculator extends Command
{
    protected $signature = 'pipeline:recalculate-optimal-times {--dry-run}';

    protected $description = 'Weekly — analyse GA traffic and override best_times per platform';

    private const MIN_SESSIONS_PER_PLATFORM = 20;

    private const OVERRIDE_CACHE_KEY = 'pipeline.social.best_times_override';

    public function handle(GoogleAnalyticsReporter $ga): int
    {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline disabled — skipping.');

            return self::SUCCESS;
        }

        $lookback = (int) config('pipeline.google_analytics.default_lookback_days', 56);
        $dry = (bool) $this->option('dry-run');

        try {
            $peaks = $ga->peakSlotsByPlatform($lookback);
        } catch (Throwable $e) {
            $this->error('GA fetch failed: '.$e->getMessage());
            Log::channel('pipeline')->error('WeeklyOptimalTimeRecalculator: GA fetch failed.', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }

        if ($peaks === []) {
            $this->warn('GA returned no data — static defaults kept.');

            return self::SUCCESS;
        }

        $override = [];
        foreach ($peaks as $platform => $rows) {
            $totalSessions = array_sum(array_column($rows, 'sessions'));
            if ($totalSessions < self::MIN_SESSIONS_PER_PLATFORM) {
                $this->line("  · {$platform}: {$totalSessions} sessions — below threshold, keeping static defaults");

                continue;
            }

            usort($rows, fn ($a, $b) => $b['sessions'] <=> $a['sessions']);
            $override[$platform] = array_map(
                fn (array $slot) => [
                    'day' => $slot['day'],
                    'time' => sprintf('%02d:00', $slot['hour']),
                ],
                array_slice($rows, 0, 3),
            );

            $this->info("  ✓ {$platform}: {$totalSessions} sessions → 3 overridden slots");
            foreach ($override[$platform] as $slot) {
                $this->line("      {$slot['day']} at {$slot['time']}");
            }
        }

        if ($override === []) {
            $this->warn('No platform met the session threshold — no override written.');

            return self::SUCCESS;
        }

        if ($dry) {
            $this->info('Dry run — override not written.');

            return self::SUCCESS;
        }

        Cache::put(self::OVERRIDE_CACHE_KEY, $override, now()->addDays(10));

        Log::channel('pipeline')->info('WeeklyOptimalTimeRecalculator wrote override.', [
            'platforms' => array_keys($override),
        ]);

        return self::SUCCESS;
    }
}
