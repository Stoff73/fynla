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
        .header {
            background-color: #4F46E5;
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }
        .content {
            padding: 30px;
        }
        .content p {
            margin: 0 0 15px 0;
        }
        .info-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 15px;
            margin: 20px 0;
        }
        .info-box p {
            margin: 0;
            color: #1e40af;
        }
        .btn {
            display: inline-block;
            background-color: #4F46E5;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Account Linked Notification</h1>
        </div>

        <div class="content">
            <p>Hello {{ $spouse->name }},</p>

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

            <p style="margin-top: 30px;">If you did not expect this account linkage or have any questions, please contact {{ $linkedBy->name }} directly or reach out to our support team.</p>

            <p>Best regards,<br>The Fynla Team</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
