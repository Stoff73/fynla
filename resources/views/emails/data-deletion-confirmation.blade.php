<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Deleted</title>
    <style>
        body { font-family: 'Segoe UI', Inter, sans-serif; margin: 0; padding: 0; background-color: #f5f0eb; color: #1F2A44; }
        a { text-decoration: none; }
    </style>
</head>
<body style="font-family: 'Segoe UI', Inter, sans-serif; margin: 0; padding: 0; background-color: #f5f0eb; color: #1F2A44;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f5f0eb;">
        <tr><td align="center" style="padding: 20px 0;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="600" style="max-width: 600px; width: 100%; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px #d9d3cc;">

                {{-- Logo Bar --}}
                <tr><td style="background: #ffffff; padding: 14px 36px;">
                    <a href="https://fynla.org" style="display: inline-block;">
                        <img src="https://fynla.org/images/logos/LogoHiResFynlaDark.png" alt="Fynla" width="71" height="32" style="height: 32px; width: 71px; display: block;" />
                    </a>
                </td></tr>

                {{-- Hero Header --}}
                <tr><td bgcolor="#1F2A44" style="background-color: #1F2A44; background-image: linear-gradient(135deg, #1F2A44, #e74c6f); padding: 28px 36px 0; min-height: 180px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr>
                        <td style="padding-bottom: 28px; vertical-align: bottom;">
                            <h2 style="font-size: 36px; font-weight: 800; color: #ffffff; line-height: 1.15; margin: 0;">Account <span style="color: #f9a8c0;">deleted</span></h2>
                            <p style="font-size: 14px; color: #a8b0bf; margin: 6px 0 0 0;">Your data has been permanently removed</p>
                        </td>
                        <td style="vertical-align: bottom; width: 120px;" align="right">
                            <img src="https://fynla.org/images/Fyn/Design%20Character%20001a.png" alt="Fyn" width="130" height="171" style="height: 171px; width: 130px; display: block; margin-bottom: -15px;" />
                        </td>
                    </tr></table>
                </td></tr>

                {{-- Body: Eggshell --}}
                <tr><td style="background: #f5f0eb; padding: 32px 36px;">
                    <p style="font-size: 20px; color: #1F2A44; font-weight: 700; margin: 0 0 10px 0;">Hi {{ $firstName ?? 'there' }},</p>
                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;">Your Fynla account and all associated data have been permanently deleted as requested. This includes all financial records, projections, goals, and conversation history.</p>

                    {{-- Detail Box --}}
                    <div style="background: #ffffff; border-radius: 10px; padding: 16px 20px; margin: 16px 0;">
                        <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                            <tr>
                                <td style="padding: 10px 0; border-bottom: 1px solid #e8e2db; font-size: 14px; color: #888;">Account</td>
                                <td style="padding: 10px 0; border-bottom: 1px solid #e8e2db; font-size: 14px; font-weight: 600; color: #1F2A44;" align="right">{{ $email }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px 0; font-size: 14px; color: #888;">Deletion date</td>
                                <td style="padding: 10px 0; font-size: 14px; font-weight: 600; color: #1F2A44;" align="right">{{ now()->format('j F Y') }}</td>
                            </tr>
                        </table>
                    </div>

                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;">This data cannot be recovered. If you'd like to use Fynla again in the future, you're welcome to create a new account at any time.</p>

                    {{-- Alert: Light Blue --}}
                    <div style="background: #dbeafe; border-radius: 10px; padding: 16px 20px; margin: 16px 0;">
                        <p style="font-size: 13px; margin: 0; color: #1F2A44;">A copy of this confirmation has been saved for our records in compliance with GDPR. No personal financial data has been retained.</p>
                    </div>
                </td></tr>

                {{-- CTA Block: Horizon Blue --}}
                <tr><td style="background: #1F2A44; padding: 28px 36px; text-align: center;">
                    <p style="color: #c0c5cf; font-size: 16px; font-weight: 600; margin: 0 0 16px 0;">Start fresh with a new account</p>
                    <a href="https://fynla.org" style="display: inline-block; padding: 14px 40px; border-radius: 12px; font-size: 16px; font-weight: 700; background: #e74c6f; color: #ffffff; box-shadow: 0 4px 14px #cccccc;">Visit Fynla</a>
                </td></tr>

                {{-- Footer --}}
                <tr><td style="background: #1F2A44; padding: 0 36px 24px;">
                    <div style="height: 1px; background: #353f55; margin-bottom: 24px;"></div>
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr>
                        <td style="vertical-align: top; width: 100px;">
                            <a href="https://fynla.org" style="display: inline-block;"><img src="https://fynla.org/images/logos/LogoHiResFynlaLight.png" alt="Fynla" width="62" height="28" style="height: 28px; width: 62px;" /></a>
                        </td>
                        <td style="vertical-align: top; padding-left: 24px;">
                            <p style="margin: 0 0 10px 0;">
                                <a href="https://fynla.org/privacy" style="font-size: 12px; color: #b3b9c5; text-decoration: none; margin-right: 16px;">Privacy Policy</a>
                                <a href="https://fynla.org/terms" style="font-size: 12px; color: #b3b9c5; text-decoration: none;">Terms of Service</a>
                            </p>
                            <p style="font-size: 11px; color: #7a8194; line-height: 1.5; margin: 0;">&copy; {{ date('Y') }} Fynla Ltd, 124 City Road, London, EC1V 2NX<br/>This is an automated message. Please do not reply directly to this email.</p>
                        </td>
                    </tr></table>
                </td></tr>

            </table>
        </td></tr>
    </table>
</body>
</html>
