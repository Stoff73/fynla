<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

describe('AuditLog::log foreign-key resilience', function () {
    it('writes user_id when caller passes a valid user id', function () {
        $user = User::factory()->create();

        $log = AuditLog::log(
            AuditLog::EVENT_AUTH,
            AuditLog::ACTION_LOGIN_SUCCESS,
            $user->id,
        );

        expect($log->user_id)->toBe($user->id);
    });

    it('writes user_id when caller passes a soft-deleted user id', function () {
        $user = User::factory()->create();
        $deletedId = $user->id;
        $user->delete();

        $log = AuditLog::log(
            AuditLog::EVENT_AUTH,
            AuditLog::ACTION_LOGOUT,
            $deletedId,
        );

        expect($log->user_id)->toBe($deletedId);
    });

    it('falls back to null user_id when caller passes a hard-deleted user id', function () {
        $user = User::factory()->create();
        $deletedId = $user->id;
        $user->forceDelete();

        $log = AuditLog::log(
            AuditLog::EVENT_AUTH,
            AuditLog::ACTION_LOGIN_FAILED,
            $deletedId,
            null,
            null,
            null,
            null,
            ['reason' => 'stale_session_after_force_delete']
        );

        expect($log->user_id)->toBeNull();
        expect($log->event_type)->toBe(AuditLog::EVENT_AUTH);
        expect($log->action)->toBe(AuditLog::ACTION_LOGIN_FAILED);
        expect($log->metadata)->toBe(['reason' => 'stale_session_after_force_delete']);
    });

    it('falls back to null when auth() points at a hard-deleted user', function () {
        $user = User::factory()->create();
        $deletedId = $user->id;
        test()->actingAs($user);
        $user->forceDelete();

        $log = AuditLog::log(
            AuditLog::EVENT_DATA_CHANGE,
            AuditLog::ACTION_CREATED,
        );

        expect($log->user_id)->toBeNull();
    });

    it('writes a null user_id when no caller and no auth', function () {
        $log = AuditLog::log(
            AuditLog::EVENT_AUTH,
            AuditLog::ACTION_LOGIN_FAILED,
        );

        expect($log->user_id)->toBeNull();
    });

    it('writes user_id for the authenticated user when caller does not pass one', function () {
        $user = User::factory()->create();
        test()->actingAs($user);

        $log = AuditLog::log(
            AuditLog::EVENT_AUTH,
            AuditLog::ACTION_LOGIN_SUCCESS,
        );

        expect($log->user_id)->toBe($user->id);
    });
});
