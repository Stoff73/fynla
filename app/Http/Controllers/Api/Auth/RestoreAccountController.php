<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RestoreAccountController extends Controller
{
    public function __construct(
        private readonly AccountDeletionService $service
    ) {}

    public function check(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::withTrashed()->where('email', $request->input('email'))->first();
        if (! $user || ! $user->trashed()) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }
        if ($user->deletion_reason === 'legacy_purged') {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return response()->json(['restoration_token' => $this->issueToken($user)]);
    }

    public function restore(Request $request): JsonResponse
    {
        $request->validate(['restoration_token' => 'required|string']);

        $cached = Cache::pull('restoration_token:'.$request->input('restoration_token'));
        if (! $cached) {
            return response()->json(['message' => 'Invalid or expired restoration token'], 401);
        }

        $user = User::withTrashed()->find($cached['user_id']);
        if (! $user || ! $user->canBeRestored()) {
            return response()->json(['message' => 'Account cannot be restored'], 401);
        }

        $this->service->restoreAccount($user);
        $user->refresh();

        $token = $user->createToken('restored-session')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'email', 'first_name', 'surname']),
            'redirect_to' => '/subscription/select',
        ]);
    }

    private function issueToken(User $user): string
    {
        $token = Str::random(64);
        Cache::put(
            "restoration_token:{$token}",
            ['user_id' => $user->id, 'issued_at' => now()->toIso8601String()],
            now()->addMinutes(5)
        );

        return $token;
    }
}
