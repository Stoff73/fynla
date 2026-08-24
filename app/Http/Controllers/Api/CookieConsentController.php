<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Consent\CookieConsentService;
use App\Support\AwinClickCookie;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The single write path for cookie-banner consent, on every surface: the web
 * SPA banner, the server-rendered public pages, and anything on /m.
 *
 * Public by necessity — the decision is made before an account exists, and
 * most visitors never create one. It is unauthenticated but not unbounded: the
 * body carries no identifiers, the subject token is issued by the server, and
 * the route is rate-limited per IP.
 */
class CookieConsentController extends Controller
{
    public function __construct(
        private readonly CookieConsentService $consent
    ) {}

    /**
     * Record the visitor's decision and put it into force.
     *
     * `awc` is the Awin click reference from the URL the visitor is on. An
     * affiliate landing is a single request, and the banner is answered after
     * it, so gating capture on consent would otherwise lose the reference for
     * every affiliate visitor. Accepting it here moves capture to the moment
     * of consent instead of losing it. It grants no new capability: any
     * visitor can already set the same cookie by requesting /?awc=<value>.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in([
                CookieConsentService::STATUS_ACCEPTED,
                CookieConsentService::STATUS_DECLINED,
            ])],
            'awc' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $consented = $validated['status'] === CookieConsentService::STATUS_ACCEPTED;
        $subjectToken = $this->consent->subjectTokenFor($request);

        $this->consent->record($request, $consented, $subjectToken);

        $response = response()->json([
            'success' => true,
            'data' => ['status' => $validated['status']],
        ]);

        $this->consent->applyTo($request, $response, $consented, $subjectToken);

        $awc = $validated['awc'] ?? null;

        if ($consented) {
            if (config('awin.enabled') && is_string($awc) && $awc !== '') {
                AwinClickCookie::applyTo($response, $awc);
            }
        } else {
            // Withdrawal has to reach the HttpOnly affiliate cookie, which the
            // browser cannot clear on the visitor's behalf.
            AwinClickCookie::forgetFrom($response);
        }

        return $response;
    }
}
