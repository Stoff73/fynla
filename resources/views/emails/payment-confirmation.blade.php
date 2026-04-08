<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Confirmation</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        .success-badge {
            background-color: #f0fdf4;
            border: 2px solid #22c55e;
            border-radius: 12px;
            padding: 15px 20px;
            margin: 20px 0;
            text-align: center;
        }
        .success-badge .checkmark {
            font-size: 32px;
            color: #22c55e;
        }
        .success-badge .label {
            font-size: 16px;
            font-weight: 600;
            color: #166534;
            margin-top: 5px;
        }
        .info-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .info-box table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-box td {
            padding: 8px 0;
            vertical-align: top;
        }
        .info-label {
            font-size: 14px;
            color: #64748b;
            width: 140px;
        }
        .info-value {
            font-size: 14px;
            color: #1e3a5f;
            font-weight: 600;
        }
        .cta-button {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 16px;
            margin: 20px 0;
        }
        .cta-container {
            text-align: center;
            margin: 25px 0;
        }
        .sign-off {
            margin-top: 30px;
        }
        .sign-off p {
            margin: 5px 0;
        }
        .logo {
            margin-top: 20px;
        }
        .logo img {
            max-width: 120px;
            height: auto;
        }
        .footer {
            background-color: #f9fafb;
            padding: 20px 30px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        .footer p {
            margin: 5px 0;
        }
        .footer a {
            color: #3b82f6;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="content">
            <p>Dear {{ $user->first_name ?? 'User' }},</p>

            <div class="success-badge">
                <div class="checkmark">&#10003;</div>
                <div class="label">Payment Successful</div>
            </div>

            <p>Thank you for your payment. Here are your receipt details:</p>

            <div class="info-box">
                <table>
                    <tr>
                        <td class="info-label">Plan:</td>
                        <td class="info-value">{{ $planName }} ({{ ucfirst($billingCycle) }})</td>
                    </tr>
                    @if($hasDiscount ?? false)
                    <tr>
                        <td class="info-label">Original Price:</td>
                        <td class="info-value" style="text-decoration: line-through; color: #94a3b8;">&pound;{{ $originalAmount }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Discount:</td>
                        <td class="info-value" style="color: #20B486;">{{ $discountDescription }} (Code: {{ $discountCode }})</td>
                    </tr>
                    @endif
                    <tr>
                        <td class="info-label">Amount Paid:</td>
                        <td class="info-value">&pound;{{ $amount }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Date:</td>
                        <td class="info-value">{{ $paymentDate }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Reference:</td>
                        <td class="info-value">FYN-{{ str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT) }}</td>
                    </tr>
                    @if(($autoRenew ?? false) && ($nextRenewalDate ?? null))
                    <tr>
                        <td class="info-label">Next Renewal:</td>
                        <td class="info-value">{{ $nextRenewalDate }} (&pound;{{ $renewalAmount }})</td>
                    </tr>
                    @endif
                </table>
            </div>

            <p>Your subscription is active and you have full access to all Fynla features.</p>

            <div class="cta-container">
                <a href="{{ config('app.url') }}/dashboard" class="cta-button">Go to Dashboard</a>
            </div>

            <div class="sign-off">
                <p>Kindest regards,</p>
                <p><strong>The Fynla Team (Chris & Brett)</strong></p>
                <div class="logo">
                    <img src="{{ config('app.url') }}/images/logos/logoMain.png" alt="Fynla">
                </div>
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>Need help? <a href="mailto:support@fynla.org">Contact Support</a></p>
        </div>
    </div>
</body>
</html>
