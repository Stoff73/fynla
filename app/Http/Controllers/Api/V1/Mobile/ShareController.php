<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Mobile\ShareContentGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly ShareContentGenerator $generator,
    ) {}

    public function show(Request $request, string $type, ?string $id = null): JsonResponse
    {
        $validTypes = ['goal_milestone', 'net_worth_milestone', 'fyn_insight', 'app_referral'];

        if (! in_array($type, $validTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid share type.',
            ], 422);
        }

        try {
            $content = $this->generator->generate(
                $type,
                $id ? (int) $id : null,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data' => $content,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Generating share content');
        }
    }
}
