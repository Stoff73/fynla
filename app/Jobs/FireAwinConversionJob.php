<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Marketing\AwinTrackingService;
use App\Traits\StructuredLogging;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fires the Awin server-to-server conversion event for a completed payment.
 *
 * Idempotent: checks payments.awin_fired_at before firing. Safe to dispatch
 * from both the Revolut webhook AND the PaymentController::confirmPayment
 * endpoint — whichever arrives second short-circuits.
 *
 * Retry policy: 3 attempts at 30s / 5min / 30min. Exceptions are only
 * thrown from handle() when the S2S call returns false — the HTTP client
 * itself never throws because AwinTrackingService swallows transport errors.
 * Throwing tells the queue driver to reschedule via backoff().
 */
class FireAwinConversionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StructuredLogging;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $paymentId
    ) {}

    /**
     * Exponential backoff in seconds: 30s → 5min → 30min.
     */
    public function backoff(): array
    {
        return [30, 300, 1800];
    }

    public function handle(AwinTrackingService $awin): void
    {
        if (! config('awin.enabled')) {
            return;
        }

        $payment = Payment::with('discountCode')->find($this->paymentId);

        if (! $payment) {
            $this->logWarning('[awin] job: payment not found', [
                'payment_id' => $this->paymentId,
            ]);

            return;
        }

        // Idempotent: another dispatch already fired.
        if ($payment->awin_fired_at !== null) {
            $this->logInfo('[awin] job: already fired, skipping', [
                'payment_id' => $payment->id,
                'awin_fired_at' => $payment->awin_fired_at->toIso8601String(),
            ]);

            return;
        }

        // Only fire for completed payments. Anything else is a dispatch bug
        // on the caller's side — we do NOT retry, we just bail.
        if ($payment->status !== 'completed') {
            $this->logWarning('[awin] job: payment not completed, skipping', [
                'payment_id' => $payment->id,
                'status' => $payment->status,
            ]);

            return;
        }

        $params = $awin->buildSaleParams($payment);
        $ok = $awin->fireServerToServer($params);

        if (! $ok) {
            // Throw so the queue driver applies the backoff schedule.
            // The exception message is swallowed by the logger in the service,
            // so we only need a marker here.
            throw new \RuntimeException(
                "Awin S2S call failed for payment {$payment->id} (attempt {$this->attempts()})"
            );
        }

        $payment->forceFill(['awin_fired_at' => now()])->save();

        $this->logInfo('[awin] job: fired and marked', [
            'payment_id' => $payment->id,
            'order_ref' => $params['order_ref'],
        ]);
    }

    /**
     * Called by the queue driver once $tries is exhausted. Surfaces the final
     * failure to the structured log so the admin panel can see it.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->logError('[awin] job: exhausted all retries', [
            'payment_id' => $this->paymentId,
            'error' => $exception?->getMessage(),
        ], $exception);
    }
}
