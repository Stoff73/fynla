<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Cancelled</title>
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
                            <h2 style="font-size: 36px; font-weight: 800; color: #ffffff; line-height: 1.15; margin: 0;">Subscription <span style="color: #f9a8c0;">cancelled</span></h2>
                            <p style="font-size: 14px; color: #a8b0bf; margin: 6px 0 0 0;">We're sorry to see you go</p>
                        </td>
                        <td style="vertical-align: bottom; width: 120px;" align="right">
                            <img src="https://fynla.org/images/Fyn/Design%20Character%20001a.png" alt="Fyn" width="130" height="171" style="height: 171px; width: 130px; display: block; margin-bottom: -15px;" />
                        </td>
                    </tr></table>
                </td></tr>

                {{-- Body: Light Pink --}}
                <tr><td style="background: #fce4ec; padding: 32px 36px;">
                    <p style="font-size: 20px; color: #1F2A44; font-weight: 700; margin: 0 0 10px 0;">Hi {{ $user->first_name ?? 'there' }},</p>
                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;">Your <strong>{{ $planName }}</strong> plan has been cancelled as requested.@if($accessUntil) You'll continue to have full access until <strong>{{ $accessUntil }}</strong>, the end of your current billing period.@endif</p>

                    @if($accessUntil)
                    {{-- Alert: Light Pink --}}
                    <div style="background: #ffffff; border-radius: 10px; padding: 16px 20px; margin: 16px 0;">
                        <p style="font-size: 13px; margin: 0; color: #1F2A44;"><strong>What happens after {{ $accessUntil }}?</strong><br/>Your data will be retained for 30 days. After that, it will be permanently deleted unless you resubscribe.</p>
                    </div>
                    @endif

                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0;">If there's anything we could have done differently, we'd love to hear from you.</p>
                </td></tr>

                {{-- CTA Block: Raspberry Gradient --}}
                <tr><td style="background-color: #e74c6f; padding: 28px 36px; text-align: center;">
                    <p style="color: #ffffff; font-size: 16px; font-weight: 600; margin: 0 0 16px 0;">Changed your mind? Resubscribe instantly</p>
                    <a href="https://fynla.org/checkout" style="display: inline-block; padding: 14px 40px; border-radius: 12px; font-size: 16px; font-weight: 700; background: #ffffff; color: #e74c6f; box-shadow: 0 4px 14px #d9d3cc;">Resubscribe</a>
                </td></tr>

                {{-- Share Feedback: Eggshell --}}
                <tr><td style="background: #f5f0eb; padding: 20px 36px; text-align: center;">
                    <a href="mailto:support@fynla.org?subject=Cancellation%20Feedback" style="display: inline-block; padding: 12px 32px; border-radius: 10px; font-size: 14px; font-weight: 600; background: #fce4ec; color: #1F2A44;">Share feedback</a>
                </td></tr>

                {{-- Footer --}}
                <tr><td bgcolor="#1F2A44" style="background: #1F2A44; padding: 24px 36px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%"><tr>
                        <td style="vertical-align: top; width: 100px;">
                            <a href="https://fynla.org" style="display: inline-block;"><img src="https://fynla.org/images/logos/LogoHiResFynlaLight.png" alt="Fynla" width="62" height="28" style="height: 28px; width: 62px;" /></a>
                        </td>
                        <td style="vertical-align: top; padding-left: 24px;">
                            <p style="margin: 0 0 10px 0;">
                                <a href="https://fynla.org/privacy" style="font-size: 12px; color: #b3b9c5; text-decoration: none; margin-right: 16px;">Privacy Policy</a>
                                <a href="https://fynla.org/terms" style="font-size: 12px; color: #b3b9c5; text-decoration: none; margin-right: 16px;">Terms of Service</a>
                                <a href="mailto:support@fynla.org" style="font-size: 12px; color: #b3b9c5; text-decoration: none; margin-right: 16px;">Help</a>
                                <a href="https://fynla.org/unsubscribe" style="font-size: 12px; color: #b3b9c5; text-decoration: none;">Unsubscribe</a>
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
