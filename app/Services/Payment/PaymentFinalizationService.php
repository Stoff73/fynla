<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\PaymentConfirmation;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class PaymentFinalizationService
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly ReferralService $referralService,
    ) {}

    /**
     * Complete idempotent artifacts for an already-settled payment.
     */
    public function finalize(Payment $sourcePayment): Payment
    {
        $payment = $sourcePayment->fresh(['user', 'discountCode']);
        if ($payment->status !== 'completed') {
            throw new \RuntimeException('Only a completed payment can be finalised.');
        }

        $this->invoiceService->generateInvoice($payment, $payment->discountCode);

        if ($payment->user->referred_by_code) {
            $this->referralService->applyReferralBonus(
                $payment->user,
                $payment->billing_cycle,
                $payment->subscription_id,
            );
        }

        DB::transaction(function () use ($payment): void {
            Payment::query()->lockForUpdate()->findOrFail($payment->id)->update([
                'financial_finalized_at' => now(),
            ]);
        });

        $claimedAt = now();
        $claimed = Payment::query()
            ->whereKey($payment->id)
            ->whereNull('confirmation_email_sent_at')
            ->where(function ($query): void {
                $query->whereNull('confirmation_email_claimed_at')
                    ->orWhere('confirmation_email_claimed_at', '<=', now()->subMinutes(15));
            })
            ->update(['confirmation_email_claimed_at' => $claimedAt]);

        if ($claimed === 1) {
            try {
                $claimedPayment = Payment::query()->findOrFail($payment->id);
                Mail::to($payment->user->email)->send(new PaymentConfirmation($payment->user, $claimedPayment));
            } catch (\Throwable $exception) {
                Payment::query()
                    ->whereKey($payment->id)
                    ->where('confirmation_email_claimed_at', $claimedAt)
                    ->whereNull('confirmation_email_sent_at')
                    ->update(['confirmation_email_claimed_at' => null]);

                throw $exception;
            }

            Payment::query()
                ->whereKey($payment->id)
                ->where('confirmation_email_claimed_at', $claimedAt)
                ->whereNull('confirmation_email_sent_at')
                ->update([
                    'confirmation_email_claimed_at' => null,
                    'confirmation_email_sent_at' => now(),
                ]);
        }

        return $payment->fresh();
    }
}
