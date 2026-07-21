<?php

declare(strict_types=1);

use App\Data\Auth\NativeDeviceContext;
use App\Data\Auth\NativeSessionCredentials;
use App\Exceptions\NativeSessionException;
use App\Models\NativeDeviceSession;
use App\Models\NativeRefreshToken;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Auth\NativeSessionService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

function nativeDeviceContext(
    string $label = 'CJ’s iPhone',
    string $version = '1.0.0',
    string $build = '100'
): NativeDeviceContext {
    return new NativeDeviceContext(
        platform: 'ios',
        deviceLabel: $label,
        appVersion: $version,
        appBuild: $build,
    );
}

/**
 * @return array{0: User, 1: PersonalAccessToken, 2: UserSession}
 */
function nativeBootstrap(): array
{
    $user = User::factory()->create();
    $bootstrap = $user->createToken('bootstrap', ['*'])->accessToken;
    $webSession = UserSession::create([
        'user_id' => $user->id,
        'token_id' => $bootstrap->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Fynla iOS',
        'device_name' => 'iPhone',
        'last_activity_at' => CarbonImmutable::now(),
    ]);

    return [$user, $bootstrap, $webSession];
}

function nativeExchange(
    ?NativeDeviceContext $device = null
): NativeSessionCredentials {
    [$user, $bootstrap] = nativeBootstrap();

    return app(NativeSessionService::class)->exchange(
        $user,
        $bootstrap,
        $device ?? nativeDeviceContext(),
    );
}

