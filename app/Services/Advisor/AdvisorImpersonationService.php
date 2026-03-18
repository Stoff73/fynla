<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Models\AdvisorClient;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class AdvisorImpersonationService
{
    private const TTL_HOURS = 8;

    public function enterClientProfile(User $advisor, User $client): array
    {
        abort_unless(
            AdvisorClient::where('advisor_id', $advisor->id)
                ->where('client_id', $client->id)
                ->where('status', 'active')
                ->exists(),
            403, 'Client is not assigned to you'
        );
        abort_if($client->is_admin, 403, 'Cannot enter an admin account');
        abort_if($client->is_advisor, 403, 'Cannot enter another advisor account');
        abort_if($this->isImpersonating($advisor), 403, 'Already impersonating a client');

        $tokenId = $advisor->currentAccessToken()->id;
        Cache::put(
            "advisor_impersonation:{$tokenId}",
            ['client_id' => $client->id, 'started_at' => now()],
            now()->addHours(self::TTL_HOURS)
        );

        AuditLog::logAdmin('enter_client', [
            'advisor_id' => $advisor->id,
            'client_id' => $client->id,
        ]);

        return ['impersonating' => true, 'client' => $client->only(['id', 'first_name', 'surname', 'email'])];
    }

    public function exitClientProfile(User $advisor): void
    {
        $tokenId = $advisor->currentAccessToken()->id;
        $cached = Cache::get("advisor_impersonation:{$tokenId}");

        if ($cached) {
            AuditLog::logAdmin('exit_client', [
                'advisor_id' => $advisor->id,
                'client_id' => $cached['client_id'],
            ]);
            Cache::forget("advisor_impersonation:{$tokenId}");
        }
    }

    public function isImpersonating(User $advisor): bool
    {
        $tokenId = $advisor->currentAccessToken()?->id;

        return $tokenId && Cache::has("advisor_impersonation:{$tokenId}");
    }

    public function getImpersonatedClientId(User $advisor): ?int
    {
        $tokenId = $advisor->currentAccessToken()?->id;

        return Cache::get("advisor_impersonation:{$tokenId}")['client_id'] ?? null;
    }
}
