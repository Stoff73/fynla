<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tiers\TeaserGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard making the Holistic Plan a premium (Tier 2+) capability
 * (/m freemium 5.3). Mirrors EnsureFullEstateAccess: it consults the canonical
 * SP2 TeaserGate (holistic_plan capability = 'full' only on tier2/tier3),
 * independent of the legacy `feature:pro` plan gate that already sits on the
 * route group. Returns a structured 403 so both the /m and web SPAs can render
 * an Upgrade prompt instead of a generic error.
 */
class EnsureFullHolisticAccess
{
    public function __construct(private readonly TeaserGate $teaserGate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $this->teaserGate->isFull($user, 'holistic_plan')) {
            return response()->json([
                'error' => 'upgrade_required',
                'message' => 'The Holistic Plan is part of Tier 2 and above.',
                'required_tier' => 'tier2',
            ], 403);
        }

        return $next($request);
    }
}