function captureNativeSessionError(callable $operation): NativeSessionException
{
    try {
        $operation();
    } catch (NativeSessionException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a native session exception.');
}

function cleanupCommittedNativeFixture(int $userId): void
{
    $user = User::query()->find($userId);
    if ($user !== null) {
        $user->tokens()->delete();
        $user->delete();
    }

    TaxConfiguration::query()->where('tax_year', '2019/20')->delete();
}

beforeEach(function (): void {
    CarbonImmutable::setTestNow('2026-07-17 10:00:00 UTC');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('provides the native session service boundary', function (): void {
    expect(class_exists(NativeSessionService::class))->toBeTrue();
});

it('atomically exchanges a bootstrap token for a native session family', function (): void {
    [$user, $bootstrap, $webSession] = nativeBootstrap();

    $credentials = app(NativeSessionService::class)->exchange(
        $user,
        $bootstrap,
        nativeDeviceContext(),
    );

    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    $access = PersonalAccessToken::query()->findOrFail($session->current_access_token_id);
    $refresh = NativeRefreshToken::query()->where('native_device_session_id', $session->id)->sole();

    expect($session->user_id)->toBe($user->id)
        ->and($session->platform)->toBe('ios')
        ->and($session->device_label)->toBe('CJ’s iPhone')
        ->and($session->app_version)->toBe('1.0.0')
        ->and($session->app_build)->toBe('100')
        ->and($access->name)->toBe("native-access:{$session->id}")
        ->and($access->abilities)->toBe(['native'])
        ->and(PersonalAccessToken::findToken($credentials->accessToken)?->id)->toBe($access->id)
        ->and($refresh->getRawOriginal('token_hash'))->toBe(hash('sha256', $credentials->refreshToken))
        ->and($credentials->refreshToken)->toMatch('/^[A-Za-z0-9_-]{43}$/')
        ->and($refresh->getRawOriginal('token_hash'))->not->toBe($credentials->refreshToken)
        ->and(PersonalAccessToken::query()->find($bootstrap->id))->toBeNull()
        ->and(UserSession::query()->find($webSession->id))->toBeNull();
});

it('uses exact access refresh and absolute lifetimes from one operation clock', function (): void {
    [$user, $bootstrap] = nativeBootstrap();
    $authenticatedAt = CarbonImmutable::instance($bootstrap->fresh()->created_at);
    $credentials = app(NativeSessionService::class)->exchange(
        $user,
        $bootstrap,
        nativeDeviceContext(),
    );
    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    $access = PersonalAccessToken::query()->findOrFail($session->current_access_token_id);
    $refresh = NativeRefreshToken::query()->where('native_device_session_id', $session->id)->sole();

    expect($credentials->accessExpiresAt->equalTo('2026-07-17 10:15:00 UTC'))->toBeTrue()
        ->and($access->expires_at?->equalTo($credentials->accessExpiresAt))->toBeTrue()
        ->and($credentials->refreshExpiresAt->equalTo('2026-08-16 10:00:00 UTC'))->toBeTrue()
        ->and($refresh->expires_at?->equalTo($credentials->refreshExpiresAt))->toBeTrue()
        ->and($credentials->absoluteExpiresAt->equalTo($authenticatedAt->addDays(90)))->toBeTrue()
        ->and($session->authenticated_at?->equalTo($authenticatedAt))->toBeTrue()
        ->and($session->absolute_expires_at?->equalTo($credentials->absoluteExpiresAt))->toBeTrue();
});

it('anchors the absolute lifetime to the completed authentication time', function (): void {
    [$user, $bootstrap] = nativeBootstrap();
    $bootstrap->forceFill([
        'created_at' => CarbonImmutable::now()->subHours(12),
        'updated_at' => CarbonImmutable::now()->subHours(12),
    ])->save();
    $authenticatedAt = CarbonImmutable::instance($bootstrap->fresh()->created_at);

    $credentials = app(NativeSessionService::class)->exchange(
        $user,
        $bootstrap,
        nativeDeviceContext(),
    );

    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    expect($session->authenticated_at?->equalTo($authenticatedAt))->toBeTrue()
        ->and($credentials->absoluteExpiresAt->equalTo($authenticatedAt->addDays(90)))->toBeTrue()
        ->and($session->absolute_expires_at?->equalTo($credentials->absoluteExpiresAt))->toBeTrue();
});

it('rotates both credentials and retires the previous access and refresh credentials', function (): void {
    $original = nativeExchange();
    $session = NativeDeviceSession::query()->findOrFail($original->sessionId);
    $oldAccessId = $session->current_access_token_id;
    $oldRefresh = NativeRefreshToken::query()->where('native_device_session_id', $session->id)->sole();
    CarbonImmutable::setTestNow('2026-07-17 11:00:00 UTC');

    $rotated = app(NativeSessionService::class)->rotate(
        $original->refreshToken,
        nativeDeviceContext('Renamed iPhone', '1.1.0', '110'),
    );

    $session->refresh();
    $oldRefresh->refresh();
    $newRefresh = NativeRefreshToken::query()
        ->where('native_device_session_id', $session->id)
        ->whereNull('used_at')
        ->sole();

    expect($rotated->sessionId)->toBe($original->sessionId)
        ->and($rotated->accessToken)->not->toBe($original->accessToken)
        ->and($rotated->refreshToken)->not->toBe($original->refreshToken)
        ->and($oldRefresh->used_at?->equalTo('2026-07-17 11:00:00 UTC'))->toBeTrue()
        ->and(PersonalAccessToken::query()->find($oldAccessId))->toBeNull()
        ->and(PersonalAccessToken::findToken($rotated->accessToken)?->id)->toBe($session->current_access_token_id)
        ->and($newRefresh->getRawOriginal('token_hash'))->toBe(hash('sha256', $rotated->refreshToken))
        ->and($session->last_used_at?->equalTo('2026-07-17 11:00:00 UTC'))->toBeTrue()
        ->and($session->device_label)->toBe('Renamed iPhone')
        ->and($session->app_version)->toBe('1.1.0')
        ->and($session->app_build)->toBe('110');
});

it('caps a rotated refresh credential at the family absolute expiry', function (): void {
    $original = nativeExchange();
    $session = NativeDeviceSession::query()->findOrFail($original->sessionId);
    $session->forceFill(['absolute_expires_at' => CarbonImmutable::parse('2026-07-27 10:00:00 UTC')])->save();
    CarbonImmutable::setTestNow('2026-07-25 10:00:00 UTC');

    $rotated = app(NativeSessionService::class)->rotate($original->refreshToken, nativeDeviceContext());
    $absoluteExpiresAt = CarbonImmutable::instance($session->fresh()->absolute_expires_at);

    expect($rotated->refreshExpiresAt->equalTo($absoluteExpiresAt))->toBeTrue()
        ->and($rotated->refreshExpiresAt->lessThan(CarbonImmutable::now()->addDays(30)))->toBeTrue();
});

it('caps a rotated access credential at the family absolute expiry', function (): void {
    $original = nativeExchange();
    $session = NativeDeviceSession::query()->findOrFail($original->sessionId);
    $session->forceFill(['absolute_expires_at' => CarbonImmutable::now()->addMinutes(5)])->save();
    $absoluteExpiresAt = CarbonImmutable::instance($session->fresh()->absolute_expires_at);

    $rotated = app(NativeSessionService::class)->rotate($original->refreshToken, nativeDeviceContext());
    $access = PersonalAccessToken::findToken($rotated->accessToken);

    expect($rotated->accessExpiresAt->equalTo($absoluteExpiresAt))->toBeTrue()
        ->and($access?->expires_at?->equalTo($absoluteExpiresAt))->toBeTrue();
});

it('returns the generic invalid error for unknown and revoked families', function (): void {
    $unknown = captureNativeSessionError(
        fn () => app(NativeSessionService::class)->rotate('unknown', nativeDeviceContext())
    );

    $credentials = nativeExchange();
    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    app(NativeSessionService::class)->revoke($session, 'user_logout');
    $revoked = captureNativeSessionError(
        fn () => app(NativeSessionService::class)->rotate($credentials->refreshToken, nativeDeviceContext())
    );

    expect($unknown->errorCode)->toBe('native_session_invalid')
        ->and($unknown->getStatusCode())->toBe(401)
        ->and($revoked->errorCode)->toBe('native_session_invalid')
        ->and($revoked->getStatusCode())->toBe(401);
});

it('commits family revocation before surfacing an expired refresh error', function (): void {
    $credentials = nativeExchange();
    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    $accessId = $session->current_access_token_id;
    NativeRefreshToken::query()
        ->where('native_device_session_id', $session->id)
        ->update(['expires_at' => CarbonImmutable::parse('2026-07-17 09:59:59 UTC')]);

    $error = captureNativeSessionError(
        fn () => app(NativeSessionService::class)->rotate($credentials->refreshToken, nativeDeviceContext())
    );

    expect($error->errorCode)->toBe('native_session_expired')
        ->and($error->getStatusCode())->toBe(401)
        ->and($session->fresh()?->revoked_at)->not->toBeNull()
        ->and($session->fresh()?->revoke_reason)->toBe('refresh_expired')
        ->and(NativeRefreshToken::query()->where('native_device_session_id', $session->id)->whereNull('revoked_at')->exists())->toBeFalse()
        ->and(PersonalAccessToken::query()->find($accessId))->toBeNull();
});

it('commits replay revocation after a consumed refresh credential is reused', function (): void {
    $credentials = nativeExchange();
    app(NativeSessionService::class)->rotate($credentials->refreshToken, nativeDeviceContext());

    $error = captureNativeSessionError(
        fn () => app(NativeSessionService::class)->rotate($credentials->refreshToken, nativeDeviceContext())
    );

    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);

    expect($error->errorCode)->toBe('native_session_replayed')
        ->and($error->getStatusCode())->toBe(401)
        ->and($session->revoked_at)->not->toBeNull()
        ->and($session->revoke_reason)->toBe('refresh_replayed')
        ->and($session->current_access_token_id)->toBeNull()
        ->and(NativeRefreshToken::query()->where('native_device_session_id', $session->id)->whereNull('used_at')->whereNull('revoked_at')->exists())->toBeFalse();
});

it('commits revocation at absolute expiry and requires a full login', function (): void {
    $credentials = nativeExchange();
    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    $accessId = $session->current_access_token_id;
    $session->forceFill(['absolute_expires_at' => CarbonImmutable::now()])->save();

    $error = captureNativeSessionError(
        fn () => app(NativeSessionService::class)->rotate($credentials->refreshToken, nativeDeviceContext())
    );

    expect($error->errorCode)->toBe('native_full_login_required')
        ->and($error->getStatusCode())->toBe(401)
        ->and($session->fresh()?->revoked_at)->not->toBeNull()
        ->and($session->fresh()?->revoke_reason)->toBe('absolute_expired')
        ->and(PersonalAccessToken::query()->find($accessId))->toBeNull();
});

it('logs revocation with only the session uuid and reason', function (): void {
    $credentials = nativeExchange();
    $session = NativeDeviceSession::query()->findOrFail($credentials->sessionId);
    Log::spy();

    app(NativeSessionService::class)->revoke($session, 'user_logout');

    Log::shouldHaveReceived('notice')->once()->with(
        'Native session revoked',
        ['session_uuid' => $session->id, 'reason' => 'user_logout'],
    );
});

it('serializes simultaneous exchanges so one bootstrap creates only one family', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('The deterministic MySQL concurrency test requires pcntl and sockets.');
    }

    [$user, $bootstrap] = nativeBootstrap();
    $userId = $user->id;
    $bootstrapId = $bootstrap->id;
    DB::commit();

    $blockerName = 'native_exchange_blocker';
    config(["database.connections.{$blockerName}" => config('database.connections.mysql')]);
    $blocker = DB::connection($blockerName);
    $blocker->beginTransaction();
    $blocker->table('personal_access_tokens')->where('id', $bootstrapId)->lockForUpdate()->first();
    $workers = [];

    try {
        foreach ([1, 2] as $workerNumber) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            expect($sockets)->not->toBeFalse();
            $pid = pcntl_fork();
            expect($pid)->toBeGreaterThanOrEqual(0);

            if ($pid === 0) {
                fclose($sockets[0]);
                DB::purge();
                DB::connection()->getPdo();
                fwrite($sockets[1], "ready\n");
                fgets($sockets[1]);
                fwrite($sockets[1], "exchanging\n");

                try {
                    $issued = app(NativeSessionService::class)->exchange(
                        $user,
                        $bootstrap,
                        nativeDeviceContext("Exchange worker {$workerNumber}"),
                    );
                    $result = ['status' => 'success', 'session_id' => $issued->sessionId];
                } catch (NativeSessionException $exception) {
                    $result = ['status' => $exception->errorCode];
                } catch (Throwable $exception) {
                    $result = ['status' => 'unexpected', 'type' => $exception::class, 'message' => $exception->getMessage()];
                }

                fwrite($sockets[1], json_encode($result, JSON_THROW_ON_ERROR)."\n");
                fclose($sockets[1]);
                exit(0);
            }

            fclose($sockets[1]);
            $workers[] = ['pid' => $pid, 'socket' => $sockets[0]];
        }

        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('ready');
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "go\n");
        }
        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('exchanging');
        }
        $blocker->commit();

        $results = [];
        foreach ($workers as $worker) {
            $results[] = json_decode((string) fgets($worker['socket']), true, flags: JSON_THROW_ON_ERROR);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            expect(pcntl_wexitstatus($status))->toBe(0);
        }

        DB::purge();
        DB::connection()->getPdo();
        expect(collect($results)->pluck('status')->sort()->values()->all())->toBe([
            'native_session_invalid',
            'success',
        ])->and(NativeDeviceSession::query()->where('user_id', $userId)->count())->toBe(1);
    } finally {
        if ($blocker->transactionLevel() > 0) {
            $blocker->rollBack();
        }
        DB::purge($blockerName);
        DB::purge();
        DB::connection()->getPdo();
        cleanupCommittedNativeFixture($userId);
        DB::beginTransaction();
    }
});

