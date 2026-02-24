<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Fynla Subscription Renews Soon</title>
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
            background-color: #f0f9ff;
            border: 2px solid #3b82f6;
            border-radius: 12px;
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
        .manage-link {
            text-align: center;
            margin: 15px 0;
        }
        .manage-link a {
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
        }
        .manage-link a:hover {
            text-decoration: underline;
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

            <p>This is a friendly reminder that your Fynla subscription will automatically renew in <strong>7 days</strong>.</p>

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
                    <tr>
                        <td class="info-label">Renewal Amount:</td>
                        <td class="info-value">&pound;{{ $amount }}</td>
                    </tr>
                    <tr>
                        <td class="info-label">Renewal Date:</td>
                        <td class="info-value">{{ $renewalDate }}</td>
                    </tr>
                </table>
            </div>

            <p>If you wish to continue, no action is required &mdash; your subscription will renew automatically using your saved payment method.</p>

            <p>If you would like to make changes or cancel before your renewal date, you can manage your subscription from your profile.</p>

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
