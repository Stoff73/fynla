<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateNotificationPreferencesRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web (desktop) endpoint for notification preferences. The mobile app has
 * its own dedicated controller at Api\V1\Mobile\NotificationPreferenceController
 * because the mobile settings UI shape differs slightly — this one exists
 * so the web UserProfile page can read/write the full 14 preference columns
 * without routing through the mobile namespace.
 */
class NotificationPreferenceController extends Controller
{
    use SanitizedErrorResponse;

    public function show(Request $request): JsonResponse
    {
        try {
            $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => $prefs->only([
                    'policy_renewals',
                    'goal_milestones',
                    'contribution_reminders',
                    'market_updates',
                    'fyn_daily_insight',
                    'security_alerts',
                    'payment_alerts',
                    'mortgage_rate_alerts',
                    'estate_alerts',
                    'lifecycle_empty_trialer',
                    'lifecycle_engaged_trialer',
                    'lifecycle_cancelled_trialer',
                    'lifecycle_churned_subscriber',
                    'lifecycle_lapsed_subscriber',
                ]),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching notification preferences');
        }
    }

    public function update(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        try {
            $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);
            $prefs->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Notification preferences updated.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating notification preferences');
        }
    }
}
