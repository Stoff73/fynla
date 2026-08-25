<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Auth\WebHandoffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Cookie;

class WebHandoffController extends Controller
{
    public function __invoke(
        Request $request,
        string $token,
        WebHandoffService $handoffService,
    ): RedirectResponse {
        $handoff = $handoffService->consume($token);
        abort_if($handoff === null, 403);

        Auth::guard('web')->login($handoff->user);
        $request->session()->regenerate();

        $response = redirect()->to($handoff->destination->path());
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->setCookie(Cookie::create(
            name: 'fynla_web_session',
            value: '1',
            expire: now()->addMinutes(2),
            path: '/',
            // This short-lived bootstrap marker is consumed by same-host
            // JavaScript. Keep it host-only so that client-side deletion is
            // reliable even when the Laravel session spans a parent domain.
            domain: null,
            secure: (bool) config('session.secure'),
            httpOnly: false,
            raw: false,
            sameSite: Cookie::SAMESITE_LAX,
        ));

        return $response;
    }
}
