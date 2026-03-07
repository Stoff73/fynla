<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LetterToSpouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LetterToSpouse>
 */
class LetterToSpouseFactory extends Factory
{
    protected $model = LetterToSpouse::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'immediate_actions' => fake()->optional()->paragraph(),
            'executor_name' => fake()->optional()->name(),
            'executor_contact' => fake()->optional()->phoneNumber(),
            'attorney_name' => fake()->optional()->name(),
            'attorney_contact' => fake()->optional()->phoneNumber(),
            'financial_advisor_name' => fake()->optional()->name(),
            'financial_advisor_contact' => fake()->optional()->phoneNumber(),
            'accountant_name' => fake()->optional()->name(),
            'accountant_contact' => fake()->optional()->phoneNumber(),
            'immediate_funds_access' => fake()->optional()->paragraph(),
            'employer_hr_contact' => fake()->optional()->company(),
            'employer_benefits_info' => fake()->optional()->paragraph(),
            'password_manager_info' => fake()->optional()->sentence(),
            'phone_plan_info' => fake()->optional()->sentence(),
            'bank_accounts_info' => fake()->optional()->paragraph(),
            'investment_accounts_info' => fake()->optional()->paragraph(),
            'insurance_policies_info' => fake()->optional()->paragraph(),
            'real_estate_info' => fake()->optional()->paragraph(),
            'vehicles_info' => fake()->optional()->sentence(),
            'valuable_items_info' => fake()->optional()->sentence(),
            'cryptocurrency_info' => fake()->optional()->sentence(),
            'liabilities_info' => fake()->optional()->paragraph(),
            'recurring_bills_info' => fake()->optional()->paragraph(),
            'estate_documents_location' => fake()->optional()->sentence(),
            'beneficiary_info' => fake()->optional()->paragraph(),
            'children_education_plans' => fake()->optional()->paragraph(),
            'financial_guidance' => fake()->optional()->paragraph(),
            'social_security_info' => fake()->optional()->paragraph(),
            'funeral_preference' => fake()->randomElement(['burial', 'cremation', 'not_specified']),
            'funeral_service_details' => fake()->optional()->paragraph(),
            'obituary_wishes' => fake()->optional()->paragraph(),
            'additional_wishes' => fake()->optional()->paragraph(),
        ];
    }

    /**
     * Letter with executor details filled in.
     */
    public function withExecutor(string $name = 'John Smith', string $contact = '07700 900000'): static
    {
        return $this->state(fn (array $attributes) => [
            'executor_name' => $name,
            'executor_contact' => $contact,
        ]);
    }

    /**
     * Letter with insurance info filled in.
     */
    public function withInsuranceInfo(string $info = 'Aviva Life Policy - Policy LI123456'): static
    {
        return $this->state(fn (array $attributes) => [
            'insurance_policies_info' => $info,
        ]);
    }

    /**
     * Letter with cryptocurrency info filled in.
     */
    public function withCryptocurrency(string $info = 'Bitcoin wallet on Coinbase'): static
    {
        return $this->state(fn (array $attributes) => [
            'cryptocurrency_info' => $info,
        ]);
    }

    /**
     * Letter with vehicle info filled in.
     */
    public function withVehicles(string $info = 'BMW 3 Series, Reg: AB12 CDE'): static
    {
        return $this->state(fn (array $attributes) => [
            'vehicles_info' => $info,
        ]);
    }

    /**
     * Empty letter with no user-entered content.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'executor_name' => null,
            'executor_contact' => null,
            'attorney_name' => null,
            'attorney_contact' => null,
            'insurance_policies_info' => null,
            'vehicles_info' => null,
            'valuable_items_info' => null,
            'cryptocurrency_info' => null,
            'real_estate_info' => null,
            'funeral_preference' => 'not_specified',
        ]);
    }
}
