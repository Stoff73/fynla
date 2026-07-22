<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnforceNativeVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/native/*')) {
            return $next($request);
        }

        $policy = $this->policy();

        if ($policy === null) {
            return response()->json([
                'success' => false,
                'error' => 'native_policy_unavailable',
                'message' => 'Native version policy is temporarily unavailable.',
            ], 503);
        }

        $clientVersion = $request->attributes->get('fynla_version');
        $clientBuild = $request->attributes->get('fynla_build');

        if (! is_string($clientVersion) || ! is_int($clientBuild)) {
            return response()->json([
                'success' => false,
                'error' => 'invalid_native_client',
                'message' => 'Valid native client headers are required.',
            ], 400);
        }

        if (version_compare($clientVersion, $policy['minimum_version'], '<')
            || $clientBuild < $policy['minimum_build']) {
            return $this->updateRequiredResponse($policy);
        }

        return $next($request);
    }

    /**
     * @return array{minimum_version: string, minimum_build: int, app_store_url: string}|null
     */
    private function policy(): ?array
    {
        $environment = config('native.environment');
        $minimumVersion = config("native.environments.{$environment}.minimum_version");
        $minimumBuild = config("native.environments.{$environment}.minimum_build");
        $appStoreURL = config('native.app_store_url');

        if (! is_string($environment)
            || ! in_array($environment, ['staging', 'production'], true)
            || ! is_string($minimumVersion)
            || preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/D', $minimumVersion) !== 1
            || ! is_int($minimumBuild)
            || $minimumBuild < 1
            || ! is_string($appStoreURL)
            || ! $this->isPublicAppStoreURL($appStoreURL)) {
            return null;
        }

        return [
            'minimum_version' => $minimumVersion,
            'minimum_build' => $minimumBuild,
            'app_store_url' => $appStoreURL,
        ];
    }

    private function isPublicAppStoreURL(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && strtolower((string) ($parts['host'] ?? '')) === 'apps.apple.com'
            && ! isset($parts['user'], $parts['pass'], $parts['port'], $parts['fragment']);
    }

    /**
     * @param  array{minimum_version: string, minimum_build: int, app_store_url: string}  $policy
     */
    private function updateRequiredResponse(array $policy): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'native_update_required',
            'message' => 'A newer version of Fynla is required.',
            'minimum_version' => $policy['minimum_version'],
            'minimum_build' => $policy['minimum_build'],
            'app_store_url' => $policy['app_store_url'],
        ], 426);
    }
}
