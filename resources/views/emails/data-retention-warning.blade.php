<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Retention Warning</title>
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
        .countdown-box {
            background-color: {{ $isFinalWarning ? '#fef2f2' : ($isUrgent ? '#eff6ff' : '#f0f9ff') }};
            border: 2px solid {{ $isFinalWarning ? '#ef4444' : '#3b82f6' }};
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }
        .countdown-label {
            font-size: 14px;
            color: {{ $isFinalWarning ? '#991b1b' : '#1e40af' }};
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .countdown-number {
            font-size: 36px;
            font-weight: bold;
            color: {{ $isFinalWarning ? '#ef4444' : '#3b82f6' }};
        }
        .countdown-text {
            font-size: 14px;
            color: #64748b;
        }
        .data-list {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .data-list p {
            margin: 0 0 10px 0;
            font-weight: 600;
            color: #374151;
        }
        .data-list ul {
            margin: 0;
            padding-left: 20px;
            color: #6b7280;
        }
        .data-list li {
            margin-bottom: 5px;
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

            @if($isFinalWarning)
                <p><strong>This is your final warning.</strong> Your Fynla data will be permanently deleted tomorrow.</p>
            @elseif($isUrgent)
                <p>Your Fynla data will be permanently deleted in <strong>{{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}</strong>. Subscribe now to keep your financial plans.</p>
            @elseif($daysRemaining === 15)
                <p>This is a reminder that your Fynla data will be permanently deleted in <strong>15 days</strong>.</p>
            @else
                <p>Your Fynla subscription has ended. Your data will be retained for <strong>30 days</strong> before being permanently deleted.</p>
            @endif

            <div class="countdown-box">
                <div class="countdown-label">{{ $isFinalWarning ? 'Final Warning' : 'Data deleted in' }}</div>
                <div class="countdown-number">{{ $daysRemaining }}</div>
                <div class="countdown-text">{{ $daysRemaining === 1 ? 'day' : 'days' }} remaining</div>
            </div>

            <div class="data-list">
                <p>The following data will be permanently deleted:</p>
                <ul>
                    <li>Properties, mortgages, and property valuations</li>
                    <li>Pensions (defined contribution, defined benefit, state pension)</li>
                    <li>Investment accounts and holdings</li>
                    <li>Savings accounts and ISA tracking</li>
                    <li>Protection policies (life, critical illness, income protection)</li>
                    <li>Goals and life events</li>
                    <li>Estate plans, trusts, and wills</li>
                    <li>Uploaded documents</li>
                </ul>
            </div>

            <p>Subscribe now to regain full access and keep all your financial plans and data safe.</p>

            <div class="cta-container">
                <a href="{{ config('app.url') }}/checkout" class="cta-button">Subscribe Now</a>
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
