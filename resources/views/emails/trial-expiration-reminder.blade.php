<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Fynla Trial is Ending Soon</title>
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
            text-align: center;
        }
        .days-label {
            font-size: 14px;
            color: #1e40af;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .days-number {
            font-size: 36px;
            font-weight: bold;
            color: #3b82f6;
        }
        .days-text {
            font-size: 14px;
            color: #64748b;
        }
        .feature-list {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 6px;
            padding: 15px 20px;
            margin: 20px 0;
        }
        .feature-list p {
            margin: 0 0 10px 0;
            color: #991b1b;
            font-weight: 600;
        }
        .feature-list ul {
            margin: 0;
            padding-left: 20px;
            color: #7f1d1d;
        }
        .feature-list li {
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

            <p>Your <strong>{{ $planName }}</strong> plan trial is ending soon. Here's how much time you have left:</p>

            <div class="info-box">
                <div class="days-label">Trial ends in</div>
                <div class="days-number">{{ $daysRemaining }}</div>
                <div class="days-text">{{ $daysRemaining === 1 ? 'day' : 'days' }} remaining</div>
            </div>

            <div class="feature-list">
                <p>When your trial ends, you'll lose access to:</p>
                <ul>
                    <li>All financial planning tools and dashboards</li>
                    <li>Protection, savings, and investment tracking</li>
                    <li>Retirement and estate planning features</li>
                    <li>Document uploads and AI extraction</li>
                </ul>
            </div>

            <p>Upgrade now to keep your financial plan on track and never lose your data.</p>

            <div class="cta-container">
                <a href="{{ config('app.url') }}/checkout" class="cta-button">Upgrade Now</a>
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
