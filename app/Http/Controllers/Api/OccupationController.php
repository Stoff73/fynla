<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\OccupationCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OccupationController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Search for occupations matching the query.
     * Requires minimum 3 characters to search.
     *
     * GET /api/occupations/search?q={query}
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 3) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Enter at least 3 characters to search',
            ]);
        }

        $occupations = OccupationCode::search($query, 10);

        return response()->json([
            'success' => true,
            'data' => $occupations->map(fn ($occ) => [
                'id' => $occ->id,
                'title' => $occ->title,
                'soc_code' => $occ->soc_code,
                'unit_group' => $occ->unit_group,
            ]),
        ]);
    }
}
