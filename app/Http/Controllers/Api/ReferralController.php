<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payment\ReferralService;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly ReferralService $referralService
    ) {}

    /**
     * Get the authenticated user's referral code.
     * GET /api/referral/code
     */
    public function getMyCode(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->subscription;
        if (! $subscription || $subscription->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'You must have an active subscription to refer a friend.',
            ], 403);
        }

        $code = $this->referralService->generateCode($user);

        return response()->json([
            'success' => true,
            'data' => ['code' => $code],
        ]);
    }

    /**
     * Send a referral invitation.
     * POST /api/referral/invite
     */
    public function sendInvitation(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = $request->user();

        try {
            $referral = $this->referralService->sendInvitation($user, $request->input('email'));

            return response()->json([
                'success' => true,
                'message' => 'Invitation sent successfully.',
                'data' => [
                    'referral_id' => $referral->id,
                    'email' => $referral->referee_email,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Sending referral invitation');
        }
    }

    /**
     * Get the user's referral history.
     * GET /api/referral/list
     */
    public function myReferrals(Request $request): JsonResponse
    {
        $user = $request->user();

        $referrals = $user->referralsSent()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'email' => $r->referee_email,
                'status' => $r->status,
                'bonus_applied' => $r->bonus_applied,
                'referred_at' => $r->referred_at?->toIso8601String(),
                'registered_at' => $r->registered_at?->toIso8601String(),
                'converted_at' => $r->converted_at?->toIso8601String(),
            ]);

        return response()->json([
            'success' => true,
            'data' => ['referrals' => $referrals],
        ]);
    }
}
