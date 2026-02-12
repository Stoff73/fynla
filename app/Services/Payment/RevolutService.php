<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevolutService
{
    private string $apiKey;

    private bool $sandbox;

    private const PLAN_PRICING = [
        'student' => ['monthly' => 399, 'yearly' => 3000],
        'standard' => ['monthly' => 1099, 'yearly' => 10000],
        'pro' => ['monthly' => 1999, 'yearly' => 20000],
    ];

    public function __construct()
    {
        $this->apiKey = config('services.revolut.api_key', '');
        $this->sandbox = config('services.revolut.sandbox', true);
    }

    public function createOrder(User $user, string $plan, string $billingCycle): array
    {
        $amount = self::PLAN_PRICING[$plan][$billingCycle] ?? 0;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post($this->baseUrl() . '/orders', [
            'amount' => $amount,
            'currency' => 'GBP',
            'description' => ucfirst($plan) . ' plan (' . $billingCycle . ')',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => $plan,
                'billing_cycle' => $billingCycle,
            ],
        ]);

        if (! $response->successful()) {
            Log::error('Revolut order creation failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'user_id' => $user->id,
            ]);

            throw new \RuntimeException('Failed to create Revolut order');
        }

        return $response->json();
    }

    public function getOrderStatus(string $orderId): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->get($this->baseUrl() . '/orders/' . $orderId);

        if (! $response->successful()) {
            Log::error('Revolut order status check failed', [
                'status' => $response->status(),
                'order_id' => $orderId,
            ]);

            throw new \RuntimeException('Failed to get Revolut order status');
        }

        return $response->json();
    }

    private function baseUrl(): string
    {
        return $this->sandbox
            ? 'https://sandbox-merchant.revolut.com/api/1.0'
            : 'https://merchant.revolut.com/api/1.0';
    }
}
