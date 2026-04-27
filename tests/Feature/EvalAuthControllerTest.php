<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

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

    $response = $this->withToken($token)->postJson('/api/eval/reset/peak_earners');

    $response->assertOk()->assertJsonPath('reset', 'peak_earners');
    expect(User::where('preview_persona_id', 'peak_earners')->exists())->toBeTrue();
});

it('trace endpoint returns the collector contents for the calling user', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    $token = $user->createToken('trace-test', ['bypass-preview-mode']);
    $user->withAccessToken($token->accessToken);
    $this->actingAs($user);

    event(new \App\Events\Eval\GateChecked('kyc', 'global', true, ['field' => 'dob'], microtime(true)));

    $conv = \App\Models\AiConversation::create([
        'user_id' => $user->id,
        'title' => 'trace test',
        'status' => 'active',
        'message_count' => 0,
        'model_used' => 'claude-haiku-4-5-20251001',
    ]);

    $response = $this->withToken($token->plainTextToken)->getJson("/api/eval/trace/{$conv->id}");

    $response->assertOk()
        ->assertJsonStructure(['conversation_id', 'events'])
        ->assertJsonPath('conversation_id', $conv->id);

    expect($response->json('events'))->toBeArray();
});

it('trace endpoint returns 404 for a conversation not owned by the calling user', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    $token = $user->createToken('trace-mismatch', ['bypass-preview-mode'])->plainTextToken;

    $other = User::factory()->create();
    $conv = \App\Models\AiConversation::create([
        'user_id' => $other->id,
        'title' => 'someone else',
        'status' => 'active',
        'message_count' => 0,
        'model_used' => 'claude-haiku-4-5-20251001',
    ]);

    $this->withToken($token)->getJson("/api/eval/trace/{$conv->id}")->assertStatus(404);
});