it('serializes simultaneous rotations to one success and one replay revocation', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('The deterministic MySQL concurrency test requires pcntl and sockets.');
    }

    $credentials = nativeExchange();
    $sessionId = $credentials->sessionId;
    $userId = NativeDeviceSession::query()->findOrFail($sessionId)->user_id;
    $refreshId = NativeRefreshToken::query()
        ->where('native_device_session_id', $sessionId)
        ->sole()
        ->id;

    // Publish the fixture so independent worker connections can contend on it.
    DB::commit();

    $blockerName = 'native_concurrency_blocker';
    config(["database.connections.{$blockerName}" => config('database.connections.mysql')]);
    $blocker = DB::connection($blockerName);
    $blocker->beginTransaction();
    $blocker->table('native_refresh_tokens')->where('id', $refreshId)->lockForUpdate()->first();

    $workers = [];

    try {
        foreach ([1, 2] as $workerNumber) {
            $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            expect($sockets)->not->toBeFalse();
            $pid = pcntl_fork();
            expect($pid)->toBeGreaterThanOrEqual(0);

            if ($pid === 0) {
                fclose($sockets[0]);
                DB::purge();
                DB::connection()->getPdo();
                fwrite($sockets[1], "ready\n");
                fgets($sockets[1]);
                fwrite($sockets[1], "rotating\n");

                try {
                    $rotated = app(NativeSessionService::class)->rotate(
                        $credentials->refreshToken,
                        nativeDeviceContext("Worker {$workerNumber}"),
                    );
                    $result = ['status' => 'success', 'refresh' => $rotated->refreshToken];
                } catch (NativeSessionException $exception) {
                    $result = ['status' => $exception->errorCode];
                } catch (Throwable $exception) {
                    $result = ['status' => 'unexpected', 'type' => $exception::class, 'message' => $exception->getMessage()];
                }

                fwrite($sockets[1], json_encode($result, JSON_THROW_ON_ERROR)."\n");
                fclose($sockets[1]);
                exit(0);
            }

            fclose($sockets[1]);
            $workers[] = ['pid' => $pid, 'socket' => $sockets[0]];
        }

        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('ready');
        }
        foreach ($workers as $worker) {
            fwrite($worker['socket'], "go\n");
        }
        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('rotating');
        }

        // Both independent service calls are now released against a row held
        // FOR UPDATE; releasing it forces MySQL to serialize their decisions.
        $blocker->commit();

        $results = [];
        foreach ($workers as $worker) {
            $results[] = json_decode((string) fgets($worker['socket']), true, flags: JSON_THROW_ON_ERROR);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            expect(pcntl_wexitstatus($status))->toBe(0);
        }

        DB::purge();
        DB::connection()->getPdo();

        expect(collect($results)->pluck('status')->sort()->values()->all())->toBe([
            'native_session_replayed',
            'success',
        ]);

        $session = NativeDeviceSession::query()->findOrFail($sessionId);
        expect($session->revoked_at)->not->toBeNull()
            ->and($session->revoke_reason)->toBe('refresh_replayed')
            ->and($session->current_access_token_id)->toBeNull()
            ->and(NativeRefreshToken::query()->where('native_device_session_id', $sessionId)->whereNull('used_at')->whereNull('revoked_at')->count())->toBe(0);
    } finally {
        if ($blocker->transactionLevel() > 0) {
            $blocker->rollBack();
        }
        DB::purge($blockerName);
        DB::purge();
        DB::connection()->getPdo();
        cleanupCommittedNativeFixture($userId);
        DB::beginTransaction();
    }
});

