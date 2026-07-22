<?php

declare(strict_types=1);
use App\Services\Lifecycle\Campaigns\ChurnedSubscriberCampaign;
use App\Services\Lifecycle\Campaigns\LapsedSubscriberCampaign;

return [
    'enabled' => env('LIFECYCLE_ENGINE_ENABLED', true),

    // Campaigns are registered here. The engine resolves them via container
    // and sorts by priority() at runtime.
    'campaigns' => [
        ChurnedSubscriberCampaign::class,
        LapsedSubscriberCampaign::class,
    ],

    // Timing knobs (all in days)
    'magic_link_ttl_days' => 7,
    'cancellation_feedback_delay_days' => 3,
    'lapsed_recovery_threshold_days' => 5,

    // Reason codes per feedback campaign
    'feedback_reasons' => [
        'churned_subscriber' => [
            'too_expensive', 'missing_features', 'found_alternative',
            'not_what_expected', 'bugs_or_ux', 'personal_change', 'other',
        ],
        'lapsed_subscriber' => [
            'will_fix', 'wants_to_cancel', 'needs_help',
        ],
    ],

    // Maps each campaign slug to its corresponding notification_preferences column
    'campaign_to_preference' => [
        'churned_subscriber' => 'lifecycle_churned_subscriber',
        'lapsed_subscriber' => 'lifecycle_lapsed_subscriber',
    ],

    'test_recipient_override' => env('LIFECYCLE_TEST_RECIPIENT', null),

    // Milliseconds to sleep between sends so we stay below SMTP provider rate
    // limits. SiteGround caps shared mail at ~10 messages/sec; 150 ms (≈6.6/s)
    // gives headroom. Set to 0 to disable pacing (tests, self-hosted SMTP).
    'throttle_ms' => (int) env('LIFECYCLE_THROTTLE_MS', 150),
];
