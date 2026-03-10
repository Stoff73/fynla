<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\UpdateNotificationPreferencesRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use SanitizedErrorResponse;

    public function show(Request $request): JsonResponse
    {
        try {
            $prefs = NotificationPreference::getOrCreateForUser($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'policy_renewals' => $prefs->policy_renewals,
                    'goal_milestones' => $prefs->goal_milestones,
                    'contribution_reminders' => $prefs->contribution_reminders,
                    'market_updates' => $prefs->market_updates,
                    'fyn_daily_insight' => $prefs->fyn_daily_insight,
                    'security_alerts' => $prefs->security_alerts,
                    'payment_alerts' => $prefs->payment_alerts,
                    'mortgage_rate_alerts' => $prefs->mortgage_rate_alerts,
                ],
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
