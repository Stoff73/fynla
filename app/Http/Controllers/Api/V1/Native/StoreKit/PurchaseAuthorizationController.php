<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Native\StoreKit;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class PurchaseAuthorizationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => (bool) config('native.storekit_purchase_enabled', false),
            ],
        ]);
    }
}
