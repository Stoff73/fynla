<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Phone → /m scaffold redirect
    |--------------------------------------------------------------------------
    |
    | When enabled, App\Http\Middleware\RedirectPhoneToMobile 302s phone-form-
    | factor web visitors to the dedicated SP3 mobile surface (/m). This was the
    | original SP3 behaviour while the public site was desktop-only.
    |
    | The public marketing site (homepage, savetax, etc.) is now fully
    | responsive, so phones get a first-class landing experience without the
    | iframe scaffold. Default OFF: phones see the responsive public site. The
    | isolated mobile SPA at /m/app remains reachable directly. Flip to true
    | (MOBILE_PHONE_REDIRECT=true) to restore the scaffold redirect.
    |
    */
    'phone_redirect' => env('MOBILE_PHONE_REDIRECT', false),
];
