<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Pipeline;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Health check for cross-env sync credentials. Returns per-env:
 *   - configured: URL + token set locally
 *   - reachable: target's sync-inbound endpoint responds within 5s
 *   - token_valid: target accepts our token (ping succeeds)
 *
 * Called by ArticleManager.vue's status band. Failure modes:
 *   configured=false → env keys missing on THIS machine
 *   reachable=false  → target env down, DNS wrong, or firewall
 *   token_valid=false → local token doesn't match target's PIPELINE_SYNC_TOKEN
 */
class PipelineSyncStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:admin.access');
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'dev' => $this->probe('dev'),
                'prod' => $this->probe('prod'),
                'inbound' => [
                    'configured' => is_string(config('pipeline.sync.inbound_token')) && config('pipeline.sync.inbound_token') !== '',
                ],
            ],
        ]);
    }

    /**
     * @return array{configured:bool,reachable:?bool,token_valid:?bool,url:?string,error:?string}
     */
    private function probe(string $env): array
    {
        $url = (string) config("pipeline.sync.{$env}_url");
        $token = (string) config("pipeline.sync.{$env}_token");

        if ($url === '' || $token === '') {
            return [
                'configured' => false,
                'reachable' => null,
                'token_valid' => null,
                'url' => $url ?: null,
                'error' => 'PIPELINE_SYNC_URL_'.strtoupper($env).' or PIPELINE_SYNC_TOKEN_'.strtoupper($env).' not set.',
            ];
        }

        $endpoint = rtrim($url, '/').'/api/admin/pipeline/articles/sync-inbound';

        try {
            $response = Http::withHeaders([
                'X-Pipeline-Sync-Token' => $token,
                'X-Pipeline-Sync-Env' => app()->environment(),
                'X-Pipeline-Ping' => '1',
            ])
                ->timeout(5)
                ->post($endpoint.'?ping=1', []);
        } catch (Throwable $e) {
            return [
                'configured' => true,
                'reachable' => false,
                'token_valid' => null,
                'url' => $url,
                'error' => 'Connection failed: '.substr($e->getMessage(), 0, 200),
            ];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            return [
                'configured' => true,
                'reachable' => true,
                'token_valid' => false,
                'url' => $url,
                'error' => 'Token rejected by '.$env.'. Regenerate or align PIPELINE_SYNC_TOKEN_'.strtoupper($env).'.',
            ];
        }

        if (! $response->successful()) {
            return [
                'configured' => true,
                'reachable' => true,
                'token_valid' => null,
                'url' => $url,
                'error' => 'Target returned HTTP '.$response->status().' — check target env logs.',
            ];
        }

        return [
            'configured' => true,
            'reachable' => true,
            'token_valid' => true,
            'url' => $url,
            'error' => null,
        ];
    }
}
