<?php

declare(strict_types=1);

namespace App\Providers;

use Anthropic\Client;
use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use App\Models\RecommendationTracking;
use App\Observers\DocumentArticleObserver;
use App\Observers\InsightArticleObserver;
use App\Observers\RecommendationTrackingObserver;
use App\Services\AI\AdviceFyn;
use App\Services\AI\XaiClient;
use App\Services\Gamification\LevelUpCollector;
use App\Services\Lifecycle\LifecycleDiscountCodeGenerator;
use App\Services\Lifecycle\LifecycleEngine;
use App\Services\Lifecycle\LifecycleSnapshotService;
use App\Services\Plans\PlanConfigService;
use App\Services\Stores\TierGate;
use App\Services\TaxConfigService;
use App\Services\Tiers\DbTierGate;
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

        // Request-scoped collector that surfaces in-turn gamification level-ups
        // to the SSE/API layer (one instance per request).
        $this->app->scoped(LevelUpCollector::class);

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

        // TierGate — SP2: DB-backed, admin-editable, defence-in-depth
        $this->app->bind(
            TierGate::class,
            DbTierGate::class
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
        $this->assertDatabaseCredentialsLoaded();

        // Prevent lazy loading in non-production environments to catch N+1 query issues
        Model::preventLazyLoading(! app()->isProduction());

        InsightArticle::observe(InsightArticleObserver::class);
        DocumentArticle::observe(DocumentArticleObserver::class);
        RecommendationTracking::observe(RecommendationTrackingObserver::class);
    }

    /**
     * Fail fast and loudly if the production DB username resolved to the
     * framework default ('forge'). That only happens when the .env failed to
     * load for the request (e.g. config not cached on shared hosting), which
     * otherwise surfaces as a cryptic, intermittent "Access denied for user
     * 'forge'@'localhost' (using password: NO)" 500 on every authenticated
     * request — silently breaking auth, checkout, and everything DB-backed.
     * Caching config ("php artisan config:cache") prevents the fallback; this
     * guard makes any recurrence instantly diagnosable instead of buried.
     */
    private function assertDatabaseCredentialsLoaded(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $default = config('database.default');
        $username = config("database.connections.{$default}.username");

        if ($username === 'forge') {
            throw new \RuntimeException(
                "Database credentials failed to load: DB_USERNAME resolved to the default 'forge'. "
                .'The .env was not loaded for this request. Run "php artisan config:cache" so the '
                .'real credentials are baked into the cached config and can never fall back to defaults.'
            );
        }
    }
}
