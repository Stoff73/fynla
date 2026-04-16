<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Awin Integration Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch. When false, no MasterTag is loaded, no conversion events
    | fire, no server-to-server calls are made, and the awc cookie capture
    | middleware short-circuits. Keep false in local/staging unless you are
    | actively running an attribution test.
    |
    */

    'enabled' => env('AWIN_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Merchant ID
    |--------------------------------------------------------------------------
    |
    | Fynla's advertiser ID issued by Awin. Embedded in the MasterTag URL,
    | the fallback pixel URL, and every server-to-server call.
    |
    */

    'merchant_id' => env('AWIN_MERCHANT_ID', '126105'),

    /*
    |--------------------------------------------------------------------------
    | URLs
    |--------------------------------------------------------------------------
    |
    | Awin endpoints. The MasterTag URL is served from dwin1.com (their
    | first-party tracking host); the S2S and fallback pixel URLs are on
    | awin1.com. Overridable via env for test/QA endpoints.
    |
    */

    'master_tag_url' => env('AWIN_MASTER_TAG_URL', 'https://www.dwin1.com/126105.js'),
    's2s_base_url' => env('AWIN_S2S_BASE_URL', 'https://www.awin1.com/sread.php'),
    'fallback_pixel_base' => env('AWIN_FALLBACK_PIXEL_BASE', 'https://www.awin1.com/sread.img'),

    /*
    |--------------------------------------------------------------------------
    | Commission Group
    |--------------------------------------------------------------------------
    |
    | Awin commission groups let merchants pay different rates for different
    | product categories. For v1, Fynla uses a single group for all
    | subscription tiers. To add per-tier groups later, extend
    | AwinTrackingService::commissionGroupFor().
    |
    */

    'default_commission_group' => env('AWIN_DEFAULT_COMMISSION_GROUP', 'SUB'),

    /*
    |--------------------------------------------------------------------------
    | Click Cookie Settings
    |--------------------------------------------------------------------------
    |
    | The awc click cookie is set when a user arrives via an affiliate link
    | with ?awc=... in the query string. Domain must match the production
    | host (fynla.org in prod, csjones.co in dev). Lifetime is 365 days per
    | Awin's attribution window.
    |
    */

    'cookie_domain' => env('AWIN_COOKIE_DOMAIN', 'fynla.org'),
    'cookie_lifetime_days' => 365,

    /*
    |--------------------------------------------------------------------------
    | HTTP Timeouts
    |--------------------------------------------------------------------------
    |
    | Bounds on the S2S call. Both connect and total timeouts are set to the
    | same value so a slow Awin response cannot block the queue worker. The
    | job layer handles retries; the HTTP client does not.
    |
    */

    'http_timeout_seconds' => (int) env('AWIN_HTTP_TIMEOUT_SECONDS', 3),

    /*
    |--------------------------------------------------------------------------
    | Excluded Routes
    |--------------------------------------------------------------------------
    |
    | Vue route names where the MasterTag must NOT load. Primarily the
    | checkout page (Revolut embedded widget) — Awin's own guidance is to
    | never place their tag on pages that display or process sensitive
    | payment information.
    |
    */

    'excluded_routes' => [
        'checkout',
        'auth.checkout',
        'payment.confirm',
    ],

];
