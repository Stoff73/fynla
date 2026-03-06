<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserMassAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function is_admin_attribute_is_not_mass_assignable()
    {
        $userData = [
            'first_name' => 'Test',
            'surname' => 'User',
            'email' => 'test@example.com',
            'password' => 'password',
            'is_admin' => true,
        ];

        // Attempt to create user with mass assignment
        $user = User::create($userData);

        // Assert that the user was created but is_admin is false (default) or null (if no default)
        // Key security verification is that it is NOT true
        $this->assertNotTrue($user->is_admin, 'is_admin should not be mass assignable');
        $this->assertEquals('Test User', $user->name);
    }

    /** @test */
    public function is_admin_cannot_be_updated_via_mass_assignment()
    {
        $user = User::factory()->create(['is_admin' => false]);

        $user->update(['is_admin' => true]);

        $this->assertFalse($user->fresh()->is_admin, 'is_admin should not be updateable via mass assignment');
    }
}
