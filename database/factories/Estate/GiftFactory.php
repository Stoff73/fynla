<?php

declare(strict_types=1);

namespace Database\Factories\Estate;

use App\Models\Estate\Gift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gift>
 */
class GiftFactory extends Factory
{
    protected $model = Gift::class;

    public function definition(): array
    {
        $giftDate = fake()->dateTimeBetween('-7 years', 'now');
        $yearsAgo = now()->diffInYears($giftDate);

        return [
            'user_id' => User::factory(),
            'gift_date' => $giftDate,
            'recipient' => fake()->name(),
            'gift_type' => fake()->randomElement([
                'pet',
                'clt',
                'exempt',
                'small_gift',
                'annual_exemption',
            ]),
            'gift_value' => fake()->randomFloat(2, 250, 100000),
            'status' => $yearsAgo >= 7 ? 'survived_7_years' : 'within_7_years',
            'taper_relief_applicable' => $yearsAgo >= 3,
            'notes' => fake()->optional(0.3)->sentence(),
        ];
    }

    /**
     * A gift made within the last 7 years (still within IHT scope).
     */
    public function withinSevenYears(): static
    {
        return $this->state(fn (array $attributes) => [
            'gift_date' => fake()->dateTimeBetween('-6 years', '-1 month'),
            'status' => 'within_7_years',
        ]);
    }

    /**
     * A gift the donor survived by 7+ years (outside IHT scope).
     */
    public function survivedSevenYears(): static
    {
        return $this->state(fn (array $attributes) => [
            'gift_date' => fake()->dateTimeBetween('-15 years', '-7 years -1 day'),
            'status' => 'survived_7_years',
            'taper_relief_applicable' => false,
        ]);
    }

    /**
     * A gift that qualifies for taper relief (3-7 years ago).
     */
    public function taperEligible(): static
    {
        return $this->state(fn (array $attributes) => [
            'gift_date' => fake()->dateTimeBetween('-7 years', '-3 years'),
            'taper_relief_applicable' => true,
            'status' => 'within_7_years',
        ]);
    }

    /**
     * A gift exempt under annual exemption.
     */
    public function annualExemption(): static
    {
        return $this->state(fn (array $attributes) => [
            'gift_type' => 'annual_exemption',
            'gift_value' => 3000.00,
            'status' => 'within_7_years',
            'taper_relief_applicable' => false,
        ]);
    }

    /**
     * A small gift exemption (up to 250 per recipient).
     */
    public function smallGift(): static
    {
        return $this->state(fn (array $attributes) => [
            'gift_type' => 'small_gift',
            'gift_value' => fake()->randomFloat(2, 50, 250),
            'status' => 'within_7_years',
            'taper_relief_applicable' => false,
        ]);
    }

    /**
     * A large potentially exempt transfer.
     */
    public function largePET(): static
    {
        return $this->state(fn (array $attributes) => [
            'gift_type' => 'pet',
            'gift_value' => fake()->randomFloat(2, 50000, 500000),
            'status' => 'within_7_years',
        ]);
    }
}
