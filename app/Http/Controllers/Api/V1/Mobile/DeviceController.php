<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\RegisterDeviceRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    use SanitizedErrorResponse;

    public function store(RegisterDeviceRequest $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $existing = DeviceToken::where('user_id', $userId)
                ->where('device_id', $request->device_id)
                ->first();

            if ($existing) {
                $existing->update([
                    'device_token' => $request->device_token,
                    'platform' => $request->platform,
                    'device_name' => $request->device_name,
                    'app_version' => $request->app_version,
                    'os_version' => $request->os_version,
                    'last_used_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Device token updated.',
                    'data' => ['device_id' => $existing->device_id],
                ]);
            }

            $token = DeviceToken::create([
                'user_id' => $userId,
                'device_token' => $request->device_token,
                'device_id' => $request->device_id,
                'platform' => $request->platform,
                'device_name' => $request->device_name,
                'app_version' => $request->app_version,
                'os_version' => $request->os_version,
                'last_used_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device registered.',
                'data' => ['device_id' => $token->device_id],
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Registering device');
        }
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $devices = DeviceToken::forUser($request->user()->id)
                ->orderByDesc('last_used_at')
                ->get(['device_id', 'platform', 'device_name', 'app_version', 'os_version', 'last_used_at']);

            return response()->json([
                'success' => true,
                'data' => ['devices' => $devices],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Listing devices');
        }
    }

    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        try {
            $deleted = DeviceToken::where('user_id', $request->user()->id)
                ->where('device_id', $deviceId)
                ->delete();

            if (! $deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device not found.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Device revoked.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Revoking device');
        }
    }
}
