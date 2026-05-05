<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'PreviewUserSeeder']);
});

it('returns a Sanctum token with bypass-preview-mode ability for a valid persona', function () {
    $response = $this->postJson('/api/eval/login/peak_earners');

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'persona', 'is_preview_user', 'token_abilities']])
        ->assertJsonPath('user.persona', 'peak_earners')
        ->assertJsonPath('user.is_preview_user', true)
        ->assertJsonPath('user.token_abilities', ['bypass-preview-mode']);

    expect($response->json('token'))->toBeString()->toContain('|');
});

it('refuses in production environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->postJson('/api/eval/login/peak_earners')->assertStatus(403);
});

it('returns 400 for invalid persona', function () {
    $this->postJson('/api/eval/login/not_a_persona')->assertStatus(400);
});

it('returns 404 when persona is not seeded', function () {
    User::where('preview_persona_id', 'student')->forceDelete();

    $this->postJson('/api/eval/login/student')->assertStatus(404);
});

it('reset endpoint runs preview:reset for the persona', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    $token = $user->createToken('reset-test', ['bypass-preview-mode'])->plainTextToken;

    $response = $this->withToken($token)
        ->withHeaders(['X-Eval-Run-Id' => 'test-run-'.uniqid()])
        ->postJson('/api/eval/reset/peak_earners');

    $response->assertOk()->assertJsonPath('reset', 'peak_earners');
    expect(User::where('preview_persona_id', 'peak_earners')->exists())->toBeTrue();
});

it('does not register the legacy /api/eval/trace HTTP endpoint (P0.1 — replaced by cache hand-off)', function () {
    $evalRoutes = collect(Route::getRoutes())
        ->map(fn ($r) => $r->uri())
        ->filter(fn ($uri) => str_starts_with($uri, 'api/eval/'))
        ->values()
        ->all();

    expect($evalRoutes)
        ->toContain('api/eval/login/{personaId}')
        ->toContain('api/eval/reset/{personaId}')
        ->and(collect($evalRoutes)->filter(fn ($u) => str_contains($u, 'trace'))->all())
        ->toBe([]);
});
