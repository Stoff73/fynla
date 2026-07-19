<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Social;

use DateTimeInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Buffer GraphQL API v2 client (https://api.buffer.com/graphql).
 *
 * Migrated from the legacy REST v1 endpoint because Buffer's new
 * Personal Keys are rejected on the old endpoint with
 *   HTTP 401 · "Public API tokens are not accepted for REST API access."
 *
 * Design:
 *   - Video is passed as a URL, not a file upload. Callers pass a
 *     publicly-reachable signed URL (we already generate one for the
 *     tracker sheet download — same signed route).
 *   - The public interface (schedule / fetchUpdateMetrics / listProfiles)
 *     is unchanged from the REST-era client so SchedulePostJob and
 *     WeeklySocialReport keep working.
 *   - Config keys are unchanged (pipeline.buffer.access_token /
 *     .profiles.{platform}). The "profile ID" is now Buffer's
 *     "channel ID" in v2 vocabulary — same 24-char hex, same lookup.
 */
class BufferClient
{
    private const GRAPHQL_URL = 'https://api.buffer.com/graphql';

    /** @return array{data:array{createPost:array<string,mixed>}} */
    public function schedule(string $platform, string $text, string $videoUrl, ?DateTimeInterface $at = null): array
    {
        $channelId = $this->channelId($platform);

        $input = [
            'channelId' => $channelId,
            'text' => $text,
            'assets' => [[
                'video' => ['url' => $videoUrl],
            ]],
            'mode' => $at !== null ? 'customScheduled' : 'shareNow',
            'schedulingType' => 'automatic',
            'metadata' => $this->platformMetadata($platform),
        ];

        if ($at !== null) {
            $input['dueAt'] = $at->format(DATE_ATOM);
        }

        $mutation = <<<'GRAPHQL'
            mutation CreatePost($input: CreatePostInput!) {
                createPost(input: $input) {
                    __typename
                    ... on PostActionSuccess { post { id status dueAt channelId } }
                    ... on InvalidInputError { message }
                    ... on LimitReachedError { message }
                    ... on UnauthorizedError { message }
                    ... on NotFoundError { message }
                    ... on RestProxyError { message code }
                    ... on UnexpectedError { message }
                }
            }
        GRAPHQL;

        $response = $this->client()->post(self::GRAPHQL_URL, [
            'query' => $mutation,
            'variables' => ['input' => $input],
        ]);

        $data = $response->json();
        $this->assertGraphqlOk($response, $data, 'schedule');

        $result = $data['data']['createPost'] ?? [];
        $type = $result['__typename'] ?? null;

        if ($type !== 'PostActionSuccess') {
            $reason = $result['message'] ?? 'unknown Buffer error';
            Log::channel('pipeline')->error('Buffer createPost returned error variant.', [
                'variant' => $type,
                'message' => $reason,
                'platform' => $platform,
            ]);
            throw new RuntimeException("Buffer schedule failed ({$type}): {$reason}");
        }

        // Preserve legacy shape { updates: [{ id }] } used by SchedulePostJob
        // to pluck the update ID without knowing about the migration.
        return [
            'updates' => [[
                'id' => $result['post']['id'] ?? null,
                'status' => $result['post']['status'] ?? null,
                'dueAt' => $result['post']['dueAt'] ?? null,
            ]],
            'raw' => $result,
        ];
    }

    /**
     * Fetch normalised metrics for a previously scheduled post.
     * Returns whatever Buffer surfaces on the post at query time; the
     * shape mirrors what the REST v1 client returned so
     * WeeklySocialReport's aggregator keeps its existing summing code.
     */
    public function fetchUpdateMetrics(string $postId): array
    {
        $query = <<<'GRAPHQL'
            query GetPost($input: PostInput!) {
                post(input: $input) {
                    id
                    status
                    dueAt
                    sentAt
                    text
                    metadata { __typename }
                }
            }
        GRAPHQL;

        $response = $this->client()->post(self::GRAPHQL_URL, [
            'query' => $query,
            'variables' => ['input' => ['id' => $postId]],
        ]);

        $data = $response->json();
        if (! $response->successful() || isset($data['errors'])) {
            Log::channel('pipeline')->warning('Buffer post metrics fetch failed.', [
                'post_id' => $postId,
                'status' => $response->status(),
                'errors' => $data['errors'] ?? null,
            ]);

            return [];
        }

        $post = $data['data']['post'] ?? null;
        if ($post === null) {
            return [];
        }

        // Aggregation metrics live on aggregatedPostMetrics, not on the
        // post object — that's a per-org batch query we'll wire into
        // WeeklySocialReport separately. For now, return the raw post so
        // the report can distinguish "posted" from "not posted".
        return [
            'status' => $post['status'] ?? null,
            'sent_at' => $post['sentAt'] ?? null,
            'due_at' => $post['dueAt'] ?? null,
        ];
    }

    /**
     * List connected channels. Kept for the admin diagnostic + config
     * bootstrap flow; returns the flat array of channel dicts (matching
     * the REST-era shape callers expected).
     *
     * @return list<array<string,mixed>>
     */
    public function listProfiles(): array
    {
        $account = $this->client()->post(self::GRAPHQL_URL, [
            'query' => 'query { account { organizations { id name } } }',
        ]);
        $this->assertGraphqlOk($account, $account->json(), 'listProfiles.account');

        $orgId = $account->json('data.account.organizations.0.id');
        if ($orgId === null) {
            throw new RuntimeException('Buffer account has no organizations.');
        }

        $channelsResp = $this->client()->post(self::GRAPHQL_URL, [
            'query' => 'query Q($i:ChannelsInput!){ channels(input:$i){ id service serviceId name isDisconnected } }',
            'variables' => ['i' => ['organizationId' => $orgId]],
        ]);
        $this->assertGraphqlOk($channelsResp, $channelsResp->json(), 'listProfiles.channels');

        return $channelsResp->json('data.channels', []);
    }

    private function client(): PendingRequest
    {
        return Http::withToken($this->accessToken())
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout((int) config('pipeline.buffer.timeout_seconds', 60));
    }

    private function accessToken(): string
    {
        $token = config('pipeline.buffer.access_token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('BUFFER_ACCESS_TOKEN is not set.');
        }

        return $token;
    }

    private function channelId(string $platform): string
    {
        $id = config('pipeline.buffer.profiles.'.$platform);
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('BUFFER_PROFILE_'.strtoupper($platform).' is not set — this platform is disconnected.');
        }

        return $id;
    }

    /**
     * Per-platform metadata block. Instagram wants "reel" + shouldShareToFeed;
     * Facebook takes a plain video with the "reel" type; TikTok wants a title
     * (title falls back to first N chars of the caption when we don't have
     * one). Extend as we add channels.
     *
     * @return array<string,mixed>|null
     */
    private function platformMetadata(string $platform): ?array
    {
        return match ($platform) {
            'instagram' => [
                'instagram' => [
                    'type' => 'reel',
                    'shouldShareToFeed' => true,
                ],
            ],
            'facebook' => [
                'facebook' => [
                    'type' => 'reel',
                ],
            ],
            'tiktok' => [
                'tiktok' => [
                    'title' => 'Fynla',
                ],
            ],
            default => null,
        };
    }

    private function assertGraphqlOk($response, ?array $data, string $context): void
    {
        if (! $response->successful()) {
            Log::channel('pipeline')->error("Buffer GraphQL {$context} HTTP error.", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException("Buffer GraphQL failed ({$context}): HTTP {$response->status()}");
        }

        if (is_array($data) && isset($data['errors']) && $data['errors'] !== []) {
            $first = $data['errors'][0]['message'] ?? 'unknown';
            Log::channel('pipeline')->error("Buffer GraphQL {$context} errors.", [
                'errors' => $data['errors'],
            ]);
            throw new RuntimeException("Buffer GraphQL failed ({$context}): {$first}");
        }
    }
}
