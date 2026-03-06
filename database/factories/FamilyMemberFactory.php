<?php

namespace Database\Factories;

use App\Models\FamilyMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FamilyMember>
 */
class FamilyMemberFactory extends Factory
{
    protected $model = FamilyMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'relationship' => fake()->randomElement(['spouse', 'child', 'parent', 'other_dependent']),
            'name' => $firstName.' '.$lastName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date_of_birth' => fake()->date(),
            'gender' => fake()->randomElement(['male', 'female', 'other']),
            'is_dependent' => false,
        ];
    }

    /**
     * Configure the factory as a child.
     */
    public function child(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'child',
            'is_dependent' => true,
        ]);
    }

    /**
     * Configure the factory as a parent.
     */
    public function parent(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'parent',
            'is_dependent' => false,
        ]);
    }

    /**
     * Configure the factory as a spouse.
     */
    public function spouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'relationship' => 'spouse',
            'is_dependent' => false,
        ]);
    }
}
