<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Cancelled</title>
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
        .highlight-box {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 20px 0;
        }
        .highlight-box p {
            margin: 0;
            font-size: 14px;
            color: #1e40af;
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

            <p>We're sorry to see you go. Your Fynla subscription has been cancelled.</p>

            <div class="info-box">
                <table>
                    <tr>
                        <td class="info-label">Plan:</td>
                        <td class="info-value">{{ $planName }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Billing Cycle:</td>
                        <td class="info-value">{{ ucfirst($billingCycle) }}</td>
                    </tr>
                    @if($accessUntil)
                    <tr>
                        <td class="info-label">Access Until:</td>
                        <td class="info-value">{{ $accessUntil }}</td>
                    </tr>
                    @endif
                </table>
            </div>

            @if($accessUntil)
            <div class="highlight-box">
                <p><strong>You still have full access</strong> to all Fynla features until {{ $accessUntil }}. After that date, your data will be retained for 30 days before being permanently deleted.</p>
            </div>
            @endif

            <p>If you change your mind, you can resubscribe at any time from your profile to keep all your financial plans and data.</p>

            <div class="cta-container">
                <a href="{{ config('app.url') }}/profile#subscription" class="cta-button">Manage Subscription</a>
            </div>

            <div class="sign-off">
                <p>Kindest regards,</p>
                <p><strong>The Fynla Team (Chris & Brett)</strong></p>
                <div class="logo">
                    <img src="{{ config('app.url') }}/images/logoMain.png" alt="Fynla">
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
