<?php

declare(strict_types=1);

use App\Models\AppleNotificationLog;
use App\Models\AppleTransaction;
use App\Models\PremiumEntitlement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('adds a guarded hidden unique Apple account token to users', function (): void {
    expect(Schema::hasColumn('users', 'apple_app_account_token'))->toBeTrue();

    $column = DB::selectOne(
        'SELECT COLUMN_TYPE, IS_NULLABLE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?',
        ['users', 'apple_app_account_token']
    );
    $indexes = collect(DB::select('SHOW INDEX FROM users'))
        ->groupBy('Key_name')
        ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->all());

    expect(strtolower((string) $column->COLUMN_TYPE))->toBe('char(36)')
        ->and($column->IS_NULLABLE)->toBe('YES')
        ->and($indexes->get('users_apple_app_account_token_unique'))->toBe(['apple_app_account_token']);

    $token = (string) Str::uuid();
    $user = User::factory()->make();
    $user->fill(['apple_app_account_token' => $token]);

    $reflection = new ReflectionClass($user);
    $auditExclude = $reflection->getProperty('auditExcludeFields')->getValue($user);

    expect($user->getGuarded())->toContain('apple_app_account_token')
        ->and($user->getHidden())->toContain('apple_app_account_token')
        ->and($auditExclude)->toContain('apple_app_account_token')
        ->and($user->getCasts())->not->toHaveKey('apple_app_account_token')
        ->and($user->getAttribute('apple_app_account_token'))->toBeNull();

    $user->forceFill(['apple_app_account_token' => $token]);

    expect($user->toArray())->not->toHaveKey('apple_app_account_token');
});

