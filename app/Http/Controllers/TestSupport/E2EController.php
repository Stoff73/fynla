<?php

declare(strict_types=1);

namespace App\Http\Controllers\TestSupport;

use App\Http\Controllers\Controller;
use App\Models\EmailVerificationCode;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class E2EController extends Controller
{
    public function verificationCode(Request $request): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $userId = User::query()
            ->where('email', $validated['email'])
            ->value('id');

        if ($userId !== null) {
            $code = EmailVerificationCode::query()
                ->where('user_id', $userId)
                ->whereNull('verified_at')
                ->where('expires_at', '>', now())
                ->where('failed_attempts', '<', 5)
                ->latest('id')
                ->value('code');
        } else {
            $code = PendingRegistration::query()
                ->where('email', $validated['email'])
                ->where('expires_at', '>', now())
                ->latest('id')
                ->value('verification_code');
        }

        abort_if($code === null, 404);

        return response()->json(['code' => $code]);
    }

    public function user(Request $request): JsonResponse
    {
        $this->ensureE2EEnvironment();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $user = User::query()
            ->select([
                'email',
                'email_verified_at',
                'is_preview_user',
                'onboarding_completed',
            ])
            ->where('email', $validated['email'])
            ->firstOrFail();

        return response()->json([
            'email' => $user->email,
            'email_verified' => $user->email_verified_at !== null,
            'is_preview_user' => (bool) $user->is_preview_user,
            'onboarding_completed' => (bool) $user->onboarding_completed,
        ]);
    }

    private function ensureE2EEnvironment(): void
    {
        abort_unless(app()->environment('e2e'), 404);
    }
}
