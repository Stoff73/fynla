<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Models\AdvisorClient;
use App\Models\ClientActivity;
use App\Models\User;
use App\Services\Admin\UserModuleTrackingService;
use Illuminate\Support\Facades\Cache;

class AdvisorDashboardService
{
    public function __construct(
        private readonly UserModuleTrackingService $moduleTracking
    ) {}

    public function getDashboardStats(User $advisor): array
    {
        $clients = $advisor->advisorClients()->active()->count();
        $reviewsDue = $advisor->advisorClients()->active()
            ->where('next_review_due', '<=', now())->count();
        $commsThisWeek = ClientActivity::where('advisor_id', $advisor->id)
            ->where('activity_date', '>=', now()->startOfWeek())->count();
        $reportsThisMonth = ClientActivity::where('advisor_id', $advisor->id)
            ->reports()->where('activity_date', '>=', now()->startOfMonth())->count();

        return compact('clients', 'reviewsDue', 'commsThisWeek', 'reportsThisMonth');
    }

    public function getClientList(User $advisor, array $filters = []): array
    {
        return Cache::remember("advisor:{$advisor->id}:clients", 300, function () use ($advisor) {
            $query = $advisor->advisorClients()
                ->active()
                ->with(['client', 'activities' => fn ($q) => $q->latest('activity_date')->limit(5)]);

            $advisorClients = $query->get();

            // In production, filter out preview personas (spec 2.3 / 2.4)
            $isProduction = app()->environment('production');

            return $advisorClients
                ->when($isProduction, fn ($collection) => $collection->filter(
                    fn (AdvisorClient $ac) => ! $ac->client->is_preview_user
                ))
                ->map(function (AdvisorClient $ac) {
                    $client = $ac->client;
                    $spouse = $client->spouse_id ? User::find($client->spouse_id) : null;

                    $displayName = $spouse
                        ? "{$client->first_name} & {$spouse->first_name} {$client->surname}"
                        : "{$client->first_name} {$client->surname}";

                    return [
                        'id' => $ac->id,
                        'client_id' => $client->id,
                        'display_name' => $displayName,
                        'persona_type' => $client->preview_persona_id,
                        'is_preview_user' => $client->is_preview_user,
                        'module_status' => $this->moduleTracking->getModuleStatus($client),
                        'last_review_date' => $ac->last_review_date,
                        'next_review_due' => $ac->next_review_due,
                        'review_frequency_months' => $ac->review_frequency_months,
                        'status' => $ac->status,
                        'last_communication' => $ac->activities->first(fn ($a) => $a->activity_type !== 'suitability_report'),
                        'last_report' => $ac->activities->first(fn ($a) => $a->activity_type === 'suitability_report'),
                    ];
                })->values()->toArray();
        });
    }

    public function getReviewsDue(User $advisor): array
    {
        $advisorClients = $advisor->advisorClients()
            ->active()
            ->where(function ($q) {
                $q->where('next_review_due', '<=', now())
                    ->orWhere('next_review_due', '<=', now()->addDays(30));
            })
            ->with(['client', 'activities' => fn ($q) => $q->latest('activity_date')->limit(1)])
            ->orderBy('next_review_due', 'asc')
            ->get();

        return $advisorClients->map(function (AdvisorClient $ac) {
            $client = $ac->client;
            $spouse = $client->spouse_id ? User::find($client->spouse_id) : null;
            $displayName = $spouse
                ? "{$client->first_name} & {$spouse->first_name} {$client->surname}"
                : "{$client->first_name} {$client->surname}";

            $daysOverdue = $ac->next_review_due?->isPast()
                ? (int) $ac->next_review_due->diffInDays(now())
                : null;
            $daysUntilDue = $ac->next_review_due?->isFuture()
                ? (int) now()->diffInDays($ac->next_review_due)
                : null;

            return [
                'id' => $ac->id,
                'client_id' => $client->id,
                'display_name' => $displayName,
                'next_review_due' => $ac->next_review_due,
                'last_review_date' => $ac->last_review_date,
                'days_overdue' => $daysOverdue,
                'days_until_due' => $daysUntilDue,
                'is_overdue' => $ac->next_review_due?->isPast() ?? false,
                'review_frequency_months' => $ac->review_frequency_months,
                'last_activity' => $ac->activities->first(),
                'module_status' => $this->moduleTracking->getModuleStatus($client),
            ];
        })->toArray();
    }

    public function getRecentActivity(User $advisor, int $limit = 10): array
    {
        $activities = ClientActivity::where('advisor_id', $advisor->id)
            ->with(['client', 'advisorClient'])
            ->latest('activity_date')
            ->limit($limit)
            ->get();

        return $activities->map(function (ClientActivity $activity) {
            $client = $activity->client;
            $spouse = $client->spouse_id ? User::find($client->spouse_id) : null;
            $displayName = $spouse
                ? "{$client->first_name} & {$spouse->first_name} {$client->surname}"
                : "{$client->first_name} {$client->surname}";

            return [
                'id' => $activity->id,
                'client_id' => $activity->client_id,
                'client_name' => $displayName,
                'activity_type' => $activity->activity_type,
                'summary' => $activity->summary,
                'activity_date' => $activity->activity_date,
                'report_type' => $activity->report_type,
                'follow_up_date' => $activity->follow_up_date,
                'follow_up_completed' => $activity->follow_up_completed,
            ];
        })->toArray();
    }
}
