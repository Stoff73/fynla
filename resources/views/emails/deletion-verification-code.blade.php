<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deletion Verification Code</title>
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
            background-color: #dc2626;
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
            text-align: center;
        }
        .content p {
            margin: 0 0 15px 0;
            text-align: left;
        }
        .code-box {
            background-color: #fef2f2;
            border: 2px solid #dc2626;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        .code-label {
            font-size: 14px;
            color: #991b1b;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .verification-code {
            font-family: 'Courier New', monospace;
            font-size: 42px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #dc2626;
            background-color: #ffffff;
            padding: 15px 25px;
            border-radius: 8px;
            display: inline-block;
            border: 1px solid #fecaca;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 12px 15px;
            margin: 20px 0;
            text-align: left;
        }
        .warning-box p {
            margin: 0;
            color: #92400e;
            font-size: 14px;
        }
        .danger-note {
            background-color: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 12px 15px;
            margin: 20px 0;
            text-align: left;
        }
        .danger-note p {
            margin: 0;
            color: #991b1b;
            font-size: 14px;
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
            <h1>Account Deletion Verification</h1>
        </div>

        <div class="content">
            <p>Hello {{ $user->first_name ?? 'there' }},</p>

            <p>You requested to delete your Fynla account or data. Use the following code to verify your identity:</p>

            <div class="code-box">
                <div class="code-label">Your verification code</div>
                <div class="verification-code">{{ $code }}</div>
            </div>

            <div class="warning-box">
                <p><strong>Important:</strong> This code expires in 15 minutes. If you did not request this deletion, please ignore this email and your account will remain safe.</p>
            </div>

            <div class="danger-note">
                <p><strong>Warning:</strong> Deletion is permanent and cannot be undone. All your financial data, goals, and planning history will be permanently removed.</p>
            </div>

            <p style="margin-top: 20px; color: #6b7280; font-size: 14px;">If you did not request this deletion, someone may have access to your account. Please change your password immediately and contact support.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
