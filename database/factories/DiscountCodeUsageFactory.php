<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DiscountCode;
use App\Models\DiscountCodeUsage;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCodeUsage>
 */
class DiscountCodeUsageFactory extends Factory
{
    protected $model = DiscountCodeUsage::class;

    public function definition(): array
    {
        return [
            'discount_code_id' => DiscountCode::factory(),
            'user_id' => User::factory(),
            'payment_id' => Payment::factory(),
            'applied_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }
}
