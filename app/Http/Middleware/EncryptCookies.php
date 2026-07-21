<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Awin's affiliate click reference — must reach their S2S endpoint as
        // the raw value captured at click time, unencrypted by Laravel.
        'awc',
        // SP3: mobile full-site pin cookie — must be readable as plaintext for
        // the RedirectPhoneToMobile middleware pin-check.
        'm_full_site',
    ];
}
