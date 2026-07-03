<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\PointAward;
use App\Models\RecommendationTracking;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * WP-3 — the user-facing activity history. The point_awards ledger is
 * already a complete append-only record of everything the user has done
 * (onboarding answers, records added, actions completed, milestones,
 * check-ins); it was only ever used for dedup + level sums and never shown.
 * This service translates dedup keys into plain-English event labels.
 *
 * Rule #12: raw point VALUES are never exposed — the feed is events and
 * dates only.
 */
final class ActivityFeedService
{
    private const CATEGORY_LABELS = [
        'protection_policy' => 'protection policy',
        'savings_account' => 'savings account',
        'investment_account' => 'investment account',
        'pension' => 'pension',
        'estate' => 'estate record',
        'goal' => 'goal',
        'property' => 'property',
        'expenditure' => 'spending details',
        'income' => 'income details',
    ];

    private const ONBOARDING_STEP_LABELS = [
        'base_work' => 'Told us about your income',
        'base_employment' => 'Told us about your work',
        'base_employment_more' => 'Confirmed your income sources',
        'base_expenditure' => 'Told us about your monthly spending',
        'base_personal' => 'Shared your personal details',
        'campaign_dob' => 'Shared your date of birth',
        'campaign_isa_holdings' => 'Told us about your ISAs',
        'campaign_bank_accounts' => 'Told us about your bank accounts',
        'campaign_investment_accounts' => 'Told us about your investments',
        'campaign_occupational_scheme' => 'Told us about your workplace pension',
        'campaign_pension_contribs' => 'Told us about your pension contributions',
        'campaign_charitable_giving' => 'Told us about your charitable giving',
        'campaign_terminal' => 'Completed your tax profile',
    ];

    /**
     * Newest-first activity events.
     *
     * @return list<array{kind: string, label: string, occurred_at: string|null}>
     */
    public function feed(User $user, int $limit = 100): array
    {
        $awards = PointAward::where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        // One query for the completed-action texts referenced by
        // recommendation:* awards, so those events carry the real wording.
        $recIds = $awards
            ->filter(fn (PointAward $a): bool => str_starts_with($a->dedup_key, 'recommendation:'))
            ->map(fn (PointAward $a): string => substr($a->dedup_key, strlen('recommendation:')))
            ->all();
        $recTexts = $recIds === [] ? collect() : RecommendationTracking::where('user_id', $user->id)
            ->whereIn('recommendation_id', $recIds)
            ->pluck('recommendation_text', 'recommendation_id');

        return $awards
            ->map(function (PointAward $award) use ($recTexts): ?array {
                $event = $this->describe($award, $recTexts);
                if ($event === null) {
                    return null;
                }
                $event['occurred_at'] = $award->created_at?->toIso8601String();

                return $event;
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  Collection<string, string>  $recTexts
     * @return array{kind: string, label: string}|null
     */
    private function describe(PointAward $award, $recTexts): ?array
    {
        $key = $award->dedup_key;

        if (str_starts_with($key, 'onboarding:')) {
            $step = substr($key, strlen('onboarding:'));
            if (str_starts_with($step, 'inline:')) {
                return ['kind' => 'onboarding', 'label' => 'Added details in a chat with Fyn'];
            }
            // Verify/confirm steps are flow plumbing, not user achievements —
            // skip them so the feed reads as things the user actually did.
            if (str_contains($step, 'verify')) {
                return null;
            }

            return [
                'kind' => 'onboarding',
                'label' => self::ONBOARDING_STEP_LABELS[$step] ?? 'Answered an onboarding question',
            ];
        }

        if (preg_match('/^data:([a-z_]+):first$/', $key, $m) === 1) {
            $label = self::CATEGORY_LABELS[$m[1]] ?? str_replace('_', ' ', $m[1]);

            return ['kind' => 'data', 'label' => 'Added your first '.$label];
        }

        if (preg_match('/^data:([a-z_]+):rec:/', $key, $m) === 1) {
            $label = self::CATEGORY_LABELS[$m[1]] ?? str_replace('_', ' ', $m[1]);

            return ['kind' => 'data', 'label' => 'Added a '.$label];
        }

        if (str_starts_with($key, 'recommendation:')) {
            $recId = substr($key, strlen('recommendation:'));
            $text = trim((string) $recTexts->get($recId, ''));

            return [
                'kind' => 'action',
                'label' => $text !== '' ? 'Completed: '.$text : 'Completed an action',
            ];
        }

        if (preg_match('/^milestone:net_worth:\d+:(\d+)$/', $key, $m) === 1) {
            return ['kind' => 'milestone', 'label' => 'Net worth passed £'.number_format((int) $m[1])];
        }

        if (preg_match('/^milestone:goal:\d+:(\d+)$/', $key, $m) === 1) {
            return ['kind' => 'milestone', 'label' => 'Reached '.$m[1].'% of a goal'];
        }

        if (str_starts_with($key, 'streak:')) {
            $days = (int) explode(':', $key)[1];

            return ['kind' => 'streak', 'label' => $days.'-day check-in streak'];
        }

        if (str_starts_with($key, 'login:')) {
            // Daily check-ins would dominate the feed — keep them out; the
            // streak events carry the same signal at meaningful moments.
            return null;
        }

        return null;
    }
}
