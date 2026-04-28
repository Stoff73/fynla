@extends('emails.layouts.master', [
    'title'     => 'Your free trial has ended',
    'preheader' => "But your plan's still here — your data won't be deleted.",
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Your free trial<br/><span style="color:#f9a8c0;">has ended</span>',
        'subtitle' => "But your plan's still here.",
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:32px 36px;text-align:center;">
            <h2 style="margin:0 0 14px;font-size:32px;font-weight:900;color:#1F2A44;line-height:1.2;letter-spacing:-0.5px;">But don't worry</h2>
            <p style="margin:0 0 24px;font-size:15px;color:#1F2A44;line-height:1.7;">Your data won't be deleted &mdash; your accounts, pensions, investments and targets are all frozen and ready to resume the moment you subscribe.</p>
            <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#9a1b4a;text-transform:uppercase;letter-spacing:1.5px;">Your discount</p>
            <p style="margin:0;font-size:80px;font-weight:900;color:#e74c6f;line-height:1;letter-spacing:-2px;">{{ $discountPercent }}</p>
            <p style="margin:10px 0 22px;font-size:14px;color:#1F2A44;font-weight:700;">off your first year &mdash; if you subscribe before it expires.</p>
            <a href="{{ $reassureUrl }}" style="display:inline-block;padding:14px 44px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Subscribe now</a>
        </td>
    </tr>

    <tr>
        <td style="padding:12px 0 0;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                <tr>
                    <td width="50%" bgcolor="#1F2A44" style="background:#1F2A44;padding:24px 28px;vertical-align:middle;">
                        <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#f9a8c0;line-height:1.3;">Don't lose your plan</p>
                        <p style="margin:0;font-size:40px;font-weight:900;color:#ffffff;line-height:1;font-family:'Segoe UI',Inter,Arial,sans-serif;">100%</p>
                    </td>
                    <td width="50%" bgcolor="#dbeafe" style="background:#dbeafe;padding:24px 28px;vertical-align:middle;">
                        <p style="margin:0;font-size:14px;color:#1F2A44;line-height:1.6;">Everything you built is frozen &mdash; ready to resume the moment you subscribe.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:0;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                <tr>
                    <td width="50%" bgcolor="#dbeafe" style="background:#dbeafe;padding:24px 28px;vertical-align:middle;">
                        <p style="margin:0;font-size:14px;color:#1F2A44;line-height:1.6;">The average Fynla user claws back over &pound;3,500 in tax each year with a completed plan.</p>
                    </td>
                    <td width="50%" bgcolor="#1F2A44" style="background:#1F2A44;padding:24px 28px;vertical-align:middle;">
                        <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#f9a8c0;line-height:1.3;">Keep saving tax</p>
                        <p style="margin:0;font-size:40px;font-weight:900;color:#ffffff;line-height:1;font-family:'Segoe UI',Inter,Arial,sans-serif;">&pound;3,500/yr</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td style="padding:0;">
            <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">
                <tr>
                    <td width="50%" bgcolor="#1F2A44" style="background:#1F2A44;padding:24px 28px;vertical-align:middle;">
                        <p style="margin:0 0 10px;font-size:13px;font-weight:700;color:#f9a8c0;line-height:1.3;">Protect your estate</p>
                        <p style="margin:0;font-size:40px;font-weight:900;color:#ffffff;line-height:1;font-family:'Segoe UI',Inter,Arial,sans-serif;">&pound;70,000+</p>
                    </td>
                    <td width="50%" bgcolor="#dbeafe" style="background:#dbeafe;padding:24px 28px;vertical-align:middle;">
                        <p style="margin:0;font-size:14px;color:#1F2A44;line-height:1.6;">Households with a completed estate plan typically reduce future Inheritance Tax by this much or more.</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:32px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">Ready when you are</h3>
            <p style="margin:0 0 22px;font-size:14px;color:#1F2A44;line-height:1.6;">Subscribe before your discount expires and pick up exactly where you left off.</p>
            <a href="{{ $finalSubscribeUrl }}" style="display:inline-block;padding:14px 44px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Subscribe now</a>
            <p style="margin:12px 0 0;font-size:12px;color:#1F2A44;">Cancel anytime. UK-based support. Data encrypted end-to-end.</p>
        </td>
    </tr>
@endsection

@section('signoff')
    @include('emails.modules.signoff')
@endsection

@section('footer')
    @include('emails.modules.footer', ['variant' => 'dark'])
@endsection
