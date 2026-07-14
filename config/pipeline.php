<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Pipeline Feature Flag
    |--------------------------------------------------------------------------
    |
    | Master switch for the marketing pipeline. When false, the
    | `pipeline:detect-new-articles` command exits without dispatching any
    | work and the daily scheduler entry is a no-op. Set to true only after
    | Google Drive credentials, Anthropic Opus, and downstream stages are
    | wired up.
    |
    */

    'enabled' => env('PIPELINE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Detect New Articles — Schedule
    |--------------------------------------------------------------------------
    |
    | Time of day (HH:MM, server timezone) the daily detect command runs.
    |
    */

    'detect_schedule' => env('PIPELINE_DETECT_SCHEDULE', '07:00'),

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    |
    | Dedicated queue so pipeline work can be paused/scaled independently of
    | the default queue that handles user-facing email, notifications, etc.
    |
    */

    'queue' => env('PIPELINE_QUEUE', 'pipeline'),

    /*
    |--------------------------------------------------------------------------
    | Google (Drive + Sheets)
    |--------------------------------------------------------------------------
    |
    | OAuth client credentials from the Fynla Marketing Pipeline GCP project.
    | folder_id is the Marketing Automation Drive folder; scripts are uploaded
    | there and the tracker sheet is created inside it.
    | tracker_sheet_id is set once by `pipeline:setup-tracker` and pasted back
    | into .env.
    |
    */

    'google' => [
        'oauth_client_id' => env('GOOGLE_OAUTH_CLIENT_ID'),
        'oauth_client_secret' => env('GOOGLE_OAUTH_CLIENT_SECRET'),
        'oauth_redirect_uri' => env('GOOGLE_OAUTH_REDIRECT_URI'),
        'drive_folder_id' => env('PIPELINE_GOOGLE_DRIVE_FOLDER_ID', '1HR5oTck5ZQuAviTvAMdJEoNpPIcd-75P'),
        'tracker_sheet_id' => env('PIPELINE_GOOGLE_TRACKER_SHEET_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Anthropic (Opus 4.7)
    |--------------------------------------------------------------------------
    |
    | The script generator uses Opus for its analogy quality — cost is
    | governed by the caps below. `prompt_version` is echoed on every stored
    | script so we can regenerate when the brand-voice prompt changes.
    |
    */

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_OPUS_MODEL', 'claude-opus-4-7'),
        'timeout_seconds' => env('PIPELINE_ANTHROPIC_TIMEOUT', 120),
        'max_output_tokens' => env('PIPELINE_ANTHROPIC_MAX_OUTPUT_TOKENS', 4096),
        'prompt_version' => env('PIPELINE_PROMPT_VERSION', 'v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Caps
    |--------------------------------------------------------------------------
    |
    | Soft caps to prevent runaway API spend. Values in GBP. Anthropic bills
    | in USD — we convert at a conservative 1 GBP ≈ 0.79 USD (see CostCap).
    |
    | Per-request cap catches abnormal single calls (large article, prompt
    | drift). Daily cap catches runaway loops. Once the day cap is hit, the
    | job errors out with a clear message rather than silently over-spending.
    |
    */

    'cost' => [
        'per_request_gbp' => env('PIPELINE_COST_PER_REQUEST_GBP', 0.30),
        'per_day_gbp' => env('PIPELINE_COST_PER_DAY_GBP', 2.00),
        'usd_per_gbp' => env('PIPELINE_USD_PER_GBP', 1.27),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Where script-ready notification emails are sent. Defaults to
    | marketing@fynla.org but can be overridden per environment (e.g. a
    | personal email for local dev).
    |
    */

    'notifications' => [
        'script_ready_to' => env('PIPELINE_NOTIFICATION_TO', 'marketing@fynla.org'),
    ],

];
