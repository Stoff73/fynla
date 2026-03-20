<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientActivityRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\User;
use App\Services\Admin\UserModuleTrackingService;
use App\Services\Advisor\AdvisorDashboardService;
use App\Services\Advisor\AdvisorImpersonationService;
use App\Services\Advisor\ClientActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvisorController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly AdvisorDashboardService $dashboardService,
        private readonly ClientActivityService $activityService,
        private readonly AdvisorImpersonationService $impersonationService,
        private readonly UserModuleTrackingService $moduleTracking
    ) {}

    public function dashboard(Request $request): JsonResponse
    {
        try {
            $stats = $this->dashboardService->getDashboardStats($request->user());

            return response()->json(['success' => true, 'data' => $stats]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load advisor dashboard', 500);
        }
    }

    public function clients(Request $request): JsonResponse
    {
        try {
            $clients = $this->dashboardService->getClientList($request->user(), $request->all());

            return response()->json(['success' => true, 'data' => $clients]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load clients', 500);
        }
    }

    public function clientDetail(Request $request, int $id): JsonResponse
    {
        try {
            $advisor = $request->user();
            $advisorClient = $advisor->advisorClients()
                ->where('client_id', $id)
                ->with(['client', 'activities' => fn ($q) => $q->latest('activity_date')])
                ->firstOrFail();

            $client = $advisorClient->client;
            $spouse = $client->spouse_id ? User::find($client->spouse_id) : null;
            $displayName = $spouse
                ? "{$client->first_name} & {$spouse->first_name} {$client->surname}"
                : "{$client->first_name} {$client->surname}";

            $data = [
                'id' => $advisorClient->id,
                'client_id' => $client->id,
                'display_name' => $displayName,
                'email' => $client->email,
                'module_status' => $this->moduleTracking->getModuleStatus($client),
                'last_review_date' => $advisorClient->last_review_date,
                'next_review_due' => $advisorClient->next_review_due,
                'review_frequency_months' => $advisorClient->review_frequency_months,
                'assigned_date' => $advisorClient->assigned_date,
                'status' => $advisorClient->status,
                'notes' => $advisorClient->notes,
                'activities' => $advisorClient->activities->map(fn ($a) => [
                    'id' => $a->id,
                    'activity_type' => $a->activity_type,
                    'summary' => $a->summary,
                    'details' => $a->details,
                    'activity_date' => $a->activity_date,
                    'follow_up_date' => $a->follow_up_date,
                    'follow_up_completed' => $a->follow_up_completed,
                    'report_type' => $a->report_type,
                    'report_sent_date' => $a->report_sent_date,
                ]),
            ];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Client not found or not assigned to you.'], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load client detail', 500);
        }
    }

    public function clientModuleStatus(Request $request, int $id): JsonResponse
    {
        try {
            $advisor = $request->user();
            $advisor->advisorClients()
                ->where('client_id', $id)
                ->firstOrFail();

            $client = User::findOrFail($id);
            $moduleStatus = $this->moduleTracking->getModuleStatus($client);

            return response()->json(['success' => true, 'data' => $moduleStatus]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Client not found or not assigned to you.'], 404);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load module status', 500);
        }
    }

    public function enterClient(Request $request, int $id): JsonResponse
    {
        try {
            $advisor = $request->user();
            $advisor->advisorClients()->where('client_id', $id)->where('status', 'active')->firstOrFail();
            $client = User::findOrFail($id);
            $result = $this->impersonationService->enterClientProfile($advisor, $client);

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Client not assigned to you.'], 403);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->getStatusCode());
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to enter client profile', 500);
        }
    }

    public function exitClient(Request $request): JsonResponse
    {
        try {
            $this->impersonationService->exitClientProfile($request->user());

            return response()->json(['success' => true, 'message' => 'Exited client profile.']);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to exit client profile', 500);
        }
    }

    public function activities(Request $request): JsonResponse
    {
        try {
            $activities = $this->activityService->listForAdvisor($request->user(), $request->all());

            return response()->json(['success' => true, 'data' => $activities]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load activities', 500);
        }
    }

    public function storeActivity(StoreClientActivityRequest $request): JsonResponse
    {
        try {
            $activity = $this->activityService->create($request->user(), $request->validated());

            return response()->json(['success' => true, 'data' => $activity, 'message' => 'Activity logged.'], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to create activity', 500);
        }
    }

    public function updateActivity(Request $request, int $id): JsonResponse
    {
        try {
            $activity = $this->activityService->update($request->user(), $id, $request->all());

            return response()->json(['success' => true, 'data' => $activity, 'message' => 'Activity updated.']);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to update activity', 500);
        }
    }

    public function reviewsDue(Request $request): JsonResponse
    {
        try {
            $reviews = $this->dashboardService->getReviewsDue($request->user());

            return response()->json(['success' => true, 'data' => $reviews]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load reviews', 500);
        }
    }

    public function reports(Request $request): JsonResponse
    {
        try {
            $reports = $this->activityService->listForAdvisor($request->user(), [
                'activity_type' => 'suitability_report',
            ]);

            return response()->json(['success' => true, 'data' => $reports]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Failed to load reports', 500);
        }
    }
}
