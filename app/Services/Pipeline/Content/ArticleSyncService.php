<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Content;

use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\ArticleSyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Cross-environment article publishing without a code deploy.
 *
 * Push flow (called from the admin CMS "Push to dev" / "Push to live" buttons):
 *   1. Package the article as a self-contained JSON payload.
 *   2. POST to the target env's /api/admin/pipeline/articles/sync-inbound
 *      endpoint with a shared secret header (X-Pipeline-Sync-Token).
 *   3. Target env's controller validates the token, upserts the article
 *      into its own DB, returns 200.
 *   4. Locally, stamp dev_synced_at or prod_synced_at + record the row
 *      in article_sync_logs.
 *
 * Env config (in local .env only — targets don't need these):
 *   PIPELINE_SYNC_URL_DEV   = https://csjones.co/fynla
 *   PIPELINE_SYNC_URL_PROD  = https://fynla.org
 *   PIPELINE_SYNC_TOKEN_DEV = <shared secret matched by dev's env>
 *   PIPELINE_SYNC_TOKEN_PROD= <shared secret matched by prod's env>
 *
 * On each target env's .env: PIPELINE_SYNC_TOKEN = <same shared secret>.
 */
class ArticleSyncService
{
    public function push(InsightArticle $article, string $targetEnv, ?int $actorId = null): void
    {
        $this->assertEnv($targetEnv);

        [$url, $token] = $this->targetCredentials($targetEnv);

        $payload = $this->buildPayload($article);
        $payloadHash = hash('sha256', json_encode($payload));

        $response = Http::withHeaders([
            'X-Pipeline-Sync-Token' => $token,
            'X-Pipeline-Sync-Env' => app()->environment(),
            'X-Pipeline-Sync-Payload-Hash' => $payloadHash,
        ])
            ->timeout(60)
            ->post(rtrim($url, '/').'/api/admin/pipeline/articles/sync-inbound', $payload);

        ArticleSyncLog::create([
            'insight_article_id' => $article->id,
            'actor_id' => $actorId,
            'target_env' => $targetEnv,
            'action' => 'sync',
            'status' => $response->successful() ? 'success' : 'error',
            'payload_hash' => $payloadHash,
            'response_status' => $response->status(),
            'response_body' => $response->successful() ? null : substr($response->body(), 0, 2000),
            'metadata' => [
                'block_count' => count($payload['body_blocks'] ?? []),
                'target_url' => $url,
            ],
        ]);

        if (! $response->successful()) {
            Log::channel('pipeline')->error('Cross-env sync failed.', [
                'insight_article_id' => $article->id,
                'target_env' => $targetEnv,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException(
                "Sync to {$targetEnv} failed: HTTP {$response->status()} — ".substr($response->body(), 0, 200)
            );
        }

        $now = now();
        if ($targetEnv === 'dev') {
            $article->update(['dev_synced_at' => $now]);
        } elseif ($targetEnv === 'prod') {
            $article->update([
                'prod_synced_at' => $now,
                'prod_pushed_by' => $actorId,
            ]);
        }
    }

    /**
     * Consume a sync payload sent from another env. Returns the local
     * InsightArticle after upsert.
     *
     * Called by the sync-inbound controller (has no local pipeline_articles
     * or pipeline_campaigns — just the article itself). Campaign linkage
     * is dropped on cross-env sync because campaign IDs don't match across
     * envs; article's campaign_slug metadata is preserved on the payload
     * for future re-linking if needed.
     *
     * @param  array<string,mixed>  $payload
     */
    public function receive(array $payload): InsightArticle
    {
        $slug = (string) ($payload['slug'] ?? '');
        if ($slug === '') {
            throw new RuntimeException('sync payload missing slug.');
        }

        $article = InsightArticle::withTrashed()->where('slug', $slug)->first();

        $fill = [
            'slug' => $slug,
            'title' => (string) ($payload['title'] ?? ''),
            'subtitle' => $payload['subtitle'] ?? null,
            'summary' => (string) ($payload['summary'] ?? ''),
            'category' => $payload['category'] ?? 'financial-planning',
            'tags' => (array) ($payload['tags'] ?? []),
            'body_blocks' => (array) ($payload['body_blocks'] ?? []),
            'status' => $payload['status'] ?? 'published',
            'is_featured' => (bool) ($payload['is_featured'] ?? false),
            'meta_title' => $payload['meta_title'] ?? null,
            'meta_description' => $payload['meta_description'] ?? null,
            'published_at' => $payload['published_at'] ?? now(),
            'source_docx_drive_file_id' => $payload['source_docx_drive_file_id'] ?? null,
            'source_docx_drive_url' => $payload['source_docx_drive_url'] ?? null,
            'source_docx_hash' => $payload['source_docx_hash'] ?? null,
        ];

        if ($article === null) {
            $article = InsightArticle::create($fill);
        } else {
            if ($article->trashed()) {
                $article->restore();
            }
            $article->update($fill);
        }

        ArticleSyncLog::create([
            'insight_article_id' => $article->id,
            'target_env' => 'local',
            'action' => 'sync',
            'status' => 'success',
            'payload_hash' => $payload['_payload_hash'] ?? null,
            'metadata' => [
                'received_from' => request()->header('X-Pipeline-Sync-Env'),
            ],
        ]);

        return $article;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildPayload(InsightArticle $article): array
    {
        $article->loadMissing('pipelineCampaign');

        return [
            'slug' => $article->slug,
            'title' => $article->title,
            'subtitle' => $article->subtitle,
            'summary' => $article->summary,
            'category' => $article->category,
            'tags' => $article->tags,
            'body_blocks' => $article->body_blocks,
            'status' => $article->status,
            'is_featured' => (bool) $article->is_featured,
            'meta_title' => $article->meta_title,
            'meta_description' => $article->meta_description,
            'published_at' => optional($article->published_at)->toIso8601String(),
            'source_docx_drive_file_id' => $article->source_docx_drive_file_id,
            'source_docx_drive_url' => $article->source_docx_drive_url,
            'source_docx_hash' => $article->source_docx_hash,
            'campaign_slug' => $article->pipelineCampaign?->slug,
        ];
    }

    private function assertEnv(string $env): void
    {
        if (! in_array($env, ['dev', 'prod'], true)) {
            throw new RuntimeException("Invalid target env: {$env} (must be dev or prod).");
        }
    }

    /**
     * @return array{0:string,1:string} [url, token]
     */
    private function targetCredentials(string $env): array
    {
        $urlKey = 'pipeline.sync.'.$env.'_url';
        $tokenKey = 'pipeline.sync.'.$env.'_token';

        $url = (string) config($urlKey);
        $token = (string) config($tokenKey);

        if ($url === '') {
            throw new RuntimeException(strtoupper($env).' target URL not set (PIPELINE_SYNC_URL_'.strtoupper($env).').');
        }
        if ($token === '') {
            throw new RuntimeException(strtoupper($env).' sync token not set (PIPELINE_SYNC_TOKEN_'.strtoupper($env).').');
        }

        return [$url, $token];
    }
}
