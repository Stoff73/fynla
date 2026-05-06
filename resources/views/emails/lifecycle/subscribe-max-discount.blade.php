@extends('emails.layouts.master', [
    'title'     => 'Your trial is ending',
    'preheader' => "Lock in your 20% before it's gone.",
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Your trial<br/><span style="color:#f9a8c0;">is ending</span>',
        'subtitle' => "Lock in your 20% before it's gone.",
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:32px 36px 28px;text-align:center;">
            <p style="margin:0;font-size:96px;font-weight:900;color:#e74c6f;line-height:0.9;letter-spacing:-2px;font-family:'Segoe UI',Inter,Arial,sans-serif;">{{ $daysRemaining }}</p>
            <p style="margin:8px 0 0;font-size:18px;font-weight:700;color:#1F2A44;text-transform:uppercase;letter-spacing:1px;">{{ $daysRemaining === 1 ? 'Day remaining' : 'Days remaining' }}</p>
            <p style="margin:6px 0 22px;font-size:13px;color:#555;">Your free trial ends on {{ $trialEndDate }}.</p>
            <a href="{{ $countdownUrl }}" style="display:inline-block;padding:14px 44px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Subscribe now</a>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:32px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">You've unlocked the maximum discount</h3>
            <p style="margin:0 0 22px;font-size:14px;color:#1F2A44;line-height:1.6;">You finished your journey &mdash; here's the biggest reward we offer.</p>
            <p style="margin:0 0 4px;font-size:12px;font-weight:700;color:#9a1b4a;text-transform:uppercase;letter-spacing:1.5px;">Your discount</p>
            <p style="margin:0;font-size:80px;font-weight:900;color:#e74c6f;line-height:1;letter-spacing:-2px;">20%</p>
            <p style="margin:10px 0 22px;font-size:14px;color:#1F2A44;font-weight:700;">off your first year &mdash; applied automatically at checkout.</p>
            <a href="{{ $discountUrl }}" style="display:inline-block;padding:14px 44px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Subscribe now</a>
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
