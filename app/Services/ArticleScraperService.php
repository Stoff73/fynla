<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Article;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\Readability;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Process\Process;

/**
 * Stage 1.5 — Fetches article HTML, extracts main body.
 *
 * Strategy:
 *  1. Try plain HTTP fetch first (fast, works on static/SSR pages)
 *  2. If body extraction yields < 200 chars, fall back to Playwright
 *     (renders JS, handles SPAs)
 *  3. Run multiple extraction strategies on the rendered HTML
 *  4. Fail loud if final body < 200 chars
 */
class ArticleScraperService
{
    public function scrape(Article $article): Article
    {
        Log::channel('pipeline')->info("Scraping: {$article->source_url}");

        // Step 1 — fast plain HTTP fetch
        $html = $this->fetchPlain($article->source_url);
        [$title, $body] = $this->extractContent($html, $article->source_url);

        // Step 2 — if too short, fall back to Playwright
        if (strlen($body) < 200) {
            Log::channel('pipeline')->info('Plain fetch yielded only '.strlen($body).' chars. Falling back to Playwright.');
            $html = $this->fetchWithPlaywright($article->source_url);
            [$title, $body] = $this->extractContent($html, $article->source_url);
        }

        if (strlen($body) < 200) {
            Log::channel('pipeline')->error("All strategies failed for {$article->source_url} ({$body})");
            throw new \Exception('Could not extract article body. Site markup may need a custom selector.');
        }

        $article->update([
            'title' => $title,
            'body' => $body,
            'status' => 'scraped',
        ]);

        Log::channel('pipeline')->info("Scraped \"{$title}\" (".strlen($body).' chars)');

        return $article->fresh();
    }

    private function fetchPlain(string $url): string
    {
        $r = Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; FynlaPipeline/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en-GB,en;q=0.9',
            ])
            ->get($url);
        if (! $r->successful()) {
            throw new \Exception("Plain fetch failed: HTTP {$r->status()}");
        }

        return $r->body();
    }

    private function fetchWithPlaywright(string $url): string
    {
        $process = new Process([
            'python3',
            base_path('scripts/render_page.py'),
            $url,
            '--wait-ms=3000',
        ]);
        $process->setTimeout(60);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \Exception('Playwright render failed: '.$process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /** @return array{0: ?string, 1: string} [title, body] */
    private function extractContent(string $html, string $sourceUrl): array
    {
        // Strategy 1 — Readability.php
        try {
            $r = new Readability(new Configuration);
            $r->parse($html);
            $body = $this->cleanText($r->getContent() ?? '');
            $title = $r->getTitle();
            if (strlen($body) >= 200) {
                Log::channel('pipeline')->info('Readability: '.strlen($body).' chars');

                return [$title, $body];
            }
        } catch (\Throwable $e) {
            Log::channel('pipeline')->warning("Readability failed: {$e->getMessage()}");
        }

        // Strategy 2 — common article selectors
        $crawler = new Crawler($html, $sourceUrl);
        $title = $crawler->filter('title')->count() ? trim($crawler->filter('title')->text()) : null;
        if (! $title && $crawler->filter('h1')->count()) {
            $title = trim($crawler->filter('h1')->first()->text());
        }

        $selectors = [
            // Fynla-specific (preferred — matches the SSR markup we agreed)
            'article.insight-body',
            '.insight-body',
            '.insight-content',
            '[itemtype="https://schema.org/Article"]',
            // Generic fallbacks
            'article', 'main', '[role="main"]',
            '.entry-content', '.post-content', '.article-content',
            '.article-body', '.post-body',
            '#content', '#main-content', '#app',
        ];

        foreach ($selectors as $sel) {
            try {
                $node = $crawler->filter($sel)->first();
                if ($node->count() > 0) {
                    $body = $this->cleanText($node->html());
                    if (strlen($body) >= 200) {
                        Log::channel('pipeline')->info("Selector '{$sel}': ".strlen($body).' chars');

                        return [$title, $body];
                    }
                }
            } catch (\Throwable $e) {
                continue;
            }
        }

        // Strategy 3 — strip nav/footer/script and take body
        try {
            $stripped = preg_replace('/<(nav|header|footer|script|style|aside|noscript)\b[^>]*>.*?<\/\1>/si', '', $html);
            $crawler2 = new Crawler($stripped);
            if ($crawler2->filter('body')->count()) {
                $body = $this->cleanText($crawler2->filter('body')->html());
                Log::channel('pipeline')->info('Body fallback: '.strlen($body).' chars');

                return [$title, $body];
            }
        } catch (\Throwable $e) {
            Log::channel('pipeline')->warning("Body fallback: {$e->getMessage()}");
        }

        return [$title ?? null, ''];
    }

    private function cleanText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
