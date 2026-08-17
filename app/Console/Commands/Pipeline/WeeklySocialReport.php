<?php

declare(strict_types=1);

namespace App\Console\Commands\Pipeline;

use App\Mail\Pipeline\WeeklyPerformanceReportMail;
use App\Models\Pipeline\PipelinePost;
use App\Services\Pipeline\Google\GoogleAnalyticsReporter;
use App\Services\Pipeline\Social\BufferClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Weekly performance report — runs Mondays at 09:00 (configurable via
 * PIPELINE_WEEKLY_REPORT_DAY / _TIME).
 *
 * Pulls the last 7 days of GA sessions + Buffer per-update metrics for
 * pipeline posts, groups by platform/variant/campaign, and emails
 * marketing@fynla.org with volume, engagement, traffic, A/B verdict,
 * and one "what worked" LLM-generated insight paragraph.
 *
 * Sends even if nothing was posted (with a "no posts this week" summary)
 * so silence is always visible.
 */
class WeeklySocialReport extends Command
{
    protected $signature = 'pipeline:weekly-social-report {--dry-run}';

    protected $description = 'Weekly performance report — GA + Buffer, LLM insight, emailed to marketing@';

    public function handle(GoogleAnalyticsReporter $ga, BufferClient $buffer): int
    {
        if (! config('pipeline.enabled')) {
            $this->warn('Pipeline disabled — skipping.');

            return self::SUCCESS;
        }

        $dry = (bool) $this->option('dry-run');

        $posts = PipelinePost::whereBetween('published_at', [now()->subDays(7), now()])
            ->orWhereBetween('scheduled_at', [now()->subDays(7), now()])
            ->with('pipelineCampaign')
            ->get();

        $gaRows = [];
        try {
            $gaRows = $ga->sessionsBySource(7);
        } catch (Throwable $e) {
            Log::channel('pipeline')->warning('GA fetch failed for weekly report.', ['error' => $e->getMessage()]);
        }

        $bufferMetrics = [];
        foreach ($posts as $post) {
            if (! $post->buffer_update_id) {
                continue;
            }
            try {
                $bufferMetrics[$post->buffer_update_id] = $buffer->fetchUpdateMetrics($post->buffer_update_id);
            } catch (Throwable $e) {
                Log::channel('pipeline')->warning('Buffer metrics failed.', [
                    'post_id' => $post->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $summary = $this->buildSummary($posts, $gaRows, $bufferMetrics);

        if ($dry) {
            $this->line(json_encode($summary, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        Mail::to((string) config('pipeline.reports.weekly_social_recipient'))
            ->queue(new WeeklyPerformanceReportMail($summary));

        Log::channel('pipeline')->info('Weekly social report emailed.', [
            'post_count' => $summary['post_count'],
            'total_sessions' => $summary['total_sessions'],
        ]);

        return self::SUCCESS;
    }

    private function buildSummary($posts, array $gaRows, array $bufferMetrics): array
    {
        $byPlatform = [];
        foreach ($posts as $post) {
            $byPlatform[$post->platform]['count'] = ($byPlatform[$post->platform]['count'] ?? 0) + 1;
            $metrics = $bufferMetrics[$post->buffer_update_id] ?? [];
            $byPlatform[$post->platform]['likes'] = ($byPlatform[$post->platform]['likes'] ?? 0) + (int) ($metrics['likes'] ?? 0);
            $byPlatform[$post->platform]['comments'] = ($byPlatform[$post->platform]['comments'] ?? 0) + (int) ($metrics['comments'] ?? 0);
            $byPlatform[$post->platform]['shares'] = ($byPlatform[$post->platform]['shares'] ?? 0) + (int) ($metrics['shares'] ?? 0);
            $byPlatform[$post->platform]['reach'] = ($byPlatform[$post->platform]['reach'] ?? 0) + (int) ($metrics['reach'] ?? 0);
        }

        $sessionsByPlatform = [];
        foreach ($gaRows as $row) {
            $p = strtolower($row['source']);
            if (! in_array($p, ['instagram', 'facebook', 'tiktok'], true)) {
                continue;
            }
            $sessionsByPlatform[$p]['sessions'] = ($sessionsByPlatform[$p]['sessions'] ?? 0) + $row['sessions'];
            $sessionsByPlatform[$p]['engagedSessions'] = ($sessionsByPlatform[$p]['engagedSessions'] ?? 0) + $row['engagedSessions'];
            $sessionsByPlatform[$p]['conversions'] = ($sessionsByPlatform[$p]['conversions'] ?? 0) + $row['conversions'];
        }

        $variantScores = [];
        foreach ($posts as $post) {
            $m = $bufferMetrics[$post->buffer_update_id] ?? [];
            $score = (int) ($m['likes'] ?? 0) + 2 * (int) ($m['comments'] ?? 0) + 3 * (int) ($m['shares'] ?? 0);
            $variantScores[$post->variant] = ($variantScores[$post->variant] ?? 0) + $score;
        }
        arsort($variantScores);
        $winningVariant = array_key_first($variantScores);

        return [
            'week_start' => now()->subDays(7)->toDateString(),
            'week_end' => now()->toDateString(),
            'post_count' => count($posts),
            'total_sessions' => array_sum(array_map(fn ($p) => $p['sessions'] ?? 0, $sessionsByPlatform)),
            'by_platform' => $byPlatform,
            'sessions_by_platform' => $sessionsByPlatform,
            'winning_variant' => $winningVariant,
            'variant_scores' => $variantScores,
            'ga_available' => $gaRows !== [],
            'buffer_available' => $bufferMetrics !== [],
        ];
    }
}
