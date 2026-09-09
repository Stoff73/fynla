<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Consent re-check interval (seconds)
    |--------------------------------------------------------------------------
    |
    | How often a streaming turn re-checks ai_chat consent mid-stream. Tests set
    | it to 0 to assert per-event behaviour. Was read with a bare default before
    | this file existed (F9).
    */
    'consent_recheck_interval_seconds' => (float) env('AI_CHAT_CONSENT_RECHECK_SECONDS', 2.0),
];
