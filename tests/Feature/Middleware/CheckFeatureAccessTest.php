<?php

declare(strict_types=1);

use App\Http\Middleware\CheckFeatureAccess;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

beforeEach(function () {
    config(['app.payment_enabled' => true]);
    $this->middleware = new CheckFeatureAccess;
});

function makeRequest(User $user): Request
{
    $request = Request::create('/api/test', 'GET');
    $request->setUserResolver(fn () => $user);

    return $request;
}

function passThrough(): Closure
{
    return fn ($request) => new JsonResponse(['ok' => true]);
}

it('blocks student user from standard-tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->plan('student')->create(['user_id' => $user->id]);
    $user->load('subscription');

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'standard');

    expect($response->getStatusCode())->toBe(403);
    expect($response->getData(true))->toMatchArray([
        'error' => 'upgrade_required',
        'required_plan' => 'standard',
    ]);
});

it('allows standard user to access standard-tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->plan('standard')->create(['user_id' => $user->id]);
    $user->load('subscription');

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'standard');

    expect($response->getStatusCode())->toBe(200);
});

it('blocks standard user from pro-tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->plan('standard')->create(['user_id' => $user->id]);
    $user->load('subscription');

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'pro');

    expect($response->getStatusCode())->toBe(403);
    expect($response->getData(true)['required_plan'])->toBe('pro');
});

it('allows pro user to access all tiers', function () {
    $user = User::factory()->create();
    Subscription::factory()->plan('pro')->create(['user_id' => $user->id]);
    $user->load('subscription');

    expect($this->middleware->handle(makeRequest($user), passThrough(), 'standard')->getStatusCode())->toBe(200);
    expect($this->middleware->handle(makeRequest($user), passThrough(), 'family')->getStatusCode())->toBe(200);
    expect($this->middleware->handle(makeRequest($user), passThrough(), 'pro')->getStatusCode())->toBe(200);
});

it('allows trial users to access all tiers', function () {
    $user = User::factory()->create();
    Subscription::factory()->plan('student')->trialing()->create(['user_id' => $user->id]);
    $user->load('subscription');

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'pro');

    expect($response->getStatusCode())->toBe(200);
});

it('allows preview users to access all tiers', function () {
    $user = User::factory()->create(['is_preview_user' => true]);

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'pro');

    expect($response->getStatusCode())->toBe(200);
});

it('bypasses feature gate when payments are disabled', function () {
    config(['app.payment_enabled' => false]);

    $user = User::factory()->create();
    Subscription::factory()->plan('student')->create(['user_id' => $user->id]);
    $user->load('subscription');

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'pro');

    expect($response->getStatusCode())->toBe(200);
});

it('returns correct error format with required_plan field', function () {
    $user = User::factory()->create();
    Subscription::factory()->plan('student')->create(['user_id' => $user->id]);
    $user->load('subscription');

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'pro');

    expect($response->getStatusCode())->toBe(403);
    $data = $response->getData(true);
    expect($data)->toHaveKeys(['error', 'message', 'required_plan']);
    expect($data['required_plan'])->toBe('pro');
});

it('treats user with no subscription as student tier', function () {
    $user = User::factory()->create();
    // No subscription

    $response = $this->middleware->handle(makeRequest($user), passThrough(), 'standard');

    expect($response->getStatusCode())->toBe(403);
    expect($response->getData(true)['required_plan'])->toBe('standard');
});

it('allows family user to access standard and family tier routes', function () {
    $user = User::factory()->create();
    Subscription::factory()->plan('family')->create(['user_id' => $user->id]);
    $user->load('subscription');

    expect($this->middleware->handle(makeRequest($user), passThrough(), 'standard')->getStatusCode())->toBe(200);
    expect($this->middleware->handle(makeRequest($user), passThrough(), 'family')->getStatusCode())->toBe(200);
});
