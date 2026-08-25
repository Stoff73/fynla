<?php

declare(strict_types=1);

return [
    // Finite ladder of named levels. 'min_points' = cumulative points to REACH this level.
    // Tunable post-launch. No emoji / acronyms in names (Rules #9, #15).
    'levels' => [
        1 => ['name' => 'Starter',    'min_points' => 0],
        2 => ['name' => 'Saver',      'min_points' => 50],
        3 => ['name' => 'Builder',    'min_points' => 120],
        4 => ['name' => 'Organiser',  'min_points' => 220],
        5 => ['name' => 'Planner',    'min_points' => 360],
        6 => ['name' => 'Strategist', 'min_points' => 550],
        7 => ['name' => 'Optimiser',  'min_points' => 800],
        8 => ['name' => 'Guardian',   'min_points' => 1120],
        9 => ['name' => 'Steward',    'min_points' => 1520],
        10 => ['name' => 'Master',    'min_points' => 2000],
    ],

    // Point values per source (tunable).
    'points' => [
        'data_first_in_category' => 20,
        'data_extra_record' => 5,
        'data_extra_cap_per_category' => 3,   // max extra-record awards per category
        'onboarding_answer' => 10,
        'milestone' => 30,
        'recommendation' => 25,
        'daily_login' => 5,
        'streak' => [3 => 15, 7 => 30, 14 => 50, 30 => 100],
    ],

    // Sort weight given to a KYC "unlock" action when interleaved with real
    // recommendations in the mobile next-actions list (tunable).
    'unlock_action_weight' => 65,

    // WP-5c-iii — push notification on newly-earned milestones. Flag-gated
    // (CSJ decision 2026-07-03): OFF by default, one send per mint when on.
    'push_enabled' => env('GAMIFICATION_PUSH_ENABLED', false),
];
