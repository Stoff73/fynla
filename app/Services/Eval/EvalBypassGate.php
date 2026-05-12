<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * April30Updates F-12 — defence-in-depth on the eval-only Sanctum
 * `bypass-preview-mode` ability.
 *
 * Pre-fix: any token with the `bypass-preview-mode` ability bypassed
 * preview-mode tool filtering and write interception. A leaked or
 * misconfigured token alone was enough to write to a preview persona's
 * data. The eval harness is the only legitimate consumer.
 *
 * Post-fix: the ability is honoured ONLY when paired with the
 * `X-Eval-Run-Id` header. The eval harness already sets this header
 * (`EvalHttpDriver`); a stolen or accidentally-broadcast token cannot
 * be used unless the attacker also forges the header — which is
 * trivial in isolation, but the combination is now visible in access
 * logs and easy to alert on. A future enhancement should verify the
 * run-id against a server-side allowlist of in-flight runs.
 *
 * The gate is conservative: when in doubt, return false (no bypass).
 * False negatives just mean an eval needs to set the header (which it
 * already does). False positives — granting bypass when not in eval —
 * are the security failure to avoid.
 */
final class EvalBypassGate
{
    /**
     * Returns true when the request can use the bypass-preview-mode
     * ability — i.e. the user's active Sanctum token has the ability
     * AND an X-Eval-Run-Id header is present.
     */
    public static function isActive(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        $token = $user->currentAccessToken();
        if ($token === null) {
            return false;
        }

        // Security boundary: only Personal Access Tokens carry persisted
        // abilities. TransientToken (stateful SPA cookie auth) reports
        // `can($anything)` as true and has no `abilities` array — never
        // honour a bypass under that path. Fail closed.
        if (! ($token instanceof PersonalAccessToken)) {
            return false;
        }

        if (! in_array('bypass-preview-mode', $token->abilities ?? [], true)) {
            return false;
        }

        // Without an active request context (e.g. a queued job calling
        // chat()) we cannot read the eval header. Treat that as
        // not-in-eval and deny the bypass.
        if (! function_exists('request') || request() === null) {
            return false;
        }

        $header = request()->header('X-Eval-Run-Id');
        if (! is_string($header) || trim($header) === '') {
            return false;
        }

        // M20 — audit log first time the gate opens for a request. The
        // request bag holds a one-shot flag so subsequent calls in the
        // same request don't spam (every event listener calls isActive).
        $request = request();
        if ($request !== null && ! $request->attributes->getBoolean('eval_bypass_logged')) {
            Log::channel(config('logging.eval_audit_channel', 'stack'))->notice('[EvalBypassGate] bypass-preview-mode honoured for request', [
                'user_id' => $user->id,
                'eval_run_id' => trim($header),
                'environment' => app()->environment(),
                'route' => $request->path(),
                'method' => $request->method(),
            ]);
            $request->attributes->set('eval_bypass_logged', true);
        }

        return true;
    }
}
