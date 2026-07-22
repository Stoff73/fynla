<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Native;

use App\Http\Controllers\Controller;
use App\Http\Resources\NativeEntitlementResource;
use App\Models\User;
use App\Services\Billing\PremiumEntitlementResolver;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NativeEntitlementController extends Controller
{
    public function __construct(
        private readonly PremiumEntitlementResolver $entitlements,
        private readonly TierConfigurationStore $tiers,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->entitlements->invalidate($user);
        $entitlement = $this->entitlements->resolve($user->fresh());
        $tier = $this->tiers->forTier($entitlement->tier);

        return response()->json([
            'success' => true,
            'data' => [
                'entitlement' => (new NativeEntitlementResource(
                    $entitlement,
                    $tier->capability_matrix ?? [],
                    $tier->count_caps ?? [],
                ))->resolve(),
            ],
        ]);
    }
}
