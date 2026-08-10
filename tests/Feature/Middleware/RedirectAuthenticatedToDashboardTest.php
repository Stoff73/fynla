<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps help reachable for an authenticated account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/help')
        ->assertOk()
        ->assertSee('Help');
});

it('still redirects authenticated accounts away from marketing pages', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect('/dashboard');
});
