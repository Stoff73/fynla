<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AppAccountTokenService
{
    public function issue(User $user): string
    {
        return DB::transaction(function () use ($user): string {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUser->is_preview_user) {
                throw new AuthorizationException('Preview users cannot receive StoreKit account tokens.');
            }

            if ($lockedUser->apple_app_account_token !== null) {
                return $lockedUser->apple_app_account_token;
            }

            $token = (string) Str::uuid();
            $lockedUser->forceFill(['apple_app_account_token' => $token])->save();

            return $token;
        });
    }
}
