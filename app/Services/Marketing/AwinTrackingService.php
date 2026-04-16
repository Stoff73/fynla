<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Models\Payment;
use App\Models\User;
use App\Traits\StructuredLogging;
use Illuminate\Support\Facades\Http;

/**
 * Builds and fires Awin affiliate conversion events.
 *
 * Pure service — no controller/request dependencies, fully testable via
 * Http::fake(). The caller (FireAwinConversionJob) decides WHEN to fire;
 * this service decides WHAT to send and HOW.
 *
 * Error handling contract: fireServerToServer() NEVER throws. Tracking is
 * best-effort and MUST NOT break the payment confirmation flow. Failures
 * return false and are logged; the caller uses the return value to decide
 * whether to retry.
 */
class AwinTrackingService
{
    use StructuredLogging;

    /**
     * Build the Awin sale parameter array from a Payment record.
     *
     * All amounts are converted from pence (integer) to GBP (string with
     * 2 decimal places), which is the format Awin expects.
     *
     * The payment MUST have been updated with awin_* columns (cks, order_ref,
     * customer_acquisition) before this is called — those values live on the
     * model because the webhook has no access to the browser cookie at the
     * moment of firing.
     */
    public function buildSaleParams(Payment $payment): array
    {
        $amountDecimal = number_format(((int) $payment->amount) / 100, 2, '.', '');
        $voucherCode = $payment->discountCode?->code ?? '';

        return [
            'order_subtotal' => $amountDecimal,
            'currency_code' => $payment->currency ?: 'GBP',
            'order_ref' => $payment->awin_order_ref ?? $this->orderRefFor($payment),
            'commission_group' => $this->commissionGroupFor($payment->plan_slug),
            'sale_amount' => $amountDecimal,
            'voucher_code' => $voucherCode,
            'customer_acquisition' => $payment->awin_customer_acquisition ?? 'existing',
            'awc' => $payment->awin_cks ?? '',
        ];
    }

    /**
     * Determine whether a user's purchase counts as new customer acquisition.
     *
     * Returns true iff the user has zero completed payments *other than* the
     * one currently being considered. A re-subscription after cancel still
     * counts as "existing".
     */
    public function isCustomerAcquisition(User $user, ?int $excludePaymentId = null): bool
    {
        $query = Payment::query()
            ->where('user_id', $user->id)
            ->where('status', 'completed');

        if ($excludePaymentId !== null) {
            $query->where('id', '!=', $excludePaymentId);
        }

        return ! $query->exists();
    }

    /**
     * Map a Fynla plan slug to an Awin commission group.
     *
     * v1 uses a single group (`SUB`) for all tiers. Structured as a match so
     * per-tier groups can be added later by editing a single block.
     */
    public function commissionGroupFor(?string $planSlug): string
    {
        return match ($planSlug) {
            'student', 'standard', 'family', 'pro' => config('awin.default_commission_group', 'SUB'),
            default => config('awin.default_commission_group', 'SUB'),
        };
    }

    /**
     * Build the canonical order reference for a payment.
     *
     * Deterministic and unique: Awin deduplicates both the browser pixel and
     * the S2S call against this value.
     */
    public function orderRefFor(Payment $payment): string
    {
        return "FYN-PAY-{$payment->id}";
    }

    /**
     * Fire the server-to-server conversion call to Awin.
     *
     * Never throws. Returns true on 2xx response, false on any non-2xx or
     * transport exception. Retries are the caller's responsibility (via the
     * queued job's backoff policy) — the HTTP client does NOT retry inline
     * because that would block the queue worker while Awin is slow.
     */
    public function fireServerToServer(array $params): bool
    {
        $query = [
            'tt' => 'ss',
            'tv' => '2',
            'merchant' => (string) config('awin.merchant_id'),
            'amount' => $params['order_subtotal'],
            'ch' => 'aw',
            'cr' => $params['currency_code'],
            'ref' => $params['order_ref'],
            'parts' => "{$params['commission_group']}:{$params['sale_amount']}",
            'vc' => $params['voucher_code'] ?? '',
            'customeracquisition' => $params['customer_acquisition'],
        ];

        if (! empty($params['awc'])) {
            $query['cks'] = $params['awc'];
        }

        $timeout = (int) config('awin.http_timeout_seconds', 3);

        try {
            $response = Http::timeout($timeout)
                ->connectTimeout($timeout)
                ->retry(0)
                ->get((string) config('awin.s2s_base_url'), $query);

            if ($response->successful()) {
                $this->logInfo('[awin] s2s fired', [
                    'order_ref' => $params['order_ref'],
                    'status' => $response->status(),
                ]);

                return true;
            }

            $this->logError('[awin] s2s non-2xx', [
                'order_ref' => $params['order_ref'],
                'status' => $response->status(),
                'body' => substr($response->body(), 0, 500),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logError('[awin] s2s exception', [
                'order_ref' => $params['order_ref'] ?? null,
                'message' => $e->getMessage(),
            ], $e);

            return false;
        }
    }
}
