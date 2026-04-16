<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PaymentConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Payment $payment
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: 'Payment confirmation - Fynla',
        );
    }

    public function content(): Content
    {
        $subscription = $this->payment->subscription;
        $discountCode = $this->payment->discountCode;
        $hasDiscount = $this->payment->discount_amount > 0 && $discountCode;
        $originalAmount = $hasDiscount
            ? ($this->payment->amount + $this->payment->discount_amount)
            : $this->payment->amount;

        $invoice = $this->payment->invoice;

        return new Content(
            view: 'emails.payment-confirmation',
            with: [
                'user' => $this->user,
                'payment' => $this->payment,
                'planName' => ucfirst($subscription->plan ?? 'Standard'),
                'billingCycle' => $subscription->billing_cycle ?? 'monthly',
                'amount' => number_format($this->payment->amount / 100, 2),
                'paymentDate' => $this->payment->created_at?->format('j F Y'),
                'hasDiscount' => $hasDiscount,
                'discountCode' => $discountCode?->code,
                'discountDescription' => $hasDiscount ? $this->describeDiscount($discountCode) : null,
                'discountAmount' => $hasDiscount ? number_format($this->payment->discount_amount / 100, 2) : null,
                'originalAmount' => number_format($originalAmount / 100, 2),
                'renewalAmount' => number_format(($subscription->amount ?? $this->payment->amount) / 100, 2),
                'nextRenewalDate' => $subscription->current_period_end?->format('j F Y'),
                'autoRenew' => $subscription->auto_renew ?? false,
                'invoiceNumber' => $invoice?->invoice_number,
                'awinOrderRef' => $this->payment->awin_cks ? $this->payment->awin_order_ref : null,
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $invoice = $this->payment->invoice;

        if (! $invoice) {
            Log::warning('CRITICAL: Payment confirmation sent without invoice — legal requirement', [
                'payment_id' => $this->payment->id,
            ]);

            return [];
        }

        if ($invoice->pdf_path && Storage::exists($invoice->pdf_path)) {
            return [
                Attachment::fromStorage($invoice->pdf_path)
                    ->as("{$invoice->invoice_number}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        Log::warning('Invoice exists but PDF file missing', [
            'payment_id' => $this->payment->id,
            'invoice_number' => $invoice->invoice_number,
            'pdf_path' => $invoice->pdf_path,
        ]);

        return [];
    }

    private function describeDiscount(\App\Models\DiscountCode $discount): string
    {
        return match ($discount->type) {
            'percentage' => "{$discount->value}% off",
            'fixed_amount' => '£' . number_format($discount->value / 100, 2) . ' off',
            default => 'Discount applied',
        };
    }
}
