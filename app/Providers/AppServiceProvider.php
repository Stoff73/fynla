<?php

declare(strict_types=1);

namespace App\Providers;

use Anthropic\Client;
use App\Models\DocumentArticle;
use App\Models\Insights\InsightArticle;
use App\Observers\DocumentArticleObserver;
use App\Observers\InsightArticleObserver;
use App\Services\AI\AdviceFyn;
use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use App\Services\AI\Memory\Episodic\SemanticSnapshotHolder;
use App\Services\AI\Memory\Procedural\ProceduralContributionCollector;
use App\Services\AI\Memory\Procedural\ProceduralCorpusLoader;
use App\Services\AI\Pointers\FetchDispatcher;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\Handlers\RecommendationHandler;
use App\Services\AI\Pointers\Handlers\TaxAllowanceHandler;
use App\Services\AI\Pointers\Handlers\UserFinancialHandler;
use App\Services\AI\Pointers\PointerRegistry;
use App\Services\AI\XaiClient;
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

        // CoALA pointer registry — FetchHandlerRegistry has no zero-arg constructor so
        // it must be bound explicitly with its three proof handlers injected. PointerRegistry
        // and FetchDispatcher each depend on FetchHandlerRegistry and auto-wire once it is
        // resolvable, so plain singleton() calls are sufficient for them.
        $this->app->singleton(FetchHandlerRegistry::class, function ($app) {
            return new FetchHandlerRegistry([
                $app->make(TaxAllowanceHandler::class),
                $app->make(UserFinancialHandler::class),
                $app->make(RecommendationHandler::class),
            ]);
        });

        $this->app->singleton(PointerRegistry::class);
        // FetchDispatcher depends on the request-scoped FetchProvenanceCollector,
        // so it must re-resolve per request (a singleton would capture a stale collector).
        $this->app->bind(FetchDispatcher::class);

        // Request-scoped provenance accumulator — one instance per request, reset per turn.
        $this->app->scoped(FetchProvenanceCollector::class);

        // Request-scoped semantic-snapshot holder — assembler stamps, persistEpisode reads.
        $this->app->scoped(SemanticSnapshotHolder::class);

        // Request-scoped procedural-contribution accumulator (Phase 4c) — the
        // assembler records overlay/fca_block procedures it injected; Phase 4e
        // reads it at persistEpisode time. One instance per request, reset per turn.
        $this->app->scoped(ProceduralContributionCollector::class);

        // Request-scoped procedural-version holder (Phase 4e) — the tool-schema
        // assembler (4b), prompt-overlay assembler (4c) and onboarding director
        // (4d) record each active procedure_id@version they resolved; Phase 4e
        // reads it at persistEpisode time and stamps it onto the episode blob,
        // the ai_messages.procedural_version column and the audit attestation.
        // One instance per request, reset per turn alongside the holders above.
        $this->app->scoped(ProceduralVersionHolder::class);

        // Procedural corpus loader — singleton so the in-memory corpus + 60s
        // re-stat throttle persist within a request (and across requests under Octane).
        $this->app->singleton(ProceduralCorpusLoader::class);

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
