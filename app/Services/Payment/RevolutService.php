<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevolutService
{
    private string $apiKey;

    private bool $sandbox;

    public function __construct()
    {
        $this->apiKey = config('services.revolut.api_key', '');
        $this->sandbox = (bool) config('services.revolut.sandbox', true);

        if (config('app.payment_enabled', false)) {
            if (empty($this->apiKey)) {
                Log::critical('PAYMENT_ENABLED is true but REVOLUT_API_KEY is not configured');
            }
            if ($this->sandbox && app()->environment('production')) {
                Log::critical('REVOLUT_SANDBOX is true in production — payments will use sandbox API');
            }
        }
    }

    // -------------------------------------------------------------------------
    // Order methods (v1.0 — used for setup_order flow)
    // -------------------------------------------------------------------------

    public function createOrder(User $user, string $plan, string $billingCycle): array
    {
        $subscriptionPlan = SubscriptionPlan::findBySlug($plan);
        if (! $subscriptionPlan) {
            throw new \InvalidArgumentException("Unknown or inactive subscription plan: {$plan}");
        }

        return $this->request('post', $this->v1Url('/orders'), $this->v1Headers(), [
            'amount' => $subscriptionPlan->getPriceForCycle($billingCycle),
            'currency' => 'GBP',
            'description' => ucfirst($plan).' plan ('.$billingCycle.')',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan' => $plan,
                'billing_cycle' => $billingCycle,
            ],
        ], 'order creation', ['user_id' => $user->id]);
    }

    public function getOrderStatus(string $orderId): array
    {
        return $this->request('get', $this->v1Url('/orders/'.$orderId), $this->v1Headers(),
            [], 'order status check', ['order_id' => $orderId]);
    }

    // -------------------------------------------------------------------------
    // Customer methods (v1.0)
    // -------------------------------------------------------------------------

    /**
     * Create a Revolut customer record.
     * Called lazily at checkout time, not at registration.
     */
    public function createCustomer(User $user): array
    {
        return $this->request('post', $this->v1Url('/customers'), $this->v1Headers(), [
            'email' => $user->email,
            'full_name' => $user->name,
        ], 'customer creation', ['user_id' => $user->id]);
    }

    /**
     * Retrieve a Revolut customer by ID.
     */
    public function getCustomer(string $customerId): array
    {
        return $this->request('get', $this->v1Url('/customers/'.$customerId), $this->v1Headers(),
            [], 'customer retrieval', ['customer_id' => $customerId]);
    }

    // -------------------------------------------------------------------------
    // Subscription methods (versioned API)
    // -------------------------------------------------------------------------

    /**
     * Create a Revolut subscription.
     *
     * Uses trial_duration: "P0D" because Fynla manages its own 7-day trial.
     * When the user converts from trial, Revolut billing starts immediately.
     *
     * Returns the full subscription object including setup_order_id.
     */
    public function createSubscription(string $revolutCustomerId, string $revolutVariationId): array
    {
        return $this->request('post', $this->subscriptionUrl('/subscriptions'), $this->subscriptionHeaders(), [
            'customer_id' => $revolutCustomerId,
            'plan_variation_id' => $revolutVariationId,
            'trial_duration' => 'P0D',
        ], 'subscription creation', ['customer_id' => $revolutCustomerId, 'variation_id' => $revolutVariationId]);
    }

    /**
     * Retrieve a subscription by its Revolut ID.
     */
    public function getSubscription(string $subscriptionId): array
    {
        return $this->request('get', $this->subscriptionUrl('/subscriptions/'.$subscriptionId), $this->subscriptionHeaders(),
            [], 'subscription retrieval', ['subscription_id' => $subscriptionId]);
    }

    /**
     * Cancel a subscription.
     * Revolut returns 204 No Content on success.
     */
    public function cancelSubscription(string $subscriptionId): void
    {
        $this->request('post', $this->subscriptionUrl('/subscriptions/'.$subscriptionId.'/cancel'), $this->subscriptionHeaders(),
            [], 'subscription cancellation', ['subscription_id' => $subscriptionId]);
    }

    /**
     * Retrieve billing cycles for a subscription.
     */
    public function getSubscriptionCycles(string $subscriptionId): array
    {
        return $this->request('get', $this->subscriptionUrl('/subscriptions/'.$subscriptionId.'/cycles'), $this->subscriptionHeaders(),
            [], 'subscription cycles retrieval', ['subscription_id' => $subscriptionId]);
    }

    // -------------------------------------------------------------------------
    // Plan creation (used by revolut:seed-plans artisan command)
    // -------------------------------------------------------------------------

    /**
     * Create a subscription plan in Revolut with monthly and yearly variations.
     */
    public function createPlan(string $displayName, int $monthlyPricePence, int $yearlyPricePence, int $trialDays = 0): array
    {
        $payload = [
            'name' => $displayName,
            'variations' => [
                [
                    'phases' => [
                        [
                            'ordinal' => 1,
                            'cycle_duration' => 'P1M',
                            'amount' => $monthlyPricePence,
                            'currency' => 'GBP',
                        ],
                    ],
                ],
                [
                    'phases' => [
                        [
                            'ordinal' => 1,
                            'cycle_duration' => 'P1Y',
                            'amount' => $yearlyPricePence,
                            'currency' => 'GBP',
                        ],
                    ],
                ],
            ],
        ];

        if ($trialDays > 0) {
            $payload['trial_duration'] = "P{$trialDays}D";
        }

        return $this->request('post', $this->subscriptionUrl('/subscription-plans'), $this->subscriptionHeaders(),
            $payload, 'plan creation', ['plan_name' => $displayName]);
    }

    // -------------------------------------------------------------------------
    // Webhook registration (v1.0)
    // -------------------------------------------------------------------------

    /**
     * Register a webhook URL with Revolut.
     *
     * @param  string  $url  The publicly accessible URL to receive webhooks
     * @param  array  $events  List of event types to subscribe to
     */
    public function registerWebhook(string $url, array $events = []): array
    {
        $payload = ['url' => $url];
        if (! empty($events)) {
            $payload['events'] = $events;
        }

        return $this->request('post', $this->v1Url('/webhooks'), $this->v1Headers(),
            $payload, 'webhook registration', ['url' => $url]);
    }

    /**
     * List all registered webhooks.
     */
    public function listWebhooks(): array
    {
        return $this->request('get', $this->v1Url('/webhooks'), $this->v1Headers(),
            [], 'webhook listing');
    }

    // -------------------------------------------------------------------------
    // HTTP helper
    // -------------------------------------------------------------------------

    /**
     * Execute an HTTP request to the Revolut API with standard error handling.
     */
    private function request(string $method, string $url, array $headers, array $data, string $action, array $context = []): array
    {
        $response = match ($method) {
            'get' => Http::withHeaders($headers)->get($url),
            'post' => Http::withHeaders($headers)->post($url, $data),
        };

        if (! $response->successful()) {
            Log::error("Revolut {$action} failed", array_merge([
                'status' => $response->status(),
                'body' => $response->body(),
            ], $context));

            throw new \RuntimeException("Failed: Revolut {$action}");
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    // -------------------------------------------------------------------------
    // URL and header helpers
    // -------------------------------------------------------------------------

    private function baseHost(): string
    {
        return $this->sandbox
            ? 'https://sandbox-merchant.revolut.com'
            : 'https://merchant.revolut.com';
    }

    private function v1Url(string $path): string
    {
        return $this->baseHost().'/api/1.0'.$path;
    }

    private function subscriptionUrl(string $path): string
    {
        return $this->baseHost().'/api'.$path;
    }

    private function v1Headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    private function subscriptionHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
            'Revolut-Api-Version' => '2025-12-04',
        ];
    }
}
