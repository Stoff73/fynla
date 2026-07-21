<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureMFAVerified;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

beforeEach(function () {
    $this->middleware = new EnsureMFAVerified;
    $this->next = fn ($request) => response()->json(['ok' => true]);
});

it('returns 401 when user is not authenticated', function () {
    $request = Request::create('/api/test', 'GET');
    $request->setUserResolver(fn () => null);

    $response = $this->middleware->handle($request, $this->next);

    expect($response->getStatusCode())->toBe(401)
        ->and(json_decode($response->getContent(), true)['message'])->toBe('Unauthenticated.');
});

describe('Bearer token path', function () {
    it('passes through when user has mfa disabled and bearer token present', function () {
        $user = User::factory()->create(['mfa_enabled' => false]);
        $token = $user->createToken('test-token');
        $user->withAccessToken($token->accessToken);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token->plainTextToken);
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200);
    });

    it('returns 403 when PAT lacks mfa_verified ability and user has mfa enabled', function () {
        $user = User::factory()->create(['mfa_enabled' => true]);
        $token = $user->createToken('test-token', ['read-only']);
        $user->withAccessToken($token->accessToken);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token->plainTextToken);
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(403)
            ->and(json_decode($response->getContent(), true)['message'])->toBe('MFA verification required.');
    });

    it('passes through when PAT has mfa_verified ability', function () {
        $user = User::factory()->create(['mfa_enabled' => true]);
        $token = $user->createToken('test-token', ['mfa_verified']);
        $user->withAccessToken($token->accessToken);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer '.$token->plainTextToken);
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(200);
    });

    it('fails closed with 403 when bearer header present but token is a TransientToken (stateful SPA)', function () {
        // Edge case: bearer header present (so we enter the PAT branch) but
        // currentAccessToken() resolves to a TransientToken. Without the
        // instanceof guard, TransientToken::can('mfa_verified') returns true
        // unconditionally — bypassing MFA. Must fail closed.
        $user = User::factory()->create(['mfa_enabled' => true]);
        $user->withAccessToken(new TransientToken);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Authorization', 'Bearer fake-token-123');
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(403)
            ->and(json_decode($response->getContent(), true)['message'])->toBe('MFA verification required.');
    });
});

describe('Session path', function () {
    it('returns 403 when mfa enabled and no mfa_verified session flag', function () {
        $user = User::factory()->create(['mfa_enabled' => true]);

        $request = Request::create('/api/test', 'GET');
        // No bearer header → session path
        $request->setUserResolver(fn () => $user);

        $response = $this->middleware->handle($request, $this->next);

        expect($response->getStatusCode())->toBe(403);
        $body = json_decode($response->getContent(), true);
        expect($body['message'])->toBe('MFA verification required.')
            ->and($body['mfa_required'])->toBeTrue();
    });
});
