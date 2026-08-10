<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\WebHandoffDestination;
use App\Models\User;
use App\Models\WebHandoff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebHandoffService
{
    private const int TOKEN_LENGTH = 64;

    private const int LIFETIME_MINUTES = 2;

    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function issue(User $user, WebHandoffDestination $destination): array
    {
        $plainToken = Str::random(self::TOKEN_LENGTH);
        $expiresAt = CarbonImmutable::instance(now())->addMinutes(self::LIFETIME_MINUTES);

        WebHandoff::query()->create([
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $plainToken),
            'destination' => $destination,
            'expires_at' => $expiresAt,
        ]);

        return [
            'token' => $plainToken,
            'expires_at' => $expiresAt,
        ];
    }

    public function consume(string $plainToken): ?WebHandoff
    {
        return DB::transaction(function () use ($plainToken): ?WebHandoff {
            $handoff = WebHandoff::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if ($handoff === null
                || $handoff->consumed_at !== null
                || $handoff->expires_at->lessThanOrEqualTo(now())
                || $handoff->user === null) {
                return null;
            }

            $handoff->forceFill(['consumed_at' => now()])->save();

            return $handoff;
        });
    }
}
