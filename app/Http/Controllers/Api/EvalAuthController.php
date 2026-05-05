<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Eval-only HTTP surface — issues `bypass-preview-mode` Sanctum tokens for
 * preview personas and resets persona data via the existing preview:reset
 * command. The engine/gate trace is no longer fetched over HTTP — see
 * EvalHttpDriver and EvalTraceCollector::persistForConversation for the
 * in-process cache hand-off.
 *
 * Every endpoint refuses outright in production. Belt-and-braces: the
 * routes themselves are wrapped in `if (! app()->environment('production'))`
 * in routes/api.php so they don't even register on prod.
 */
final class EvalAuthController extends Controller
{
    private const VALID_PERSONAS = [
        'young_family',
        'peak_earners',
        'entrepreneur',
        'young_saver',
        'retired_couple',
        'student',
    ];

    public function login(Request $request, string $personaId): JsonResponse
    {
        if (App::environment('production')) {
            return response()->json(['error' => 'eval login disabled in production'], 403);
        }

        if (! in_array($personaId, self::VALID_PERSONAS, true)) {
            return response()->json(['error' => 'invalid persona'], 400);
        }

        $user = User::where('is_preview_user', true)
            ->where('preview_persona_id', $personaId)
            ->first();

        if (! $user) {
            return response()->json([
                'error' => 'preview user not seeded',
                'hint' => 'php artisan db:seed --class=PreviewUserSeeder',
            ], 404);
        }

        $token = $user->createToken(
            name: 'eval-'.now()->timestamp,
            abilities: ['bypass-preview-mode'],
            expiresAt: now()->addMinutes((int) config('fyn_eval.token_ttl_minutes', 15)),
        )->plainTextToken;

        // M20 — audit log at mint. The bypass-preview-mode token sidesteps
        // PreviewWriteInterceptor; even though minting is gated to
        // non-production environments, every mint should be visible in the
        // audit trail so an unexpected eval login on staging is detectable.
        Log::channel(config('logging.eval_audit_channel', 'stack'))->notice('[EvalAuthController] bypass-preview-mode token minted', [
            'user_id' => $user->id,
            'persona' => $personaId,
            'environment' => app()->environment(),
            'ttl_minutes' => (int) config('fyn_eval.token_ttl_minutes', 15),
            'request_ip' => $request->ip(),
        ]);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'persona' => $personaId,
                'is_preview_user' => true,
                'token_abilities' => ['bypass-preview-mode'],
            ],
        ]);
    }

    public function reset(Request $request, string $personaId): JsonResponse
    {
        if (App::environment('production')) {
            return response()->json(['error' => 'eval reset disabled in production'], 403);
        }

        if (! in_array($personaId, self::VALID_PERSONAS, true)) {
            return response()->json(['error' => 'invalid persona'], 400);
        }

        Artisan::call('preview:reset', ['persona' => $personaId]);

        return response()->json(['reset' => $personaId]);
    }
}
