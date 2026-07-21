<?php

declare(strict_types=1);

use App\Http\Middleware\IdentifyNativeClient;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

function nativeClientHeaders(array $overrides = []): array
{
    return array_merge([
        'X-Fynla-Client' => 'ios',
        'X-Fynla-Version' => '1.0.0',
        'X-Fynla-Build' => '1',
    ], $overrides);
}

it('accepts the native health route with valid iOS client headers', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/native/health', nativeClientHeaders())
        ->assertOk()
        ->assertExactJson([
            'success' => true,
            'data' => ['api_version' => 'v1'],
        ]);
});

it('requires authentication before native client identification', function (): void {
    $this->getJson('/api/v1/native/health', nativeClientHeaders())
        ->assertUnauthorized();
});

it('normalises validated native headers into request attributes', function (): void {
    $request = Request::create('/api/v1/native/health', 'GET', server: [
        'HTTP_X_FYNLA_CLIENT' => 'ios',
        'HTTP_X_FYNLA_VERSION' => '1.2.3',
        'HTTP_X_FYNLA_BUILD' => '42',
    ]);

    $response = app(IdentifyNativeClient::class)->handle(
        $request,
        fn (Request $validated) => response()->json([
            'client' => $validated->attributes->get('fynla_client'),
            'version' => $validated->attributes->get('fynla_version'),
            'build' => $validated->attributes->get('fynla_build'),
        ]),
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getData(true))->toBe([
            'client' => 'ios',
            'version' => '1.2.3',
            'build' => 42,
        ]);
});

it('rejects missing or malformed native client headers', function (array $headers): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/native/health', $headers)
        ->assertStatus(400)
        ->assertJson([
            'success' => false,
            'error' => 'invalid_native_client',
        ]);
})->with([
    'all missing' => [[]],
    'wrong client' => [nativeClientHeaders(['X-Fynla-Client' => 'android'])],
    'non-semantic version' => [nativeClientHeaders(['X-Fynla-Version' => '1.0'])],
    'version suffix' => [nativeClientHeaders(['X-Fynla-Version' => '1.0.0-beta'])],
    'zero build' => [nativeClientHeaders(['X-Fynla-Build' => '0'])],
    'negative build' => [nativeClientHeaders(['X-Fynla-Build' => '-1'])],
    'non-numeric build' => [nativeClientHeaders(['X-Fynla-Build' => 'one'])],
]);

it('does not apply native header validation to existing web mobile or webhook routes', function (): void {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/user')
        ->assertOk();

    $this->getJson('/api/v1/mobile/dashboard')
        ->assertOk();

    $webhook = $this->postJson('/api/webhooks/revolut');
    expect($webhook->status())->not->toBe(400)
        ->and($webhook->json('error'))->not->toBe('invalid_native_client');
});
