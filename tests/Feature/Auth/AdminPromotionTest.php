<?php

declare(strict_types=1);

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    Role::firstOrCreate(['name' => Role::ROLE_USER], ['display_name' => 'User']);
    Role::firstOrCreate(['name' => Role::ROLE_ADMIN], ['display_name' => 'Administrator']);
});

describe('Admin auto-promotion at login (REVIEW.md Top-10 #6)', function () {
    it('does NOT promote a user to admin even if their email is in ADMIN_EMAILS', function () {
        config(['auth.admin_emails' => ['attacker@example.com']]);

        $user = User::factory()->create([
            'email' => 'attacker@example.com',
            'password' => bcrypt('password123'),
            'is_preview_user' => true,
            'is_admin' => false,
            'role_id' => Role::findByName(Role::ROLE_USER)->id,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'attacker@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200);

        $user->refresh();
        expect($user->is_admin)->toBeFalse()
            ->and($user->role_id)->toBe(Role::findByName(Role::ROLE_USER)->id);
    });

    it('keeps existing admin flags intact at login (does not demote either)', function () {
        config(['auth.admin_emails' => []]);

        $adminRole = Role::findByName(Role::ROLE_ADMIN);

        $admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'is_preview_user' => true,
            'is_admin' => true,
            'role_id' => $adminRole->id,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertStatus(200);

        $admin->refresh();
        expect($admin->is_admin)->toBeTrue()
            ->and($admin->role_id)->toBe($adminRole->id);
    });
});
