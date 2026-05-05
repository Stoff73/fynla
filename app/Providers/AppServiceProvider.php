<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Insights\InsightArticle;
use App\Observers\InsightArticleObserver;
use App\Services\Plans\PlanConfigService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-scoped singleton for plan configuration (same pattern as TaxConfigService)
        $this->app->scoped(PlanConfigService::class);

        // Register both AI client singletons — runtime provider selection happens
        // in HasAiChat/HasAiGuardrails via cache check (admin toggle)
        $this->app->singleton(\App\Services\AI\XaiClient::class);

        if (class_exists(\Anthropic\Client::class)) {
            $this->app->singleton(\Anthropic\Client::class, function () {
                $apiKey = config('services.anthropic.api_key');

                if (empty($apiKey)) {
                    throw new \RuntimeException('ANTHROPIC_API_KEY is not configured.');
                }

                return new \Anthropic\Client(apiKey: $apiKey);
            });
        }

        // LifecycleEngine is a singleton so its per-run caches
        // (trialAfterEndCandidates, cachedHasDataIds) are shared across every
        // campaign resolved from config during a single engine run. Without
        // the singleton, Laravel would construct a fresh engine each time a
        // campaign's constructor asks for one, defeating the cache and
        // re-running the expensive candidate query N times per run.
        $this->app->singleton(\App\Services\Lifecycle\LifecycleEngine::class, function ($app) {
            return new \App\Services\Lifecycle\LifecycleEngine(
                snapshotService: $app->make(\App\Services\Lifecycle\LifecycleSnapshotService::class),
                discountGenerator: $app->make(\App\Services\Lifecycle\LifecycleDiscountCodeGenerator::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent lazy loading in non-production environments to catch N+1 query issues
        Model::preventLazyLoading(! app()->isProduction());

        InsightArticle::observe(InsightArticleObserver::class);
    }
}
