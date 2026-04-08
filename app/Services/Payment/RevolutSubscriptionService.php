<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RevolutSubscriptionService
{
    private string $apiKey;

    private string $apiUrl;

    private string $apiVersion = '2025-12-04';

    public function __construct()
    {
        $this->apiKey = config('services.revolut.api_key');
        $sandbox = config('services.revolut.sandbox');
        $this->apiUrl = $sandbox
            ? 'https://sandbox-merchant.revolut.com/api'
            : 'https://merchant.revolut.com/api';
    }

    // ── Customer Management ──

    /**
     * Create a Revolut customer for the given user.
     * Idempotent: returns existing customer_id if already set on user.
     */
    public function createCustomer(User $user): array
    {
        if ($user->revolut_customer_id) {
            return $this->getCustomer($user->revolut_customer_id);
        }

        $response = Http::withHeaders($this->headers())
            ->post("{$this->apiUrl}/customers", [
                'email' => $user->email,
                'full_name' => trim("{$user->first_name} {$user->surname}"),
            ]);

        if ($response->failed()) {
            Log::error('Revolut createCustomer failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'user_id' => $user->id,
            ]);
            $response->throw();
        }

        $data = $response->json();

        $user->update(['revolut_customer_id' => $data['id']]);

        Log::info('Revolut customer created', [
            'user_id' => $user->id,
            'customer_id' => $data['id'],
        ]);

        return $data;
    }

    /**
     * Retrieve a Revolut customer by ID.
     */
    public function getCustomer(string $customerId): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->apiUrl}/customers/{$customerId}");

        if ($response->failed()) {
            Log::error('Revolut getCustomer failed', [
                'customer_id' => $customerId,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    // ── Subscription Plan Management ──

    /**
     * Create a Revolut subscription plan with monthly + yearly variations.
     *
     * Each plan gets 2 variations:
     * - Variation 0: Monthly (P1M), using launch price or regular price
     * - Variation 1: Yearly (P1Y), using launch price or regular price
     *
     * trial_duration set at plan level (P7D = 7-day trial).
     * cycle_count null = indefinite billing.
     */
    public function createSubscriptionPlan(SubscriptionPlan $plan): array
    {
        $monthlyPrice = $plan->launch_monthly_price ?? $plan->monthly_price;
        $yearlyPrice = $plan->launch_yearly_price ?? $plan->yearly_price;

        $body = [
            'name' => "Fynla {$plan->name} Plan",
            'trial_duration' => 'P' . ($plan->trial_days ?? 7) . 'D',
            'variations' => [
                [
                    'phases' => [
                        [
                            'ordinal' => 1,
                            'cycle_duration' => 'P1M',
                            'amount' => $monthlyPrice,
                            'currency' => 'GBP',
                        ],
                    ],
                ],
                [
                    'phases' => [
                        [
                            'ordinal' => 1,
                            'cycle_duration' => 'P1Y',
                            'amount' => $yearlyPrice,
                            'currency' => 'GBP',
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders($this->headers())
            ->post("{$this->apiUrl}/subscription-plans", $body);

        if ($response->failed()) {
            Log::error('Revolut createSubscriptionPlan failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'plan_slug' => $plan->slug,
            ]);
            $response->throw();
        }

        $data = $response->json();

        Log::info('Revolut subscription plan created', [
            'plan_slug' => $plan->slug,
            'revolut_plan_id' => $data['id'],
            'variation_count' => count($data['variations'] ?? []),
        ]);

        return $data;
    }

    /**
     * Retrieve a specific subscription plan by ID.
     */
    public function getSubscriptionPlan(string $planId): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->apiUrl}/subscription-plans/{$planId}");

        if ($response->failed()) {
            Log::error('Revolut getSubscriptionPlan failed', [
                'plan_id' => $planId,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Retrieve all subscription plans (paginated).
     */
    public function getSubscriptionPlans(?int $limit = null, ?string $pageToken = null): array
    {
        $query = [];
        if ($limit !== null) {
            $query['limit'] = $limit;
        }
        if ($pageToken !== null) {
            $query['page_token'] = $pageToken;
        }

        $response = Http::withHeaders($this->headers())
            ->get("{$this->apiUrl}/subscription-plans", $query);

        if ($response->failed()) {
            Log::error('Revolut getSubscriptionPlans failed', [
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    // ── Subscription Management ──

    /**
     * Create a subscription for a customer using a plan variation.
     *
     * Returns the subscription object including setup_order_id for initial payment.
     * Uses Idempotency-Key header to prevent duplicate subscriptions.
     */
    public function createSubscription(
        User $user,
        string $planVariationId,
        string $redirectUrl,
        ?string $trialDuration = null,
        ?string $externalReference = null
    ): array {
        $body = [
            'plan_variation_id' => $planVariationId,
            'customer_id' => $user->revolut_customer_id,
            'setup_order_redirect_url' => $redirectUrl,
        ];

        if ($externalReference !== null) {
            $body['external_reference'] = $externalReference;
        }

        if ($trialDuration !== null) {
            $body['trial_duration'] = $trialDuration;
        }

        $response = Http::withHeaders(array_merge($this->headers(), [
            'Idempotency-Key' => Str::uuid()->toString(),
        ]))->post("{$this->apiUrl}/subscriptions", $body);

        if ($response->failed()) {
            Log::error('Revolut createSubscription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'user_id' => $user->id,
                'plan_variation_id' => $planVariationId,
            ]);
            $response->throw();
        }

        $data = $response->json();

        Log::info('Revolut subscription created', [
            'user_id' => $user->id,
            'subscription_id' => $data['id'],
            'state' => $data['state'],
            'setup_order_id' => $data['setup_order_id'] ?? null,
        ]);

        return $data;
    }

    /**
     * Retrieve a subscription by its ID.
     */
    public function getSubscription(string $subscriptionId): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->apiUrl}/subscriptions/{$subscriptionId}");

        if ($response->failed()) {
            Log::error('Revolut getSubscription failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Retrieve all subscriptions (paginated, optionally filtered by external_reference).
     */
    public function getSubscriptions(
        ?string $externalReference = null,
        ?int $limit = null,
        ?string $pageToken = null
    ): array {
        $query = [];
        if ($externalReference !== null) {
            $query['external_reference'] = $externalReference;
        }
        if ($limit !== null) {
            $query['limit'] = $limit;
        }
        if ($pageToken !== null) {
            $query['page_token'] = $pageToken;
        }

        $response = Http::withHeaders($this->headers())
            ->get("{$this->apiUrl}/subscriptions", $query);

        if ($response->failed()) {
            Log::error('Revolut getSubscriptions failed', [
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Update a subscription's external_reference.
     *
     * Only works in states: pending, active, overdue, paused.
     * Cannot modify in: cancelled, finished.
     */
    public function updateSubscription(string $subscriptionId, string $externalReference): array
    {
        $response = Http::withHeaders($this->headers())
            ->patch("{$this->apiUrl}/subscriptions/{$subscriptionId}", [
                'external_reference' => $externalReference,
            ]);

        if ($response->failed()) {
            Log::error('Revolut updateSubscription failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Cancel a subscription.
     *
     * Can cancel in any state except cancelled or finished.
     * Returns 204 No Content (empty body).
     */
    public function cancelSubscription(string $subscriptionId): void
    {
        $response = Http::withHeaders($this->headers())
            ->post("{$this->apiUrl}/subscriptions/{$subscriptionId}/cancel");

        if ($response->failed()) {
            Log::error('Revolut cancelSubscription failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        Log::info('Revolut subscription cancelled', [
            'subscription_id' => $subscriptionId,
        ]);
    }

    // ── Billing Cycle Management ──

    /**
     * Retrieve all billing cycles for a subscription (paginated).
     */
    public function getSubscriptionCycles(
        string $subscriptionId,
        ?int $limit = null,
        ?string $pageToken = null
    ): array {
        $query = [];
        if ($limit !== null) {
            $query['limit'] = $limit;
        }
        if ($pageToken !== null) {
            $query['page_token'] = $pageToken;
        }

        $response = Http::withHeaders($this->headers())
            ->get("{$this->apiUrl}/subscriptions/{$subscriptionId}/cycles", $query);

        if ($response->failed()) {
            Log::error('Revolut getSubscriptionCycles failed', [
                'subscription_id' => $subscriptionId,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Retrieve a specific billing cycle.
     */
    public function getSubscriptionCycle(string $subscriptionId, string $cycleId): array
    {
        $response = Http::withHeaders($this->headers())
            ->get("{$this->apiUrl}/subscriptions/{$subscriptionId}/cycles/{$cycleId}");

        if ($response->failed()) {
            Log::error('Revolut getSubscriptionCycle failed', [
                'subscription_id' => $subscriptionId,
                'cycle_id' => $cycleId,
                'status' => $response->status(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Find the Revolut plan variation ID for a given plan slug and billing cycle.
     *
     * Variation index 0 = monthly (P1M), index 1 = yearly (P1Y).
     */
    public function findVariationId(string $revolutPlanId, string $billingCycle): ?string
    {
        $plan = $this->getSubscriptionPlan($revolutPlanId);
        $variations = $plan['variations'] ?? [];

        foreach ($variations as $variation) {
            $phases = $variation['phases'] ?? [];
            if (empty($phases)) {
                continue;
            }
            $cycleDuration = $phases[0]['cycle_duration'] ?? '';
            $targetDuration = $billingCycle === 'monthly' ? 'P1M' : 'P1Y';

            if ($cycleDuration === $targetDuration) {
                return $variation['id'];
            }
        }

        return null;
    }

    /**
     * Build standard headers for Revolut API calls.
     */
    private function headers(): array
    {
        return [
            'Authorization' => "Bearer {$this->apiKey}",
            'Revolut-Api-Version' => $this->apiVersion,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }
}
