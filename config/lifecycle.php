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
    'trial_restart_days' => 14,
    'magic_link_ttl_days' => 7,
    'discount_code_ttl_days' => 7,
    'cancellation_feedback_delay_days' => 3,
    'lapsed_recovery_threshold_days' => 5,
    'eligibility_anchor_days' => 9,

    // Per-plan-per-cycle discount amounts in pence (Campaign 2)
    'campaign2_discounts' => [
        'student.monthly' => 100,    // £3.99 → £2.99 = £1.00 off
        'student.yearly' => 801,     // £30.00 → £21.99 = £8.01 off
        'standard.monthly' => 500,   // £10.99 → £5.99 = £5.00 off
        'standard.yearly' => 4500,   // £100.00 → £55.00 = £45.00 off
        'family.monthly' => 400,     // £14.99 → £10.99 = £4.00 off
        'family.yearly' => 5000,     // £150.00 → £100.00 = £50.00 off
    ],

    // Reason codes per feedback campaign
    'feedback_reasons' => [
        'cancelled_trialer' => [
            'too_expensive', 'missing_features', 'found_alternative',
            'not_what_expected', 'bugs_or_ux', 'personal_change', 'other',
        ],
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
        'empty_trialer' => 'lifecycle_empty_trialer',
        'engaged_trialer' => 'lifecycle_engaged_trialer',
        'cancelled_trialer' => 'lifecycle_cancelled_trialer',
        'churned_subscriber' => 'lifecycle_churned_subscriber',
        'lapsed_subscriber' => 'lifecycle_lapsed_subscriber',
    ],

    'test_recipient_override' => env('LIFECYCLE_TEST_RECIPIENT', null),

    // Milliseconds to sleep between sends so we stay below SMTP provider rate
    // limits. SiteGround caps shared mail at ~10 messages/sec; 150 ms (≈6.6/s)
    // gives headroom. Set to 0 to disable pacing (tests, self-hosted SMTP).
    'throttle_ms' => (int) env('LIFECYCLE_THROTTLE_MS', 150),
];
