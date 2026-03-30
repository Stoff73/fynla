<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\AuditLog;
use App\Services\Audit\AuditService;
use App\Services\Auth\SessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SessionController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly SessionService $sessionService,
        private readonly AuditService $auditService
    ) {}

    /**
     * List all active sessions for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $sessions = $this->sessionService->getUserSessions($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'sessions' => $sessions,
                    'total' => $sessions->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Failed to load sessions', 500);
        }
    }

    /**
     * Revoke a specific session
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $session = $this->sessionService->findSession($user, $id);

        if (! $session) {
            return response()->json([
                'success' => false,
                'message' => 'Session not found.',
            ], 404);
        }

        if ($session->isCurrentSession()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot revoke current session. Use logout instead.',
            ], 400);
        }

        $this->sessionService->revokeSession($session);

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_SESSION_REVOKED, $user, [
            'session_id' => $id,
            'device' => $session->device_name ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Session has been revoked.',
        ]);
    }

    /**
     * Revoke all sessions except current.
     *
     * Requires password re-authentication to prevent abuse with a stolen token.
     */
    public function destroyOthers(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $revokedCount = $this->sessionService->revokeAllExceptCurrent($user);

        // Audit log
        $this->auditService->logAuth(AuditLog::ACTION_SESSION_REVOKED, $user, [
            'action' => 'revoke_all_others',
            'count' => $revokedCount,
        ]);

        return response()->json([
            'success' => true,
            'message' => "{$revokedCount} session(s) have been revoked.",
            'data' => [
                'revoked_count' => $revokedCount,
            ],
        ]);
    }
}
