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

    /*
    |--------------------------------------------------------------------------
    | Local Whisper (Stage 3 — transcription)
    |--------------------------------------------------------------------------
    |
    | Model choice for the whisper CLI. Trade-off:
    |   tiny  (75 MB, fastest)
    |   base  (150 MB)
    |   small (500 MB) — recommended default
    |   medium (1.5 GB, more accurate, slower)
    |   large-v3 (3 GB, most accurate, GPU recommended)
    | Language should stay 'en' unless you record in another language.
    |
    */

    'whisper' => [
        'model' => env('PIPELINE_WHISPER_MODEL', 'small'),
        'language' => env('PIPELINE_WHISPER_LANGUAGE', 'en'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Video (Stage 3 — clip generation + retention)
    |--------------------------------------------------------------------------
    |
    | Detect schedule: 30 min after the article detect so newly scripted
    | pipeline rows have a chance to land before we look for videos.
    | Audit runs quarterly (1st day of Jan/Apr/Jul/Oct at 08:00).
    | Signed URL TTL: how long the tracker-sheet download links stay valid.
    | Retention threshold: audit flags anything older than this.
    |
    */

    'video' => [
        'detect_schedule' => env('PIPELINE_VIDEO_DETECT_SCHEDULE', '07:30'),
        'audit_older_than_days' => env('PIPELINE_VIDEO_AUDIT_DAYS', 365),
        'signed_url_ttl_days' => env('PIPELINE_SIGNED_URL_TTL_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Clip Approval (Stage 3.5 — gate before Stage 4 composition)
    |--------------------------------------------------------------------------
    |
    | When enabled, ProcessVideoJob doesn't hand clips straight to
    | ComposePostsJob — it creates ClipApproval rows, sends
    | ClipsAwaitingApprovalMail to marketing, and waits. Marketing can
    | approve/reject via the /admin/pipeline/clips queue OR one-click
    | via magic links in the email.
    |
    | Anything still `pending` when we're within `auto_approve_minutes_before_post`
    | of the scheduled post time is silently auto-approved by the
    | pipeline:auto-approve-clips cron. Default: 10 min before.
    |
    */

    'clip_approval' => [
        'enabled' => env('PIPELINE_CLIP_APPROVAL_ENABLED', true),
        'auto_approve_minutes_before_post' => (int) env('PIPELINE_CLIP_AUTO_APPROVE_MINUTES', 10),
        'auto_approve_cron_frequency_minutes' => (int) env('PIPELINE_CLIP_AUTO_APPROVE_CRON_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Social (Stage 4 — posting + reporting)
    |--------------------------------------------------------------------------
    |
    | Static industry-benchmark posting windows per platform, GMT. Overridden
    | weekly by WeeklyOptimalTimeRecalculator once we have enough GA data.
    |
    | Governed by the `social-media-posts` skill — read it before changing
    | anything under `social.*`.
    |
    */

    'social' => [

        'best_times' => [
            'instagram' => [
                ['day' => 'Tuesday',  'time' => '12:00'],
                ['day' => 'Thursday', 'time' => '12:00'],
                ['day' => 'Saturday', 'time' => '09:30'],
            ],
            'facebook' => [
                ['day' => 'Wednesday', 'time' => '13:00'],
                ['day' => 'Friday',    'time' => '13:00'],
            ],
            'tiktok' => [
                ['day' => 'Tuesday',   'time' => '06:00'],
                ['day' => 'Wednesday', 'time' => '07:00'],
                ['day' => 'Friday',    'time' => '19:30'],
            ],
        ],

        'default_hashtag_count' => (int) env('PIPELINE_HASHTAG_COUNT', 3),
        'hashtag_min' => 2,
        'hashtag_max' => 5,

        // Tags never picked by HashtagPicker — too generic or against
        // platform norms. Extend liberally.
        'banned_hashtags' => [
            '#finance', '#money', '#uk', '#saving', '#investing',
            '#fyp', '#foryou', '#foryoupage', '#viral', '#trending',
            '#followme', '#like4like',
        ],

        'variant_count' => 2,

        'compose_after_render' => (bool) env('PIPELINE_COMPOSE_AFTER_RENDER', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Buffer (Stage 4 — the posting mechanism)
    |--------------------------------------------------------------------------
    |
    | Buffer Publishing API v1. Access token from https://publish.buffer.com/
    | → Settings → Integrations → API. Profiles map platform → profile ID
    | (find via `curl -H "Authorization: Bearer $TOKEN" https://api.bufferapp.com/1/profiles.json`).
    |
    */

    'buffer' => [
        'access_token' => env('BUFFER_ACCESS_TOKEN'),
        'profiles' => [
            'instagram' => env('BUFFER_PROFILE_INSTAGRAM'),
            'facebook' => env('BUFFER_PROFILE_FACEBOOK'),
            'tiktok' => env('BUFFER_PROFILE_TIKTOK'),
        ],
        'timeout_seconds' => (int) env('BUFFER_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Analytics (Stage 4 — weekly report + optimal-time recalculator)
    |--------------------------------------------------------------------------
    |
    | Reuses the existing Google OAuth grant with the analytics.readonly
    | scope added. After changing scopes, users must re-run
    | pipeline:authorise-google to re-consent.
    |
    */

    'google_analytics' => [
        'property_id' => env('PIPELINE_GA_PROPERTY_ID'),
        'default_lookback_days' => (int) env('PIPELINE_GA_LOOKBACK_DAYS', 56),
    ],

    /*
    |--------------------------------------------------------------------------
    | Reports (Stage 4)
    |--------------------------------------------------------------------------
    */

    'reports' => [
        'weekly_social_recipient' => env('PIPELINE_WEEKLY_REPORT_TO', 'marketing@fynla.org'),
        'weekly_social_schedule_day' => (int) env('PIPELINE_WEEKLY_REPORT_DAY', 1),
        'weekly_social_schedule_time' => env('PIPELINE_WEEKLY_REPORT_TIME', '09:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content ingestion (Stage 5 — Word docs → InsightArticles)
    |--------------------------------------------------------------------------
    */

    'article_docs_schedule' => env('PIPELINE_ARTICLE_DOCS_SCHEDULE', '06:45'),

    'gate' => [
        'dev_to_prod_min_hours' => (int) env('PIPELINE_DEV_TO_PROD_MIN_HOURS', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cross-env sync (Stage 5)
    |--------------------------------------------------------------------------
    |
    | Local set: PIPELINE_SYNC_URL_* + PIPELINE_SYNC_TOKEN_* — targets.
    | On each target env set: PIPELINE_SYNC_TOKEN — matched against inbound
    | requests' X-Pipeline-Sync-Token header. This IS the auth.
    |
    */

    'sync' => [
        'dev_url' => env('PIPELINE_SYNC_URL_DEV'),
        'dev_token' => env('PIPELINE_SYNC_TOKEN_DEV'),
        'prod_url' => env('PIPELINE_SYNC_URL_PROD'),
        'prod_token' => env('PIPELINE_SYNC_TOKEN_PROD'),
        'inbound_token' => env('PIPELINE_SYNC_TOKEN'),
    ],

];
