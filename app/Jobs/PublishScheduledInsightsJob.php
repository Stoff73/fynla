<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Insights\InsightArticle;
use App\Services\Insights\InsightArticleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledInsightsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $service = app(InsightArticleService::class);

        InsightArticle::scheduledDue()->each(function (InsightArticle $article) use ($service): void {
            $service->publish($article);
        });
    }
}
