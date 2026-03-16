<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\WillDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WillDocument>
 */
class WillDocumentFactory extends Factory
{
    protected $model = WillDocument::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'will_type' => 'simple',
            'status' => 'draft',
            'testator_full_name' => fake()->name(),
            'testator_address' => fake()->address(),
            'testator_date_of_birth' => fake()->dateTimeBetween('-70 years', '-18 years'),
            'testator_occupation' => fake()->jobTitle(),
            'executors' => [
                ['name' => fake()->name(), 'address' => fake()->address(), 'relationship' => 'Brother', 'phone' => fake()->phoneNumber()],
            ],
            'guardians' => null,
            'specific_gifts' => null,
            'residuary_estate' => [
                ['beneficiary_name' => fake()->name(), 'percentage' => 100, 'substitution_beneficiary' => ''],
            ],
            'funeral_preference' => null,
            'funeral_wishes_notes' => null,
            'digital_executor_name' => null,
            'digital_assets_instructions' => null,
            'survivorship_days' => 28,
            'domicile_confirmed' => 'england_wales',
        ];
    }

    public function simple(): static
    {
        return $this->state(fn () => ['will_type' => 'simple']);
    }

    public function mirror(): static
    {
        return $this->state(fn () => ['will_type' => 'mirror']);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft', 'generated_at' => null]);
    }

    public function complete(): static
    {
        return $this->state(fn () => [
            'status' => 'complete',
            'generated_at' => now(),
            'last_edited_at' => now(),
        ]);
    }

    public function withGifts(): static
    {
        return $this->state(fn () => [
            'specific_gifts' => [
                ['beneficiary_name' => 'John Smith', 'type' => 'cash', 'amount' => 5000, 'description' => '', 'conditions' => ''],
                ['beneficiary_name' => 'Jane Doe', 'type' => 'item', 'amount' => null, 'description' => 'My gold watch', 'conditions' => ''],
            ],
        ]);
    }

    public function withGuardians(): static
    {
        return $this->state(fn () => [
            'guardians' => [
                ['name' => fake()->name(), 'address' => fake()->address(), 'relationship' => 'Sister'],
            ],
        ]);
    }

    public function withTwoBeneficiaries(): static
    {
        return $this->state(fn () => [
            'residuary_estate' => [
                ['beneficiary_name' => 'Child One', 'percentage' => 50, 'substitution_beneficiary' => 'Their children equally'],
                ['beneficiary_name' => 'Child Two', 'percentage' => 50, 'substitution_beneficiary' => 'Their children equally'],
            ],
        ]);
    }
}
