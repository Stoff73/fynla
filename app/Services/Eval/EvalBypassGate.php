<?php

declare(strict_types=1);

namespace App\Services\Eval;

use App\Models\User;

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

        return true;
    }
}
