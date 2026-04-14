<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Fynla Trial is Ending Soon</title>
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
                            <h2 style="font-size: 36px; font-weight: 800; color: #ffffff; line-height: 1.15; margin: 0;">Don't lose<br/>your <span style="color: #f9a8c0;">money</span></h2>
                            <p style="font-size: 14px; color: #a8b0bf; margin: 6px 0 0 0;">Your free trial ends soon &mdash; don't lose your progress</p>
                        </td>
                        <td style="vertical-align: bottom; width: 120px;" align="right">
                            <img src="https://fynla.org/images/Fyn/Design%20Character%20001a.png" alt="Fyn" width="130" height="171" style="height: 171px; width: 130px; display: block; margin-bottom: -15px;" />
                        </td>
                    </tr></table>
                </td></tr>

                {{-- Body: Eggshell --}}
                <tr><td style="background: #f5f0eb; padding: 32px 36px;">
                    <p style="font-size: 20px; color: #1F2A44; font-weight: 700; margin: 0 0 10px 0;">Hi {{ $user->first_name ?? 'there' }},</p>
                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;">Your {{ $daysRemaining }}-day free trial of the <strong>{{ $planName }}</strong> plan ends soon. You've made great progress setting up your financial dashboard &mdash; don't let it go to waste.</p>
                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0;">Subscribe now to keep all your data, projections, and personalised recommendations.</p>
                </td></tr>

                {{-- Days remaining counter --}}
                <tr><td style="background: #fce4ec; padding: 36px 36px 20px; text-align: center;">
                    <div style="font-size: 96px; font-weight: 900; color: #e74c6f; line-height: 1; margin-bottom: 4px;">{{ $daysRemaining }}</div>
                    <div style="font-size: 18px; font-weight: 700; color: #1F2A44; text-transform: uppercase; letter-spacing: 2px;">{{ $daysRemaining === 1 ? 'day' : 'days' }} remaining</div>
                    <div style="font-size: 13px; color: #888; margin-top: 4px;">on your free trial</div>
                </td></tr>

                {{-- CTA Block: Raspberry --}}
                <tr><td style="background-color: #e74c6f; padding: 24px 36px; text-align: center;">
                    <p style="color: #ffffff; font-size: 16px; font-weight: 600; margin: 0 0 16px 0;">Keep your progress &mdash; subscribe today</p>
                    <a href="https://fynla.org/checkout" style="display: inline-block; padding: 14px 40px; border-radius: 12px; font-size: 16px; font-weight: 700; background: #ffffff; color: #e74c6f; text-decoration: none; box-shadow: 0 4px 14px #d9a0b0;">Continue your journey</a>
                </td></tr>

                {{-- Dark Features Block --}}
                <tr><td style="background-color: #0F172A; padding: 32px 36px;">
                    <h3 style="font-size: 20px; font-weight: 700; color: #ffffff; margin: 0 0 10px 0;">Check out some of our features</h3>
                    <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                        <tr>
                            <td width="50%" style="padding: 5px;" valign="top">
                                <a href="https://fynla.org/features#protection" style="display: block; background: #1F2A44; border-radius: 12px; padding: 16px; text-decoration: none; height: 110px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width: 32px; height: 32px; border-radius: 8px; background: #e74c6f; text-align: center; vertical-align: middle; font-size: 16px; color: #ffffff; font-weight: 700;">&#10003;</td></tr></table>
                                    <div style="font-size: 13px; font-weight: 600; color: #ffffff; margin-top: 8px;">Protection</div>
                                    <div style="font-size: 11px; color: #9ca4b4; margin-top: 2px; line-height: 1.4;">Life insurance and income protection coverage</div>
                                </a>
                            </td>
                            <td width="50%" style="padding: 5px;" valign="top">
                                <a href="https://fynla.org/features#savings" style="display: block; background: #1F2A44; border-radius: 12px; padding: 16px; text-decoration: none; height: 110px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width: 32px; height: 32px; border-radius: 8px; background: #22c55e; text-align: center; vertical-align: middle; font-size: 16px; color: #ffffff; font-weight: 700;">&pound;</td></tr></table>
                                    <div style="font-size: 13px; font-weight: 600; color: #ffffff; margin-top: 8px;">Savings</div>
                                    <div style="font-size: 11px; color: #9ca4b4; margin-top: 2px; line-height: 1.4;">Track emergency funds and ISA allowances</div>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td width="50%" style="padding: 5px;" valign="top">
                                <a href="https://fynla.org/features#investment" style="display: block; background: #1F2A44; border-radius: 12px; padding: 16px; text-decoration: none; height: 110px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width: 32px; height: 32px; border-radius: 8px; background: #8b5cf6; text-align: center; vertical-align: middle; font-size: 16px; color: #ffffff; font-weight: 700;">&#8599;</td></tr></table>
                                    <div style="font-size: 13px; font-weight: 600; color: #ffffff; margin-top: 8px;">Investment</div>
                                    <div style="font-size: 11px; color: #9ca4b4; margin-top: 2px; line-height: 1.4;">Portfolio analysis and risk profiling</div>
                                </a>
                            </td>
                            <td width="50%" style="padding: 5px;" valign="top">
                                <a href="https://fynla.org/features#retirement" style="display: block; background: #1F2A44; border-radius: 12px; padding: 16px; text-decoration: none; height: 110px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width: 32px; height: 32px; border-radius: 8px; background: #3b82f6; text-align: center; vertical-align: middle; font-size: 16px; color: #ffffff; font-weight: 700;">&#9200;</td></tr></table>
                                    <div style="font-size: 13px; font-weight: 600; color: #ffffff; margin-top: 8px;">Retirement</div>
                                    <div style="font-size: 11px; color: #9ca4b4; margin-top: 2px; line-height: 1.4;">Pension tracking and income projections</div>
                                </a>
                            </td>
                        </tr>
                        <tr>
                            <td width="50%" style="padding: 5px;" valign="top">
                                <a href="https://fynla.org/features#estate" style="display: block; background: #1F2A44; border-radius: 12px; padding: 16px; text-decoration: none; height: 110px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width: 32px; height: 32px; border-radius: 8px; background: #c4956a; text-align: center; vertical-align: middle; font-size: 16px; color: #ffffff; font-weight: 700;">&#9965;</td></tr></table>
                                    <div style="font-size: 13px; font-weight: 600; color: #ffffff; margin-top: 8px;">Estate</div>
                                    <div style="font-size: 11px; color: #9ca4b4; margin-top: 2px; line-height: 1.4;">Inheritance Tax and gifting strategies</div>
                                </a>
                            </td>
                            <td width="50%" style="padding: 5px;" valign="top">
                                <a href="https://fynla.org/features#networth" style="display: block; background: #1F2A44; border-radius: 12px; padding: 16px; text-decoration: none; height: 110px;">
                                    <table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="width: 32px; height: 32px; border-radius: 8px; background: #64748b; text-align: center; vertical-align: middle; font-size: 16px; color: #ffffff; font-weight: 700;">&#9776;</td></tr></table>
                                    <div style="font-size: 13px; font-weight: 600; color: #ffffff; margin-top: 8px;">Net Worth</div>
                                    <div style="font-size: 11px; color: #9ca4b4; margin-top: 2px; line-height: 1.4;">Properties, assets, and liabilities tracking</div>
                                </a>
                            </td>
                        </tr>
                    </table>
                </td></tr>

                {{-- Don't lose your data: Eggshell --}}
                <tr><td style="background: #f5f0eb; padding: 32px 36px;">
                    <h3 style="font-size: 20px; font-weight: 700; color: #1F2A44; margin: 0 0 10px 0;">Don't lose your data</h3>
                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0 0 14px 0;">Subscribe to a Fynla plan before your trial ends to keep all your financial information safe. After your trial expires, your data will be retained for 30 days before being permanently deleted.</p>
                    <p style="font-size: 14px; color: #555; line-height: 1.7; margin: 0;">Plans start from just &pound;3.99/month with our launch pricing. Lock in this rate before it increases.</p>
                </td></tr>

                {{-- CTA Block: Light Pink --}}
                <tr><td style="background: #fce4ec; padding: 28px 36px; text-align: center;">
                    <p style="color: #1F2A44; font-size: 16px; font-weight: 600; margin: 0 0 16px 0;">Choose a plan that works for you</p>
                    <a href="https://fynla.org/checkout" style="display: inline-block; padding: 14px 40px; border-radius: 12px; font-size: 16px; font-weight: 700; background: #e74c6f; color: #ffffff; box-shadow: 0 4px 14px #d9a0b0;">Choose a plan</a>
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
