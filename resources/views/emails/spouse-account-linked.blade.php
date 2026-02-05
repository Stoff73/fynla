<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Fynla Account Has Been Linked</title>
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
            border: 1px solid #3b82f6;
            border-radius: 6px;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box p {
            margin: 0;
            color: #1e40af;
        }
        .btn {
            display: inline-block;
            background-color: #3b82f6;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
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
            <p>Dear {{ $spouse->first_name ?? $spouse->name }},</p>

            <p>{{ $linkedBy->name }} has linked their Fynla account to yours as your spouse.</p>

            <p>This connection allows you both to:</p>
            <ul>
                <li>Coordinate your family's financial planning</li>
                <li>Share financial data (with your permission)</li>
                <li>View joint accounts and assets</li>
                <li>Work together on shared financial goals</li>
            </ul>

            <div class="info-box">
                <p><strong>Privacy Notice:</strong> {{ $linkedBy->name }} cannot automatically see your financial data. They will need to request permission from you, which you can approve or decline at any time from your account settings.</p>
            </div>

            <center>
                <a href="{{ config('app.url') }}/login" class="btn">Log In to Your Account</a>
            </center>

            <p style="margin-top: 30px;">What you can do now:</p>
            <ul>
                <li>Review and manage permission requests in your Settings</li>
                <li>Configure which data you want to share with {{ $linkedBy->name }}</li>
                <li>Send your own permission request to view their data</li>
                <li>Continue managing your financial planning independently</li>
            </ul>

            <p style="margin-top: 30px;">If you did not expect this account linkage or have any questions, please contact {{ $linkedBy->name }} directly or <a href="mailto:support@fynla.org" style="color: #3b82f6;">contact support</a>.</p>

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