it('avoids deadlock when replay races the current refresh credential', function (): void {
    if (! function_exists('pcntl_fork') || ! function_exists('stream_socket_pair')) {
        $this->markTestSkipped('The deterministic MySQL concurrency test requires pcntl and sockets.');
    }

    $original = nativeExchange();
    $current = app(NativeSessionService::class)->rotate($original->refreshToken, nativeDeviceContext());
    $sessionId = $original->sessionId;
    $userId = NativeDeviceSession::query()->findOrFail($sessionId)->user_id;
    DB::commit();

    $workers = [];
    foreach ([
        ['label' => 'replay', 'token' => $original->refreshToken],
        ['label' => 'current', 'token' => $current->refreshToken],
    ] as $workerInput) {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        expect($sockets)->not->toBeFalse();
        $pid = pcntl_fork();
        expect($pid)->toBeGreaterThanOrEqual(0);

        if ($pid === 0) {
            fclose($sockets[0]);
            DB::purge();
            DB::connection()->getPdo();
            fwrite($sockets[1], "ready\n");
            fgets($sockets[1]);
            fwrite($sockets[1], "rotating\n");

            try {
                app(NativeSessionService::class)->rotate(
                    $workerInput['token'],
                    nativeDeviceContext(ucfirst($workerInput['label']).' worker'),
                );
                $result = ['status' => 'success'];
            } catch (NativeSessionException $exception) {
                $result = ['status' => $exception->errorCode];
            } catch (Throwable $exception) {
                $result = ['status' => 'unexpected', 'type' => $exception::class, 'message' => $exception->getMessage()];
            }

            fwrite($sockets[1], json_encode($result, JSON_THROW_ON_ERROR)."\n");
            fclose($sockets[1]);
            exit(0);
        }

        fclose($sockets[1]);
        $workers[$workerInput['label']] = ['pid' => $pid, 'socket' => $sockets[0]];
    }

    $blockerName = 'native_replay_current_blocker';
    config(["database.connections.{$blockerName}" => config('database.connections.mysql')]);
    $blocker = DB::connection($blockerName);
    $blocker->beginTransaction();
    $blocker->table('native_device_sessions')->where('id', $sessionId)->lockForUpdate()->first();

    try {
        foreach ($workers as $worker) {
            expect(trim((string) fgets($worker['socket'])))->toBe('ready');
        }

        fwrite($workers['replay']['socket'], "go\n");
        expect(trim((string) fgets($workers['replay']['socket'])))->toBe('rotating');
        usleep(100_000);
        fwrite($workers['current']['socket'], "go\n");
        expect(trim((string) fgets($workers['current']['socket'])))->toBe('rotating');
        usleep(100_000);
        $blocker->commit();

        $results = [];
        foreach ($workers as $label => $worker) {
            $results[$label] = json_decode((string) fgets($worker['socket']), true, flags: JSON_THROW_ON_ERROR);
            fclose($worker['socket']);
            pcntl_waitpid($worker['pid'], $status);
            expect(pcntl_wexitstatus($status))->toBe(0);
        }

        DB::purge();
        DB::connection()->getPdo();
        expect(collect($results)->pluck('status')->contains('unexpected'))->toBeFalse()
            ->and(collect($results)->pluck('status')->contains('native_session_replayed'))->toBeTrue()
            ->and(collect($results)->pluck('status')->diff([
                'success',
                'native_session_invalid',
                'native_session_replayed',
            ])->isEmpty())->toBeTrue();

        $session = NativeDeviceSession::query()->findOrFail($sessionId);
        expect($session->revoked_at)->not->toBeNull()
            ->and($session->revoke_reason)->toBe('refresh_replayed')
            ->and($session->current_access_token_id)->toBeNull()
            ->and(NativeRefreshToken::query()->where('native_device_session_id', $sessionId)->whereNull('used_at')->whereNull('revoked_at')->count())->toBe(0);
    } finally {
        if ($blocker->transactionLevel() > 0) {
            $blocker->rollBack();
        }
        DB::purge($blockerName);
        DB::purge();
        DB::connection()->getPdo();
        cleanupCommittedNativeFixture($userId);
        DB::beginTransaction();
    }
});
