<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'PreviewUserSeeder']);
});

it('preview user without bypass ability gets writes intercepted by middleware', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    $token = $user->createToken('test-no-bypass')->plainTextToken;

    $countBefore = SavingsAccount::where('user_id', $user->id)->count();

    $response = $this->withToken($token)->postJson('/api/savings/accounts', [
        'institution' => 'Test Bank',
        'account_type' => 'easy_access',
        'current_balance' => 1000,
    ]);

    $response->assertOk();
    expect($response->json('preview_mode'))->toBeTrue()
        ->and(SavingsAccount::where('user_id', $user->id)->count())
        ->toBe($countBefore, 'preview-mode interceptor should swallow the write');
});

it('preview user WITH bypass-preview-mode ability + X-Eval-Run-Id header gets writes through', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    $token = $user->createToken('test-bypass', ['bypass-preview-mode'])->plainTextToken;

    $countBefore = SavingsAccount::where('user_id', $user->id)->count();

    $response = $this->withToken($token)
        ->withHeaders(['X-Eval-Run-Id' => 'test-run-'.uniqid()])
        ->postJson('/api/savings/accounts', [
            'institution' => 'Test Bank',
            'account_type' => 'easy_access',
            'current_balance' => 1000,
        ]);

    $response->assertSuccessful();

    expect(SavingsAccount::where('user_id', $user->id)->count())
        ->toBe($countBefore + 1, 'eval bypass token + run-id header must let the write through')
        ->and($response->json('preview_mode'))->not->toBeTrue('real write must not return preview_mode envelope');
});

it('preview user WITH bypass ability but WITHOUT X-Eval-Run-Id header is intercepted (F-12)', function () {
    $user = User::where('preview_persona_id', 'peak_earners')->firstOrFail();
    $token = $user->createToken('test-bypass-no-header', ['bypass-preview-mode'])->plainTextToken;

    $countBefore = SavingsAccount::where('user_id', $user->id)->count();

    $response = $this->withToken($token)->postJson('/api/savings/accounts', [
        'institution' => 'Test Bank',
        'account_type' => 'easy_access',
        'current_balance' => 1000,
    ]);

    $response->assertOk();
    expect($response->json('preview_mode'))->toBeTrue('leaked token alone must not bypass — F-12 requires X-Eval-Run-Id header')
        ->and(SavingsAccount::where('user_id', $user->id)->count())
        ->toBe($countBefore, 'no header → preview interceptor still swallows the write');
});

it('non-preview user is unaffected by ability — writes always go through', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $token = $user->createToken('test-non-preview')->plainTextToken;

    $countBefore = SavingsAccount::where('user_id', $user->id)->count();

    $response = $this->withToken($token)->postJson('/api/savings/accounts', [
        'institution' => 'Test Bank',
        'account_type' => 'easy_access',
        'current_balance' => 1000,
    ]);

    $response->assertSuccessful();

    expect(SavingsAccount::where('user_id', $user->id)->count())
        ->toBe($countBefore + 1);
});