it('creates provider-neutral entitlement and Apple evidence schemas with required indexes', function (): void {
    expect(Schema::hasColumns('premium_entitlements', [
        'id',
        'user_id',
        'provider',
        'provider_reference',
        'product_id',
        'status',
        'will_renew',
        'period_start',
        'period_end',
        'grace_period_ends_at',
        'revoked_at',
        'last_verified_at',
        'provider_metadata',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('apple_transactions', [
            'id',
            'user_id',
            'premium_entitlement_id',
            'transaction_id',
            'original_transaction_id',
            'app_account_token',
            'product_id',
            'environment',
            'purchased_at',
            'expires_at',
            'revoked_at',
            'ownership_type',
            'transaction_reason',
            'signed_payload_sha256',
            'received_at',
            'reconciled_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    $entitlementIndexes = collect(DB::select('SHOW INDEX FROM premium_entitlements'))
        ->groupBy('Key_name')
        ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->all());
    $transactionIndexes = collect(DB::select('SHOW INDEX FROM apple_transactions'))
        ->groupBy('Key_name')
        ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->all());

    expect($entitlementIndexes->get('premium_entitlements_provider_reference_unique'))
        ->toBe(['provider', 'provider_reference'])
        ->and($entitlementIndexes->get('premium_entitlements_user_status_index'))
        ->toBe(['user_id', 'status'])
        ->and($entitlementIndexes->get('premium_entitlements_user_period_end_index'))
        ->toBe(['user_id', 'period_end'])
        ->and($transactionIndexes->get('apple_transactions_transaction_id_unique'))
        ->toBe(['transaction_id'])
        ->and($transactionIndexes->get('apple_transactions_original_transaction_id_index'))
        ->toBe(['original_transaction_id']);

    $hashColumn = DB::selectOne(
        'SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?',
        ['apple_transactions', 'signed_payload_sha256']
    );
    $enumColumns = collect(DB::select(
        'SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND (TABLE_NAME, COLUMN_NAME) IN (
               (?, ?), (?, ?), (?, ?)
           )',
        [
            'premium_entitlements', 'provider',
            'premium_entitlements', 'status',
            'apple_transactions', 'environment',
        ]
    ))->keyBy(fn (object $row): string => $row->TABLE_NAME.'.'.$row->COLUMN_NAME);

    expect(strtolower((string) $hashColumn->COLUMN_TYPE))->toBe('char(64)')
        ->and(strtolower((string) $enumColumns->get('premium_entitlements.provider')->COLUMN_TYPE))
        ->toBe("enum('apple','revolut')")
        ->and(strtolower((string) $enumColumns->get('premium_entitlements.status')->COLUMN_TYPE))
        ->toBe("enum('active','grace_period','billing_retry','cancelled','expired','revoked')")
        ->and(strtolower((string) $enumColumns->get('apple_transactions.environment')->COLUMN_TYPE))
        ->toBe("enum('sandbox','production')");
});

it('keeps Apple notification audit rows strictly non-personal and hash-only', function (): void {
    $allowedColumns = [
        'id',
        'notification_uuid',
        'environment',
        'notification_type',
        'subtype',
        'signed_payload_sha256',
        'status',
        'error_code',
        'processed_at',
        'created_at',
        'updated_at',
    ];

    $actualColumns = Schema::getColumnListing('apple_notification_logs');
    sort($actualColumns);
    sort($allowedColumns);

    expect($actualColumns)->toBe($allowedColumns);

    $indexes = collect(DB::select('SHOW INDEX FROM apple_notification_logs'))
        ->groupBy('Key_name')
        ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->all());
    $hashColumn = DB::selectOne(
        'SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?',
        ['apple_notification_logs', 'signed_payload_sha256']
    );
    $statusColumn = DB::selectOne(
        'SELECT COLUMN_TYPE
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?',
        ['apple_notification_logs', 'status']
    );

    expect($indexes->get('apple_notification_logs_notification_uuid_unique'))
        ->toBe(['notification_uuid'])
        ->and(strtolower((string) $hashColumn->COLUMN_TYPE))->toBe('char(64)')
        ->and(strtolower((string) $statusColumn->COLUMN_TYPE))
        ->toBe("enum('received','processed','duplicate','rejected','failed')")
        ->and($actualColumns)->not->toContain(
            'user_id',
            'app_account_token',
            'transaction_id',
            'original_transaction_id',
            'signed_payload',
            'raw_signed_payload',
            'raw_jws',
            'decoded_payload',
            'private_key',
        );
});

it('never creates raw signed-payload or private-key columns in billing persistence', function (): void {
    $forbiddenColumns = [
        'signed_payload',
        'raw_signed_payload',
        'raw_jws',
        'decoded_payload',
        'signed_transaction',
        'signed_renewal_info',
        'private_key',
        'private_key_id',
    ];

    foreach (['premium_entitlements', 'apple_transactions', 'apple_notification_logs'] as $table) {
        foreach ($forbiddenColumns as $column) {
            expect(Schema::hasColumn($table, $column))
                ->toBeFalse("{$table}.{$column} must never exist");
        }
    }
});

it('casts billing state and exposes only the focused persistence relationships', function (): void {
    expect(class_exists(PremiumEntitlement::class))->toBeTrue()
        ->and(class_exists(AppleTransaction::class))->toBeTrue()
        ->and(class_exists(AppleNotificationLog::class))->toBeTrue();

    $now = CarbonImmutable::parse('2026-07-18T12:00:00', config('app.timezone'));
    $user = User::factory()->create();
    $entitlement = PremiumEntitlement::query()->create([
        'user_id' => $user->id,
        'provider' => PremiumEntitlement::PROVIDER_APPLE,
        'provider_reference' => 'original-transaction-1',
        'product_id' => 'org.fynla.premium.monthly',
        'status' => PremiumEntitlement::STATUS_ACTIVE,
        'will_renew' => true,
        'period_start' => $now,
        'period_end' => $now->addMonth(),
        'last_verified_at' => $now,
        'provider_metadata' => ['source' => 'verified_transaction'],
    ]);
    $transaction = AppleTransaction::query()->create([
        'user_id' => $user->id,
        'premium_entitlement_id' => $entitlement->id,
        'transaction_id' => 'transaction-1',
        'original_transaction_id' => 'original-transaction-1',
        'app_account_token' => (string) Str::uuid(),
        'product_id' => 'org.fynla.premium.monthly',
        'environment' => AppleTransaction::ENVIRONMENT_SANDBOX,
        'purchased_at' => $now,
        'expires_at' => $now->addMonth(),
        'ownership_type' => 'PURCHASED',
        'transaction_reason' => 'PURCHASE',
        'signed_payload_sha256' => hash('sha256', 'transaction evidence'),
        'received_at' => $now,
    ]);
    $notification = AppleNotificationLog::query()->create([
        'notification_uuid' => (string) Str::uuid(),
        'environment' => AppleNotificationLog::ENVIRONMENT_SANDBOX,
        'notification_type' => 'SUBSCRIBED',
        'subtype' => 'INITIAL_BUY',
        'signed_payload_sha256' => hash('sha256', 'notification evidence'),
        'status' => AppleNotificationLog::STATUS_RECEIVED,
    ]);

    expect($entitlement->will_renew)->toBeTrue()
        ->and($entitlement->period_start->equalTo($now))->toBeTrue()
        ->and($entitlement->period_end->equalTo($now->addMonth()))->toBeTrue()
        ->and($entitlement->provider_metadata)->toBe(['source' => 'verified_transaction'])
        ->and($entitlement->user())->toBeInstanceOf(BelongsTo::class)
        ->and($entitlement->appleTransactions())->toBeInstanceOf(HasMany::class)
        ->and($entitlement->user->is($user))->toBeTrue()
        ->and($entitlement->appleTransactions->first()->is($transaction))->toBeTrue()
        ->and($transaction->user())->toBeInstanceOf(BelongsTo::class)
        ->and($transaction->premiumEntitlement())->toBeInstanceOf(BelongsTo::class)
        ->and($transaction->purchased_at->equalTo($now))->toBeTrue()
        ->and($transaction->premiumEntitlement->is($entitlement))->toBeTrue()
        ->and($notification->processed_at)->toBeNull()
        ->and($user->premiumEntitlements())->toBeInstanceOf(HasMany::class)
        ->and($user->appleTransactions())->toBeInstanceOf(HasMany::class);
});
