<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceEmail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public User $user
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@fynla.org', 'Fynla'),
            subject: "Your Fynla invoice — {$this->invoice->invoice_number}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.invoice',
            with: [
                'user' => $this->user,
                'invoice' => $this->invoice,
                'planName' => $this->invoice->plan_name,
                'billingCycle' => $this->invoice->billing_cycle,
                'amount' => number_format($this->invoice->total_amount / 100, 2),
                'invoiceDate' => $this->invoice->issued_at?->format('j F Y'),
                'periodStart' => $this->invoice->period_start?->format('j F Y'),
                'periodEnd' => $this->invoice->period_end?->format('j F Y'),
                'nextRenewalDate' => $this->invoice->next_renewal_date?->format('j F Y'),
                'hasDiscount' => $this->invoice->discount_amount > 0,
                'discountDescription' => $this->invoice->discount_description,
                'discountAmount' => number_format($this->invoice->discount_amount / 100, 2),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if ($this->invoice->pdf_path && Storage::exists($this->invoice->pdf_path)) {
            return [
                Attachment::fromStorage($this->invoice->pdf_path)
                    ->as("{$this->invoice->invoice_number}.pdf")
                    ->withMime('application/pdf'),
            ];
        }

        return [];
    }
}
