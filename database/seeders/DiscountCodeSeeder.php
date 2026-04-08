<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DiscountCode;
use Illuminate\Database\Seeder;

class DiscountCodeSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            [
                'code' => 'LAUNCH20',
                'type' => 'percentage',
                'value' => 20,
                'max_uses' => 500,
                'max_uses_per_user' => 1,
                'expires_at' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'FYNLA10',
                'type' => 'fixed_amount',
                'value' => 1000, // £10.00 in pence
                'max_uses' => null,
                'max_uses_per_user' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'TRYME',
                'type' => 'trial_extension',
                'value' => 14, // 14 extra days
                'max_uses' => 200,
                'max_uses_per_user' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($codes as $code) {
            DiscountCode::updateOrCreate(
                ['code' => $code['code']],
                $code
            );
        }

        echo "Discount codes seeded: " . count($codes) . " codes.\n";
    }
}
