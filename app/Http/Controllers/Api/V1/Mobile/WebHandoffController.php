<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Enums\WebHandoffDestination;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\IssueWebHandoffRequest;
use App\Models\User;
use App\Services\Auth\PermissionService;
use App\Services\Auth\WebHandoffService;
use Illuminate\Http\JsonResponse;

class WebHandoffController extends Controller
{
    public function __invoke(
        IssueWebHandoffRequest $request,
        PermissionService $permissionService,
        WebHandoffService $handoffService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $destination = $request->destination();

        abort_if(
            $destination === WebHandoffDestination::ADMIN
                && ! $permissionService->isAdmin($user),
            403,
        );

        $issued = $handoffService->issue($user, $destination);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => route('web-handoff.consume', ['token' => $issued['token']]),
                'expires_at' => $issued['expires_at']->toISOString(),
            ],
        ], 201)->withHeaders([
            'Cache-Control' => 'no-store, private',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
