<?php

declare(strict_types=1);

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
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

        // Anthropic SDK client singleton
        $this->app->singleton(AnthropicClient::class, function () {
            $apiKey = config('services.anthropic.api_key');

            if (empty($apiKey)) {
                throw new \RuntimeException('ANTHROPIC_API_KEY is not configured.');
            }

            return new AnthropicClient(apiKey: $apiKey);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent lazy loading in non-production environments to catch N+1 query issues
        Model::preventLazyLoading(! app()->isProduction());
    }
}
