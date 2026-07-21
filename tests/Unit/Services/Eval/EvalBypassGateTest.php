<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Eval\EvalBypassGate;
use Laravel\Sanctum\TransientToken;

describe('EvalBypassGate::isActive', function () {
    it('returns false when user is null', function () {
        expect(EvalBypassGate::isActive(null))->toBeFalse();
    });

    it('returns false when user has no current access token', function () {
        $user = User::factory()->create();
        // No token attached — currentAccessToken() returns null.

        expect(EvalBypassGate::isActive($user))->toBeFalse();
    });

    describe('TransientToken (stateful SPA auth) handling', function () {
        it('fails closed when current token is a TransientToken', function () {
            $user = User::factory()->create();
            $user->withAccessToken(new TransientToken);

            // Request header is irrelevant — guard short-circuits first.
            // Without the instanceof guard, accessing $token->abilities on a
            // TransientToken would either error or report all abilities as
            // granted via TransientToken::can(), defeating the gate.
            request()->headers->set('X-Eval-Run-Id', 'eval-run-test-123');

            expect(EvalBypassGate::isActive($user))->toBeFalse();
        });
    });

    describe('PersonalAccessToken (bearer auth) handling', function () {
        it('returns false when token lacks bypass-preview-mode ability', function () {
            $user = User::factory()->create();
            $token = $user->createToken('test-token', ['read']);
            $user->withAccessToken($token->accessToken);

            request()->headers->set('X-Eval-Run-Id', 'eval-run-test-123');

            expect(EvalBypassGate::isActive($user))->toBeFalse();
        });

        it('returns false when X-Eval-Run-Id header missing', function () {
            $user = User::factory()->create();
            $token = $user->createToken('eval-token', ['bypass-preview-mode']);
            $user->withAccessToken($token->accessToken);

            request()->headers->remove('X-Eval-Run-Id');

            expect(EvalBypassGate::isActive($user))->toBeFalse();
        });

        it('returns true when PAT has ability AND X-Eval-Run-Id header is set', function () {
            $user = User::factory()->create();
            $token = $user->createToken('eval-token', ['bypass-preview-mode']);
            $user->withAccessToken($token->accessToken);

            request()->headers->set('X-Eval-Run-Id', 'eval-run-test-123');

            expect(EvalBypassGate::isActive($user))->toBeTrue();
        });
    });
});
