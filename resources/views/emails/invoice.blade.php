<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Fynla Invoice</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; line-height: 1.6; color: #333333; background-color: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 20px auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); }
        .header { background: linear-gradient(135deg, #1F2A44 0%, #2d3a5c 100%); padding: 24px 30px; text-align: center; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0; font-weight: 700; }
        .header p { color: rgba(255,255,255,0.7); font-size: 13px; margin: 4px 0 0; }
        .content { padding: 30px; }
        .content p { margin: 0 0 15px 0; }
        .invoice-badge { display: inline-block; background: #f0f0f0; color: #1F2A44; font-size: 13px; font-weight: 600; padding: 4px 12px; border-radius: 4px; letter-spacing: 0.5px; }
        .summary-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .summary-table td { padding: 8px 0; font-size: 14px; border-bottom: 1px solid #eee; }
        .summary-table td.label { color: #717171; width: 40%; }
        .summary-table td.value { text-align: right; font-weight: 600; color: #1F2A44; }
        .total-row td { border-top: 2px solid #1F2A44; border-bottom: none; font-size: 16px; padding-top: 12px; }
        .discount-row td.value { color: #20B486; }
        .renewal-box { background: #f0fdf7; border-left: 4px solid #20B486; padding: 12px 16px; margin: 20px 0; font-size: 13px; border-radius: 0 4px 4px 0; }
        .btn { display: inline-block; background: #E83E6D; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 6px; font-weight: 600; font-size: 14px; margin: 16px 0; }
        .footer { padding: 20px 30px; background: #fafafa; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice</h1>
            <p>{{ $invoice->invoice_number }}</p>
        </div>

        <div class="content">
            <p>Hello {{ $user->first_name ?? 'there' }},</p>

            <p>Thank you for your payment. Here is a summary of your invoice:</p>

            <table class="summary-table">
                <tr>
                    <td class="label">Invoice Number</td>
                    <td class="value"><span class="invoice-badge">{{ $invoice->invoice_number }}</span></td>
                </tr>
                <tr>
                    <td class="label">Date</td>
                    <td class="value">{{ $invoiceDate }}</td>
                </tr>
                <tr>
                    <td class="label">Plan</td>
                    <td class="value">{{ $planName }} ({{ ucfirst($billingCycle) }})</td>
                </tr>
                <tr>
                    <td class="label">Billing Period</td>
                    <td class="value">{{ $periodStart }} &mdash; {{ $periodEnd }}</td>
                </tr>
                @if($hasDiscount)
                <tr class="discount-row">
                    <td class="label">Discount ({{ $discountDescription }})</td>
                    <td class="value">-&pound;{{ $discountAmount }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td class="label">Total Paid</td>
                    <td class="value">&pound;{{ $amount }}</td>
                </tr>
            </table>

            @if($nextRenewalDate)
            <div class="renewal-box">
                Your subscription will automatically renew on <strong>{{ $nextRenewalDate }}</strong>.
                You can cancel at any time from your profile settings.
            </div>
            @endif

            <p style="text-align: center;">
                <a href="{{ config('app.url') }}/profile" class="btn">View Your Subscription</a>
            </p>

            <p style="font-size: 13px; color: #717171;">A PDF copy of this invoice is attached to this email for your records.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
            <p>Questions? Contact us at <a href="mailto:support@fynla.org" style="color: #E83E6D;">support@fynla.org</a></p>
        </div>
    </div>
</body>
</html>
