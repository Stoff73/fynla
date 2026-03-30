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
                'monthly_price' => 499,
                'launch_monthly_price' => 399,
                'yearly_price' => 4500,
                'launch_yearly_price' => 3000,
                'trial_days' => 7,
                'is_active' => true,
                'sort_order' => 1,
                'features' => [
                    'Full financial dashboard',
                    'Protection module',
                    'Savings module',
                    'Goal tracking',
                    'Investment module',
                    'Retirement module',
                ],
            ],
            [
                'slug' => 'standard',
                'name' => 'Fynla Standard',
                'monthly_price' => 1499,
                'launch_monthly_price' => 1099,
                'yearly_price' => 13500,
                'launch_yearly_price' => 10000,
                'trial_days' => 7,
                'is_active' => true,
                'sort_order' => 2,
                'features' => [
                    'Everything in Student',
                    'Personal Valuables',
                    'Business',
                    'Property',
                    'Letter to Spouse / Expression of Wishes',
                    'Coordination module',
                ],
            ],
            [
                'slug' => 'family',
                'name' => 'Fynla Family',
                'monthly_price' => 2199,
                'launch_monthly_price' => 1499,
                'yearly_price' => 19900,
                'launch_yearly_price' => 15000,
                'trial_days' => 7,
                'is_active' => true,
                'sort_order' => 3,
                'features' => [
                    'Everything in Standard',
                    'Family module',
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Fynla Pro',
                'monthly_price' => 2999,
                'launch_monthly_price' => 1999,
                'yearly_price' => 26999,
                'launch_yearly_price' => 20000,
                'trial_days' => 7,
                'is_active' => true,
                'sort_order' => 4,
                'features' => [
                    'Everything in Family',
                    'Estate Planning',
                    'Holistic Plan',
                    'Wills',
                    'Powers of Attorney',
                    'Trusts',
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
