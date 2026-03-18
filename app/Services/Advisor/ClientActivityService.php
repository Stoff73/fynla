<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Models\AdvisorClient;
use App\Models\AuditLog;
use App\Models\ClientActivity;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class ClientActivityService
{
    public function create(User $advisor, array $data): ClientActivity
    {
        $advisorClient = AdvisorClient::where('advisor_id', $advisor->id)
            ->where('client_id', $data['client_id'])
            ->active()
            ->firstOrFail();

        $activity = ClientActivity::create([
            'advisor_client_id' => $advisorClient->id,
            'advisor_id' => $advisor->id,
            'client_id' => $data['client_id'],
            'activity_type' => $data['activity_type'],
            'summary' => $data['summary'],
            'details' => $data['details'] ?? null,
            'activity_date' => $data['activity_date'],
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'follow_up_completed' => $data['follow_up_completed'] ?? false,
            'report_type' => $data['report_type'] ?? null,
            'report_sent_date' => $data['report_sent_date'] ?? null,
            'report_acknowledged_date' => $data['report_acknowledged_date'] ?? null,
        ]);

        Cache::forget("advisor:{$advisor->id}:clients");

        AuditLog::logAdmin('log_activity', [
            'advisor_id' => $advisor->id,
            'client_id' => $data['client_id'],
            'activity_type' => $data['activity_type'],
        ]);

        return $activity;
    }

    public function update(User $advisor, int $activityId, array $data): ClientActivity
    {
        $activity = ClientActivity::where('advisor_id', $advisor->id)
            ->findOrFail($activityId);

        $activity->update($data);

        Cache::forget("advisor:{$advisor->id}:clients");

        return $activity->fresh();
    }

    public function listForAdvisor(User $advisor, array $filters = []): array
    {
        $query = ClientActivity::where('advisor_id', $advisor->id)
            ->with(['client', 'advisorClient'])
            ->latest('activity_date');

        if (! empty($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (! empty($filters['activity_type'])) {
            $types = is_array($filters['activity_type'])
                ? $filters['activity_type']
                : explode(',', $filters['activity_type']);
            $query->whereIn('activity_type', $types);
        }

        if (! empty($filters['date_from'])) {
            $query->where('activity_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('activity_date', '<=', $filters['date_to']);
        }

        $paginated = $query->paginate($filters['per_page'] ?? 20);

        $paginated->getCollection()->transform(function (ClientActivity $activity) {
            $client = $activity->client;
            $clientName = $client
                ? "{$client->first_name} {$client->surname}"
                : 'Unknown';

            $activity->setAttribute('client_name', $clientName);

            return $activity;
        });

        return $paginated->toArray();
    }

    public function listForClient(int $advisorClientId): array
    {
        return ClientActivity::where('advisor_client_id', $advisorClientId)
            ->latest('activity_date')
            ->get()
            ->toArray();
    }
}
