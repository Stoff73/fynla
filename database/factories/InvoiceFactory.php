<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'invoice_number' => Invoice::generateNumber(),
            'status' => 'issued',
            'subtotal_amount' => 1099,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'total_amount' => 1099,
            'currency' => 'GBP',
            'plan_name' => 'Standard',
            'billing_cycle' => 'monthly',
            'period_start' => now(),
            'period_end' => now()->addMonth(),
            'issued_at' => now(),
            'billing_name' => fake()->name(),
            'billing_email' => fake()->safeEmail(),
        ];
    }
}
