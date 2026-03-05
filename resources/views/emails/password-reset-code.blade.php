<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Fynla Password</title>
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
            text-align: center;
        }
        .content p {
            margin: 0 0 15px 0;
            text-align: left;
        }
        .code-box {
            background-color: #f0f9ff;
            border: 2px solid #3b82f6;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
            text-align: center;
        }
        .code-label {
            font-size: 14px;
            color: #1e40af;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .verification-code {
            font-family: 'Courier New', monospace;
            font-size: 42px;
            font-weight: bold;
            letter-spacing: 8px;
            color: #3b82f6;
            background-color: #ffffff;
            padding: 15px 25px;
            border-radius: 8px;
            display: inline-block;
            border: 1px solid #bfdbfe;
        }
        .info-box {
            background-color: #f0f9ff;
            border: 1px solid #3b82f6;
            border-radius: 6px;
            padding: 12px 15px;
            margin: 20px 0;
            text-align: left;
        }
        .info-box p {
            margin: 0;
            color: #1e40af;
            font-size: 14px;
        }
        .security-note {
            background-color: #f0fdf4;
            border: 1px solid #22c55e;
            border-radius: 6px;
            padding: 12px 15px;
            margin: 20px 0;
            text-align: left;
        }
        .security-note p {
            margin: 0;
            color: #166534;
            font-size: 14px;
        }
        .sign-off {
            margin-top: 30px;
            text-align: left;
        }
        .sign-off p {
            margin: 5px 0;
        }
        .logo {
            margin-top: 20px;
            text-align: left;
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

            <p>We received a request to reset your password. Use the verification code below to proceed with your password reset:</p>

            <div class="code-box">
                <div class="code-label">Your verification code</div>
                <div class="verification-code">{{ $code }}</div>
            </div>

            <div class="info-box">
                <p><strong>Important:</strong> This code will expire in 15 minutes. If you need a new code, click "Resend Code" in the password reset form.</p>
            </div>

            <div class="security-note">
                <p><strong>Didn't request this?</strong> If you did not request a password reset, please ignore this email. Your password will remain unchanged, but you may want to review your account security.</p>
            </div>

            <p style="margin-top: 20px; color: #6b7280; font-size: 14px;">Never share this code with anyone. Fynla will never ask you for this code via phone, text, or other emails.</p>

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
