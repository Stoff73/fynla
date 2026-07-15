<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Social;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Picks the next slot for a given platform out of the configured best_times.
 * Static defaults live in config('pipeline.social.best_times'); the weekly
 * GA-informed recalculator can override them by writing to the same shape
 * under cache key `pipeline.social.best_times_override`.
 */
class PostScheduler
{
    private const OVERRIDE_CACHE_KEY = 'pipeline.social.best_times_override';

    public function nextSlot(string $platform, CarbonImmutable $from = null): CarbonImmutable
    {
        $from = $from ?? CarbonImmutable::now('UTC');

        $slots = $this->slotsForPlatform($platform);
        if ($slots === []) {
            return $from->addHour();
        }

        $candidates = [];
        foreach (range(0, 13) as $daysAhead) {
            $day = $from->addDays($daysAhead);
            $dayName = $day->format('l');

            foreach ($slots as $slot) {
                if (($slot['day'] ?? null) !== $dayName) {
                    continue;
                }
                [$hh, $mm] = array_map('intval', explode(':', (string) $slot['time']));
                $candidate = $day->setTime($hh, $mm);
                if ($candidate->gt($from)) {
                    $candidates[] = $candidate;
                }
            }

            if ($candidates !== []) {
                sort($candidates);

                return $candidates[0];
            }
        }

        return $from->addDay();
    }

    /**
     * @return list<array{day:string,time:string}>
     */
    private function slotsForPlatform(string $platform): array
    {
        $override = Cache::get(self::OVERRIDE_CACHE_KEY);
        if (is_array($override) && isset($override[$platform])) {
            return (array) $override[$platform];
        }

        return (array) config('pipeline.social.best_times.'.$platform, []);
    }
}
