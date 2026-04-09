<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', 'Inter', Helvetica, Arial, sans-serif; font-size: 14px; color: #1F2A44; line-height: 1.5; }
        .container { max-width: 700px; margin: 0 auto; padding: 40px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .logo img { height: 40px; width: auto; }
        .logo-sub { font-size: 11px; color: #717171; margin-top: 4px; }
        .invoice-title { text-align: right; }
        .invoice-title h1 { font-size: 24px; font-weight: 700; color: #1F2A44; margin-bottom: 4px; }
        .invoice-number { font-size: 14px; color: #717171; }
        .meta-row { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .meta-block { width: 48%; }
        .meta-label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: #717171; margin-bottom: 4px; letter-spacing: 0.5px; }
        .meta-value { font-size: 14px; color: #1F2A44; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        thead th { background: #FDFAF7; padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; color: #717171; border-bottom: 2px solid #EEEEEE; letter-spacing: 0.5px; }
        thead th.right { text-align: right; }
        tbody td { padding: 12px; border-bottom: 1px solid #EEEEEE; font-size: 14px; }
        tbody td.right { text-align: right; }
        .totals { margin-top: 10px; }
        .totals-row { display: flex; justify-content: flex-end; padding: 4px 0; }
        .totals-label { width: 150px; text-align: right; padding-right: 20px; color: #717171; font-size: 13px; }
        .totals-value { width: 100px; text-align: right; font-size: 14px; }
        .totals-row.total { border-top: 2px solid #1F2A44; margin-top: 6px; padding-top: 8px; }
        .totals-row.total .totals-label { font-weight: 700; color: #1F2A44; }
        .totals-row.total .totals-value { font-weight: 700; font-size: 16px; }
        .renewal-box { background: #F0FDF7; border: 1px solid #20B486; border-radius: 6px; padding: 12px 16px; margin: 24px 0; font-size: 13px; color: #1F2A44; }
        .renewal-box strong { color: #20B486; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #EEEEEE; text-align: center; font-size: 12px; color: #717171; }
        .footer a { color: #E83E6D; text-decoration: none; }
        .paid-badge { display: inline-block; background: #DCFCE7; color: #166534; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 4px; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header --}}
        <table style="width:100%; margin-bottom: 30px; border: none;">
            <tr>
                <td style="border: none; padding: 0;">
                    <div class="logo"><img src="{{ public_path('images/logos/LogoHiResFynlaDark.png') }}" alt="Fynla"></div>
                    <div class="logo-sub">Your financial companion for life</div>
                </td>
                <td style="border: none; padding: 0; text-align: right;">
                    <div class="invoice-title">
                        <h1>Invoice</h1>
                        <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                        <div style="margin-top: 6px;"><span class="paid-badge">Paid</span></div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Meta --}}
        <table style="width:100%; margin-bottom: 24px; border: none;">
            <tr>
                <td style="border: none; padding: 0; width: 50%; vertical-align: top;">
                    <div class="meta-label">Billed To</div>
                    <div class="meta-value">{{ $invoice->billing_name ?? 'Customer' }}</div>
                    @if($invoice->billing_address)
                        @foreach(explode("\n", $invoice->billing_address) as $addressLine)
                            <div class="meta-value" style="color: #717171;">{{ $addressLine }}</div>
                        @endforeach
                    @endif
                    <div class="meta-value" style="color: #717171;">{{ $invoice->billing_email }}</div>
                </td>
                <td style="border: none; padding: 0; width: 50%; vertical-align: top; text-align: right;">
                    <div class="meta-label">Invoice Date</div>
                    <div class="meta-value">{{ $invoice->issued_at->format('d F Y') }}</div>
                    <div class="meta-label" style="margin-top: 8px;">Billing Period</div>
                    <div class="meta-value">{{ $invoice->period_start->format('d M Y') }} &mdash; {{ $invoice->period_end->format('d M Y') }}</div>
                </td>
            </tr>
        </table>

        {{-- Line Items --}}
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Billing Cycle</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ $invoice->plan_name }} Plan</td>
                    <td>{{ ucfirst($invoice->billing_cycle) }}</td>
                    <td class="right">&pound;{{ number_format($invoice->subtotal_amount / 100, 2) }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Totals --}}
        <div class="totals">
            <div class="totals-row">
                <span class="totals-label">Subtotal</span>
                <span class="totals-value">&pound;{{ number_format($invoice->subtotal_amount / 100, 2) }}</span>
            </div>
            @if($invoice->discount_amount > 0)
            <div class="totals-row">
                <span class="totals-label">Discount{{ $invoice->discount_description ? ' (' . $invoice->discount_description . ')' : '' }}{{ $invoice->discount_code ? ' — Code: ' . $invoice->discount_code : '' }}</span>
                <span class="totals-value" style="color: #20B486;">-&pound;{{ number_format($invoice->discount_amount / 100, 2) }}</span>
            </div>
            @endif
            @if($invoice->tax_amount > 0)
            <div class="totals-row">
                <span class="totals-label">Tax</span>
                <span class="totals-value">&pound;{{ number_format($invoice->tax_amount / 100, 2) }}</span>
            </div>
            @endif
            <div class="totals-row total">
                <span class="totals-label">Total Paid</span>
                <span class="totals-value">&pound;{{ number_format($invoice->total_amount / 100, 2) }}</span>
            </div>
        </div>

        {{-- Renewal Notice --}}
        @if($invoice->next_renewal_date)
        @php
            $subAmount = $invoice->subscription?->amount;
            $renewalAmount = ($subAmount && $subAmount > 0) ? $subAmount : $invoice->subtotal_amount;
        @endphp
        <div class="renewal-box">
            <strong>Auto-renewal:</strong> Your subscription will automatically renew on <strong>{{ $invoice->next_renewal_date->format('d F Y') }}</strong> at <strong>&pound;{{ number_format($renewalAmount / 100, 2) }}/{{ $invoice->billing_cycle === 'monthly' ? 'month' : 'year' }}</strong>.
            You can cancel at any time from your profile settings.
        </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            <p>Thank you for choosing Fynla.</p>
            <p style="margin-top: 4px;">Questions? Contact us at <a href="mailto:support@fynla.org">support@fynla.org</a></p>
            <p style="margin-top: 8px; font-size: 11px;">Payment processed by Revolut &bull; Currency: {{ $invoice->currency }}</p>
            <p style="margin-top: 16px; font-size: 11px; color: #717171;">Fynla Limited is registered in England &amp; Wales, Company Number: 16903721</p>
            <p style="font-size: 11px; color: #717171;">Registered address: 124 City Road, London, England, EC1V 2NX</p>
        </div>
    </div>
</body>
</html>
