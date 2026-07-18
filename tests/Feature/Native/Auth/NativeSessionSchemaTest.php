<?php

declare(strict_types=1);

use App\Models\NativeDeviceSession;
use App\Models\NativeRefreshToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

it('creates the native session tables with the required columns and indexes', function (): void {
    expect(Schema::hasColumns('native_device_sessions', [
        'id',
        'user_id',
        'platform',
        'device_label',
        'app_version',
        'app_build',
        'current_access_token_id',
        'authenticated_at',
        'absolute_expires_at',
        'last_used_at',
        'revoked_at',
        'revoke_reason',
        'created_at',
        'updated_at',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('native_refresh_tokens', [
            'id',
            'native_device_session_id',
            'token_hash',
            'expires_at',
            'used_at',
            'revoked_at',
            'created_at',
            'updated_at',
        ]))->toBeTrue();

    $sessionId = DB::selectOne(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?',
        ['native_device_sessions', 'id']
    );

    $refreshHash = DB::selectOne(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?',
        ['native_refresh_tokens', 'token_hash']
    );

    expect(strtolower((string) $sessionId->COLUMN_TYPE))->toBe('char(36)')
        ->and(strtolower((string) $refreshHash->COLUMN_TYPE))->toBe('char(64)');

    $sessionIndexes = collect(DB::select('SHOW INDEX FROM native_device_sessions'))
        ->groupBy('Key_name')
        ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->all());

    expect($sessionIndexes->get('PRIMARY'))->toBe(['id'])
        ->and($sessionIndexes->get('native_device_sessions_user_id_index'))->toBe(['user_id'])
        ->and($sessionIndexes->get('native_device_sessions_absolute_expires_at_index'))->toBe(['absolute_expires_at'])
        ->and($sessionIndexes->get('native_device_sessions_revoked_at_index'))->toBe(['revoked_at']);

    $refreshIndexes = collect(DB::select('SHOW INDEX FROM native_refresh_tokens'))
        ->groupBy('Key_name')
        ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->all());

    expect($refreshIndexes->get('native_refresh_tokens_token_hash_unique'))->toBe(['token_hash'])
        ->and($refreshIndexes->get('native_refresh_session_expiry_index'))->toBe([
            'native_device_session_id',
            'expires_at',
        ]);
});

it('applies the required native session cascade relationships', function (): void {
    $relationships = collect(DB::select(
        'SELECT k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME,
                r.DELETE_RULE, r.UPDATE_RULE
         FROM information_schema.KEY_COLUMN_USAGE k
         JOIN information_schema.REFERENTIAL_CONSTRAINTS r
           ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
          AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
         WHERE k.CONSTRAINT_SCHEMA = DATABASE()
           AND k.TABLE_NAME IN (?, ?)
           AND k.REFERENCED_TABLE_NAME IS NOT NULL',
        ['native_device_sessions', 'native_refresh_tokens']
    ))->keyBy(fn (object $row): string => $row->TABLE_NAME.'.'.$row->COLUMN_NAME);

    expect($relationships->keys()->all())->toContain(
        'native_device_sessions.user_id',
        'native_device_sessions.current_access_token_id',
        'native_refresh_tokens.native_device_session_id',
    );

    expect($relationships->get('native_device_sessions.user_id')->REFERENCED_TABLE_NAME)
        ->toBe('users')
        ->and($relationships->get('native_device_sessions.user_id')->DELETE_RULE)
        ->toBe('CASCADE')
        ->and($relationships->get('native_device_sessions.current_access_token_id')->REFERENCED_TABLE_NAME)
        ->toBe('personal_access_tokens')
        ->and($relationships->get('native_device_sessions.current_access_token_id')->DELETE_RULE)
        ->toBe('SET NULL')
        ->and($relationships->get('native_refresh_tokens.native_device_session_id')->REFERENCED_TABLE_NAME)
        ->toBe('native_device_sessions')
        ->and($relationships->get('native_refresh_tokens.native_device_session_id')->DELETE_RULE)
        ->toBe('CASCADE');
});

it('casts native session state and exposes its persistence relationships', function (): void {
    expect(class_exists(NativeDeviceSession::class))->toBeTrue()
        ->and(class_exists(NativeRefreshToken::class))->toBeTrue();

    $now = CarbonImmutable::parse('2026-07-17T09:00:00', config('app.timezone'));
    $user = User::factory()->create();
    $accessToken = $user->createToken('native-access:test', ['native'])->accessToken;

    $session = NativeDeviceSession::factory()->for($user)->create([
        'current_access_token_id' => $accessToken->id,
        'authenticated_at' => $now,
        'absolute_expires_at' => $now->addDays(90),
        'last_used_at' => $now,
    ]);
    $refresh = NativeRefreshToken::factory()
        ->for($session, 'deviceSession')
        ->create();

    expect(Str::isUuid($session->id))->toBeTrue()
        ->and($session->authenticated_at->equalTo($now))->toBeTrue()
        ->and($session->absolute_expires_at->equalTo($now->addDays(90)))->toBeTrue()
        ->and($session->last_used_at->equalTo($now))->toBeTrue()
        ->and($session->user->is($user))->toBeTrue()
        ->and($session->currentAccessToken->is($accessToken))->toBeTrue()
        ->and($session->refreshTokens->first()->is($refresh))->toBeTrue()
        ->and($refresh->deviceSession->is($session))->toBeTrue();
});

it('keeps refresh token hashes outside mass assignment and JSON', function (): void {
    expect(class_exists(NativeRefreshToken::class))->toBeTrue();

    $hash = hash('sha256', 'native-refresh-test-secret');
    $token = NativeRefreshToken::factory()->create(['token_hash' => $hash]);

    expect($token->getFillable())->not->toContain('token_hash')
        ->and($token->getHidden())->toContain('token_hash')
        ->and($token->toArray())->not->toHaveKey('token_hash')
        ->and($token->getRawOriginal('token_hash'))->toBe($hash);
});

it('evaluates native session and refresh eligibility against the supplied server clock', function (): void {
    expect(class_exists(NativeDeviceSession::class))->toBeTrue()
        ->and(class_exists(NativeRefreshToken::class))->toBeTrue();

    $now = CarbonImmutable::parse('2026-07-17T09:00:00', config('app.timezone'));
    $session = NativeDeviceSession::factory()->make([
        'absolute_expires_at' => $now->addSecond(),
        'revoked_at' => null,
    ]);
    $refresh = NativeRefreshToken::factory()->make([
        'expires_at' => $now->addSecond(),
        'used_at' => null,
        'revoked_at' => null,
    ]);

    expect($session->isActive($now))->toBeTrue()
        ->and($session->isActive($now->addSecond()))->toBeFalse()
        ->and($refresh->canRotate($now))->toBeTrue()
        ->and($refresh->canRotate($now->addSecond()))->toBeFalse();

    $session->revoked_at = $now;
    $refresh->used_at = $now;

    expect($session->isActive($now->subSecond()))->toBeFalse()
        ->and($refresh->canRotate($now->subSecond()))->toBeFalse();
});
