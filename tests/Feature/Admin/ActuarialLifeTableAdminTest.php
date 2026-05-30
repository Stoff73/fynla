<?php

declare(strict_types=1);

use App\Models\ActuarialLifeTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    Sanctum::actingAs($this->admin);
});

it('lists actuarial life table rows', function () {
    // Distinct ages so the three rows can never collide on the
    // UNIQUE(age, gender, table_year) key (the factory randomises age + gender
    // within a fixed table_year, so count(3) alone is a ~1.5% flake).
    ActuarialLifeTable::factory()
        ->count(3)
        ->sequence(['age' => 60], ['age' => 65], ['age' => 70])
        ->create();

    $this->getJson('/api/admin/actuarial-life-tables')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a row via the store', function () {
    $payload = [
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.00989,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS National Life Tables',
    ];

    $this->postJson('/api/admin/actuarial-life-tables', $payload)
        ->assertCreated()
        ->assertJsonPath('data.age', 65)
        ->assertJsonPath('data.gender', 'male')
        ->assertJsonPath('data.life_expectancy_years', 18.3);

    $this->assertDatabaseHas('actuarial_life_tables', [
        'age' => 65,
        'gender' => 'male',
        'table_year' => '2020-2022',
    ]);
});

it('updates a row via the store', function () {
    $row = ActuarialLifeTable::factory()->create([
        'age' => 65,
        'gender' => 'male',
        'life_expectancy_years' => 18.3,
    ]);

    $this->patchJson("/api/admin/actuarial-life-tables/{$row->id}", ['life_expectancy_years' => 19.5])
        ->assertOk()
        ->assertJsonPath('data.life_expectancy_years', 19.5);

    $this->assertDatabaseHas('actuarial_life_tables', [
        'id' => $row->id,
        'life_expectancy_years' => 19.5,
    ]);
});

it('deletes a row via the store', function () {
    $row = ActuarialLifeTable::factory()->create();

    $this->deleteJson("/api/admin/actuarial-life-tables/{$row->id}")->assertOk();

    $this->assertDatabaseMissing('actuarial_life_tables', ['id' => $row->id]);
});

it('returns 422 for missing required fields', function () {
    $this->postJson('/api/admin/actuarial-life-tables', [
        'age' => 65,
        'gender' => 'male',
        // life_expectancy_years missing
        'probability_of_death' => 0.01,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS',
    ])->assertStatus(422);
});

it('returns 422 for unknown gender', function () {
    $this->postJson('/api/admin/actuarial-life-tables', [
        'age' => 65,
        'gender' => 'nonbinary',
        'life_expectancy_years' => 18.3,
        'probability_of_death' => 0.01,
        'table_year' => '2020-2022',
        'table_source' => 'UK ONS',
    ])->assertStatus(422);
});

it('returns 403 for non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/admin/actuarial-life-tables')->assertForbidden();
});
