<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Account retention period (years)
    |--------------------------------------------------------------------------
    | After a user's account is soft-deleted (any reason), their data is
    | retained in the database for this many years before the monthly
    | RetentionPurgeService cron hard-deletes it.
    |
    | Default 7 years matches FCA COBS 11.5 record-keeping requirements.
    */
    'account_years' => (int) env('ACCOUNT_RETENTION_YEARS', 7),

    /*
    | Reminder emails sent before a scheduled deletion fires.
    | Days before `deletion_scheduled_for` to send each reminder.
    */
    'reminder_days_before' => [7, 1],
];
