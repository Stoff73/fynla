<?php

declare(strict_types=1);

use App\Models\AppleNotificationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function createAppleNotificationAuditLog(int $ageInDays): AppleNotificationLog
{
    $log = AppleNotificationLog::query()->create([
        'notification_uuid' => (string) Str::uuid(),
        'environment' => AppleNotificationLog::ENVIRONMENT_SANDBOX,
        'notification_type' => 'DID_RENEW',
        'subtype' => null,
        'signed_payload_sha256' => hash('sha256', "notification evidence {$ageInDays}"),
        'status' => AppleNotificationLog::STATUS_PROCESSED,
        'processed_at' => now()->subDays($ageInDays),
    ]);

    DB::table('apple_notification_logs')->where('id', $log->id)->update([
        'created_at' => now()->subDays($ageInDays),
    ]);

    return $log;
}

it('prunes non-personal Apple notification audit after its configured seven-year window', function (): void {
    expect(config('retention.apple_notification_audit_days'))->toBe(2555);

    $oldLog = createAppleNotificationAuditLog(2556);
    $freshLog = createAppleNotificationAuditLog(2554);

    $this->artisan('audit:purge')
        ->expectsOutputToContain('Apple notification audit: 2555 days')
        ->assertSuccessful();

    expect(AppleNotificationLog::find($oldLog->id))->toBeNull()
        ->and(AppleNotificationLog::find($freshLog->id))->not->toBeNull();
});

it('includes Apple notification audit in dry runs without deleting it', function (): void {
    $oldLog = createAppleNotificationAuditLog(2556);

    $this->artisan('audit:purge --dry-run')
        ->expectsOutputToContain('DRY RUN - No records will be deleted')
        ->assertSuccessful();

    expect(AppleNotificationLog::find($oldLog->id))->not->toBeNull();
});
