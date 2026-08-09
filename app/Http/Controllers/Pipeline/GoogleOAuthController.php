<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pipeline;

use App\Http\Controllers\Controller;
use App\Services\Pipeline\Google\GoogleOAuthClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Landing endpoint for the Google OAuth redirect. Only reached during the
 * one-time `pipeline:authorise-google` flow. Not a user-facing route — the
 * response is a plain HTML confirmation page.
 */
class GoogleOAuthController extends Controller
{
    public function callback(Request $request, GoogleOAuthClient $client): mixed
    {
        if ($request->has('error')) {
            return response($this->page(
                'Authorisation cancelled',
                'Google returned: '.$request->string('error').
                ($request->has('error_description') ? '<br><br>'.$request->string('error_description') : '')
            ), 400);
        }

        $code = $request->string('code')->toString();
        $state = $request->string('state')->toString();

        if ($code === '' || $state === '') {
            return response($this->page(
                'Missing code or state',
                'The redirect did not include the expected parameters. Re-run <code>php artisan pipeline:authorise-google</code>.'
            ), 400);
        }

        if (! Cache::pull('pipeline.oauth.state.'.$state)) {
            return response($this->page(
                'Invalid or expired state',
                'This authorisation URL has already been used or has expired. Re-run <code>php artisan pipeline:authorise-google</code> to get a fresh one.'
            ), 400);
        }

        try {
            $credential = $client->exchangeCode($code);
        } catch (Throwable $e) {
            Log::channel('pipeline')->error('OAuth callback exchange failed.', [
                'error' => $e->getMessage(),
            ]);

            return response($this->page(
                'Authorisation failed',
                'Token exchange with Google failed.<br><br>'.e($e->getMessage()).
                '<br><br>See <code>storage/logs/pipeline*.log</code> for details.'
            ), 500);
        }

        Cache::put('pipeline.oauth.result', [
            'account_email' => $credential->account_email,
            'authorised_at' => now()->toIso8601String(),
        ], now()->addMinutes(15));

        return response($this->page(
            'Authorised as '.($credential->account_email ?? 'unknown account'),
            'Refresh token stored. You can close this tab and return to the terminal.'
        ));
    }

    private function page(string $title, string $body): string
    {
        $escapedTitle = e($title);

        return <<<HTML
            <!doctype html>
            <html lang="en-GB">
            <head>
                <meta charset="utf-8">
                <title>{$escapedTitle} — Fynla Pipeline</title>
                <style>
                    body { font-family: 'Segoe UI', system-ui, sans-serif; max-width: 640px; margin: 5rem auto; padding: 0 1.5rem; color: #1F2A44; }
                    h1 { font-weight: 700; font-size: 1.5rem; margin-bottom: 1rem; }
                    p { line-height: 1.55; color: #4b5563; }
                    code { background: #f3f4f6; padding: 0.15rem 0.35rem; border-radius: 4px; font-size: 0.9em; }
                </style>
            </head>
            <body>
                <h1>{$escapedTitle}</h1>
                <p>{$body}</p>
            </body>
            </html>
        HTML;
    }
}
