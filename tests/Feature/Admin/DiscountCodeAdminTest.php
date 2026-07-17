<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create([
        'is_admin' => true,
        'email_verified_at' => now(),
    ]));
});

it('creates monetary discount codes', function () {
    $this->postJson('/api/admin/discount-codes', [
        'code' => 'SAVE20',
        'type' => 'percentage',
        'value' => 20,
        'max_uses_per_user' => 1,
        'is_active' => true,
    ])->assertCreated()
        ->assertJsonPath('data.type', 'percentage');
});

it('rejects the retired trial extension product', function () {
    $this->postJson('/api/admin/discount-codes', [
        'code' => 'OLDTRIAL',
        'type' => 'trial_extension',
        'value' => 14,
        'max_uses_per_user' => 1,
        'is_active' => true,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('type');
});
