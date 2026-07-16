<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Mail\InvoiceEmail;
use App\Models\DiscountCode;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    public function __construct(private readonly TierConfigurationStore $tierStore) {}

    /**
     * Generate an invoice for a completed payment.
     */
    public function generateInvoice(Payment $payment, ?DiscountCode $discount = null): Invoice
    {
        $payment->refresh();
        if ($payment->invoice_id !== null) {
            return Invoice::findOrFail($payment->invoice_id);
        }

        $existingInvoice = Invoice::where('payment_id', $payment->id)->first();
        if ($existingInvoice !== null) {
            if ($existingInvoice->pdf_path === null || ! Storage::exists($existingInvoice->pdf_path)) {
                $existingInvoice->update(['pdf_path' => $this->generatePdf($existingInvoice)]);
            }
            $payment->update(['invoice_id' => $existingInvoice->id]);

            return $existingInvoice;
        }

        $subscription = $payment->subscription;
        $user = $payment->user;

        $subtotalAmount = $payment->amount + ($payment->discount_amount ?? 0);
        $discountAmount = $payment->discount_amount ?? 0;
        $totalAmount = $payment->amount;

        $nextRenewalDate = $subscription->current_period_end;

        $invoice = Invoice::create([
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'subscription_id' => $subscription->id,
            'invoice_number' => Invoice::generateNumber(),
            'status' => 'issued',
            'subtotal_amount' => $subtotalAmount,
            'discount_amount' => $discountAmount,
            'tax_amount' => 0,
            'total_amount' => $totalAmount,
            'currency' => $payment->currency ?? 'GBP',
            'discount_code' => $discount?->code,
            'discount_description' => $discount ? $this->describeDiscount($discount) : null,
            'plan_name' => $this->resolvePlanName($payment->plan_slug ?? $subscription->plan),
            'billing_cycle' => $payment->billing_cycle ?? $subscription->billing_cycle,
            'period_start' => $subscription->current_period_start,
            'period_end' => $subscription->current_period_end,
            'next_renewal_date' => $subscription->auto_renew ? $nextRenewalDate : null,
            'issued_at' => now(),
            'billing_name' => trim(($user->first_name ?? '').' '.($user->surname ?? '')),
            'billing_address' => $this->buildAddress($user),
            'billing_email' => $user->email,
        ]);

        // Generate and store PDF
        $pdfPath = $this->generatePdf($invoice);
        $invoice->update(['pdf_path' => $pdfPath]);

        // Link invoice to payment
        $payment->update(['invoice_id' => $invoice->id]);

        Log::info('Invoice generated', [
            'invoice_number' => $invoice->invoice_number,
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'total_amount' => $totalAmount,
        ]);

        return $invoice;
    }

    /**
     * Generate a PDF for the invoice and store it.
     *
     * @return string Storage path of the generated PDF
     */
    public function generatePdf(Invoice $invoice): string
    {
        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'user' => $invoice->user,
        ]);
        $pdf->setPaper('A4', 'portrait');

        $directory = "invoices/{$invoice->user_id}";
        $filename = "{$invoice->invoice_number}.pdf";
        $path = "{$directory}/{$filename}";

        Storage::makeDirectory($directory);
        Storage::put($path, $pdf->output());

        return $path;
    }

    /**
     * Email the invoice to the user with PDF attachment.
     */
    public function emailInvoice(Invoice $invoice, User $user): void
    {
        try {
            Mail::to($user->email)->send(new InvoiceEmail($invoice, $user));

            Log::info('Invoice emailed', [
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to email invoice', [
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Regenerate PDF from existing invoice data.
     */
    public function regeneratePdf(Invoice $invoice): string
    {
        $path = $this->generatePdf($invoice);
        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    private function buildAddress($user): ?string
    {
        $lines = array_filter([
            $user->address_line_1 ?? null,
            $user->address_line_2 ?? null,
            collect([$user->city ?? null, $user->county ?? null, $user->postcode ?? null])->filter()->implode(', '),
        ]);

        return count($lines) > 0 ? implode("\n", $lines) : null;
    }

    /**
     * Resolve a human-readable plan name for invoice line items.
     * For canonical tier-based plans the display name comes from
     * TierConfigurationStore so a single admin edit propagates to all new invoices.
     * Legacy plan slugs (student/standard/family/pro) fall back to ucfirst.
     */
    private function resolvePlanName(?string $planSlug): string
    {
        if ($planSlug && in_array($planSlug, TierConfigurationStore::TIERS, true)) {
            try {
                return $this->tierStore->forTier($planSlug)->display_name;
            } catch (\Throwable) {
                // Store unavailable — fall through to fallback
            }
        }

        return ucfirst((string) $planSlug);
    }

    private function describeDiscount(DiscountCode $discount): string
    {
        return match ($discount->type) {
            'percentage' => "{$discount->value}% off",
            'fixed_amount' => '£'.number_format($discount->value / 100, 2).' off',
            default => 'Discount applied',
        };
    }
}
