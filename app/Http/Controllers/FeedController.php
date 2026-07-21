<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Insights\InsightArticle;
use App\Models\News\NewsArticle;
use Illuminate\Database\QueryException;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class FeedController extends Controller
{
    /**
     * RSS 2.0 feed of published news articles.
     * Public endpoint at /feed/news.xml — no auth.
     */
    public function news(): Response
    {
        $articles = NewsArticle::published()
            ->orderByDesc('published_at')
            ->take(50)
            ->get();

        $items = $articles->map(fn (NewsArticle $a) => [
            'title' => $a->title,
            'link' => url("/news/{$a->slug}"),
            'guid' => url("/news/{$a->slug}"),
            'description' => $a->summary,
            'pubDate' => optional($a->published_at)->toRssString(),
            'author' => $a->author_name,
        ]);

        return $this->renderRss(
            channelTitle: 'Fynla News',
            channelLink: url('/news'),
            channelDescription: 'Announcements, product updates and stories from the Fynla team.',
            channelFeedUrl: url('/feed/news.xml'),
            items: $items->all(),
            lastBuildDate: $articles->first()?->published_at?->toRssString(),
        );
    }

    /**
     * RSS 2.0 feed of published insight articles.
     * Public endpoint at /feed/insights.xml — no auth.
     */
    public function insights(): Response
    {
        // Wrap in try/catch — local environments may not yet have the
        // insight_articles table (pending migration). Production has it.
        try {
            $articles = InsightArticle::published()
                ->orderByDesc('published_at')
                ->take(50)
                ->get();
        } catch (QueryException) {
            $articles = new Collection;
        }

        $items = $articles->map(fn (InsightArticle $a) => [
            'title' => $a->title,
            'link' => url("/insights/{$a->slug}"),
            'guid' => url("/insights/{$a->slug}"),
            'description' => $a->summary,
            'pubDate' => optional($a->published_at)->toRssString(),
            'author' => $this->insightAuthorLabel($a),
        ]);

        return $this->renderRss(
            channelTitle: 'Fynla Insights',
            channelLink: url('/insights'),
            channelDescription: 'UK financial planning insights — pensions, ISAs, tax, estate planning and more.',
            channelFeedUrl: url('/feed/insights.xml'),
            items: $items->all(),
            lastBuildDate: $articles->first()?->published_at?->toRssString(),
        );
    }

    /**
     * Resolve the author label for an insight article. The model has both a
     * single `author_id` relation (admin who created it) and a multi-author
     * `authors` JSON array (the editorial byline shown to readers). Use the
     * array when populated; otherwise fall back to the team byline.
     *
     * We deliberately do NOT touch the `author` BelongsTo here — the app has
     * Model::preventLazyLoading() enabled, and we don't want every feed render
     * to require an N+1-aware eager load just for a fallback that the dev/prod
     * data rarely uses. Authors who want a personal byline set `authors`.
     */
    private function insightAuthorLabel(InsightArticle $article): string
    {
        if (is_array($article->authors) && count($article->authors) > 0) {
            return implode(', ', $article->authors);
        }

        return 'The Fynla Team';
    }

    /**
     * @param  array<int,array<string,?string>>  $items
     */
    private function renderRss(
        string $channelTitle,
        string $channelLink,
        string $channelDescription,
        string $channelFeedUrl,
        array $items,
        ?string $lastBuildDate,
    ): Response {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">'."\n";
        $xml .= "  <channel>\n";
        $xml .= '    <title>'.e($channelTitle)."</title>\n";
        $xml .= '    <link>'.e($channelLink)."</link>\n";
        $xml .= '    <description>'.e($channelDescription)."</description>\n";
        $xml .= "    <language>en-gb</language>\n";
        $xml .= '    <atom:link href="'.e($channelFeedUrl).'" rel="self" type="application/rss+xml" />'."\n";

        if ($lastBuildDate) {
            $xml .= '    <lastBuildDate>'.e($lastBuildDate)."</lastBuildDate>\n";
        }

        foreach ($items as $item) {
            $xml .= "    <item>\n";
            $xml .= '      <title>'.e((string) ($item['title'] ?? ''))."</title>\n";
            $xml .= '      <link>'.e((string) ($item['link'] ?? ''))."</link>\n";
            $xml .= '      <guid isPermaLink="true">'.e((string) ($item['guid'] ?? ''))."</guid>\n";
            $xml .= '      <description><![CDATA['.($item['description'] ?? '')."]]></description>\n";

            if (! empty($item['pubDate'])) {
                $xml .= '      <pubDate>'.e((string) $item['pubDate'])."</pubDate>\n";
            }
            if (! empty($item['author'])) {
                $xml .= '      <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">'.e((string) $item['author'])."</dc:creator>\n";
            }

            $xml .= "    </item>\n";
        }

        $xml .= "  </channel>\n";
        $xml .= "</rss>\n";

        return response($xml, 200, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=600',
        ]);
    }
}
