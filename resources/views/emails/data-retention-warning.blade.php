<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Retention Warning</title>
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
                            <h2 style="font-size: 36px; font-weight: 800; color: #ffffff; line-height: 1.15; margin: 0;">Data <span style="color: #f9a8c0;">retention</span> warning</h2>
                            <p style="font-size: 14px; color: #a8b0bf; margin: 6px 0 0 0;">Your data will be deleted in {{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}</p>
                        </td>
                        <td style="vertical-align: bottom; width: 120px;" align="right">
                            <img src="https://fynla.org/images/Fyn/Design%20Character%20001a.png" alt="Fyn" width="130" height="171" style="height: 171px; width: 130px; display: block; margin-bottom: -15px;" />
                        </td>
                    </tr></table>
                </td></tr>

                {{-- Body: Light Pink --}}
                <tr><td style="background: #fce4ec; padding: 32px 36px;">
                    <p style="font-size: 20px; color: #1F2A44; font-weight: 700; margin: 0 0 10px 0;">Hi {{ $user->first_name ?? 'there' }},</p>

                    @if($isFinalWarning)
                        <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;"><strong>This is your final warning.</strong> Your Fynla data will be permanently deleted tomorrow.</p>
                    @elseif($isUrgent)
                        <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;">Your Fynla data will be permanently deleted in <strong>{{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}</strong>. Subscribe now to keep your financial plans.</p>
                    @else
                        <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;">Your Fynla subscription has ended. Your financial data will be permanently deleted in <strong>{{ $daysRemaining }} {{ $daysRemaining === 1 ? 'day' : 'days' }}</strong>.</p>
                    @endif

                    {{-- Alert: Raspberry --}}
                    <div style="background: #e74c6f; border-radius: 10px; padding: 16px 20px; margin: 16px 0;">
                        <p style="font-size: 13px; margin: 0; color: #ffffff;"><strong>This action is irreversible.</strong> Once deleted, your financial data, projections, goals, and conversation history with Fyn cannot be recovered.</p>
                    </div>

                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0;">To keep your data, simply resubscribe before the deletion date. Everything will be exactly as you left it.</p>
                </td></tr>

                {{-- CTA Block: Light Pink --}}
                <tr><td style="background: #fce4ec; padding: 28px 36px; text-align: center;">
                    <p style="color: #1F2A44; font-size: 16px; font-weight: 600; margin: 0 0 16px 0;">Keep your financial data safe</p>
                    <a href="https://fynla.org/checkout" style="display: inline-block; padding: 14px 40px; border-radius: 12px; font-size: 16px; font-weight: 700; background: #e74c6f; color: #ffffff; box-shadow: 0 4px 14px #d9a0b0;">Resubscribe now</a>
                </td></tr>

                {{-- Export note: Eggshell --}}
                <tr><td style="background: #f5f0eb; padding: 20px 36px; text-align: center;">
                    <p style="font-size: 13px; color: #999; margin: 0;">If you'd like to export your data before deletion, please contact <a href="mailto:support@fynla.org" style="color: #e74c6f; text-decoration: none;">support@fynla.org</a></p>
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
