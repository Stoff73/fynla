<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Seed default subscription plans.
     *
     * Re-runnable: uses updateOrCreate on slug.
     * To update pricing, modify the values below and run:
     *   php artisan db:seed --class=SubscriptionPlanSeeder --force
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'student',
                'name' => 'Fynla Student',
                'monthly_price' => 399,
                'yearly_price' => 3000,
                'trial_days' => 7,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    'Full financial dashboard',
                    'Protection module',
                    'Savings module',
                    'Goal tracking',
                ],
            ],
            [
                'slug' => 'standard',
                'name' => 'Fynla Standard',
                'monthly_price' => 1099,
                'yearly_price' => 10000,
                'trial_days' => 7,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    'Everything in Student',
                    'Investment module',
                    'Retirement module',
                    'Estate planning',
                    'Coordination module',
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Fynla Pro',
                'monthly_price' => 1999,
                'yearly_price' => 20000,
                'trial_days' => 7,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'Everything in Standard',
                    'AI document extraction',
                    'Advanced projections',
                    'Priority support',
                ],
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
