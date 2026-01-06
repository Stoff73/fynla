<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Fynla Account Has Been Created</title>
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
        .credentials-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 20px;
            margin: 20px 0;
        }
        .credentials-box h2 {
            margin: 0 0 15px 0;
            font-size: 18px;
            color: #1f2937;
        }
        .credential-item {
            margin: 10px 0;
        }
        .credential-label {
            font-weight: 600;
            color: #4b5563;
            display: block;
            margin-bottom: 5px;
        }
        .credential-value {
            font-family: monospace;
            background-color: #ffffff;
            border: 1px solid #d1d5db;
            padding: 8px 12px;
            border-radius: 4px;
            display: inline-block;
            font-size: 14px;
        }
        .warning-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
        }
        .warning-box p {
            margin: 0;
            color: #92400e;
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
            <h1>Welcome to Fynla</h1>
        </div>

        <div class="content">
            <p>Hello {{ $spouse->name }},</p>

            <p>{{ $createdBy->name }} has created an account for you on Fynla and added you as their spouse.</p>

            <p>This allows you both to manage your family's financial planning together, including sharing financial data and coordinating your planning goals.</p>

            <div class="credentials-box">
                <h2>Your Login Credentials</h2>

                <div class="credential-item">
                    <span class="credential-label">Email Address:</span>
                    <span class="credential-value">{{ $spouse->email }}</span>
                </div>

                <div class="credential-item">
                    <span class="credential-label">Temporary Password:</span>
                    <span class="credential-value">{{ $temporaryPassword }}</span>
                </div>
            </div>

            <div class="warning-box">
                <p><strong>Important:</strong> This is a temporary password. You will be required to change it when you first log in to ensure your account security.</p>
            </div>

            <center>
                <a href="{{ config('app.url') }}/login" class="btn">Log In to Your Account</a>
            </center>

            <p style="margin-top: 30px;">Once you log in, you can:</p>
            <ul>
                <li>Change your password to something secure and memorable</li>
                <li>Complete your personal profile information</li>
                <li>Review permission requests from {{ $createdBy->name }} to share financial data</li>
                <li>Start managing your own financial planning data</li>
            </ul>

            <p style="margin-top: 30px;">If you have any questions or did not expect to receive this email, please contact {{ $createdBy->name }} directly.</p>

            <p>Best regards,<br>The Fynla Team</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Fynla. All rights reserved.</p>
            <p>This is an automated message. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
