<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;

/**
 * Stage 1 — Hourly cron: poll fynla.org/sitemap.xml, find new /insights/ articles,
 * dispatch them through pipeline:process via the queue.
 */
class PollSitemap extends Command
{
    protected $signature = 'pipeline:poll-sitemap';
    protected $description = 'Poll sitemap.xml for new articles, queue them for processing';

    public function handle(SitemapService $sitemap): int
    {
        $newArticles = $sitemap->poll();

        if (empty($newArticles)) {
            $this->info('No new articles.');
            return self::SUCCESS;
        }

        foreach ($newArticles as $article) {
            $this->info("Queuing #{$article->id}: {$article->source_url}");
            // Dispatch as queued job — chunk 3 ships ProcessArticleJob;
            // for now run synchronously
            $this->call('pipeline:process', ['--article-id' => $article->id]);
        }

        return self::SUCCESS;
    }
}
