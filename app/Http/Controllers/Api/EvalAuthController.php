<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\User;
use App\Services\Eval\EvalTraceCollector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;

/**
 * Eval-only HTTP surface — issues `bypass-preview-mode` Sanctum tokens for
 * preview personas, resets persona data via the existing preview:reset
 * command, and returns the request-scoped engine/gate trace captured
 * during a chat send.
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
            abilities: ['bypass-preview-mode']
        )->plainTextToken;

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

    public function trace(Request $request, int $conversationId): JsonResponse
    {
        if (App::environment('production')) {
            return response()->json(['error' => 'eval trace disabled in production'], 403);
        }

        $user = $request->user();
        if (! $user || ! AiConversation::where('id', $conversationId)->where('user_id', $user->id)->exists()) {
            return response()->json(['error' => 'conversation not found'], 404);
        }

        return response()->json([
            'conversation_id' => $conversationId,
            'events' => app(EvalTraceCollector::class)->all(),
        ]);
    }
}
