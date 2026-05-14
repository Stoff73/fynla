<?php

declare(strict_types=1);

namespace App\Providers;

use Anthropic\Client;
use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use App\Observers\DocumentArticleObserver;
use App\Observers\InsightArticleObserver;
use App\Services\AI\AdviceFyn;
use App\Services\AI\XaiClient;
use App\Services\Lifecycle\LifecycleDiscountCodeGenerator;
use App\Services\Lifecycle\LifecycleEngine;
use App\Services\Lifecycle\LifecycleSnapshotService;
use App\Services\Plans\PlanConfigService;
use App\Services\Stores\PermissiveTierGate;
use App\Services\Stores\TierGate;
use App\Services\TaxConfigService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-scoped singletons for tax + plan configuration. Both services
        // load active config from the database on first call and cache it on the
        // instance, so resolving a fresh instance per injection causes every
        // agent that takes one as a constructor dep to re-run the lookup.
        $this->app->scoped(TaxConfigService::class);
        $this->app->scoped(PlanConfigService::class);

        // Register both AI client singletons — runtime provider selection happens
        // in HasAiChat/HasAiGuardrails via cache check (admin toggle)
        $this->app->singleton(XaiClient::class);

        // Two-Fyn architecture — AdviceFyn is a stateless post-onboarding
        // dispatcher with a read-only tool list. Singleton-scoped so its
        // constructor dependencies (AiToolDefinitions + XaiToolDefinitions)
        // resolve once per request.
        $this->app->singleton(AdviceFyn::class);

        if (class_exists(Client::class)) {
            $this->app->singleton(Client::class, function () {
                $apiKey = config('services.anthropic.api_key');

                if (empty($apiKey)) {
                    throw new \RuntimeException('ANTHROPIC_API_KEY is not configured.');
                }

                return new Client(apiKey: $apiKey);
            });
        }

        // TierGate — permissive default until sub-project 2 supplies the real impl
        $this->app->bind(
            TierGate::class,
            PermissiveTierGate::class
        );

        // LifecycleEngine is a singleton so its per-run caches
        // (trialAfterEndCandidates, cachedHasDataIds) are shared across every
        // campaign resolved from config during a single engine run. Without
        // the singleton, Laravel would construct a fresh engine each time a
        // campaign's constructor asks for one, defeating the cache and
        // re-running the expensive candidate query N times per run.
        $this->app->singleton(LifecycleEngine::class, function ($app) {
            return new LifecycleEngine(
                snapshotService: $app->make(LifecycleSnapshotService::class),
                discountGenerator: $app->make(LifecycleDiscountCodeGenerator::class),
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
        DocumentArticle::observe(DocumentArticleObserver::class);
    }
}
