<?php

declare(strict_types=1);

namespace App\Services\Pipeline;

use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Suggests when an approved article should go live — the article equivalent of
 * PostScheduler for social posts.
 *
 * Slot source, in order of preference:
 *   1. Google Analytics engagement peaks (day + hour), cached by the weekly
 *      recalculator. Only used when there's enough data to be meaningful.
 *   2. The curated slots in config('pipeline.articles.publish_slots').
 *
 * Whichever is used, a slot is rejected if another article is already going out
 * within `min_gap_hours`, so releases stay spaced rather than bunching up.
 */
class ArticlePublishScheduler
{
    public const OVERRIDE_CACHE_KEY = 'pipeline.articles.publish_slots_override';

    /** Below this many engaged sessions a GA slot isn't trustworthy. */
    private const MIN_SESSIONS_FOR_CONFIDENCE = 20;

    /**
     * @return array{at:CarbonImmutable,reason:string,source:string,clashes_avoided:int}
     */
    public function recommend(?int $excludeArticleId = null, ?CarbonImmutable $from = null): array
    {
        $now = $from ?? CarbonImmutable::now();
        $earliest = $now->addMinutes((int) config('pipeline.articles.min_lead_minutes', 30));

        [$slots, $source] = $this->slots();
        $taken = $this->scheduledArticleTimes($excludeArticleId);
        $gapHours = (int) config('pipeline.articles.min_gap_hours', 24);

        $clashesAvoided = 0;

        // Walk the next fortnight looking for the first preferred slot that is
        // far enough from everything already scheduled.
        foreach (range(0, 13) as $daysAhead) {
            $day = $earliest->addDays($daysAhead);
            $dayName = $day->format('l');

            $candidates = [];
            foreach ($slots as $slot) {
                if (($slot['day'] ?? null) !== $dayName) {
                    continue;
                }
                [$hh, $mm] = array_pad(array_map('intval', explode(':', (string) $slot['time'])), 2, 0);
                $candidate = $day->setTime($hh, $mm);
                if ($candidate->gt($earliest)) {
                    $candidates[] = $candidate;
                }
            }

            sort($candidates);

            foreach ($candidates as $candidate) {
                if ($this->clashes($candidate, $taken, $gapHours)) {
                    $clashesAvoided++;

                    continue;
                }

                return [
                    'at' => $candidate,
                    'source' => $source,
                    'clashes_avoided' => $clashesAvoided,
                    'reason' => $this->reason($candidate, $source, $clashesAvoided),
                ];
            }
        }

        // Nothing clean in a fortnight — fall back to the earliest allowed time.
        return [
            'at' => $earliest,
            'source' => 'fallback',
            'clashes_avoided' => $clashesAvoided,
            'reason' => 'No free preferred slot in the next two weeks, so this is the earliest sensible time.',
        ];
    }

    /**
     * Preferred slots, GA-derived when available.
     *
     * @return array{0:list<array{day:string,time:string}>,1:string}
     */
    private function slots(): array
    {
        $override = Cache::get(self::OVERRIDE_CACHE_KEY);
        if (is_array($override) && $override !== []) {
            return [$override, 'analytics'];
        }

        return [(array) config('pipeline.articles.publish_slots', []), 'default'];
    }

    /**
     * Turn GA day/hour engagement rows into publish slots. Kept here so the
     * weekly recalculator and any future tooling share one interpretation.
     *
     * @param  list<array{day:string,hour:int,sessions:int}>  $gaSlots
     * @return list<array{day:string,time:string}>
     */
    public function slotsFromAnalytics(array $gaSlots, int $take = 4): array
    {
        $usable = array_values(array_filter(
            $gaSlots,
            fn ($s) => (int) ($s['sessions'] ?? 0) >= self::MIN_SESSIONS_FOR_CONFIDENCE
                && (string) ($s['day'] ?? '') !== ''
        ));

        usort($usable, fn ($a, $b) => (int) $b['sessions'] <=> (int) $a['sessions']);

        $slots = [];
        $seen = [];
        foreach ($usable as $s) {
            $day = (string) $s['day'];
            // One slot per day keeps releases spread across the week.
            if (isset($seen[$day])) {
                continue;
            }
            $seen[$day] = true;
            $slots[] = ['day' => $day, 'time' => sprintf('%02d:00', (int) $s['hour'])];

            if (count($slots) >= $take) {
                break;
            }
        }

        return $slots;
    }

    /**
     * Times other articles are already going live (published or scheduled).
     *
     * @return list<CarbonImmutable>
     */
    private function scheduledArticleTimes(?int $excludeArticleId): array
    {
        $horizonStart = CarbonImmutable::now()->subDays(7);

        $docs = DocumentArticle::query()
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $horizonStart)
            ->when($excludeArticleId !== null, fn ($q) => $q->where('id', '!=', $excludeArticleId))
            ->pluck('published_at');

        $insights = InsightArticle::query()
            ->whereNotNull('published_at')
            ->where('published_at', '>=', $horizonStart)
            ->pluck('published_at');

        return $docs->merge($insights)
            ->filter()
            ->map(fn ($t) => CarbonImmutable::parse($t))
            ->values()
            ->all();
    }

    /** @param  list<CarbonImmutable>  $taken */
    private function clashes(CarbonImmutable $candidate, array $taken, int $gapHours): bool
    {
        foreach ($taken as $t) {
            if (abs($candidate->diffInMinutes($t, false)) < $gapHours * 60) {
                return true;
            }
        }

        return false;
    }

    private function reason(CarbonImmutable $at, string $source, int $clashesAvoided): string
    {
        $when = $at->format('l j M \a\t H:i');

        $base = $source === 'analytics'
            ? "{$when} — one of your strongest reader-engagement windows in Google Analytics."
            : "{$when} — a preferred publishing slot.";

        if ($clashesAvoided > 0) {
            $base .= ' Earlier slots were skipped because another article publishes too close to them.';
        }

        return $base;
    }
}
