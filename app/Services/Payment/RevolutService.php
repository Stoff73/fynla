<?php

declare(strict_types=1);

namespace App\Services\Payment;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RevolutService
{
    private string $apiKey;

    private string $apiUrl;

    private string $webhookSecret;

    public function __construct()
    {
        $this->apiKey = config('services.revolut.api_key');
        $sandbox = config('services.revolut.sandbox');
        $this->apiUrl = $sandbox
            ? 'https://sandbox-merchant.revolut.com/api'
            : 'https://merchant.revolut.com/api';
        $this->webhookSecret = config('services.revolut.webhook_secret');
    }

    /**
     * Create a Revolut order.
     *
     * POST {apiUrl}/orders
     *
     * @param  int  $amount  Amount in minor currency units (pence for GBP)
     * @param  string  $currency  ISO 4217 currency code (e.g. 'GBP')
     * @param  string  $description  Order description
     * @param  string  $redirectUrl  URL to redirect after hosted/redirect payment methods (must be https://)
     * @param  string|null  $merchantRef  Internal reference echoed back in webhooks as merchant_order_ext_ref
     * @param  string|null  $email  Customer email for pre-fill
     * @return array Revolut order response: { id, token, state, amount, currency, ... }
     */
    public function createOrder(
        int $amount,
        string $currency,
        string $description,
        string $redirectUrl,
        ?string $merchantRef = null,
        ?string $email = null
    ): array {
        $body = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'redirect_url' => $redirectUrl,
        ];

        if ($merchantRef) {
            $body['merchant_order_data'] = ['reference' => $merchantRef];
        }

        if ($email) {
            $body['customer'] = ['email' => $email];
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Revolut-Api-Version' => '2025-12-04',
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/orders", $body);

        if ($response->failed()) {
            Log::error('Revolut createOrder failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'amount' => $amount,
                'currency' => $currency,
            ]);
            $response->throw();
        }

        $data = $response->json();

        Log::info('Revolut order created', [
            'order_id' => $data['id'],
            'state' => $data['state'],
            'amount' => $amount,
            'currency' => $currency,
            'merchant_ref' => $merchantRef,
        ]);

        return $data;
    }

    /**
     * Retrieve a Revolut order by its internal UUID.
     *
     * GET {apiUrl}/orders/{orderId}
     *
     * @param  string  $orderId  The Revolut order UUID (order.id, NOT order.token)
     * @return array Order response including 'state'
     */
    public function getOrder(string $orderId): array
    {
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Revolut-Api-Version' => '2025-12-04',
        ])->get("{$this->apiUrl}/orders/{$orderId}");

        if ($response->failed()) {
            Log::error('Revolut getOrder failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            $response->throw();
        }

        return $response->json();
    }

    /**
     * Create a Revolut order with a customer ID and optional payment method saving.
     *
     * Used for the subscription setup flow — the order is created by Revolut when
     * a subscription is created, but this method is kept as a fallback for upgrades
     * and one-off payments where we need to associate a customer.
     *
     * @param  int  $amount  Amount in minor currency units (pence for GBP)
     * @param  string  $currency  ISO 4217 currency code
     * @param  string  $description  Order description
     * @param  string  $redirectUrl  URL to redirect after payment
     * @param  string  $customerId  Revolut customer UUID
     * @param  string|null  $merchantRef  Internal reference
     * @param  string|null  $email  Customer email
     * @param  bool  $savePaymentMethod  Whether to save card for merchant-initiated charges
     * @return array Revolut order response
     */
    public function createOrderWithCustomer(
        int $amount,
        string $currency,
        string $description,
        string $redirectUrl,
        string $customerId,
        ?string $merchantRef = null,
        ?string $email = null,
        bool $savePaymentMethod = false
    ): array {
        $body = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'redirect_url' => $redirectUrl,
            'customer' => [
                'id' => $customerId,
            ],
        ];

        if ($savePaymentMethod) {
            $body['save_payment_method_for'] = 'merchant';
        }

        if ($merchantRef) {
            $body['merchant_order_data'] = ['reference' => $merchantRef];
        }

        if ($email) {
            $body['customer']['email'] = $email;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
            'Revolut-Api-Version' => '2025-12-04',
            'Content-Type' => 'application/json',
        ])->post("{$this->apiUrl}/orders", $body);

        if ($response->failed()) {
            Log::error('Revolut createOrderWithCustomer failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'amount' => $amount,
                'customer_id' => $customerId,
            ]);
            $response->throw();
        }

        $data = $response->json();

        Log::info('Revolut order created with customer', [
            'order_id' => $data['id'],
            'customer_id' => $customerId,
            'save_payment_method' => $savePaymentMethod,
            'merchant_ref' => $merchantRef,
        ]);

        return $data;
    }

    /**
     * Verify a Revolut webhook signature.
     *
     * Algorithm (from Revolut docs):
     * 1. Construct: payload_to_sign = "v1.{timestamp}.{raw_body}"
     * 2. Compute: HMAC-SHA256(signing_secret, payload_to_sign)
     * 3. Compare: hex_digest against each signature in Revolut-Signature header
     * 4. Validate: timestamp within 5 minutes of current UTC (replay protection)
     *
     * CRITICAL: $rawPayload must be the exact bytes from $request->getContent().
     * Do NOT trim whitespace, re-encode JSON, or alter the payload in any way.
     *
     * @param  string  $rawPayload  Raw request body (exact bytes from $request->getContent())
     * @param  string  $signatureHeader  Revolut-Signature header value (may contain multiple comma-separated sigs)
     * @param  string  $timestampHeader  Revolut-Request-Timestamp header value (UNIX timestamp in milliseconds)
     * @return bool True if signature is valid and timestamp is within tolerance
     */
    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader, string $timestampHeader): bool
    {
        if (empty($this->webhookSecret)) {
            Log::warning('Revolut webhook secret not configured');

            return false;
        }

        // Step 4: Validate timestamp (5-minute tolerance for replay protection)
        $webhookTimestamp = (int) $timestampHeader;
        $currentTimestamp = (int) (microtime(true) * 1000);
        $fiveMinutesMs = 5 * 60 * 1000;

        if (abs($currentTimestamp - $webhookTimestamp) > $fiveMinutesMs) {
            Log::warning('Revolut webhook timestamp outside 5-minute tolerance', [
                'webhook_timestamp_ms' => $timestampHeader,
                'current_timestamp_ms' => $currentTimestamp,
                'drift_ms' => abs($currentTimestamp - $webhookTimestamp),
            ]);

            return false;
        }

        // Step 1: Prepare the signing payload — "v1.{timestamp}.{raw_body}"
        $payloadToSign = "v1.{$timestampHeader}.{$rawPayload}";

        // Step 2: Compute expected HMAC-SHA256
        $expectedHex = hash_hmac('sha256', $payloadToSign, $this->webhookSecret);

        // Step 3: Compare against each signature in the header (may have multiple during rotation)
        $signatures = explode(',', $signatureHeader);
        foreach ($signatures as $sig) {
            $sig = trim($sig);
            if (str_starts_with($sig, 'v1=')) {
                $providedHex = substr($sig, 3);
                if (hash_equals($expectedHex, $providedHex)) {
                    return true;
                }
            }
        }

        Log::warning('Revolut webhook signature mismatch');

        return false;
    }
}
