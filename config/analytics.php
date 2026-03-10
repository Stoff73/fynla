<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics Enabled
    |--------------------------------------------------------------------------
    |
    | Controls whether the Plausible analytics script is loaded. Set to true
    | in production to enable privacy-first analytics. Disabled by default
    | for local development and testing environments.
    |
    */

    'enabled' => env('ANALYTICS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Plausible Domain
    |--------------------------------------------------------------------------
    |
    | The domain registered with Plausible Cloud. This must match the site
    | domain configured in your Plausible dashboard.
    |
    */

    'plausible_domain' => env('PLAUSIBLE_DOMAIN', ''),

];
