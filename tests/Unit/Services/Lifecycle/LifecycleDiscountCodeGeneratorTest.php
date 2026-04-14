<?php

declare(strict_types=1);

use App\Models\DiscountCode;
use App\Models\User;
use App\Services\Lifecycle\LifecycleDiscountCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('generate creates a unique code prefixed WELCOME_', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code)->toBeInstanceOf(DiscountCode::class);
    expect($code->code)->toStartWith('WELCOME_');
});

it('generate locks the code to the user via user_id', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->user_id)->toBe($user->id);
});

it('generate sets type to lifecycle_welcome', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->type)->toBe('lifecycle_welcome');
});

it('generate sets max_uses=1 and max_uses_per_user=1', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->max_uses)->toBe(1);
    expect($code->max_uses_per_user)->toBe(1);
});

it('generate sets expires_at to 7 days from now', function () {
    $user = User::factory()->create();

    config(['lifecycle.discount_code_ttl_days' => 7]);

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->expires_at->isAfter(now()->addDays(6)))->toBeTrue();
    expect($code->expires_at->isBefore(now()->addDays(8)))->toBeTrue();
});

it('generate populates metadata with per-plan-per-cycle discount amounts from config', function () {
    config(['lifecycle.campaign2_discounts' => [
        'student.monthly' => 100,
        'standard.monthly' => 500,
    ]]);

    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->metadata)->toBeArray();
    expect($code->metadata['plan_amounts'])->toBe([
        'student.monthly' => 100,
        'standard.monthly' => 500,
    ]);
});

it('generate sets applicable_plans to student/standard/family (no pro)', function () {
    $user = User::factory()->create();

    $service = app(LifecycleDiscountCodeGenerator::class);
    $code = $service->generate($user);

    expect($code->applicable_plans)->toBe(['student', 'standard', 'family']);
});
