<?php

declare(strict_types=1);

namespace App\Services\Pipeline\Social;

use App\Models\Insights\InsightArticle;
use App\Models\Pipeline\PipelineCampaign;
use InvalidArgumentException;

/**
 * Builds tracked destination URLs for social posts. Rule 1 of the
 * social-media-posts skill: never post a plain fynla.org URL; every
 * link goes through here.
 *
 * URL pattern:
 *   {base}?utm_source={platform}&utm_medium=reel_9x16&utm_campaign={slug}&utm_content=clip-{n}
 *
 * Where:
 *  - platform: instagram | facebook | tiktok
 *  - utm_medium: fixed 'reel_9x16' for now (will extend when we add other
 *    formats — e.g. 'short_9x16', 'square_1x1')
 *  - utm_campaign: campaign->slug when a campaign is attached, else the
 *    article slug (keeps GA reporting granular per article)
 *  - utm_content: clip variant identifier — always 'clip-N' so we can
 *    A/B-compare which highlights convert
 */
class UtmLinkBuilder
{
    private const MEDIUM = 'reel_9x16';

    private const VALID_PLATFORMS = ['instagram', 'facebook', 'tiktok'];

    public function forArticle(InsightArticle $article, string $platform, int $clipIndex): string
    {
        return $this->build(
            baseUrl: $this->articleUrl($article->slug),
            platform: $platform,
            campaignSlug: $article->slug,
            clipIndex: $clipIndex,
        );
    }

    public function forCampaign(PipelineCampaign $campaign, string $platform, int $clipIndex): string
    {
        return $this->build(
            baseUrl: $campaign->landing_url,
            platform: $platform,
            campaignSlug: $campaign->slug,
            clipIndex: $clipIndex,
        );
    }

    public function forCustomUrl(string $url, string $platform, string $campaignSlug, int $clipIndex): string
    {
        return $this->build(
            baseUrl: $url,
            platform: $platform,
            campaignSlug: $campaignSlug,
            clipIndex: $clipIndex,
        );
    }

    private function build(string $baseUrl, string $platform, string $campaignSlug, int $clipIndex): string
    {
        if (! in_array($platform, self::VALID_PLATFORMS, true)) {
            throw new InvalidArgumentException("Unknown platform: {$platform}");
        }
        if ($clipIndex < 1) {
            throw new InvalidArgumentException("clipIndex must be >= 1, got {$clipIndex}");
        }

        $params = [
            'utm_source' => $platform,
            'utm_medium' => self::MEDIUM,
            'utm_campaign' => $campaignSlug,
            'utm_content' => 'clip-'.$clipIndex,
        ];

        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl.$separator.http_build_query($params);
    }

    private function articleUrl(string $slug): string
    {
        return rtrim((string) config('app.url', 'https://fynla.org'), '/').'/insights/'.$slug;
    }
}
