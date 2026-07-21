<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Models\AdvisorClient;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\PersonalAccessToken;

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

        $token = $advisor->currentAccessToken();
        abort_if(
            ! ($token instanceof PersonalAccessToken),
            503,
            'Advisor impersonation is not supported under cookie-based SPA authentication. Use a personal access token.'
        );
        $tokenId = $token->id;

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
        $token = $advisor->currentAccessToken();
        if (! ($token instanceof PersonalAccessToken)) {
            return;
        }
        $tokenId = $token->id;
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
        $token = $advisor->currentAccessToken();
        if (! ($token instanceof PersonalAccessToken)) {
            return false;
        }

        return Cache::has("advisor_impersonation:{$token->id}");
    }

    public function getImpersonatedClientId(User $advisor): ?int
    {
        $token = $advisor->currentAccessToken();
        if (! ($token instanceof PersonalAccessToken)) {
            return null;
        }

        return Cache::get("advisor_impersonation:{$token->id}")['client_id'] ?? null;
    }
}
