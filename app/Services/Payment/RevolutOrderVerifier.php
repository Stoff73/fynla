<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Payment;

class RevolutOrderVerifier
{
    public function completedOrderFailure(Payment $payment, array $order): ?string
    {
        if (($order['state'] ?? null) !== 'completed') {
            return 'Payment has not been completed yet';
        }

        if (! array_key_exists('amount', $order) || (int) $order['amount'] !== (int) $payment->amount) {
            return 'Payment amount could not be verified';
        }

        if (strtoupper((string) ($order['currency'] ?? '')) !== strtoupper($payment->currency)) {
            return 'Payment currency could not be verified';
        }

        return null;
    }
}
