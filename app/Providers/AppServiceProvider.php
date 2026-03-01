<?php

declare(strict_types=1);

namespace App\Providers;

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
