@extends('emails.layouts.master', [
    'title'     => "Time's running out",
    'preheader' => 'Your discount expires with your trial.',
])

@section('logoBar')
    @include('emails.modules.logo-bar')
@endsection

@section('header')
    @include('emails.modules.hero-header', [
        'heading'  => 'Time\'s<br/><span style="color:#f9a8c0;">running out</span>',
        'subtitle' => 'Your discount expires with your trial.',
    ])
@endsection

@section('body')
    <tr>
        <td style="background:#f5f0eb;padding:32px 36px 20px;text-align:center;">
            <h2 style="margin:0 0 14px;font-size:32px;font-weight:900;color:#1F2A44;line-height:1.2;letter-spacing:-0.5px;">Don't lose your data</h2>
            <p style="margin:0;font-size:15px;color:#1F2A44;line-height:1.7;">Your trial is about to end. If you don't subscribe in time, your plan will be put on hold &mdash; your data stays safe, but you'll lose access to projections, recommendations and Fyn.</p>
        </td>
    </tr>

    <tr>
        <td style="background:#f5f0eb;padding:8px 36px 32px;text-align:center;">
            <p style="margin:0;font-size:96px;font-weight:900;color:#e74c6f;line-height:0.9;letter-spacing:-2px;font-family:'Segoe UI',Inter,Arial,sans-serif;">{{ $daysRemaining }}</p>
            <p style="margin:8px 0 0;font-size:18px;font-weight:700;color:#1F2A44;text-transform:uppercase;letter-spacing:1px;">{{ $daysRemaining === 1 ? 'Day remaining' : 'Days remaining' }}</p>
            <p style="margin:6px 0 0;font-size:13px;color:#555;">Your free trial ends on {{ $trialEndDate }}.</p>
        </td>
    </tr>

    <tr>
        <td bgcolor="#fce4ec" style="background:#fce4ec;padding:32px 36px;text-align:center;">
            <h3 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#1F2A44;">Your discount code</h3>
            <p style="margin:0 0 18px;font-size:14px;color:#1F2A44;line-height:1.6;">Use this code at checkout to claim <strong>{{ $discountPercent }} off</strong> your first year.</p>
            <div style="background:#1F2A44;border-radius:12px;padding:20px 24px;margin:0 0 22px;display:inline-block;min-width:240px;">
                <p style="margin:0 0 6px;font-size:12px;color:#8f97a8;text-transform:uppercase;letter-spacing:1px;font-family:'Segoe UI',Inter,Arial,sans-serif;">Discount code</p>
                <p style="margin:0;font-size:30px;font-weight:900;color:#ffffff;letter-spacing:6px;font-family:'Courier New',monospace;">{{ $discountCode }}</p>
            </div>
            <div>
                <a href="{{ $subscribeUrl }}" style="display:inline-block;padding:14px 44px;background:#e74c6f;color:#ffffff;font-size:16px;font-weight:700;border-radius:12px;text-decoration:none;box-shadow:0 4px 14px #d9a0b0;">Subscribe now</a>
            </div>
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
