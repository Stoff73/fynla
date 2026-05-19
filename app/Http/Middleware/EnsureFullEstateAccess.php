<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Tiers\TeaserGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level guard for full-Estate sub-routes (spec §10.2 / SP2 PR7).
 *
 * The Will Builder, Will, Bequests and Lasting Power of Attorney endpoints use
 * FormRequest type-hints, so the controller-body GatesEstateAccess trait would
 * run *after* validation (422 before 403). This middleware applies the same
 * canonical TeaserGate decision before FormRequest resolution.
 *
 * It deliberately does NOT consult the legacy subscription plan (the existing
 * `feature:pro` middleware does that): a grandfathered legacy-paid user
 * resolves to the `free` tier under SP2 and must hit the Estate teaser, so the
 * SP2 TierResolver/TeaserGate is the only correct authority here.
 */
class EnsureFullEstateAccess
{
    public function __construct(private readonly TeaserGate $teaserGate) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $this->teaserGate->isFull($user, 'estate')) {
            abort(403, 'Full Estate Planning requires Tier 2 or above.');
        }

        return $next($request);
    }
}
